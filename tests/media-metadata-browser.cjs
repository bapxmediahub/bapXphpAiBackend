'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {readFileSync}=require('node:fs');
const path=require('node:path');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'..');
const origin='http://media-fixture.example.test';
const render=(saved=false)=>execFileSync(process.env.PHP_BINARY||'php',['-r',
    "putenv('BAPX_TEST_MODE=1'); require 'app/bootstrap.php'; $csrf='fixture-only'; "+
    "$items=[['id'=>'0123456789abcdef','url'=>'/assets/images/media/2099/01/fixture.webp','context'=>'shared','description'=>'"+(saved?'New description':'Before')+"','alt_text'=>'"+(saved?'Accessible fixture':'')+"','caption'=>'"+(saved?'New caption':'')+"','original_name'=>'Fixture'],['id'=>'file:/assets/images/products/hero.webp','source'=>'file','url'=>'/assets/images/products/hero.webp','context'=>'products','original_name'=>'Bundled']]; require 'views/admin/media.php';"
],{cwd:root,encoding:'utf8'});
const pageHtml=saved=>'<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/css/band.css"><link rel="stylesheet" href="/assets/css/admin-workspaces.css"></head><body><main class="admin-body">'+render(saved)+'</main></body></html>';
for(const width of [375,1280]) test('media metadata editor at '+width+'px',async()=>{
    const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox']});
    const page=await browser.newPage({viewport:{width,height:850}});
    const sent=[];
    try{
        await page.route('**/*',route=>{
            const req=route.request(),u=new URL(req.url());
            assert.equal(u.origin,origin);
            if(u.pathname==='/admin/media/metadata'){
                sent.push(Object.fromEntries(new URLSearchParams(req.postData())));
                return route.fulfill({contentType:'text/html',body:pageHtml(true)});
            }
            if(u.pathname==='/admin/media')return route.fulfill({contentType:'text/html',body:pageHtml(false)});
            if(['/assets/css/band.css','/assets/css/admin-workspaces.css'].includes(u.pathname))return route.fulfill({contentType:'text/css',body:readFileSync(path.join(root,u.pathname.slice(1)))});
            return route.fulfill({status:404,body:''});
        });
        await page.goto(origin+'/admin/media');
        assert.equal(await page.getByText('Edit metadata',{exact:true}).count(),1);
        await page.getByText('Edit metadata',{exact:true}).click();
        const form=page.locator('form[action="/admin/media/metadata"]');
        await form.getByLabel('Description',{exact:true}).fill('New description');
        await form.getByLabel('Alternative text').fill('Accessible fixture');
        await form.getByLabel('Caption').fill('New caption');
        assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
        await form.getByRole('button',{name:'Save metadata'}).click();
        assert.equal(sent.length,1);
        assert.equal(sent[0]._csrf,'fixture-only');
        assert.equal(sent[0].id,'0123456789abcdef');
        assert.match(sent[0].expected_revision,/^[a-f0-9]{64}$/);
        assert.equal(sent[0].alt_text,'Accessible fixture');
        assert.equal(await page.getByAltText('Accessible fixture').count(),1);
        assert.equal(await page.getByText('New caption',{exact:true}).count(),1);
    }finally{await browser.close();}
});
