'use strict';
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');
const source = readFileSync(path.join(__dirname,'../assets/support-checkout.js'),'utf8');
const origin = 'https://cart.example.test';
const key = 'bapx-support-commerce:v1:' + origin;
const token = 'cart_' + 'a'.repeat(32) + '.' + 'b'.repeat(64);
const paymentToken = 'payment_' + 'c'.repeat(32) + '.' + 'd'.repeat(64);
const date = seconds => new Date(Date.now() + seconds * 1000).toISOString().replace(/\.\d{3}Z$/,'Z');
const snapshot = () => ({version:1,currentCart:{cartToken:token,revision:2,expiresAt:date(3600)},buyNowCart:null,
    checkout:{paymentToken,expiresAt:date(86400),kind:'current'}});

// DOM-independent adapter boundary checks. Actual shared form/layout behavior is
// covered by the browser fixture; this host records transport and recovery only.
function fixture({stored=null,denyStorage=false,response=null,config={},mounted=true,sync=null,restoreWork=null} = {}) {
    const nodes = new Map(), windowListeners = new Map(), storage = new Map();
    const requests = [], restores = [], opened = [], accepted = [];
    let options;
    if (stored !== null) storage.set(key,stored);
    const node = id => {
        if (!nodes.has(id)) nodes.set(id,{textContent:'',dataset:{},hidden:false,disabled:true,isConnected:true,
            listeners:new Map(),addEventListener(name,handler){this.listeners.set(name,handler);},
            setAttribute(){},querySelectorAll:()=>[],querySelector:()=>null,replaceChildren(){},focus(){},contains:()=>false});
        return nodes.get(id);
    };
    node('bapx-support-commerce-config').textContent = JSON.stringify({ publicOrigin:origin,protocolVersion:'2026-07-28',deliveryStates:['Tamil Nadu','Telangana'],...config });
    const empty = {version:1,currentCart:null,buyNowCart:null,checkout:null};
    const shopping = { open(){},checkout(){},busy:()=>false,productAction:()=>true,render:()=>true,
        async acceptToolResult(name,args,result,options){accepted.push({name,args:JSON.parse(JSON.stringify(args)),result:JSON.parse(JSON.stringify(result))});
            return sync ? sync(name,args,result,options) : true;},
        restore:async value=>{restores.push(JSON.parse(JSON.stringify(value)));if(restoreWork)await restoreWork;},
        cancel(){options.onRecoveryState(empty);},teardown(){options.onRecoveryState(empty);} };
    const context = { URL,AbortController,Date,JSON,Number,Set,Promise,
        setTimeout:()=>1,clearTimeout(){},MutationObserver:class {observe(){} disconnect(){}},
        sessionStorage:{getItem(name){if(denyStorage)throw Error('Blocked');return storage.get(name)||null;},
            setItem(name,value){if(denyStorage)throw Error('Blocked');storage.set(name,value);},
            removeItem(name){if(denyStorage)throw Error('Blocked');storage.delete(name);}},
        document:{getElementById:id=>!mounted&&id==='support-panel'?null:node(id),querySelector:()=>null},
        window:{location:{origin},BapxCheckout:{},BapxShopping:{create(value){options=value;return shopping;}},
            open(...args){opened.push(args);},addEventListener(name,callback){windowListeners.set(name,callback);}},
        fetch:async(url,init)=>{requests.push({url,init});const request=JSON.parse(init.body);
            return {ok:true,text:async()=>JSON.stringify(response ? response(request) : {jsonrpc:'2.0',id:request.id,result:{content:[],isError:true}})};}
    };
    vm.runInNewContext(source,context,{filename:'support-checkout.js'});
    return {get options(){return options;},get api(){return context.window.BapxSupportCheckout;},nodes,node,storage,requests,restores,opened,accepted,
        event(name,detail){windowListeners.get(name)?.(detail);},shopping};
}

test('native shopping sends exact modern framing without cookies and permits only its fixed public tool names', async()=>{
    const host=fixture();
    const result=await host.options.request('tools/call',{name:'get_cart',arguments:{cartToken:token}});
    assert.equal(result.isError,true,'Business tool errors must reach the shared visible error handler');
    const {url,init}=host.requests[0], body=JSON.parse(init.body);
    assert.equal(url,'/api/mcp');assert.equal(init.credentials,'omit');assert.equal(init.redirect,'error');
    assert.equal(init.referrerPolicy,'no-referrer');assert.equal(init.cache,'no-store');
    assert.equal(init.headers['Mcp-Method'],'tools/call');assert.equal(init.headers['Mcp-Name'],'get_cart');
    assert.equal(init.headers['MCP-Protocol-Version'],body.params._meta['io.modelcontextprotocol/protocolVersion']);
    assert.deepEqual(body.params.arguments,{cartToken:token});
    assert.deepEqual(body.params._meta['io.modelcontextprotocol/clientCapabilities'],{});
    for (const name of ['sales_summary','get_orders','shopping_cart','arbitrary_sql']) {
        await assert.rejects(host.options.request('tools/call',{name,arguments:{}}),/Unsupported/);
    }
    await assert.rejects(host.options.request('resources/read',{name:'get_cart',arguments:{}}),/Unsupported/);
    assert.equal(host.requests.length,1);
    const mismatched=fixture({response:()=>({jsonrpc:'2.0',id:'foreign',result:{content:[]}})});
    await assert.rejects(mismatched.options.request('tools/call',{name:'create_cart',arguments:{}}),/could not be confirmed/);
});

test('native recovery retains only bounded capability receipts and never accepts extra personal or price fields',async()=>{
    const saved=snapshot(), host=fixture({stored:JSON.stringify(saved)});
    await Promise.resolve();
    assert.deepEqual(host.restores,[saved]);
    host.options.onRecoveryState({...saved,address:'private fixture address'});
    assert.deepEqual(JSON.parse(host.storage.get(key)),saved,'Unexpected fields cannot broaden persisted recovery data');
    for (const invalid of [ {...saved,currentCart:{...saved.currentCart,price:99}},
        {...saved,checkout:{...saved.checkout,email:'private@example.test'}}, {...saved,currentCart:{...saved.currentCart,revision:'2'}},
        {...saved,checkout:{...saved.checkout,expiresAt:date(8*86400)}}, {...saved,currentCart:{...saved.currentCart,cartToken:'owner-secret'}} ]) {
        const rejected=fixture({stored:JSON.stringify(invalid)});
        assert.equal(rejected.restores.length,0);
        assert.equal(rejected.storage.has(key),false);
    }
    const expired=fixture({stored:JSON.stringify({...saved,currentCart:{...saved.currentCart,expiresAt:date(-1)},checkout:null})});
    assert.equal(expired.restores.length,0);assert.equal(expired.storage.has(key),false);
    assert.equal(fixture({stored:'x'.repeat(2049)}).restores.length,0);
});

test('storage refusal keeps the in-memory adapter usable, and unload/BFCache do not erase valid recovery',async()=>{
    const blocked=fixture({denyStorage:true});
    assert.ok(blocked.options);assert.equal(blocked.node('support-commerce-toggle').disabled,false);
    assert.doesNotThrow(()=>blocked.options.onRecoveryState(snapshot()));
    assert.match(blocked.node('support-commerce-recovery').textContent,/Keep this tab open/);
    const saved=snapshot(), host=fixture({stored:JSON.stringify(saved)});
    await Promise.resolve();
    host.event('pagehide',{persisted:true});
    assert.deepEqual(JSON.parse(host.storage.get(key)),saved,'Module cancel during pagehide must not clear saved handles');
    host.event('pageshow',{persisted:true});
    await new Promise(resolve=>setImmediate(resolve));
    assert.equal(host.restores.length,2,'BFCache restore refreshes server state again');
    host.node('support-commerce-reset').listeners.get('click')();
    assert.equal(host.storage.has(key),false,'Explicit reset clears native recovery');
    const normal=fixture({stored:JSON.stringify(saved)});
    normal.event('pagehide',{persisted:false});
    assert.deepEqual(JSON.parse(normal.storage.get(key)),saved,'Teardown cannot erase a pending status handle');
});

test('native external-link bridge allows the canonical merchant payment page and rejects providers or altered scopes',async()=>{
    const host=fixture();
    const payment=origin+'/checkout/payment?ticket=pay_'+'e'.repeat(32)+'.'+'f'.repeat(64);
    assert.equal(await host.options.openLink(payment),true);
    assert.deepEqual(host.opened,[[payment,'_blank','noopener,noreferrer']]);
    for (const url of ['https://rzp.io/i/example','https://untrusted.invalid/checkout',origin+'/admin',
        origin+'/shop?cartToken='+token,payment+'&extra=1',origin+'/product/%2e%2e']) {
        assert.equal(await host.options.openLink(url),false,url);
    }
    assert.equal(host.opened.length,1);
    assert.equal(fixture({config:{publicOrigin:'https://other.example.test'}}).options,undefined);
    assert.equal(fixture({config:{deliveryStates:['Tamil Nadu','Tamil Nadu']}}).options,undefined);
});

test('browser-tool result sync awaits the shared controller and strips unrelated input, text and App metadata',async()=>{
    let finish;
    const host=fixture({sync:()=>new Promise(resolve=>{finish=resolve;})});
    host.node('support-panel').hidden=true;
    const result={structuredContent:{revision:2,cartToken:token,lines:[]},
        _meta:{'in.bapx/shoppingView':'cart',privateOwner:{email:'PRIVATE_OWNER_FIXTURE'}},
        content:[{type:'text',text:'PRIVATE_TEXT_FIXTURE'}]};
    let resolved=false;
    const promise=host.api.acceptToolResult('set_cart_quantity',{cartToken:token,expectedRevision:1,
        slug:'test-ring',quantity:1,delivery:{name:'PRIVATE_DELIVERY_FIXTURE'},couponCode:'PRIVATE_COUPON_FIXTURE'},result)
        .then(value=>{resolved=true;return value;});
    await new Promise(resolve=>setImmediate(resolve));
    assert.equal(resolved,false,'The bridge must not finish before the shared canonical state render');
    assert.deepEqual(host.accepted[0].args,{cartToken:token,expectedRevision:1});
    assert.deepEqual(host.accepted[0].result,{structuredContent:result.structuredContent,_meta:{'in.bapx/shoppingView':'cart'}});
    assert.equal(host.node('support-panel').hidden,true);
    finish(true);
    assert.equal((await promise).rendered,true);
    assert.equal(host.node('support-panel').hidden,false);
    assert.equal(host.node('support-commerce').hidden,false);
    assert.equal(host.node('support-log').hidden,true);
    assert.doesNotMatch(JSON.stringify(host.accepted),/PRIVATE_/);
    assert.equal(fixture({mounted:false}).api,undefined,'Browser-only pages have no phantom native controller');
});

test('native result bridge rejects wrong tool families and mismatched cart, quote and payment identities',async()=>{
    const host=fixture();
    const foreign='cart_'+'e'.repeat(32)+'.'+'f'.repeat(64);
    const quoteId='quote_'+'1'.repeat(32);
    const payment={structuredContent:{quoteId,attemptId:'c'.repeat(32),paymentToken},_meta:{'in.bapx/shoppingView':'payment'}};
    const prepare={cartToken:token,expectedRevision:2,quoteId,confirm:true};
    const attempts=[
        ['sales_summary',{},payment],['search_public_products',{},payment],
        ['get_cart',{cartToken:token},payment],
        ['get_cart',{cartToken:token},{structuredContent:{cartToken:foreign},_meta:{'in.bapx/shoppingView':'cart'}}],
        ['create_cart',{}, {structuredContent:{cartToken:'owner-secret'},_meta:{'in.bapx/shoppingView':'cart'}}],
        ['review_checkout',{cartToken:token,expectedRevision:2},{structuredContent:{cart:{cartToken:foreign}},_meta:{'in.bapx/shoppingView':'review'}}],
        ['prepare_checkout_payment',{...prepare,confirm:false},payment],
        ['prepare_checkout_payment',prepare,{...payment,structuredContent:{...payment.structuredContent,attemptId:'e'.repeat(32)}}],
        ['prepare_checkout_payment',prepare,{...payment,structuredContent:{...payment.structuredContent,quoteId:'quote_'+'2'.repeat(32)}}],
        ['get_checkout_status',{paymentToken},{structuredContent:{paymentToken:'payment_'+'e'.repeat(32)+'.'+'f'.repeat(64)},_meta:{'in.bapx/shoppingView':'status'}}],
        ['get_checkout_status',{paymentToken},{structuredContent:{},_meta:{'in.bapx/shoppingView':'status'},isError:true}]
    ];
    for(const [name,args,result]of attempts)assert.equal((await host.api.acceptToolResult(name,args,result)).rendered,false,name);
    assert.equal(host.accepted.length,0,'Rejected responses cannot erase or replace shared recovery state');
    assert.equal((await host.api.acceptToolResult('prepare_checkout_payment',prepare,payment)).rendered,true);
});

test('native status-only mode permits existing status recovery while rejecting shopping, and unload drops delayed sync',async()=>{
    const host=fixture({config:{canShop:false}});
    assert.equal(host.options.canShop,false);
    assert.equal(host.node('support-commerce-toggle').textContent,'Checkout status');
    await host.options.request('tools/call',{name:'get_checkout_status',arguments:{paymentToken}});
    for(const name of ['search_public_products','create_cart','get_cart','set_cart_quantity','review_checkout','prepare_checkout_payment']){
        await assert.rejects(host.options.request('tools/call',{name,arguments:{cartToken:token}}),/Unsupported/);
        assert.equal((await host.api.acceptToolResult(name,{cartToken:token},{structuredContent:{cartToken:token},_meta:{'in.bapx/shoppingView':'cart'}})).rendered,false);
    }
    assert.equal((await host.api.acceptToolResult('get_checkout_status',{paymentToken},
        {structuredContent:{paymentStatus:'pending'},_meta:{'in.bapx/shoppingView':'status'}})).rendered,true);
    assert.equal(host.requests.length,1);
    assert.equal(fixture({config:{canShop:'false'}}).api,undefined);
    let finish;
    const unload=fixture({sync:()=>new Promise(resolve=>{finish=resolve;})});
    unload.node('support-panel').hidden=true;
    const promise=unload.api.acceptToolResult('get_cart',{cartToken:token},{structuredContent:{},_meta:{'in.bapx/shoppingView':'cart'}});
    await new Promise(resolve=>setImmediate(resolve));
    unload.event('pagehide',{persisted:false});finish(true);
    assert.equal((await promise).rendered,false);
    assert.equal(unload.node('support-panel').hidden,true,'A stale response cannot reopen a departed native surface');
});

test('caller cancellation discards only the queued native sync while preserving existing recovery work',async()=>{
    let finishRestore;
    const saved=snapshot(),host=fixture({stored:JSON.stringify(saved),restoreWork:new Promise(resolve=>{finishRestore=resolve;})});
    const signal=new AbortController();
    const result={structuredContent:{},_meta:{'in.bapx/shoppingView':'cart'}};
    const promise=host.api.acceptToolResult('get_cart',{cartToken:token},result,{signal:signal.signal});
    await Promise.resolve();signal.abort();
    assert.equal((await promise).rendered,false);
    assert.equal(host.accepted.length,0,'A canceled queue item cannot reach the shared renderer after recovery finishes');
    assert.deepEqual(JSON.parse(host.storage.get(key)),saved,'Canceling one external sync must not cancel the user’s existing recovery');
    finishRestore();await new Promise(resolve=>setImmediate(resolve));
    assert.equal(host.accepted.length,0);
    assert.equal((await host.api.acceptToolResult('get_cart',{cartToken:token},result)).rendered,true,'A later fresh result still works');
    let receivedSignal;
    const shared=fixture({sync:(_name,_args,_result,options)=>{
        receivedSignal=options.signal;
        return new Promise(resolve=>options.signal.addEventListener('abort',()=>resolve(false),{once:true}));
    }});
    const active=new AbortController();
    const waiting=shared.api.acceptToolResult('get_cart',{cartToken:token},result,{signal:active.signal});
    await new Promise(resolve=>setImmediate(resolve));
    assert.equal(receivedSignal,active.signal,'The same cancellation scope reaches the shared idle wait');
    active.abort();assert.equal((await waiting).rendered,false);
});
