'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const target=path.join(__dirname,'../.github/scripts/review-automation.cjs');
const sha='a'.repeat(40), repo='bapxmediahub/bapXphpAiBackend';
function load(){assert.ok(fs.existsSync(target),'Post-CI review planner must be implemented'); return require(target);}
function input(overrides={}) { return {
  repo, pr:{number:245,state:'open',draft:true,head:{sha,repo:{full_name:repo}},base:{repo:{full_name:repo}}},
  run:{name:'Validate application',conclusion:'success',event:'pull_request',head_sha:sha,head_repository:{full_name:repo}},
  actor:{login:'review-owner',id:123,type:'User'},hasRequestToken:true,comments:[],reviews:[],now:'2026-11-15T12:00:00Z',...overrides
}; }
test('successful same-head draft plans all three separate review commands',()=>{
 const p=load().planReviews(input());assert.equal(p.eligible,true);assert.deepEqual(p.requests.map(r=>r.command),['@codex review','@sourcery-ai review','@coderabbitai review']);
});
test('failed CI cannot request a review',()=>{const i=input();i.run.conclusion='failure';assert.equal(load().planReviews(i).reason,'ci_not_successful');});
test('stale CI cannot review a newer head',()=>{const i=input();i.run.head_sha='b'.repeat(40);assert.equal(load().planReviews(i).reason,'stale_ci');});
test('fork pull request cannot consume privileged reviewer credentials',()=>{const i=input();i.pr.head.repo.full_name='outside/fork';assert.equal(load().planReviews(i).reason,'foreign_repository');});
test('untrusted workflow or push run is not accepted as PR CI',()=>{const i=input();i.run.name='Fake CI';assert.equal(load().planReviews(i).reason,'untrusted_ci');i.run.name='Validate application';i.run.event='push';assert.equal(load().planReviews(i).reason,'untrusted_ci');});
test('missing dedicated token is explicit rather than silently using GITHUB_TOKEN',()=>{const p=load().planReviews(input({hasRequestToken:false}));assert.equal(p.reason,'missing_review_request_token');assert.equal(p.requests.length,0);});
test('app/bot token cannot request Sourcery comments',()=>{const p=load().planReviews(input({actor:{id:1,login:'github-actions[bot]',type:'Bot'}}));assert.equal(p.reason,'review_actor_not_human');});
test('closed PR never triggers',()=>{const i=input();i.pr.state='closed';assert.equal(load().planReviews(i).reason,'pr_not_open');});
test('Codex completed receipt must match current SHA',()=>{
 const c={user:{login:'chatgpt-codex-connector[bot]',type:'Bot'},body:'<!-- codex-review-state:v1 '+JSON.stringify({code_review:{commit_sha:sha,status:'completed',has_unresolved_findings:false}})+' -->'};
 let p=load().planReviews(input({comments:[c]}));assert.ok(!p.requests.some(r=>r.name==='codex'));assert.equal(p.reviewed[0].name,'codex');
 c.body=c.body.replace(sha,'b'.repeat(40));p=load().planReviews(input({comments:[c]}));assert.ok(p.requests.some(r=>r.name==='codex'));
});
test('current-head formal reviewer result avoids duplicate work even when it requests changes',()=>{
 const p=load().planReviews(input({reviews:[{user:{login:'coderabbitai[bot]'},commit_id:sha,state:'CHANGES_REQUESTED'}]}));
 assert.ok(!p.requests.some(r=>r.name==='coderabbit'));assert.equal(p.reviewed[0].state,'CHANGES_REQUESTED');
});
test('recent quota message blocks only affected reviewer and is not a completed review',()=>{
 const p=load().planReviews(input({comments:[{user:{login:'chatgpt-codex-connector[bot]'},created_at:'2026-11-15T11:23:56Z',body:'You have reached your Codex usage limits for code reviews.'}]}));
 assert.ok(!p.requests.some(r=>r.name==='codex'));assert.equal(p.blocked[0].reason,'quota_or_entitlement');assert.equal(p.reviewed.length,0);assert.equal(p.requests.length,2);
});
test('same-head request marker prevents duplicate dispatch without claiming completed reviews',()=>{
 const {marker,planReviews}=load();const c={user:{id:123,login:'review-owner'},body:marker(245,sha)};
 const p=planReviews(input({comments:[c]}));assert.equal(p.reason,'already_dispatched');assert.equal(p.requests.length,0);assert.equal(p.reviewed.length,0);
});
test('other users cannot forge the dispatcher idempotency marker',()=>{
 const {marker,planReviews}=load();const c={user:{id:999,login:'attacker'},body:marker(245,sha)};
 assert.equal(planReviews(input({comments:[c]})).requests.length,3);
});
test('fake Codex receipt in a human comment is not trusted',()=>{
 const body='<!-- codex-review-state:v1 '+JSON.stringify({code_review:{commit_sha:sha,status:'completed'}})+' -->';
 assert.equal(load().planReviews(input({comments:[{user:{login:'someone'},body}]})).requests.length,3);
});
test('pending real vendor check suppresses another request; stale or spoofed checks do not',()=>{
 const checks=[{name:'CodeRabbit',head_sha:sha,status:'in_progress',app:{slug:'coderabbitai'}}];
 let p=load().planReviews(input({checks}));assert.ok(!p.requests.some(r=>r.name==='coderabbit'));assert.equal(p.requested[0].state,'provider_review_in_progress');
 checks[0].head_sha='b'.repeat(40);assert.equal(load().planReviews(input({checks})).requests.length,3);
 checks[0].head_sha=sha;checks[0].app.slug='github-actions';assert.equal(load().planReviews(input({checks})).requests.length,3);
});
test('code feedback mentioning rate limiting is not provider quota exhaustion',()=>{
 const c={user:{login:'coderabbitai[bot]',type:'Bot'},created_at:'2026-11-15T11:23:56Z',body:'The endpoint needs a rate limit for repeated support submissions.'};
 assert.equal(load().planReviews(input({comments:[c]})).requests.length,3);
});
test('quota on another PR suppresses repeated requests within the bounded cooldown',()=>{
 const c={user:{login:'chatgpt-codex-connector[bot]',type:'Bot'},created_at:'2026-11-15T11:23:56Z',body:'You have reached your Codex usage limits for code reviews.'};
 assert.equal(load().planReviews(input({quotaComments:[c]})).blocked[0].name,'codex');
 c.created_at='2026-11-10T11:23:56Z';assert.equal(load().planReviews(input({quotaComments:[c]})).requests.length,3);
});
test('known native in-progress Codex metadata is not a completed review or duplicate trigger',()=>{
 const c={user:{login:'chatgpt-codex-connector[bot]',type:'Bot'},body:'<!-- codex-review-state:v1 '+JSON.stringify({code_review:{commit_sha:sha,status:'in_progress'}})+' -->'};
 const p=load().planReviews(input({comments:[c]}));assert.equal(p.reviewed.length,0);assert.ok(!p.requests.some(r=>r.name==='codex'));
});
test('partial dispatch is durable per provider and does not resend an earlier request',()=>{
 const i=input();i.comments=[{user:i.actor,body:'@codex review\n\n'+load().marker(245,sha,'codex')}];
 const p=load().planReviews(i);assert.equal(p.requests.length,2);assert.equal(p.requested[0].name,'codex');
});
test('head changes stop dispatch before any untested-head request',async()=>{
 const plan=load().planReviews(input());let posted=0;
 const result=await load().dispatch(plan,{repo,number:245,sha,api:async()=>({state:'open',head:{sha:'b'.repeat(40),repo:{full_name:repo}}}),requestApi:async()=>{posted++;return{id:1};}});
 assert.equal(posted,0);assert.equal(result.stopped,'head_changed_before_request');
});
test('dispatch sends distinct literal commands without executable PR content',async()=>{
 const bodies=[];const i=input();i.pr.title='malicious $(touch /tmp/bad)';
 const p=load().planReviews(i);
 await load().dispatch(p,{repo,number:245,sha,api:async()=>i.pr,requestApi:async(path,method,body)=>{assert.ok(['POST','PATCH'].includes(method));bodies.push(body.body);return{id:bodies.length};}});
 const commands=bodies.filter(b=>b.startsWith('@'));
 assert.deepEqual(commands,['@codex review','@sourcery-ai review','@coderabbitai review']);
 assert.ok(bodies.every(b=>!b.includes('malicious')));
 assert.equal(bodies.filter(b=>b.includes('Preparing ')).length,3);
});
test('HTTP errors never disclose token or upstream body',async()=>{
 const api=load().createApi('TOP_SECRET',async()=>({ok:false,status:403,text:async()=> 'TOP_SECRET failed'}));
 await assert.rejects(api('/user'),error=>error.message==='GitHub metadata request failed: HTTP 403');
});
test('status report must not equate dispatch to approval',()=>{
 const p=load().planReviews(input({hasRequestToken:false}));const text=load().markdown(245,sha,p);
 assert.ok(text.includes('not merge approval'));assert.ok(text.includes('missing_review_request_token'));
});

test('quota discovered on another PR suppresses blind repo-wide retries',()=>{
 const p=load().planReviews(input({quotaComments:[{user:{login:'chatgpt-codex-connector[bot]'},created_at:'2026-11-15T11:23:56Z',body:'You have reached your Codex usage limits for code reviews.'}]}));
 assert.ok(!p.requests.some(r=>r.name==='codex'));assert.equal(p.blocked[0].name,'codex');
});
test('dispatch claim is separate from Sourcery literal command and survives interruption',async()=>{
 const i=input(),calls=[];let count=0;
 await assert.rejects(load().dispatch(load().planReviews(i),{repo,number:245,sha,api:async()=>i.pr,
   requestApi:async(path,method,body)=>{calls.push({path,method,body});if(++count===2)throw Error('simulated connection error');return{id:1};}
 }),/simulated/);
 assert.equal(calls.length,2);assert.ok(calls[0].body.body.includes(load().marker(245,sha,'codex')));
 assert.equal(calls[1].body.body,'@codex review');
 const p=load().planReviews(input({comments:[{user:i.actor,body:calls[0].body.body}]}));
 assert.ok(!p.requests.some(r=>r.name==='codex'));assert.equal(p.requests.length,2);
});
test('workflow invokes the tested trusted CLI runner, not the incompatible dispatch wrapper',()=>{
 const w=fs.readFileSync(path.join(__dirname,'../.github/workflows/ai-review-evidence.yml'),'utf8');
 assert.ok(w.includes('node .github/scripts/review-automation.cjs'));
 assert.ok(w.includes('checks: read'));assert.ok(!w.includes('await dispatch({'));
});
test('workflow checks out only trusted default branch and never pull_request_target code',()=>{
 const source=fs.readFileSync(path.join(__dirname,'../.github/workflows/ai-review-evidence.yml'),'utf8');
 assert.ok(source.includes('github.event.repository.default_branch'));assert.ok(source.includes('persist-credentials: false'));
 assert.ok(!source.includes('pull_request_target:'));assert.ok(!source.includes('pull_request.head.sha'));assert.ok(!source.includes('workflow_run.head_sha }}'));
});
test('workflow never checks out PR head with privileged secrets',()=>{
 const text=fs.readFileSync(path.join(__dirname,'../.github/workflows/ai-review-evidence.yml'),'utf8');
 assert.ok(text.includes('github.event.repository.default_branch'));
 assert.ok(text.includes('persist-credentials: false'));
 assert.ok(!text.includes('pull_request_target'));
 assert.ok(!text.includes('ref: ${{ github.event.workflow_run.head_sha }}'));
 assert.ok(text.includes('secrets.REVIEW_REQUEST_TOKEN'));
 assert.ok(!text.includes('FORK_SYNC_TOKEN'));
});
test('CodeRabbit drafts are explicitly included without auto-approving PRs',()=>{
 const text=fs.readFileSync(path.join(__dirname,'../.coderabbit.yaml'),'utf8');
 assert.match(text,/drafts:\s*true/);assert.match(text,/auto_incremental_review:\s*true/);
 assert.match(text,/request_changes_workflow:\s*false/);
});
test('branch and CI configuration have one canonical reviewer-test invocation',()=>{
 const branch=fs.readFileSync(path.join(__dirname,'../.github/workflows/branch-pr.yml'),'utf8');
 const ci=fs.readFileSync(path.join(__dirname,'../.github/workflows/ci.yml'),'utf8');
 assert.equal((branch.match(/- 'bapX\/\*\*'/g)||[]).length,1);
 assert.equal((ci.match(/node --test tests\/review-automation\.test\.cjs/g)||[]).length,1);
 assert.ok(ci.indexOf('actions/setup-node@v4')<ci.indexOf('node --test tests/review-automation.test.cjs'));
});
test('new pull requests are explicitly ready for review rather than drafts',()=>{
 const workflow=fs.readFileSync(path.join(__dirname,'../.github/workflows/branch-pr.yml'),'utf8');
 const create=workflow.slice(workflow.indexOf('github.rest.pulls.create('));
 assert.match(create,/draft:\s*false/);
 assert.ok(!/draft:\s*true/.test(create));
});
