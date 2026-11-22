'use strict';
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {createServer}=require('node:http');
const {once}=require('node:events');
const {createRequire}=require('node:module');
const path=require('node:path');
const {pathToFileURL}=require('node:url');
const {test}=require('node:test');
const sdk=createRequire(path.resolve(process.argv[2]||'.','package.json'));
const {chromium}=sdk('playwright');
const {Client,StreamableHTTPClientTransport}=sdk('@modelcontextprotocol/client');

async function readDefaultHostResource(){
    const client=new Client({name:'unmodified-basic-host-profile',version:'1'});
    const methods=[];
    const adapter=async(input,init)=>{
        const request=new Request(input,init);
        if(request.method!=='POST')return new Response('',{status:405});
        const body=await request.text();methods.push(JSON.parse(body).method);
        const headers=Object.fromEntries(request.headers);
        const reply=JSON.parse(execFileSync('php',['tests/public-mcp-request.php',Buffer.from(body).toString('base64'),Buffer.from(JSON.stringify(headers)).toString('base64')],
            {cwd:path.join(__dirname,'..'),encoding:'utf8',maxBuffer:1024*1024}));
        return new Response(reply.body===null?null:JSON.stringify(reply.body),{status:reply.status,headers:{'Content-Type':'application/json'}});
    };
    try{
        await client.connect(new StreamableHTTPClientTransport(new URL('https://example.test/api/mcp'),{fetch:adapter}));
        const {tools}=await client.listTools();
        const uri=tools.find(tool=>tool.name==='get_public_contact')._meta.ui.resourceUri;
        const resource=(await client.readResource({uri})).contents[0];
        const contact=await client.callTool({name:'get_public_contact',arguments:{}});
        assert.equal(methods[0],'initialize');assert.ok(methods.includes('notifications/initialized'));
        assert.equal(resource.mimeType,'text/html;profile=mcp-app');
        assert.ok(contact._meta['in.bapx/publicSurface']);
        return {resource,contact};
    }finally{await client.close();}
}

// A protocol/browser harness using the actual official AppBridge. This is not evidence of ChatGPT installation.
test('default MCP client discovers the widget and official AppBridge renders bounded cards at desktop and mobile',{timeout:45000},async()=>{
    const {AppBridge}=await import(pathToFileURL(sdk.resolve('@modelcontextprotocol/ext-apps/app-bridge')).href);
    const fixture=JSON.parse(execFileSync('php',['tests/public-mcp-app-fixture.php'],{cwd:path.join(__dirname,'..'),encoding:'utf8'}));
    const {resource,contact}=await readDefaultHostResource();
    const html=resource.text;
    const server=createServer((req,res)=>{
        res.setHeader('Content-Type','text/html; charset=utf-8');
        if(req.url==='/sandbox'){
            const source=JSON.stringify(html).replace(/</g,'\\u003c');
            res.end(`<!doctype html><html><body style="margin:0"><script>
                const view=document.createElement('iframe');view.id='view';view.style='border:0;width:100%;height:800px';view.sandbox='allow-scripts allow-same-origin';
                addEventListener('message',event=>{if(event.source===view.contentWindow)parent.postMessage(event.data,'*');else if(event.source===parent)view.contentWindow.postMessage(event.data,'*');});
                view.srcdoc=${source};document.body.append(view);
                <\/script></body></html>`);return;
        }
        res.end('<!doctype html><html><body style="margin:0"><iframe id="sandbox" title="Public information sandbox" sandbox="allow-scripts allow-same-origin" style="border:0;width:100%;height:800px"></iframe></body></html>');
    });
    server.listen(0,'0.0.0.0');await once(server,'listening');
    let browser;
    try {
        browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||undefined,args:['--no-sandbox']});
        for(const {width,teardownWhileOpening=false} of [{width:1100},{width:375},{width:375,teardownWhileOpening:true}]){
            const page=await browser.newPage({viewport:{width,height:900}});
            const errors=[];page.on('pageerror',error=>errors.push(error.message));
            const bridge=new AppBridge(null,{name:'bapx-reference-harness',version:'1'},{openLinks:{}},{hostContext:{theme:'light',displayMode:'inline'}});
            let receive;
            let heldInitializeResolve;const heldInitialize=new Promise(resolve=>{heldInitializeResolve=resolve;});
            let teardownRequestId;let teardownReplies=0;let initializedCount=0;
            const transport={
                start:async()=>{},close:async()=>{},
                send:async message=>{
                    if(teardownWhileOpening&&message.id==='bapx-ui-initialize'&&message.result){heldInitializeResolve();return;}
                    if(message.method==='ui/resource-teardown')teardownRequestId=message.id;
                    return page.evaluate(message=>document.querySelector('#sandbox').contentWindow.postMessage(message,'*'),message);
                },
                set onmessage(handler){receive=handler;},get onmessage(){return receive;}
            };
            let initializedResolve;const initialized=new Promise(resolve=>{initializedResolve=resolve;});
            bridge.oninitialized=()=>{initializedCount++;initializedResolve();};
            const links=[];bridge.onopenlink=async params=>{links.push(params.url);return {};};
            await page.exposeFunction('hostMessage',message=>{
                if(message.id!==undefined&&message.id===teardownRequestId&&!message.method)teardownReplies++;
                receive?.(message);
            });
            await page.goto(`http://127.0.0.1:${server.address().port}/`);
            await page.evaluate(()=>addEventListener('message',event=>{
                if(event.source===document.querySelector('#sandbox').contentWindow)window.hostMessage(event.data);
            }));
            await bridge.connect(transport);
            await page.locator('#sandbox').evaluate((frame,url)=>{frame.src=url;},`http://localhost:${server.address().port}/sandbox`);
            if(teardownWhileOpening){
                await Promise.race([heldInitialize,new Promise((_,reject)=>setTimeout(()=>reject(Error('Initialize request not received')),5000))]);
                await bridge.teardownResource({},{timeout:2000});
                assert.equal(teardownReplies,1,'An opening view acknowledges teardown exactly once');
                assert.equal(initializedCount,0,'Teardown must not initialize the cancelled view');
                assert.deepEqual(errors,[]);
                await bridge.close();await page.close();continue;
            }
            await Promise.race([initialized,new Promise((_,reject)=>setTimeout(()=>reject(Error('Apps handshake timed out')),5000))]);
            await bridge.sendToolInput({arguments:{}});
            await bridge.sendToolResult(fixture.result);
            const view=page.frameLocator('#sandbox').frameLocator('#view');
            await view.getByText('Fixture public ring',{exact:true}).waitFor();
            assert.equal(await view.locator('#mcp-fallback').isVisible(),false);
            assert.match(await view.locator('#mcp-cards').innerText(),/125/);
            const anchor=view.locator('#mcp-cards a').first();
            assert.equal(await anchor.getAttribute('href'),'https://example.test/product/fixture-ring');
            await anchor.focus();await page.keyboard.press('Enter');
            for(let i=0;links.length===0&&i<20;i++)await new Promise(resolve=>setTimeout(resolve,25));
            assert.deepEqual(links,['https://example.test/product/fixture-ring']);
            assert.equal(await view.locator('body').evaluate(body=>body.scrollWidth<=document.documentElement.clientWidth),true);
            bridge.onopenlink=async()=>({isError:true});
            await anchor.click();
            await view.getByText('The link could not be opened. Use the website link in the chat answer.').waitFor();
            assert.equal(await view.locator('#mcp-cards a').count(),1);
            bridge.onopenlink=async()=>({});
            await anchor.click();
            await view.locator('#mcp-status').waitFor({state:'hidden'});
            assert.equal(await view.locator('#mcp-status').textContent(),'','Successful retry clears stale link refusal');
            assert.equal(await view.locator('#mcp-cards a').count(),1);
            const invalid=structuredClone(fixture.result);
            invalid._meta['in.bapx/publicSurface'].messages[0].createSurface.dataModel.items[0].path='javascript:alert(1)';
            await bridge.sendToolResult(invalid);
            await view.locator('#mcp-fallback').waitFor({state:'visible'});
            assert.equal(await view.locator('#mcp-cards a').count(),0);
            await bridge.sendToolResult(contact);
            const contactTitle=contact._meta['in.bapx/publicSurface'].messages[0].createSurface.dataModel.items[0].title;
            await view.locator('#mcp-cards').getByText(contactTitle,{exact:true}).first().waitFor();
            assert.equal(await view.locator('#mcp-fallback').isVisible(),false);
            await bridge.sendToolResult({isError:true,content:[{type:'text',text:'Public information is temporarily unavailable.'}]});
            await view.getByText('Public information is temporarily unavailable.').waitFor();
            assert.deepEqual(errors,[]);
            await bridge.teardownResource({});await bridge.close();await page.close();
        }
    } finally {
        await browser?.close();await new Promise(resolve=>server.close(resolve));
    }
});
