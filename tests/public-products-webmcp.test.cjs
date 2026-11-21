'use strict';
const assert=require('node:assert/strict');
const {readFileSync}=require('node:fs');
const {join}=require('node:path');
const vm=require('node:vm');
const {test}=require('node:test');

const source=readFileSync(join(__dirname,'../assets/public-products-webmcp.js'),'utf8');
const contract={
    inputSchema:{type:'object',additionalProperties:false,
        properties:{query:{type:'string',maxLength:120},
            offset:{type:'integer',minimum:0,maximum:2147483647},
            limit:{type:'integer',minimum:1,maximum:20}}},
    outputSchema:{type:'object',additionalProperties:false,
        required:['products','total','nextOffset'],properties:{
            products:{type:'array',maxItems:20},total:{type:'integer'},nextOffset:{type:['integer','null']}}}
};
function setup(payload,enabled=true) {
    let registration,handle;
    const calls=[];
    const modelContext=enabled?{registerTool(tool,opts){registration={tool,opts};return Promise.resolve()}}:undefined;
    vm.runInNewContext(source,{
        document:{modelContext,getElementById:id=>id==='bapx-public-products-schema'
            ?{textContent:JSON.stringify(contract)}:null},
        window:{location:{origin:'https://shop.example.test'},
            addEventListener:(name,listener)=>{if(name==='pagehide')handle=listener}},
        AbortController,URL,
        fetch:async(url,opts)=>{calls.push({url:String(url),opts});return {ok:true,json:async()=>payload}},
        console:{warn(){}}
    });
    return {get tool(){return registration?.tool},get signal(){return registration?.opts.signal},
        get event(){return handle},calls};
}

test('browser registers canonical public catalogue tool and PHP-compatible schemas',()=>{
    const ctx=setup({products:[],total:0,nextOffset:null});
    assert.equal(ctx.tool.name,'search_public_products');
    assert.deepEqual(JSON.parse(JSON.stringify(ctx.tool.inputSchema)),contract.inputSchema);
    assert.deepEqual(JSON.parse(JSON.stringify(ctx.tool.outputSchema)),contract.outputSchema);
    assert.equal(ctx.tool.annotations.readOnlyHint,true);
    assert.equal(setup({},false).tool,undefined);
    ctx.event({persisted:true});
    assert.equal(ctx.signal.aborted,false);
    ctx.event({persisted:false});
    assert.equal(ctx.signal.aborted,true);
});

test('product tool supports bounded remote-free public API paging with omitted credentials',async()=>{
    const result={products:[{name:'Item A',path:'/product/item-a',priceInr:99,available:true}],total:30,nextOffset:1};
    const ctx=setup(result);
    const answer=await ctx.tool.execute({query:'A',limit:1,offset:0});
    assert.deepEqual(JSON.parse(JSON.stringify(answer)),result);
    assert.equal(ctx.calls.length,1);
    const url=new URL(ctx.calls[0].url);
    assert.equal(url.pathname,'/api/knowledge/products');
    assert.equal(url.searchParams.get('q'),'A');
    assert.equal(url.searchParams.get('limit'),'1');
    assert.equal(ctx.calls[0].opts.credentials,'omit');
    assert.equal(ctx.calls[0].opts.method,'GET');
});

test('product tool rejects invalid input and private/external or malformed output',async()=>{
    const wrong={products:[{name:'Owner secret',path:'/admin/docs/settings',priceInr:1,available:true}],total:1,nextOffset:null};
    const ctx=setup(wrong);
    await assert.rejects(ctx.tool.execute({limit:21}),{name:'TypeError'});
    await assert.rejects(ctx.tool.execute({offset:-1}),{name:'TypeError'});
    await assert.rejects(ctx.tool.execute({query:'x',actor:'admin'}),{name:'TypeError'});
    await assert.rejects(ctx.tool.execute({}),{name:'TypeError'});
    for(const bad of [
        {products:[{name:'Leak',path:'https://untrusted.invalid',priceInr:1,available:true}],total:1,nextOffset:null},
        {products:[{name:'Unpriced',path:'/product/price',priceInr:'N/A',available:true}],total:1,nextOffset:null},
        {products:[{name:'Unknown',path:'/product/unknown',priceInr:12,available:'yes'}],total:1,nextOffset:null},
        {products:[],total:-1,nextOffset:1},
        {products:[],total:0,nextOffset:'all'}
    ]) {
        await assert.rejects(setup(bad).tool.execute({}),{name:'TypeError'});
    }
});
