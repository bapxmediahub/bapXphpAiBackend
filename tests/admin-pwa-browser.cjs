const assert = require('node:assert/strict');
const {spawn, execFileSync} = require('node:child_process');
const {once} = require('node:events');
const {mkdtemp, rm} = require('node:fs/promises');
const net = require('node:net');
const os = require('node:os');
const path = require('node:path');
const {test} = require('node:test');
const {chromium} = require('playwright');

test('installed admin launches inside its own scope and remains private offline', async () => {
    const root = path.resolve(__dirname, '..');
    const temp = await mkdtemp(path.join(os.tmpdir(), 'bapx-admin-pwa-'));
    const socket = net.createServer();
    socket.listen(0, '127.0.0.1');
    await once(socket, 'listening');
    const port = socket.address().port;
    await new Promise(resolve => socket.close(resolve));
    const origin = 'http://127.0.0.1:' + port;
    const env = {...process.env, BAPX_TEST_MODE: '1', APP_URL: origin};
    const session = 'adminpwabrowserfixture';
    const options = ['-d', 'session.save_path=' + temp];
    execFileSync('php', [...options, '-r', 'session_id("adminpwabrowserfixture"); session_start(); $_SESSION["user"]=["id"=>"fixture","role"=>"admin"]; session_write_close();'], {cwd:root,env});
    const server = spawn('php', [...options,'-S','127.0.0.1:'+port,'index.php'], {cwd:root,env,stdio:'ignore'});
    let context;
    try {
        context = await chromium.launchPersistentContext(path.join(temp,'chromium'), {
            executablePath: process.env.CHROMIUM_PATH || undefined, headless:true,
        });
        await context.addCookies([{name:'PHPSESSID',value:session,url:origin}]);
        const page = await context.newPage();
        await page.route('https://**/*', route => route.abort());
        await page.goto(origin+'/');
        await page.evaluate(() => navigator.serviceWorker.ready);
        await page.goto(origin+'/admin/google-reporting');
        const callback = page.locator('.google-reporting__callback');
        const hostedCallback = 'https://sripanchamispiritual.com/admin/google-reporting/callback';
        await callback.evaluate((el, value) => { el.textContent = value; }, hostedCallback);
        for (const width of [320, 375]) {
            await page.setViewportSize({width, height:800});
            assert.equal(await callback.innerText(), hostedCallback);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth), width);
        }
        await page.setViewportSize({width:1280, height:800});
        await page.goto(origin+'/admin?period=7');
        assert.equal(page.url(), origin+'/admin/?period=7');
        await page.waitForFunction(() => navigator.serviceWorker.controller?.scriptURL.endsWith('/admin/sw.js'));
        assert.equal(await page.getByRole('link',{name:'Last 7 days'}).getAttribute('aria-current'), 'page');
        const manifest = await page.evaluate(async () => {
            return (await fetch(document.querySelector('link[rel=manifest]').href)).json();
        });
        assert.equal(manifest.id, '/admin');
        assert.equal(manifest.start_url, '/admin/');
        assert.equal(manifest.scope, '/admin/');
        const cdp = await context.newCDPSession(page);
        const manifestId = origin+manifest.id;
        assert.deepEqual((await cdp.send('Page.getInstallabilityErrors')).installabilityErrors, []);
        await cdp.send('PWA.install', {manifestId,installUrlOrBundleUrl:origin+manifest.start_url});

        // Stop the HTTP origin before launching a new installed window. No open-tab emulation.
        const exited = once(server, 'exit');
        server.kill();
        await exited;
        const launched = context.waitForEvent('page');
        const {targetId} = await cdp.send('PWA.launch', {manifestId});
        const app = await launched;
        await app.waitForURL(origin+manifest.start_url);
        await app.getByText('Connect to the internet to open the admin workspace.').waitFor();
        assert.equal(await app.evaluate(() => navigator.serviceWorker.controller?.scriptURL), origin+'/admin/sw.js');
        const appSession = await context.newCDPSession(app);
        // PWA.launch returns the tab target. Playwright attaches to the page in that window.
        const launchTarget = (await cdp.send('Target.getTargetInfo', {targetId})).targetInfo;
        assert.equal(launchTarget.url, app.url());
        const launchWindow = await cdp.send('Browser.getWindowForTarget', {targetId});
        const pageWindow = await appSession.send('Browser.getWindowForTarget');
        assert.equal(pageWindow.windowId, launchWindow.windowId);
        assert.equal(await app.getByRole('heading',{name:'Commerce overview'}).count(), 0);

        // Older installations used /admin, which falls under the public root worker.
        const legacy = await context.newPage();
        const response = await legacy.goto(origin+'/admin');
        assert.equal(response.status(),503);
        await legacy.getByText('Connect to the internet to open the admin workspace.').waitFor();
        assert.equal(await legacy.evaluate(() => navigator.serviceWorker.controller?.scriptURL), origin+'/sw.js');
        assert.equal(await legacy.evaluate(async () => (await caches.match('/admin')) !== undefined), false);
    } finally {
        server.kill();
        await context?.close();
        await rm(temp, {recursive:true,force:true});
    }
});
