'use strict';
const {execFileSync}=require('node:child_process');
const path=require('node:path');

// In-memory test state only. The real PHP service and dispatcher handle each official SDK request.
function shoppingAdapter(){
    let state=null;
    const calls=[];
    const operations=[];
    const controls=[];
    const failing=new Set();
    const fetch=async(input,init)=>{
        const request=new Request(input,init);
        if(request.method!=='POST')return new Response('',{status:405});
        const body=await request.text();
        const parsed=JSON.parse(body);
        if(parsed.method==='tools/call'&&failing.has(parsed.params?.name))return new Response(JSON.stringify({jsonrpc:'2.0',id:parsed.id,
            error:{code:-32603,message:'Synthetic temporary service outage.'}}),{status:503,headers:{'Content-Type':'application/json'}});
        const pending=controls.splice(0);
        const output=JSON.parse(execFileSync('php',['tests/public-mcp-shopping-request.php'],{
            cwd:path.join(__dirname,'../..'),encoding:'utf8',maxBuffer:4*1024*1024,
            input:JSON.stringify({body,headers:Object.fromEntries(request.headers),state,controls:pending})
        }));
        state=output.state;operations.push(...output.operations);
        calls.push({method:parsed.method,params:parsed.params,result:output.reply.body?.result});
        return new Response(output.reply.body===null?null:JSON.stringify(output.reply.body),{
            status:output.reply.status,headers:{'Content-Type':'application/json'}
        });
    };
    return {fetch,calls,operations,get state(){return state;},advance(seconds){state.now+=seconds;},hide(slug){state.hidden.push(slug);},
        markPaid(attemptId,overrides={}){controls.push({action:'markPaid',attemptId,overrides});},
        failFor(name,enabled=true){if(enabled)failing.add(name);else failing.delete(name);},
        configure(flags){for(const[key,value]of Object.entries(flags)){
            if(!['gatewayOffline','timeoutAfterCreate','failPersist','saveThenFailOnce'].includes(key)||typeof value!=='boolean')throw Error('Unsupported fixture flag');
            state[key]=value;
        }}
    };
}
module.exports={shoppingAdapter};
