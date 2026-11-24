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

async function freePort(){
 const socket=net.createServer();
 socket.listen(0,'127.0.0.1');await once(socket,'listening');
 const port=socket.address().port;await new Promise(resolve=>socket.close(resolve));return port;
}
const expected=process.env.EXPECTED_SHA||'';
const live=Boolean(expected);
if(live&&!/^[a-f0-9]{40}$/.test(expected))throw Error('Expected exact deployed Git SHA');

test('actual public layout: pinned brand, complete editorial sequence, readable footer and navigation',{timeout:120000},async()=>{
 const root=path.resolve(__dirname,'..');
 const temporary=live?null:mkdtempSync(path.join(os.tmpdir(),'bapx-home-release-'));
 const port=live?null:await freePort();
 const origin=live?'https://sripanchamispiritual.com':'http://127.0.0.1:'+port;
 const server=live?null:spawn('php',['-d','session.save_path='+temporary,'-S','127.0.0.1:'+port,'index.php'],{cwd:root,env:{...process.env,BAPX_TEST_MODE:'1',APP_URL:origin},stdio:'ignore'});
 let browser;
 try{
  browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true});
  for(const [width,height] of [[1536,850],[1280,900],[375,812],[375,580]]){
   const context=await browser.newContext({viewport:{width,height},serviceWorkers:'block'});
   if(!live)await context.route('https://**/*',r=>r.abort());
   const p=await context.newPage();const errors=[];p.on('pageerror',e=>errors.push(e.message));
   if(live){const r=await p.request.get(origin+'/is-sha-valid?sha='+expected);assert.equal(r.status(),200);const d=await r.json();assert.equal(d.match,true);assert.equal(d.deployed_sha,expected);assert.equal(d.source,'git_head');}
   for(let i=0;i<20;i++){try{const r=await p.goto(origin+'/',{waitUntil:'domcontentloaded',timeout:live?25000:2000});assert.equal(r.status(),200);break;}catch(e){if(i===19||live)throw e;await new Promise(r=>setTimeout(r,100));}}
   await p.locator('[data-home-stack][data-stack-ready]').waitFor({timeout:10000});
   await p.evaluate(()=>document.fonts.ready);
   const header=p.locator('#site-header');
   assert.equal(await header.locator('.brand span').innerText(),'Sri Panchami Spiritual');
   assert.ok(await header.locator('.brand img').isVisible());
   assert.equal(await p.locator('.site-footer').count(),1);
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
  assert.ok(await p.locator('.site-footer .footer-bottom a[href="/privacy"]').count());
  assert.equal(await p.locator('#site-header').evaluate(e=>getComputedStyle(e).position),'sticky');
  await p.getByRole('link',{name:'Menu',exact:true}).click();
  assert.ok(await p.locator('#primary-nav').isVisible());
  await p.getByRole('link',{name:'Close menu',exact:true}).click();
  assert.equal(await p.locator('#primary-nav').isVisible(),false);
  await context.close();
 }finally{await browser?.close();server?.kill();if(temporary)rmSync(temporary,{recursive:true,force:true});}
});
