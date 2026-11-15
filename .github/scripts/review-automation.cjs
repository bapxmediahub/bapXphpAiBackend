'use strict';
/**
 * Metadata-only review coordination. CI success, request delivery, and a review
 * receipt are intentionally distinct. Never executes or checks out PR head code.
 * Sources: docs/engineering/ai-review-workflow.md.
 */
const fs = require('node:fs');
const REPOSITORY = 'bapxmediahub/bapXphpAiBackend';
const SHA = /^[a-f0-9]{40}$/;
const PROVIDERS = Object.freeze([
  {name:'codex', command:'@codex review', logins:['chatgpt-codex-connector[bot]']},
  {name:'sourcery', command:'@sourcery-ai review', logins:['sourcery-ai[bot]','sourcery-ai']},
  {name:'coderabbit', command:'@coderabbitai review', logins:['coderabbitai[bot]','coderabbitai']},
]);
function marker(number, sha, provider = '') {
  if (!Number.isSafeInteger(number) || number < 1 || !SHA.test(sha)) throw new Error('Invalid review identity');
  if (provider && !PROVIDERS.some(p=>p.name===provider)) throw new Error('Invalid reviewer');
  return provider
    ? `<!-- sps-review-request:v1:${number}:${sha}:${provider} -->`
    : `<!-- sps-review-dispatch:v1:${number}:${sha} -->`;
}
function vendorComment(comment, provider) {
  return provider.logins.includes(comment?.user?.login)
    && (comment.user.type === 'Bot' || comment.user.login.endsWith('[bot]'));
}
function receipt(provider, sha, comments, reviews) {
  // A formal review is bound by GitHub to the commit; COMMENTED is not approval.
  const formal = reviews.filter(r=>provider.logins.includes(r.user?.login)
    && r.commit_id===sha && ['APPROVED','COMMENTED','CHANGES_REQUESTED'].includes(r.state))
    .sort((a,b)=>String(b.submitted_at||'').localeCompare(String(a.submitted_at||'')))[0];
  if (formal) return {name:provider.name, state:formal.state, evidence:'github_review', id:formal.id||null};
  if (provider.name === 'codex') {
    for (const comment of [...comments].reverse()) {
      if (!vendorComment(comment,provider)) continue;
      const match = String(comment.body||'').match(/<!--\s*codex-review-state:v1\s+(\{[^]*?\})\s*-->/);
      if (!match) continue;
      try {
        const review=JSON.parse(match[1]).code_review;
        if (review?.commit_sha!==sha || review.status!=='completed') continue;
        return {name:provider.name,state:review.has_unresolved_findings ? 'CHANGES_REQUESTED' : 'COMPLETED',
          evidence:'codex_current_head_summary',id:comment.id||null};
      } catch { /* Malformed/unbound comments cannot certify a review. */ }
    }
  }
  return null;
}
function inProgress(provider,sha,comments,checks) {
  if (provider.name==='codex') {
    for(const c of comments) {
      if(!vendorComment(c,provider))continue;
      const m=String(c.body||'').match(/<!--\s*codex-review-state:v1\s+(\{[^]*?\})\s*-->/);
      if(!m)continue;
      try {const r=JSON.parse(m[1]).code_review;
        if(r?.commit_sha===sha && ['in_progress','pending','queued','running'].includes(r.status))return true;
      } catch {}
    }
  }
  const apps={codex:['chatgpt-codex-connector'],sourcery:['sourcery-ai','sourcery'],coderabbit:['coderabbitai']};
  return checks.some(c=>c.head_sha===sha && apps[provider.name].includes(c.app?.slug)
    && ['in_progress','queued'].includes(c.status)
    && (['CodeRabbit','Sourcery'].includes(c.name) || /review/i.test(c.name||'')));
}
function quotaWarning(provider, comments, now) {
  const since = Date.parse(now) - 24*60*60*1000;
  return comments.some(c=>vendorComment(c,provider)
    && Number.isFinite(Date.parse(c.created_at)) && Date.parse(c.created_at)>=since
    && Date.parse(c.created_at)<=Date.parse(now)
    && /(?:usage limits? for code reviews|review quota|rate limit exceeded|review rate limit|insufficient credits|quota.*exhaust|paid plan required|seat.*required)/i.test(String(c.body||'')));
}
function planReviews(input) {
  const {repo,pr,run,actor,hasRequestToken,comments=[],reviews=[],checks=[],quotaComments=[],now=new Date().toISOString()}=input;
  const empty={eligible:false,requests:[],reviewed:[],blocked:[],requested:[]};
  if (repo!==REPOSITORY || !pr || !run
    || pr.base?.repo?.full_name!==repo || pr.head?.repo?.full_name!==repo
    || run.head_repository?.full_name!==repo) return {...empty,reason:'foreign_repository'};
  if (pr.state!=='open') return {...empty,reason:'pr_not_open'};
  if (run.name!=='Validate application' || run.event!=='pull_request'
    || (run.path && run.path!=='.github/workflows/ci.yml')) return {...empty,reason:'untrusted_ci'};
  if (run.conclusion!=='success' || (run.status && run.status!=='completed')) return {...empty,reason:'ci_not_successful'};
  if (!SHA.test(pr.head.sha||'') || !Number.isSafeInteger(pr.number)) return {...empty,reason:'invalid_pr_identity'};
  if (run.head_sha!==pr.head.sha) return {...empty,reason:'stale_ci'};
  const plan={...empty,eligible:true,reason:'ready'};
  const ownMarker = m=>comments.some(c=>c.user?.id===actor?.id && actor?.id!=null && String(c.body||'').includes(m));
  const batchSent=ownMarker(marker(pr.number,pr.head.sha));
  for (const provider of PROVIDERS) {
    const reviewed=receipt(provider,pr.head.sha,comments,reviews);
    if (reviewed) {plan.reviewed.push(reviewed);continue;}
    if (inProgress(provider,pr.head.sha,comments,checks)) {
      plan.requested.push({name:provider.name,state:'provider_review_in_progress'});continue;
    }
    if (quotaWarning(provider,[...comments,...quotaComments],now)) {
      plan.blocked.push({name:provider.name,reason:'quota_or_entitlement'});continue;
    }
    if (batchSent || ownMarker(marker(pr.number,pr.head.sha,provider.name))) {
      plan.requested.push({name:provider.name,state:'requested_no_current_head_receipt'});continue;
    }
    if (!hasRequestToken) {
      plan.blocked.push({name:provider.name,reason:'missing_review_request_token'});continue;
    }
    if (actor?.type!=='User' || !Number.isSafeInteger(actor.id) || /\[bot\]$/i.test(actor.login||'')) {
      plan.blocked.push({name:provider.name,reason:'review_actor_not_human'});continue;
    }
    plan.requests.push({...provider,marker:marker(pr.number,pr.head.sha,provider.name)});
  }
  if (!plan.requests.length) {
    plan.reason = plan.blocked.find(x=>x.reason==='missing_review_request_token')?.reason
      || plan.blocked.find(x=>x.reason==='review_actor_not_human')?.reason
      || (plan.blocked.length ? 'provider_blocked' : plan.requested.length ? 'already_dispatched' : 'current_head_review_observed');
  }
  return plan;
}
function createApi(token, transport=fetch) {
  if (!token) throw new Error('A GitHub metadata token is required');
  return async function api(path,method='GET',body) {
    if (!path.startsWith('/') || path.startsWith('//') || /[\r\n]/.test(path)) throw new Error('Invalid GitHub API path');
    const response=await transport('https://api.github.com'+path,{
      method,headers:{Authorization:`Bearer ${token}`,Accept:'application/vnd.github+json',
        'X-GitHub-Api-Version':'2022-11-28','Content-Type':'application/json'},
      signal:AbortSignal.timeout(20000),body:body===undefined?undefined:JSON.stringify(body),redirect:'error',
    });
    if (!response.ok) throw new Error(`GitHub metadata request failed: HTTP ${response.status}`);
    return response.status===204 ? null : response.json();
  };
}
async function pages(api,path) {
  const rows=[];
  for (let page=1;page<=10;page++) {
    const chunk=await api(`${path}${path.includes('?')?'&':'?'}per_page=100&page=${page}`);
    if (!Array.isArray(chunk)) throw new Error('Unexpected paginated API response');
    rows.push(...chunk);
    if (chunk.length<100) return rows;
  }
  throw new Error('Review metadata exceeds bounded pagination; refusing ambiguous duplicate dispatch');
}
async function dispatch(plan,{api,requestApi,repo,number,sha}) {
  const sent=[];
  for (const p of plan.requests) {
    // A push between CI completion and this request must not review a new, untested head.
    const current=await api(`/repos/${repo}/pulls/${number}`);
    if (current.state!=='open' || current.head?.sha!==sha || current.head.repo?.full_name!==repo) {
      return {sent,stopped:'head_changed_before_request'};
    }
    // Sourcery requires its command as its own comment. Keep idempotency metadata
    // separate and claim before dispatch; an interrupted claim must be inspected.
    const claim=await requestApi(`/repos/${repo}/issues/${number}/comments`,'POST',{
      body:p.marker+'\n\nPreparing '+p.name+' review request for '+sha+'. Delivery is not yet confirmed; this is not approval.',
    });
    const checked=await api(`/repos/${repo}/pulls/${number}`);
    if (checked.state!=='open' || checked.head?.sha!==sha || checked.head.repo?.full_name!==repo) {
      return {sent,stopped:'head_changed_before_request'};
    }
    const comment=await requestApi(`/repos/${repo}/issues/${number}/comments`,'POST',{body:p.command});
    sent.push({name:p.name,comment_id:comment.id,state:'requested_not_reviewed'});
    await requestApi(`/repos/${repo}/issues/comments/${claim.id}`,'PATCH',{
      body:p.marker+'\n\nRequested '+p.name+' review for '+sha+'; command comment ID '+comment.id+'. Completion is not verified. This is not approval.',
    });
  }
  return {sent,stopped:null};
}
function markdown(number,sha,plan,sent=[]) {
  const rows=PROVIDERS.map(p=>{
    const r=plan.reviewed.find(r=>r.name===p.name);
    const b=plan.blocked.find(r=>r.name===p.name);
    const q=sent.find(r=>r.name===p.name)||plan.requested.find(r=>r.name===p.name);
    const state=r ? `Current-head receipt: ${r.state}` : b ? `Blocked: ${b.reason}` : q ? (q.state==='provider_review_in_progress'?'Provider review in progress':'Requested; completion not verified') : plan.reason;
    return `| ${p.name} | ${state} |`;
  });
  return ['<!-- sps-ai-review-evidence:v1 -->','## AI reviewer evidence (not merge approval)',
    `PR #${number} · head \`${sha}\``, '', '| Reviewer | Observed state |','|---|---|',...rows,'',
    'CI success is not an AI review. A request comment is not a completed review. Formal comments may contain findings; human approval and release tests still apply.',
    'Missing REVIEW_REQUEST_TOKEN means no bot-comment fallback. Sourcery ignores bot authors. Configure a dedicated least-privilege user token or use native reviewer-app settings; never repurpose unrelated secrets.',
    'Provider quotas/installations/entitlements cannot be repaired by repository code. No credits or subscriptions are purchased by this workflow.'].join('\n');
}
async function run(environment=process.env) {
  if (environment.GITHUB_REPOSITORY!==REPOSITORY) throw new Error('Wrong repository');
  const event=JSON.parse(fs.readFileSync(environment.GITHUB_EVENT_PATH,'utf8'));
  const api=createApi(environment.GITHUB_TOKEN);
  const mode=event.inputs?.mode==='audit'?'audit':'request';
  let prs=[],ciRun;
  if (environment.GITHUB_EVENT_NAME==='workflow_run') {
    ciRun=await api(`/repos/${REPOSITORY}/actions/runs/${Number(event.workflow_run?.id)}`);
    if (ciRun.name!=='Validate application' || ciRun.event!=='pull_request' || ciRun.conclusion!=='success'
      || ciRun.path!=='.github/workflows/ci.yml' || ciRun.head_repository?.full_name!==REPOSITORY) {
      console.log('No trusted successful PR validation to process');return;
    }
    const linked=ciRun.pull_requests?.length ? ciRun.pull_requests
      : await api(`/repos/${REPOSITORY}/commits/${ciRun.head_sha}/pulls?per_page=50`);
    prs=linked.slice(0,10).map(p=>p.number);
  } else if (environment.GITHUB_EVENT_NAME==='workflow_dispatch') {
    const number=Number(event.inputs?.pull_request);
    if (!Number.isSafeInteger(number) || number<1) throw new Error('Invalid PR number');
    prs=[number];
  } else throw new Error('Unsupported review event');
  let actor=null,requestApi=null,credentialError=null;
  if (mode==='request' && environment.REVIEW_REQUEST_TOKEN) {
    requestApi=createApi(environment.REVIEW_REQUEST_TOKEN);
    try {actor=await requestApi('/user');}
    catch {credentialError='review_request_token_invalid';}
  }
  const since=encodeURIComponent(new Date(Date.now()-86400000).toISOString());
  const quotaComments=await pages(api,`/repos/${REPOSITORY}/issues/comments?since=${since}&sort=created&direction=desc`);
  for(const number of [...new Set(prs)]) {
    if(!Number.isSafeInteger(number)||number<1)continue;
    const pr=await api(`/repos/${REPOSITORY}/pulls/${number}`);
    let run=ciRun;
    if (!run) {
      if (!SHA.test(pr.head?.sha||''))continue;
      const runs=await api(`/repos/${REPOSITORY}/actions/workflows/ci.yml/runs?event=pull_request&head_sha=${pr.head.sha}&per_page=20`);
      run=runs.workflow_runs?.[0];
      if (!run) {console.log(`PR #${number}: no validation for current head`);continue;}
    }
    const comments=await pages(api,`/repos/${REPOSITORY}/issues/${number}/comments`);
    const reviews=await pages(api,`/repos/${REPOSITORY}/pulls/${number}/reviews`);
    const checkResponse=await api(`/repos/${REPOSITORY}/commits/${pr.head.sha}/check-runs?per_page=100`);
    const checks=checkResponse.check_runs||[];
    const plan=planReviews({repo:REPOSITORY,pr,run,actor,
      hasRequestToken:mode==='request'&&Boolean(environment.REVIEW_REQUEST_TOKEN),comments,reviews,checks,quotaComments});
    if(credentialError) {plan.requests=[];plan.reason=credentialError;}
    const result=plan.requests.length ? await dispatch(plan,{api,requestApi,repo:REPOSITORY,number,sha:pr.head.sha}) : {sent:[],stopped:null};
    const body=markdown(number,pr.head.sha,plan,result.sent)+(result.stopped?'\n\nStopped: '+result.stopped:'');
    if(environment.GITHUB_STEP_SUMMARY)fs.appendFileSync(environment.GITHUB_STEP_SUMMARY,body+'\n\n');
    console.log(JSON.stringify({pr:number,head:pr.head.sha,reason:plan.reason,reviewed:plan.reviewed,blocked:plan.blocked,sent:result.sent,stopped:result.stopped}));
    // Use the workflow token only for a status note, never to impersonate a user reviewer request.
    const existing=comments.find(c=>c.user?.login==='github-actions[bot]' && String(c.body||'').startsWith('<!-- sps-ai-review-evidence:v1 -->'));
    if(existing)await api(`/repos/${REPOSITORY}/issues/comments/${existing.id}`,'PATCH',{body});
    else await api(`/repos/${REPOSITORY}/issues/${number}/comments`,'POST',{body});
    if(plan.blocked.length)console.log('::warning title=AI review prerequisite blocked::See the reviewer evidence summary; no completed review is claimed.');
  }
}
module.exports={PROVIDERS,marker,planReviews,dispatch,markdown,createApi,receipt,run};
if(require.main===module)run().catch(()=>{console.error('AI review metadata coordination failed; no approval is implied.');process.exitCode=1;});
