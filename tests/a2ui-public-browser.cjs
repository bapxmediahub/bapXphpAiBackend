'use strict';
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {createServer}=require('node:http');
const {readFileSync}=require('node:fs');
const {once}=require('node:events');
const path=require('node:path');
const {test}=require('node:test');
const {chromium}=require('playwright');
const root=path.join(__dirname,'..');
const scripts={
    '/polyfill.js':readFileSync(path.join(root,'assets/vendor/webmcp/polyfill-0.1.1.js')),
    '/renderer.js':readFileSync(path.join(root,'assets/a2ui-public-renderer.js')),
    '/webmcp.js':readFileSync(path.join(root,'assets/public-a2ui-webmcp.js')),
};
const css=readFileSync(path.join(root,'assets/css/a2ui-public.css'));
const fixtures=JSON.parse(execFileSync('php',['tests/fixtures/a2ui-responses.php'],{cwd:root,encoding:'utf8'}));
const catalog='https://a2ui.org/specification/v1_0/catalogs/basic/catalog.json';
const a2ui=(label,pathValue)=>({messages:[{
    version:'1.0',createSurface:{
        surfaceId:'bapx_products_0123456789abcdef',
        catalogId:catalog,
        components:[
            {id:'root',component:'Column',children:['title','card_0']},
            {id:'title',component:'Text',text:{'@path':'/heading'}},
            {id:'card_0',component:'Card',child:'body_0'},
            {id:'body_0',component:'Column',children:['name_0','detail_0','open_0']},
            {id:'name_0',component:'Text',text:{'@path':'/items/0/title'}},
            {id:'detail_0',component:'Text',variant:'caption',text:{'@path':'/items/0/detail'}},
            {id:'open_label_0',component:'Text',text:'View products'},
            {id:'open_0',component:'Button',child:'open_label_0',variant:'borderless',
                action:{functionCall:{'@call':'openUrl',args:{url:{'@path':'/items/0/path'}}}}},
        ],
        dataModel:{heading:'Published products',items:[{title:label,detail:'₹125.00',path:pathValue}]}
    }
}]});
const schema={
    inputSchema:{type:'object',additionalProperties:false,required:['view'],
        properties:{view:{type:'string',enum:['products','articles','contact','services']},
            query:{type:'string',maxLength:120},limit:{type:'integer',minimum:1,maximum:4},
            layout:{type:'string',enum:['auto','grid','list','detail','compare']},
            slugs:{type:'array',maxItems:3,uniqueItems:true,items:{type:'string'}}}},
    outputSchema:{type:'object',additionalProperties:false,required:['messages'],
        properties:{messages:{type:'array',minItems:1,maxItems:1,items:{type:'object',required:['version','createSurface']}}}}
};
test('official WebMCP polyfill executes a read-only A2UI v1.0 tool and renderer rejects untrusted code', {timeout:30000},async()=>{
    const requests=[];
    const server=createServer((req,res)=>{
        const url=new URL(req.url,'http://localhost');
        if(url.pathname==='/style.css'){
            res.writeHead(200,{'content-type':'text/css'});res.end(css);return;
        }
        if(scripts[url.pathname]){
            res.writeHead(200,{'content-type':'application/javascript'});res.end(scripts[url.pathname]);return;
        }
        if(url.pathname==='/api/knowledge/a2ui'){
            requests.push(Object.fromEntries(url.searchParams));
            res.writeHead(200,{'content-type':'application/json','cache-control':'no-store'});
            const view=url.searchParams.get('view');
            res.end(JSON.stringify(view==='contact'?fixtures.contact:view==='services'?fixtures.services:url.searchParams.get('layout')==='compare'?fixtures.productCompare:a2ui('Public Gemstone Ring','/product/public-ring')));return;
        }
        if(url.pathname==='/'){
            const html='<!doctype html><html><head>'+
                '<link rel="stylesheet" href="/style.css">'+
                '<script src="/polyfill.js"></script>'+
                '<script id="bapx-a2ui-schema" type="application/json">'+JSON.stringify(schema)+'</script>'+
                '<script src="/renderer.js" defer></script>'+
                '<script src="/webmcp.js" defer></script>'+
                '</head><body><div id="cards"></div></body></html>';
            res.writeHead(200,{'content-type':'text/html'});res.end(html);return;
        }
        res.writeHead(404);res.end('Not found');
    });
    server.listen(0,'127.0.0.1');await once(server,'listening');
    let browser;
    try{
        browser=await chromium.launch({headless:true,
            executablePath:process.env.CHROMIUM_PATH||undefined,
            args:['--no-sandbox','--disable-features=WebMCP']});
        const page=await browser.newPage();
        const errors=[];page.on('pageerror',error=>errors.push(error.message));
        const origin='http://127.0.0.1:'+server.address().port;
        await page.goto(origin+'/',{waitUntil:'load'});
        await page.waitForFunction(async()=>typeof document.modelContext?.getTools==='function'
            && (await document.modelContext.getTools()).some(x=>x.name==='present_public_knowledge'));
        const result=await page.evaluate(async()=>{
            const tool=(await document.modelContext.getTools()).find(x=>x.name==='present_public_knowledge');
            if(!tool.outputSchema?.properties?.messages)throw Error('Missing declared A2UI output schema.');
            let bad=false;
            try{await document.modelContext.executeTool(tool,{view:'orders',limit:1})}catch{bad=true}
            const response=JSON.parse(await document.modelContext.executeTool(tool,{view:'products',limit:1}));
            const container=document.getElementById('cards');
            const rendered=window.BapxA2ui.render(container,response);
            const link=container.querySelector('a[href]');
            return {names:(await document.modelContext.getTools()).map(x=>x.name),
                bad,rendered,text:container.innerText,href:link?.getAttribute('href'),
                linkSize:link?.getBoundingClientRect().height};
        });
        assert.equal(result.bad,true,'Invalid presentation input must reject');
        assert.equal(result.rendered,true);
        assert.equal(result.href,'/product/public-ring');
        assert.ok(result.text.includes('Public Gemstone Ring'));
        assert.ok(result.linkSize>=44);
        const selected=await page.evaluate(async()=>{
            const tool=(await document.modelContext.getTools()).find(x=>x.name==='present_public_knowledge');
            const payload=JSON.parse(await document.modelContext.executeTool(tool,{view:'products',layout:'compare',slugs:['emerald-ring','ruby-pendant']}));
            let invalid=0;
            for(const input of [
                {view:'products',layout:'compare',query:'ring',slugs:['emerald-ring','ruby-pendant']},
                {view:'products',layout:'compare',slugs:['emerald-ring']},
                {view:'products',layout:'detail',slugs:['emerald-ring','ruby-pendant']},
                {view:'products',slugs:Array(1)},
                {view:'products',slugs:['../private']},{view:'contact',layout:'grid'},
            ]) {try{await document.modelContext.executeTool(tool,input)}catch{invalid++;}}
            await document.modelContext.executeTool(tool,{view:'products',query:'𐐀'.repeat(120)});
            return {presentation:payload.messages[0].createSurface.dataModel.presentation,invalid};
        });
        assert.deepEqual(selected,{presentation:'product_compare',invalid:6});
        assert.ok(requests.some(request=>request.layout==='compare'&&JSON.parse(request.slugs).join(',')==='emerald-ring,ruby-pendant'));
        assert.ok(requests.some(request=>[...request.query].length===120),'WebMCP uses Unicode code-point query limits');
        const business=await page.evaluate(async()=>{
            const tool=(await document.modelContext.getTools()).find(x=>x.name==='present_public_knowledge');
            const contact=JSON.parse(await document.modelContext.executeTool(tool,{view:'contact',limit:4}));
            const services=JSON.parse(await document.modelContext.executeTool(tool,{view:'services',limit:4}));
            return {contact:contact.messages[0].createSurface,services:services.messages[0].createSurface};
        });
        assert.ok(business.contact.surfaceId.startsWith('bapx_contact_'));
        assert.ok(business.services.surfaceId.startsWith('bapx_services_'));
        assert.equal(business.services.dataModel.items[0].path,'/contact?subject=product#contact-form');
        const safety=await page.evaluate(()=>{
            const root=document.getElementById('cards');
            const draft=(label,href)=>({
                messages:[{version:'1.0',createSurface:{
                    surfaceId:'bapx_products_0123456789abcdef',
                    catalogId:'https://a2ui.org/specification/v1_0/catalogs/basic/catalog.json',
                    components:[
                        {id:'root',component:'Column',children:['txt','btn']},
                        {id:'txt',component:'Text',text:{'@path':'/items/0/title'}},
                        {id:'caption',component:'Text',text:'Go'},
                        {id:'btn',component:'Button',child:'caption',
                          action:{functionCall:{'@call':'openUrl',args:{url:{'@path':'/items/0/path'}}}}}
                    ],
                    dataModel:{heading:'Results',items:[{title:label,detail:'',path:href}]}
                }}]
            });
            const attack=draft('<img src=x onerror=alert(1)>','/blog/public-help');
            const rendered=window.BapxA2ui.render(root,attack);
            const rawText=root.textContent.includes('<img src=x onerror=alert(1)>');
            const imageCount=root.querySelectorAll('img').length;
            const remote=draft('bad','https://evil.invalid/phishing');
            const remoteResult=window.BapxA2ui.render(root,remote);
            const script=draft('bad','javascript:alert(1)');
            const scriptResult=window.BapxA2ui.render(root,script);
            const malicious=draft('bad','/product/good');
            malicious.messages[0].createSurface.components[1].component='Html';
            const arbitraryResult=window.BapxA2ui.render(root,malicious);
            return {rendered,rawText,imageCount,remoteResult,scriptResult,arbitraryResult};
        });
        assert.deepEqual(safety,{rendered:true,rawText:true,imageCount:0,
            remoteResult:false,scriptResult:false,arbitraryResult:false});
        assert.deepEqual(errors,[]);
        console.log('Official 0.1.1 WebMCP + safe A2UI v1.0 discovery, execution, native rendering, invalid input, XSS and URL rejection PASS.');
        await page.close();
    }finally{
        if(browser)await browser.close();
        await new Promise(resolve=>server.close(resolve));
    }
});

for(const width of [1280,375]) {
    test(`Purposeful public cards, native disclosure and private reports at ${width}px`,{timeout:30000},async()=>{
        const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||undefined,args:['--no-sandbox']});
        const page=await browser.newPage({viewport:{width,height:900}});
        const errors=[];page.on('pageerror',error=>errors.push(error.message));
        try {
            await page.setContent('<style>body{margin:8px;font:16px sans-serif}#wide{max-width:900px}#narrow{width:320px;max-width:100%}</style><main id="wide"></main><aside id="narrow"></aside>');
            await page.addStyleTag({path:path.join(root,'assets/css/a2ui-public.css')});
            await page.addScriptTag({path:path.join(root,'assets/a2ui-public-renderer.js')});
            const output=await page.evaluate(fixtures=>{
                const wide=document.getElementById('wide'),narrow=document.getElementById('narrow');
                const results={};
                for(const key of ['productGrid','productList','productCompare','partialComparison','articles','contact','services','product']) {
                    const host=document.createElement('div');host.dataset.fixture=key;wide.appendChild(host);
                    results[key]=window.BapxA2ui.render(host,fixtures[key]);
                }
                results.narrow=window.BapxA2ui.render(narrow,fixtures.productGrid);
                const report=document.createElement('div');report.dataset.fixture='commerce';wide.appendChild(report);
                results.commerce=window.BapxA2ui.render(report,fixtures.commerce,{scope:'admin'});
                const rejected=[];
                const unknown=structuredClone(fixtures.product);unknown.messages[0].createSurface.dataModel.presentation='arbitrary_html';rejected.push(unknown);
                const wrongFamily=structuredClone(fixtures.product);wrongFamily.messages[0].createSurface.dataModel.presentation='contact_panel';rejected.push(wrongFamily);
                const partial=structuredClone(fixtures.product);partial.messages[0].createSurface.dataModel.presentation='product_compare';rejected.push(partial);
                const privateData=structuredClone(fixtures.product);privateData.messages[0].createSurface.dataModel.reports=fixtures.commerce.messages[0].createSurface.dataModel.reports;rejected.push(privateData);
                const extraSpecs=structuredClone(fixtures.product);extraSpecs.messages[0].createSurface.dataModel.items[0].specifications=Array(7).fill({label:'Field',value:'Value'});rejected.push(extraSpecs);
                results.rejected=rejected.every(payload=>!window.BapxA2ui.render(document.createElement('div'),payload));
                results.privateDenied=!window.BapxA2ui.render(document.createElement('div'),fixtures.commerce);
                return results;
            },fixtures);
            for(const [key,ok] of Object.entries(output))assert.equal(ok,true,key);
            const columns=await page.locator('[data-fixture="productGrid"] .bapx-a2ui__items').evaluate(element=>getComputedStyle(element).gridTemplateColumns.split(' ').length);
            assert.equal(columns,width===1280?2:1,'Collection adapts to its actual available width');
            assert.equal(await page.locator('#narrow .bapx-a2ui__items').evaluate(element=>getComputedStyle(element).gridTemplateColumns.split(' ').length),1,'Desktop narrow hosts must not get a desktop grid');
            assert.equal(await page.locator('[data-fixture="productCompare"]').getByText('Not listed',{exact:true}).count(),1);
            assert.match(await page.locator('[data-fixture="partialComparison"]').innerText(),/Showing 2 of 3 selected products/);
            assert.equal(await page.locator('[data-fixture="contact"] a').count(),1,'Contact is one grouped panel');
            assert.equal(await page.locator('[data-fixture="commerce"] .bapx-a2ui__report-item').count(),2);
            assert.match(await page.locator('[data-fixture="commerce"]').innerText(),/Showing 2 of 2 rows/);
            const summary=page.locator('[data-fixture="product"] summary');
            assert.ok(await summary.evaluate(element=>element.getBoundingClientRect().height>=44));
            assert.equal(await page.locator('[data-fixture="product"] details img').first().isVisible(),false);
            await summary.press('Enter');
            assert.equal(await page.locator('[data-fixture="product"] details img').first().isVisible(),true);
            await page.locator('[data-fixture="product"]').evaluate(element=>{element.innerHTML=element.innerHTML;});
            await summary.press('Space');
            assert.equal(await page.locator('[data-fixture="product"] details img').first().isVisible(),false,'Disclosure remains native after saved HTML restoration');
            assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
            assert.deepEqual(errors,[]);
        } finally {await browser.close();}
    });
}

// These are real server view models, not hand-authored copies of renderer schemas.
test('Public and actor-scoped surfaces preserve content and fail closed', {timeout:30000},async()=>{
    const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||undefined,args:['--no-sandbox']});
    try {
        const page=await browser.newPage();
        await page.setContent('<main id="output"></main>');
        await page.addScriptTag({path:path.join(root,'assets/a2ui-public-renderer.js')});
        const result=await page.evaluate(fixtures=>{
            const container=document.getElementById('output');
            const render=(payload,options)=>{container.replaceChildren();return window.BapxA2ui.render(container,payload,options);};
            const product=render(fixtures.product,{publicBaseUrl:'https://shop.example.test'});
            const href=container.querySelector('a')?.href;
            const image=container.querySelector('img');
            const src=image?.src,alt=image?.alt;
            const contact=render(fixtures.contact),contactText=container.textContent;
            const services=render(fixtures.services),servicePath=container.querySelector('a')?.getAttribute('href');
            const publicAsSupport=render(fixtures.product,{scope:'support'});
            const publicAsAdmin=render(fixtures.product,{scope:'admin'});
            const wrongOrder=structuredClone(fixtures.support);wrongOrder.messages.reverse();
            const wrongOrderResult=render(wrongOrder,{scope:'support'});
            const duplicatePrivate=structuredClone(fixtures.enterprise);duplicatePrivate.messages.push(structuredClone(duplicatePrivate.messages[0]));
            const duplicatePrivateResult=render(duplicatePrivate,{scope:'admin'});
            const duplicatePublic=structuredClone(fixtures.product);duplicatePublic.messages.push(structuredClone(duplicatePublic.messages[0]));
            const duplicatePublicResult=render(duplicatePublic);
            const privateDefault=render(fixtures.enterprise);
            const privateSupport=render(fixtures.enterprise,{scope:'support'});
            const privateAdmin=render(fixtures.enterprise,{scope:'admin'});
            const privatePath=container.querySelector('a')?.getAttribute('href');
            const metricRows=container.querySelectorAll('.bapx-a2ui__row').length;
            const recordedPeriod=container.textContent.includes('2026-11-01 to 2026-11-22');
            const support=render(fixtures.support,{scope:'support'}),surfaces=container.children.length;
            const unicodeRendered=render(fixtures.unicodeProduct);
            const unicodeTitle=[...(container.querySelector('img')?.alt||'')].length;
            const unicodeDescription=[...(container.querySelectorAll('small')[1]?.textContent||'')].length;
            const parityRendered=render(fixtures.parityProducts);
            const parityPaths=[...container.querySelectorAll('a')].map(a=>a.getAttribute('href'));
            const parityImage=container.querySelector('img')?.getAttribute('src');
            const wrongOrigin=render(fixtures.product,{publicBaseUrl:'https://shop.example.test/nested'});
            const encoded=structuredClone(fixtures.product);
            encoded.messages[0].createSurface.dataModel.items[0].path='/product/%2e%2e';
            const traversal=render(encoded);
            const remoteImage=structuredClone(fixtures.product);
            remoteImage.messages[0].createSurface.dataModel.items[0].imagePath='https://evil.invalid/pixel.png';
            const imageAttack=render(remoteImage);
            const cycle=structuredClone(fixtures.product);
            cycle.messages[0].createSurface.components[0].children=['root'];
            const cyclic=render(cycle);
            const unsupported=structuredClone(fixtures.support);
            unsupported.messages[1].version='99.0';
            container.replaceChildren();container.textContent='Complete text fallback';
            const malformed=window.BapxA2ui.render(container,unsupported,{scope:'support'});
            const atomic=container.textContent;
            const injection=structuredClone(fixtures.enterprise);
            injection.messages[0].createSurface.dataModel.sections[0].body='<script>window.injected=true</script><img src=x onerror=alert(1)>';
            const textOnly=render(injection,{scope:'admin'});
            const scriptCount=container.querySelectorAll('script,img').length;
            return {product,href,src,alt,contact,contactText,services,servicePath,publicAsSupport,publicAsAdmin,wrongOrderResult,duplicatePrivateResult,duplicatePublicResult,privateDefault,privateSupport,privateAdmin,privatePath,
                support,surfaces,metricRows,recordedPeriod,unicodeRendered,unicodeTitle,unicodeDescription,parityRendered,parityPaths,parityImage,wrongOrigin,traversal,imageAttack,cyclic,malformed,atomic,textOnly,scriptCount};
        },fixtures);
        assert.equal(result.product,true);
        assert.equal(result.href,'https://shop.example.test/product/emerald-ring');
        assert.equal(result.src,'https://shop.example.test/assets/images/fixture-ring.png');
        assert.equal(result.alt,'Emerald Ring');
        assert.equal(result.contact,true);assert.match(result.contactText,/help@example\.test/);
        assert.equal(result.services,true);assert.equal(result.servicePath,'/contact?subject=product#contact-form');
        for(const field of ['publicAsSupport','publicAsAdmin','wrongOrderResult','duplicatePrivateResult','duplicatePublicResult'])assert.equal(result[field],false,field);
        assert.equal(result.privateDefault,false);assert.equal(result.privateSupport,false);assert.equal(result.privateAdmin,true);
        assert.equal(result.privatePath,'/admin/tax-report?report=sales&from=2026-11-01&to=2026-11-22');
        assert.equal(result.support,true);assert.equal(result.surfaces,2);
        assert.equal(result.metricRows,2);assert.equal(result.recordedPeriod,true);
        assert.equal(result.unicodeRendered,true);assert.equal(result.unicodeTitle,160);assert.equal(result.unicodeDescription,240);
        assert.equal(result.parityRendered,true);assert.equal(result.parityPaths.length,3);
        assert.ok(result.parityPaths[0].length>200);assert.equal(decodeURIComponent(result.parityPaths[0]),'/product/'+'தமிழ்'.repeat(10));
        assert.equal(result.parityPaths[1],'/product/foo..bar');assert.equal(result.parityPaths[2].length,2048);
        assert.equal(decodeURIComponent(result.parityImage),'/assets/images/தமிழ்.avif');
        for(const field of ['wrongOrigin','traversal','imageAttack','cyclic','malformed'])assert.equal(result[field],false,field);
        assert.equal(result.atomic,'Complete text fallback');
        assert.equal(result.textOnly,true);assert.equal(result.scriptCount,0);
        await page.evaluate(product=>{
            const container=document.getElementById('output');container.replaceChildren();window.opened=[];
            window.BapxA2ui.render(container,product,{publicBaseUrl:'https://shop.example.test',openUrl:url=>{window.opened.push(url);return Promise.resolve();}});
        },fixtures.product);
        await page.locator('#output a').focus();await page.keyboard.press('Enter');
        assert.deepEqual(await page.evaluate(()=>window.opened),['https://shop.example.test/product/emerald-ring']);
        assert.equal(page.url(),'about:blank','Host callback must prevent iframe navigation');
    } finally {await browser.close();}
});

for (const width of [1280,375]) {
    test(`Support structured primary answer, complete fallback and reload at ${width}px`, {timeout:30000},async()=>{
        const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||undefined,args:['--no-sandbox']});
        const page=await browser.newPage({viewport:{width,height:800}});
        const origin='https://support-a2ui.example.test';
        const template=readFileSync(path.join(root,'views/layouts/app.php'),'utf8');
        const start=template.indexOf('var supportFab=');
        const end=template.indexOf('function showToast(',start);
        assert.ok(start>=0&&end>start,'Actual Support interaction script not found');
        const widget=template.slice(start,end);
        const html='<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>'+css+
            'body{margin:8px;font:16px sans-serif}*{box-sizing:border-box}.support-panel{max-width:420px}.support-panel[hidden]{display:none}textarea{width:100%}</style></head><body>'+
            '<button class="support-fab" aria-controls="support-panel" aria-expanded="false">Support</button>'+
            '<section id="support-panel" class="support-panel" data-support-key="synthetic-support" hidden><button class="support-panel__close">Close support</button>'+
            '<div id="support-log" aria-live="polite"></div><form id="support-form"><input name="_csrf" type="hidden" value="fixture">'+
            '<textarea name="message" aria-label="Support message" required></textarea><button>Send</button></form></section>'+
            '<script>'+scripts['/renderer.js']+'</script><script>'+readFileSync(path.join(root,'assets/agent-status.js'))+'</script><script>'+widget+'</script></body></html>';
        const errors=[];page.on('pageerror',e=>errors.push(e.message));
        let sends=0;
        try {
            await page.route(origin+'/**',route=>{
                const url=new URL(route.request().url());
                if(url.pathname==='/support/ask'){
                    sends++;
                    const surface=structuredClone(sends===2?fixtures.product:fixtures.support);
                    if(sends>2)surface.messages[0].version='unsupported';
                    return route.fulfill({json:{reply:sends===1?'Here is the matching Emerald Ring.\n\nIts published price is ₹1,250.00.':sends===2?'Complete fallback after public-only presentation.':'Complete fallback after unsupported presentation.',
                        a2ui:surface,conversation_id:'a'.repeat(32),turn_id:'b'.repeat(32)}});
                }
                if(url.pathname.startsWith('/assets/images/'))return route.fulfill({contentType:'image/png',body:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZlV0AAAAASUVORK5CYII=','base64')});
                return route.fulfill({contentType:'text/html',body:html});
            });
            await page.goto(origin+'/');
            await page.getByRole('button',{name:'Support',exact:true}).click();
            await page.getByRole('textbox',{name:'Support message'}).fill('Show the Emerald ring');
            await page.getByRole('button',{name:'Send',exact:true}).click();
            await page.locator('.bapx-a2ui--answer').waitFor();
            assert.equal(await page.locator('.support-answer').count(),1);
            assert.equal(await page.getByText('Here is the matching Emerald Ring.',{exact:true}).count(),1,'No duplicate flat answer');
            const product=page.getByRole('link',{name:'View product',exact:true});
            assert.equal(await product.getAttribute('href'),'/product/emerald-ring');
            assert.ok(await product.evaluate(el=>el.getBoundingClientRect().height>=44));
            await product.focus();assert.equal(await product.evaluate(el=>el===document.activeElement),true);
            assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
            await page.reload();
            await page.getByRole('button',{name:'Support',exact:true}).click();
            await page.locator('.bapx-a2ui--answer').waitFor();
            assert.equal(sends,1,'Reload must not execute a tool or resend the question');
            const photos=page.locator('.support-answer summary');
            await photos.press('Enter');
            assert.equal(await page.locator('.support-answer details img').first().isVisible(),true,'Saved Support photo control still works');
            await page.getByRole('textbox',{name:'Support message'}).fill('Another question');
            await page.getByRole('button',{name:'Send',exact:true}).click();
            await page.getByText('Complete fallback after public-only presentation.',{exact:false}).waitFor();
            assert.equal(await page.locator('.bapx-a2ui--answer').count(),1,'Public-only response cannot replace the full native answer');
            assert.equal(await page.locator('.bapx-a2ui:not(.bapx-a2ui--answer)').count(),1,'Rejected public-only response adds no cards');
            await page.getByRole('textbox',{name:'Support message'}).fill('Unsupported response');
            await page.getByRole('button',{name:'Send',exact:true}).click();
            await page.getByText('Complete fallback after unsupported presentation.',{exact:false}).waitFor();
            assert.equal(await page.locator('.bapx-a2ui--answer').count(),1,'Malformed second answer must leave the full fallback');
            assert.deepEqual(errors,[]);
        } finally {await browser.close();}
    });
}
