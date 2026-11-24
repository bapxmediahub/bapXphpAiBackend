'use strict';

const assert = require('node:assert/strict');
const {execFileSync, spawn} = require('node:child_process');
const {once} = require('node:events');
const {mkdtemp, rm} = require('node:fs/promises');
const {createServer} = require('node:http');
const net = require('node:net');
const os = require('node:os');
const path = require('node:path');
const {test} = require('node:test');
const {chromium} = require('playwright');

test('Users keeps secondary filters collapsed and preserves them through native navigation', {timeout: 45000}, async t => {
    const root = path.resolve(__dirname, '..');
    const sessions = await mkdtemp(path.join(os.tmpdir(), 'bapx-users-'));
    const sessionId = 'users-directory-admin-fixture';

    const fixtures = {
        users: Array.from({length: 13}, (_, index) => {
            const id = String(index + 1).padStart(2, '0');
            return {id, name: 'Fixture Account ' + id, email: 'account-' + id + '@example.test', role: 'customer'};
        }),
        orders: [{id:'FIXTURE-ORDER', customer_email:'account-01@example.test', created_at: new Date().toISOString(),
            status:'delivered', total:100, invoice_number:'FIXTURE-INVOICE'}],
    };
    const bridge = createServer(async (request, response) => {
        const chunks = [];
        for await (const chunk of request) chunks.push(chunk);
        const {query, password} = JSON.parse(Buffer.concat(chunks));
        if (password !== 'directory-fixture' || typeof query !== 'string' || !query.startsWith('SELECT ')) {
            response.writeHead(403).end();
            return;
        }
        const rows = fixtures[/\bFROM (\w+)/i.exec(query)?.[1]] ?? [];
        response.setHeader('Content-Type', 'application/json');
        response.end(JSON.stringify({success:true, data:rows.map(row => ({id:row.id, _data:JSON.stringify(row)}))}));
    }).listen(0, '127.0.0.1');
    await once(bridge, 'listening');
    const socket = net.createServer().listen(0, '127.0.0.1');
    await once(socket, 'listening');
    const port = socket.address().port;
    await new Promise(resolve => socket.close(resolve));
    const origin = 'http://127.0.0.1:' + port;
    const env = {...process.env, BAPX_TEST_MODE:'0', APP_URL:origin,
        BAPX_MYSQL_HOST:'127.0.0.1', BAPX_MYSQL_PORT:'1',
        BAPX_MYSQL_DB:'fixture', BAPX_MYSQL_USER:'fixture', BAPX_MYSQL_PASS:'directory-fixture',
        BAPX_REMOTE_DB_URL:'http://localhost:' + bridge.address().port + '/remotedb'};
    const options = ['-d', 'session.save_path=' + sessions];
    let browser;
    let server;
    t.after(async () => {
        await browser?.close();
        server?.kill();
        await new Promise(resolve => bridge.close(resolve));
        await rm(sessions, {recursive:true,force:true});
    });
    execFileSync('php', [...options, '-r',
        'session_id($argv[1]); session_start(); $_SESSION["user"]=["id"=>"fixture","role"=>"admin"]; session_write_close();',
        sessionId], {cwd:root,env});
    server = spawn('php', [...options, '-S', '127.0.0.1:' + port, 'index.php'], {cwd:root,env,stdio:'ignore'});
    browser = await chromium.launch({executablePath:process.env.CHROMIUM_PATH || undefined});
    const context = await browser.newContext({javaScriptEnabled:false, serviceWorkers:'block'});
    context.setDefaultTimeout(6000);
    await context.route('https://**/*', route => route.abort());
    const page = await context.newPage();
    const url = origin + '/admin/users';
    await page.goto(url);
    assert.equal(new URL(page.url()).pathname, '/login');
    await context.addCookies([{name:'PHPSESSID', value:sessionId, url:origin}]);

    const search = page.getByRole('searchbox', {name:'Search accounts'});
    const optionsToggle = page.locator('.users-filter-options > summary');
    const role = page.getByLabel('Role', {exact:true});
    const rows = page.locator('.users-entry');
    for (const width of [1440, 375, 320]) {
        await page.setViewportSize({width,height:760});
        const response = await page.goto(url);
        assert.match(response.headers()['cache-control'], /private, no-store/);
        assert.equal(await rows.count(), 13);
        assert.ok(await search.isVisible());
        assert.ok(!await role.isVisible());
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        assert.ok((await page.locator('.users-filters').boundingBox()).height < 165);
        assert.ok(await page.locator('.admin-topbar').getByRole('link', {name:'Help & Docs', exact:true}).isVisible());
        const topbar = await page.locator('.admin-topbar__actions a').evaluateAll(actions => actions.map(action => {
            const icon = action.querySelector('svg[aria-hidden=true]');
            return {label:action.textContent.trim(), iconWidth:icon?.getBoundingClientRect().width ?? 0,
                scrollWidth:action.scrollWidth, clientWidth:action.clientWidth};
        }));
        assert.equal(topbar.length, 3);
        for (const action of topbar) {
            assert.ok(action.iconWidth >= 14 && action.scrollWidth <= action.clientWidth + 1,
                'Top-bar icon and label fit at ' + width + 'px ' + JSON.stringify(action));
        }
        if (width === 375) {
            const y = (await rows.first().boundingBox()).y;
            assert.ok(y < 760, 'First account starts inside the initial viewport at y=' + y);
        }
        await optionsToggle.press('Space');
        assert.ok(await role.isVisible());
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    }
    await role.selectOption('customer');
    await page.getByLabel('Per page').selectOption('10');
    await page.getByRole('button', {name:'Apply filters'}).press('Enter');
    assert.match(await optionsToggle.innerText(), /2 active/);
    assert.ok(!await role.isVisible());
    assert.equal(await rows.count(), 10);
    await page.getByRole('link', {name:'Next',exact:true}).press('Enter');
    const params = new URL(page.url()).searchParams;
    assert.equal(params.get('role'), 'customer');
    assert.equal(params.get('per_page'), '10');
    assert.equal(await rows.count(), 3);
    await search.fill('account-01@example.test');
    await search.press('Enter');
    assert.equal(new URL(page.url()).searchParams.get('role'), 'customer');
    assert.equal(await rows.count(), 1);
    await rows.locator('summary').press('Space');
    await page.getByRole('link', {name:'FIXTURE-INVOICE'}).waitFor();
    await page.getByRole('link', {name:'Reset filters'}).press('Enter');
    assert.equal(page.url(), url);
    assert.equal(await rows.count(), 13);

    const pointer = await browser.newContext({viewport:{width:375,height:760}, serviceWorkers:'block'});
    pointer.setDefaultTimeout(6000);
    await pointer.route('https://**/*', route => route.abort());
    await pointer.addCookies([{name:'PHPSESSID', value:sessionId, url:origin}]);
    const mobile = await pointer.newPage();
    await mobile.goto(url);
    await mobile.locator('.users-filter-options > summary').click();
    await mobile.getByLabel('Order activity').selectOption('one_order');
    await mobile.getByLabel('Latest order').selectOption('30');
    await mobile.getByRole('button', {name:'Apply filters'}).click();
    assert.equal(await mobile.locator('.users-entry').count(), 1);
    assert.match(await mobile.locator('.users-result-count').innerText(), /of 1 account/);
    await mobile.getByRole('link', {name:'Remove latest order filter: Last 30 days'}).click();
    const retained = new URL(mobile.url()).searchParams;
    assert.equal(retained.get('activity'), 'one_order');
    assert.ok(!retained.has('period'));
    assert.ok(await mobile.getByRole('link', {name:'Remove order activity filter: One order'}).isVisible());
    assert.equal(await mobile.locator('.users-entry').count(), 1);

    await page.goto(url + '?role=customer&sort=order_count&per_page=10&page=2');
    await page.getByRole('link', {name:'Remove sort filter: Most orders'}).press('Enter');
    const updated = new URL(page.url()).searchParams;
    assert.equal(updated.get('role'), 'customer');
    assert.equal(updated.get('per_page'), '10');
    assert.equal(updated.get('sort'), 'name');
    assert.ok(!updated.has('page'));
    assert.equal(await rows.count(), 10);

    await page.goto(url + '?q=' + 'x'.repeat(150));
    assert.ok(await page.getByRole('link', {name:/Remove search filter/}).isVisible());
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    await page.getByRole('link', {name:/Remove search filter/}).press('Enter');
    assert.ok(!new URL(page.url()).searchParams.has('q'));
    assert.equal(await rows.count(), 13);
});
