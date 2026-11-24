'use strict';
// Real Chromium rendering of the native PHP owner PageSpeed view; synthetic
// results and intercepted same-origin form action, never real Google calls.
const {test}=require('node:test');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {readFileSync}=require('node:fs');
const path=require('node:path');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'..');
const origin='http://pagespeed-owner.example.test';
const view=execFileSync('php',['-r',[
  "putenv('BAPX_TEST_MODE=1'); require 'app/bootstrap.php';",
  "$csrf='fixture-csrf'; $origin='https://example.org'; $notice=''; $cooldown=false;",
  "$record=['at'=>time(),'results'=>[",
  "['strategy'=>'mobile','status'=>'complete','performance_score'=>92,",
  "'field_status'=>'unavailable','field'=>[],'lab'=>[['name'=>'Largest contentful paint','display'=>'1.8 s']],",
  "'opportunities'=>[['title'=>'Reduce unused CSS','savings_ms'=>650]]],",
  "['strategy'=>'desktop','status'=>'unavailable','message'=>'PageSpeed quota reached. Try again later.', 'performance_score'=>null]]];",
  "include 'views/admin/google-pagespeed.php';",
].join(' ')],{cwd:root,encoding:'utf8',maxBuffer:2*1024*1024});
const pageHtml='<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
  +'<link rel="stylesheet" href="/assets/css/band.css">'
  +'<link rel="stylesheet" href="/assets/css/admin-refinement.css">'
  +'</head><body><main class="admin-body">'+view+'</main></body></html>';
for(const width of [375,1280]) test('native owner PageSpeed displays honest observed and unavailable states at '+width+'px',async()=>{
  const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||undefined,args:['--no-sandbox']});
  const context=await browser.newContext({viewport:{width,height:850}});
  const tab=await context.newPage();
  const errors=[],posts=[];
  tab.on('pageerror',e=>errors.push(e.message));
  try {
    await tab.route('**/*',route=>{
      const req=route.request(),url=new URL(req.url());
      assert.equal(url.origin,origin,'Only fixture origin may be contacted');
      if(url.pathname==='/admin/google-pagespeed/run'){
        posts.push(new URLSearchParams(req.postData()||'').get('_csrf'));
        return route.fulfill({status:200,contentType:'text/html',body:pageHtml});
      }
      if(url.pathname==='/admin/google-pagespeed') return route.fulfill({contentType:'text/html',body:pageHtml});
      if(url.pathname==='/assets/css/band.css'||url.pathname==='/assets/css/admin-refinement.css'){
        return route.fulfill({contentType:'text/css',body:readFileSync(path.join(root,url.pathname.slice(1)))});
      }
      return route.fulfill({status:404,body:'Fixture only'});
    });
    await tab.goto(origin+'/admin/google-pagespeed');
    assert.ok(await tab.getByRole('heading',{name:'PageSpeed Insights'}).isVisible());
    assert.match(await tab.locator('main').innerText(),/92\/100 performance/);
    assert.match(await tab.locator('main').innerText(),/PageSpeed quota reached/);
    assert.match(await tab.locator('main').innerText(),/Not enough field data/);
    const layout=await tab.evaluate(()=>({
      width:document.documentElement.scrollWidth,
      viewport:innerWidth,
      resultCount:document.querySelectorAll('.google-pagespeed__result').length,
      columns:getComputedStyle(document.querySelector('.google-pagespeed__results')).gridTemplateColumns,
    }));
    assert.equal(layout.resultCount,2,'Mobile and desktop show independently');
    assert.ok(layout.width<=width+1,'Admin PageSpeed must fit viewport: '+JSON.stringify(layout));
    assert.equal(layout.columns.split(' ').length,width===375?1:2,'Use one column on phone');
    await tab.getByRole('button',{name:'Analyze mobile and desktop'}).click();
    assert.deepEqual(posts,['fixture-csrf'],'Only user gesture submits the real CSRF-backed form');
    assert.deepEqual(errors,[],'No JS exceptions');
  } finally {await context.close();await browser.close();}
});
