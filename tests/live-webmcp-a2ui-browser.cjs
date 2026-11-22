'use strict';
const assert=require('node:assert/strict');
const {chromium}=require('playwright');
const {test}=require('node:test');
const native=process.env.WEBMCP_NATIVE==='1';
const commerceNames=['create_cart','get_cart','set_cart_quantity','review_checkout','prepare_checkout_payment','get_checkout_status'];
const landingNames=['get_public_contact','list_public_services','present_public_knowledge','search_public_articles','search_public_products',...commerceNames].sort();
const shopNames=[...landingNames,'list_shop_results'].sort();
async function waitForTools(page,expected){
    await page.waitForFunction(async expected=>{
        if(typeof document.modelContext?.getTools!=='function')return false;
        try{
            const names=(await document.modelContext.getTools()).map(tool=>tool.name).sort();
            return names.length===expected.length&&names.every((name,index)=>name===expected[index]);
        }catch{return false}
    },expected,{timeout:12000});
}
function checkCommerceAnnotations(tools){
    for(const name of commerceNames){
        const annotations=tools[name];
        if(!native)assert.ok(annotations&&typeof annotations==='object','Commerce annotations missing: '+name);
        const readOnly=name==='get_cart';
        for(const [hint,value]of Object.entries({readOnlyHint:readOnly,consequentialHint:!readOnly,untrustedContentHint:true})){
            // Native Chrome releases may omit annotations from discovered descriptors.
            if(!native||Object.hasOwn(annotations??{},hint))assert.equal(annotations?.[hint],value,name+' '+hint);
        }
    }
}

test('live landing and shop WebMCP tools can be discovered and invoked ('+(native?'native':'official polyfill')+')', {timeout:65000}, async()=>{
    const browser=await chromium.launch({headless:!native,
        executablePath:process.env.CHROMIUM_PATH||undefined,
        args:native?['--no-sandbox','--enable-features=WebMCP,WebMCPTesting,DevToolsWebMCPSupport']
            :['--no-sandbox','--disable-features=WebMCP,WebMCPTesting']});
    try{
        const page=await browser.newPage({viewport:{width:375,height:812}});
        const errors=[];
        page.on('pageerror',e=>errors.push(e.message));
        const site='https://sripanchamispiritual.com';
        const response=await page.goto(site+'/',{waitUntil:'load',timeout:20000});
        assert.equal(response?.status(),200);
        assert.ok(await page.locator('a[href="/ai-assistants"]').count()>0,'Landing page exposes the public connection guide');
        const instructions=await page.request.get(site+'/llms.txt');
        // Preserve the HTTP assertion and retain only safe diagnostic headers
        // when the hosting layer intermittently rejects a public read.
        const instructionHeaders=instructions.headers();
        const diagnosticHeaders=Object.fromEntries(['server','content-type','retry-after','x-hcdn-request-id','x-hcdn-cache-status']
            .filter(name=>instructionHeaders[name]).map(name=>[name,instructionHeaders[name]]));
        assert.equal(instructions.status(),200,'Public /llms.txt read failed: '+JSON.stringify(diagnosticHeaders));
        const instructionText=await instructions.text();
        assert.ok(instructionText.includes('/api/mcp')&&instructionText.includes('/ai-assistants'),'Advisory index points to the real public interface');
        await waitForTools(page,landingNames);
        const landing=await page.evaluate(async({native,commerceNames})=>{
            const ctx=document.modelContext;
            if(native&&!String(ctx.registerTool).includes('[native code]'))throw Error('Native probe must use the browser implementation');
            const all=await ctx.getTools();
            const names=all.map(t=>t.name).sort();
            const invoke=async(name,args)=>JSON.parse(await ctx.executeTool(all.find(t=>t.name===name),args));
            const products=await invoke('search_public_products',{limit:2});
            const slugs=products.products.map(item=>decodeURIComponent(item.path.slice('/product/'.length)));
            const selected=await invoke('search_public_products',{slugs,limit:Math.max(1,slugs.length)});
            return {names,commerceAnnotations:Object.fromEntries(all.filter(tool=>commerceNames.includes(tool.name)).map(tool=>[tool.name,tool.annotations??null])),
                contact:await invoke('get_public_contact',{}),
                services:await invoke('list_public_services',{limit:4}),
                products,selected,
                articles:await invoke('search_public_articles',{limit:1}),
                contactUi:await invoke('present_public_knowledge',{view:'contact',limit:4}),
                servicesUi:await invoke('present_public_knowledge',{view:'services',limit:4}),
                ui:await invoke('present_public_knowledge',{view:'products',limit:1})};
        },{native,commerceNames});
        assert.deepEqual(landing.names,landingNames);
        checkCommerceAnnotations(landing.commerceAnnotations);
        assert.equal(landing.contact.path,'/contact');
        assert.ok(landing.contact.phones.length>0&&landing.contact.email);
        assert.equal(landing.services.total,4);
        assert.ok(landing.services.services.every(s=>/^\/contact\?subject=(product|temple|order|general)#contact-form$/.test(s.path)));
        assert.equal(typeof landing.products.products[0]?.description,'string');
        assert.deepEqual(landing.selected.products.map(item=>item.path),landing.products.products.map(item=>item.path));
        assert.ok(landing.selected.products.every(item=>(item.imagePaths===undefined||Array.isArray(item.imagePaths))
            &&(item.highlights===undefined||Array.isArray(item.highlights))
            &&(item.specifications===undefined||Array.isArray(item.specifications))),'Published detail stays typed when present');
        assert.equal(landing.ui.messages[0].version,'1.0');
        assert.ok(landing.contactUi.messages[0].createSurface.surfaceId.startsWith('bapx_contact_'));
        assert.equal(landing.contactUi.messages[0].createSurface.dataModel.items.length,4);
        assert.ok(landing.servicesUi.messages[0].createSurface.surfaceId.startsWith('bapx_services_'));
        assert.equal(landing.servicesUi.messages[0].createSurface.dataModel.items.length,4);
        assert.ok(Number.isInteger(landing.articles.total));
        await page.goto(site+'/shop',{waitUntil:'load',timeout:20000});
        await waitForTools(page,shopNames);
        const results=await page.evaluate(async({native,commerceNames,required})=>{
            const context=document.modelContext;
            const all=await context.getTools();
            if(required.some(n=>!all.some(t=>t.name===n)))throw Error('Public WebMCP capabilities not discoverable');
            const invoke=async(name,args)=>{
                const tool=all.find(t=>t.name===name);
                if(!native&&!tool?.outputSchema)throw Error('Public tool lacks outputSchema: '+name);
                return JSON.parse(await context.executeTool(tool,args));
            };
            const dom=await invoke('list_shop_results',{offset:0,limit:1});
            const products=await invoke('search_public_products',{query:'',offset:0,limit:1});
            const articles=await invoke('search_public_articles',{query:'',offset:0,limit:1});
            const ui=await invoke('present_public_knowledge',{view:'products',query:'',limit:1});
            const host=document.createElement('div');
            host.id='a2ui-live-fixture';document.body.appendChild(host);
            const rendered=window.BapxA2ui?.render(host,ui)===true;
            return {
                names:all.map(t=>t.name).sort(),
                commerceAnnotations:Object.fromEntries(all.filter(tool=>commerceNames.includes(tool.name)).map(tool=>[tool.name,tool.annotations??null])),
                domCount:dom.products?.length,domTotal:dom.total,domNext:dom.nextOffset,
                productsCount:products.products?.length,productsTotal:products.total,productsNext:products.nextOffset,
                articlesCount:articles.articles?.length,articlesTotal:articles.total,
                version:ui.messages?.[0]?.version,catalog:ui.messages?.[0]?.createSurface?.catalogId,
                rendered,cardCount:host.querySelectorAll('.bapx-a2ui__card').length,
                href:host.querySelector('a.bapx-a2ui__link')?.getAttribute('href'),
            };
        },{native,commerceNames,required:shopNames});
        assert.deepEqual(results.names,shopNames);
        checkCommerceAnnotations(results.commerceAnnotations);
        assert.equal(results.domCount,1);
        assert.equal(results.productsCount,1);
        assert.ok(Number.isInteger(results.domTotal)&&results.domTotal>=1);
        assert.ok(Number.isInteger(results.productsTotal)&&results.productsTotal>=1);
        assert.equal(results.version,'1.0');
        assert.equal(results.catalog,'https://a2ui.org/specification/v1_0/catalogs/basic/catalog.json');
        assert.equal(results.rendered,true);
        assert.equal(results.cardCount,1);
        assert.match(results.href,/^\/product\/[A-Za-z0-9%_.-]+$/);
        assert.deepEqual(errors,[]);
        console.log('LIVE WEBMCP '+(native?'NATIVE':'POLYFILL')+' A2UI PASS: landing 11 tools; shop 12 tools ('+results.names.join(', ')+'), rendered 1 public item, no commerce actions invoked, no JS exceptions.');
        await page.close();
    }finally{
        await browser.close();
    }
});
