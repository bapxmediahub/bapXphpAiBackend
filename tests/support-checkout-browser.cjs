'use strict';
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {readFileSync}=require('node:fs');
const path=require('node:path');
const {test}=require('node:test');
const {chromium}=require('playwright');
const {shoppingAdapter}=require('./fixtures/public-mcp-shopping.cjs');
const root=path.resolve(__dirname,'..');
const origin='https://cart.example.test';
const view=JSON.parse(execFileSync(process.env.PHP_BINARY||'php',['tests/fixtures/support-checkout.php'],{cwd:root,encoding:'utf8',maxBuffer:2*1024*1024}));
const allowedAssets=new Set(['band.css','a2ui-public.css','chat-commerce.css','support-checkout.css','a2ui-public-renderer.js',
    'agent-status.js','checkout-form.js','public-mcp-shopping.js','support-checkout.js',
    'varahi-amman-ring-front.webp','varahi-amman-ring-side-1.webp']);
const actionNames=new Set(['search_public_products','create_cart','get_cart','set_cart_quantity','review_checkout',
    'prepare_checkout_payment','get_checkout_status']);
let toolId=0;
async function apiTool(adapter,name,args){
    const response=await adapter.fetch(new Request(origin+'/api/mcp',{method:'POST',
        headers:{'Content-Type':'application/json','MCP-Protocol-Version':'2026-07-28','Mcp-Method':'tools/call','Mcp-Name':name},
        body:JSON.stringify({jsonrpc:'2.0',id:'browserless-fixture-'+(++toolId),method:'tools/call',params:{name,arguments:args,
            _meta:{'io.modelcontextprotocol/protocolVersion':'2026-07-28','io.modelcontextprotocol/clientCapabilities':{},
                'io.modelcontextprotocol/clientInfo':{name:'synthetic-api-agent',version:'1.0'}}}})}));
    const body=await response.json();
    assert.equal(response.status,200);assert.equal(body.error,undefined);assert.notEqual(body.result?.isError,true);
    return body.result;
}

for(const width of [1280,375])test(`native Support cart, inline checkout and private recovery at ${width}px`,{timeout:180000},async()=>{
    const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||undefined,args:['--no-sandbox']});
    const context=await browser.newContext({viewport:{width,height:850}});
    const page=await context.newPage();
    const adapter=shoppingAdapter();
    const errors=[];page.on('pageerror',error=>errors.push(error.message));
    let asks=0;
    let heldRefresh=null;
    await context.addCookies([{name:'PHPSESSID',value:'synthetic-owner-cookie',url:origin}]);
    try{
        await page.route('**/*',async route=>{
            const req=route.request(),url=new URL(req.url());
            assert.equal(url.origin,origin,'Fixture must never contact a provider or production host');
            if(url.pathname==='/api/mcp'){
                const body=JSON.parse(req.postData());
                assert.ok(actionNames.has(body.params.name));
                assert.equal(req.headers().cookie,undefined,'Anonymous commerce must not send even a signed-in owner cookie');
                if(heldRefresh&&body.params.name==='get_cart'){
                    const held=heldRefresh;heldRefresh=null;held.started();await held.pending;
                }
                const response=await adapter.fetch(new Request(req.url(),{method:'POST',headers:req.headers(),body:req.postData()}));
                return route.fulfill({status:response.status,contentType:'application/json',body:await response.text()});
            }
            if(url.pathname==='/support/ask'){asks++;return route.fulfill({json:view.answer});}
            if(url.pathname.startsWith('/assets/')&&allowedAssets.has(path.basename(url.pathname))){
                const type=url.pathname.endsWith('.js')?'application/javascript':url.pathname.endsWith('.css')?'text/css':'image/webp';
                return route.fulfill({contentType:type,body:readFileSync(path.join(root,url.pathname))});
            }
            if(url.pathname==='/'||url.pathname==='/favicon.ico')return route.fulfill({contentType:'text/html',body:url.pathname==='/'?view.html:''});
            return route.fulfill({status:404,body:'Fixture asset unavailable'});
        });
        await page.goto(origin);
        await page.getByRole('button',{name:'Support',exact:true}).click();
        assert.equal(await page.locator('.support-fab').isVisible(),false,'The floating launcher must not overlap the open Support canvas');
        await page.getByRole('textbox',{name:'Support message'}).fill('Add the Sri Varahi Amman Ring to my cart.');
        await page.getByRole('button',{name:'Send',exact:true}).click();
        const add=page.locator('#support-log').getByRole('button',{name:'Add to cart',exact:true});
        await add.waitFor();
        assert.equal(adapter.calls.length,0,'A bot answer and card render cannot mutate shopping state');
        await add.press('Enter');
        const pane=page.locator('#support-commerce');
        await pane.getByText('Added to this cart.',{exact:false}).waitFor();
        assert.equal(adapter.calls.filter(call=>call.params?.name==='create_cart').length,1);
        assert.equal(adapter.calls.filter(call=>call.params?.name==='set_cart_quantity').length,1);
        assert.equal(await pane.locator('.mcp-shop__line').count(),1);
        await page.getByRole('button',{name:'Back to chat',exact:true}).click();
        assert.equal(await add.evaluate(element=>element===document.activeElement),true,'Back returns focus to the product control');
        await page.locator('#support-log').getByRole('button',{name:'Checkout',exact:true}).click();
        const name=pane.getByRole('textbox',{name:'Full name',exact:true});
        await name.waitFor();
        await name.fill('PRIVATE_FORM_FIXTURE');
        await pane.getByRole('textbox',{name:'Email',exact:true}).fill('private-form@example.test');
        await page.evaluate(()=>window.supportSaveLog());
        const stored=await page.evaluate(()=>Object.fromEntries(Object.keys(sessionStorage).map(key=>[key,sessionStorage.getItem(key)])));
        assert.ok(Object.keys(stored).some(key=>key.startsWith('bapx-support-commerce:v1:')),'Cart capability has separate bounded recovery');
        for(const [key,value]of Object.entries(stored)){
            assert.doesNotMatch(value,/PRIVATE_FORM_FIXTURE|private-form@example\.test/,'Typed delivery data cannot enter chat or native recovery');
            if(!key.startsWith('bapx-support-commerce:'))assert.doesNotMatch(value,/cart_[a-f0-9]{32}\.[a-f0-9]{64}/,'Capabilities cannot enter the saved conversation');
        }
        const before=adapter.calls.length;
        const recoveryKey=Object.keys(stored).find(key=>key.startsWith('bapx-support-commerce:v1:'));
        const savedAccess=JSON.parse(stored[recoveryKey]);
        adapter.failFor('get_cart',true);
        await page.reload();
        await page.getByRole('button',{name:'Support',exact:true}).click();
        await page.getByRole('button',{name:'Cart & checkout',exact:true}).click();
        const retry=pane.getByRole('button',{name:'Retry saved access',exact:true});
        await retry.waitFor();
        await page.waitForFunction(()=>[...document.querySelectorAll('#support-commerce button')]
            .some(button=>button.textContent==='Retry saved access'&&!button.disabled));
        assert.equal(await pane.locator('.mcp-shop__line').count(),0,'Failed refresh must not show unverified saved cart facts');
        assert.equal(await page.evaluate(key=>JSON.parse(sessionStorage.getItem(key)).currentCart.cartToken,recoveryKey),
            savedAccess.currentCart.cartToken,'A transient failure must preserve the only recovery capability');
        adapter.failFor('get_cart',false);
        await retry.click();
        await pane.locator('.mcp-shop__line').waitFor();
        assert.equal(asks,1,'Reload must not resend Support chat');
        assert.ok(adapter.calls.slice(before).every(call=>call.params?.name==='get_cart'),'Reload refreshes saved facts without repeating a mutation');
        assert.equal(await pane.locator('input,textarea').evaluateAll(inputs=>inputs.some(input=>input.value==='PRIVATE_FORM_FIXTURE')),false,
            'Reload does not restore the private delivery draft');
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
        const box=await page.locator('#support-panel').boundingBox();
        assert.ok(box.x>=0&&box.x+box.width<=width+1,'Chat checkout stays within the mobile/desktop viewport');
        await page.getByRole('button',{name:'Back to chat',exact:true}).click();
        await page.locator('#support-log').getByRole('button',{name:'Buy now',exact:true}).click();
        await pane.getByRole('textbox',{name:'Full name',exact:true}).waitFor();
        assert.equal(adapter.calls.filter(call=>call.params?.name==='create_cart').length,2,'Buy now uses a separate cart');
        assert.ok(!adapter.calls.some(call=>call.params?.name==='prepare_checkout_payment'),'Opening/filling checkout cannot start payment');
        const access=await page.evaluate(key=>JSON.parse(sessionStorage.getItem(key)),recoveryKey);
        const args={cartToken:access.currentCart.cartToken,expectedRevision:access.currentCart.revision,
            slug:'sri-varahi-amman-ring',quantity:3};
        const canonical=await apiTool(adapter,'set_cart_quantity',args);
        const synced=await page.evaluate(async({args,result})=>window.BapxSupportCheckout.acceptToolResult('set_cart_quantity',args,result),{args,result:canonical});
        assert.equal(synced.rendered,true,'A browserless canonical tool response must update native state before completion');
        assert.equal(await pane.locator('.mcp-shop__quantity input').first().inputValue(),'3');
        const afterSync=await page.evaluate(key=>JSON.parse(sessionStorage.getItem(key)),recoveryKey);
        assert.equal(afterSync.currentCart.revision,canonical.structuredContent.revision);
        assert.equal(afterSync.buyNowCart.cartToken,access.buyNowCart.cartToken,'An agent updating the current cart cannot replace the separate Buy now cart');
        const wrongFamily=await page.evaluate(async({args,result})=>window.BapxSupportCheckout.acceptToolResult('get_checkout_status',args,result),{args,result:canonical});
        assert.equal(wrongFamily.rendered,false);
        assert.equal(await pane.locator('.mcp-shop__quantity input').first().inputValue(),'3','Rejected tool families leave the visible canonical cart intact');
        await pane.getByRole('textbox',{name:'Full name',exact:true}).fill('CANCEL_PRESERVES_DRAFT');
        const readArgs={cartToken:access.currentCart.cartToken},readResult=await apiTool(adapter,'get_cart',readArgs);
        let releaseRefresh,refreshStarted;
        const started=new Promise(resolve=>{refreshStarted=resolve;});
        heldRefresh={started:refreshStarted,pending:new Promise(resolve=>{releaseRefresh=resolve;})};
        await pane.getByRole('button',{name:'Refresh cart',exact:true}).click();await started;
        const cancelled=await page.evaluate(async({args,result})=>{
            const controller=new AbortController();
            const queued=window.BapxSupportCheckout.acceptToolResult('get_cart',args,result,{signal:controller.signal});
            // Let the native adapter reach the busy shared controller before cancellation.
            await new Promise(resolve=>setTimeout(resolve,0));controller.abort();
            return Promise.race([queued,new Promise(resolve=>setTimeout(()=>resolve({timedOut:true}),2000))]);
        },{args:readArgs,result:readResult});
        assert.equal(cancelled.rendered,false,'Cancellation must release a queued sync without waiting for or replacing the active human action');
        releaseRefresh();
        await pane.getByText('Cart refreshed. Review current quantities, prices and availability.',{exact:true}).waitFor();
        assert.equal(await pane.getByRole('textbox',{name:'Full name',exact:true}).inputValue(),'CANCEL_PRESERVES_DRAFT');
        const beforeReset=await page.evaluate(key=>sessionStorage.getItem(key),recoveryKey);
        const beforeResetCalls=adapter.calls.length;
        await page.getByRole('button',{name:'Back to chat',exact:true}).click();
        const newChat=page.getByRole('button',{name:'New chat',exact:true});
        const control=await newChat.boundingBox();
        assert.ok(control&&control.height>=44&&control.x+control.width<=width,'New chat stays reachable in the real Support header');
        await newChat.press('Enter');
        assert.equal(await page.locator('#support-log .support-answer').count(),0);
        assert.equal(await page.evaluate(key=>sessionStorage.getItem(key),recoveryKey),beforeReset);
        assert.equal(adapter.calls.length,beforeResetCalls,'Starting a chat cannot call a shopping tool');
        assert.equal(asks,1,'Starting a chat cannot send a question');
        assert.deepEqual(errors,[]);
    }finally{await context.close();await browser.close();}
});

test('native Support keeps verified payment status available when Shop is disabled',{timeout:180000},async()=>{
    const disabledView=JSON.parse(execFileSync(process.env.PHP_BINARY||'php',['tests/fixtures/support-checkout.php','--shop-disabled'],
        {cwd:root,encoding:'utf8',maxBuffer:2*1024*1024}));
    const supportKey=disabledView.html.match(/data-support-key="([^"]*)"/)?.[1];
    assert.match(supportKey,/^sps-support-chat:[a-f0-9]{64}$/);
    const adapter=shoppingAdapter();
    const created=(await apiTool(adapter,'create_cart',{})).structuredContent;
    const cart=(await apiTool(adapter,'set_cart_quantity',{cartToken:created.cartToken,expectedRevision:created.revision,
        slug:'sri-varahi-amman-ring',quantity:1})).structuredContent;
    const review=(await apiTool(adapter,'review_checkout',{cartToken:created.cartToken,expectedRevision:cart.revision,
        delivery:{name:'Synthetic Shopper',email:'synthetic-shopper@example.test',phone:'9876543210',
            address:'12 Synthetic Test Street',city:'Chennai',pincode:'600001',state:'Tamil Nadu',gstin:''}})).structuredContent;
    const payment=(await apiTool(adapter,'prepare_checkout_payment',{cartToken:created.cartToken,expectedRevision:review.cartRevision,
        quoteId:review.quote.id,confirm:true})).structuredContent;
    adapter.markPaid(payment.attemptId);adapter.advance(6);
    const receipt={version:1,currentCart:{cartToken:created.cartToken,revision:review.cartRevision,expiresAt:cart.expiresAt},
        buyNowCart:null,checkout:{paymentToken:payment.paymentToken,expiresAt:payment.statusExpiresAt,kind:'current'}};
    const before=adapter.calls.length;
    const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||undefined,args:['--no-sandbox']});
    const context=await browser.newContext({viewport:{width:375,height:850}}),page=await context.newPage();
    const errors=[];page.on('pageerror',error=>errors.push(error.message));
    try{
        await context.addInitScript(({origin,receipt,supportKey})=>{
            sessionStorage.setItem('bapx-support-commerce:v1:'+origin,JSON.stringify(receipt));
            sessionStorage.setItem(supportKey,'<div class="bapx-a2ui__card"><a href="/product/sri-varahi-amman-ring">Ring</a>'
                +'<div class="support-commerce-actions"><button data-support-commerce="add" data-product-path="/product/sri-varahi-amman-ring">Add to cart</button></div></div>');
        },{origin,receipt,supportKey});
        await page.route('**/*',async route=>{
            const req=route.request(),url=new URL(req.url());
            assert.equal(url.origin,origin,'Synthetic status recovery must never contact a real provider');
            if(url.pathname==='/api/mcp'){
                assert.equal(JSON.parse(req.postData()).params.name,'get_checkout_status','Shop-off native recovery permits status only');
                assert.equal(req.headers().cookie,undefined);
                const response=await adapter.fetch(new Request(req.url(),{method:'POST',headers:req.headers(),body:req.postData()}));
                return route.fulfill({status:response.status,contentType:'application/json',body:await response.text()});
            }
            if(url.pathname.startsWith('/assets/')&&allowedAssets.has(path.basename(url.pathname)))return route.fulfill({
                contentType:url.pathname.endsWith('.js')?'application/javascript':url.pathname.endsWith('.css')?'text/css':'image/webp',
                body:readFileSync(path.join(root,url.pathname))});
            return route.fulfill({contentType:'text/html',body:url.pathname==='/'?disabledView.html:''});
        });
        await page.goto(origin);
        await page.getByRole('button',{name:'Support',exact:true}).click();
        await page.getByRole('button',{name:'Checkout status',exact:true}).click();
        const pane=page.locator('#support-commerce');
        await pane.getByText('captured',{exact:true}).waitFor();
        await pane.getByText('saved',{exact:true}).waitFor();
        assert.equal(await page.locator('#support-log button[data-support-commerce]').count(),0,'Restored shopping controls must be removed when Shop is off');
        assert.equal(await pane.getByRole('textbox').count(),0,'Status-only recovery cannot render delivery or coupon forms');
        assert.equal(await pane.getByRole('button',{name:/Add to cart|Buy now|Review checkout|Confirm .*prepare payment|Continue to payment/}).count(),0);
        assert.ok(adapter.calls.slice(before).length>0);
        assert.ok(adapter.calls.slice(before).every(call=>call.params?.name==='get_checkout_status'));
        assert.doesNotMatch(await pane.textContent(),/synthetic-shopper@example\.test|12 Synthetic Test Street|payment_[a-f0-9]{32}\./,
            'Status UI cannot print contact fields or the status capability');
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
        assert.deepEqual(errors,[]);
    }finally{await context.close();await browser.close();}
});


// Reuse the real PHP contact template, but prevent any production contact, booking, or
// mail action. This exercises the native Support widget without a second form implementation.
const contactFixtureCode = [
    "putenv('BAPX_TEST_MODE=1'); require 'app/bootstrap.php';",
    "$_SESSION['csrf_token']='fixture-csrf';",
    "$subjects=\\App\\Services\\PublicKnowledgeService::contactSubjects();",
    "$publicContact=(new \\App\\Services\\PublicKnowledgeService())->contact();",
    "$submission=['name'=>'','email'=>'','phone'=>'','subject'=>'general','message'=>''];",
    "$errors=[]; $success=false; ob_start(); include 'views/public/contact.php'; $normal=ob_get_clean();",
    "$success=true; ob_start(); include 'views/public/contact.php'; $received=ob_get_clean();",
    "echo json_encode(['normal'=>$normal,'received'=>$received],JSON_THROW_ON_ERROR);"
].join(' ');
const contactView=JSON.parse(execFileSync(process.env.PHP_BINARY||'php',['-r',contactFixtureCode],{
    cwd:root,encoding:'utf8',maxBuffer:2*1024*1024
}));

for(const width of [1280,375])test(`Support shows only an opaque reference for evaluated failures at ${width}px`,
    {timeout:60000},async()=>{
    const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||undefined,args:['--no-sandbox']});
    const context=await browser.newContext({viewport:{width,height:width===375?500:850}}),page=await context.newPage();
    const errors=[];page.on('pageerror',error=>errors.push(error.message));
    const reference='01ab23cd45ef6789';
    const replayReference='9876fe54dc32ba10';
    const malformedReference='z1ab23cd45ef6789';
    const failure={
        reply:'The AI model is temporarily unavailable. Please try again shortly, or use /contact to reach the team.',
        actions:[{type:'navigate',label:'Contact Us',path:'/contact'}],
        turn_id:'a'.repeat(32),
        evaluation:{passed:false,checks:{turn_completed:false}},
        run:{outcome:'error',trace_id:reference,reason:'PROVIDER_REASON_FIXTURE',
            attempts:[{model:'PRIVATE_MODEL_FIXTURE'}],tools:[{name:'PRIVATE_TOOL_FIXTURE'}]},
    };
    const replies=[
        {status:503,headers:{'X-Agent-Trace':reference},json:failure},
        {status:503,json:{...failure,replayed:true,actions:undefined,run:{trace_id:replayReference}}},
        {status:503,headers:{'X-Agent-Trace':malformedReference},json:failure},
        {status:503,json:{...failure,run:{trace_id:reference+'\n'}}},
        {status:503,json:{...failure,run:undefined}},
        {status:503,headers:{'X-Agent-Trace':reference},json:{error:'The request could not be completed safely.',
            request_status:'incomplete'}},
        {status:200,headers:{'X-Agent-Trace':reference},json:{reply:'Support is available.',run:{trace_id:reference}}},
    ];
    let asks=0;
    try{
        await page.route('**/*',route=>{
            const url=new URL(route.request().url());
            assert.equal(url.origin,origin,'Failure fixture cannot visit production');
            if(url.pathname==='/support/ask'){
                assert.equal(route.request().method(),'POST');
                return route.fulfill(replies[asks++]);
            }
            if(url.pathname==='/contact')return route.fulfill({contentType:'text/html',body:contactView.normal});
            if(url.pathname.startsWith('/assets/')&&allowedAssets.has(path.basename(url.pathname))){
                return route.fulfill({contentType:url.pathname.endsWith('.js')?'application/javascript':
                    url.pathname.endsWith('.css')?'text/css':'image/webp',body:readFileSync(path.join(root,url.pathname))});
            }
            if(url.pathname==='/')return route.fulfill({contentType:'text/html',body:view.html});
            return route.fulfill({status:404,body:'Fixture route unavailable'});
        });
        const launcher=page.getByRole('button',{name:'Support',exact:true});
        const composer=page.getByRole('textbox',{name:'Support message'});
        const send=page.getByRole('button',{name:'Send',exact:true});
        const log=page.getByRole('region',{name:'Support conversation'});
        await page.goto(origin);
        await launcher.press('Enter');
        assert.equal(await log.getAttribute('aria-live'),'polite');
        for(let index=0;index<replies.length;index++){
            await composer.fill('Check Support recovery '+(index+1));
            await send.press('Enter');
            const answer=log.locator('.support-answer').nth(index);
            await answer.waitFor();
            const expected=[reference,replayReference][index];
            if(expected){
                await answer.getByText('Support reference: '+expected,{exact:false}).waitFor({timeout:3000});
                assert.match(await answer.ariaSnapshot(),new RegExp(expected));
                assert.match(await answer.innerText(),/temporarily unavailable/);
            }else{
                const text=await answer.innerText();
                assert.doesNotMatch(text,/Support reference/);
                assert.equal(text.includes(malformedReference),false);
            }
            assert.equal(await composer.isEnabled(),true);
            assert.equal(await send.isEnabled(),true);
        }
        assert.doesNotMatch(await log.innerText(),/PROVIDER_REASON_FIXTURE|PRIVATE_(?:MODEL|TOOL)_FIXTURE/);
        assert.equal(await log.getByText(/Check whether your last request completed/).count(),1);
        assert.equal(await log.evaluate(el=>el.scrollWidth<=el.clientWidth+1),true);

        await page.reload();
        await launcher.click();
        assert.equal(await log.getByText(/Support reference:/).count(),2,'Reopening the same tab restores both references');
        const contact=log.getByRole('link',{name:'Contact Us',exact:true}).first();
        await contact.press('Enter');
        const enquiry=page.getByRole('region',{name:'Send an enquiry to Fixture shop'});
        await enquiry.locator('form.contact-form').waitFor();
        await enquiry.getByRole('button',{name:'Back to chat'}).click();
        assert.equal(await contact.evaluate(el=>document.activeElement===el),true);
        assert.equal(asks,replies.length,'Recovery and reload do not repeat the request');
        assert.deepEqual(errors,[]);
    }finally{await context.close();await browser.close();}
});

for(const width of [1280,375])test('Existing contact form opens inside Support, preserves privacy and confirms only real submission at '+width+'px',
    {timeout:120000},async()=>{
    const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||undefined,args:['--no-sandbox']});
    const context=await browser.newContext({viewport:{width,height:850}}),page=await context.newPage();
    const errors=[];page.on('pageerror',error=>errors.push(error.message));
    let questions=0,posts=0,postsData=[];
    try{
        await page.route('**/*',async route=>{
            const req=route.request(),url=new URL(req.url());
            assert.equal(url.origin,origin,'Inline enquiry fixture cannot visit production.');
            if(url.pathname==='/support/ask'){
                questions++;
                return route.fulfill({json:{
                    reply:'New consultation appointments cannot currently be booked online. You may send a general enquiry; it is not a booking.',
                    ui:{kind:'contact_enquiry',subject:'general'}
                }});
            }
            if(url.pathname==='/contact'){
                if(req.method()==='GET')return route.fulfill({contentType:'text/html',body:contactView.normal});
                assert.equal(req.method(),'POST');
                posts++;postsData.push(req.postData());
                return route.fulfill({status:posts===1?422:200,contentType:'text/html',
                    body:posts===1?contactView.normal:contactView.received});
            }
            if(url.pathname.startsWith('/assets/')&&allowedAssets.has(path.basename(url.pathname))){
                return route.fulfill({contentType:url.pathname.endsWith('.js')?'application/javascript':
                    url.pathname.endsWith('.css')?'text/css':'image/webp',
                    body:readFileSync(path.join(root,url.pathname))});
            }
            if(url.pathname==='/'||url.pathname==='/favicon.ico')
                return route.fulfill({contentType:'text/html',body:url.pathname==='/'?view.html:''});
            return route.fulfill({status:404,body:'Fixture route unavailable'});
        });
        await page.goto(origin);
        await page.getByRole('button',{name:'Support',exact:true}).click();
        await page.getByRole('textbox',{name:'Support message'}).fill('Please book me an appointment');
        await page.getByRole('button',{name:'Send',exact:true}).click();
        const panel=page.locator('#support-enquiry');
        await panel.getByRole('heading',{name:'Send an enquiry'}).waitFor();
        await panel.locator('form.contact-form').waitFor();
        assert.equal(questions,1);
        assert.equal(posts,0,'Opening the form does not submit a message');
        assert.equal(await panel.locator('form').getAttribute('action'),'/contact');
        assert.equal(await panel.locator('select[name="subject"]').inputValue(),'general');
        assert.equal(await panel.locator('input[name="_csrf"]').inputValue(),'fixture-csrf');
        assert.equal(await panel.locator('form').getAttribute('toolautosubmit'),null,
            'Writing a contact enquiry may never auto-submit an agent tool');

        if(width===375){
            await page.setViewportSize({width,height:500});
            await panel.evaluate(el=>{el.scrollTop=el.scrollHeight;});
            assert.ok(await panel.evaluate(el=>el.scrollTop>0),'The enquiry pane must be scrollable');
            await page.getByRole('button',{name:'Close support'}).click();
            await page.getByRole('button',{name:'Support',exact:true}).press('Enter');
            const heading=panel.getByRole('heading',{name:'Send an enquiry'});
            assert.equal(await heading.evaluate(el=>{
                const pane=el.closest('.support-enquiry');
                return document.activeElement===el && el.getBoundingClientRect().top>=pane.getBoundingClientRect().top;
            }),true,'Reopening the pane must reveal its focused heading');
        }
        await panel.locator('input[name="name"]').fill('PRIVATE_INLINE_NAME');
        await panel.locator('input[name="email"]').fill('private-inline@example.test');
        await panel.locator('textarea[name="message"]').fill('PRIVATE_INLINE_APPOINTMENT_REQUEST');
        await page.evaluate(()=>supportSaveLog());
        const saved=await page.evaluate(()=>Object.values(sessionStorage).join(' '));
        assert.doesNotMatch(saved,/PRIVATE_INLINE_NAME|private-inline@example\.test|PRIVATE_INLINE_APPOINTMENT_REQUEST/,
            'Private contact drafts cannot be persisted in the Support conversation or cart recovery');
        assert.equal(await page.locator('#support-log').isVisible(),false,'The enquiry uses the existing panel, not a second page');
        await panel.getByRole('button',{name:'Send message',exact:true}).click();
        await panel.getByText('Please check the required fields and try again.').waitFor();
        assert.equal(posts,1);
        assert.equal(await panel.locator('input[name="email"]').inputValue(),'private-inline@example.test');
        assert.doesNotMatch(await panel.getByRole('status').innerText(),/received|booked/i);
        // A second attempt must be an explicit visitor click, not an agent/network retry.
        await panel.getByRole('button',{name:'Send message',exact:true}).click();
        await panel.getByText(/Your enquiry was received/).waitFor();
        assert.equal(posts,2);
        assert.ok(postsData.every(body=>body.includes('fixture-csrf')&&body.includes('PRIVATE_INLINE_NAME')));
        assert.equal(await panel.locator('form.contact-form').count(),0,'Only the existing server receipt confirms submission');
        assert.match(await panel.getByRole('status').innerText(),/no appointment has been booked/);
        await panel.getByRole('button',{name:'Back to chat'}).click();
        assert.equal(await page.locator('#support-log').isVisible(),true);
        assert.equal(page.url(),origin+'/','Enquiry stays in the same Support page');
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true);
        const box=await page.locator('#support-panel').boundingBox();
        assert.ok(box.x>=0&&box.x+box.width<=width+1,'Existing Support pane cannot exceed the viewport');
        assert.deepEqual(errors,[]);
    }finally{await context.close();await browser.close();}
});
