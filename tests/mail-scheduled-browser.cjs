'use strict';

const assert = require('node:assert/strict');
const {execFileSync, spawn} = require('node:child_process');
const {randomBytes} = require('node:crypto');
const {once} = require('node:events');
const {mkdtemp, rm, writeFile} = require('node:fs/promises');
const {createServer} = require('node:http');
const net = require('node:net');
const os = require('node:os');
const path = require('node:path');
const {test} = require('node:test');
const {chromium} = require('playwright');

test('owner schedules one synthetic sample per confirmation in the actual Email Templates UI', {timeout:45000}, async t => {
    const root = path.resolve(__dirname, '..');
    const fixture = await mkdtemp(path.join(os.tmpdir(), 'bapx-mail-scheduled-'));
    const router = path.join(fixture, 'router.php');
    const queue = new Map();
    const outbox = new Map();
    const password = 'synthetic-scheduled-mail-fixture';
    const profile = {id:'app_settings', site_display_name:'Scheduled Mail Fixture'};
    let claims = 0;
    let browser;
    let server;
    let serverClosed;
    const bridge = createServer(async (request, response) => {
        const chunks = [];
        for await (const chunk of request) chunks.push(chunk);
        const input = JSON.parse(Buffer.concat(chunks));
        response.setHeader('Content-Type', 'application/json');
        if (input.password !== password) {
            response.writeHead(403).end('{}');
            return;
        }
        const respond = data => response.end(JSON.stringify({success:true, ...data}));
        if (typeof input.query === 'string') {
            const table = /\bFROM (\w+)/i.exec(input.query)?.[1];
            const records = ({settings:[profile], mail_queue:[...queue.values()], mail_outbox:[...outbox.values()]})[table] ?? [];
            const matching = input.params?.length ? records.filter(row => row.id === input.params[0]) : records;
            return respond({data:matching.map(row => ({id:row.id, _data:JSON.stringify(row)}))});
        }
        if (input.collection === 'mail_queue' && input.action === 'insert_if_absent') {
            const {id} = input.record;
            const inserted = !queue.has(id);
            if (inserted) queue.set(id, input.record);
            return respond({inserted, record:queue.get(id)});
        }
        if (input.collection === 'mail_queue' && input.action === 'transition_mail_delivery') {
            claims++;
            response.writeHead(409).end('{}');
            return;
        }
        if (input.collection === 'mail_outbox' && input.action === 'upsert') {
            outbox.set(input.record.id, input.record);
            return respond({record:input.record});
        }
        response.writeHead(400).end('{}');
    });
    t.after(async () => {
        try {
            await browser?.close();
        } finally {
            if (server) {
                server.kill();
                await serverClosed;
            }
            if (bridge.listening) {
                const closed = new Promise(resolve => bridge.close(resolve));
                bridge.closeAllConnections();
                await closed;
            }
            await rm(fixture, {recursive:true, force:true});
        }
    });

    bridge.listen(0, '127.0.0.1');
    await once(bridge, 'listening');
    const socket = net.createServer().listen(0, '127.0.0.1');
    await once(socket, 'listening');
    const port = socket.address().port;
    await new Promise(resolve => socket.close(resolve));
    const origin = 'http://127.0.0.1:' + port;
    const sessionId = 'scheduled-mail-browser-fixture';
    const clientIp = '2001:db8::' + randomBytes(8).toString('hex').match(/.{4}/g).join(':');
    await writeFile(router, "<?php $_SERVER['REMOTE_ADDR'] = getenv('SPS_MAIL_FIXTURE_IP'); return require getenv('SPS_MAIL_FIXTURE_INDEX');");
    const env = {...process.env, BAPX_TEST_MODE:'0', APP_URL:origin,
        BAPX_MYSQL_HOST:'127.0.0.1', BAPX_MYSQL_PORT:'1', BAPX_MYSQL_DB:'fixture',
        BAPX_MYSQL_USER:'fixture', BAPX_MYSQL_PASS:password,
        BAPX_REMOTE_DB_URL:'http://localhost:' + bridge.address().port + '/remotedb',
        SPS_MAIL_FIXTURE_INDEX:path.join(root, 'index.php'), SPS_MAIL_FIXTURE_IP:clientIp};
    const options = ['-d', 'session.save_path=' + fixture];
    execFileSync('php', [...options, '-r',
        'session_id($argv[1]); session_start(); $_SESSION["user"]=["id"=>"fixture","role"=>"admin"]; session_write_close();',
        sessionId], {cwd:root,env});
    server = spawn('php', [...options, '-S', '127.0.0.1:' + port, router], {cwd:root,env,stdio:'ignore'});
    serverClosed = new Promise(resolve => server.once('close', resolve));
    browser = await chromium.launch({executablePath:process.env.CHROMIUM_PATH || undefined});
    const context = await browser.newContext({javaScriptEnabled:false, serviceWorkers:'block', reducedMotion:'reduce'});
    context.setDefaultTimeout(6000);
    await context.route('https://**/*', route => route.abort());
    const editor = await context.newPage();
    const templateUrl = origin + '/admin/email-templates?template=welcome';
    const postUrl = origin + '/admin/email-templates/test';
    await editor.goto(templateUrl);
    assert.equal(new URL(editor.url()).pathname, '/login');
    await context.addCookies([{name:'PHPSESSID', value:sessionId, url:origin}]);

    for (const width of [1280, 375]) {
        await editor.setViewportSize({width,height:760});
        await editor.goto(templateUrl);
        const form = editor.locator('[data-mail-template-test]');
        const timing = form.getByLabel('Delivery timing');
        assert.equal(await timing.inputValue(), 'now');
        await timing.selectOption('scheduled');
        const recipient = 'fixture-' + width + '@example.invalid';
        await form.getByLabel('Customer test email').fill(recipient);
        const consent = form.getByRole('checkbox');
        await consent.press('Space');
        assert.ok(await consent.isChecked());
        const payload = await form.evaluate(form => Object.fromEntries(new FormData(form)));
        const count = queue.size;
        if (width === 1280) {
            await context.request.post(postUrl, {form:{...payload, _csrf:'invalid'}, maxRedirects:0});
            assert.equal(queue.size, count);
        }

        const confirm = form.getByRole('button', {name:'Confirm test sample'});
        if (width === 1280) await confirm.click();
        else await confirm.press('Enter');
        const result = editor.locator('[data-template-test-result]');
        await result.getByText('Scheduled sample queued.', {exact:true}).waitFor();
        assert.equal(await result.getAttribute('data-send-status'), 'pending');
        assert.equal(queue.size, count + 1);
        const saved = queue.get(await result.getAttribute('data-queue-id'));
        assert.equal(saved.type, 'scheduled_template_test');
        assert.equal(saved.status, 'pending');
        assert.equal(saved.to, recipient);
        assert.deepEqual(saved.meta, {template:'welcome', synthetic_sample:true});
        assert.equal(await result.locator('time').getAttribute('datetime'), saved.available_at);
        assert.ok(Date.parse(saved.available_at) > Date.now() + 60000);
        assert.equal(outbox.get('out_' + saved.id).status, 'pending');
        assert.ok(await editor.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        await result.getByRole('link', {name:'outbox evidence'}).click();
        await editor.getByText('The sample-only worker has ' + queue.size + ' pending test samples.', {exact:false}).waitFor();

        if (width === 375) {
            await context.request.post(postUrl, {form:payload, maxRedirects:0});
            assert.equal(queue.size, count + 1, 'Replaying the consumed confirmation creates no second row');
        }
    }
    assert.equal(claims, 0, 'Enqueue and admin preview never enter SMTP claim');
});
