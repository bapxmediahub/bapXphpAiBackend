'use strict';
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const path=require('node:path');
const {test}=require('node:test');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'..');
const origin='http://blog-inference.test';
const render=String.raw`putenv('BAPX_TEST_MODE=1'); require 'app/bootstrap.php'; $csrf='fixture'; $posts=[];$categories=[];$mediaFiles=[];$blogDraftOwner='fixture';$currentAuthor='Fixture';include 'views/admin/blog.php';`;
const view=execFileSync('php',['-r',render],{cwd:root,encoding:'utf8'});
for(const width of [1280,375])test('Provider-only editorial generation, review and stale protection at '+width+'px',{timeout:30000},async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||undefined});
 const page=await browser.newPage({viewport:{width,height:800}});
 let requests=[],mode='failed',release;
 try{
  await page.route('**/*',async route=>{
   const u=new URL(route.request().url());
   if(u.origin!==origin)return route.abort();
   if(u.pathname==='/admin/blog')return route.fulfill({contentType:'text/html',body:'<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>'+view+'</body></html>'});
   if(u.pathname.startsWith('/assets/')&&u.pathname.endsWith('.js'))return route.fulfill({path:path.join(root,u.pathname)});
   if(u.pathname==='/admin/blog/ai-draft'||u.pathname==='/admin/blog/enhance-content'){
    const args=new URLSearchParams(route.request().postData());
    requests.push({path:u.pathname,title:args.get('title'),content:args.get('content')});
    if(mode==='failed')return route.fulfill({status:503,json:{error:'Provider unavailable'}});
    if(mode==='delayed')await new Promise(r=>release=r);
    return route.fulfill({json:{source:'provider',content:'## Generated article\n\nReview this actual fixture output.'}});
   }
   return route.fulfill({status:404,body:''});
  });
  await page.goto(origin+'/admin/blog');
  await page.getByRole('button',{name:'+ New Post',exact:true}).click();
  const title=page.locator('#edit-title'),body=page.locator('#edit-content');
  const button=page.getByRole('button',{name:'Enhance content',exact:true});
  const panel=page.locator('#be-content-rewrite');
  await title.fill('Original article title');
  await page.getByRole('button',{name:'Edit Markdown',exact:true}).click();
  await button.click();
  await panel.getByText('Provider unavailable',{exact:true}).waitFor();
  assert.equal(await body.inputValue(),'');
  assert.equal(await panel.locator('.be-rewrite__review').isVisible(),false);
  assert.deepEqual(requests[0],{path:'/admin/blog/ai-draft',title:'Original article title',content:''});
  mode='provider';await button.click();
  await panel.getByRole('button',{name:'Apply to content'}).waitFor();
  assert.equal(await body.inputValue(),'','Model output must not apply without review');
  await title.fill('Changed title');
  await panel.getByRole('button',{name:'Apply to content'}).click();
  assert.equal(await body.inputValue(),'','A stale title must prevent applying a draft');
  await button.click();
  await panel.getByRole('button',{name:'Apply to content'}).click();
  assert.match(await body.inputValue(),/Generated article/);
  assert.equal(await page.locator('#edit-published').inputValue(),'0');
  await body.fill('Existing user text.');
  mode='failed';await button.click();
  await panel.getByText('Provider unavailable',{exact:true}).waitFor();
  assert.equal(await body.inputValue(),'Existing user text.');
  assert.equal(await panel.locator('.be-rewrite__review').isVisible(),false);
  assert.equal(requests.at(-1).path,'/admin/blog/enhance-content');
  mode='delayed';await button.click();
  await page.waitForFunction(()=>document.querySelector('#enhance-content').disabled);
  for(let i=0;i<100&&!release;i++)await new Promise(r=>setTimeout(r,20));
  assert.equal(typeof release,'function');
  await body.fill('Newer unsaved edit.');
  release();
  await panel.getByText(/draft changed while the request was running/).waitFor();
  assert.equal(await body.inputValue(),'Newer unsaved edit.');
  assert.equal(await panel.locator('.be-rewrite__review').isVisible(),false);
 }finally{await browser.close();}
});

test('Enterprise provider draft is explicit create-only unpublished form; no save on inference failure',{timeout:20000},async()=>{
 const html=execFileSync('php',['-r',String.raw`function e($s){return htmlspecialchars($s,ENT_QUOTES);}$csrf='fixture';$attachables=[];$modelConfig=['configured'=>true];$chatScope='fixture-owner';include 'views/admin/agent.php';`],{cwd:root,encoding:'utf8'});
 const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||undefined});
 const page=await browser.newPage({viewport:{width:375,height:812}});
 let fails=true,saves=0;
 try{
  await page.route('**/*',route=>{
   const u=new URL(route.request().url());
   if(u.origin!==origin)return route.abort();
   if(u.pathname==='/admin/agent')return route.fulfill({contentType:'text/html',body:html});
   if(u.pathname.startsWith('/assets/')&&u.pathname.endsWith('.js'))return route.fulfill({path:path.join(root,u.pathname)});
   if(u.pathname==='/admin/agent/ask'){
    if(fails)return route.fulfill({status:503,json:{error:'Provider unavailable'}});
    return route.fulfill({json:{draft:{kind:'blog',action:'/admin/blog/save',source:'provider',note:'Model-generated draft.',fields:[
     {name:'title',label:'Title',type:'text',required:true,value:'Fixture title'},
     {name:'slug',label:'Slug',type:'text',required:true,value:'fixture-title'},
     {name:'content',label:'Content',type:'markdown',required:true,value:'Fixture body.'}
    ]}}});
   }
   if(u.pathname==='/admin/blog/save'){saves++;return route.fulfill({status:500,body:'Unexpected automatic save'});}
   return route.fulfill({status:404,body:''});
  });
  await page.goto(origin+'/admin/agent');
  await page.addScriptTag({path:path.join(root,'assets/agent-status.js')});
  const input=page.locator('#agent-input'),send=page.locator('#agent-submit');
  await input.fill('/create-blog Fixture');await send.click();
  await page.getByText('Provider unavailable',{exact:false}).first().waitFor();
  assert.equal(await page.locator('.agent-draft__form').count(),0);
  fails=false;await input.fill('/create-blog Fixture');await send.click();
  const form=page.locator('.agent-draft__form');
  await form.getByRole('button',{name:'Save unpublished draft'}).waitFor();
  assert.equal(await form.locator('[name=published]').inputValue(),'0');
  assert.equal(await form.locator('[name=expected_revision]').inputValue(),'new');
  assert.equal(await form.locator('[name=original_slug]').inputValue(),'');
  assert.equal(await form.locator('[name=content]').inputValue(),'Fixture body.');
  assert.equal(saves,0);
 }finally{await browser.close();}
});
