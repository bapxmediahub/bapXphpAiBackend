'use strict';
const assert = require('node:assert/strict');
const {test} = require('node:test');
const {chromium} = require('playwright');

// Runs only after the live SHA check passes. Uses a fresh, unauthenticated
// browser session; never signs in, starts checkout, pays or stores customer data.
test('Hostinger mobile brand and real add/remove cart remain functional', {timeout:70000}, async()=>{
    const origin = 'https://sripanchamispiritual.com';
    const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH || undefined,headless:true});
    const context=await browser.newContext({viewport:{width:375,height:812},serviceWorkers:'block'});
    let added=false;
    const page=await context.newPage();
    const errors=[];
    const cartResponses=[];
    page.on('pageerror',error=>errors.push(error.message));
    page.on('requestfailed',req=>{
        if(/\/cart\/(add|update|remove)/.test(new URL(req.url()).pathname)) {
            console.log('Cart network failed', req.failure()?.errorText || 'unknown');
        }
    });
    page.on('response',async response=>{
        if(!/\/cart\/(add|update|remove)/.test(new URL(response.url()).pathname))return;
        let payload=null;
        try{
            const raw=await response.text();
            const json=JSON.parse(raw);
            payload={keys:Object.keys(json),quantity:json.quantity,cart_count:json.cart_count,
                error:typeof json.error==='string'?json.error.slice(0,180):undefined};
        }catch{}
        const row={method:response.request().method(),status:response.status(),
            type:response.headers()['content-type']?.split(';')[0]||null,
            payload};
        cartResponses.push(row);
        console.log('CART_HTTP',JSON.stringify(row));
    });
    try {
        const response=await page.goto(origin+'/shop',{waitUntil:'domcontentloaded',timeout:25000});
        assert.equal(response?.status(),200,'Storefront must respond HTTP 200');
        for(const width of [320,375,390]){
            await page.setViewportSize({width,height:812});
            const current=await page.evaluate(()=>{
                const rect=selector=>{
                    const e=document.querySelector(selector);
                    if(!e)return null;
                    const r=e.getBoundingClientRect();
                    return {left:r.left,right:r.right,width:r.width,display:getComputedStyle(e).display};
                };
                return {width:document.documentElement.scrollWidth,brand:rect('.site-header .brand span'),
                    cart:rect('.site-header .cart-btn'),menu:rect('.site-header .menu-toggle'),
                    nav:!!document.querySelector('#bottom-nav'),
                    title:document.querySelector('.site-header .brand span')?.textContent.trim()};
            });
            assert.equal(current.nav,false,'Unexpected fixed bottom navigation at '+width);
            assert.equal(current.title,'Sri Panchami Spiritual');
            assert.ok(current.brand && current.brand.display!=='none' && current.brand.width>25,
                'Missing mobile brand at '+width);
            assert.ok(current.cart && current.cart.width>=42 && current.cart.left>=0 && current.cart.right<=width+1,
                'Missing or clipped mobile top cart at '+width);
            assert.ok(current.menu && current.menu.width>=44 && current.menu.right<=width+1,
                'Mobile menu must stay tappable at '+width);
            assert.ok(current.width<=width+2,'Horizontal viewport overflow at '+width);
        }
        console.log('LIVE_MOBILE_HEADER_PASS: brand, top cart, menu and absent bottom navigation at 320/375/390px');
        await page.setViewportSize({width:375,height:812});
        const tray=page.locator('#mobile-cart-tray');
        assert.equal(await tray.count(),1);
        assert.equal(await tray.isVisible(),false,'Empty session must not show floating cart');
        const add=page.locator('.shop-results form[data-cart-add]:not([hidden]) button').first();
        assert.ok(await add.count()===1,'Public shop needs at least one addable product');
        const addResponsePromise=page.waitForResponse(response=>
            new URL(response.url()).pathname==='/cart/add'
            && response.request().method()==='POST',{timeout:15000});
        await add.click();
        const addResponse=await addResponsePromise;
        if(addResponse.status()===429) {
            console.log('LIVE_CART_BACKEND_BLOCKED: POST /cart/add HTTP 429 (rate-limited upstream; not a successful cart addition).');
            throw new Error('Real guest cart acceptance blocked by Hostinger/upstream HTTP 429 rate limiting; inspect host access logs and WAF policy.');
        }
        if(!addResponse.ok()){
            throw new Error('Real guest cart POST failed with HTTP '+addResponse.status());
        }
        added=true;
        try {
            await page.waitForFunction(()=>document.querySelector('.cart-count')?.textContent.trim()==='1'
                && !document.getElementById('mobile-cart-tray')?.hidden,null,{timeout:12000});
        } catch(error) {
            const state=await page.evaluate(()=>({
                url:location.pathname,
                badge:document.querySelector('.cart-count')?.textContent?.trim(),
                trayHidden:document.getElementById('mobile-cart-tray')?.hidden,
                toast:document.querySelector('.toast--error .toast__text')?.textContent?.slice(0,180),
                addHidden:document.querySelector('.shop-results form[data-cart-add]')?.hidden,
                stepperHidden:document.querySelector('.shop-results [data-cart-stepper]')?.hidden,
            }));
            console.log('CART_UI_AFTER_CLICK',JSON.stringify(state));
            console.log('CART_HTTP_SUMMARY',JSON.stringify(cartResponses));
            throw error;
        }
        assert.equal(await tray.getAttribute('href'),'/cart');
        const target=await page.evaluate(()=>{
            const e=document.getElementById('mobile-cart-tray');
            const r=e.getBoundingClientRect();
            const center=document.elementFromPoint(r.left+r.width/2,r.top+r.height/2);
            return {left:r.left,right:r.right,top:r.top,bottom:r.bottom,
                visible:!!center?.closest('#mobile-cart-tray')};
        });
        assert.ok(target.visible&&target.left>=0&&target.right<=375&&target.bottom<=812,
            'Floating cart button must be visible and unobstructed');
        // Delete only the item added to this isolated browser session.
        const dec=page.locator('.shop-results [data-cart-stepper]:not([hidden]) form[data-cart-change]:has(input[value="dec"]) button').first();
        await dec.click();
        await page.waitForFunction(()=>document.querySelector('.cart-count')?.textContent.trim()==='0'
            && document.getElementById('mobile-cart-tray')?.hidden===true,null,{timeout:12000});
        added=false;
        assert.deepEqual(errors,[],'No uncaught mobile JavaScript exceptions');
        console.log('LIVE 320/375/390 brand, top cart and no bottom bar; real isolated add/remove; floating cart hit target PASS.');
    } finally {
        if(added){
            // Avoid leaving a product behind after an assertion failure.
            try {
                const dec=page.locator('.shop-results [data-cart-stepper]:not([hidden]) form[data-cart-change]:has(input[value="dec"]) button').first();
                if(await dec.count())await dec.click({timeout:5000});
            }catch{}
        }
        await context.close();
        await browser.close();
    }
});
