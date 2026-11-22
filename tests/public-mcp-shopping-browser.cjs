'use strict';
const assert=require('node:assert/strict');
const {createServer}=require('node:http');
const {once}=require('node:events');
const {createRequire}=require('node:module');
const path=require('node:path');
const {pathToFileURL}=require('node:url');
const {test}=require('node:test');
const {shoppingAdapter}=require('./fixtures/public-mcp-shopping.cjs');
const sdk=createRequire(path.resolve(process.argv[2]||'.','package.json'));
const {chromium}=sdk('playwright');
const {Client,StreamableHTTPClientTransport}=sdk('@modelcontextprotocol/client');
const ring='sri-varahi-amman-ring';
const selection={slugs:[ring,'varahi-amman-pendant'],limit:2};
const deferred=()=>{let resolve;const promise=new Promise(done=>{resolve=done;});return{promise,resolve};};
const toolCalls=adapter=>adapter.calls.filter(call=>call.method==='tools/call');

async function hostFixture(browser,AppBridge,{width=1100,capabilities={serverTools:{},openLinks:{},updateModelContext:{structuredContent:{}}}}={}){
    const adapter=shoppingAdapter();
    const client=new Client({name:'unmodified-shopping-host',version:'1'});
    await client.connect(new StreamableHTTPClientTransport(new URL('https://cart.example.test/api/mcp'),{fetch:adapter.fetch}));
    const {tools}=await client.listTools();
    const tool=tools.find(item=>item.name==='search_public_products');
    const resource=(await client.readResource({uri:tool._meta.ui.resourceUri})).contents[0];
    assert.deepEqual(resource._meta.ui.csp.connectDomains,[]);assert.deepEqual(resource._meta.ui.csp.frameDomains,[]);
    const policy="default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; img-src https://cart.example.test; connect-src 'none'; frame-src 'none'; form-action 'none'; base-uri 'none'";
    const html=resource.text.replace('<head>','<head><meta http-equiv="Content-Security-Policy" content="'+policy+'">');
    const products=await client.callTool({name:'search_public_products',arguments:selection});
    const server=createServer((req,res)=>{
        res.setHeader('Content-Type','text/html; charset=utf-8');
        if(req.url==='/sandbox'){
            const source=JSON.stringify(html).replace(/</g,'\\u003c');
            res.end(`<!doctype html><html><body style="margin:0"><script>
                const view=document.createElement('iframe');view.id='view';view.style='border:0;width:100%;height:2400px';view.sandbox='allow-scripts allow-same-origin';
                addEventListener('message',event=>{if(event.source===view.contentWindow)parent.postMessage(event.data,'*');else if(event.source===parent)view.contentWindow.postMessage(event.data,'*');});
                view.srcdoc=${source};document.body.append(view);
                <\/script></body></html>`);return;
        }
        res.end('<!doctype html><html><body style="margin:0"><iframe id="sandbox" title="Shopping sandbox" sandbox="allow-scripts allow-same-origin" style="border:0;width:100%;height:2400px"></iframe></body></html>');
    });
    server.listen(0,'0.0.0.0');await once(server,'listening');
    const page=await browser.newPage({viewport:{width,height:900}});
    const errors=[];page.on('pageerror',error=>errors.push(error.message));
    const imageRequests=[];
    await page.route('https://cart.example.test/assets/images/**',async route=>{
        const pathname=new URL(route.request().url()).pathname;
        const allowed=['varahi-amman-ring-front.webp','varahi-amman-ring-side-1.webp','varahi-amman-dollar-front.webp'];
        const file=pathname.split('/').at(-1);
        assert.ok(pathname==='/assets/images/products/'+file&&allowed.includes(file),'Only official fixture photos may load');
        imageRequests.push(pathname);
        await route.fulfill({contentType:'image/webp',path:path.join(__dirname,'../assets/images/products',file)});
    });
    const bridge=new AppBridge(null,{name:'official-shopping-host-fixture',version:'1'},capabilities,{hostContext:{theme:'light',displayMode:'inline'}});
    let receive;
    const ready=deferred();
    const links=[];
    const hostCalls=[];
    const contexts=[];
    const host={adapter,client,page,bridge,products,errors,imageRequests,links,hostCalls,contexts,denyLinks:false,denyContext:false,holdContext:null,holdNext:null,echo:false};
    bridge.oninitialized=()=>ready.resolve();
    bridge.onopenlink=async params=>{links.push(params.url);return host.denyLinks?{isError:true}:{};};
    bridge.onupdatemodelcontext=async params=>{
        assert.ok(capabilities.updateModelContext?.structuredContent,'Context sent without structured-context capability');
        assert.equal(params.content,undefined,'Cart capability must not enter text context');
        contexts.push(params.structuredContent);
        const hold=host.holdContext;host.holdContext=null;
        if(hold){hold.started.resolve();await hold.release.promise;}
        if(host.denyContext)throw Error('Fixture host declined context');
        return {};
    };
    bridge.oncalltool=async params=>{
        assert.ok(capabilities.serverTools,'View called tools without host capability');
        assert.ok(['create_cart','get_cart','set_cart_quantity','review_checkout','prepare_checkout_payment','get_checkout_status'].includes(params.name),'View selected an unrelated server tool');
        hostCalls.push({name:params.name,arguments:params.arguments});
        // The host forwards to the same server after its own visibility allowlist; server checks the capability.
        const hold=host.holdNext;host.holdNext=null;
        if(host.echo)await bridge.sendToolInput({arguments:params.arguments});
        const result=await client.callTool(params);
        if(hold){hold.started.resolve();await hold.release.promise;}
        if(host.echo)await bridge.sendToolResult(result);
        hold?.returned.resolve();
        return result;
    };
    const transport={start:async()=>{},close:async()=>{},
        send:message=>page.evaluate(message=>document.querySelector('#sandbox').contentWindow.postMessage(message,'*'),message),
        set onmessage(handler){receive=handler;},get onmessage(){return receive;}};
    await page.exposeFunction('hostMessage',message=>receive?.(message));
    await page.goto(`http://127.0.0.1:${server.address().port}/`);
    await page.evaluate(()=>addEventListener('message',event=>{
        if(event.source===document.querySelector('#sandbox').contentWindow)window.hostMessage(event.data);
    }));
    await bridge.connect(transport);
    await page.locator('#sandbox').evaluate((frame,url)=>{frame.src=url;},`http://localhost:${server.address().port}/sandbox`);
    await Promise.race([ready.promise,new Promise((_,reject)=>setTimeout(()=>reject(Error('Shopping UI handshake timed out')),5000))]);
    host.view=page.frameLocator('#sandbox').frameLocator('#view');
    host.send=async(result,args={})=>{await bridge.sendToolInput({arguments:args});await bridge.sendToolResult(result);};
    host.close=async(tornDown=false)=>{if(!tornDown)await bridge.teardownResource({});await bridge.close();await page.close();await client.close();await new Promise(resolve=>server.close(resolve));};
    await host.send(products,selection);
    await host.view.getByRole('heading',{name:'Sri Varahi Amman Ring',exact:true}).waitFor();
    return host;
}

// This is a real sandboxed browser and official AppBridge, not proof of a commercial host installation.
test('official MCP Apps shopping controls use real photos, isolated carts, absolute updates and reviewed checkout',{timeout:180000},async()=>{
    const {AppBridge}=await import(pathToFileURL(sdk.resolve('@modelcontextprotocol/ext-apps/app-bridge')).href);
    const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||undefined,args:['--no-sandbox']});
    try{
        for(const width of [1100,375]){
            const host=await hostFixture(browser,AppBridge,{width});
            const {view,page,client,adapter}=host;
            try{
                const cards=view.locator('.mcp-shop__products');
                const ringCard=cards.locator('article').filter({has:view.getByRole('heading',{name:'Sri Varahi Amman Ring',exact:true})});
                const pendantCard=cards.locator('article').filter({has:view.getByRole('heading',{name:'Varahi Amman Pendant',exact:true})});
                const cart=view.getByRole('region',{name:'Anonymous shopping cart'});
                assert.equal(host.hostCalls.length,0,'Rendering does not create a cart or call a tool');
                assert.equal(host.contexts.length,0,'Rendering does not rewrite model context');
                await ringCard.locator('img').first().evaluate(image=>image.decode());
                assert.ok(await ringCard.locator('img').first().evaluate(image=>image.naturalWidth>0));
                assert.equal(await ringCard.locator('img').first().getAttribute('src'),'https://cart.example.test/assets/images/products/varahi-amman-ring-front.webp');
                await ringCard.getByRole('button',{name:'Add to cart',exact:true}).focus();await page.keyboard.press('Enter');
                await cart.getByRole('heading',{name:'Sri Varahi Amman Ring',exact:true}).waitFor();
                assert.deepEqual(host.hostCalls.map(call=>call.name),['create_cart','set_cart_quantity']);
                const token=adapter.calls.find(call=>call.result?.structuredContent?.cartToken).result.structuredContent.cartToken;
                assert.equal(await cart.getByRole('spinbutton').inputValue(),'1');
                await view.getByText('Cart details were shared for the next chat turn. This does not save the view across reloads.',{exact:true}).waitFor();
                assert.ok(host.contexts.at(-1).currentCart.cartToken===token,'The next model turn receives the same anonymous cart access');
                assert.equal(host.contexts.at(-1).currentCart.revision,1);
                const beforeQuantity=host.hostCalls.length;
                await cart.getByRole('spinbutton').fill('2');
                assert.equal(host.hostCalls.length,beforeQuantity,'Editing an input does not submit automatically');
                await cart.getByRole('spinbutton').press('Enter');
                await view.getByText('Quantity updated. The total is an item-price estimate.',{exact:true}).waitFor();
                assert.equal(host.hostCalls.at(-1).arguments.quantity,2);
                assert.equal(host.hostCalls.at(-1).arguments.expectedRevision,1);
                assert.match(await cart.innerText(),/1,001\.50/);

                // Another ordinary HTTP client changes this cart. The view must not retry or overwrite silently.
                await client.callTool({name:'set_cart_quantity',arguments:{cartToken:token,slug:ring,quantity:4,expectedRevision:2}});
                await cart.getByRole('spinbutton').fill('3');
                const conflictCalls=host.hostCalls.length;
                await cart.getByRole('button',{name:'Update quantity',exact:true}).click();
                await view.getByText('The cart changed elsewhere. Refresh it and review the quantities before updating.',{exact:true}).waitFor();
                assert.equal(host.hostCalls.length,conflictCalls+1,'Conflict does not trigger an automatic quantity retry');
                assert.equal(await cart.getByRole('button',{name:'Update quantity',exact:true}).isDisabled(),true);
                await cart.getByRole('button',{name:'Refresh cart',exact:true}).click();
                await view.getByText('Cart refreshed. Review current quantities, prices and availability.',{exact:true}).waitFor();
                assert.equal(await cart.getByRole('spinbutton').inputValue(),'4');
                await cart.getByRole('spinbutton').fill('3');await cart.getByRole('button',{name:'Update quantity',exact:true}).click();
                await view.getByText('Quantity updated. The total is an item-price estimate.',{exact:true}).waitFor();

                // Optional host echoes are accepted without cancelling or applying the user action twice.
                host.echo=true;
                const beforeEcho=host.hostCalls.length;
                await ringCard.getByRole('button',{name:'Add to cart',exact:true}).evaluate(button=>{button.click();button.click();});
                await view.getByText('Added to this cart. Review the quantity and estimated total below.',{exact:true}).waitFor();
                assert.equal(host.hostCalls.length,beforeEcho+1,'Rapid activation has one absolute quantity call');
                assert.equal(await cart.getByRole('spinbutton').inputValue(),'4');host.echo=false;

                const currentCount=Object.keys(adapter.state.carts).length;
                const currentQuantity=await cart.getByRole('spinbutton').inputValue();
                await pendantCard.getByRole('button',{name:'Buy now',exact:true}).click();
                await view.getByText('A separate one-product checkout is ready here. Your current cart is unchanged. Review delivery and totals before payment.',{exact:true}).waitFor();
                assert.equal(Object.keys(adapter.state.carts).length,currentCount+1);
                assert.equal(await cart.getByRole('spinbutton').inputValue(),currentQuantity);
                await view.getByText('Cart details were shared for the next chat turn. This does not save the view across reloads.',{exact:true}).waitFor();
                assert.ok(host.contexts.at(-1).currentCart.cartToken===token);
                assert.ok(host.contexts.at(-1).separateBuyNowCart.cartToken!==token,'Buy now remains a separate context cart');
                await view.getByRole('region',{name:'Buy now checkout review',exact:true}).waitFor();
                assert.equal(host.links.length,0,'Buy now opens the inline form without navigation');
                await cart.getByRole('button',{name:'Checkout',exact:true}).click();
                const checkout=view.getByRole('region',{name:'Checkout review',exact:true});
                await checkout.waitFor();
                const beforeFields=host.hostCalls.length;
                const delivery={name:'Synthetic App Shopper',email:'app-fixture@example.test',phone:'9876543210',
                    address:'24 Synthetic App Street',city:'Chennai',pincode:'600001',state:'Tamil Nadu'};
                for(const[label,key]of [['Full name','name'],['Email','email'],['Phone','phone'],['Delivery address','address'],['City','city'],['PIN code','pincode']])await checkout.getByLabel(label,{exact:true}).fill(delivery[key]);
                await checkout.getByLabel('State or union territory',{exact:true}).selectOption(delivery.state);
                assert.equal(host.hostCalls.length,beforeFields,'Typing delivery fields does not execute tools');
                assert.equal(adapter.state.gatewayCalls.length,0,'No provider request before quote confirmation');
                host.echo=true;
                if(width===375)await checkout.getByLabel('PIN code',{exact:true}).press('Enter');
                else await checkout.getByRole('button',{name:'Review checkout',exact:true}).click();
                await checkout.getByRole('button',{name:/^Confirm .* and prepare payment$/}).waitFor();host.echo=false;
                assert.equal(host.hostCalls.at(-1).name,'review_checkout');
                assert.equal(host.hostCalls.length,beforeFields+1,'One explicit Review produces one tool call');
                assert.ok((await checkout.innerText()).includes(delivery.address),'The buyer can review their locally entered address');
                assert.equal(adapter.state.gatewayCalls.length,0);
                const quote=adapter.calls.filter(call=>call.params?.name==='review_checkout').at(-1).result.structuredContent;
                assert.equal(quote.totals.totalPaise,200300);
                for(const value of [delivery.name,delivery.email,delivery.address,delivery.phone])assert.ok(!JSON.stringify(quote).includes(value),'Public quote never echoes personal input');
                await checkout.getByRole('button',{name:/^Confirm .* and prepare payment$/}).click();
                await checkout.getByRole('button',{name:'Continue to payment',exact:true}).waitFor();
                const payment=adapter.calls.filter(call=>call.params?.name==='prepare_checkout_payment').at(-1).result.structuredContent;
                assert.equal(host.links.length,0,'Preparing payment does not navigate');
                assert.equal(adapter.state.gatewayCalls.filter(call=>call.operation==='create_link').length,1);
                assert.equal(Object.keys(adapter.state.orders).length,0,'A prepared payment is not an order');
                assert.equal(await checkout.getByLabel('Full name',{exact:true}).count(),0,'Raw delivery fields are removed after payment preparation');
                host.denyLinks=true;await checkout.getByRole('button',{name:'Continue to payment',exact:true}).click();
                await checkout.getByText('This host declined the payment link. Keep this checkout open and use the verified payment link from the tool response.',{exact:true}).waitFor();
                const preparedCalls=host.hostCalls.length;host.denyLinks=false;
                await checkout.getByRole('button',{name:'Continue to payment',exact:true}).click();
                assert.equal(host.hostCalls.length,preparedCalls,'Opening the prepared link cannot repeat provider creation');
                assert.match(host.links.at(-1),/^https:\/\/cart\.example\.test\/checkout\/payment\?ticket=pay_/);
                assert.ok(!host.links.at(-1).includes(token)&&!host.links.at(-1).includes(payment.paymentToken));
                await checkout.getByRole('button',{name:'Check payment status',exact:true}).click();
                await checkout.getByText('not created',{exact:true}).waitFor();
                adapter.advance(31);adapter.markPaid(payment.attemptId);adapter.configure({failPersist:true});
                await checkout.getByRole('button',{name:'Check payment status',exact:true}).click();
                await checkout.getByText('save pending',{exact:true}).waitFor();
                assert.equal(Object.keys(adapter.state.orders).length,0);
                adapter.advance(31);adapter.configure({failPersist:false});
                await checkout.getByRole('button',{name:'Check payment status',exact:true}).click();
                await checkout.getByText('saved',{exact:true}).waitFor();
                assert.equal(Object.keys(adapter.state.orders).length,1,'A verified payment produces one saved order');
                await view.getByText('Cart details were shared for the next chat turn. This does not save the view across reloads.',{exact:true}).waitFor();
                const context=host.contexts.at(-1).checkout;
                assert.equal(context.paymentToken,payment.paymentToken);assert.equal(context.paymentStatus,'captured');assert.equal(context.orderStatus,'saved');
                for(const value of [delivery.name,delivery.email,delivery.address,delivery.phone])assert.ok(!JSON.stringify(host.contexts).includes(value),'Personal fields cannot enter model context');
                for(const value of [token,payment.paymentToken])assert.ok(!await view.locator('body').evaluate((body,secret)=>body.innerHTML.includes(secret),value),'Capabilities never enter DOM');
                assert.equal(await view.locator('body').evaluate(body=>body.scrollWidth<=document.documentElement.clientWidth),true);
                assert.deepEqual(host.errors,[]);
                assert.ok(!adapter.operations.includes('claim_checkout'),'Inline checkout cannot import a browser session cart');
            }finally{await host.close();}
        }
    }finally{await browser.close();}
});

test('shopping UI gates host actions and resumes only the cart capability supplied for this result',{timeout:120000},async()=>{
    const {AppBridge}=await import(pathToFileURL(sdk.resolve('@modelcontextprotocol/ext-apps/app-bridge')).href);
    const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||undefined,args:['--no-sandbox']});
    try{
        for(const capabilities of [{openLinks:{}},{serverTools:{}},{serverTools:{},updateModelContext:{text:{}}}]){
            const host=await hostFixture(browser,AppBridge,{width:375,capabilities});
            try{
                const {view}=host;
                const add=view.getByRole('button',{name:'Add to cart',exact:true}).first();
                assert.equal(await add.isDisabled(),!capabilities.serverTools);
                assert.equal(await view.getByRole('button',{name:'Buy now',exact:true}).first().isDisabled(),!capabilities.serverTools);
                assert.equal(host.hostCalls.length,0);
                assert.equal(host.contexts.length,0);
                if(capabilities.serverTools){
                    await add.click();await view.getByText('Added to this cart. Review the quantity and estimated total below.',{exact:true}).waitFor();
                    assert.equal(await view.getByRole('button',{name:'Checkout',exact:true}).isEnabled(),true);
                    const before=host.hostCalls.length;await view.getByRole('button',{name:'Checkout',exact:true}).click();
                    await view.getByRole('region',{name:'Checkout review',exact:true}).waitFor();
                    assert.equal(host.hostCalls.length,before,'Opening the inline form is local');
                    assert.equal(host.links.length,0);
                    assert.equal(host.contexts.length,0,'Absent structured context capability leaves buttons working locally without exposing cart access in text');
                }
                assert.deepEqual(host.errors,[]);
            }finally{await host.close();}
        }
        const host=await hostFixture(browser,AppBridge);
        try{
            const {view,client,adapter}=host;
            const created=await client.callTool({name:'create_cart',arguments:{}});
            const token=created.structuredContent.cartToken;
            await client.callTool({name:'set_cart_quantity',arguments:{cartToken:token,slug:ring,quantity:1,expectedRevision:0}});
            const cart=await client.callTool({name:'get_cart',arguments:{cartToken:token}});
            const count=toolCalls(adapter).length;
            await host.send(cart,{});
            await view.getByText('This view can show the cart but cannot edit it. Ask the assistant to reopen this cart before editing.',{exact:true}).waitFor();
            assert.equal(await view.getByRole('button',{name:'Update quantity',exact:true}).isDisabled(),true);
            assert.equal(toolCalls(adapter).length,count,'A missing capability does not create or merge a replacement cart');
            await host.send(cart,{cartToken:token});
            await view.getByText('This view can show the cart but cannot edit it. Ask the assistant to reopen this cart before editing.',{exact:true}).waitFor({state:'hidden'});
            assert.equal(await view.getByRole('button',{name:'Update quantity',exact:true}).isEnabled(),true);
            assert.ok(!await view.locator('body').evaluate((body,value)=>body.innerHTML.includes(value),token));
            const corrupted=structuredClone(host.products);
            corrupted.structuredContent.products[0].imageUrl='https://untrusted.invalid/track.webp';
            await host.send(corrupted,selection);
            await view.locator('.mcp-shop').waitFor({state:'hidden'});
            assert.equal(await view.locator('.mcp-shop').count(),0,'A foreign photo URL cannot create shopping controls');
            const invalidCheckout={content:[{type:'text',text:'Review unavailable.'}],_meta:{'in.bapx/shoppingView':'payment'},structuredContent:{
                stage:'payment_ready',quoteId:'quote_'+'a'.repeat(32),attemptId:'b'.repeat(32),paymentToken:'payment_'+'b'.repeat(32)+'.'+'c'.repeat(64),
                statusExpiresAt:cart.structuredContent.expiresAt,provider:'razorpay',mode:'test',amountPaise:50075,currency:'INR',
                paymentUrl:'https://untrusted.invalid/checkout',paymentExpiresAt:cart.structuredContent.expiresAt,notice:'Payment unavailable.'
            }};
            await host.send(invalidCheckout,{cartToken:token,expectedRevision:cart.structuredContent.revision});
            await view.getByText('Review unavailable.',{exact:true}).waitFor();
            assert.equal(await view.locator('#mcp-cards a, #mcp-cards button').count(),0,'Foreign payment URL stays inert');
            await host.send(cart,{cartToken:token});
            await view.getByRole('button',{name:'Refresh cart',exact:true}).waitFor();
            adapter.hide(ring);
            await view.getByRole('button',{name:'Refresh cart',exact:true}).click();
            await view.getByRole('heading',{name:'Unavailable selection',exact:true}).waitFor();
            assert.equal(await view.getByRole('button',{name:'Checkout',exact:true}).isDisabled(),true);
            await view.getByRole('button',{name:'Remove unavailable selection',exact:true}).click();
            await view.getByText('Removed from this cart.',{exact:true}).waitFor();
            assert.match(await view.getByRole('region',{name:'Anonymous shopping cart'}).innerText(),/0 item\(s\)/);
            adapter.advance(86400);
            await view.getByRole('button',{name:'Refresh cart',exact:true}).click();
            await view.getByText('This cart expired. Start a new cart to continue.',{exact:true}).waitFor();
            assert.equal(await view.getByRole('button',{name:'Checkout',exact:true}).isDisabled(),true);
            assert.deepEqual(host.errors,[]);
        }finally{await host.close();}
        const declined=await hostFixture(browser,AppBridge);
        try{
            declined.denyContext=true;
            await declined.view.getByRole('button',{name:'Add to cart',exact:true}).first().click();
            await declined.view.getByText('Cart updated here. This host did not accept the chat context; follow-up chat may need the cart reopened.',{exact:true}).waitFor();
            assert.equal(await declined.view.getByRole('button',{name:'Update quantity',exact:true}).isEnabled(),true,'Context denial does not disable cart controls');
            assert.equal(declined.contexts.length,1);
            declined.denyContext=false;
            const hold={started:deferred(),release:deferred()};declined.holdContext=hold;
            await declined.view.getByRole('spinbutton').fill('2');
            await declined.view.getByRole('button',{name:'Update quantity',exact:true}).click();
            await hold.started.promise;
            await declined.view.getByRole('spinbutton').fill('3');
            hold.release.resolve();
            await declined.view.getByText('Cart details were shared for the next chat turn. This does not save the view across reloads.',{exact:true}).waitFor();
            assert.equal(await declined.view.getByRole('spinbutton').inputValue(),'3','A late context acknowledgment does not erase an unsent quantity edit');
            assert.equal(declined.contexts.at(-1).currentCart.items[0].quantity,2,'Only the confirmed server quantity is shared');
            assert.deepEqual(declined.errors,[]);
        }finally{await declined.close();}
    }finally{await browser.close();}
});

test('cancellation and teardown interrupt Buy now before later chained actions',{timeout:90000},async()=>{
    const {AppBridge}=await import(pathToFileURL(sdk.resolve('@modelcontextprotocol/ext-apps/app-bridge')).href);
    const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||undefined,args:['--no-sandbox']});
    try{
        for(const cancel of [true,false]){
            const host=await hostFixture(browser,AppBridge);
            let tornDown=false;
            let hold;
            try{
                hold={started:deferred(),release:deferred(),returned:deferred()};host.holdNext=hold;
                await host.view.getByRole('button',{name:'Buy now',exact:true}).first().click();
                await hold.started.promise;
                if(cancel){await host.bridge.sendToolCancelled({reason:'Fixture cancellation'});await host.view.getByText('This request was cancelled.',{exact:true}).waitFor();}
                else {await host.bridge.teardownResource({});tornDown=true;}
                hold.release.resolve();await hold.returned.promise;
                await host.page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
                assert.equal(host.hostCalls.length,1,'An interrupted create must not continue to set quantity or prepare checkout');
                assert.equal(host.links.length,0);
                assert.equal(host.contexts.length,0,'An interrupted action does not publish new context');
                if(cancel)assert.equal(await host.view.locator('#mcp-cards').textContent(),'');
                assert.deepEqual(host.errors,[]);
            }finally{
                hold?.release.resolve();
                await host.close(tornDown);
            }
        }
        const host=await hostFixture(browser,AppBridge);
        const contextHold={started:deferred(),release:deferred()};
        try{
            host.holdContext=contextHold;
            await host.view.getByRole('button',{name:'Add to cart',exact:true}).first().click();
            await contextHold.started.promise;
            await host.view.getByRole('spinbutton').fill('2');
            await host.view.getByRole('button',{name:'Update quantity',exact:true}).click();
            await host.view.getByText('Quantity updated. The total is an item-price estimate.',{exact:true}).waitFor();
            assert.equal(host.contexts.length,1,'Context updates are serialized while the previous acknowledgment is pending');
            await host.bridge.sendToolCancelled({reason:'Cancel context delivery'});
            await host.view.getByText('This request was cancelled.',{exact:true}).waitFor();
            contextHold.release.resolve();
            await host.page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
            assert.equal(host.contexts.length,1,'Cancellation discards queued newer context');
            assert.equal(await host.view.locator('#mcp-cards').textContent(),'','A late context acknowledgment cannot resurrect a cancelled view');
            assert.deepEqual(host.errors,[]);
        }finally{contextHold.release.resolve();await host.close();}
    }finally{await browser.close();}
});
