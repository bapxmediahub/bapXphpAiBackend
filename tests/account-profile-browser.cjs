'use strict';

const assert = require('node:assert/strict');
const {execFileSync, spawn} = require('node:child_process');
const {once} = require('node:events');
const {mkdtemp, rm} = require('node:fs/promises');
const {createServer} = require('node:http');
const {createServer: reservePort} = require('node:net');
const os = require('node:os');
const path = require('node:path');
const {test} = require('node:test');
const {chromium} = require('playwright');

test('Profile keeps its avatar and form usable as a saved photo loads or fails', {timeout: 30000}, async t => {
    const root = path.resolve(__dirname, '..');
    const sessions = await mkdtemp(path.join(os.tmpdir(), 'sps-profile-'));
    const sessionId = 'profile-customer-fixture';
    const photoUrl = 'https://lh3.googleusercontent.com/profile-fixture';
    const account = {id:'fixture', name:'Fixture Customer', email:'fixture'.repeat(12) + '@example.test', role:'customer'};
    const bridge = createServer(async (request, response) => {
        const chunks = [];
        for await (const chunk of request) chunks.push(chunk);
        const {query, password, params} = JSON.parse(Buffer.concat(chunks));
        if (password !== 'profile-fixture' || typeof query !== 'string' || !query.startsWith('SELECT ')) {
            response.writeHead(403).end();
            return;
        }
        const data = query === 'SELECT * FROM users WHERE id = ?' && params[0] === account.id
            ? [{id:account.id, _data:JSON.stringify(account)}] : [];
        response.writeHead(200, {'Content-Type':'application/json'}).end(JSON.stringify({success:true,data}));
    }).listen(0, '127.0.0.1');
    await once(bridge, 'listening');
    const reservation = reservePort().listen(0, '127.0.0.1');
    await once(reservation, 'listening');
    const port = reservation.address().port;
    await new Promise(resolve => reservation.close(resolve));
    const origin = 'http://127.0.0.1:' + port;
    const profile = origin + '/account/dashboard/profile';
    const env = {...process.env, BAPX_TEST_MODE:'0', APP_URL:origin,
        BAPX_MYSQL_HOST:'127.0.0.1', BAPX_MYSQL_PORT:'1',
        BAPX_MYSQL_DB:'fixture', BAPX_MYSQL_USER:'fixture', BAPX_MYSQL_PASS:'profile-fixture',
        BAPX_REMOTE_DB_URL:'http://localhost:' + bridge.address().port + '/remotedb'};
    const phpOptions = ['-d', 'session.save_path=' + sessions];
    let browser;
    let server;
    t.after(async () => {
        await browser?.close();
        server?.kill();
        await new Promise(resolve => bridge.close(resolve));
        await rm(sessions, {recursive:true,force:true});
    });
    execFileSync('php', [...phpOptions, '-r',
        'session_id($argv[1]); session_start(); $_SESSION["user"]=["sub"=>"fixture","email"=>$argv[2],"role"=>"customer"]; session_write_close();',
        sessionId, account.email], {cwd:root,env});
    server = spawn('php', [...phpOptions, '-S', '127.0.0.1:' + port, 'index.php'], {cwd:root,env,stdio:'ignore'});
    browser = await chromium.launch({executablePath:process.env.CHROMIUM_PATH || undefined});
    const context = await browser.newContext({serviceWorkers:'block'});
    context.setDefaultTimeout(5000);
    await context.route('https://**/*', route => route.abort());
    const page = await context.newPage();
    await page.goto(profile);
    assert.equal(new URL(page.url()).pathname, '/login');
    await context.addCookies([{name:'PHPSESSID',value:sessionId,url:origin}]);

    const identity = page.locator('.account-profile__identity');
    const avatar = page.locator('.account-profile__avatar');
    const photo = avatar.locator('img');
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aQSUAAAAASUVORK5CYII=', 'base64');
    for (const width of [1440,375]) {
        await page.setViewportSize({width,height:760});
        account.picture = '';
        await page.goto(profile);
        assert.equal(await photo.count(),0);
        assert.equal((await avatar.innerText()).trim(),'F');
        assert.ok(await identity.getByRole('heading',{name:'My Profile'}).isVisible());
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));

        account.picture = photoUrl;
        let allowPhoto;
        const pending = new Promise(resolve => { allowPhoto = resolve; });
        await context.route(photoUrl, async route => {
            await pending;
            await route.fulfill({contentType:'image/png',body:png});
        });
        await page.reload({waitUntil:'domcontentloaded'});
        const boxBefore = await avatar.boundingBox();
        try {
            assert.ok(!await photo.isVisible());
            assert.equal((await avatar.innerText()).trim(),'F');
        } finally {
            allowPhoto();
        }
        await photo.waitFor();
        assert.ok(await photo.evaluate(image => image.naturalWidth > 0));
        assert.equal(await photo.getAttribute('referrerpolicy'),'no-referrer');
        assert.deepEqual(await avatar.boundingBox(),boxBefore);
        await context.unroute(photoUrl);
        await context.route(photoUrl, route => route.fulfill({status:404,body:''}));
        await page.reload();
        assert.ok(await photo.evaluate(image => image.complete && image.naturalWidth === 0 && image.hidden));
        assert.ok(!await photo.isVisible());
        assert.equal((await avatar.innerText()).trim(),'F');
        assert.ok(await page.getByRole('button',{name:'Save profile',exact:true}).isVisible());
        await context.unroute(photoUrl);
    }

    const native = await browser.newContext({javaScriptEnabled:false, viewport:{width:375,height:760},serviceWorkers:'block'});
    await native.addCookies([{name:'PHPSESSID',value:sessionId,url:origin}]);
    await native.route('https://**/*', route => route.abort());
    const fallback = await native.newPage();
    await fallback.goto(profile);
    assert.ok(await fallback.locator('.account-profile__avatar').isVisible());
    assert.equal(await fallback.locator('.account-profile__avatar img').count(),1);
    assert.ok(!await fallback.locator('.account-profile__avatar img').isVisible());
    assert.ok(await fallback.getByLabel('Name',{exact:true}).isVisible());
});
