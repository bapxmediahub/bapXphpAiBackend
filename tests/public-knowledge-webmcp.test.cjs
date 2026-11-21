const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');

const source = readFileSync(join(__dirname, '../assets/public-knowledge-webmcp.js'), 'utf8');
const contract = {
    inputSchema: {
        type:'object', additionalProperties:false, properties:{
            query:{type:'string',maxLength:120},
            offset:{type:'integer',minimum:0,maximum:2147483647},
            limit:{type:'integer',minimum:1,maximum:20},
        }
    },
    outputSchema: {
        type:'object', required:['articles','total','nextOffset'],
        properties:{articles:{type:'array'},total:{type:'integer'},nextOffset:{type:['integer','null']}}
    },
};

function fixture(fetchData, modelContext = true) {
    let registered, event;
    const calls = [];
    const registry = modelContext ? {registerTool(tool, opts) {
        registered = {tool, opts};
        return Promise.resolve();
    }} : undefined;
    const context = {
        document:{
            modelContext:registry,
            getElementById:(id)=>id==='bapx-public-knowledge-schema'
                ? {textContent:JSON.stringify(contract)} : null
        },
        window:{
            location:{origin:'https://example.test'},
            addEventListener:(name, handler)=>{if(name==='pagehide')event=handler;}
        },
        URL, AbortController,
        fetch:async (url, opts)=>{
            calls.push({url:String(url),opts});
            return {ok:true,json:async()=>fetchData};
        },
        console:{warn(){}}
    };
    vm.runInNewContext(source, context);
    return {get registration(){return registered},get event(){return event},calls};
}

test('public article browser registration uses server-derived schemas and abort lifecycle', () => {
    const x=fixture({articles:[],total:0,nextOffset:null});
    assert.equal(x.registration.tool.name,'search_public_articles');
    assert.deepEqual(JSON.parse(JSON.stringify(x.registration.tool.inputSchema)),contract.inputSchema);
    assert.deepEqual(JSON.parse(JSON.stringify(x.registration.tool.outputSchema)),contract.outputSchema);
    assert.equal(x.registration.tool.annotations.readOnlyHint,true);
    x.event({persisted:true});
    assert.equal(x.registration.opts.signal.aborted,false);
    x.event({persisted:false});
    assert.equal(x.registration.opts.signal.aborted,true);
    assert.equal(fixture({},false).registration,undefined);
});

test('browser execution returns bounded public articles using unauthenticated GET', async()=>{
    const data={articles:[{title:'Public guide',path:'/blog/public-guide',category:'help',score:2}],total:1,nextOffset:null};
    const x=fixture(data);
    const result=await x.registration.tool.execute({query:'guide',offset:0,limit:2});
    assert.deepEqual(JSON.parse(JSON.stringify(result)),data);
    assert.equal(x.calls.length,1);
    assert.equal(new URL(x.calls[0].url).pathname,'/api/knowledge/articles');
    assert.equal(new URL(x.calls[0].url).searchParams.get('q'),'guide');
    assert.equal(x.calls[0].opts.credentials,'omit');
    assert.equal(x.calls[0].opts.method,'GET');
});

test('browser refuses invalid input and a forged private or external article URL', async()=>{
    const x=fixture({articles:[{title:'Secret',path:'/admin/docs/settings',category:'private',score:1}],total:1,nextOffset:null});
    await assert.rejects(x.registration.tool.execute({offset:-1}),TypeError);
    await assert.rejects(x.registration.tool.execute({role:'admin'}),TypeError);
    await assert.rejects(x.registration.tool.execute({limit:21}),TypeError);
    await assert.rejects(x.registration.tool.execute({query:'secret'}),TypeError);
    const external=fixture({articles:[{title:'Bad',path:'https://outside.test',category:'help',score:1}],total:1,nextOffset:null});
    await assert.rejects(external.registration.tool.execute({query:'test'}),TypeError);
});
