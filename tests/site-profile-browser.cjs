'use strict';

const assert = require('node:assert/strict');
const {spawn, execFileSync} = require('node:child_process');
const {once} = require('node:events');
const {mkdtemp, rm} = require('node:fs/promises');
const {createServer} = require('node:http');
const net = require('node:net');
const os = require('node:os');
const path = require('node:path');
const {test} = require('node:test');
const {chromium} = require('playwright');

const root = path.resolve(__dirname, '..');

async function fixture(cleanup, id, modules) {
    const sessionDirectory = await mkdtemp(path.join(os.tmpdir(), 'bapx-profile-' + id + '-'));
    let bridge;
    let server;
    let serverClosed;
    cleanup.push(async () => {
        if (server) {
            server.kill();
            await serverClosed;
        }
        if (bridge?.listening) {
            const closed = new Promise(resolve => bridge.close(resolve));
            bridge.closeAllConnections();
            await closed;
        }
        await rm(sessionDirectory, {recursive:true, force:true});
    });
    const password = 'synthetic-bridge-' + id;
    const privateMarker = 'PRIVATE-ADMIN-' + id;
    const settings = {id:'app_settings', ...modules, shipping_mode:'flat', flat_rate:75, admin_email:privateMarker};
    const product = {id:id+'-product', slug:id+'-product', name:'Fixture product '+id, price:120, in_stock:true};
    const audit = [];
    bridge = createServer(async (request, response) => {
        const chunks = [];
        for await (const chunk of request) chunks.push(chunk);
        const input = JSON.parse(Buffer.concat(chunks));
        response.setHeader('Content-Type', 'application/json');
        if (input.password !== password) {
            response.writeHead(401).end('{"success":false}');
            return;
        }
        if (typeof input.query === 'string') {
            const table = /\bFROM (\w+)/i.exec(input.query)?.[1];
            const records = ({settings:[settings], products:[product]})[table] ?? [];
            response.end(JSON.stringify({success:true,data:records.map(row=>({id:row.id,_data:JSON.stringify(row)}))}));
            return;
        }
        if (input.action === 'upsert' && input.collection === 'settings' && input.record.id === 'app_settings') {
            Object.assign(settings, input.record);
        } else if (input.action === 'upsert' && input.collection === 'audit_events') {
            audit.push(input.record);
        } else {
            response.writeHead(400).end('{"success":false}');
            return;
        }
        response.end(JSON.stringify({success:true,record:input.record}));
    });
    bridge.listen(0, '127.0.0.1');
    await once(bridge, 'listening');

    const socket = net.createServer().listen(0, '127.0.0.1');
    await once(socket, 'listening');
    const port = socket.address().port;
    await new Promise(resolve => socket.close(resolve));
    const origin = 'http://127.0.0.1:' + port;
    const canonical = 'https://' + id + '.example.test';
    const env = {...process.env, APP_NAME:'Fixture ' + id, APP_URL:canonical, BAPX_TEST_MODE:'0',
        BAPX_MYSQL_HOST:'127.0.0.1', BAPX_MYSQL_PORT:'1', BAPX_MYSQL_DB:'unused',
        BAPX_MYSQL_USER:'unused', BAPX_MYSQL_PASS:password,
        BAPX_REMOTE_DB_URL:'http://localhost:' + bridge.address().port + '/remotedb'};
    const sessionId = 'fixture-owner-' + id;
    execFileSync('php', ['-d','session.save_path='+sessionDirectory,'-r',
        'session_id($argv[1]); session_start(); $_SESSION["user"]=["id"=>"fixture","role"=>"admin","email"=>"owner@example.test"]; session_write_close();',
        sessionId], {cwd:root,env});
    server = spawn('php',['-d','session.save_path='+sessionDirectory,'-S','127.0.0.1:'+port,'index.php'],{cwd:root,env,stdio:'ignore'});
    serverClosed = new Promise(resolve => server.once('close', resolve));
    return {origin,canonical,sessionId,settings,audit,privateMarker,product};
}

test('two deployments publish separately saved identity and contact facts through actual browser, SEO and MCP', {timeout:90000}, async t => {
    const cleanup = [];
    let browser;
    t.after(async () => {
        try {
            await browser?.close();
        } finally {
            const results = await Promise.allSettled(cleanup.map(stop => stop()));
            const errors = results.filter(result => result.status === 'rejected').map(result => result.reason);
            if (errors.length) throw new AggregateError(errors, 'Site-profile fixture cleanup failed');
        }
    });
    const alpha = await fixture(cleanup, 'alpha', {module_shop:'0',module_blog:'0'});
    const beta = await fixture(cleanup, 'beta', {module_shop:'1',module_blog:'1'});
    browser = await chromium.launch({executablePath:process.env.CHROMIUM_PATH || undefined});

    for (const [install,width,name,email,phone,otherName] of [
        [alpha,1280,'Caldera & Iris','hello@alpha.example.test','+44 (20) 7000-0123','Vela Workshop'],
        [beta,375,'Vela Workshop','','','Caldera & Iris'],
    ]) {
        const browserOptions = {viewport:{width,height:900},javaScriptEnabled:false,serviceWorkers:'block'};
        const staff = await browser.newContext(browserOptions);
        const publicContext = await browser.newContext(browserOptions);
        for (const context of [staff,publicContext]) await context.route('https://**/*',route=>route.abort());
        const visitor = await publicContext.newPage();
        const editor = await staff.newPage();
        const settingsUrl = install.origin+'/admin/settings';
        await visitor.goto(settingsUrl);
        assert.equal(new URL(visitor.url()).pathname,'/login');
        await staff.addCookies([{name:'PHPSESSID',value:install.sessionId,url:install.origin}]);
        await editor.goto(settingsUrl);
        const form = editor.locator('#settings-site-profile');
        const summary = name + ' makes local information available.';
        const story = name + ' story.\n\nSecond published paragraph.';
        for (const [label,value] of [
            ['Display name',name],['Business summary',summary],['About story',story],
            ['Public email',email],['Public phone 1',phone],['Days 1','Mon–Fri'],['Hours 1','10–16 UTC'],
        ]) await form.getByLabel(label,{exact:true}).fill(value);
        await form.getByRole('button',{name:'Save public site profile'}).press('Enter');
        await editor.getByRole('status').filter({hasText:'Public site profile saved.'}).waitFor();
        assert.equal(install.settings.site_display_name,name);
        assert.equal(install.settings.flat_rate,75);
        assert.equal(install.settings.admin_email,install.privateMarker);
        assert.equal(install.audit.filter(event=>event.resource_id==='site-profile').length,1);
        assert.ok(await editor.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
        const csrf = await form.locator('[name=_csrf]').getAttribute('value');
        await form.getByLabel('Public phone 1').fill('just text');
        await form.getByRole('button',{name:'Save public site profile'}).press('Enter');
        await editor.getByRole('alert').filter({hasText:'Use international phone numbers'}).waitFor();
        assert.equal(install.settings.public_contact_phone_1,phone);
        const changes = await form.locator('input[name],textarea[name]').evaluateAll(fields=>Object.fromEntries(fields.map(element=>[element.name,element.value])));
        const wrongToken = await staff.request.post(install.origin+'/admin/settings/save',{
            form:{...changes,_csrf:csrf+'incorrect',site_display_name:'Must stay unchanged'},maxRedirects:0,
        });
        assert.equal(wrongToken.status(),302);
        assert.equal(install.settings.site_display_name,name);

        const mailPreviewUrl = install.origin + '/admin/email-templates?template=welcome';
        await visitor.goto(mailPreviewUrl);
        assert.equal(new URL(visitor.url()).pathname,'/login');
        assert.equal((await editor.goto(mailPreviewUrl)).status(),200);
        const frame = editor.locator('iframe[title="Synthetic email preview"]');
        assert.equal(await frame.getAttribute('sandbox'),'');
        const message = frame.contentFrame();
        await message.getByRole('heading',{name:'Welcome to '+name,exact:true}).waitFor();
        assert.equal(await message.locator('img').count(),0,'A separate install without an uploaded logo must not show reference art');
        const helpLink = message.getByRole('link',{name:email ? 'Email '+email : 'Open the contact form',exact:true});
        assert.equal(await helpLink.getAttribute('href'),email ? 'mailto:'+email : install.canonical+'/contact#contact-form');
        const plainMail = await editor.locator('[data-mail-plain-preview]').textContent();
        assert.ok(plainMail.includes(name));
        assert.ok(!plainMail.includes(otherName) && !plainMail.includes(install.privateMarker));
        assert.ok(await editor.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
        if (install === alpha) {
            install.settings.logo_url = '/assets/images/media/2099/01/caldera-logo.png';
            await editor.reload();
            assert.equal(await message.locator('img').getAttribute('src'),install.canonical+install.settings.logo_url);
            delete install.settings.logo_url;
        }

        const forbidden = [otherName, install.privateMarker, 'Sri Panchami Spiritual', 'support@sripanchamispiritual.com'];
        for (const route of ['/', '/about', '/contact']) {
            const response = await visitor.goto(install.origin+route);
            assert.equal(response.status(),200);
            assert.equal(await visitor.locator('.brand span').innerText(),name);
            assert.equal(await visitor.locator('.footer-brand').innerText(),name);
            assert.equal(await visitor.locator('meta[property="og:site_name"]').getAttribute('content'),name);
            assert.equal(await visitor.locator('link[rel=canonical]').getAttribute('href'),install.canonical+route);
            assert.equal(await visitor.locator('.brand img').count(),0,'Reference logo never appears on a new installation');
            assert.equal(await visitor.locator('meta[property="og:image"]').count(),0,'No different business supplies the default image');
            const html = await visitor.content();
            assert.ok(forbidden.every(value=>!html.includes(value)));
            const graph = await visitor.locator('script[type="application/ld+json"]').evaluateAll(nodes=>nodes.map(node=>JSON.parse(node.textContent)));
            const organization = graph.find(node=>[node['@type']].flat().includes('Organization'));
            assert.equal(organization.name,name);
            assert.equal(organization.email,email || undefined);
            if (route === '/about') {
                assert.ok(await visitor.getByText(name+' story.',{exact:true}).isVisible());
                assert.ok(await visitor.getByText('Second published paragraph.',{exact:true}).isVisible());
                assert.equal(await visitor.locator('.about-hero__visual img').count(),0);
            }
            assert.ok(await visitor.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
        }

        const methods = await visitor.locator('.contact-card a').evaluateAll(links=>links.map(element=>element.getAttribute('href')));
        assert.deepEqual(methods,email ? ['tel:+442070000123','mailto:'+email] : []);
        const contact = await (await publicContext.request.get(install.origin+'/api/knowledge/contact')).json();
        const mcp = await publicContext.request.post(install.origin+'/api/mcp',{
            headers:{'MCP-Protocol-Version':'2025-11-25','Accept':'application/json, text/event-stream'},
            data:{jsonrpc:'2.0',id:1,method:'tools/call',params:{name:'get_public_contact',arguments:{}}},
        });
        assert.equal(mcp.status(),200);
        const result = (await mcp.json()).result;
        const projected = result.structuredContent;
        assert.deepEqual(projected,contact);
        assert.equal(projected.name,name);
        assert.equal(projected.email,email || undefined);
        assert.deepEqual(projected.hours,[{days:'Mon–Fri',hours:'10–16 UTC'}]);
        if (!email) {
            const contactCard = result._meta['in.bapx/publicSurface'].messages[0].createSurface.dataModel.items[0];
            assert.equal(contactCard.title,'Contact form');
            assert.equal(contactCard.path,'/contact#contact-form');
        }
        const manifest = await publicContext.request.get(install.origin+'/manifest.json');
        assert.equal((await manifest.json()).name,name);
        assert.equal(manifest.headers()['set-cookie'],undefined);
        const adminManifest = await publicContext.request.get(install.origin+'/admin/manifest.json');
        assert.equal((await adminManifest.json()).name,name+' Admin');

        if (install === alpha) {
            assert.equal(await visitor.locator('#primary-nav a[href="/shop"], #primary-nav a[href="/blog"]').count(),0);
            for (const route of ['/shop','/product/'+alpha.product.slug,'/blog','/blog/order-products','/cart','/checkout']) {
                assert.equal((await visitor.goto(install.origin+route)).status(),404,route);
            }
            for (const route of ['/api/shop','/api/product/'+alpha.product.slug,'/api/blog/order-products']) {
                const response = await publicContext.request.get(install.origin+route);
                assert.equal(response.status(),404,route);
                assert.deepEqual(await response.json(),{error:'Not found'});
            }
            const sitemap = await publicContext.request.get(install.origin+'/sitemap.xml');
            assert.doesNotMatch(await sitemap.text(), /<loc>[^<]+\/(shop|blog|product)(\/|<)/);
        } else {
            assert.equal((await visitor.goto(install.origin+'/shop')).status(),200);
            assert.ok(await visitor.getByText(beta.product.name,{exact:true}).first().isVisible());
            assert.equal(await visitor.getByText(alpha.product.name,{exact:true}).count(),0);
            assert.equal((await visitor.goto(install.origin+'/blog')).status(),200);
        }
        await staff.close();
        await publicContext.close();
    }
    assert.equal(alpha.settings.site_display_name,'Caldera & Iris');
    assert.equal(beta.settings.site_display_name,'Vela Workshop');
});
