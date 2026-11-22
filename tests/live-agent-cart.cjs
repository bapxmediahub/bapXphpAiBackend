'use strict';
// Explicit post-deployment acceptance only. Creates one expiring anonymous cart,
// adds one published item, reads it, removes it and verifies empty. Never delivery/payment/order.
const assert=require('node:assert/strict');
const {execFile}=require('node:child_process');
const {test}=require('node:test');
const endpoint=new URL(process.env.PUBLIC_MCP_URL||'https://sripanchamispiritual.com/api/mcp');
assert.equal(endpoint.protocol,'https:');assert.equal(endpoint.pathname,'/api/mcp');
assert.ok(!endpoint.username&&!endpoint.password&&!endpoint.search&&!endpoint.hash);
const origin=endpoint.origin;
const tokenPattern=/^cart_[a-f0-9]{32}\.[a-f0-9]{64}$/;
const segment=/^[\p{L}\p{N}_][\p{L}\p{N}\p{M}._ -]*$/u;
let nextId=0;
function execute(args,input=''){
    return new Promise((resolve,reject)=>{
        const child=execFile('curl',args,{timeout:25000,maxBuffer:1024*1024},(error,stdout)=>{
            if(error)reject(Error('A bounded live HTTP request failed.'));
            else resolve(stdout);
        });
        child.stdin.on('error',()=>{});child.stdin.end(input);
    });
}
async function call(name,args){
    assert.ok(['search_public_products','create_cart','get_cart','set_cart_quantity'].includes(name));
    const id=++nextId;
    const body={jsonrpc:'2.0',id,method:'tools/call',params:{name,arguments:args,
        _meta:{'io.modelcontextprotocol/protocolVersion':'2026-07-28','io.modelcontextprotocol/clientCapabilities':{}}}};
    let response;
    try{
        response=JSON.parse(await execute(['--fail-with-body','--silent','--show-error','--max-time','20',
            '-H','Content-Type: application/json','-H','Accept: application/json, text/event-stream',
            '-H','MCP-Protocol-Version: 2026-07-28','-H','Mcp-Method: tools/call','-H','Mcp-Name: '+name,
            '--data-binary','@-',endpoint.href],JSON.stringify(body)));
    }catch(_){throw Error('Live '+name+' transport or JSON response failed.');}
    assert.equal(response.jsonrpc,'2.0');assert.equal(response.id,id);
    assert.ok(!response.error,'Live '+name+' returned an RPC error.');
    assert.ok(!response.result?.isError,'Live '+name+' returned a tool refusal.');
    assert.ok(response.result?.structuredContent,'Live '+name+' omitted structured data.');
    return response.result;
}
function cartShape(cart,quantity,slug){
    assert.equal(cart.status,'open');assert.equal(cart.currency,'INR');assert.ok(Number.isSafeInteger(cart.revision));
    assert.equal(cart.itemCount,quantity);assert.equal(cart.items.length,quantity?1:0);assert.deepEqual(cart.unavailableSlugs,[]);
    if(!quantity){assert.equal(cart.estimatedTotalInr,0);return;}
    const item=cart.items[0];assert.equal(item.slug,slug);assert.equal(item.quantity,1);assert.equal(item.available,true);
    assert.ok(Number.isFinite(item.unitPriceInr)&&item.unitPriceInr>0&&item.unitPriceInr<=1000000000);
    assert.equal(Math.round(item.lineTotalInr*100),Math.round(item.unitPriceInr*100));
    assert.equal(Math.round(cart.estimatedTotalInr*100),Math.round(item.lineTotalInr*100));
    assert.equal(item.productUrl,origin+item.path);
}

test('live curl cart actions are browserless, revision-checked and cleaned back to empty',{timeout:180000},async()=>{
    let token=null,slug=null,cleanupRequired=false,failure=null,cleanupFailure=false;
    try{
        for(const [path,expected] of [['/api/payments/razorpay-webhook','405'],['/checkout/payment','400']]){
            const status=await execute(['--silent','--show-error','--max-time','20','--output','/dev/null','--write-out','%{http_code}',origin+path]);
            assert.equal(status,expected,'Live payment route must reject a request without its method or capability.');
        }
        const result=await call('search_public_products',{limit:5});
        const item=result.structuredContent.products.find(product=>product.available&&Number.isFinite(product.priceInr)&&product.priceInr>0&&product.priceInr<=1000000000);
        assert.ok(item,'Live acceptance needs one available, positively priced published product.');
        assert.ok(typeof item.path==='string'&&item.path.length<=2048&&/^\/product\/[A-Za-z0-9%_.-]+$/.test(item.path));
        slug=decodeURIComponent(item.path.slice(9));assert.ok(segment.test(slug));assert.equal(item.productUrl,origin+item.path);
        if(item.imagePath!==undefined){
            assert.ok(typeof item.imagePath==='string'&&item.imagePath.length<=512
                &&/^\/assets\/images\/[A-Za-z0-9%_./-]+\.(?:avif|gif|jpe?g|png|webp)$/i.test(item.imagePath));
            assert.ok(item.imagePath.slice(15).split('/').every(part=>segment.test(decodeURIComponent(part))));
            assert.equal(item.imageUrl,origin+item.imagePath);
            const type=await execute(['--fail-with-body','--silent','--show-error','--max-time','20','--max-filesize','20971520',
                '--output','/dev/null','--write-out','%{http_code} %{content_type}',item.imageUrl]);
            assert.match(type,/^200 image\/(?:png|gif|jpe?g|webp|avif)(?:;|$)/i,'Official product photo did not return a supported raster response.');
        }
        const created=await call('create_cart',{});token=created.structuredContent.cartToken;
        assert.ok(typeof token==='string'&&tokenPattern.test(token));
        assert.ok(!JSON.stringify(created.content).includes(token),'Cart handle escaped into human text.');
        cartShape(created.structuredContent,0,slug);
        cleanupRequired=true;
        const changed=(await call('set_cart_quantity',{cartToken:token,slug,quantity:1,expectedRevision:created.structuredContent.revision})).structuredContent;
        cartShape(changed,1,slug);assert.equal(changed.revision,created.structuredContent.revision+1);
        const read=(await call('get_cart',{cartToken:token})).structuredContent;
        cartShape(read,1,slug);assert.equal(read.revision,changed.revision);
    }catch(error){failure=error;}
    finally{
        if(token&&cleanupRequired){
            try{
                // Read once before cleanup in case the successful quantity response was lost.
                const current=(await call('get_cart',{cartToken:token})).structuredContent;
                if(current.items.some(item=>item.slug===slug)||current.unavailableSlugs.includes(slug)){
                    const empty=(await call('set_cart_quantity',{cartToken:token,slug,quantity:0,expectedRevision:current.revision})).structuredContent;
                    cartShape(empty,0,slug);assert.equal(empty.revision,current.revision+1);
                }
                cartShape((await call('get_cart',{cartToken:token})).structuredContent,0,slug);
            }catch(_){cleanupFailure=true;}
        }
        token=null;
    }
    assert.equal(cleanupFailure,false,'Live cart cleanup was not confirmed; inspect the bounded acceptance cart before repeating.');
    if(failure)throw failure;
});
