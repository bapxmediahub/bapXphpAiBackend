'use strict';
const assert=require('node:assert/strict');
const {test}=require('node:test');
const {execFileSync,spawn}=require('node:child_process');
const {createServer}=require('node:http');
const {once}=require('node:events');
const {mkdtemp,rm}=require('node:fs/promises');
const {randomBytes}=require('node:crypto');
const net=require('node:net');
const os=require('node:os');
const path=require('node:path');
const {chromium}=require('playwright');

test('owner persists a shipping proposal through actual HTTP, previews, reloads and refuses stale saves',{timeout:90000},async t=>{
    const root=path.resolve(__dirname,'..');
    const sessions=await mkdtemp(path.join(os.tmpdir(),'bapx-shipping-policy-'));
    const profile={id:'app_settings',shipping_mode:'flat',flat_rate:25,site_display_name:'Shipping Fixture'};
    let writes=0,audits=0,server,serverClosed,browser;
    const bridge=createServer(async(req,res)=>{
        try{
            const chunks=[];for await(const chunk of req)chunks.push(chunk);
            const input=JSON.parse(Buffer.concat(chunks));
            res.setHeader('Content-Type','application/json');
            const ok=fields=>res.end(JSON.stringify({success:true,...fields}));
            if(input.password!=='shipping-fixture')return res.writeHead(403).end('{}');
            if(typeof input.query==='string'){
                const table=/\bFROM (\w+)/i.exec(input.query)?.[1];
                const rows=table==='settings'?[profile]:[];
                const matching=input.query.includes('WHERE id = ?')?rows.filter(row=>row.id===input.params[0]):rows;
                return ok({data:/^SELECT COUNT\(/i.test(input.query)?[{total:matching.length}]:
                    matching.map(row=>({id:row.id,_data:JSON.stringify(row)}))});
            }
            if(input.action==='save_shipping_draft'&&input.collection==='settings'){
                if(input.expected!==(profile.shipping_policy_draft?.revision??'0'))return ok({saved:false});
                writes++;
                profile.shipping_policy_draft={revision:randomBytes(16).toString('hex'),zones:input.zones};
                return ok({saved:true});
            }
            if(input.action==='upsert'&&input.collection==='audit_events'){audits++;return ok({record:input.record});}
            res.writeHead(400).end('{}');
        }catch{res.writeHead(500).end('{}');}
    });
    t.after(async()=>{
        try{await browser?.close();}
        finally{
            if(server){server.kill();await serverClosed;}
            if(bridge.listening){const closed=new Promise(resolve=>bridge.close(resolve));bridge.closeAllConnections();await closed;}
            await rm(sessions,{recursive:true,force:true});
        }
    });
    bridge.listen(0,'127.0.0.1');await once(bridge,'listening');
    const socket=net.createServer().listen(0,'127.0.0.1');await once(socket,'listening');
    const port=socket.address().port;await new Promise(resolve=>socket.close(resolve));
    const origin='http://127.0.0.1:'+port;
    const env={...process.env,BAPX_TEST_MODE:'0',APP_URL:origin,BAPX_MYSQL_HOST:'127.0.0.1',
        BAPX_MYSQL_PORT:'1',BAPX_MYSQL_DB:'fixture',BAPX_MYSQL_USER:'fixture',BAPX_MYSQL_PASS:'shipping-fixture',
        BAPX_REMOTE_DB_URL:'http://localhost:'+bridge.address().port+'/remotedb'};
    const options=['-d','session.save_path='+sessions], sessionId='shipping-policy-owner-fixture';
    execFileSync('php',[...options,'-r','session_id($argv[1]);session_start();$_SESSION["user"]=["sub"=>"fixture","role"=>"admin"];session_write_close();',sessionId],{cwd:root,env});
    server=spawn('php',[...options,'-S','127.0.0.1:'+port,'index.php'],{cwd:root,env,stdio:'ignore'});
    serverClosed=new Promise(resolve=>server.once('close',resolve));
    browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true});
    const context=await browser.newContext({viewport:{width:375,height:850},serviceWorkers:'block',reducedMotion:'reduce'});
    await context.route('https://**/*',r=>r.abort());
    const page=await context.newPage();page.setDefaultTimeout(8000);
    await page.goto(origin+'/admin/shipping');
    assert.equal(new URL(page.url()).pathname,'/login','Guest cannot enter owner settings');
    await context.addCookies([{name:'PHPSESSID',value:sessionId,url:origin}]);
    const stale=await context.newPage();
    for(const p of [page,stale]){
        await p.goto(origin+'/admin/shipping');
        assert.equal(await p.locator('[name=draft_revision]').inputValue(),'0');
    }
    const zone=(p,n)=>p.getByRole('group',{name:'Zone '+n,exact:true});
    async function flat(p,fee){
        const z=zone(p,1);
        await z.getByLabel('State',{exact:true}).selectOption('Tamil Nadu');
        await z.getByLabel('PIN patterns').fill('641*,600001');
        await z.getByLabel('Flat fee').fill(fee);
    }
    async function save(p){
        const [res]=await Promise.all([p.waitForResponse(r=>new URL(r.url()).pathname==='/admin/shipping/save-draft'&&r.request().method()==='POST'),
            p.getByRole('button',{name:'Save shipping draft',exact:true}).click()]);
        return res.status();
    }
    await flat(page,'75.25');
    await flat(stale,'99');
    assert.equal(await save(page),302);
    await page.getByText('Shipping draft saved. Customer charges are unchanged.',{exact:true}).waitFor();
    const first=profile.shipping_policy_draft;
    assert.equal(writes,1);
    assert.equal(audits,1);
    assert.equal(first.zones[0].fee,'75.25');
    assert.equal(first.zones[0].pins,'641*, 600001');
    assert.equal(await page.locator('[name=draft_revision]').inputValue(),first.revision);
    for(const width of [1280,375]){
        await page.setViewportSize({width,height:850});await page.reload();
        assert.equal(await zone(page,1).getByLabel('Flat fee').inputValue(),'75.25');
        assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
    }
    assert.equal(await save(stale),409);
    await stale.getByText(/Another owner session saved the shipping draft/).waitFor();
    assert.equal(await zone(stale,1).getByLabel('Flat fee').inputValue(),'99');
    assert.equal(await stale.getByRole('button',{name:'Save shipping draft',exact:true}).count(),0);
    assert.equal(writes,1);
    await zone(page,1).getByLabel('Flat fee').fill('75.251');
    assert.equal(await save(page),422);
    await page.getByText(/at most two decimal places/).waitFor();
    assert.equal(await zone(page,1).getByLabel('Flat fee').inputValue(),'75.251');
    assert.equal(writes,1);
    await page.goto(origin+'/admin/shipping');
    await page.getByLabel('Delivery state').selectOption('Tamil Nadu');
    await page.getByLabel('Delivery PIN').fill('641001');
    await page.getByLabel('Hypothetical item subtotal').fill('100');
    await page.getByRole('button',{name:'Preview shipping fee'}).click();
    await page.getByText('₹75.25',{exact:true}).waitFor();
    assert.equal(await page.locator('[name=draft_revision]').inputValue(),first.revision);
    assert.equal(writes,1,'Preview remains non-mutating');
    await zone(page,1).getByLabel('Flat fee').fill('80');
    assert.equal(await save(page),302);
    await page.getByText('Shipping draft saved. Customer charges are unchanged.',{exact:true}).waitFor();
    assert.equal(writes,2);
    assert.notEqual(profile.shipping_policy_draft.revision,first.revision);
    assert.equal(profile.shipping_policy_draft.zones[0].fee,'80.00');
    assert.equal(profile.shipping_mode,'flat');
    assert.equal(profile.flat_rate,25,'Draft never changes existing active settings');
});
