'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {randomBytes}=require('node:crypto');
const {readFileSync}=require('node:fs');
const {createServer}=require('node:http');
const {once}=require('node:events');
const path=require('node:path');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'..');
const assetNames=['editor-server-draft.js','page-draft.js','blog-draft.js','blog-enhancement.js','css/band.css','css/admin-refinement.css'];
function render(kind,revision) {
    const code=String.raw`putenv('BAPX_TEST_MODE=1'); require 'app/bootstrap.php';
        $kind=$argv[1];$rev=$argv[2];$csrf='fixture';$pages=[];$posts=[];$categories=[];$mediaFiles=[];
        $pageDraftOwner=$blogDraftOwner='editor-fixture-owner';$savedPageDraftToken=$savedDraftToken='';$currentAuthor='Admin';
        $form=['slug'=>'guide','original_slug'=>'guide','expected_revision'=>$rev,'title'=>'Original',
            'content'=>'Original body','status'=>'draft'];
        $recoveredDraft=['slug'=>'guide','_revision'=>$rev,'title'=>'Original','content'=>'Original body',
            'published'=>false,'published_at'=>'2099-01-01','author'=>'Admin'];
        require $kind==='page'?'views/admin/pages.php':'views/admin/blog.php';`;
    return '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">'
        +'<link rel="stylesheet" href="/assets/css/band.css"><link rel="stylesheet" href="/assets/css/admin-refinement.css"></head>'
        +'<body><main class="admin-body">'+execFileSync('php',['-r',code,kind,revision],{cwd:root,encoding:'utf8'})+'</main></body></html>';
}
async function fixture(kind){
    const n=kind==='page'?32:64;
    let revision='a'.repeat(n), draft=null, saves=0;
    const server=createServer(async(req,res)=>{
        const send=(status,body,type='text/html; charset=utf-8')=>{res.writeHead(status,{'Content-Type':type,'Cache-Control':'no-store'});res.end(body);};
        try{
            if (req.url.startsWith('/assets/')){
                const name=req.url.slice(8).split('?')[0];
                if(!assetNames.includes(name))return send(404,'');
                return send(200,readFileSync(path.join(root,'assets',name)),name.endsWith('.js')?'application/javascript':'text/css');
            }
            if(req.url==='/editor')return send(200,render(kind,revision));
            if(req.url!=='/admin/editor-draft'||req.method!=='POST')return send(404,'');
            const chunks=[];for await(const c of req)chunks.push(c);
            const form=await new Response(Buffer.concat(chunks),{headers:{'Content-Type':req.headers['content-type']}}).formData();
            assert.equal(form.get('_csrf'),'fixture');assert.equal(form.get('kind'),kind);assert.equal(form.get('target'),'guide');
            const json=(code,value)=>send(code,JSON.stringify({...value,currentRevision:revision}),'application/json');
            const op=form.get('operation');
            if(op==='read')return json(200,{outcome:'read',draft});
            if(op==='write'&&form.get('revision')!==revision)return json(409,{outcome:'stale'});
            if((draft?.token||'')!==form.get('expected'))return json(409,{outcome:'conflict',draft});
            if(op==='discard'){draft=null;return json(200,{outcome:'discarded',draft:null});}
            assert.equal(op,'write');
            const values=JSON.parse(form.get('values'));
            assert.equal(Object.hasOwn(values,'published'),false);assert.equal(Object.hasOwn(values,'status'),false);
            assert.equal(Object.hasOwn(values,'notify_subscribers'),false);
            const item={revision,at:Math.floor(Date.now()/1000),values};
            draft={token:randomBytes(16).toString('hex'),versions:[item,...(draft?.versions||[])].slice(0,3)};saves++;
            return json(200,{outcome:'saved',token:draft.token});
        }catch(e){send(500,String(e));}
    });
    server.listen(0,'127.0.0.1');await once(server,'listening');
    return {server,origin:'http://127.0.0.1:'+server.address().port,get draft(){return draft;},get saves(){return saves;},
        advance(){revision='b'.repeat(n);}};
}
for(const kind of ['page','blog']) for(const width of [1280,375])test(kind+' server editor recovery at '+width+'px',{timeout:45000},async()=>{
    const app=await fixture(kind);
    const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||undefined,args:['--no-sandbox']});
    try{
        const make=async()=>{
            const context=await browser.newContext({viewport:{width,height:850},acceptDownloads:true});
            await context.route('**/*',route=>route.request().url().startsWith(app.origin+'/')?route.continue():route.abort());
            const page=await context.newPage();page.setDefaultTimeout(10000);
            await page.goto(app.origin+'/editor');
            return {context,page};
        };
        const first=await make(), second=await make();
        const panel=p=>p.getByRole('region',{name:'Server draft recovery'});
        const status=p=>panel(p).locator('[data-server-status]');
        const title=p=>p.locator(kind==='page'?'#page-draft-form [name=title]':'#edit-title');
        const contents=async p=>{
            if(kind==='blog')await p.getByRole('button',{name:'Edit Markdown',exact:true}).click();
            return p.locator(kind==='page'?'#page-draft-form [name=content]':'#edit-content');
        };
        for(const p of [first.page,second.page]) await status(p).getByText('Server recovery is ready.').waitFor();
        await title(first.page).fill('First server edit');
        await (await contents(first.page)).fill('Private recovery body one');
        await status(first.page).getByText('Server recovery saved.',{exact:false}).waitFor();
        assert.equal(app.draft.versions[0].values.content,'Private recovery body one');
        await title(second.page).fill('Concurrent local edit');
        await status(second.page).getByText('Another editor changed the server copy.',{exact:false}).waitFor();
        assert.equal(await title(second.page).inputValue(),'Concurrent local edit');
        assert.equal(app.draft.versions[0].values.title,'First server edit');
        const [localCopy]=await Promise.all([second.page.waitForEvent('download'),panel(second.page).getByRole('button',{name:'Download current edit'}).click()]);
        assert.equal(JSON.parse(readFileSync(await localCopy.path(),'utf8')).values.title,'Concurrent local edit');
        second.page.once('dialog',d=>d.dismiss());
        await panel(second.page).getByRole('button',{name:'Restore server draft'}).click();
        assert.equal(await title(second.page).inputValue(),'Concurrent local edit','Cancel restore must preserve concurrent unsaved edit');
        assert.equal(app.draft.versions[0].values.title,'First server edit');
        await second.context.close();
        const third=await make();
        await panel(third.page).getByRole('button',{name:'Restore server draft'}).waitFor();
        await panel(third.page).getByRole('button',{name:'Restore server draft'}).click();
        assert.equal(await title(third.page).inputValue(),'First server edit');
        assert.equal(await (await contents(third.page)).inputValue(),'Private recovery body one');
        assert.equal(await third.page.locator(kind==='page'?'[name=status]':'#edit-published').inputValue(),kind==='page'?'draft':'0');
        await title(third.page).fill('Second server version');
        await status(third.page).getByText('Server recovery saved.',{exact:false}).waitFor();
        assert.equal(app.draft.versions.length,2);
        await third.context.close();
        const fourth=await make();
        await panel(fourth.page).getByRole('combobox',{name:'Recovery version'}).selectOption('1');
        await panel(fourth.page).getByRole('button',{name:'Restore server draft'}).click();
        assert.equal(await title(fourth.page).inputValue(),'First server edit');
        await status(fourth.page).getByText('Server recovery saved.',{exact:false}).waitFor();
        assert.equal(app.draft.versions.length,3);
        assert.ok(await fourth.page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
        app.advance();
        const stale=await make();
        const restore=panel(stale.page).getByRole('button',{name:'Restore server draft'});
        await restore.waitFor();
        assert.equal(await restore.isDisabled(),true);
        const [file]=await Promise.all([stale.page.waitForEvent('download'),panel(stale.page).getByRole('button',{name:'Download server recovery'}).click()]);
        const recovered=JSON.parse(readFileSync(await file.path(),'utf8'));
        assert.equal(recovered.values.content,'Private recovery body one');
        stale.page.on('dialog',d=>d.accept());
        await panel(stale.page).getByRole('button',{name:'Discard server recovery'}).click();
        await status(stale.page).getByText('Server recovery discarded.',{exact:false}).waitFor();
        assert.equal(app.draft,null);
        assert.equal(await title(stale.page).inputValue(),'Original');
        assert.ok(await stale.page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
        await first.context.close();await fourth.context.close();await stale.context.close();
    }finally{await browser.close();await new Promise(resolve=>app.server.close(resolve));}
});
