'use strict';
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {readFileSync}=require('node:fs');
const {test}=require('node:test');
const path=require('node:path');
const {chromium}=require('playwright');
const root=path.join(__dirname,'..');
const fixtures=JSON.parse(execFileSync('php',['tests/fixtures/remote-cart-review.php'],{cwd:root,encoding:'utf8'}));
const css=readFileSync(path.join(root,'assets/css/band.css'),'utf8');
const pixel=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aPpQAAAAASUVORK5CYII=','base64');

test('checkout review keeps explicit replacement usable at desktop and375px with no scripts or external requests',{timeout:60000},async()=>{
    const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||undefined,args:['--no-sandbox']});
    try{
        for(const width of[1280,375])for(const[state,html]of Object.entries(fixtures)){
            const context=await browser.newContext({viewport:{width,height:900},javaScriptEnabled:false});
            const page=await context.newPage();const errors=[];const submissions=[];
            page.on('pageerror',error=>errors.push(error.message));
            await context.route('**/*',async route=>{
                const request=route.request();const url=new URL(request.url());
                assert.equal(url.origin,'https://cart.example.test','Review requested a third-party destination');
                if(url.pathname==='/assets/css/band.css')return route.fulfill({contentType:'text/css',body:css});
                if(url.pathname.startsWith('/assets/images/'))return route.fulfill({contentType:'image/png',body:pixel});
                if(request.method()==='POST'){
                    assert.equal(url.pathname,'/cart/agent-checkout');
                    submissions.push(Object.fromEntries(new URLSearchParams(request.postData())));
                    return route.fulfill({contentType:'text/html',body:'<!doctype html><title>Checkout handoff</title><h1>Continue at checkout</h1>'});
                }
                return route.fulfill({contentType:'text/html',headers:{'Referrer-Policy':'no-referrer','Cache-Control':'no-store'},body:html});
            });
            await page.goto('https://cart.example.test/cart/agent-checkout?ticket=fixture');
            await page.getByRole('heading',{name:'Review your cart',exact:true}).waitFor();
            const dimensions=await page.evaluate(()=>({width:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth}));
            assert.ok(dimensions.scroll<=dimensions.width+1,`${state} overflowed at${width}px`);
            assert.equal(await page.locator('script').count(),0);
            assert.equal(await page.locator('[href^="https://"]').count(),0);
            if(['ready','changed','recovery'].includes(state)){
                const button=page.getByRole('button',{name:'Use this cart',exact:true});
                await button.click();assert.equal(submissions.length,0,'Unchecked replacement submitted');
                const checkbox=page.getByRole('checkbox',{name:'Replace my current cart with these selections.'});
                await checkbox.focus();await page.keyboard.press('Space');await button.focus();await page.keyboard.press('Enter');
                await page.getByRole('heading',{name:'Continue at checkout'}).waitFor();
                assert.equal(submissions.length,1);assert.equal(submissions[0].confirm,'replace');
                assert.equal(submissions[0]._csrf,'fixture-csrf-token');
                assert.match(submissions[0].ticket,/^checkout_[a-f0-9]{32}\.[a-f0-9]{64}$/);
                assert.match(submissions[0].quote,/^[a-f0-9]{64}$/);
            }else{
                assert.equal(await page.getByRole('button',{name:'Use this cart',exact:true}).count(),0);
                if(state==='imported')assert.equal(await page.getByRole('link',{name:'Continue to checkout'}).getAttribute('href'),'/checkout');
                if(state==='expired')await page.getByRole('heading',{name:'Review link unavailable'}).waitFor();
            }
            assert.deepEqual(errors,[]);await context.close();
        }
    }finally{await browser.close();}
});
