'use strict';
const assert=require('node:assert/strict');
const {readFileSync}=require('node:fs');
const {join}=require('node:path');
const vm=require('node:vm');
const {test}=require('node:test');
const source=readFileSync(join(__dirname,'../assets/public-business-webmcp.js'),'utf8');
const contracts={
    contact:{inputSchema:{type:'object',properties:{},additionalProperties:false},outputSchema:{type:'object'}},
    services:{inputSchema:{type:'object',properties:{query:{type:'string'},offset:{type:'integer'},limit:{type:'integer'}}},outputSchema:{type:'object'}}
};
const contact={name:'Published Business',path:'/contact',phones:[{label:'Support',number:'+919789444037'}],
    email:'support@example.test',hours:[{days:'Mon–Sat',hours:'9 AM–7 PM'}],servicePath:'/contact#contact-form'};
const service={name:'Temple Guidance',description:'Ask about published temple guides.',path:'/contact?subject=temple#contact-form'};
function fixture(payload,{mode='promise',enabled=true,ok=true}={}) {
    const tools=[],requests=[],warnings=[];
    let pagehide, release;
    const registry=enabled?{registerTool(tool,options){
        if(mode==='throw'||(mode==='secondThrow'&&tools.length===1))throw new Error('Registration denied');
        if(mode==='secondReject'&&tools.length===1)return Promise.reject(new Error('Registration denied'));
        tools.push({tool,options});
        if(mode==='pending'&&tools.length===2)return new Promise(resolve=>{release=resolve;});
        return mode==='void'?undefined:Promise.resolve();
    }}:undefined;
    vm.runInNewContext(source,{
        document:{modelContext:registry,getElementById:id=>id==='bapx-public-business-schema'?{textContent:JSON.stringify(contracts)}:null},
        window:{location:{origin:'https://example.test'},addEventListener:(name,handler)=>{if(name==='pagehide')pagehide=handler}},
        URL,AbortController, AbortSignal,console:{warn:message=>warnings.push(message)},
        fetch:async(url,options)=>{requests.push({url:new URL(url),options});return {ok,json:async()=>payload};}
    });
    return {tools,requests,warnings,get pagehide(){return pagehide},get release(){return release},tool:name=>tools.find(x=>x.tool.name===name)?.tool};
}
const settle=()=>new Promise(resolve=>setImmediate(resolve));
async function readyFixture(payload,options){const f=fixture(payload,options);await settle();return f;}
test('business tools register without shop DOM, use canonical schemas and clean up on pagehide',async()=>{
    const f=await readyFixture(contact,{mode:'void'});
    assert.deepEqual(f.tools.map(x=>x.tool.name),['get_public_contact','list_public_services']);
    assert.deepEqual(JSON.parse(JSON.stringify(f.tools[0].tool.inputSchema)),contracts.contact.inputSchema);
    assert.ok(f.tools.every(x=>x.tool.annotations.readOnlyHint));
    f.pagehide({persisted:true});
    assert.ok(f.tools.every(x=>!x.options.signal.aborted));
    f.pagehide({persisted:false});
    assert.ok(f.tools.every(x=>x.options.signal.aborted));
    assert.equal(fixture(contact,{enabled:false}).tools.length,0);
});
test('a first registration refusal stops the batch and logs a single warning',async()=>{
    const f=await readyFixture(contact,{mode:'throw'});
    assert.equal(f.tools.length,0);
    assert.equal(f.warnings.length,1);
});
test('a refused sibling rolls back the registered business tool',async()=>{
    for(const mode of ['secondThrow','secondReject']){
        const f=await readyFixture(contact,{mode});
        assert.equal(f.tools.length,1);
        assert.equal(f.tools[0].options.signal.aborted,true);
        assert.equal(f.warnings.length,1);
        await assert.rejects(f.tool('get_public_contact').execute(),/unavailable/);
        assert.equal(f.requests.length,0);
    }
});
test('a partially registered group is not executable and teardown cannot reactivate it',async()=>{
    const f=fixture(contact,{mode:'pending'});
    await settle();
    assert.equal(f.tools.length,2);
    await assert.rejects(f.tool('get_public_contact').execute(),/unavailable/);
    assert.equal(f.requests.length,0);
    f.release();await settle();
    assert.deepEqual(await f.tool('get_public_contact').execute(),contact);
    const g=fixture(contact,{mode:'pending'});
    await settle();g.pagehide({persisted:false});g.release();await settle();
    await assert.rejects(g.tool('get_public_contact').execute(),/unavailable/);
    assert.equal(g.requests.length,0);
});
test('contact returns only published facts through credential-free GET and never submits the form',async()=>{
    const f=await readyFixture(contact);
    assert.deepEqual(await f.tool('get_public_contact').execute(),contact);
    assert.equal(f.requests[0].url.pathname,'/api/knowledge/contact');
    assert.equal(f.requests[0].options.method,'GET');
    assert.equal(f.requests[0].options.credentials,'omit');
    for(const input of [null,[],{email:'user@example.test'},{role:'owner'}]) {
        await assert.rejects(f.tool('get_public_contact').execute(input),{name:'TypeError'});
    }
    assert.equal(f.requests.length,1);
    for(const bad of [{...contact,adminEmail:'owner@example.test'}, {...contact,path:'/admin'},
        {...contact,phones:[{label:'Bad',number:'javascript:alert(1)'}]},
        {...contact,hours:[{days:'Mon',hours:'9–5',private:true}]}]) {
        await assert.rejects((await readyFixture(bad)).tool('get_public_contact').execute(),{name:'TypeError'});
    }
    await assert.rejects((await readyFixture(contact,{ok:false})).tool('get_public_contact').execute(),/unavailable/);
});
test('service search is paginated, bounded, and limited to real enquiry destinations',async()=>{
    const payload={services:[service],total:2,nextOffset:1};
    const f=await readyFixture(payload);
    assert.deepEqual(await f.tool('list_public_services').execute({query:'temple',offset:0,limit:1}),payload);
    assert.equal(f.requests[0].url.pathname,'/api/knowledge/services');
    assert.equal(f.requests[0].url.searchParams.get('q'),'temple');
    assert.equal(f.requests[0].url.searchParams.get('limit'),'1');
    assert.equal(f.requests[0].options.credentials,'omit');
    for(const input of [{limit:21},{offset:-1},{query:'x'.repeat(121)},{actor:'admin'}]) {
        await assert.rejects(f.tool('list_public_services').execute(input),{name:'TypeError'});
    }
    for(const path of ['/consult','https://outside.test','/admin','/contact?subject=temple&role=owner#contact-form']) {
        await assert.rejects((await readyFixture({...payload,services:[{...service,path}]})).tool('list_public_services').execute(),{name:'TypeError'});
    }
    await assert.rejects((await readyFixture({...payload,services:[{...service,description:'x'.repeat(281)}]})).tool('list_public_services').execute(),{name:'TypeError'});
    await assert.rejects((await readyFixture({...payload,total:5})).tool('list_public_services').execute(),{name:'TypeError'});
});

test('each business invocation can be cancelled without unregistering the complete group',async()=>{
    for(const [name,payload] of [['get_public_contact',contact],['list_public_services',{services:[service],total:1,nextOffset:null}]]){
        const f=await readyFixture(payload);
        const call=new AbortController();
        await f.tool(name).execute({}, {signal:call.signal});
        call.abort();
        assert.equal(f.requests[0].options.signal.aborted,true);
        assert.ok(f.tools.every(entry=>!entry.options.signal.aborted));
        await f.tool(name).execute({});
        assert.equal(f.requests[1].options.signal.aborted,false);
    }
});
