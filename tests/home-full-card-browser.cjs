'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process'),{readFileSync,existsSync}=require('node:fs'),path=require('node:path'),{chromium}=require('playwright');
const root=path.resolve(__dirname,'..'),origin='https://home-fixture.example.test';
const source="putenv('BAPX_TEST_MODE=1'); require 'app/bootstrap.php'; $siteProfile=new App\\Services\\SiteProfileService([], 'https://sripanchamispiritual.com'); $_SESSION=['csrf_token'=>'fixture']; $bestSellers=[]; for($i=1;$i<=4;$i++)$bestSellers[]=['slug'=>'fixture-'.$i,'name'=>$i===4?'Fixture product with an exceptionally long devotional jewellery title and an extensive descriptive name for the festival collection':'Fixture product '.$i,'description'=>'A published product description with details about the devotional item and its use, materials, and care information.','price'=>500,'stock_status'=>'active','image_url'=>'/assets/images/hero/varahi/varahi-01.webp']; $homeCollections=[['slug'=>'festival','name'=>'Festival collection','products'=>$bestSellers]]; $temples=[['slug'=>'one','name'=>'Temple one','address'=>'Tamil Nadu','timings'=>'Morning'],['slug'=>'two','name'=>'Temple two','address'=>'Tamil Nadu']]; require 'views/public/home.php';";
const markup=execFileSync('php',['-r',source],{cwd:root,encoding:'utf8'});
const html='<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/css/band.css"><link rel="stylesheet" href="/assets/css/site-motion.css"><link rel="stylesheet" href="/assets/css/page-actions.css"><script defer src="/assets/site-motion.js"></script><script defer src="/assets/page-actions.js"></script></head><body><header class="site-header" id="site-header"><a href="/">Fixture shop</a></header><main class="home-main">'+markup+'</main><footer class="site-footer"><div class="container"><div class="footer-grid"><div><span class="footer-brand">Fixture shop</span><p>Shop information</p></div><div><h4>Explore</h4><a href="/shop">All Products</a></div><div><h4>Support</h4><a href="/contact">Contact</a></div></div><div class="footer-bottom"><a href="/privacy">Privacy</a></div></div></footer><button class="support-fab" type="button" aria-label="Support">?</button><a id="mobile-cart-tray" class="mobile-cart-tray" href="/cart" hidden>View cart</a></body></html>';
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

for (const [width,height] of [[375,812],[1280,900]]) test('homepage resting frame preserves header and shopping controls '+width, {timeout:30000}, async()=>{
 const b=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox']});
 const p=await b.newPage({viewport:{width,height}});
 try {
  await setup(p);await p.locator('[data-stack-ready]').waitFor();
  const frame=await p.evaluate(()=>{
    const header=document.querySelector('#site-header'), section=document.querySelector('.home-bestsellers');
    return {header:header.offsetHeight,section:section.offsetHeight};
  });
  assert.ok(frame.section<=height-frame.header+2,'Bestseller section fits a normal-height viewport with complete cards');
  await p.evaluate(()=>{const section=document.querySelector('.home-bestsellers'),header=document.querySelector('#site-header');window.scrollTo({top:section.getBoundingClientRect().top+scrollY-header.offsetHeight,behavior:'instant'})});
  await p.waitForTimeout(180);
  const geometry=await p.evaluate(()=>{
   const header=document.querySelector('#site-header').getBoundingClientRect();
   const section=document.querySelector('.home-bestsellers').getBoundingClientRect();
   const card=document.querySelector('.home-bestsellers .home-product-card').getBoundingClientRect();
   const controls=[...document.querySelectorAll('.home-bestsellers .home-product-card:first-child .product-purchase .btn')].filter(x=>x.getClientRects().length);
   return {headerTop:header.top,headerBottom:header.bottom,sectionTop:section.top,cardBottom:card.bottom,controls:controls.map(x=>{const r=x.getBoundingClientRect();return {h:r.height,visible:r.top>=header.bottom&&r.bottom<=innerHeight,uncovered:x.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2))}})};
  });
  assert.ok(Math.abs(geometry.headerTop)<=2,'Home header stays visible without an empty top strip');
  assert.ok(Math.abs(geometry.sectionTop-geometry.headerBottom)<=2,'Incoming card meets header');
  assert.ok(geometry.cardBottom<=height-60,'Whole product card clears floating controls');
  assert.ok(geometry.controls.length===2&&geometry.controls.every(x=>x.h>=44&&x.visible&&x.uncovered),'Real purchase controls remain tappable');
  const counter=p.locator('[data-home-position]').first();
  if(width===375) assert.equal(await counter.innerText(),'1 of 4');
  await p.screenshot({path:'/tmp/bapx-home-polish-'+width+'-products.png'});
 } finally {await b.close();}
});

test('cart-visible resting frame keeps purchase controls and trust labels clear', {timeout:30000}, async()=>{
 const b=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox']});
 const p=await b.newPage({viewport:{width:375,height:812}});
 try {
  await setup(p);await p.locator('[data-stack-ready]').waitFor();
  await p.locator('#mobile-cart-tray').evaluate(x=>{x.hidden=false;});
  await p.waitForTimeout(120);
  const trust=await p.evaluate(()=>{
   const t=document.querySelector('.trust-bar__inner').getBoundingClientRect(), c=document.querySelector('#mobile-cart-tray').getBoundingClientRect();
   return {trustBottom:t.bottom,cartTop:c.top};
  });
  assert.ok(trust.trustBottom<=trust.cartTop+2,'Trust labels clear the cart tray on opening card');
  await p.evaluate(()=>{
   const section=document.querySelector('.home-bestsellers');
   const start=section.getBoundingClientRect().top+scrollY;
   scrollTo({top:start+section.offsetHeight-innerHeight,behavior:'instant'});
  });
  await p.waitForTimeout(180);
  const controls=await p.evaluate(()=>{
   return [...document.querySelectorAll('.home-bestsellers .home-product-card:first-child .product-purchase .btn')].filter(x=>x.getClientRects().length).map(x=>{const r=x.getBoundingClientRect();return {visible:r.top>=0&&r.bottom<=innerHeight,hit:x.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2))}});
  });
  assert.ok(controls.length===2&&controls.every(x=>x.visible&&x.hit),'Buy and cart remain available with an active floating cart tray');
 }finally{await b.close();}
});

for(const [width,height] of [[375,812],[1280,900]]) test('footer is a final opaque slide and header stays pinned in every motion mode '+width,{timeout:30000},async()=>{
 const b=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox']});
 const p=await b.newPage({viewport:{width,height}});
 try{
  await setup(p); await p.locator('[data-stack-ready]').waitFor();
  const footer=p.locator('.site-footer');
  assert.equal(await footer.count(),1,'Only one real footer');
  assert.ok(await footer.evaluate(e=>e.parentElement.matches('[data-home-stack]')&&e===e.parentElement.lastElementChild),'Footer is final sibling in editorial stack');
  assert.equal(await p.locator('.home-faq').evaluate(e=>getComputedStyle(e).position),'sticky','FAQ must hold for final footer handoff');
  const g=await p.evaluate(()=>{const h=document.querySelector('#site-header'),f=document.querySelector('.site-footer');return {h:h.offsetHeight,footer:f.offsetHeight}});
  assert.ok(g.footer>=height-g.h-2,'Footer fills available screen');
  const max=await p.evaluate(()=>document.scrollingElement.scrollHeight-innerHeight);
  await p.evaluate(y=>scrollTo({top:y-100,behavior:'instant'}),max);
  const before=await p.locator('.home-faq').evaluate(e=>e.getBoundingClientRect().top);
  await p.evaluate(y=>scrollTo({top:y-40,behavior:'instant'}),max);
  const after=await p.locator('.home-faq').evaluate(e=>e.getBoundingClientRect().top);
  assert.ok(Math.abs(before-after)<=3,'FAQ remains stationary as footer advances');
  await p.evaluate(y=>scrollTo({top:y,behavior:'instant'}),max);
  const resting=await p.evaluate(()=>{const h=document.querySelector('#site-header').getBoundingClientRect(),f=document.querySelector('.site-footer').getBoundingClientRect(),link=document.querySelector('.footer-bottom a'),r=link.getBoundingClientRect();return {top:h.top,bottom:h.bottom,footerTop:f.top,footerBottom:f.bottom,linkHit:link.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2))}});
  assert.ok(Math.abs(resting.top)<=2&&resting.footerTop<=resting.bottom+2&&resting.footerBottom>=height-2,'Final slide reaches pinned header and viewport bottom');
  assert.ok(resting.linkHit,'Footer link is uncovered');
  await p.emulateMedia({reducedMotion:'reduce'});
  await p.evaluate(()=>scrollTo({top:document.scrollingElement.scrollHeight,behavior:'instant'}));
  assert.ok(Math.abs(await p.locator('#site-header').evaluate(e=>e.getBoundingClientRect().top))<=2,'Header stays pinned in reduced motion');
  assert.equal(await p.locator('.home-faq').evaluate(e=>getComputedStyle(e).position),'relative');
 }finally{await b.close();}
});

test('hero has eleven dot controls, stable frame and accessible rotation', {timeout:30000}, async()=>{
 const b=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox']});
 try {
  const p=await b.newPage({viewport:{width:375,height:812}});
  await setup(p);
  const root=p.locator('[data-varahi-slider]');
  const dots=root.locator('[data-portrait-index]');
  assert.equal(await dots.count(),11);
  await p.waitForFunction(()=>document.querySelector('[data-varahi-slider]').dataset.activeIndex==='1',{},{timeout:6000});
  await dots.nth(1).click();
  const selected=await root.getAttribute('data-active-index');
  await p.waitForTimeout(2200);
  assert.equal(await root.getAttribute('data-active-index'),selected);
  const frame=await root.locator('.deity-frame').boundingBox();
  await dots.nth(10).click();
  await p.waitForFunction(()=>document.querySelector('[data-varahi-slider]').dataset.activeIndex==='10');
  assert.equal(await dots.nth(10).getAttribute('aria-current'),'true');
  await root.getByRole('button',{name:'Next portrait'}).click();
  await p.waitForFunction(()=>document.querySelector('[data-varahi-slider]').dataset.activeIndex==='0');
  assert.equal((await root.locator('.deity-frame').boundingBox()).height,frame.height);
  assert.equal(await root.locator('.varahi-slide.is-active').count(),1);
  await p.emulateMedia({reducedMotion:'reduce'});
  await p.waitForTimeout(2200);
  assert.equal(await root.getAttribute('data-active-index'),'0');
  assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
 } finally {await b.close();}
});

test('delayed manual portrait survives focus changes; covered artwork never restarts autoplay', {timeout:30000}, async()=>{
 const b=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox']});
 try {
  const p=await b.newPage({viewport:{width:1280,height:900}});
  await setup(p);
  const root=p.locator('[data-varahi-slider]');
  const dots=root.locator('[data-portrait-index]');
  await p.evaluate(()=>{
   const original=HTMLImageElement.prototype.decode;
   HTMLImageElement.prototype.decode=function(){
    if(this.closest('[data-varahi-slider]')&&this.src.includes('varahi-10.webp')){
     const image=this;
     return new Promise((resolve,reject)=>{window.releasePortrait=()=>original.call(image).then(resolve,reject);});
    }
    return original.call(this);
   };
  });
  await dots.nth(10).click();
  await p.waitForFunction(()=>typeof window.releasePortrait==='function');
  await dots.nth(9).focus();
  await p.evaluate(()=>window.releasePortrait());
  await p.waitForFunction(()=>document.querySelector('[data-varahi-slider]').dataset.activeIndex==='10',{},{timeout:2000});
  // New visit enables autoplay; manual selection permanently stops it for this visit.
  await p.mouse.move(1,1);
  await p.reload();
  await p.waitForFunction(()=>document.querySelector('[data-varahi-slider]').dataset.activeIndex==='1',{},{timeout:6000});
  await p.evaluate(()=>{
   document.activeElement.blur();
   const frame=document.querySelector('[data-varahi-slider] .deity-frame').getBoundingClientRect();
   const cover=document.createElement('div');
   cover.id='test-portrait-cover';
   Object.assign(cover.style,{position:'fixed',left:frame.left+'px',top:frame.top+'px',width:frame.width+'px',height:frame.height+'px',zIndex:'2147483647',background:'#fff'});
   document.body.append(cover);
   window.dispatchEvent(new Event('scroll'));
   document.dispatchEvent(new Event('visibilitychange'));
  });
  await p.waitForTimeout(2300);
  assert.equal(await root.getAttribute('data-active-index'),'1','Covered artwork must not restart');
  await p.evaluate(()=>{document.querySelector('#test-portrait-cover').remove();window.dispatchEvent(new Event('scroll'));});
  await p.waitForFunction(()=>document.querySelector('[data-varahi-slider]').dataset.activeIndex==='2',{},{timeout:6000});
 } finally {await b.close();}
});
