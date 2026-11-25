const assert = require('node:assert/strict');
const {spawn, execFileSync} = require('node:child_process');
const {once} = require('node:events');
const {mkdtemp,rm} = require('node:fs/promises');
const net = require('node:net');
const os = require('node:os');
const path = require('node:path');
const {test} = require('node:test');
const {chromium} = require('playwright');

async function freePort() {
    const socket = net.createServer();
    socket.listen(0, '127.0.0.1');
    await once(socket, 'listening');
    const port = socket.address().port;
    await new Promise(resolve => socket.close(resolve));
    return port;
}

test('owner edits the public header through remotedb with native controls and concurrent-write protection', async () => {
    const root = path.resolve(__dirname,'..');
    const temp = await mkdtemp(path.join(os.tmpdir(),'bapx-header-menu-'));
    const db = {...process.env,BAPX_MYSQL_HOST:'127.0.0.1',BAPX_MYSQL_PORT:process.env.BAPX_MENU_TEST_PORT || '33068',
        BAPX_MYSQL_DB:'sps_order_test',BAPX_MYSQL_USER:'sps_test',BAPX_MYSQL_PASS:'sps_ci_fixture',BAPX_TEST_MODE:'0',
        BAPX_REMOTE_DB_URL:'http://127.0.0.1:1/remotedb'};
    const php = (code) => execFileSync('php',['-d','session.save_path='+temp,'-r',code],{cwd:root,env:db,encoding:'utf8'});
    const direct = code => php("require 'app/bootstrap.php'; $store = new App\\Services\\DatabaseService(true); " + code);
    function publishPage(title, status) {
        const input = JSON.stringify({slug:'festival-hours', title, status, content:'Fixture opening hours.'});
        const encoded = Buffer.from(input).toString('base64');
        direct(`$pages=new App\\Services\\AuthoredPageService($store);
            $record=json_decode(base64_decode('${encoded}'),true);
            $record['expected_revision']=(string)($pages->find($record['slug'])['revision'] ?? '0');
            $pages->save($record);`);
    }
    const session = 'header-menu-owner-fixture';
    const servers = [];
    let browser;
    let initialized = false;
    try {
        direct(`foreach (['settings','secrets','audit_events'] as $table) {
            $store->connection()->exec("CREATE TABLE IF NOT EXISTS $table (id VARCHAR(64) PRIMARY KEY, _data JSON NOT NULL,
                _owner VARCHAR(255), _status VARCHAR(64), _created_at VARCHAR(40), _updated_at VARCHAR(40))");
        }
        if ($store->read('settings')) throw new RuntimeException('An empty disposable settings table is required.');
        $store->upsert('settings',['id'=>'legacy_fixture','timezone'=>'Asia/Kolkata',
            'module_shop'=>'1','module_blog'=>'1','admin_password'=>'synthetic-legacy-plaintext']);`);
        initialized = true;
        const patches = [
            "['site_display_name'=>'Menu fixture storefront']",
            "['flat_rate'=>99]",
        ];
        await Promise.all(patches.map(async fields => {
            const writer = spawn('php',['-r',`
                require 'app/bootstrap.php';
                $store = new App\\Services\\DatabaseService(true);
                for ($attempt = 0; $attempt < 6; ++$attempt) {
                    $store->upsert('settings', ['id'=>'app_settings'] + ${fields});
                }
            `],{cwd:root,env:db,stdio:'ignore'});
            assert.deepEqual(await once(writer,'exit'),[0,null]);
        }));
        const patched = JSON.parse(direct("echo json_encode($store->find('settings','app_settings'));"));
        assert.equal(patched.site_display_name,'Menu fixture storefront');
        assert.equal(patched.flat_rate,99);
        assert.equal(patched.timezone,'Asia/Kolkata');
        assert.equal(Object.hasOwn(patched,'admin_password'),false);

        php('session_id("'+session+'"); session_start(); $_SESSION["user"]=["id"=>"owner","role"=>"admin","email"=>"header-menu-fixture@example.test"]; session_write_close();');
        const bridgePort = await freePort();
        const bridge = spawn('php',['-S','127.0.0.1:'+bridgePort,'index.php'],{cwd:root,env:db,stdio:'ignore'});
        servers.push(bridge);
        const port = await freePort();
        const origin = 'http://127.0.0.1:'+port;
        servers.push(spawn('php',['-d','session.save_path='+temp,'-S','127.0.0.1:'+port,'index.php'],{
            cwd:root,env:{...db,BAPX_MYSQL_PORT:'1',BAPX_REMOTE_DB_URL:'http://localhost:'+bridgePort+'/remotedb',APP_URL:origin},stdio:'ignore'}));
        browser = await chromium.launch({executablePath:process.env.CHROMIUM_PATH || undefined});
        const owner = await browser.newContext({javaScriptEnabled:false,viewport:{width:1280,height:800}});
        const guest = await browser.newContext({javaScriptEnabled:false,viewport:{width:375,height:812}});
        for (const context of [owner,guest]) await context.route('https://**/*',route=>route.abort());
        const editor = await owner.newPage();
        const visitor = await guest.newPage();
        const url = origin+'/admin/appearance/menu';
        await editor.goto(url);
        assert.equal(new URL(editor.url()).pathname, '/login');
        await owner.addCookies([{name:'PHPSESSID',value:session,url:origin}]);
        await editor.goto(url);
        const readSlots = () => editor.locator('select[name="positions[]"]').evaluateAll(inputs=>inputs.map(input=>input.value));
        assert.deepEqual(await readSlots(), ['shop','temples','blog','about','contact','']);
        assert.equal(await editor.locator('option[value="search"]').count(), 0);
        direct("$store->upsert('settings',['id'=>'header_menu','menu_revision'=>1,'header_menu'=>['about','search','temples','shop']]);");
        await editor.reload();
        assert.deepEqual(await readSlots(), ['about','temples','shop','','','']);
        const stale = await owner.newPage();
        await stale.goto(url);
        publishPage('Festival & opening hours','published');
        await editor.goto(url);
        const links = ['terms','about','blog','shop','privacy','pg:festival-hours'];
        for (let i=0;i<links.length;i++) await editor.getByLabel('Position '+(i+1)).selectOption(links[i]);
        await editor.getByRole('button',{name:'Save header menu'}).press('Enter');
        await editor.getByRole('status').filter({hasText:'Header menu saved.'}).waitFor();
        await stale.getByRole('button',{name:'Save header menu'}).click();
        await stale.getByRole('status').filter({hasText:'Another editor saved'}).waitFor();
        const csrf = await editor.locator('[name="_csrf"]').getAttribute('value');
        const revision = await editor.locator('[name="revision"]').getAttribute('value');
        for (const [token,items] of [['incorrect',links],[csrf,['terms','terms','','','','']],[csrf,['unlisted-page','','','','','']],[csrf,['pg:hidden-page','','','','','']],[csrf,['search','','','','','']],[csrf,['about']]]) {
            const body = new URLSearchParams({_csrf:token,revision});
            for(const item of items) body.append('positions[]',item);
            const response = await owner.request.post(url,{headers:{'content-type':'application/x-www-form-urlencoded'},data:body.toString(),maxRedirects:0});
            assert.equal(response.status(),302);
            await editor.goto(url);
            assert.deepEqual(await readSlots(),links);
            assert.equal(await editor.locator('[name=revision]').getAttribute('value'),revision);
        }
        assert.equal(direct("echo $store->find('settings','app_settings')['timezone'];"),'Asia/Kolkata');
        const desktop = await owner.newPage();
        await desktop.goto(origin+'/terms');
        const primary = page=>page.locator('#primary-nav');
        const visibleTargets = page=>primary(page).locator(':scope > a, .nav-dropdown__trigger').evaluateAll(nodes=>nodes.map(n=>n.pathname));
        assert.deepEqual(await visibleTargets(desktop),['/terms','/about','/blog','/shop','/privacy','/pages/festival-hours','/account/dashboard','/logout']);
        assert.equal(await primary(desktop).getByRole('link',{name:'Terms',exact:true}).getAttribute('aria-current'),'page');
        assert(await primary(desktop).locator('.nav-dropdown__menu a').count()>1);

        assert.equal(await primary(desktop).getByRole('link',{name:'Festival & opening hours'}).count(),1);
        publishPage('Summer hours','published');
        await desktop.reload();
        assert.equal(await primary(desktop).getByRole('link',{name:'Summer hours'}).count(),1);
        publishPage('Unpublished title','draft');
        await desktop.reload();
        assert.equal(await primary(desktop).locator('a[href="/pages/festival-hours"]').count(),0);
        await editor.reload();
        assert.equal(await editor.locator('option:checked').last().innerText(),'Unavailable page · festival-hours');
        publishPage('Summer hours','published');

        // Switch a module through the existing settings flow, keeping the menu choice for re-enablement.
        const disable = await owner.request.post(origin+'/admin/settings/save',{form:{_csrf:csrf,module_shop:'0'},maxRedirects:0});
        assert.equal(disable.status(),302);
        await visitor.goto(origin+'/terms');
        const menu = primary(visitor);
        assert.equal(await menu.isVisible(),false);
        await visitor.getByRole('link',{name:'Menu',exact:true}).press('Enter');
        await menu.getByRole('link',{name:'Privacy',exact:true}).waitFor();
        assert.deepEqual(await visibleTargets(visitor),['/terms','/about','/blog','/privacy','/pages/festival-hours','/login']);
        await menu.getByRole('link',{name:'Close menu'}).click();
        assert.equal(await menu.isVisible(),false);
        await visitor.getByRole('link',{name:'Search',exact:true}).press('Enter');
        assert.equal(new URL(visitor.url()).pathname, '/search');
        assert.equal(await visitor.getByRole('searchbox',{name:'Search by name or keyword'}).count(), 1);

        // Enable progressive controls in a normal browser and exercise the real header markup.
        const enhanced = await browser.newContext({viewport:{width:375,height:812}});
        await enhanced.route('https://**/*',route=>route.abort());
        const shopper = await enhanced.newPage();
        await shopper.goto(origin+'/terms');
        const trigger = shopper.getByRole('button',{name:'Search',exact:true});
        await trigger.press('Space');
        const popup = shopper.getByRole('dialog',{name:'Search the site'});
        await popup.waitFor();
        const field = popup.getByRole('searchbox');
        assert.equal(await field.evaluate(el=>el === document.activeElement), true);
        await shopper.keyboard.press('Escape');
        await popup.waitFor({state:'hidden'});
        assert.equal(await trigger.evaluate(el=>el === document.activeElement), true);
        await trigger.click();
        await shopper.mouse.click(8,240);
        await popup.waitFor({state:'hidden'});
        await enhanced.close();

        await editor.goto(url);
        assert(await editor.locator('select').first().locator('option[value=shop]').innerText().then(t=>t.includes('module off')));
        assert.deepEqual(await readSlots(),links);
        direct("$store->delete('settings','pg:festival-hours');");
        await desktop.reload();
        assert.equal(await primary(desktop).locator('a[href="/pages/festival-hours"]').count(),0);
        publishPage('Reopened venue hours','published');
        await desktop.reload();
        assert.equal(await primary(desktop).getByRole('link',{name:'Reopened venue hours'}).count(),1);
        await editor.setViewportSize({width:375,height:812});
        const slots = editor.locator('select[name="positions[]"]');
        for (let i=0;i<6;i++) await slots.nth(i).selectOption('');
        await editor.getByRole('button',{name:'Save header menu'}).press('Enter');
        await editor.getByRole('status').filter({hasText:'Header menu saved.'}).waitFor();
        await visitor.goto(origin+'/terms');
        await visitor.getByRole('link',{name:'Menu',exact:true}).click();
        assert.deepEqual(await visibleTargets(visitor),['/login']);
        assert(await visitor.locator('footer a[href^="/blog/category/"]').count()>0);
        const audit = direct("echo count(array_filter($store->read('audit_events'),fn($e)=>$e['resource_id']==='header_menu' && $e['actor_email']==='header-menu-fixture@example.test'));");
        assert.equal(audit,'2');
        assert.equal(direct("$store->delete('settings','app_settings'); $store->delete('settings','legacy_fixture'); $settings=new App\\Services\\SettingsService($store); echo json_encode([$settings->public()['shipping_mode'],$settings->headerMenu()['items']]);"),'["free",[]]');
    } finally {
        await browser?.close();
        for (const server of servers) server.kill();
        if(initialized) direct("$store->delete('settings','app_settings'); $store->delete('settings','legacy_fixture'); $store->delete('settings','header_menu'); $store->delete('settings','pg:festival-hours'); foreach ($store->read('audit_events') as $event) if ($event['actor_email']==='header-menu-fixture@example.test' && in_array($event['resource_id'],['header_menu','public'],true)) $store->delete('audit_events',$event['id']);");
        await rm(temp,{recursive:true,force:true});
    }
});
