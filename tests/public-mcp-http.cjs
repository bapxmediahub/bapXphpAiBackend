'use strict';
const assert=require('node:assert/strict');
const {spawn}=require('node:child_process');
const {createServer}=require('node:http');
const {once}=require('node:events');
const {createRequire}=require('node:module');
const path=require('node:path');
const {test}=require('node:test');
const sdk=createRequire(path.resolve(process.argv[2]||'.','package.json'));
const {Client,StreamableHTTPClientTransport}=sdk('@modelcontextprotocol/client');

const liveUrl=process.env.PUBLIC_MCP_URL;
if(liveUrl){
    const target=new URL(liveUrl);
    assert.equal(target.protocol,'https:','PUBLIC_MCP_URL must use HTTPS');
    assert.equal(target.pathname,'/api/mcp','PUBLIC_MCP_URL must identify the exact public MCP endpoint');
    assert.ok(!target.username&&!target.password&&!target.search&&!target.hash,'PUBLIC_MCP_URL must not contain credentials, query or fragment');
}

async function verifyPublicClients(url,{live=false}={}){
    for(const profile of ['default','modern']){
        const info={name:'public-http-interop',version:'1'};
        const client=profile==='default' ? new Client(info) : new Client(info,{
            versionNegotiation:{mode:{pin:'2026-07-28'}},
            capabilities:{extensions:{'io.modelcontextprotocol/ui':{mimeTypes:['text/html;profile=mcp-app']}}}
        });
        try{
            await client.connect(new StreamableHTTPClientTransport(new URL(url),{fetch:async(input,init)=>{
                const response=await fetch(input,init);
                assert.equal(response.headers.get('set-cookie'),null,'Public MCP created a browser session');
                assert.equal(response.headers.get('mcp-session-id'),null,'Public MCP created a protocol session');
                return response;
            }}));
            if(profile==='modern')assert.deepEqual((await client.discover()).supportedVersions,['2026-07-28','2025-11-25']);
            const {tools}=await client.listTools();
            assert.deepEqual(tools.map(tool=>tool.name),['search_public_products','search_public_articles','get_public_contact','list_public_services','create_cart','get_cart','set_cart_quantity','review_checkout','prepare_checkout_payment','get_checkout_status']);
            assert.ok(tools.every(tool=>tool._meta.ui.resourceUri===
                (['search_public_products','create_cart','get_cart','set_cart_quantity','review_checkout','prepare_checkout_payment','get_checkout_status'].includes(tool.name)?'ui://bapx/public-shopping.html':'ui://bapx/public-knowledge.html')));
            assert.ok(tools.every(tool=>tool.annotations.readOnlyHint===!['create_cart','set_cart_quantity','review_checkout','prepare_checkout_payment','get_checkout_status'].includes(tool.name)));
            const contact=await client.callTool({name:'get_public_contact',arguments:{}});
            assert.equal(contact.isError,undefined);assert.equal(contact.structuredContent.path,'/contact');
            assert.equal(contact._meta['in.bapx/publicSurface'].messages.length,1);
            const services=await client.callTool({name:'list_public_services',arguments:{offset:1,limit:1}});
            assert.equal(services.structuredContent.services.length,1);
            assert.equal(services._meta['in.bapx/publicSurface'].messages[0].createSurface.dataModel.items[0].path,services.structuredContent.services[0].path);
            const products=await client.callTool({name:'search_public_products',arguments:{limit:2}});
            assert.equal(products.isError,undefined);assert.ok(Array.isArray(products.structuredContent.products));
            assert.ok(products.content.some(item=>item.type==='text'&&item.text.length>0));
            if(live){
                assert.ok(products.structuredContent.products.length>0,'Live catalogue returned no published products');
                assert.ok(products._meta['in.bapx/publicSurface'].messages[0].createSurface.dataModel.items.length>0,'Live products have no cards');
            }
            const articles=await client.callTool({name:'search_public_articles',arguments:{limit:2}});
            assert.equal(articles.isError,undefined);assert.ok(Array.isArray(articles.structuredContent.articles));
            const resources=await client.listResources();
            assert.equal(resources.resources[0].uri,'ui://bapx/public-knowledge.html');
            const resource=await client.readResource({uri:resources.resources[0].uri});
            assert.equal(resource.contents[0].mimeType,'text/html;profile=mcp-app');
            assert.ok(resource.contents[0].text.includes('ui/initialize'));
            const shoppingResource=await client.readResource({uri:'ui://bapx/public-shopping.html'});
            assert.ok(shoppingResource.contents[0].text.includes('BapxShopping'));
            if(!live){
                await assert.rejects(client.callTool({name:'paid_sales_summary',arguments:{}}));
                await assert.rejects(client.readResource({uri:'file:///storage/secrets'}));
            }
        }finally{await client.close();}
    }
}

// Live mode performs only public discovery, catalogue/article/contact/service reads and resource reads.
// Native fixture mode also exercises malformed requests and verifies ordinary API session behavior.
test(liveUrl?'deployed public MCP accepts default and modern clients with actual catalogue cards':
    'public HTTP MCP accepts default and modern clients and enforces framing without browser sessions',{timeout:60000},async()=>{
    if(liveUrl){await verifyPublicClients(liveUrl,{live:true});return;}
    const reserve=createServer();reserve.listen(0,'127.0.0.1');await once(reserve,'listening');
    const port=reserve.address().port;await new Promise(resolve=>reserve.close(resolve));
    const url=`http://127.0.0.1:${port}/api/mcp`;
    const child=spawn('php',['-S',`127.0.0.1:${port}`,'index.php'],{
        cwd:path.join(__dirname,'..'),env:{...process.env,BAPX_TEST_MODE:'1',APP_URL:'https://example.test',BAPX_MCP_CLIENT_TOKEN:'fixture-owner-token-must-be-ignored'},
        stdio:['ignore','ignore','pipe']});
    let serverErrors='';child.stderr.on('data',chunk=>{serverErrors+=chunk;});
    try {
        for(let attempt=0;;attempt++){
            try{await fetch(url);break;}catch(error){if(attempt>=60)throw Error('PHP HTTP fixture did not start: '+serverErrors);await new Promise(resolve=>setTimeout(resolve,50));}
        }
        await verifyPublicClients(url);

        const meta={'io.modelcontextprotocol/protocolVersion':'2026-07-28','io.modelcontextprotocol/clientCapabilities':{}};
        const request={jsonrpc:'2.0',id:42,method:'server/discover',params:{_meta:meta}};
        const headers={'Content-Type':'application/json','Accept':'application/json, text/event-stream',
            'MCP-Protocol-Version':'2026-07-28','Mcp-Method':'server/discover'};
        const post=(body=request,extra={})=>fetch(url,{method:'POST',headers:{...headers,...extra},body:typeof body==='string'?body:JSON.stringify(body)});
        const noSession=await post(request,{'Cookie':'PHPSESSID=fixture-owner-session','Authorization':'Bearer fixture-owner-token-must-be-ignored','Mcp-Session-Id':'old-session'});
        assert.equal(noSession.status,200);
        assert.equal(noSession.headers.get('set-cookie'),null);
        assert.equal(noSession.headers.get('mcp-session-id'),null);
        for(const method of ['GET','DELETE'])assert.equal((await fetch(url,{method})).status,405);
        assert.equal((await fetch(url,{method:'OPTIONS',headers:{Origin:'https://example.test'}})).status,204);
        assert.equal((await post(request,{Origin:'https://attacker.example'})).status,403);
        assert.equal((await fetch(url,{method:'OPTIONS',headers:{Origin:'null'}})).status,403);
        assert.equal((await post(request,{'Content-Type':'text/plain'})).status,415);
        assert.equal((await post(request,{Accept:'application/json'})).status,406);
        assert.equal((await post(request,{Accept:'application/json, text/event-stream;q=0'})).status,406);
        const mismatch=await post(request,{'Mcp-Method':'tools/list'});
        assert.equal(mismatch.status,400);assert.equal((await mismatch.json()).error.code,-32020);
        assert.equal((await post('x'.repeat(65537))).status,413);
        const malformed=await post('{');assert.equal(malformed.status,400);assert.equal((await malformed.json()).error.code,-32700);
        const notification=await post({jsonrpc:'2.0',method:'notifications/initialized'});
        assert.equal(notification.status,400);assert.equal(await notification.text(),'');
        const legacyHeaders={'Content-Type':'application/json',Accept:'application/json, text/event-stream','MCP-Protocol-Version':'2025-11-25',
            Cookie:'PHPSESSID=fixture-owner-session',Authorization:'Bearer fixture-owner-token-must-be-ignored','Mcp-Session-Id':'old-session'};
        const legacy=body=>fetch(url,{method:'POST',headers:legacyHeaders,body:JSON.stringify(body)});
        const ready=await legacy({jsonrpc:'2.0',method:'notifications/initialized'});
        assert.equal(ready.status,202);assert.equal(await ready.text(),'');
        assert.equal(ready.headers.get('set-cookie'),null);
        const legacyPublic=await legacy({jsonrpc:'2.0',id:51,method:'tools/list'});
        assert.equal(legacyPublic.status,200);assert.equal(legacyPublic.headers.get('set-cookie'),null);
        assert.equal(legacyPublic.headers.get('mcp-session-id'),null);
        assert.equal((await legacyPublic.json()).result.tools.length,10);
        const privateLegacy=await legacy({jsonrpc:'2.0',id:52,method:'tools/call',params:{name:'paid_sales_summary',arguments:{}}});
        assert.equal((await privateLegacy.json()).error.code,-32602);
        const normal=await fetch(`http://127.0.0.1:${port}/api/knowledge/articles`);
        assert.ok(normal.headers.get('set-cookie'),'Normal API session bootstrap was changed');
    } finally {
        if(child.exitCode===null){child.kill();await once(child,'exit');}
    }
});
