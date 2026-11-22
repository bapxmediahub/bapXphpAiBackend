'use strict';
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {createRequire}=require('node:module');
const path=require('node:path');
const {test}=require('node:test');
const sdk=createRequire(path.resolve(process.argv[2]||'.','package.json'));
const {Client,StreamableHTTPClientTransport}=sdk('@modelcontextprotocol/client');

// Actual SDK encodes and validates messages; custom fetch invokes the real PHP dispatcher without sockets.
// The separate public-mcp-http test covers the native HTTP controller and headers on the wire.
for(const profile of ['default','modern'])test(`official MCP SDK ${profile} client accepts public PHP protocol, schemas, cards and resources`,{timeout:30000},async()=>{
    const seen=[];
    const wire=[];
    const adapter=async(input,init)=>{
        const request=new Request(input,init);
        if(request.method!=='POST')return new Response('',{status:405});
        const body=await request.text();
        seen.push(JSON.parse(body).method);
        const headers=Object.fromEntries(request.headers);
        const reply=JSON.parse(execFileSync('php',['tests/public-mcp-request.php',Buffer.from(body).toString('base64'),Buffer.from(JSON.stringify(headers)).toString('base64')],
            {cwd:path.join(__dirname,'..'),encoding:'utf8',maxBuffer:1024*1024}));
        wire.push({request:JSON.parse(body),headers,response:reply});
        return new Response(reply.body===null?null:JSON.stringify(reply.body),{status:reply.status,headers:{'Content-Type':'application/json'}});
    };
    // The default profile is deliberately the unmodified SDK configuration, with no UI advertisement.
    const info={name:'public-php-sdk-fixture',version:'1'};
    const client=profile==='default' ? new Client(info) : new Client(info,{
        versionNegotiation:{mode:{pin:'2026-07-28'}},
        capabilities:{extensions:{'io.modelcontextprotocol/ui':{mimeTypes:['text/html;profile=mcp-app']}}}
    });
    try {
        await client.connect(new StreamableHTTPClientTransport(new URL('https://example.test/api/mcp'),{fetch:adapter}));
        if(profile==='modern')assert.deepEqual((await client.discover()).supportedVersions,['2026-07-28','2025-11-25']);
        else assert.equal(client.getServerVersion().name,'bapx-public-knowledge');
        const tools=(await client.listTools()).tools;
        assert.equal(tools.length,10);
        assert.ok(tools.every(tool=>tool._meta.ui.resourceUri===
            (['search_public_products','create_cart','get_cart','set_cart_quantity','review_checkout','prepare_checkout_payment','get_checkout_status'].includes(tool.name)?'ui://bapx/public-shopping.html':'ui://bapx/public-knowledge.html')));
        for(const name of ['get_public_contact','list_public_services','search_public_products','search_public_articles']) {
            const result=await client.callTool({name,arguments:{}});
            assert.equal(result.isError,undefined);
            assert.ok(result.structuredContent);
            assert.ok(result._meta['in.bapx/publicSurface']);
        }
        const resources=await client.listResources();
        assert.equal(resources.resources[0].uri,'ui://bapx/public-knowledge.html');
        const resource=await client.readResource({uri:resources.resources[0].uri});
        assert.equal(resource.contents[0].mimeType,'text/html;profile=mcp-app');
        const shopping=await client.readResource({uri:'ui://bapx/public-shopping.html'});
        assert.ok(shopping.contents[0].text.includes('BapxShopping'));
        await assert.rejects(client.callTool({name:'paid_sales_summary',arguments:{}}));
        if(profile==='modern')assert.ok(!seen.includes('initialize') && seen.includes('server/discover'));
        else {
            assert.equal(seen[0],'initialize');
            assert.equal(wire[0].request.params.protocolVersion,'2025-11-25');
            assert.ok(seen.includes('notifications/initialized'));
            assert.ok(!seen.includes('server/discover'));
            const initialized=wire.find(item=>item.request.method==='notifications/initialized').response;
            assert.equal(initialized.status,202);assert.equal(initialized.body,null);
            for(const item of wire.filter(item=>item.response.body?.result)) {
                assert.equal(item.response.body.result.resultType,undefined);
                assert.equal(item.response.body.result.ttlMs,undefined);
            }
        }
    } finally { await client.close(); }
});
