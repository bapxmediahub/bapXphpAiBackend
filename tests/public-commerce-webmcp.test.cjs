'use strict';
const assert=require('node:assert/strict');
const {readFileSync}=require('node:fs');
const {join}=require('node:path');
const {test}=require('node:test');
const vm=require('node:vm');
const {shoppingAdapter}=require('./fixtures/public-mcp-shopping.cjs');
const source=readFileSync(join(__dirname,'../assets/public-commerce-webmcp.js'),'utf8');
const origin='https://cart.example.test',protocol='2026-07-28';
const names=['create_cart','get_cart','set_cart_quantity','review_checkout','prepare_checkout_payment','get_checkout_status'];
const tick=()=>new Promise(resolve=>setImmediate(resolve));
let contracts;
async function definitions(){
    if(contracts)return contracts;
    const adapter=shoppingAdapter();
    const response=await adapter.fetch(origin+'/api/mcp',{method:'POST',headers:{'Content-Type':'application/json',
        'MCP-Protocol-Version':protocol,'Mcp-Method':'tools/list'},body:JSON.stringify({jsonrpc:'2.0',id:1,method:'tools/list',
            params:{_meta:{'io.modelcontextprotocol/protocolVersion':protocol,'io.modelcontextprotocol/clientCapabilities':{}}}})});
    contracts=(await response.json()).result.tools.filter(tool=>names.includes(tool.name));
    assert.equal(contracts.length,6,'Use the actual six PHP tool declarations');
    return contracts;
}
async function fixture(options={}){
    const adapter=shoppingAdapter(), registered=new Map(),requests=[],warnings=[],events={},timers=new Map();
    const config={publicOrigin:origin,protocolVersion:protocol,tools:await definitions(),...options.config};
    let attempts=0;
    vm.runInNewContext(source,{
        document:{getElementById:id=>id==='bapx-public-commerce-schema'?{textContent:JSON.stringify(config)}:null,
            modelContext:{registerTool(tool,registration){
                attempts++;
                if(attempts===options.failAt)throw Error('Synthetic registration refusal');
                registered.set(tool.name,{...tool,registration});
                registration.signal.addEventListener('abort',()=>registered.delete(tool.name),{once:true});
                return options.nativeVoid?undefined:Promise.resolve();
            }}},
        window:{location:{origin},addEventListener:(name,callback)=>{events[name]=callback;},
            BapxSupportCheckout:{acceptToolResult:options.render??(async()=>({rendered:true}))}},
        URL,AbortController,TextEncoder,
        setTimeout:(callback,delay)=>{const timer=setTimeout(()=>{timers.delete(timer);callback();},delay);timers.set(timer,delay);return timer;},
        clearTimeout:timer=>{timers.delete(timer);clearTimeout(timer);},console:{warn:message=>warnings.push(message)},
        fetch:async(path,init)=>{
            requests.push({path,init});
            if(options.fetch)return options.fetch(path,init,adapter);
            return adapter.fetch(new URL(path,origin),init);
        }
    });
    await tick();
    return {adapter,registered,requests,warnings,events,timers,call:(name,args={},extra={})=>registered.get(name).execute(args,extra)};
}
test('browser commerce uses canonical named schemas and rolls back partial registration',async()=>{
    for(const nativeVoid of [true,false]){
        const f=await fixture({nativeVoid});
        assert.deepEqual([...f.registered.keys()],names);
        for(const definition of await definitions()){
            const actual=f.registered.get(definition.name);
            assert.deepEqual(JSON.parse(JSON.stringify(actual.inputSchema)),definition.inputSchema);
            assert.deepEqual(JSON.parse(JSON.stringify(actual.outputSchema)),definition.outputSchema);
            assert.equal(actual.annotations.readOnlyHint,definition.annotations.readOnlyHint);
        }
        assert.equal(f.registered.get('prepare_checkout_payment').annotations.consequentialHint,true);
        f.events.pagehide({persisted:true});assert.equal(f.registered.size,6);
        f.events.pagehide({persisted:false});assert.equal(f.registered.size,0);
    }
    const rejected=await fixture({failAt:3});
    assert.equal(rejected.registered.size,0,'A rejected action removes the complete commerce group');
    assert.equal(rejected.warnings.length,1);
    assert.equal((await fixture({config:{publicOrigin:'https://foreign.example'}})).registered.size,0);
    assert.equal((await fixture({config:{tools:(await definitions()).slice(0,3)}})).registered.size,0);
    assert.deepEqual([...(await fixture({config:{tools:(await definitions()).filter(t=>t.name==='get_checkout_status')}})).registered.keys()],['get_checkout_status']);
});
test('real PHP cart results reach the visible UI before execution resolves, without owner credentials',async()=>{
    let release,shown=false;
    const f=await fixture({render:async(name,args,result,options)=>{
        assert.equal(name,'create_cart');assert.deepEqual(JSON.parse(JSON.stringify(args)),{});
        assert.equal(result._meta['in.bapx/shoppingView'],'cart');
        assert.equal(options.signal.aborted,false,'UI sync retains caller/lifecycle cancellation after fetch');
        await new Promise(resolve=>{release=resolve;});shown=true;
        return {rendered:true};
    }});
    let finished=false;
    const pending=f.call('create_cart').then(result=>{finished=true;return result;});
    await tick();assert.equal(finished,false);assert.equal(shown,false);
    assert.equal(f.timers.size,0,'The network timeout ends after a valid response, even while human UI work is pending');
    release();const result=await pending;
    assert.equal(shown,true);assert.match(result.cartToken,/^cart_[a-f0-9]{32}\.[a-f0-9]{64}$/);
    const {path,init}=f.requests[0];assert.equal(path,'/api/mcp');
    assert.equal(init.credentials,'omit');assert.equal(init.redirect,'error');assert.equal(init.referrerPolicy,'no-referrer');
    assert.equal(init.cache,'no-store');assert.equal(init.headers['Mcp-Name'],'create_cart');
    const body=JSON.parse(init.body);
    assert.equal(body.params._meta['io.modelcontextprotocol/protocolVersion'],protocol);
    assert.deepEqual(body.params._meta['io.modelcontextprotocol/clientCapabilities'],{});
});
test('schema validation blocks extra privileged fields and malformed confirmation before HTTP',async()=>{
    const f=await fixture();
    const token='cart_'+'a'.repeat(32)+'.'+'b'.repeat(64);
    for(const [name,args]of [['create_cart',{actor:'owner'}],['get_cart',{cartToken:'bad'}],
        ['set_cart_quantity',{cartToken:token,slug:'ring',quantity:1.5,expectedRevision:0}],
        ['prepare_checkout_payment',{cartToken:token,expectedRevision:0,quoteId:'quote_'+'c'.repeat(32),confirm:false}],
        ['review_checkout',{cartToken:token,expectedRevision:0,delivery:{name:'Missing address'}}]]){
        await assert.rejects(f.call(name,args),{name:'TypeError'});
    }
    assert.equal(f.requests.length,0);
});
test('caller cancellation reaches fetch and never retries a possibly applied action',async()=>{
    let entered;
    const started=new Promise(resolve=>{entered=resolve;});
    const f=await fixture({fetch:async(path,init)=>{entered(init.signal);return new Promise((resolve,reject)=>{
        init.signal.addEventListener('abort',()=>reject(Error('Synthetic abort')),{once:true});
    });}});
    const caller=new AbortController();
    const pending=f.call('create_cart',{}, {signal:caller.signal});
    const passed=await started;caller.abort();
    await assert.rejects(pending,/unconfirmed.*existing cart.*do not create a second payment/);
    assert.equal(passed.aborted,true);assert.equal(f.requests.length,1);
    const already=new AbortController();already.abort();
    await assert.rejects(f.call('create_cart',{}, {signal:already.signal}),/unconfirmed/);
    assert.equal(f.requests.length,1,'A pre-cancelled invocation makes no request');
});
test('server conflicts preserve a bounded error and never trigger an automatic write replay',async()=>{
    const f=await fixture();const cart=await f.call('create_cart');
    const args={cartToken:cart.cartToken,slug:'sri-varahi-amman-ring',quantity:1,expectedRevision:0};
    const changed=await f.call('set_cart_quantity',args);assert.equal(changed.revision,1);
    await assert.rejects(f.call('set_cart_quantity',{...args,quantity:2}),error=>error.name==='ShoppingActionError'&&error.code==='cart_conflict');
    assert.equal(f.adapter.operations.filter(op=>op==='set_quantity').length,2,'One success and one conflict, with no retry');
    const current=await f.call('get_cart',{cartToken:cart.cartToken});assert.equal(current.itemCount,1);
});
test('invalid response identity and undeclared private fields cannot reach the UI or tool output',async()=>{
    for(const corrupt of [body=>{body.id='different';},body=>{body.result.structuredContent.customer_email='PRIVATE_FIXTURE';}]){
        let rendered=false;
        const f=await fixture({render:async()=>{rendered=true;},fetch:async(path,init,adapter)=>{
            const response=await adapter.fetch(new URL(path,origin),init);const body=await response.json();corrupt(body);
            return new Response(JSON.stringify(body),{status:200});
        }});
        await assert.rejects(f.call('create_cart'),/unconfirmed/);
        assert.equal(rendered,false);assert.equal(f.requests.length,1);
    }
});
