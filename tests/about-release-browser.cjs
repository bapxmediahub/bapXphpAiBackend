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
const {verifyLiveRevision}=require('./live-browser-revision.cjs');

const expected=process.env.EXPECTED_SHA||'';
if(expected&&!/^[a-f0-9]{40}$/.test(expected))throw Error('Expected exact deployed Git SHA');
async function freePort(){
    const server=net.createServer();server.listen(0,'127.0.0.1');await once(server,'listening');
    const port=server.address().port;await new Promise(resolve=>server.close(resolve));return port;
}
function measureTextContrast(scope, selector) {
    return scope.evaluate((container, selector)=>{
        const luminance=color=>color.match(/[\d.]+/g).slice(0,3).map(Number).map(x=>{
            x/=255;
            return x<=.04045 ? x/12.92 : ((x+.055)/1.055)**2.4;
        }).reduce((sum,x,i)=>sum+x*[.2126,.7152,.0722][i],0);
        return [...container.querySelectorAll(selector)].map(element=>{
            let background=element;
            while (background && getComputedStyle(background).backgroundColor==='rgba(0, 0, 0, 0)') {
                background=background.parentElement;
            }
            const foregroundLuminance=luminance(getComputedStyle(element).color);
            const backgroundLuminance=luminance(getComputedStyle(background).backgroundColor);
            return {
                text:element.textContent.trim().slice(0,50),
                ratio:(Math.max(foregroundLuminance,backgroundLuminance)+.05)/(Math.min(foregroundLuminance,backgroundLuminance)+.05),
            };
        });
    },selector);
}

test('real About route: editorial layout, image loading, canonical metadata and native enquiry navigation',{timeout:120000},async()=>{
    const live=Boolean(expected),root=path.resolve(__dirname,'..'),port=live?null:await freePort();
    const origin=live?'https://sripanchamispiritual.com':'http://127.0.0.1:'+port;
    const tmp=live?null:mkdtempSync(path.join(os.tmpdir(),'bapx-about-release-'));
    const server=live?null:spawn('php',['-d','session.save_path='+tmp,'-S','127.0.0.1:'+port,'index.php'],{cwd:root,env:{...process.env,BAPX_TEST_MODE:'1',APP_URL:origin},stdio:'ignore'});
    let browser;
    try{
        if(live)await verifyLiveRevision(origin,expected);
        browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true});
        for(const [width,height,js] of [[1280,900,true],[375,812,true],[375,580,true],[375,812,false]]){
            const context=await browser.newContext({viewport:{width,height},javaScriptEnabled:js,reducedMotion:'reduce',serviceWorkers:'block'});
            if(!live)await context.route('https://**/*',r=>r.abort());
            const p=await context.newPage(),errors=[];p.on('pageerror',e=>errors.push(e.message));
            for(let i=0;i<20;i++){try{const r=await p.goto(origin+'/about',{waitUntil:'domcontentloaded',timeout:live?25000:2000});assert.equal(r.status(),200);break;}catch(e){if(i===19||live)throw e;await new Promise(r=>setTimeout(r,100));}}
            const page=p.locator('[data-about-editorial]');
            await page.waitFor();
            assert.equal(await page.locator('h1').count(),1);
            assert.ok(await page.locator('h1').isVisible());
            await p.waitForFunction(()=>getComputedStyle(document.querySelector('.about-hero__inner')).display==='grid',
                null,{timeout:8000});
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
            const contrasts=await measureTextContrast(page, ':is(.about-kicker,h1,h2,h3,.about-hero__lede,.about-hero__link,.about-pathway__number,.about-pathway p,.about-pathway a,.about-story__copy p,.about-story__figure figcaption,.about-contact__details a,.about-contact__details span,.page-cta-card__eyebrow)');
            assert.ok(contrasts.every(x=>x.ratio>=4.5),'Text contrast: '+JSON.stringify(contrasts.filter(x=>x.ratio<4.5)));
            const layout=await page.locator('.about-hero__inner').evaluate(element=>{
                const style=getComputedStyle(element);
                return {display:style.display,columns:style.gridTemplateColumns.trim().split(/\s+/).length};
            });
            assert.equal(layout.display,'grid','About editorial layout stylesheet applied');
            assert.equal(layout.columns,width>743?2:1);
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
            if (!live && !js) {
                await p.route('**/assets/css/about-editorial.css*',route=>route.abort());
                const fallbackPage=await p.goto(origin+'/about',{waitUntil:'load'});
                assert.equal(fallbackPage.status(),200);
                assert.notEqual(await page.locator('.about-hero__inner').evaluate(element=>getComputedStyle(element).display),
                    'grid','Page-specific CSS was blocked');
                const fallback=await measureTextContrast(page,'.page-cta-card__eyebrow');
                assert.equal(fallback.length,1,'Shared enquiry label still rendered');
                assert.ok(fallback[0].ratio>=4.5,'Shared label contrast without About CSS: '+JSON.stringify(fallback));
            }
            await context.close();
        }
        if(live)await verifyLiveRevision(origin,expected);
    }finally{await browser?.close();server?.kill();if(tmp)rmSync(tmp,{recursive:true,force:true});}
});
