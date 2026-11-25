'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {randomBytes}=require('node:crypto');
const {once}=require('node:events');
const {createServer}=require('node:http');
const {setTimeout:pause}=require('node:timers/promises');
const {readFileSync}=require('node:fs');
const path=require('node:path');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'..'), origin='http://page-draft.example.test';
const rev='a'.repeat(32), newer='b'.repeat(32);
const libraryImage='/assets/images/media/2026/11/fixture-library.png';
const uploadedImage='/assets/images/media/2026/11/fixture-upload.png';
const raster=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jX1sAAAAASUVORK5CYII=','base64');
const styles='<link rel="stylesheet" href="/assets/css/band.css"><link rel="stylesheet" href="/assets/css/admin-refinement.css">';

async function pageFields(body, contentType) {
    return Object.fromEntries(await new Response(body, {headers:{'Content-Type':contentType}}).formData());
}

function document(body, admin=true) {
    const main=admin ? '<main class="admin-body">' : '<main>';
    return '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1">'+styles+'</head><body>'+main+body+'</main></body></html>';
}

function fixtureAsset(pathname) {
    if ([libraryImage,uploadedImage].includes(pathname)) {
        return {contentType:'image/png',body:raster};
    }
    if (['/assets/page-draft.js','/assets/css/band.css','/assets/css/admin-refinement.css'].includes(pathname)) {
        const contentType=pathname.endsWith('.js') ? 'application/javascript' : 'text/css';
        return {contentType,body:readFileSync(path.join(root,pathname))};
    }
    return null;
}

function renderHeader({form, images=[], savedToken='', preview=false, published=false}) {
    const php=String.raw`putenv('BAPX_TEST_MODE=1'); require 'app/bootstrap.php';
        $fixture=json_decode($argv[1],true,flags:JSON_THROW_ON_ERROR);
        $form=$fixture['form']; $csrf='fixture'; $pages=[];
        $pageDraftOwner='fixture-header-owner'; $savedPageDraftToken=$fixture['savedToken'];
        $headerImages=$fixture['images'];
        if ($fixture['preview'] || $fixture['published']) {
            $page=\App\Services\AuthoredPageService::record($form);
            $html=\App\Services\AuthoredPageService::renderContent($page['content']);
            $preview=compact('page','html');
        }
        require $fixture['published'] ? 'views/public/authored-page.php' : 'views/admin/pages.php';`;
    const data=JSON.stringify({form,images,savedToken,preview,published});
    return execFileSync(process.env.PHP_BINARY||'php',['-r',php,data],{cwd:root,encoding:'utf8'});
}

async function requireImage(image, source) {
    assert.equal(await image.getAttribute('src'),source);
    assert.ok(await image.evaluate(async node=>{
        await node.decode();
        return node.naturalWidth>0;
    }));
}

async function requireNoOverflow(page) {
    const {viewport,content}=await page.evaluate(()=>({viewport:innerWidth,content:document.documentElement.scrollWidth}));
    assert.ok(content<=viewport+1,`Document width ${content}px exceeds the ${viewport}px viewport.`);
}

async function visiblePreview(page) {
    await page.waitForURL('**/admin/pages/save#page-preview');
    const preview=page.getByRole('region',{name:'Unsaved preview'});
    await preview.waitFor();
    const heading=preview.getByRole('heading',{name:'Unsaved preview'});
    const deadline=Date.now()+3500;
    let bounds;
    do {
        bounds=await heading.evaluate(node=>{
            const rect=node.getBoundingClientRect();
            return {top:rect.top,bottom:rect.bottom,viewport:innerHeight};
        });
        if(bounds.top>=0 && bounds.bottom<=bounds.viewport)return preview;
        await pause(25);
    } while(Date.now()<deadline);
    assert.fail(`Preview heading falls outside the viewport: ${JSON.stringify(bounds)}.`);
}

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
        await context.route('**/*',async route=>{
            const req=route.request(),u=new URL(req.url());
            assert.equal(u.origin,origin);
            const asset=fixtureAsset(u.pathname);
            if(asset)return route.fulfill(asset);
            if(u.pathname==='/admin/pages/save'){
                posts++;
                const fields=await pageFields(req.postDataBuffer(), req.headers()['content-type']);
                assert.equal(fields.expected_revision,revision);
                assert.equal(fields._csrf,'fixture');
                assert.equal(fields.action,'save');
                saved=fields.browser_draft_token;
                assert.match(saved,/^[a-f0-9]{32}$/);
                return route.fulfill({status:303,headers:{location:origin+'/admin/pages?edit=guide'}});
            }
            if(u.pathname==='/admin/pages')return route.fulfill({contentType:'text/html',body:document(render(revision,owner,saved))});
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


async function startHeaderEditor(t) {
    const images=[{url:libraryImage,original_name:'Library courtyard'}];
    const record={original_slug:'guide',slug:'guide',title:'Existing guide',content:'Original text',status:'draft',expected_revision:rev};
    const state={record, saves:[], previews:[], uploads:0};
    let savedToken='';
    let fixtureError;
    const server=createServer(async (request,response)=>{
        function send(status,contentType,body) {
            response.writeHead(status,{'Content-Type':contentType}).end(body);
        }
        try {
            const url=new URL(request.url,origin);
            const asset=fixtureAsset(url.pathname);
            if(asset)return send(200,asset.contentType,asset.body);

            if(url.pathname==='/admin/pages') {
                assert.equal(request.method,'GET');
                return send(200,'text/html',document(renderHeader({form:state.record,images,savedToken})));
            }
            if(url.pathname==='/pages/guide') {
                if(state.record.status!=='published') {
                    return send(404,'text/html',document('<h1>Page not found</h1>',false));
                }
                return send(200,'text/html',document(renderHeader({form:state.record,published:true}),false));
            }
            if(url.pathname==='/admin/media/upload') {
                assert.equal(request.method,'POST');
                assert.match(request.headers['content-type'],/^multipart\/form-data; boundary=/);
                assert.equal(request.headers.accept,'application/json');
                const body=(await requestBody(request)).toString('latin1');
                assert.ok(body.includes('name="media_files[]"; filename="page-upload.png"'));
                assert.ok(body.includes('name="context"\r\n\r\nshared\r\n'));
                assert.ok(body.includes('name="_csrf"\r\n\r\nfixture\r\n'));
                state.uploads++;
                images.push({url:uploadedImage,original_name:'page-upload.png'});
                return send(201,'application/json',JSON.stringify({files:[{url:uploadedImage}]}));
            }
            if(url.pathname==='/admin/pages/save') {
                assert.equal(request.method,'POST');
                const fields=await pageFields(await requestBody(request), request.headers['content-type']);
                assert.equal(fields._csrf,'fixture');
                assert.equal(fields.expected_revision,state.record.expected_revision);
                assert.ok(fields.header_image_url==='' || images.some(image=>image.url===fields.header_image_url));
                if(fields.action==='preview') {
                    state.previews.push(fields);
                    return send(200,'text/html',document(renderHeader({form:fields,images,preview:true})));
                }
                assert.equal(fields.action,'save');
                savedToken=fields.browser_draft_token;
                assert.match(savedToken,/^[a-f0-9]{32}$/);
                state.saves.push(fields);
                state.record={...fields,expected_revision:randomBytes(16).toString('hex')};
                if(!fields.header_image_url) {
                    delete state.record.header_image_url;
                    delete state.record.header_image_alt;
                }
                return response.writeHead(302,{Location:'/admin/pages?edit=guide'}).end();
            }
            return send(404,'text/plain','');
        } catch(error) {
            fixtureError=error;
            return send(500,'text/plain','Invalid fixture request');
        }
    });
    // Playwright route handlers skip redirect targets. Serve the fixture so Chrome follows the real redirect.
    server.listen(0,'127.0.0.1');
    await once(server,'listening');
    t.after(async()=>{
        await new Promise((resolve,reject)=>server.close(error=>error ? reject(error) : resolve()));
        if(fixtureError) throw fixtureError;
    });
    return {origin:'http://127.0.0.1:'+server.address().port,state};
}

async function requestBody(request) {
    const chunks=[];
    for await(const chunk of request)chunks.push(chunk);
    return Buffer.concat(chunks);
}

async function saveHeader(editor,state) {
    const [response]=await Promise.all([
        editor.waitForResponse(response=>response.url().endsWith('/admin/pages?edit=guide')
            && response.request().redirectedFrom()?.method()==='POST'),
        editor.getByRole('button',{name:'Save page'}).click(),
    ]);
    assert.equal(response.status(),200);
    await editor.locator('#page-draft-form [name=expected_revision][value="'+state.record.expected_revision+'"]').waitFor({state:'attached'});
}

for(const width of [375,1280]) test('authored header selection, upload, preview, save and clear at '+width+'px',async(t)=>{
    const {origin,state}=await startHeaderEditor(t);
    const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox']});
    const context=await browser.newContext({viewport:{width,height:850}});
    const editor=await context.newPage();
    const reader=await context.newPage();
    const alt='Cool shade beside the courtyard';
    const title='Courtyard visiting guide '+'Reference'.repeat(12);
    const markup='## Opening hours\n\n**Bring water.** <script>window.fixtureExecuted=true</script>';
    try {
        await editor.goto(origin+'/admin/pages?edit=guide');
        await requireNoOverflow(editor);
        assert.equal(await editor.getByLabel('Choose uploaded image').inputValue(),'');
        assert.equal(await editor.locator('#page-header-thumbnail').isVisible(),false);
        assert.equal((await reader.goto(origin+'/pages/guide')).status(),404);

        await editor.getByLabel('Title').fill(title);
        await editor.getByLabel('Page content').fill(markup);
        await editor.getByLabel('Choose uploaded image').selectOption(libraryImage);
        await editor.getByLabel('Alternative text').fill(alt);
        await requireImage(editor.locator('#page-header-thumbnail'),libraryImage);
        await editor.reload();
        await editor.getByRole('button',{name:'Restore draft'}).click();
        assert.equal(await editor.getByLabel('Choose uploaded image').inputValue(),libraryImage);
        assert.equal(await editor.getByLabel('Alternative text').inputValue(),alt);
        await requireImage(editor.locator('#page-header-thumbnail'),libraryImage);
        await editor.getByRole('button',{name:'Preview',exact:true}).click();
        const preview=await visiblePreview(editor);
        await requireImage(preview.getByRole('img',{name:alt}),libraryImage);
        assert.equal(await preview.getByRole('heading',{name:title}).count(),1);
        assert.equal(await preview.getByRole('heading',{name:'Opening hours'}).count(),1);
        assert.equal(await preview.locator('strong').innerText(),'Bring water.');
        assert.equal(await preview.locator('script').count(),0);
        const renderedMarkdown=await preview.locator('.document-page__content').innerHTML();
        assert.equal(state.previews.length,1);
        assert.equal(state.saves.length,0);
        assert.equal(state.record.title,'Existing guide');
        assert.equal((await reader.reload()).status(),404);
        await requireNoOverflow(editor);

        await editor.getByLabel('Image file').setInputFiles({name:'page-upload.png',mimeType:'image/png',buffer:raster});
        await editor.getByRole('button',{name:'Upload to Media Library'}).click();
        await editor.getByText('Image uploaded and selected.',{exact:false}).waitFor();
        assert.equal(await editor.getByLabel('Choose uploaded image').inputValue(),uploadedImage);
        await requireImage(editor.locator('#page-header-thumbnail'),uploadedImage);
        assert.equal(state.uploads,1);
        assert.equal(state.saves.length,0);

        await editor.getByLabel('Visibility').selectOption('published');
        await saveHeader(editor,state);
        assert.equal(state.saves.length,1);
        assert.equal(state.record.header_image_url,uploadedImage);
        assert.equal(await editor.getByLabel('Choose uploaded image').inputValue(),uploadedImage);
        assert.equal(await editor.getByLabel('Alternative text').inputValue(),alt);
        assert.notEqual(await editor.locator('#page-draft-form [name=expected_revision]').inputValue(),rev);
        assert.equal(await editor.getByRole('button',{name:'Restore draft'}).isVisible(),false);

        assert.equal((await reader.reload()).status(),200);
        assert.equal(await reader.getByRole('heading',{name:title,level:1}).count(),1);
        await requireImage(reader.getByRole('img',{name:alt}),uploadedImage);
        assert.equal(await reader.locator('.document-page__content').innerHTML(),renderedMarkdown);
        const header=await reader.locator('.document-page__header').boundingBox();
        const body=await reader.locator('.document-page__content').boundingBox();
        assert.ok(Math.abs(header.x-body.x)<=1 && Math.abs(header.width-body.width)<=1);
        await requireNoOverflow(reader);

        await editor.getByLabel('Choose uploaded image').selectOption('');
        await saveHeader(editor,state);
        assert.equal(state.saves.length,2);
        assert.equal(state.saves[1].header_image_url,'');
        assert.equal(await editor.getByLabel('Choose uploaded image').inputValue(),'');
        assert.equal(await editor.getByLabel('Alternative text').inputValue(),'');
        assert.equal(await editor.locator('#page-header-thumbnail').isVisible(),false);
        assert.equal((await reader.reload()).status(),200);
        assert.equal(await reader.locator('.authored-page__image').count(),0);
        assert.equal(await reader.locator('.document-page__content').innerHTML(),renderedMarkdown);
    } finally {await browser.close();}
});

test('native header selection and preview work without JavaScript at 375px',async(t)=>{
    const {origin,state}=await startHeaderEditor(t);
    const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox']});
    const context=await browser.newContext({viewport:{width:375,height:850},javaScriptEnabled:false});
    const editor=await context.newPage();
    try {
        await editor.goto(origin+'/admin/pages?edit=guide');
        assert.equal(await editor.getByRole('button',{name:'Upload to Media Library'}).isVisible(),false);
        assert.equal(await editor.getByRole('link',{name:'Open Media Library to upload in a new tab'}).isVisible(),true);
        await editor.getByLabel('Choose uploaded image').selectOption(libraryImage);
        await editor.getByLabel('Alternative text').fill('Courtyard gate');
        await editor.getByRole('button',{name:'Preview',exact:true}).press('Enter');
        const preview=await visiblePreview(editor);
        await requireImage(preview.getByRole('img',{name:'Courtyard gate'}),libraryImage);
        assert.equal(state.previews.length,1);
        assert.equal(state.saves.length,0);
        await requireNoOverflow(editor);
    } finally {await browser.close();}
});
