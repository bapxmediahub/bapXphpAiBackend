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
const {spawnSync}=require('node:child_process');
const {inspectPagePublicContact}=require('./fixtures/webmcp-cdp.cjs');
const native=process.argv.includes('--native');
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
const businessSource=readFileSync(join(__dirname,'../assets/public-business-webmcp.js'));
const presentationSource=readFileSync(join(__dirname,'../assets/public-a2ui-webmcp.js'));
const rendererSource=readFileSync(join(__dirname,'../assets/a2ui-public-renderer.js'));
const apiProducts=Array.from({length:67},(_,i)=>({name:'Fixture item '+i,path:'/product/fixture-'+i,priceInr:i+1,available:i%2===0,description:'Published fixture description'}));
const apiRecords=[
  {title:'Public help guide',path:'/blog/public-help',category:'help',score:2},
  {title:'Public article',path:'/blog/public-article',category:'guides',score:1}
];
// Use the PHP contract itself; browser fixtures must not silently drift from it.
const schemaProcess=spawnSync('php',['-r',
  'require $argv[1]; require $argv[2]; '+
  '$k=App\\Services\\PublicKnowledgeService::class; $a=App\\Services\\PublicA2uiService::class; '+
  'echo json_encode(["articles"=>["inputSchema"=>$k::articleInputSchema(),"outputSchema"=>$k::articleOutputSchema()],'+
  '"products"=>["inputSchema"=>$k::productInputSchema(),"outputSchema"=>$k::productOutputSchema()],'+
  '"contact"=>["inputSchema"=>$k::contactInputSchema(),"outputSchema"=>$k::contactOutputSchema()],'+
  '"services"=>["inputSchema"=>$k::serviceInputSchema(),"outputSchema"=>$k::serviceOutputSchema()],'+
  '"a2ui"=>["inputSchema"=>$a::inputSchema(),"outputSchema"=>$a::outputSchema()]],JSON_THROW_ON_ERROR);',
  join(__dirname,'../app/Services/PublicKnowledgeService.php'),join(__dirname,'../app/Services/PublicA2uiService.php')],{encoding:'utf8'});
assert.equal(schemaProcess.status,0,schemaProcess.stderr||'PHP contract generation failed');
const schemas=JSON.parse(schemaProcess.stdout);
const presentationProcess=spawnSync('php',[join(__dirname,'fixtures/a2ui-responses.php')],{encoding:'utf8'});
assert.equal(presentationProcess.status,0,presentationProcess.stderr||'Public presentation fixtures failed');
const presentations=JSON.parse(presentationProcess.stdout);
const contact={name:'Fixture Business',path:'/contact',phones:[{label:'Support',number:'+919789444037'}],
  email:'support@example.test',hours:[{days:'Mon–Sat',hours:'9 AM–7 PM'}],servicePath:'/contact#contact-form'};
const services={services:[{name:'Temple Guidance',description:'Help using the published temple guides.',
  path:'/contact?subject=temple#contact-form'}],total:1,nextOffset:null};
const globalTools=['get_public_contact','list_public_services','present_public_knowledge','search_public_articles','search_public_products'];
const shopCdpTools=[...globalTools,'list_shop_results','filter_visible_shop'];
// Mirror the production form's declarative GET contract, not a JS registration.
const shopFilter='<form action="/shop" method="get" toolname="filter_visible_shop" tooldescription="Filter visible shop results" toolautosubmit>'+
  '<label>Search <input type="search" name="q" toolparamdescription="Search keywords"></label>'+
  '<label>Category <select name="category"><option value="">All</option><option value="fixture">Fixture</option></select></label>'+
  '<label>Sort <select name="sort"><option value="">Default</option></select></label>'+
  '<button type="submit">Apply</button></form>';
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
  const assets={
    '/assets/shop-webmcp.js':shopSource,
    '/assets/public-products-webmcp.js':productSource,
    '/assets/public-knowledge-webmcp.js':knowledgeSource,
    '/assets/public-business-webmcp.js':businessSource,
    '/assets/public-a2ui-webmcp.js':presentationSource,
    '/assets/a2ui-public-renderer.js':rendererSource,
  };
  if(assets[url.pathname]){
    res.writeHead(200,{'content-type':'application/javascript'});res.end(assets[url.pathname]);return;
  }
  if(url.pathname==='/api/knowledge/a2ui'){
    const view=url.searchParams.get('view');
    res.writeHead(200,{'content-type':'application/json','cache-control':'no-store'});
    res.end(JSON.stringify(view==='contact'?presentations.contact:view==='services'?presentations.services:presentations.product));return;
  }
  const simpleResponses={'/api/knowledge/contact':contact,'/api/knowledge/services':services};
  if(simpleResponses[url.pathname]){
    res.writeHead(200,{'content-type':'application/json','cache-control':'no-store'});
    res.end(JSON.stringify(simpleResponses[url.pathname]));return;
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
  if(['/', '/shop', '/without-polyfill', '/contact-only'].includes(url.pathname)){
    const load=!native&&url.pathname!=='/without-polyfill'?'<script src="/polyfill.js"></script>':'';
    const catalogue=url.pathname!=='/contact-only';
    const schema=(id,contract)=>'<script id="'+id+'" type="application/json">'+JSON.stringify(contract)+'</script>';
    const script=path=>'<script src="/assets/'+path+'" defer></script>';
    const html='<!doctype html><html><head><title>WebMCP test</title>'+load+
      schema('bapx-public-business-schema',{contact:schemas.contact,services:schemas.services})+
      schema('bapx-a2ui-schema',schemas.a2ui)+
      (catalogue?schema('bapx-public-knowledge-schema',schemas.articles)+schema('bapx-public-products-schema',schemas.products):'')+
      '</head><body><h1>Business landing page</h1>'+
      (url.pathname==='/shop'?'<div class="shop-results">'+shopFilter+cards(45)+'</div>':'')+
      script('public-business-webmcp.js')+script('shop-webmcp.js')+
      script('a2ui-public-renderer.js')+script('public-a2ui-webmcp.js')+
      (catalogue?script('public-knowledge-webmcp.js')+script('public-products-webmcp.js'):'')+
      '</body></html>';
    res.writeHead(200,{'content-type':'text/html; charset=utf-8'});res.end(html);return;
  }
  res.writeHead(404);res.end('Not found');
});
async function main(){
  server.listen(0,'127.0.0.1');await once(server,'listening');
  const port=server.address().port;
  const browser=await playwright.chromium.launch({
    headless:!native,
    executablePath:process.env.CHROMIUM_PATH||undefined,
    // The official polyfill must own the context in this test, not Chrome's native
    // Origin Trial implementation (which can reject calls under its permissions policy).
    args:native?['--no-sandbox','--enable-features=WebMCP,WebMCPTesting,DevToolsWebMCPSupport']
      :['--no-sandbox','--disable-features=WebMCP,WebMCPTesting'],
  });
  try{
    const page=await browser.newPage();
    const errors=[];page.on('pageerror',e=>errors.push(e.message));
    await page.goto('http://127.0.0.1:'+port+'/',{waitUntil:'load'});
    const waitForTools=async(target,count)=>target.waitForFunction(async count=>
      typeof document.modelContext?.getTools==='function' && (await document.modelContext.getTools()).length===count,count,{timeout:15000});
    await waitForTools(page,5);
    const landing=await page.evaluate(async()=>{
      const list=await document.modelContext.getTools();
      const call=async(name,args={})=>JSON.parse(await document.modelContext.executeTool(list.find(t=>t.name===name),args));
      return {names:list.map(t=>t.name).sort(),contact:await call('get_public_contact'),
        services:await call('list_public_services',{query:'temple',limit:1}),
        ui:await call('present_public_knowledge',{view:'products',limit:1}),
        scripts:[...document.scripts].map(s=>s.src)};
    });
    assert.deepEqual(landing.names,globalTools,'Landing page exposes the complete public tool set without a shop DOM');
    assert.deepEqual(landing.contact,contact);
    assert.deepEqual(landing.services,services);
    assert.equal(landing.ui.messages[0].version,'1.0');
    if(native){
      assert.ok(!landing.scripts.some(url=>url.endsWith('/polyfill.js')),'Native lane must load no polyfill');
      // An isolated execution world approximates a browser extension/agent; the
      // page-only WeakMap registry of the polyfill cannot satisfy this check.
      const cdp=await page.context().newCDPSession(page);
      const {frameTree}=await cdp.send('Page.getFrameTree');
      const {executionContextId}=await cdp.send('Page.createIsolatedWorld',{frameId:frameTree.frame.id,worldName:'webmcp-ci-agent'});
      const probe=await cdp.send('Runtime.evaluate',{contextId:executionContextId,awaitPromise:true,returnByValue:true,
        expression:'(async()=>{const tools=await document.modelContext.getTools(); const contact=tools.find(t=>t.name==="get_public_contact"); return {names:tools.map(t=>t.name).sort(),contact:JSON.parse(await document.modelContext.executeTool(contact,{}))};})()'});
      assert.equal(probe.exceptionDetails,undefined,'Native tools must be visible and callable from the isolated agent world');
      assert.deepEqual(probe.result.value.names,globalTools);
      assert.deepEqual(probe.result.value.contact,contact);
      await cdp.detach();
      const external=await inspectPagePublicContact(page,{expectedNames:globalTools});
      assert.deepEqual(external.contact,contact,'Native CDP returns the canonical public contact');
      console.log('LOCAL WEBMCP CDP landing: '+JSON.stringify({browser:external.browser,profile:external.profile,names:external.names}));
    }
    await page.goto('http://127.0.0.1:'+port+'/shop',{waitUntil:'load'});
    await waitForTools(page,native?7:6);
    if(native){
      const external=await inspectPagePublicContact(page,{expectedNames:shopCdpTools});
      assert.deepEqual(external.contact,contact,'Native CDP contact read also works on the shop page');
      console.log('LOCAL WEBMCP CDP shop: '+JSON.stringify({browser:external.browser,profile:external.profile,names:external.names}));
    }
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
    assert.deepEqual(result.names.sort(),(native?shopCdpTools:[...globalTools,'list_shop_results']).slice().sort(),'Exact JavaScript shop registry after all declarations register');
    assert.deepEqual([result.a.products.length,result.b.products.length,result.c.products.length],[20,20,5]);
    assert.deepEqual([result.a.nextOffset,result.b.nextOffset,result.c.nextOffset],[20,40,null]);
    assert.deepEqual(result.backend.map(x=>x.products.length),[20,20,20,7]);
    assert.equal(result.backend[0].total,67,'Backend must page beyond the 45 visible DOM cards');
    assert.deepEqual(result.backend.map(x=>x.nextOffset),[20,40,60,null]);
    if(!native)assert.deepEqual(result.backendSchema.required,['products','total','nextOffset']);
    assert.equal(result.knowledge.articles.length,1);
    assert.equal(result.knowledge.total,2);
    assert.equal(result.knowledge.nextOffset,1);
    assert.equal(result.rejected,true,'Invalid offset must reject even under the real polyfill');
    assert.equal(result.skip.products.length,3,'Missing price and cross-origin links must be rejected');
    assert.equal(result.skip.nextOffset,null);
    assert.equal(result.skip.total,45);
    // Chrome before 159 does not expose outputSchema; the returned data is still checked above.
    if(!native){
      assert.ok(result.schema,'Polyfill descriptor should include outputSchema');
      assert.deepEqual(result.schema.required,['products','total','nextOffset']);
    }
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

    await page.goto('http://127.0.0.1:'+port+'/contact-only',{waitUntil:'load'});
    await waitForTools(page,3);
    const contactOnly=await page.evaluate(async()=>{
      const tools=await document.modelContext.getTools();
      return {names:tools.map(x=>x.name).sort(),contact:JSON.parse(await document.modelContext.executeTool(tools.find(t=>t.name==='get_public_contact'),{})),
        presentation:JSON.parse(await document.modelContext.executeTool(tools.find(t=>t.name==='present_public_knowledge'),{view:'contact',limit:4}))};
    });
    assert.deepEqual(contactOnly.names,['get_public_contact','list_public_services','present_public_knowledge']);
    assert.deepEqual(contactOnly.contact,contact);
    assert.equal(contactOnly.presentation.messages[0].version,'1.0');
    assert.ok(contactOnly.presentation.messages[0].createSurface.surfaceId.startsWith('bapx_contact_'));
    if(!native){
      const without=await browser.newPage();
      const fallbackErrors=[];without.on('pageerror',error=>fallbackErrors.push(error.message));
      await without.goto('http://127.0.0.1:'+port+'/without-polyfill',{waitUntil:'load'});
      assert.equal(await without.evaluate(()=>typeof document.modelContext==='undefined'),true);
      assert.deepEqual(fallbackErrors,[],'Unsupported browsers must retain a working page');
      await without.close();
    }
    console.log((native?'NATIVE WEBMCP (no polyfill, isolated agent world and CDP discovery/contact)':'OFFICIAL @w3c/webmcp-polyfill@0.1.1')+
      ': landing 5 tools, shop 6 imperative tools'+(native?' + 1 declarative tool':'')+', contact without commerce/blog, 45-card DOM snapshot, 67 server-side products, article/service paging, contact, A2UI, invalid input and pagehide PASS.');
    await page.close();
  } finally {
    await browser.close();await new Promise(resolve=>server.close(resolve));
  }
}
main().catch(err=>{console.error(err);process.exitCode=1;server.close();});
