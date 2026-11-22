'use strict';
const assert=require('node:assert/strict');
const {spawn,execFileSync}=require('node:child_process');
const {once}=require('node:events');
const net=require('node:net');
const path=require('node:path');
const {test}=require('node:test');

test('live SHA endpoint checks actual current checkout, mismatch and invalid input without DB', {timeout:40000}, async()=>{
    const root=path.resolve(__dirname,'..');
    const current=execFileSync('git',['-C',root,'rev-parse','HEAD'],{encoding:'utf8'}).trim();
    assert.match(current,/^[0-9a-f]{40}$/);
    const socket=net.createServer();
    socket.listen(0,'127.0.0.1');await once(socket,'listening');
    const port=socket.address().port;
    await new Promise(resolve=>socket.close(resolve));
    const origin='http://127.0.0.1:'+port;
    const proc=spawn('php',['-S','127.0.0.1:'+port,'index.php'],{
        cwd:root,env:{...process.env,BAPX_TEST_MODE:'1',APP_URL:origin},stdio:'ignore'
    });
    const request=async (url)=>{
        const response=await fetch(origin+url);
        const json=await response.json();
        return {status:response.status,json,headers:response.headers};
    };
    try{
        let ready=false;
        for(let attempt=0;attempt<40;attempt++){
            try{const r=await request('/is-sha-valid?sha='+current);if(r.status===200){ready=true;break;}}
            catch{}
            await new Promise(resolve=>setTimeout(resolve,100));
        }
        assert.equal(ready,true,'PHP router must start and recognize live checkout SHA');
        for(const route of ['/is-sha-valid','/api/is-sha-valid']){
            const good=await request(route+'?sha='+current);
            assert.equal(good.status,200);
            assert.equal(good.json.status,'match');
            assert.equal(good.json.match,true);
            assert.equal(good.json.deployed_sha,current);
            assert.equal(good.json.source,'git_head');
            assert.match(good.headers.get('cache-control'),/no-store/);
            assert.match(good.headers.get('x-robots-tag'),/noindex/);
            const other=current==='a'.repeat(40)?'b'.repeat(40):'a'.repeat(40);
            const stale=await request(route+'?sha='+other);
            assert.equal(stale.status,200);
            assert.equal(stale.json.match,false);
            assert.equal(stale.json.status,'mismatch');
            assert.equal(stale.json.deployed_sha,current);
            for(const invalid of ['?sha=foo','?sha='+current.toUpperCase(),'?sha='+current+'&filename=.env','']){
                const x=await request(route+invalid);
                assert.equal(x.status,400);
                assert.equal(x.json.status,'invalid');
                assert.equal(JSON.stringify(x.json).includes('storage/'),false);
            }
        }
        console.log('Live SHA endpoint matches checkout HEAD, rejects stale/invalid SHA, exposes no private paths and disables caching.');
    }finally{
        proc.kill();
    }
});