'use strict';
// Real Chromium + official/native registry. PHP business services run with
// isolated memory state and a synthetic payment provider; never production.
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {readFileSync}=require('node:fs');
const path=require('node:path');
const {shoppingAdapter}=require('./fixtures/public-mcp-shopping.cjs');
const fixture=process.argv[2];
if(!fixture)throw Error('Provide the isolated Playwright fixture directory');
const {chromium}=require(path.resolve(fixture,'node_modules/playwright'));
const native=process.argv.includes('--native');
const root=path.resolve(__dirname,'..'),origin='https://cart.example.test',protocol='2026-07-28';
const names=['create_cart','get_cart','set_cart_quantity','review_checkout','prepare_checkout_payment','get_checkout_status'];
const view=JSON.parse(execFileSync('php',['tests/fixtures/support-checkout.php'],{cwd:root,encoding:'utf8',maxBuffer:2*1024*1024}));
const assets=new Set(['band.css','a2ui-public.css','chat-commerce.css','support-checkout.css','a2ui-public-renderer.js',
    'agent-status.js','checkout-form.js','public-mcp-shopping.js','support-checkout.js','public-commerce-webmcp.js','polyfill-0.1.1.js']);
const images=new Set(['/assets/images/products/varahi-amman-ring-front.webp','/assets/images/products/varahi-amman-ring-side-1.webp']);
async function main(){
    const browser=await chromium.launch({headless:!native,executablePath:process.env.CHROMIUM_PATH||undefined,
        args:native?['--no-sandbox','--enable-features=WebMCP,WebMCPTesting,DevToolsWebMCPSupport']
            :['--no-sandbox','--disable-features=WebMCP,WebMCPTesting']});
    try{for(const width of [1280,375]){
        const adapter=shoppingAdapter();
        const listed=await adapter.fetch(origin+'/api/mcp',{method:'POST',headers:{'Content-Type':'application/json',
            'MCP-Protocol-Version':protocol,'Mcp-Method':'tools/list'},body:JSON.stringify({jsonrpc:'2.0',id:1,method:'tools/list',
                params:{_meta:{'io.modelcontextprotocol/protocolVersion':protocol,'io.modelcontextprotocol/clientCapabilities':{}}}})});
        const tools=(await listed.json()).result.tools.filter(tool=>names.includes(tool.name));
        assert.equal(tools.length,6);
        const config=JSON.stringify({publicOrigin:origin,protocolVersion:protocol,tools});
        const head=(native?'':'<script src="/assets/vendor/webmcp/polyfill-0.1.1.js"></script>')
            +'<script type="application/json" id="bapx-public-commerce-schema">'+config+'</script>'
            +'<script defer src="/assets/public-commerce-webmcp.js"></script>';
        const html=view.html.replace('</head>',head+'</head>');
        const context=await browser.newContext({viewport:{width,height:850}}),page=await context.newPage();
        const errors=[];page.on('pageerror',error=>errors.push(error.message));
        await context.addCookies([{name:'PHPSESSID',value:'synthetic-owner-session',url:origin}]);
        try{
            await page.route('**/*',async route=>{
                const request=route.request(),url=new URL(request.url());
                assert.equal(url.origin,origin,'No third-party payment navigation, script or image is allowed in this fixture');
                if(url.pathname==='/api/mcp'){
                    assert.equal(request.headers().cookie,undefined,'Browser tools cannot attach owner/customer sessions');
                    const response=await adapter.fetch(request.url(),{method:'POST',headers:request.headers(),body:request.postData()});
                    return route.fulfill({status:response.status,contentType:'application/json',body:await response.text()});
                }
                if(images.has(url.pathname))return route.fulfill({contentType:'image/webp',body:readFileSync(path.join(root,url.pathname))});
                if(url.pathname.startsWith('/assets/')&&assets.has(path.basename(url.pathname)))return route.fulfill({
                    contentType:url.pathname.endsWith('.js')?'application/javascript':'text/css',body:readFileSync(path.join(root,url.pathname))});
                if(url.pathname==='/')return route.fulfill({contentType:'text/html',body:html});
                return route.fulfill({status:404,body:'Fixture route unavailable'});
            });
            await page.goto(origin,{waitUntil:'load'});
            await page.waitForFunction(async()=>typeof document.modelContext?.getTools==='function'&&(await document.modelContext.getTools()).length===6);
            let cdp,executionContextId;
            if(native){
                assert.equal(await page.locator('script[src*="polyfill"]').count(),0);
                cdp=await context.newCDPSession(page);
                const {frameTree}=await cdp.send('Page.getFrameTree');
                ({executionContextId}=await cdp.send('Page.createIsolatedWorld',{frameId:frameTree.frame.id,worldName:'commerce-external-agent'}));
            }
            const invoke=async(name,args={})=>{
                const expression='(async()=>{const tools=await document.modelContext.getTools();const result=await document.modelContext.executeTool(tools.find(t=>t.name==='+JSON.stringify(name)+'),'+JSON.stringify(args)+');return typeof result==="string"?JSON.parse(result):result;})()';
                if(!native)return page.evaluate(expression);
                const result=await cdp.send('Runtime.evaluate',{contextId:executionContextId,awaitPromise:true,returnByValue:true,expression});
                assert.equal(result.exceptionDetails,undefined,'Native isolated agent execution must succeed');
                return result.result.value;
            };
            const cart=await invoke('create_cart');
            const changed=await invoke('set_cart_quantity',{cartToken:cart.cartToken,expectedRevision:cart.revision,slug:'sri-varahi-amman-ring',quantity:2});
            const pane=page.locator('#support-commerce');
            assert.equal(await pane.isVisible(),true,'A resolved WebMCP write has synchronized the visible chat');
            assert.equal(await pane.locator('.mcp-shop__line').count(),1);
            assert.match(await pane.innerText(),/Sri Varahi Amman Ring/);
            await pane.getByRole('img',{name:'Sri Varahi Amman Ring',exact:true}).first().scrollIntoViewIfNeeded();
            await page.waitForFunction(()=>{const image=document.querySelector('#support-commerce img');return image?.complete&&image.naturalWidth>0;});
            assert.equal(changed.itemCount,2);
            const review=await invoke('review_checkout',{cartToken:cart.cartToken,expectedRevision:changed.revision,delivery:{
                name:'PRIVATE_WEBMCP_BUYER',email:'private-webmcp@example.test',phone:'9876543210',address:'PRIVATE_WEBMCP_ADDRESS',
                city:'Chennai',pincode:'600001',state:'Tamil Nadu'}});
            assert.equal(review.stage,'review_ready');
            await pane.getByRole('button',{name:/Confirm .* and prepare payment/}).waitFor();
            const serialized=await page.evaluate(()=>{window.supportSaveLog();return JSON.stringify(Object.fromEntries(Object.keys(sessionStorage).map(key=>[key,sessionStorage.getItem(key)])));});
            assert.doesNotMatch(serialized,/PRIVATE_WEBMCP|private-webmcp@example/,'Private delivery cannot enter saved chat or recovery');
            assert.equal(adapter.state.gatewayCalls.filter(call=>call.operation==='create_link').length,0,'Review must not start payment');
            const prepared=await invoke('prepare_checkout_payment',{cartToken:cart.cartToken,expectedRevision:review.cartRevision,quoteId:review.quote.id,confirm:true});
            assert.equal(prepared.stage,'payment_ready');
            await pane.getByRole('button',{name:'Continue to payment',exact:true}).waitFor();
            assert.equal(page.url(),origin+'/','Preparing a payment cannot automatically navigate');
            const pending=await invoke('get_checkout_status',{paymentToken:prepared.paymentToken});
            assert.equal(pending.orderStatus,'not_created');
            adapter.markPaid(prepared.attemptId);adapter.advance(31);
            const paid=await invoke('get_checkout_status',{paymentToken:prepared.paymentToken});
            assert.equal(paid.paymentStatus,'captured');assert.equal(paid.orderStatus,'saved');
            assert.match(await pane.innerText(),/saved|recorded/i);
            const current=await invoke('get_cart',{cartToken:cart.cartToken});
            assert.equal(current.status,'checkout_locked');assert.equal(current.checkout.paymentToken,prepared.paymentToken);
            assert.equal(adapter.state.gatewayCalls.filter(call=>call.operation==='create_link').length,1);
            assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
            const box=await page.locator('#support-panel').boundingBox();
            assert.ok(box.x>=0&&box.x+box.width<=width+1);
            assert.deepEqual(errors,[]);
            if(cdp)await cdp.detach();
            console.log((native?'NATIVE isolated agent':'OFFICIAL POLYFILL')+' commerce '+width+'px: six named actions, visible cart/inline quote/status, no cookie/PII history/navigation, one synthetic payment PASS.');
        }finally{await context.close();}
    }}finally{await browser.close();}
}
main().catch(error=>{console.error(error);process.exitCode=1;});
