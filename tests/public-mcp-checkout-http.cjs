'use strict';
const assert=require('node:assert/strict');
const {execFileSync,spawn}=require('node:child_process');
const {createServer}=require('node:http');
const {once}=require('node:events');
const {mkdtemp,rm}=require('node:fs/promises');
const {tmpdir}=require('node:os');
const {createRequire}=require('node:module');
const path=require('node:path');
const {test}=require('node:test');
const sdk=createRequire(path.resolve(process.argv[2]||'.','package.json'));
const {Client,StreamableHTTPClientTransport}=sdk('@modelcontextprotocol/client');
const root=path.join(__dirname,'..');
const router='tests/fixtures/public-mcp-checkout-http.php';

// Genuine PHP HTTP/controller + real service/state/order projection, with a fake in-process provider.
// Separate native MySQL fixtures cover durable locking; this test has no live merchant URL or gateway.
test('default and modern HTTP clients complete explicit checkout and recover a discarded prepare response',{timeout:120000},async()=>{
    const directory=await mkdtemp(path.join(tmpdir(),'bapx-mcp-checkout-'));
    const environment={...process.env,BAPX_MCP_CHECKOUT_FIXTURE_DIR:directory,BAPX_TEST_MODE:'1',APP_URL:'https://cart.example.test'};
    const control=(name,input={})=>JSON.parse(execFileSync('php',[router,name],{cwd:root,env:environment,encoding:'utf8',
        input:JSON.stringify(input),maxBuffer:65536}));
    let server;
    try{
        assert.equal(control('initialize').ready,true);
        const socket=createServer();socket.listen(0,'127.0.0.1');await once(socket,'listening');
        const port=socket.address().port;await new Promise(resolve=>socket.close(resolve));
        const endpoint=`http://127.0.0.1:${port}/api/mcp`;
        server=spawn('php',['-S',`127.0.0.1:${port}`,router],{cwd:root,env:environment,stdio:['ignore','ignore','pipe']});
        server.stderr.on('data',()=>{});
        for(let retry=0;;retry++){
            try{await fetch(endpoint);break;}catch(error){if(retry>=60)throw Error('Native checkout fixture did not start.');await new Promise(resolve=>setTimeout(resolve,50));}
        }
        for(const profile of ['default','modern']){
            const client=profile==='default'?new Client({name:'ordinary-http-checkout',version:'1'})
                :new Client({name:'modern-http-checkout',version:'1'},{versionNegotiation:{mode:{pin:'2026-07-28'}}});
            const call=(name,args)=>client.callTool({name,arguments:args});
            const baseline=control('inspect');
            try{
                await client.connect(new StreamableHTTPClientTransport(new URL(endpoint),{fetch:async(input,init)=>{
                    const request=new Request(input,init);request.headers.set('Cookie','PHPSESSID=unrelated-user-session');
                    request.headers.set('Authorization','Bearer not-an-owner-grant');
                    const response=await fetch(request);
                    assert.equal(response.headers.get('set-cookie'),null);assert.equal(response.headers.get('mcp-session-id'),null);
                    assert.match(response.headers.get('cache-control'),/no-store/);return response;
                }}));
                const tools=(await client.listTools()).tools;assert.equal(tools.length,10);
                const token=(await call('create_cart',{})).structuredContent.cartToken;
                await call('set_cart_quantity',{cartToken:token,slug:'sri-varahi-amman-ring',quantity:1,expectedRevision:0});
                const delivery={name:'Synthetic HTTP Buyer',email:'http-buyer@example.test',phone:'9876543210',
                    address:'18 Synthetic HTTP Road',city:'Chennai',pincode:'600001',state:'Tamil Nadu'};
                const quote=(await call('review_checkout',{cartToken:token,expectedRevision:1,delivery})).structuredContent;
                assert.equal(quote.stage,'review_ready');assert.equal(quote.totals.totalPaise,50075);
                assert.ok(!JSON.stringify(quote).includes(delivery.email));
                const args={cartToken:token,expectedRevision:2,quoteId:quote.quote.id,confirm:true};
                const body={jsonrpc:'2.0',id:987,method:'tools/call',params:{name:'prepare_checkout_payment',arguments:args}};
                const headers={'Content-Type':'application/json',Accept:'application/json, text/event-stream','MCP-Protocol-Version':profile==='default'?'2025-11-25':'2026-07-28'};
                if(profile==='modern'){
                    headers['Mcp-Method']='tools/call';headers['Mcp-Name']='prepare_checkout_payment';
                    body.params._meta={'io.modelcontextprotocol/protocolVersion':'2026-07-28','io.modelcontextprotocol/clientCapabilities':{}};
                }
                const abort=new AbortController();abort.abort();
                await assert.rejects(fetch(endpoint,{method:'POST',headers,body:JSON.stringify(body),signal:abort.signal}),error=>error.name==='AbortError');
                assert.equal(control('inspect').attempts,baseline.attempts,'A pre-aborted request cannot create an attempt');
                const prepared=await fetch(endpoint,{method:'POST',headers,body:JSON.stringify(body)});assert.equal(prepared.status,200);
                await prepared.body.cancel(); // Deliberately lose the successful response; never read its capability.
                const recovered=(await call('get_cart',{cartToken:token})).structuredContent;
                assert.equal(recovered.status,'checkout_locked');assert.ok(recovered.checkout.paymentToken);
                const paymentToken=recovered.checkout.paymentToken;
                let status=(await call('get_checkout_status',{paymentToken})).structuredContent;
                assert.equal(status.paymentStatus,'pending');assert.equal(status.orderStatus,'not_created');
                assert.equal(control('inspect').providerCreates,baseline.providerCreates+1);
                control('mark_paid',{attemptId:recovered.checkout.attemptId});control('configure',{failPersist:true});
                status=(await call('get_checkout_status',{paymentToken})).structuredContent;
                assert.equal(status.paymentStatus,'captured');assert.equal(status.orderStatus,'save_pending');
                control('configure',{failPersist:false});
                const saved=await call('get_checkout_status',{paymentToken});status=saved.structuredContent;
                assert.equal(status.orderStatus,'saved');assert.ok(status.orderReference);
                assert.equal(control('inspect').orders,baseline.orders+1);
                assert.ok(!JSON.stringify(saved).includes(paymentToken)&&!JSON.stringify(saved).includes(delivery.email));
                await call('get_checkout_status',{paymentToken});assert.equal(control('inspect').orders,baseline.orders+1);
                control('configure',{shopEnabled:false});
                assert.equal((await client.listTools()).tools.filter(tool=>tool._meta?.['in.bapx/access']?.scope!=='public_information').map(tool=>tool.name).join(','),'get_checkout_status');
                assert.equal((await call('get_checkout_status',{paymentToken})).structuredContent.orderStatus,'saved');
                control('configure',{shopEnabled:true});
            }finally{await client.close();}
        }
    }finally{
        if(server?.exitCode===null){server.kill();await once(server,'exit');}
        await rm(directory,{recursive:true,force:true});
    }
});
