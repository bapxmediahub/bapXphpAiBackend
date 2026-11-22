'use strict';
// Read-only production acceptance through real curl. No cookies, credentials,
// browser session, cart capability or write tool is used by this probe.
const assert=require('node:assert/strict');
const {execFile}=require('node:child_process');
const {promisify}=require('node:util');
const {test}=require('node:test');
const execute=promisify(execFile);
const origin=new URL(process.env.PUBLIC_AGENT_ORIGIN||'https://sripanchamispiritual.com');
assert.equal(origin.protocol,'https:');
assert.equal(origin.href,origin.origin+'/','Use an HTTPS origin without credentials, path, query or fragment.');
assert.ok(!origin.username&&!origin.password);

async function curlJson(path,example){
    const url=origin.origin+path;
    const args=['--fail-with-body','--silent','--show-error','--max-time','20'];
    if(example){
        assert.equal(example.url,url,'Discovery cannot redirect the probe to another destination.');
        assert.equal(example.method,'POST');
        assert.equal(example.body.method,example.id==='list_tools'?'tools/list':'tools/call');
        if(example.id!=='list_tools')assert.equal(example.body.params.name,'get_public_contact');
        const allowed=['Content-Type','Accept','MCP-Protocol-Version','Mcp-Method','Mcp-Name'];
        for(const [name,value] of Object.entries(example.headers)){
            assert.ok(allowed.includes(name)&&typeof value==='string'&&!/[\r\n]/.test(value));
            args.push('-H',name+': '+value);
        }
        args.push('--data-raw',JSON.stringify(example.body));
    }
    args.push(url);
    const {stdout}=await execute('curl',args,{timeout:25000,maxBuffer:1024*1024});
    const data=JSON.parse(stdout);
    assert.equal(data.error,undefined,'Public curl request returned a JSON-RPC error.');
    assert.notEqual(data.result?.isError,true,'Public curl request returned a tool error.');
    return data;
}

test('live discovery and published curl requests work without a browser',{timeout:150000},async()=>{
    const discovery=await curlJson('/api/agent-tools');
    assert.equal(discovery.format,'bapx.agent-tools');
    assert.equal(discovery.version,1);
    assert.equal(discovery.origin,origin.origin);
    assert.equal(discovery.transport.url,origin.origin+'/api/mcp');
    assert.equal(discovery.guideUrl,origin.origin+'/ai-assistants');
    assert.equal(discovery.instructionsUrl,origin.origin+'/llms.txt');
    const example=id=>{
        const matches=discovery.examples.filter(value=>value.id===id);
        assert.equal(matches.length,1,'Discovery must include one '+id+' request.');
        return matches[0];
    };
    const listed=await curlJson('/api/mcp',example('list_tools'));
    assert.deepEqual(listed.result.tools,discovery.tools,'HTTP discovery must match the actual MCP registry.');
    const contact=await curlJson('/api/mcp',example('read_contact_mcp'));
    assert.equal(contact.result.structuredContent.path,'/contact');
    const httpContact=await curlJson('/api/knowledge/contact');
    assert.deepEqual(contact.result.structuredContent,httpContact,'Curl MCP and ordinary HTTP must share public facts.');
    const data=await curlJson('/api/knowledge/products?limit=1');
    assert.equal(data.products.length,1,'Live acceptance requires one published product.');
    const product=data.products[0];
    assert.match(product.path,/^\/product\/[A-Za-z0-9%_.-]+$/);
    assert.equal(product.productUrl,origin.origin+product.path);
    assert.equal(typeof product.priceInr,'number');
    assert.equal(typeof product.available,'boolean');
    if(product.imagePath!==undefined){
        assert.match(product.imagePath,/^\/assets\/images\/[A-Za-z0-9%_./-]+$/);
        assert.equal(product.imageUrl,origin.origin+product.imagePath);
        assert.equal(new URL(product.imageUrl).pathname,product.imagePath,'Official image paths must already be canonical.');
        const {stdout}=await execute('curl',['--fail-with-body','--silent','--show-error','--max-time','20',
            '--output','/dev/null','--write-out','%{http_code} %{content_type}',product.imageUrl],{timeout:25000,maxBuffer:4096});
        assert.match(stdout,/^200 image\/(?:png|jpe?g|webp|avif|gif)(?:;.*)?$/,'The official product image must load as a raster image.');
    }else assert.equal(product.imageUrl,undefined,'Missing photos cannot gain an invented absolute URL.');
    console.log('LIVE CURL PASS: actual registry, public contact over MCP/GET, official product URL and available image; no browser or writes.');
});
