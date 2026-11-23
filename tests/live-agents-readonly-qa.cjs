'use strict';
const assert=require('node:assert/strict');
const {test}=require('node:test');
const {chromium}=require('playwright');
const fs=require('node:fs');
fs.mkdirSync('qa-live-artifacts',{recursive:true});
const site='https://sripanchamispiritual.com';
const run=async width=>chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||undefined,args:['--no-sandbox']});
async function image(p,name){try{await p.screenshot({path:'qa-live-artifacts/'+name+'.png',timeout:9000});}catch{}}
test('Live deployment SHA and guest admin denial',{timeout:65000},async()=>{
 const b=await run(),p=await b.newPage({viewport:{width:375,height:812},serviceWorkers:'block'});
 try{
  const r=await p.request.get(site+'/is-sha-valid?sha=1b549f5ecf22f70214029440585e183f3aa12fd9',{timeout:15000});
  let body={};try{body=await r.json()}catch{}
  console.log('LIVE_SHA',JSON.stringify({http:r.status(),status:body.status,match:body.match,sha:body.deployed_sha,source:body.source}));
  const admin=await p.goto(site+'/admin/agent',{waitUntil:'domcontentloaded',timeout:30000});
  await image(p,'guest-admin-boundary');
  assert.ok([200,302,303].includes(admin.status()));
  assert.ok(p.url().includes('/login')||await p.locator('input[name="password"]').count()>0,'Admin must require login');
  assert.equal(await p.locator('#agent-messages').count(),0);
 }finally{await b.close();}
});
test('Live Chromium Support answers and A2UI at mobile and desktop',{timeout:350000},async()=>{
 const b=await run(),problems=[];
 const mobile=[
  ['services','What services do you offer?'],
  ['booking','Please book me an appointment'],
  ['products','Show me your products'],
  ['private','What is my latest order?']
 ];
 try{for(const width of [375,1280]){
  const p=await b.newPage({viewport:{width,height:width===375?812:900},serviceWorkers:'block'});
  let errors=[];p.on('pageerror',e=>errors.push(e.message.slice(0,120)));
  try{
   const home=await p.goto(site+'/shop',{waitUntil:'domcontentloaded',timeout:30000});
   assert.equal(home?.status(),200);
   await p.locator('.support-fab').click({timeout:12000});
   await p.locator('#support-panel').waitFor({state:'visible'});
   const rect=await p.locator('#support-panel').boundingBox();
   assert.ok(rect.x>=-2&&rect.x+rect.width<=width+2,'Support panel overflows viewport');
   await image(p,'support-'+width+'-initial');
   for(const [label,question] of (width===375?mobile:mobile.slice(0,2))){
    await p.locator('#support-form textarea').fill(question);
    const responseWait=p.waitForResponse(r=>new URL(r.url()).pathname==='/support/ask'&&r.request().method()==='POST',{timeout:47000});
    const started=Date.now();
    await p.locator('#support-form button').click();
    const response=await responseWait;let body={};
    try{body=await response.json()}catch{}
    const answer=String(body.reply||body.error||'');
    console.log('LIVE_SUPPORT',JSON.stringify({width,label,status:response.status(),ms:Date.now()-started,
      outcome:body.run?.outcome,hasA2ui:!!body.a2ui,visibleCards:await p.locator('.bapx-a2ui__card').count(),
      inlineEnquiry:await p.locator('#support-enquiry').isVisible().catch(()=>false),answer:answer.slice(0,350)}));
    await image(p,'support-'+width+'-'+label);
    assert.equal(response.status(),200,'Support HTTP status '+label);
    assert.ok(answer.length>9,'Missing Support answer');
    if(label==='private')assert.match(answer,/sign in|login|account/i);
    if(label==='booking')assert.doesNotMatch(answer,/confirmed appointment|you are booked/i);
   }
   assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2));
   assert.deepEqual(errors,[]);
  }catch(e){problems.push({width,error:e.message.slice(0,250)});console.log('LIVE_ERROR',JSON.stringify(problems.at(-1)));await image(p,'support-'+width+'-error')}
  finally{await p.close();}
 }}finally{await b.close()}
 assert.deepEqual(problems,[],'Support live QA failures');
});
test('Owner Enterprise read-only when authorized QA credentials exist',{timeout:100000},async t=>{
 const username=process.env.SPS_QA_ADMIN_IDENTIFIER||'',password=process.env.SPS_QA_ADMIN_PASSWORD||'';
 if(!username||!password){console.log('ENTERPRISE_AUTH_SKIPPED: No authorized QA admin secret pair');t.skip('No production owner session configured');return;}
 const b=await run(),p=await b.newPage({viewport:{width:375,height:812},serviceWorkers:'block'});
 try{
  await p.goto(site+'/login',{waitUntil:'domcontentloaded',timeout:30000});
  await p.locator('#login-identifier').fill(username);await p.locator('#login-password').fill(password);
  await Promise.all([p.waitForNavigation({waitUntil:'domcontentloaded',timeout:30000}),p.getByRole('button',{name:'Sign In'}).click()]);
  await p.goto(site+'/admin/agent',{waitUntil:'domcontentloaded',timeout:30000});
  assert.equal(await p.locator('#agent-messages').count(),1,'Not authenticated as admin');
  await p.locator('#agent-input').fill('What can you do?');
  const answer=p.waitForResponse(r=>new URL(r.url()).pathname==='/admin/agent/ask',{timeout:55000});
  await p.locator('#agent-submit').click();const r=await answer;let body={};try{body=await r.json()}catch{}
  console.log('ENTERPRISE_LIVE',JSON.stringify({http:r.status(),answerLength:String(body.answer||'').length,
   a2ui:!!body.a2ui,uiCards:await p.locator('#agent-messages .bapx-a2ui__card').count(),route:body.run?.route}));
  assert.equal(r.status(),200);assert.ok(String(body.answer||'').length>10);
 }finally{await b.close()}
});
