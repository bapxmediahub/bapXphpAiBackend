'use strict';
const assert=require('node:assert/strict');
const {execFileSync,spawn}=require('node:child_process');
const {createServer}=require('node:http');
const {once}=require('node:events');
const {createRequire}=require('node:module');
const path=require('node:path');
const {test}=require('node:test');
const sdk=createRequire(path.resolve(process.argv[2]||'.','package.json'));
const {Client,StreamableHTTPClientTransport}=sdk('@modelcontextprotocol/client');
const root=path.join(__dirname,'..');
const environment={...process.env,BAPX_MYSQL_HOST:'127.0.0.1',BAPX_MYSQL_PORT:'33068',BAPX_MYSQL_DB:'sps_order_test',
    BAPX_MYSQL_USER:'sps_test',BAPX_MYSQL_PASS:'sps_ci_fixture',BAPX_REMOTE_DB_URL:'http://127.0.0.1:1/remotedb',
    BAPX_TEST_MODE:'0',APP_URL:'https://cart.example.test'};
const fixture=(action,input)=>JSON.parse(execFileSync('php',['tests/fixtures/public-mcp-shopping-http.php',action],{
    cwd:root,env:environment,encoding:'utf8',maxBuffer:1024*1024,input:input?JSON.stringify(input):undefined}));

// Real index.php + PHP HTTP + isolated MySQL. No configurable live URL, gateway calls or payment/order writes.
test('named anonymous cart tools work over real HTTP for default and modern clients without browser sessions',{timeout:60000},async()=>{
    const state=fixture('seed');state.cartIds=[];
    let child;
    try{
        const socket=createServer();socket.listen(0,'127.0.0.1');await once(socket,'listening');
        const port=socket.address().port;await new Promise(resolve=>socket.close(resolve));
        const url=`http://127.0.0.1:${port}/api/mcp`;
        child=spawn('php',['-S',`127.0.0.1:${port}`,'index.php'],{cwd:root,env:environment,stdio:['ignore','ignore','pipe']});
        // Drain server diagnostics without printing request/capability data to public CI output.
        child.stderr.on('data',()=>{});
        for(let attempt=0;;attempt++){
            try{await fetch(url);break;}catch(error){if(attempt>=60)throw Error('Disposable PHP fixture did not start.');await new Promise(resolve=>setTimeout(resolve,50));}
        }
        for(const profile of ['default','modern']){
            const info={name:'actual-http-cart-fixture',version:'1'};
            const client=profile==='default'?new Client(info):new Client(info,{versionNegotiation:{mode:{pin:'2026-07-28'}}});
            const call=async(name,args)=>{
                const result=await client.callTool({name,arguments:args});
                if(result.structuredContent?.cartToken)state.cartIds.push(result.structuredContent.cartToken.slice(5,37));
                return result;
            };
            try{
                await client.connect(new StreamableHTTPClientTransport(new URL(url),{fetch:async(input,init)=>{
                    const request=new Request(input,init);
                    request.headers.set('Cookie','PHPSESSID=fixture-unrelated-browser');
                    request.headers.set('Authorization','Bearer fixture-must-not-grant-owner-tools');
                    const response=await fetch(request);
                    assert.equal(response.headers.get('set-cookie'),null);
                    assert.equal(response.headers.get('mcp-session-id'),null);
                    assert.match(response.headers.get('cache-control'),/no-store/);
                    return response;
                }}));
                const names=(await client.listTools()).tools.map(tool=>tool.name);
                assert.ok(['create_cart','get_cart','set_cart_quantity'].every(name=>names.includes(name)));
                assert.ok(!names.includes('paid_sales_summary'));
                const first=await call('create_cart',{});const token=first.structuredContent.cartToken;
                assert.equal(first.structuredContent.itemCount,0);
                assert.ok(!JSON.stringify(first.content).includes(token));
                const args={cartToken:token,slug:state.productId,quantity:2,expectedRevision:0};
                const changed=await call('set_cart_quantity',args);
                assert.equal(changed.structuredContent.revision,1);
                assert.equal(changed.structuredContent.estimatedTotalInr,246.9);
                assert.ok(!JSON.stringify(changed).includes('PRIVATE_HTTP_CART_FIXTURE'));
                const replay=await call('set_cart_quantity',args);
                assert.equal(replay.structuredContent.itemCount,2);assert.equal(replay.structuredContent.replayed,true);
                const conflict=await call('set_cart_quantity',{...args,quantity:3});
                assert.equal(conflict.isError,true);assert.equal(conflict._meta['in.bapx/shoppingError'].httpStatus,409);
                const second=await call('create_cart',{});
                assert.equal(second.structuredContent.itemCount,0);
                const own=await call('get_cart',{cartToken:token});
                assert.equal(own.structuredContent.itemCount,2);
                assert.equal(own.structuredContent.cartToken,undefined);
                const unrelated=await call('get_cart',{cartToken:'cart_'+state.cartIds.at(-1)+'.'+'0'.repeat(64)});
                assert.equal(unrelated.isError,true);
                const absent=await call('get_cart',{});assert.equal(absent.isError,true);
                const extra=await call('create_cart',{role:'admin'});assert.equal(extra.isError,true);
                await assert.rejects(call('paid_sales_summary',{}));
            }finally{await client.close();}
        }
    }finally{
        if(child?.exitCode===null){child.kill();await once(child,'exit');}
        assert.equal(fixture('cleanup',state).ordersUnchanged,true,'Cart HTTP actions created a business order');
    }
});
