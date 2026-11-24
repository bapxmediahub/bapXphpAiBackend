'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process'),{readFileSync,existsSync}=require('node:fs'),path=require('node:path'),{chromium}=require('playwright');
const root=path.resolve(__dirname,'..'),origin='https://home-fixture.example.test';
const source="putenv('BAPX_TEST_MODE=1'); require 'app/bootstrap.php'; $_SESSION=['csrf_token'=>'fixture']; $bestSellers=[]; for($i=1;$i<=4;$i++)$bestSellers[]=['slug'=>'fixture-'.$i,'name'=>'Fixture product '.$i,'description'=>'A published product description','price'=>500,'stock_status'=>'active','image_url'=>'/assets/images/hero/varahi/varahi-01.webp']; $homeCollections=[['slug'=>'festival','name'=>'Festival collection','products'=>$bestSellers]]; $temples=[['slug'=>'one','name'=>'Temple one','address'=>'Tamil Nadu','timings'=>'Morning'],['slug'=>'two','name'=>'Temple two','address'=>'Tamil Nadu']]; require 'views/public/home.php';";
const markup=execFileSync('php',['-r',source],{cwd:root,encoding:'utf8'});
const html='<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/css/band.css"><link rel="stylesheet" href="/assets/css/site-motion.css"><script defer src="/assets/site-motion.js"></script><script defer src="/assets/page-actions.js"></script></head><body><header class="site-header" id="site-header"><a href="/">Fixture shop</a></header><main class="home-main">'+markup+'</main><footer>Footer</footer></body></html>';
async function setup(page){await page.route('**/*',route=>{const u=new URL(route.request().url());if(u.origin!==origin)return route.abort();if(u.pathname==='/')return route.fulfill({contentType:'text/html; charset=utf-8',body:html});const file=path.join(root,u.pathname);if(u.pathname.startsWith('/assets/')&&existsSync(file)){return route.fulfill({contentType:file.endsWith('.css')?'text/css':file.endsWith('.js')?'application/javascript':file.endsWith('.webp')?'image/webp':'image/png',body:readFileSync(file)});}return route.fulfill({status:404,body:''});});await page.goto(origin+'/');}
for(const [width,height] of [[375,812],[1280,900],[375,580]])test('full native cards, product rail and reverse scrolling '+width+'x'+height,{timeout:30000},async()=>{
 const b=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox']});const p=await b.newPage({viewport:{width,height}});const errors=[];p.on('pageerror',e=>errors.push(e.message));
 try{await setup(p);await p.locator('[data-home-stack][data-stack-ready]').waitFor({timeout:5000});
 const m=await p.evaluate(()=>{const hero=document.querySelector('.home-opening');const header=document.querySelector('#site-header');const track=document.querySelector('[data-home-track]');const card=track.querySelector('.home-product-card');return{hero:hero.offsetHeight,available:innerHeight-header.offsetHeight,track:track.clientWidth,card:card.offsetWidth,overflow:document.documentElement.scrollWidth,position:getComputedStyle(hero).position,animations:document.querySelector('[data-home-stack]').getAnimations({subtree:true}).length};});
 assert.ok(m.hero>=m.available-2,'Opening must fill the available viewport');assert.equal(m.position,'sticky');assert.ok(m.overflow<=width+1);assert.ok(m.animations<=2,'Avoid nested animations on every card');if(width===375)assert.ok(Math.abs(m.track-m.card)<=2,'One complete card per mobile slide');
 const track=p.locator('[data-home-track]').first();await track.scrollIntoViewIfNeeded();
 const next=p.locator('[data-home-rail]').first().getByRole('button',{name:'Next product'});
 await next.click();await p.waitForFunction(()=>document.querySelector('[data-home-track]').scrollLeft>10);
 assert.ok(await track.locator('.home-product-card').nth(1).getByRole('button',{name:'Buy Now: Fixture product 2'}).isEnabled());
 await p.evaluate(()=>{document.activeElement?.blur();scrollTo(0,0)});await p.waitForTimeout(100);
 const start=await p.locator('.home-opening').evaluate(e=>Math.max(0,e.getBoundingClientRect().top-parseFloat(getComputedStyle(e).top))+40);
 await p.evaluate(y=>scrollTo(0,y),start);await p.waitForTimeout(100);
 const before=await p.locator('.home-opening').evaluate(e=>e.getBoundingClientRect().top);
 const incomingBefore=await p.locator('.home-bestsellers').evaluate(e=>e.getBoundingClientRect().top);
 await p.evaluate(y=>scrollTo(0,y+100),start);await p.waitForTimeout(100);
 const after=await p.locator('.home-opening').evaluate(e=>e.getBoundingClientRect().top);
 const incomingAfter=await p.locator('.home-bestsellers').evaluate(e=>e.getBoundingClientRect().top);
 assert.ok(Math.abs(after-before)<=2,'Opening holds after its full content is exposed');
 assert.ok(incomingAfter<incomingBefore-90,'Next complete card advances in native flow');
 await p.screenshot({path:'/tmp/bapx-home-fixture-'+width+'-'+height+'-handoff.png'});
 await p.evaluate(()=>scrollTo(0,0));await p.waitForTimeout(100);assert.ok(await p.getByRole('link',{name:'Shop Products',exact:true}).isVisible());
 await p.screenshot({path:'/tmp/bapx-home-fixture-'+width+'-'+height+'-top.png'});await p.getByRole('link',{name:'Shop Products',exact:true}).focus();await p.keyboard.press('Tab');assert.equal(await p.locator('.home-opening').evaluate(e=>getComputedStyle(e).position),'relative','Keyboard focus restores native flow');await p.emulateMedia({reducedMotion:'reduce'});assert.equal(await p.locator('.home-opening').evaluate(e=>getComputedStyle(e).position),'relative');assert.deepEqual(errors,[]);
 }finally{await b.close();}
});
test('no JavaScript leaves complete content and purchase forms available',async()=>{const b=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox']});try{const p=await b.newPage({viewport:{width:375,height:812},javaScriptEnabled:false});await setup(p);assert.equal(await p.locator('.home-opening').evaluate(e=>getComputedStyle(e).position),'relative');assert.equal(await p.locator('.home-product-card').count(),8);assert.equal(await p.locator('.home-product-card form[action="/cart/add"]').count(),16);assert.ok(await p.getByRole('link',{name:'Shop Products',exact:true}).isVisible());}finally{await b.close();}});
