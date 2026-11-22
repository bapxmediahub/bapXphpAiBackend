'use strict';
const assert=require('node:assert/strict');
const {chromium}=require('playwright');
const {test}=require('node:test');

test('live public WebMCP tools including A2UI v1.0 can be discovered and invoked', {timeout:45000}, async()=>{
    const browser=await chromium.launch({headless:true,
        executablePath:process.env.CHROMIUM_PATH||undefined,
        args:['--no-sandbox','--disable-features=WebMCP']});
    try{
        const page=await browser.newPage({viewport:{width:375,height:812}});
        const errors=[];
        page.on('pageerror',e=>errors.push(e.message));
        const site='https://sripanchamispiritual.com';
        const response=await page.goto(site+'/shop',{waitUntil:'load',timeout:20000});
        assert.equal(response?.status(),200);
        await page.waitForFunction(async()=>{
            if(typeof document.modelContext?.getTools!=='function')return false;
            try{return (await document.modelContext.getTools())
                .some(tool=>tool.name==='present_public_knowledge');}
            catch{return false}
        },null,{timeout:12000});
        const results=await page.evaluate(async()=>{
            const context=document.modelContext;
            const all=await context.getTools();
            const required=['list_shop_results','search_public_products','search_public_articles','present_public_knowledge'];
            if(required.some(n=>!all.some(t=>t.name===n)))throw Error('Public WebMCP capabilities not discoverable');
            const invoke=async(name,args)=>{
                const tool=all.find(t=>t.name===name);
                if(!tool?.outputSchema)throw Error('Public tool lacks outputSchema: '+name);
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
                names:all.map(t=>t.name),
                domCount:dom.products?.length,domTotal:dom.total,domNext:dom.nextOffset,
                productsCount:products.products?.length,productsTotal:products.total,productsNext:products.nextOffset,
                articlesCount:articles.articles?.length,articlesTotal:articles.total,
                version:ui.messages?.[0]?.version,catalog:ui.messages?.[0]?.createSurface?.catalogId,
                rendered,cardCount:host.querySelectorAll('.bapx-a2ui__card').length,
                href:host.querySelector('a.bapx-a2ui__link')?.getAttribute('href'),
            };
        });
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
        console.log('LIVE WEBMCP A2UI PASS: discovered/executed '+results.names.join(', ')+', rendered 1 public item, no JS exceptions.');
        await page.close();
    }finally{
        await browser.close();
    }
});