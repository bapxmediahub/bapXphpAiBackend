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
    let pagehide;
    const registry=enabled?{registerTool(tool,options){
        if(mode==='throw')throw new Error('Registration denied');
        tools.push({tool,options});
        return mode==='void'?undefined:Promise.resolve();
    }}:undefined;
    vm.runInNewContext(source,{
        document:{modelContext:registry,getElementById:id=>id==='bapx-public-business-schema'?{textContent:JSON.stringify(contracts)}:null},
        window:{location:{origin:'https://example.test'},addEventListener:(name,handler)=>{if(name==='pagehide')pagehide=handler}},
        URL,AbortController,console:{warn:message=>warnings.push(message)},
        fetch:async(url,options)=>{requests.push({url:new URL(url),options});return {ok,json:async()=>payload};}
    });
    return {tools,requests,warnings,get pagehide(){return pagehide},tool:name=>tools.find(x=>x.tool.name===name)?.tool};
}
test('business tools register without shop DOM, use canonical schemas and clean up on pagehide',()=>{
    const f=fixture(contact,{mode:'void'});
    assert.deepEqual(f.tools.map(x=>x.tool.name),['get_public_contact','list_public_services']);
    assert.deepEqual(JSON.parse(JSON.stringify(f.tools[0].tool.inputSchema)),contracts.contact.inputSchema);
    assert.ok(f.tools.every(x=>x.tool.annotations.readOnlyHint));
    f.pagehide({persisted:true});
    assert.ok(f.tools.every(x=>!x.options.signal.aborted));
    f.pagehide({persisted:false});
    assert.ok(f.tools.every(x=>x.options.signal.aborted));
    assert.equal(fixture(contact,{enabled:false}).tools.length,0);
});
test('a native registration refusal is caught separately for each business tool',async()=>{
    const f=fixture(contact,{mode:'throw'});
    await new Promise(resolve=>setImmediate(resolve));
    assert.equal(f.tools.length,0);
    assert.equal(f.warnings.length,2);
});
test('contact returns only published facts through credential-free GET and never submits the form',async()=>{
    const f=fixture(contact);
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
        await assert.rejects(fixture(bad).tool('get_public_contact').execute(),{name:'TypeError'});
    }
    await assert.rejects(fixture(contact,{ok:false}).tool('get_public_contact').execute(),/unavailable/);
});
test('service search is paginated, bounded, and limited to real enquiry destinations',async()=>{
    const payload={services:[service],total:2,nextOffset:1};
    const f=fixture(payload);
    assert.deepEqual(await f.tool('list_public_services').execute({query:'temple',offset:0,limit:1}),payload);
    assert.equal(f.requests[0].url.pathname,'/api/knowledge/services');
    assert.equal(f.requests[0].url.searchParams.get('q'),'temple');
    assert.equal(f.requests[0].url.searchParams.get('limit'),'1');
    assert.equal(f.requests[0].options.credentials,'omit');
    for(const input of [{limit:21},{offset:-1},{query:'x'.repeat(121)},{actor:'admin'}]) {
        await assert.rejects(f.tool('list_public_services').execute(input),{name:'TypeError'});
    }
    for(const path of ['/consult','https://outside.test','/admin','/contact?subject=temple&role=owner#contact-form']) {
        await assert.rejects(fixture({...payload,services:[{...service,path}]}).tool('list_public_services').execute(),{name:'TypeError'});
    }
    await assert.rejects(fixture({...payload,services:[{...service,description:'x'.repeat(281)}]}).tool('list_public_services').execute(),{name:'TypeError'});
    await assert.rejects(fixture({...payload,total:5}).tool('list_public_services').execute(),{name:'TypeError'});
});
