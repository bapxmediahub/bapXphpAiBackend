'use strict';
const assert=require('node:assert/strict');
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
        properties:{view:{type:'string',enum:['products','articles']},
            query:{type:'string',maxLength:120},limit:{type:'integer',minimum:1,maximum:4}}},
    outputSchema:{type:'object',additionalProperties:false,required:['messages'],
        properties:{messages:{type:'array',minItems:1,maxItems:1,items:{type:'object',required:['version','createSurface']}}}}
};
test('official WebMCP polyfill executes a read-only A2UI v1.0 tool and renderer rejects untrusted code', {timeout:30000},async()=>{
    const server=createServer((req,res)=>{
        const url=new URL(req.url,'http://localhost');
        if(url.pathname==='/style.css'){
            res.writeHead(200,{'content-type':'text/css'});res.end(css);return;
        }
        if(scripts[url.pathname]){
            res.writeHead(200,{'content-type':'application/javascript'});res.end(scripts[url.pathname]);return;
        }
        if(url.pathname==='/api/knowledge/a2ui'){
            res.writeHead(200,{'content-type':'application/json','cache-control':'no-store'});
            res.end(JSON.stringify(a2ui('Public Gemstone Ring','/product/public-ring')));return;
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