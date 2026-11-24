'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const {spawn}=require('node:child_process');
const {once}=require('node:events');
const net=require('node:net');
const os=require('node:os');
const path=require('node:path');
const fs=require('node:fs');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'..');

async function freePort(){
    const server=net.createServer();server.listen(0,'127.0.0.1');await once(server,'listening');
    const port=server.address().port;await new Promise(resolve=>server.close(resolve));return port;
}
test('real contact route: server validation, saved receipt, bounded admission, no external mail in fixture',{timeout:90000},async()=>{
    const port=await freePort(),origin='http://127.0.0.1:'+port,tmp=fs.mkdtempSync(path.join(os.tmpdir(),'bapx-contact-browser-'));
    const state=path.join(root,'storage/logs/contact-admission.json');
    // Never overwrite a deployment's or another test's admission state.
    if(fs.existsSync(state))throw Error('Use an isolated clean checkout for contact browser acceptance');
    const server=spawn('php',['-d','session.save_path='+tmp,'-S','127.0.0.1:'+port,'index.php'],
        {cwd:root,env:{...process.env,BAPX_TEST_MODE:'1',APP_URL:origin},stdio:'ignore'});
    let browser;
    try{
        browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true});
        for(const width of [1280,375]){
            const ctx=await browser.newContext({viewport:{width,height:850},serviceWorkers:'block'});
            await ctx.route('https://**/*',r=>r.abort());
            const page=await ctx.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
            for(let i=0;i<30;i++){try{await page.goto(origin+'/contact',{waitUntil:'domcontentloaded',timeout:2000});break;}catch(e){if(i===29)throw e;await new Promise(r=>setTimeout(r,100));}}
            assert.ok(await page.locator('form.contact-form').isVisible());
            assert.equal(await page.locator('form.contact-form').getAttribute('toolautosubmit'),null);
            assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
            const csrf=await page.locator('input[name="_csrf"]').first().inputValue();
            const email='fixture'+width+'@example.invalid';
            const fields={_csrf:csrf,name:'Fixture Customer',email,subject:'general',phone:'',message:'ABCDEF'};
            // Bypass browser minlength to prove the server rejects the observed flood shape.
            const invalid=await ctx.request.post(origin+'/contact',{form:fields,maxRedirects:0});
            assert.equal(invalid.status(),422);
            assert.match(await invalid.text(),/at least 12 characters/);
            await page.locator('input[name="name"]').fill(fields.name);
            await page.locator('input[name="email"]').fill(email);
            await page.locator('select[name="subject"]').selectOption('general');
            await page.locator('textarea[name="message"]').fill('Could you provide more details about this product?');
            await page.getByRole('button',{name:'Send message',exact:true}).click();
            await page.getByRole('heading',{name:'Message received'}).waitFor();
            assert.match(await page.locator('#contact-form').innerText(),/An automatic confirmation email is not sent/);
            assert.deepEqual(errors,[]);
            await ctx.close();
        }
        // Two accepted fixture submissions above; a third is allowed, fourth is rejected for this IP.
        const c=await browser.newContext();const p=await c.newPage();await p.goto(origin+'/contact');
        const csrf=await p.locator('input[name="_csrf"]').first().inputValue();
        const fields={_csrf:csrf,name:'Fixture',email:'third@example.invalid',subject:'general',phone:'',message:'A valid local enquiry only'};
        assert.equal((await c.request.post(origin+'/contact',{form:fields,maxRedirects:0})).status(),302);
        fields.email='fourth@example.invalid';
        const limited=await c.request.post(origin+'/contact',{form:fields,maxRedirects:0});
        assert.equal(limited.status(),429);assert.match(await limited.text(),/enquiry limit has been reached/);
        await c.close();
        console.log('Contact real-route fixture: desktop and 375px receipt, malformed POST 422, IP cap 429, no SMTP transport.');
    }finally{
        await browser?.close();server.kill();await once(server,'exit').catch(()=>{});
        fs.rmSync(tmp,{recursive:true,force:true});fs.rmSync(state,{force:true});fs.rmSync(state+'.lock',{force:true});
    }
});
