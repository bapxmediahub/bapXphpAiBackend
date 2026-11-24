'use strict';
const assert = require('node:assert/strict');
const {spawn} = require('node:child_process');
const {once} = require('node:events');
const net = require('node:net');
const path = require('node:path');
const {test} = require('node:test');
const {chromium} = require('playwright');

async function freePort() {
    const socket=net.createServer();
    socket.listen(0,'127.0.0.1');
    await once(socket,'listening');
    const port=socket.address().port;
    await new Promise(resolve=>socket.close(resolve));
    return port;
}

test('restored mobile brand, header cart, no bottom nav, and post-add floating cart', {timeout:65000}, async()=>{
    const root=path.resolve(__dirname,'..');
    const port=await freePort();
    const origin='http://127.0.0.1:'+port;
    const server=spawn('php',['-S','127.0.0.1:'+port,'index.php'],{
        cwd:root,env:{...process.env,BAPX_TEST_MODE:'1',APP_URL:'https://sripanchamispiritual.com'},stdio:'ignore'
    });
    let browser;
    try {
        browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true});
        const context=await browser.newContext({viewport:{width:375,height:812},serviceWorkers:'block'});
        await context.route('https://**/*',route=>route.abort());
        const page=await context.newPage();
        const errors=[];
        page.on('pageerror',error=>errors.push(error.message));
        for(let attempt=0;attempt<30;attempt++){
            try{await page.goto(origin+'/shop',{waitUntil:'domcontentloaded',timeout:2000});break;}
            catch(error){if(attempt===29)throw error;await new Promise(resolve=>setTimeout(resolve,100));}
        }
        assert.equal(new URL(page.url()).pathname,'/shop');
        for(const width of [320,375,390,744,860,1280]){
            await page.setViewportSize({width,height:812});
            const data=await page.evaluate(()=>{
                const rect=selector=>{
                    const el=document.querySelector(selector);
                    if(!el)return null;
                    const b=el.getBoundingClientRect();
                    return {left:b.left,right:b.right,top:b.top,bottom:b.bottom,
                        width:b.width,height:b.height,
                        display:getComputedStyle(el).display,
                        visibility:getComputedStyle(el).visibility};
                };
                return {scrollWidth:document.documentElement.scrollWidth,
                    brand:rect('.site-header .brand span'),
                    cart:rect('.site-header .cart-btn'),
                    menu:rect('.site-header .menu-toggle'),
                    bottomNav:document.querySelector('#bottom-nav'),
                    trayHidden:document.querySelector('#mobile-cart-tray')?.hidden,
                    headerText:document.querySelector('.site-header .brand span')?.textContent.trim()};
            });
            assert.equal(data.bottomNav,null,'No unrequested mobile bottom navigation');
            assert.equal(data.headerText,'Sri Panchami Spiritual');
            assert.ok(data.brand && data.brand.display!=='none' && data.brand.width>35,
                'Brand text must be visible at '+width);
            assert.ok(data.cart && data.cart.display!=='none' && data.cart.width>=(width<=860?42:34)
                && data.cart.right<=width+1 && data.cart.left>=-1,
                'Top cart touch target must remain inside '+width+'px viewport');
            assert.ok(data.scrollWidth<=width+2,'Avoid horizontal header overflow at '+width);
            if(width<=860){
                assert.ok(data.menu && data.menu.width>=44 && data.menu.right<=width+1,
                    'Menu accessible at '+width);
            }
        }
        await page.setViewportSize({width:375,height:812});
        const tray=page.locator('#mobile-cart-tray');
        assert.equal(await tray.count(),1);
        assert.equal(await tray.isVisible(),false,'Empty cart must not show floating cart tray');
        // Inject a synthetic product and intercept only its cart call. Production
        // order, payment, customer and MySQL state are not touched by this test.
        await page.evaluate(()=>{
            const node=document.createElement('div');
            node.id='synthetic-cart-product';
            node.innerHTML='<div data-cart-control data-slug="synthetic-cart-product">'+
                '<form data-cart-add action="/cart/add" method="post"><button type="submit">Add to cart</button></form>'+
                '<form data-cart-stepper hidden action="/cart/update" method="post">'+
                '<button type="submit">Increase</button><span data-cart-quantity>0</span></form>'+
                '</div>';
            document.body.appendChild(node);
            window.fetch=async()=>({ok:false,status:429,json:async()=>{throw new SyntaxError('Empty WAF response');}});
            node.querySelector('form[data-cart-add]').requestSubmit();
        });
        await page.waitForFunction(()=>document.querySelector('.toast--error .toast__text')
            ?.textContent.includes('temporarily limited'),null,{timeout:5000});
        assert.equal(await tray.isVisible(),false,'429 must not falsely open the cart');
        assert.equal(await page.locator('.cart-count').innerText(),'0');
        assert.equal(await page.locator('#synthetic-cart-product form[data-cart-add] button').isDisabled(),false,
            'Rate-limited add must re-enable the cart button');
        await page.evaluate(()=>{
            window.fetch=async()=>({ok:false,status:422,json:async()=>({
                error:'<img src=x onerror=alert(1)>'
            })});
            document.querySelector('#synthetic-cart-product form[data-cart-add]').requestSubmit();
        });
        await page.waitForFunction(()=>[...document.querySelectorAll('.toast--error .toast__text')]
            .some(node=>node.textContent.includes('<img src=x')),null,{timeout:5000});
        assert.equal(await page.locator('.toast--error .toast__text img').count(),0,
            'Untrusted server error must never be inserted as markup');
        assert.equal(await tray.isVisible(),false,'Rejected response cannot reveal floating cart');
        await page.evaluate(()=>{
            window.fetch=async()=>({ok:true,status:200,json:async()=>({
                slug:'synthetic-cart-product',quantity:1,cart_count:1
            })});
            document.querySelector('#synthetic-cart-product form[data-cart-add]').requestSubmit();
        });
        await page.waitForFunction(()=>{
            const tray=document.getElementById('mobile-cart-tray');
            return tray && !tray.hidden && document.querySelector('.cart-count')?.textContent==='1';
        },null,{timeout:5000});
        assert.equal(await tray.getAttribute('href'),'/cart');
        assert.equal(await page.locator('#mobile-cart-count').innerText(),'1');
        const positions=await page.evaluate(()=>{
            const tray=document.getElementById('mobile-cart-tray').getBoundingClientRect();
            const support=document.querySelector('.support-fab')?.getBoundingClientRect();
            const hit=document.elementFromPoint(tray.left+tray.width/2,tray.top+tray.height/2);
            return {tray:{left:tray.left,right:tray.right,top:tray.top,bottom:tray.bottom,height:tray.height},
                support:support?{left:support.left,right:support.right,top:support.top,bottom:support.bottom}:null,
                hit:hit?.closest('#mobile-cart-tray')!==null};
        });
        assert.ok(positions.tray.height>=44 && positions.tray.left>=0
            && positions.tray.right<=375 && positions.tray.bottom<=812,
            'Floating cart visible within mobile viewport');
        assert.equal(positions.hit,true,'Floating cart must not be covered by another element');
        if(positions.support){
            assert.ok(positions.tray.right <= positions.support.left
                || positions.tray.bottom<=positions.support.top,
                'Cart tray and support button do not overlap');
        }
        await tray.click({trial:true});
        assert.deepEqual(errors,[],'No uncaught JavaScript exceptions');
        await context.close();
        console.log('Mobile header 320/375/390/744/860/1280, no bottom nav, synthetic add-to-cart, live header badge and floating cart hitbox passed.');
    }finally{
        if(browser)await browser.close();
        server.kill();
    }
});