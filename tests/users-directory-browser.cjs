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
        orders: [
            {id:'FIXTURE-ORDER', customer_email:'account-01@example.test', created_at:new Date().toISOString(),
                status:'delivered', total:100, invoice_number:'FIXTURE-INVOICE'},
            {id:'OLDER-ORDER', customer_email:'account-01@example.test', created_at:'2020-01-01T00:00:00Z', status:'pending'},
            {id:'UNDATED-ORDER', customer_email:'account-02@example.test', status:'delivered'},
        ],
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
    const reference = page.getByLabel('Order or invoice reference');
    const rows = page.locator('.users-entry');
    for (const width of [1440, 375, 320]) {
        await page.setViewportSize({width,height:760});
        const response = await page.goto(url);
        assert.match(response.headers()['cache-control'], /private, no-store/);
        assert.equal(await rows.count(), 13);
        assert.ok(await search.isVisible());
        assert.ok(!await role.isVisible());
        assert.ok(!await reference.isVisible());
        assert.ok((await page.getByRole('button', {name:'Search',exact:true}).locator('svg[aria-hidden=true]').boundingBox()).width >= 14);
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
        for (const action of ['.users-workspace .report-head .btn', '.admin-breadcrumb__home']) {
            assert.ok((await page.locator(action + ' svg[aria-hidden=true]').boundingBox()).width >= 14);
        }
        if (width === 375) {
            const first = await rows.first().locator('summary').boundingBox();
            assert.ok(first.y + first.height <= 760, 'First account fits in the initial viewport at y=' + first.y);
            assert.ok((await search.boundingBox()).width >= 150, 'Name or email fits inside the mobile search field');
        }
        assert.equal(await page.getByRole('link', {name:'Users guide'}).getAttribute('href'), '/admin/docs/customers');
        assert.equal(await rows.first().locator('.users-latest-status').innerText(), 'Delivered');
        assert.match(await rows.nth(1).locator('.users-latest').innerText(), /Order date unavailable/);
        await optionsToggle.press('Space');
        assert.ok(await role.isVisible());
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    }
    await page.getByRole('link', {name:'Menu', exact:true}).press('Enter');
    const fallbackNavigation = page.getByRole('navigation', {name:'Owner workspace'});
    assert.ok(await fallbackNavigation.isVisible(), 'Owner menu works without JavaScript');
    await page.getByRole('link', {name:'Close navigation', exact:true}).press('Enter');
    assert.ok(!await fallbackNavigation.isVisible(), 'Closing the native owner menu returns to content');

    await role.selectOption('customer');
    await page.getByLabel('Per page').selectOption('10');
    await page.getByRole('button', {name:'Apply filters'}).press('Enter');
    assert.match(await optionsToggle.innerText(), /2 active/);
    assert.ok(!await role.isVisible());
    assert.equal(await rows.count(), 10);
    await page.getByRole('link', {name:'Next',exact:true}).press('Enter');
    assert.ok(await page.getByRole('link', {name:'Previous',exact:true}).locator('svg[aria-hidden=true]').isVisible());
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
    const navigation = mobile.getByRole('button', {name:'Open navigation'});
    await navigation.press('Enter');
    const sidebar = mobile.locator('#admin-sidebar');
    assert.equal(await sidebar.getByRole('link', {name:'Users', exact:true}).getAttribute('aria-current'), 'page');
    for (const menu of await sidebar.locator('.admin-nav-toggle').all()) {
        assert.equal(await menu.evaluate(summary => summary.closest('details').open), false);
        await menu.press('Space');
        assert.equal(await menu.evaluate(summary => summary.closest('details').open), true);
        const links = menu.locator('xpath=following-sibling::*').getByRole('link');
        assert.ok(await links.first().isVisible(), 'Opening the native group exposes its destinations');
    }
    await sidebar.locator('.admin-agent-nav summary').press('Space');
    assert.ok(await sidebar.getByRole('link', {name:'Products', exact:true}).isVisible());
    const navIcons = await sidebar.locator('nav :is(a, button, summary)').evaluateAll(items =>
        items.filter(item => item.getClientRects().length > 0).map(item => ({
            label:item.textContent.trim(), width:item.querySelector('svg')?.getBoundingClientRect().width ?? 0
        }))
    );
    assert.ok(navIcons.length > 10);
    assert.ok(navIcons.every(icon => icon.width >= 14), JSON.stringify(navIcons));
    await mobile.keyboard.press('Escape');
    assert.equal(await navigation.getAttribute('aria-expanded'), 'false');
    assert.ok(await navigation.evaluate(button => button === document.activeElement));

    await mobile.locator('.users-filter-options > summary').click();
    await mobile.getByLabel('Order activity').selectOption('repeat');
    await mobile.getByLabel('Latest order', {exact:true}).selectOption('30');
    await mobile.getByLabel('Latest order status').selectOption('delivered');
    await mobile.getByRole('button', {name:'Apply filters'}).click();
    assert.equal(await mobile.locator('.users-entry').count(), 1);
    assert.match(await mobile.locator('.users-result-count').innerText(), /of 1 account/);
    await mobile.getByRole('link', {name:'Remove latest order filter: Last 30 days'}).click();
    const retained = new URL(mobile.url()).searchParams;
    assert.equal(retained.get('activity'), 'repeat');
    assert.equal(retained.get('status'), 'delivered');
    assert.ok(!retained.has('period'));
    await mobile.getByRole('link', {name:'Remove latest order status filter: Delivered'}).click();
    assert.equal(new URL(mobile.url()).searchParams.get('activity'), 'repeat');
    assert.ok(!new URL(mobile.url()).searchParams.has('status'));
    await mobile.locator('.users-entry summary').click();
    assert.ok(await mobile.getByRole('link', {name:'OLDER-ORDER'}).isVisible());
    assert.ok(await mobile.getByRole('link', {name:'FIXTURE-INVOICE'}).isVisible());

    await page.goto(url);
    await optionsToggle.press('Enter');
    await reference.fill('  older-order  ');
    await role.selectOption('customer');
    assert.ok(await page.getByRole('button', {name:'Apply filters'}).locator('svg[aria-hidden=true]').isVisible());
    await page.getByRole('button', {name:'Apply filters'}).press('Enter');
    assert.equal(await rows.count(), 1);
    assert.equal(await rows.first().getAttribute('open'), '');
    const matched = page.locator('.users-history__match');
    assert.equal(await matched.count(), 1);
    assert.ok(await matched.getByRole('link', {name:'OLDER-ORDER'}).isVisible());
    assert.ok(await matched.getByText('Reference match', {exact:true}).isVisible());
    assert.ok(await page.getByRole('link', {name:'FIXTURE-INVOICE'}).isVisible());
    assert.ok(await page.getByRole('link', {name:'Reset filters'}).locator('svg[aria-hidden=true]').isVisible());
    await page.getByRole('link', {name:'Remove reference filter: older-order'}).press('Enter');
    assert.equal(new URL(page.url()).searchParams.get('role'), 'customer');
    assert.ok(!new URL(page.url()).searchParams.has('reference'));
    assert.equal(await rows.count(), 13);

    await mobile.goto(url);
    await mobile.locator('.users-filter-options > summary').click();
    await mobile.getByLabel('Order or invoice reference').fill('fixture-invoice');
    await mobile.getByRole('button', {name:'Apply filters'}).click();
    assert.equal(await mobile.locator('.users-entry').count(), 1);
    assert.ok(await mobile.locator('.users-history__match').getByRole('link', {name:'FIXTURE-INVOICE'}).isVisible());
    assert.ok(await mobile.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));

    await page.goto(url + '?reference=FIXTURE-INV');
    assert.equal(await rows.count(), 0, 'Partial references do not qualify an account');
    assert.match(await page.locator('.users-empty').innerText(), /Check the full order or invoice reference/);
    await page.goto(url + '?reference=UNDATED-ORDER&status=delivered');
    assert.equal(await rows.count(), 0, 'Reference lookup preserves the latest-status filter');
    await page.goto(url + '?reference=UNDATED-ORDER');
    assert.ok(await matched.getByRole('link', {name:'UNDATED-ORDER'}).isVisible());

    await page.goto(url + '?status=pending');
    assert.equal(await rows.count(), 0, 'Older status does not qualify an account');
    await page.goto(url + '?status=delivered');
    assert.equal(await rows.count(), 1, 'An undated order cannot qualify as latest');
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

    fixtures.settings = [{id:'app_settings', module_shop:'0', module_blog:'0'}];
    await mobile.goto(url);
    await navigation.press('Enter');
    await sidebar.getByText('Commerce', {exact:true}).press('Space');
    assert.ok(await sidebar.getByRole('link', {name:'Orders', exact:true}).isVisible());
    assert.equal(await sidebar.locator('a[href="/admin/products"],a[href="/admin/blog"]').count(), 0);
});
