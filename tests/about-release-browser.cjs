'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const {spawn}=require('node:child_process');
const {once}=require('node:events');
const net=require('node:net');
const os=require('node:os');
const path=require('node:path');
const {mkdtempSync,rmSync}=require('node:fs');
const {chromium}=require('playwright');

const expected=process.env.EXPECTED_SHA||'';
if(expected&&!/^[a-f0-9]{40}$/.test(expected))throw Error('Expected exact deployed Git SHA');
async function freePort(){
    const server=net.createServer();server.listen(0,'127.0.0.1');await once(server,'listening');
    const port=server.address().port;await new Promise(resolve=>server.close(resolve));return port;
}
test('real About route: editorial layout, image loading, canonical metadata and native enquiry navigation',{timeout:120000},async()=>{
    const live=Boolean(expected),root=path.resolve(__dirname,'..'),port=live?null:await freePort();
    const origin=live?'https://sripanchamispiritual.com':'http://127.0.0.1:'+port;
    const tmp=live?null:mkdtempSync(path.join(os.tmpdir(),'bapx-about-release-'));
    const server=live?null:spawn('php',['-d','session.save_path='+tmp,'-S','127.0.0.1:'+port,'index.php'],{cwd:root,env:{...process.env,BAPX_TEST_MODE:'1',APP_URL:origin},stdio:'ignore'});
    let browser;
    try{
        browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true});
        for(const [width,height,js] of [[1280,900,true],[375,812,true],[375,580,true],[375,812,false]]){
            const context=await browser.newContext({viewport:{width,height},javaScriptEnabled:js,reducedMotion:'reduce',serviceWorkers:'block'});
            if(!live)await context.route('https://**/*',r=>r.abort());
            const p=await context.newPage(),errors=[];p.on('pageerror',e=>errors.push(e.message));
            if(live){const r=await p.request.get(origin+'/is-sha-valid?sha='+expected);assert.equal(r.status(),200);const d=await r.json();assert.equal(d.match,true);assert.equal(d.deployed_sha,expected);assert.equal(d.source,'git_head');}
            for(let i=0;i<20;i++){try{const r=await p.goto(origin+'/about',{waitUntil:'domcontentloaded',timeout:live?25000:2000});assert.equal(r.status(),200);break;}catch(e){if(i===19||live)throw e;await new Promise(r=>setTimeout(r,100));}}
            const page=p.locator('[data-about-editorial]');
            await page.waitFor();
            assert.equal(await page.locator('h1').count(),1);
            assert.ok(await page.locator('h1').isVisible());
            await p.evaluate(()=>document.fonts.ready);
            const metadata=await p.evaluate(()=>({
                canonical:document.querySelector('link[rel=canonical]').href,
                schemas:[...document.querySelectorAll('script[type="application/ld+json"]')].map(e=>JSON.parse(e.textContent)).filter(x=>x['@type']==='AboutPage'),
                title:document.title,
            }));
            assert.equal(new URL(metadata.canonical).pathname,'/about');
            assert.equal(metadata.schemas.length,1);
            assert.equal(metadata.schemas[0].url,metadata.canonical);
            assert.ok(metadata.title.startsWith('About '));
            const columns=await page.locator('.about-hero__inner').evaluate(e=>getComputedStyle(e).gridTemplateColumns.trim().split(/\s+/).length);
            assert.equal(columns,width>743?2:1);
            const photos=page.locator('img');
            assert.equal(await photos.count(),2);
            await page.locator('.about-hero__link').click();
            assert.equal(new URL(p.url()).hash,'#our-story');
            for(const img of await photos.all()){
                await img.scrollIntoViewIfNeeded();
                await img.evaluate(e=>e.decode());
                assert.ok(await img.evaluate(e=>e.complete&&e.naturalWidth>0&&e.alt.length>0));
            }
            const links=await page.locator('.about-pathway a').evaluateAll(els=>els.map(e=>new URL(e.href).pathname));
            assert.ok(links.includes('/temples')&&links.every(x=>['/shop','/temples','/blog'].includes(x)));
            assert.equal(await page.locator('a[href^="tel:"]').count(),2);
            assert.equal(await page.locator('a[href^="mailto:"]').count(),1);
            assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'No horizontal overflow');
            await p.evaluate(()=>scrollTo(0,0));
            await p.screenshot({path:path.join(os.tmpdir(),'bapx-about-'+(live?'live':'candidate')+'-'+width+'-'+height+'-'+js+'.png'),fullPage:true});
            await page.locator('a[href="/contact#contact-form"]').click();
            await p.waitForURL('**/contact#contact-form');
            assert.ok(await p.locator('#contact-form').isVisible(),'Real enquiry form reached without submitting');
            assert.deepEqual(errors,[]);
            console.log(JSON.stringify({mode:live?'live':'local',width,height,js,canonical:true,photos:2,links,contact:true,errors:0}));
            await context.close();
        }
    }finally{await browser?.close();server?.kill();if(tmp)rmSync(tmp,{recursive:true,force:true});}
});
