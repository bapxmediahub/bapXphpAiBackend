'use strict';
// Chromium Playwright against the existing PHP Integrations view, with fake-only
// credentials and network interception. No production URLs or customer sessions.
const {test}=require('node:test');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {readFileSync}=require('node:fs');
const path=require('node:path');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'..');
const origin='http://router-settings.example.test';
const fakeKey='sk-or-v1-'+('b'.repeat(64));
const render=enabled=>execFileSync(process.env.PHP_BINARY||'php',['-r',[
    "putenv('BAPX_TEST_MODE=1'); require 'app/bootstrap.php';",
    "$csrf='fixture-only'; $comparisonModels=[];",
    "$secrets=['ai_provider'=>'google','ai_api_key'=>'fixture-google-key',",
    "'ai_openrouter_enabled'=>'"+(enabled?'1':'0')+"','ai_openrouter_key'=>'"+fakeKey+"'];",
    "$fixture=new class extends \\App\\Controllers\\BaseController { public function show($secrets,$csrf,$comparisonModels){ include 'views/admin/integrations.php'; } };",
    "$fixture->show($secrets,$csrf,$comparisonModels);"
].join(' ')],{cwd:root,encoding:'utf8',maxBuffer:2*1024*1024});
for(const width of [1280,375]) test('owner OpenRouter toggle and masked save at '+width+'px',async()=>{
    const html=render(false);
    assert.ok(!html.includes(fakeKey),'Rendered admin HTML cannot expose the saved key.');
    const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox']});
    const context=await browser.newContext({viewport:{width,height:850}});
    const page=await context.newPage();
    const errors=[];page.on('pageerror',error=>errors.push(error.message));
    const sent=[];
    try{
        await page.route(origin+'/**',route=>{
            const req=route.request(),url=new URL(req.url());
            assert.equal(url.origin,origin,'No real network request is permitted.');
            if(url.pathname==='/admin/integrations/save'){
                const params=new URLSearchParams(req.postData());
                sent.push({
                    enabled:params.get('ai_openrouter_enabled'),
                    keyMatches:params.get('ai_openrouter_key')===fakeKey,
                    keyEmpty:params.get('ai_openrouter_key')==='',
                    csrf:params.get('_csrf')==='fixture-only',
                    provider:params.get('ai_provider'),
                });
                return route.fulfill({status:200,contentType:'text/html',body:render(true)});
            }
            if(url.pathname.startsWith('/assets/')){
                const name=url.pathname.replace(/^\//,'');
                if(['assets/css/band.css','assets/css/admin-refinement.css'].includes(name))
                    return route.fulfill({contentType:'text/css',body:readFileSync(path.join(root,name))});
            }
            if(url.pathname==='/admin/integrations')
                return route.fulfill({contentType:'text/html',body:render(false)});
            return route.fulfill({status:404,body:'Fixture route not available'});
        });
        await page.goto(origin+'/admin/integrations');
        const enabled=page.getByRole('checkbox',{name:'Enable OpenRouter Free'});
        const key=page.getByLabel('OpenRouter API Key',{exact:true});
        assert.equal(await enabled.isChecked(),false,'Provider must default off.');
        assert.equal(await key.getAttribute('type'),'password');
        assert.equal(await key.inputValue(),'','Stored key may never render as a password value.');
        await enabled.check();
        await key.fill(fakeKey);
        await page.getByRole('button',{name:'Save Integrations'}).click();
        assert.deepEqual(sent,[{enabled:'1',keyMatches:true,keyEmpty:false,csrf:true,provider:'google'}]);
        assert.equal(await page.getByRole('checkbox',{name:'Enable OpenRouter Free'}).isChecked(),true);
        assert.equal(await page.getByLabel('OpenRouter API Key',{exact:true}).inputValue(),'');
        await page.getByRole('checkbox',{name:'Enable OpenRouter Free'}).uncheck();
        await page.getByRole('button',{name:'Save Integrations'}).click();
        assert.equal(sent.length,2);
        assert.equal(sent[1].enabled,'0');
        assert.equal(sent[1].keyEmpty,true,'Blank input preserves the saved key in the backend.');
        assert.equal(sent[1].csrf,true);
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true);
        assert.deepEqual(errors,[]);
    }finally{await context.close();await browser.close();}
});
