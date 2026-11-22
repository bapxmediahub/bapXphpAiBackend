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
            limit:{type:'integer',minimum:1,maximum:20},
            slugs:{type:'array',maxItems:3,uniqueItems:true,items:{type:'string',minLength:1,maxLength:2039}}}},
    outputSchema:{type:'object',additionalProperties:false,
        required:['products','total','nextOffset'],properties:{
            products:{type:'array',maxItems:20},total:{type:'integer'},nextOffset:{type:['integer','null']}}}
};
function setup(payload,enabled=true,mode='promise') {
    let registration,handle;
    const calls=[];
    const warnings=[];
    const modelContext=enabled?{registerTool(tool,opts){
        if(mode==='throw')throw new Error('Registration denied');
        registration={tool,opts};return mode==='void'?undefined:Promise.resolve();
    }}:undefined;
    vm.runInNewContext(source,{
        document:{modelContext,getElementById:id=>id==='bapx-public-products-schema'
            ?{textContent:JSON.stringify(contract)}:null},
        window:{location:{origin:'https://shop.example.test'},
            addEventListener:(name,listener)=>{if(name==='pagehide')handle=listener}},
        AbortController,URL,
        fetch:async(url,opts)=>{calls.push({url:String(url),opts});return {ok:true,json:async()=>payload}},
        console:{warn(message){warnings.push(message)}}
    });
    return {get tool(){return registration?.tool},get signal(){return registration?.opts.signal},
        get event(){return handle},calls,warnings};
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
    const result={products:[{name:'Item A',path:'/product/item-a',priceInr:99,available:true,
        description:'Published product description',imagePath:'/assets/images/item-a.webp'}],total:30,nextOffset:1};
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

test('product registration supports synchronous native APIs and catches synchronous refusal',async()=>{
    assert.equal(setup({},true,'void').tool.name,'search_public_products');
    const denied=setup({},true,'throw');
    await new Promise(resolve=>setImmediate(resolve));
    assert.equal(denied.tool,undefined);
    assert.equal(denied.warnings.length,1);
});

test('exact selections forward raw Unicode identities and accept bounded selected details',async()=>{
    const row={name:'Selected',path:'/product/item',priceInr:10,available:true,description:'Published',
        imagePath:'/assets/images/one.webp',imagePaths:['/assets/images/one.webp','/assets/images/two.png'],
        highlights:['Published highlight'],specifications:[{label:'Material',value:'Published material'}]};
    const result={products:[row],total:1,nextOffset:null};
    const ctx=setup(result);
    assert.deepEqual(JSON.parse(JSON.stringify(await ctx.tool.execute({slugs:['தமிழ் pendant','item'],limit:2}))),result);
    assert.deepEqual(new URL(ctx.calls[0].url).searchParams.getAll('slugs[]'),['தமிழ் pendant','item']);
    for(const args of [{slugs:'item'},{slugs:['item','item']},{slugs:['a','b','c','d']},
        {slugs:['item'],query:'item'},{slugs:['item'],offset:1},{slugs:['a','b'],limit:1},
        {slugs:['../private']},{slugs:['item%2Fprivate']},{slugs:['்item']},{slugs:Array(1)}]) {
        const invalid=setup(result);
        await assert.rejects(invalid.tool.execute(args),{name:'TypeError'});
        assert.equal(invalid.calls.length,0);
    }
    const unicode=setup(result);
    await unicode.tool.execute({query:'𐐀'.repeat(120)});
    await assert.rejects(unicode.tool.execute({query:'𐐀'.repeat(121)}),{name:'TypeError'});
    for(const extra of [{imagePaths:['/assets/images/one.webp','/assets/images/one.webp']},
        {imagePaths:['https://foreign.example/image.png']},{imagePaths:Array(4).fill('/assets/images/one.webp')},
        {highlights:['x'.repeat(161)]},{highlights:Array(5).fill('Visible')},
        {specifications:[{label:'Material',value:'Published',privateCost:20}]},
        {specifications:[{label:'Material',value:'x'.repeat(241)}]}]) {
        await assert.rejects(setup({...result,products:[{...row,...extra}]}).tool.execute(),{name:'TypeError'});
    }
});

test('shop snapshot adds only visible bounded descriptions and safe local images',()=>{
    const shopSource=readFileSync(join(__dirname,'../assets/shop-webmcp.js'),'utf8');
    const card=(name,href,image,description='Visible description')=>({querySelector(selector){
        return ({'a.product-card__title[href]':{textContent:name,href},'.price':{textContent:'₹100'},
            '[data-cart-control]':{},'.product-card__desc':{textContent:description},
            '.product-card__image img':{currentSrc:image}})[selector]||null;
    }});
    const cards=[card('𐐀'.repeat(160),'https://shop.example.test/product/'+encodeURIComponent('தமிழ் pendant'),
        'https://shop.example.test/assets/images/'+encodeURIComponent('தமிழ்')+'.png','𐐀'.repeat(300)),
        card('Other','https://shop.example.test/product/other','https://outside.test/image.webp'),
        card('Unsafe','https://shop.example.test/product/..%2Fadmin','/assets/images/one.webp')];
    let tool;
    vm.runInNewContext(shopSource,{document:{modelContext:{registerTool(value){tool=value;}},
        querySelector:()=>({querySelectorAll:()=>cards})},window:{location:{origin:'https://shop.example.test',
        href:'https://shop.example.test/shop'},addEventListener(){}},AbortController,URL,console});
    const result=tool.execute({limit:2});
    assert.equal(result.total,3);
    assert.equal(result.nextOffset,2);
    assert.equal(result.products.length,2);
    assert.equal(Array.from(result.products[0].description).length,280);
    assert.equal(result.products[0].imagePath,'/assets/images/'+encodeURIComponent('தமிழ்')+'.png');
    assert.equal(result.products[1].imagePath,undefined);
    assert.equal(tool.execute({offset:2}).products.length,0);
    assert.match(tool.description,/current search, category and sort/);
    assert.match(tool.description,/full published catalogue/);
    for(const image of ['/assets/images/../images/one.webp','/assets/images/%2e%2e/images/one.webp',
        '/assets/images/item%2Fprivate.webp','/assets/images/item%252Fprivate.webp']) {
        cards[0]=card('Valid','https://shop.example.test/product/valid',image);
        assert.equal(tool.execute({limit:1}).products[0].imagePath,undefined);
    }
});

test('product projection rejects extra fields and unsafe image paths without losing safe descriptions',async()=>{
    const product={name:'Item',path:'/product/item',priceInr:10,available:true,description:'A short description'};
    const payload=row=>({products:[row],total:1,nextOffset:null});
    assert.equal((await setup(payload(product)).tool.execute()).products[0].description,product.description);
    const tamilImage='/assets/images/media/%E0%AE%A4%E0%AE%AE%E0%AE%BF%E0%AE%B4%E0%AF%8D.png';
    assert.equal((await setup(payload({...product,imagePath:tamilImage})).tool.execute()).products[0].imagePath,tamilImage);
    for(const imagePath of ['https://outside.test/image.png','/assets/images/../private.png',
        '/assets/images/%2e%2e/private.png','/assets/images/%2Fprivate.png',
        '/assets/images/image.svg','data:image/png;base64,xxx','/assets/images/%E0%AF%8Ditem.png']) {
        await assert.rejects(setup(payload({...product,imagePath})).tool.execute(),{name:'TypeError'});
    }
    await assert.rejects(setup(payload({...product,adminEmail:'private@example.test'})).tool.execute(),{name:'TypeError'});
    await assert.rejects(setup(payload({...product,description:'x'.repeat(281)})).tool.execute(),{name:'TypeError'});
    await assert.rejects(setup({...payload(product),owner:{name:'private'}}).tool.execute(),{name:'TypeError'});
});

test('product paths retain safe encoded legacy slugs and reject browser-normalized or encoded traversal',async()=>{
    const product={name:'𐐀'.repeat(160),path:'/product/item',priceInr:10,available:true,description:''};
    const payload=row=>({products:[row],total:1,nextOffset:null});
    for(const path of ['/product/legacy%20item.v2','/product/%E7%8F%8A%E7%91%9A%20pendant','/product/_legacy-2',
        '/product/%E0%AE%A4%E0%AE%AE%E0%AE%BF%E0%AE%B4%E0%AF%8D%20pendant','/product/'+'x'.repeat(2039)]) {
        assert.equal((await setup(payload({...product,path})).tool.execute()).products[0].path,path);
    }
    for(const path of ['/product/.','/product/..','/product/%2e%2e','/product/..%2fadmin',
        '/product/item%2Fprivate','/product/item%5cprivate','/product/item%00name','/product/item%0Aname',
        '/product/%252e%252e','/product/item%252Fprivate','/product/%FF','/product/',
        '/product/%E0%AF%8Ditem','/product/'+'x'.repeat(2040),'/product/'+encodeURIComponent('தமிழ்'.repeat(46))]) {
        await assert.rejects(setup(payload({...product,path})).tool.execute(),{name:'TypeError'});
    }
    await assert.rejects(setup(payload({...product,name:'𐐀'.repeat(161)})).tool.execute(),{name:'TypeError'});
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
