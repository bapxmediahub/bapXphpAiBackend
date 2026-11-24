'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {readFileSync}=require('node:fs');
const path=require('node:path');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'..'), origin='http://page-draft.example.test';
const rev='a'.repeat(32), newer='b'.repeat(32);
function render(revision,owner,saved=''){
    return execFileSync(process.env.PHP_BINARY||'php',['-r',
        "putenv('BAPX_TEST_MODE=1'); require 'app/bootstrap.php'; $csrf='fixture'; $pages=[]; "+
        "$pageDraftOwner='"+owner+"'; $savedPageDraftToken='"+saved+"'; "+
        "$form=['original_slug'=>'guide','slug'=>'guide','title'=>'Original','content'=>'Original text','status'=>'draft','expected_revision'=>'"+revision+"']; require 'views/admin/pages.php';"
    ],{cwd:root,encoding:'utf8'});
}
for(const width of [375,1280]) test('authored page draft reload, isolation and stale recovery at '+width+'px',async()=>{
    const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox']});
    const context=await browser.newContext({viewport:{width,height:850}});
    const page=await context.newPage();
    let revision=rev,owner='fixture-owner',saved='',posts=0;
    try{
        await context.route('**/*',route=>{
            const req=route.request(),u=new URL(req.url());
            assert.equal(u.origin,origin);
            if(u.pathname==='/assets/page-draft.js')return route.fulfill({contentType:'application/javascript',body:readFileSync(path.join(root,'assets/page-draft.js'))});
            if(u.pathname==='/assets/css/band.css')return route.fulfill({contentType:'text/css',body:readFileSync(path.join(root,'assets/css/band.css'))});
            if(u.pathname==='/admin/pages/save'){
                posts++;
                const fields=Object.fromEntries(new URLSearchParams(req.postData()));
                assert.equal(fields.expected_revision,revision);
                assert.equal(fields._csrf,'fixture');
                assert.equal(fields.action,'save');
                saved=fields.browser_draft_token;
                assert.match(saved,/^[a-f0-9]{32}$/);
                return route.fulfill({status:303,headers:{location:origin+'/admin/pages?edit=guide'}});
            }
            if(u.pathname==='/admin/pages')return route.fulfill({contentType:'text/html',body:'<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/css/band.css"></head><body><main class="admin-body">'+render(revision,owner,saved)+'</main></body></html>'});
            return route.fulfill({status:404,body:''});
        });
        await page.goto(origin+'/admin/pages?edit=guide');
        await page.getByLabel('Title').fill('Unsaved title');
        await page.getByLabel('Page content').fill('Unsaved Markdown');
        await page.getByLabel('Visibility').selectOption('published');
        await page.getByText('Draft saved in this browser.',{exact:false}).waitFor({timeout:3000});
        assert.equal(posts,0,'Autosave must not publish or write to server');
        await page.reload();
        await page.getByRole('button',{name:'Restore draft'}).click();
        assert.equal(await page.getByLabel('Title').inputValue(),'Unsaved title');
        assert.equal(await page.getByLabel('Page content').inputValue(),'Unsaved Markdown');
        assert.equal(await page.getByLabel('Visibility').inputValue(),'draft');
        assert.equal(await page.locator('[name=expected_revision]').first().inputValue(),rev);
        const other=await context.newPage();
        await other.goto(origin+'/admin/pages?edit=guide');
        await other.getByRole('button',{name:'Restore draft'}).click();
        await other.getByLabel('Title').fill('Other tab title');
        await other.getByText('Draft saved in this browser.',{exact:false}).waitFor();
        await page.getByLabel('Title').fill('Conflicting tab title');
        await page.getByText('Another tab changed this browser draft.',{exact:false}).waitFor();
        await other.close();
        owner='another-owner';
        await page.reload();
        assert.equal(await page.getByRole('button',{name:'Restore draft'}).isVisible(),false);
        owner='fixture-owner';revision=newer;
        await page.reload();
        await page.getByText('This page changed since the browser draft.',{exact:false}).waitFor();
        assert.equal(await page.getByRole('button',{name:'Restore draft'}).isVisible(),false);
        const download=page.waitForEvent('download');
        await page.getByRole('button',{name:'Download browser draft'}).click();
        assert.equal((await download).suggestedFilename(),'page-draft.md');
        await page.getByRole('button',{name:'Discard browser copy'}).click();
        await page.getByLabel('Title').fill('Final title');
        await page.getByLabel('Page content').fill('Final Markdown');
        await page.getByText('Draft saved in this browser.',{exact:false}).waitFor();
        assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
        await page.getByRole('button',{name:'Save page'}).click();
        await page.waitForURL('**/admin/pages?edit=guide');
        assert.equal(posts,1);
        assert.equal(await page.getByRole('button',{name:'Restore draft'}).isVisible(),false);
    } finally {await browser.close();}
});
