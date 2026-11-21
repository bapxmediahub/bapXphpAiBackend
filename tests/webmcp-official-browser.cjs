'use strict';
/**
 * Tests the REAL @w3c/webmcp-polyfill@0.1.1 standalone browser build in Chrome.
 * The sample records and API response are synthetic; no production requests.
 */
const assert = require('node:assert/strict');
const {createServer}=require('node:http');
const {readFileSync}=require('node:fs');
const {join,resolve}=require('node:path');
const {createHash}=require('node:crypto');
const {once}=require('node:events');
const fixture=process.argv[2];
if(!fixture)throw new Error('Provide isolated npm fixture directory');
const installed=resolve(fixture,'node_modules');
const playwright=require(join(installed,'playwright'));
const upstream=readFileSync(join(installed,'@w3c','webmcp-polyfill','dist','polyfill.js'));
const polyfill=readFileSync(join(__dirname,'../assets/vendor/webmcp/polyfill-0.1.1.js'));
const digest=data=>createHash('sha256').update(data).digest('hex');
assert.equal(digest(polyfill),digest(upstream),'Vendored standalone bundle must match official 0.1.1 npm release');
const shopSource=readFileSync(join(__dirname,'../assets/shop-webmcp.js'));
const knowledgeSource=readFileSync(join(__dirname,'../assets/public-knowledge-webmcp.js'));
const productSource=readFileSync(join(__dirname,'../assets/public-products-webmcp.js'));
const apiProducts=Array.from({length:67},(_,i)=>({name:'Fixture item '+i,path:'/product/fixture-'+i,priceInr:i+1,available:i%2===0}));
const apiRecords=[
  {title:'Public help guide',path:'/blog/public-help',category:'help',score:2},
  {title:'Public article',path:'/blog/public-article',category:'guides',score:1}
];
const articleSchema={
  inputSchema:{
    type:'object',additionalProperties:false,properties:{
      query:{type:'string',maxLength:120},
      offset:{type:'integer',minimum:0,maximum:2147483647},
      limit:{type:'integer',minimum:1,maximum:20},
    }
  },
  outputSchema:{
    type:'object',additionalProperties:false,
    properties:{
      articles:{type:'array',maxItems:20,items:{
        type:'object',additionalProperties:false,
        properties:{
          title:{type:'string',minLength:1},
          path:{type:'string',pattern:'^/blog/[a-z0-9-]+$'},
          category:{type:'string'},
          score:{type:'integer',minimum:1}
        },required:['title','path','category','score']
      }},
      total:{type:'integer',minimum:0},
      nextOffset:{type:['integer','null'],minimum:0}
    },required:['articles','total','nextOffset']
  }
};
const productSchema={
  inputSchema:articleSchema.inputSchema,
  outputSchema:{type:'object',additionalProperties:false,
    required:['products','total','nextOffset'],properties:{
      products:{type:'array',maxItems:20,items:{type:'object',additionalProperties:false,
        properties:{name:{type:'string',minLength:1},path:{type:'string',pattern:'^/product/[^/?#]+
  return Array.from({length:count},(_,i)=>'<article class="product-card">'+
    '<a class="product-card__title" href="/product/item-'+i+'">Item '+i+'</a>'+
    '<span class="price">₹'+(i+1)+'</span>'+
    '<div data-cart-control></div></article>').join('\n');
}
const server=createServer((req,res)=>{
  const url=new URL(req.url,'http://localhost');
  if(url.pathname==='/polyfill.js'){
    res.writeHead(200,{'content-type':'application/javascript'});res.end(polyfill);return;
  }
  if(url.pathname==='/assets/shop-webmcp.js'){
    res.writeHead(200,{'content-type':'application/javascript'});res.end(shopSource);return;
  }
  if(url.pathname==='/assets/public-products-webmcp.js'){
    res.writeHead(200,{'content-type':'application/javascript'});res.end(productSource);return;
  }
  if(url.pathname==='/assets/public-knowledge-webmcp.js'){
    res.writeHead(200,{'content-type':'application/javascript'});res.end(knowledgeSource);return;
  }
  if(url.pathname==='/api/knowledge/products'){
    const offset=Number(url.searchParams.get('offset')||0);
    const size=Number(url.searchParams.get('limit')||20);
    const query=(url.searchParams.get('q')||'').toLowerCase();
    const matching=apiProducts.filter(p=>p.name.toLowerCase().includes(query));
    const end=Math.min(matching.length,offset+size);
    res.writeHead(200,{'content-type':'application/json','cache-control':'no-store'});
    res.end(JSON.stringify({products:matching.slice(offset,end),total:matching.length,nextOffset:end<matching.length?end:null}));return;
  }
  if(url.pathname==='/api/knowledge/articles'){
    const offset=Number(url.searchParams.get('offset')||0);
    const size=Number(url.searchParams.get('limit')||5);
    const query=url.searchParams.get('q')||'';
    const matches=apiRecords.filter(x=>!query||x.title.toLowerCase().includes(query.toLowerCase()));
    const end=Math.min(matches.length,offset+size);
    res.writeHead(200,{'content-type':'application/json','cache-control':'no-store'});
    res.end(JSON.stringify({articles:matches.slice(offset,end),total:matches.length,nextOffset:end<matches.length?end:null}));return;
  }
  if(url.pathname==='/'||url.pathname==='/without-polyfill'){
    const load=url.pathname==='/'?'<script src="/polyfill.js"></script>':'';
    const html='<!doctype html><html><head><title>WebMCP test</title>'+load+
      '<script id="bapx-public-knowledge-schema" type="application/json">'+JSON.stringify(articleSchema)+'</script>'+ 
      '<script id="bapx-public-products-schema" type="application/json">'+JSON.stringify(productSchema)+'</script></head>'+ 
      '<body><div class="shop-results">'+cards(45)+'</div>'+
      '<script src="/assets/shop-webmcp.js" defer></script>'+
      '<script src="/assets/public-knowledge-webmcp.js" defer></script>'+ 
      '<script src="/assets/public-products-webmcp.js" defer></script></body></html>';
    res.writeHead(200,{'content-type':'text/html; charset=utf-8'});res.end(html);return;
  }
  res.writeHead(404);res.end('Not found');
});
async function main(){
  server.listen(0,'127.0.0.1');await once(server,'listening');
  const port=server.address().port;
  const browser=await playwright.chromium.launch({
    headless:true,
    executablePath:process.env.CHROMIUM_PATH||undefined,
    // The official polyfill must own the context in this test, not Chrome's native
    // Origin Trial implementation (which can reject calls under its permissions policy).
    args:['--no-sandbox','--disable-features=WebMCP'],
  });
  try{
    const page=await browser.newPage();
    const errors=[];page.on('pageerror',e=>errors.push(e.message));
    await page.goto('http://127.0.0.1:'+port+'/',{waitUntil:'load'});
    await page.waitForFunction(async()=>typeof document.modelContext?.getTools==='function'
      && (await document.modelContext.getTools()).some(x=>x.name==='list_shop_results'),{timeout:15000});
    const result=await page.evaluate(async()=>{
      const ctx=document.modelContext;
      const list=await ctx.getTools();
      const shop=list.find(x=>x.name==='list_shop_results');
      const article=list.find(x=>x.name==='search_public_articles');
      const product=list.find(x=>x.name==='search_public_products');
      if(!shop||!article||!product)throw Error('All three public tools must be discoverable with official polyfill');
      const call=async(t,input)=>JSON.parse(await ctx.executeTool(t,input));
      const a=await call(shop,{offset:0,limit:20});
      const b=await call(shop,{offset:a.nextOffset,limit:20});
      const c=await call(shop,{offset:b.nextOffset,limit:20});
      const knowledge=await call(article,{query:'public',offset:0,limit:1});
      const backend=[];
      let cursor=0;
      while(cursor!==null){
        const page=await call(product,{offset:cursor,limit:20});
        backend.push(page);
        cursor=page.nextOffset;
        if(backend.length>10)throw Error('Unbounded product pagination loop');
      }
      let rejected=false;
      try{await ctx.executeTool(shop,{offset:-1})}catch(e){rejected=true;}
      const malformed=document.querySelectorAll('.product-card')[41];
      malformed.querySelector('.price').remove();
      document.querySelectorAll('.product-card')[42].querySelector('a.product-card__title').href='https://untrusted.invalid/product/evil';
      const skip=await call(shop,{offset:40,limit:5});
      return {names:list.map(t=>t.name),schema:shop.outputSchema||null,
        backendSchema:product.outputSchema||null,backend,
        a,b,c,knowledge,rejected,skip};
    });
    assert.deepEqual([result.a.products.length,result.b.products.length,result.c.products.length],[20,20,5]);
    assert.deepEqual([result.a.nextOffset,result.b.nextOffset,result.c.nextOffset],[20,40,null]);
    assert.deepEqual(result.backend.map(x=>x.products.length),[20,20,20,7]);
    assert.equal(result.backend[0].total,67,'Backend must page beyond the 45 visible DOM cards');
    assert.deepEqual(result.backend.map(x=>x.nextOffset),[20,40,60,null]);
    assert.deepEqual(result.backendSchema.required,['products','total','nextOffset']);
    assert.equal(result.knowledge.articles.length,1);
    assert.equal(result.knowledge.total,2);
    assert.equal(result.knowledge.nextOffset,1);
    assert.equal(result.rejected,true,'Invalid offset must reject even under the real polyfill');
    assert.equal(result.skip.products.length,3,'Missing price and cross-origin links must be rejected');
    assert.equal(result.skip.nextOffset,null);
    assert.equal(result.skip.total,45);
    assert.ok(result.schema,'Discovered shop tool should include outputSchema');
    assert.deepEqual(result.schema.required,['products','total','nextOffset']);
    assert.deepEqual(errors,[],'No uncaught Chrome exceptions');
    const lifecycle=await page.evaluate(async()=>{
      window.dispatchEvent(new PageTransitionEvent('pagehide',{persisted:false}));
      try {
        return {names:(await document.modelContext.getTools()).map(x=>x.name),blocked:false};
      } catch(e) {
        // The official polyfill may disable ALL discovery after pagehide.
        return {names:[],blocked:['NotAllowedError','InvalidStateError'].includes(e.name)};
      }
    });
    assert.ok(lifecycle.blocked || (!lifecycle.names.includes('list_shop_results')
      && !lifecycle.names.includes('search_public_articles')
      && !lifecycle.names.includes('search_public_products')),
      'A pagehide must remove registered tools or render document discovery unavailable');

    const without=await browser.newPage();
    await without.goto('http://127.0.0.1:'+port+'/without-polyfill',{waitUntil:'load'});
    const noContext=await without.evaluate(()=>typeof document.modelContext==='undefined');
    assert.equal(noContext,true,'Without native or polyfill context, script should not falsely register');
    await without.close();
    console.log('OFFICIAL @w3c/webmcp-polyfill@0.1.1: registration, discovered schemas, 45-card DOM snapshot, 67 server-side products across 4 pages, argument rejection, 1 malformed card, public article API, lifecycle unregister, and no-polyfill fallback PASS.');
    await page.close();
  } finally {
    await browser.close();await new Promise(resolve=>server.close(resolve));
  }
}
main().catch(err=>{console.error(err);process.exitCode=1;server.close();});},priceInr:{type:'number',minimum:0},available:{type:'boolean'}},
        required:['name','path','priceInr','available']}},
      total:{type:'integer',minimum:0},nextOffset:{type:['integer','null'],minimum:0}
    }}
};
function cards(count){
  return Array.from({length:count},(_,i)=>'<article class="product-card">'+
    '<a class="product-card__title" href="/product/item-'+i+'">Item '+i+'</a>'+
    '<span class="price">₹'+(i+1)+'</span>'+
    '<div data-cart-control></div></article>').join('\n');
}
const server=createServer((req,res)=>{
  const url=new URL(req.url,'http://localhost');
  if(url.pathname==='/polyfill.js'){
    res.writeHead(200,{'content-type':'application/javascript'});res.end(polyfill);return;
  }
  if(url.pathname==='/assets/shop-webmcp.js'){
    res.writeHead(200,{'content-type':'application/javascript'});res.end(shopSource);return;
  }
  if(url.pathname==='/assets/public-knowledge-webmcp.js'){
    res.writeHead(200,{'content-type':'application/javascript'});res.end(knowledgeSource);return;
  }
  if(url.pathname==='/api/knowledge/articles'){
    const offset=Number(url.searchParams.get('offset')||0);
    const size=Number(url.searchParams.get('limit')||5);
    const query=url.searchParams.get('q')||'';
    const matches=apiRecords.filter(x=>!query||x.title.toLowerCase().includes(query.toLowerCase()));
    const end=Math.min(matches.length,offset+size);
    res.writeHead(200,{'content-type':'application/json','cache-control':'no-store'});
    res.end(JSON.stringify({articles:matches.slice(offset,end),total:matches.length,nextOffset:end<matches.length?end:null}));return;
  }
  if(url.pathname==='/'||url.pathname==='/without-polyfill'){
    const load=url.pathname==='/'?'<script src="/polyfill.js"></script>':'';
    const html='<!doctype html><html><head><title>WebMCP test</title>'+load+
      '<script id="bapx-public-knowledge-schema" type="application/json">'+JSON.stringify(articleSchema)+'</script></head>'+
      '<body><div class="shop-results">'+cards(45)+'</div>'+
      '<script src="/assets/shop-webmcp.js" defer></script>'+
      '<script src="/assets/public-knowledge-webmcp.js" defer></script></body></html>';
    res.writeHead(200,{'content-type':'text/html; charset=utf-8'});res.end(html);return;
  }
  res.writeHead(404);res.end('Not found');
});
async function main(){
  server.listen(0,'127.0.0.1');await once(server,'listening');
  const port=server.address().port;
  const browser=await playwright.chromium.launch({
    headless:true,
    executablePath:process.env.CHROMIUM_PATH||undefined,
    // The official polyfill must own the context in this test, not Chrome's native
    // Origin Trial implementation (which can reject calls under its permissions policy).
    args:['--no-sandbox','--disable-features=WebMCP'],
  });
  try{
    const page=await browser.newPage();
    const errors=[];page.on('pageerror',e=>errors.push(e.message));
    await page.goto('http://127.0.0.1:'+port+'/',{waitUntil:'load'});
    await page.waitForFunction(async()=>typeof document.modelContext?.getTools==='function'
      && (await document.modelContext.getTools()).some(x=>x.name==='list_shop_results'),{timeout:15000});
    const result=await page.evaluate(async()=>{
      const ctx=document.modelContext;
      const list=await ctx.getTools();
      const shop=list.find(x=>x.name==='list_shop_results');
      const article=list.find(x=>x.name==='search_public_articles');
      if(!shop||!article)throw Error('Both public tools must be discoverable with official polyfill');
      const call=async(t,input)=>JSON.parse(await ctx.executeTool(t,input));
      const a=await call(shop,{offset:0,limit:20});
      const b=await call(shop,{offset:a.nextOffset,limit:20});
      const c=await call(shop,{offset:b.nextOffset,limit:20});
      const knowledge=await call(article,{query:'public',offset:0,limit:1});
      let rejected=false;
      try{await ctx.executeTool(shop,{offset:-1})}catch(e){rejected=true;}
      const malformed=document.querySelectorAll('.product-card')[41];
      malformed.querySelector('.price').remove();
      const skip=await call(shop,{offset:40,limit:5});
      return {names:list.map(t=>t.name),schema:shop.outputSchema||null,
        a,b,c,knowledge,rejected,skip};
    });
    assert.deepEqual([result.a.products.length,result.b.products.length,result.c.products.length],[20,20,5]);
    assert.deepEqual([result.a.nextOffset,result.b.nextOffset,result.c.nextOffset],[20,40,null]);
    assert.equal(result.knowledge.articles.length,1);
    assert.equal(result.knowledge.total,2);
    assert.equal(result.knowledge.nextOffset,1);
    assert.equal(result.rejected,true,'Invalid offset must reject even under the real polyfill');
    assert.equal(result.skip.products.length,4,'Malformed card must not abort execution');
    assert.equal(result.skip.nextOffset,null);
    assert.equal(result.skip.total,45);
    assert.ok(result.schema,'Discovered shop tool should include outputSchema');
    assert.deepEqual(result.schema.required,['products','total','nextOffset']);
    assert.deepEqual(errors,[],'No uncaught Chrome exceptions');
    const lifecycle=await page.evaluate(async()=>{
      window.dispatchEvent(new PageTransitionEvent('pagehide',{persisted:false}));
      try {
        return {names:(await document.modelContext.getTools()).map(x=>x.name),blocked:false};
      } catch(e) {
        // The official polyfill may disable ALL discovery after pagehide.
        return {names:[],blocked:['NotAllowedError','InvalidStateError'].includes(e.name)};
      }
    });
    assert.ok(lifecycle.blocked || (!lifecycle.names.includes('list_shop_results')
      && !lifecycle.names.includes('search_public_articles')),
      'A pagehide must remove registered tools or render document discovery unavailable');

    const without=await browser.newPage();
    await without.goto('http://127.0.0.1:'+port+'/without-polyfill',{waitUntil:'load'});
    const noContext=await without.evaluate(()=>typeof document.modelContext==='undefined');
    assert.equal(noContext,true,'Without native or polyfill context, script should not falsely register');
    await without.close();
    console.log('OFFICIAL @w3c/webmcp-polyfill@0.1.1: registration, discovered schemas, 45-card execution, 3 pages, argument rejection, 1 malformed card, public article API, lifecycle unregister, and no-polyfill fallback PASS.');
    await page.close();
  } finally {
    await browser.close();await new Promise(resolve=>server.close(resolve));
  }
}
main().catch(err=>{console.error(err);process.exitCode=1;server.close();});