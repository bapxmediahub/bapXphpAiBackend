'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const {spawn}=require('node:child_process');
const {once}=require('node:events');
const net=require('node:net');
const path=require('node:path');
const os=require('node:os');
const {mkdtempSync,rmSync}=require('node:fs');
const {chromium}=require('playwright');
const {verifyLiveRevision}=require('./live-browser-revision.cjs');

async function freePort(){
 const socket=net.createServer();
 socket.listen(0,'127.0.0.1');await once(socket,'listening');
 const port=socket.address().port;await new Promise(resolve=>socket.close(resolve));return port;
}
const expected=process.env.EXPECTED_SHA||'';
const live=Boolean(expected);
async function verifyCredit(page) {
 const credit=page.locator('.site-footer .footer-bottom span').filter({has:page.locator('a[href="https://mediahub.bapx.in/"]')});
 assert.equal(await credit.count(),1,'Exactly one studio credit');
 assert.equal((await credit.innerText()).trim(),'Developed with ♥️ by @bapxmediahub','Preserve exact owner-approved credit');
 const link=credit.getByRole('link',{name:'@bapxmediahub',exact:true});
 assert.equal(await link.getAttribute('href'),'https://mediahub.bapx.in/');
 assert.equal(await link.getAttribute('target'),'_blank');
 assert.deepEqual((await link.getAttribute('rel')).split(/\s+/).sort(),['noopener','noreferrer']);
 assert.ok(await link.isVisible());
}

if(live&&!/^[a-f0-9]{40}$/.test(expected))throw Error('Expected exact deployed Git SHA');

test('actual public layout: pinned brand, complete editorial sequence, readable footer and navigation',{timeout:120000},async()=>{
 const root=path.resolve(__dirname,'..');
 const temporary=live?null:mkdtempSync(path.join(os.tmpdir(),'bapx-home-release-'));
 const port=live?null:await freePort();
 const origin=live?'https://sripanchamispiritual.com':'http://127.0.0.1:'+port;
 const server=live?null:spawn('php',['-d','session.save_path='+temporary,'-S','127.0.0.1:'+port,'index.php'],{cwd:root,env:{...process.env,BAPX_TEST_MODE:'1',APP_URL:'https://sripanchamispiritual.com'},stdio:'ignore'});
 let browser;
 try{
  if(live)await verifyLiveRevision(origin,expected);
  browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true});
  for(const [width,height] of [[1536,850],[1280,900],[375,812],[375,580]]){
   const context=await browser.newContext({viewport:{width,height},serviceWorkers:'block'});
   if(!live)await context.route('https://**/*',r=>r.abort());
   const p=await context.newPage();const errors=[];p.on('pageerror',e=>errors.push(e.message));
   for(let i=0;i<20;i++){try{const r=await p.goto(origin+'/',{waitUntil:'domcontentloaded',timeout:live?25000:2000});assert.equal(r.status(),200);break;}catch(e){if(i===19||live)throw e;await new Promise(r=>setTimeout(r,100));}}
   await p.locator('[data-home-stack][data-stack-ready]').waitFor({timeout:10000});
   await p.evaluate(()=>document.fonts.ready);
   const slider=p.locator('[data-varahi-slider]');
   assert.equal(await slider.locator('[data-portrait-index]').count(),11);
   assert.equal(await slider.locator('[data-portrait-rotation]').count(),0);
   const controls=await slider.locator('[data-portrait-controls]').evaluate(e=>{
    const rect=x=>{const r=x.getBoundingClientRect();return {x:r.x,y:r.y,w:r.width,h:r.height};};
    return {row:rect(e),prev:rect(e.querySelector('[data-portrait-step="-1"]')),next:rect(e.querySelector('[data-portrait-step="1"]')),dots:[...e.querySelectorAll('[data-portrait-index]')].map(rect)};
   });
   assert.ok(controls.row.h<=44,'Single compact row');
   assert.ok(controls.prev.x<controls.dots[0].x&&controls.next.x>controls.dots[10].x,'Arrows flank dots');
   assert.ok(controls.dots.every(d=>Math.abs(d.y-controls.dots[0].y)<1&&d.w>=24&&d.h>=24),'One row of accessible dot targets');
   assert.ok(controls.row.x>=0&&controls.row.x+controls.row.w<=width+1,'Controls stay inside viewport');

   if(width===1280){
    await p.waitForFunction(()=>document.querySelector('[data-varahi-slider]').dataset.activeIndex!=='0',{},{timeout:8000});
    const dots=slider.locator('[data-portrait-index]');
    await dots.nth(10).click();
    await p.waitForFunction(()=>document.querySelector('[data-varahi-slider]').dataset.activeIndex==='10');
    await slider.getByRole('button',{name:'Next portrait'}).click();
    await p.waitForFunction(()=>document.querySelector('[data-varahi-slider]').dataset.activeIndex==='0');
    await p.evaluate(()=>document.activeElement.blur());
   }
   const outerCorners=await p.locator('[data-home-stack]').evaluate(stack=>[...stack.children].flatMap(e=>[e,...(e.matches('.home-chapter')?[e.firstElementChild]:[])].filter(Boolean)).map(e=>({name:e.className,left:getComputedStyle(e).borderTopLeftRadius,right:getComputedStyle(e).borderTopRightRadius})));
   assert.ok(outerCorners.every(e=>e.left==='0px'&&e.right==='0px'),'Outer editorial sheets have square corners: '+JSON.stringify(outerCorners));
   const header=p.locator('#site-header');
   assert.equal(await header.locator('.brand span').innerText(),'Sri Panchami Spiritual');
   assert.ok(await header.locator('.brand img').isVisible());
   assert.equal(await p.locator('.site-footer').count(),1);
   await verifyCredit(p);
   assert.equal(await p.locator('[data-home-stack] > .site-footer:last-child').count(),1);
   assert.equal(await p.locator('.site-footer').getAttribute('role'),'contentinfo');
   const sections=await p.evaluate(()=>{
    const h=document.querySelector('#site-header');
    const stack=document.querySelector('[data-home-stack]');
    return {header:h.offsetHeight,cards:[...stack.children].map(e=>({name:e.className,top:e.getBoundingClientRect().top+scrollY,height:e.offsetHeight,hold:parseFloat(getComputedStyle(e).top),position:getComputedStyle(e).position}))};
   });
   assert.ok(sections.cards.length>=5);
   assert.ok(sections.cards.at(-1).height>=height-sections.header-2,'Footer is at least one available viewport');
   let handoffs=0;
   for(let i=0;i<sections.cards.length-1;i++){
    const c=sections.cards[i];if(c.position!=='sticky')continue;
    const y=Math.max(0,c.top-c.hold+20);
    await p.evaluate(y=>scrollTo({top:y,behavior:'instant'}),y);
    const a=await p.evaluate(i=>{const s=document.querySelector('[data-home-stack]').children,h=document.querySelector('#site-header').getBoundingClientRect();return [s[i].getBoundingClientRect().top,s[i+1].getBoundingClientRect().top,h.top,h.bottom]},i);
    await p.evaluate(y=>scrollTo({top:y+60,behavior:'instant'}),y);
    const b=await p.evaluate(i=>{const s=document.querySelector('[data-home-stack]').children,h=document.querySelector('#site-header').getBoundingClientRect();return [s[i].getBoundingClientRect().top,s[i+1].getBoundingClientRect().top,h.top,h.bottom]},i);
    assert.ok(Math.abs(a[2])<=2&&Math.abs(b[2])<=2,'Persistent header through '+c.name);
    assert.ok(Math.abs(a[0]-b[0])<=3,'Outgoing card holds: '+c.name+' '+JSON.stringify({a,b}));
    assert.ok(b[1]<a[1]-45,'Incoming card advances: '+c.name);
    await p.evaluate(y=>scrollTo({top:y,behavior:'instant'}),y);
    const back=await p.evaluate(i=>document.querySelector('[data-home-stack]').children[i+1].getBoundingClientRect().top,i);
    assert.ok(Math.abs(back-a[1])<=3,'Reverse handoff '+c.name);
    handoffs++;
   }
   assert.equal(handoffs,sections.cards.length-1,'Every non-footer chapter including FAQ is a predecessor');
   await p.evaluate(()=>scrollTo({top:document.scrollingElement.scrollHeight,behavior:'instant'}));
   const footer=await p.evaluate(()=>{
    const f=document.querySelector('.site-footer'),r=f.getBoundingClientRect();
    const channel=x=>{x/=255;return x<=.04045?x/12.92:((x+.055)/1.055)**2.4};
    const lum=s=>{const c=s.match(/[\d.]+/g).slice(0,3).map(Number).map(channel);return .2126*c[0]+.7152*c[1]+.0722*c[2]};
    const bg=lum(getComputedStyle(f).backgroundColor);
    const ratios=[...f.querySelectorAll('.footer-desc,.footer-links a,.footer-bottom,.footer-bottom a')].map(e=>{const fg=lum(getComputedStyle(e).color);return (Math.max(fg,bg)+.05)/(Math.min(fg,bg)+.05)});
    const h=document.querySelector('#site-header').getBoundingClientRect();
    const link=f.querySelector('.footer-bottom a'),a=link.getBoundingClientRect();
    return {minContrast:Math.min(...ratios),bottom:r.bottom,top:r.top,headerTop:h.top,headerBottom:h.bottom,linkHit:link.contains(document.elementFromPoint(a.x+a.width/2,a.y+a.height/2)),overflow:document.documentElement.scrollWidth};
   });
   assert.ok(footer.minContrast>=4.5,'Footer readable contrast: '+footer.minContrast);
   assert.ok(Math.abs(footer.headerTop)<=2&&Math.abs(footer.bottom-height)<=3,'Footer ends at viewport with header pinned');
   assert.ok(footer.top<=footer.headerBottom+3,'No blank preceding strip in final resting frame');
   assert.ok(footer.linkHit,'Footer legal link remains clickable');
   assert.ok(footer.overflow<=width+1,'No horizontal overflow');
   await p.screenshot({path:path.join(os.tmpdir(),'bapx-home-release-'+(live?'live':'local')+'-'+width+'-'+height+'-footer.png')});
   if(width===375){
    const menu=header.getByRole('button',{name:'Menu',exact:true});
    const order=await header.evaluate(e=>{const x=s=>e.querySelector(s).getBoundingClientRect().left;return [x('.menu-toggle'),x('.brand'),x('.cart-btn')]});
    assert.ok(order[0]<order[1]&&order[1]<order[2],'Menu left, brand center, cart right');
    await menu.click();assert.equal(await menu.getAttribute('aria-expanded'),'true');
    assert.ok(await header.locator('#primary-nav').isVisible());
    await menu.click();assert.equal(await menu.getAttribute('aria-expanded'),'false');
   }
   await p.emulateMedia({reducedMotion:'reduce'});
   await p.evaluate(()=>scrollTo({top:document.scrollingElement.scrollHeight,behavior:'instant'}));
   assert.equal(await p.locator('.home-faq').evaluate(e=>getComputedStyle(e).position),'relative');
   assert.ok(Math.abs(await header.evaluate(e=>e.getBoundingClientRect().top))<=2);
   await p.evaluate(()=>scrollTo({top:0,behavior:'instant'}));
   assert.ok(await header.locator('.brand img').isVisible());
   assert.deepEqual(errors,[]);
   console.log(JSON.stringify({mode:live?'live':'local',sha:expected||null,width,height,handoffs,footerContrast:Number(footer.minContrast.toFixed(2)),headerPinned:true,footerLinkHit:true,errors:0}));
   await context.close();
  }
  const context=await browser.newContext({viewport:{width:375,height:812},javaScriptEnabled:false,serviceWorkers:'block'});
  if(!live)await context.route('https://**/*',r=>r.abort());
  const p=await context.newPage();await p.goto(origin+'/');
  assert.equal(await p.locator('.site-footer').count(),1);
  await verifyCredit(p);
  assert.ok(await p.locator('.site-footer .footer-bottom a[href="/privacy"]').count());
  assert.equal(await p.locator('#site-header').evaluate(e=>getComputedStyle(e).position),'sticky');
  await p.getByRole('link',{name:'Menu',exact:true}).click();
  assert.ok(await p.locator('#primary-nav').isVisible());
  await p.getByRole('link',{name:'Close menu',exact:true}).click();
  assert.equal(await p.locator('#primary-nav').isVisible(),false);
  await context.close();
  if(live)await verifyLiveRevision(origin,expected);
 }finally{await browser?.close();server?.kill();if(temporary)rmSync(temporary,{recursive:true,force:true});}
});
