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
const renderPage=enabled=>'<!doctype html><html lang="en"><head>'
    +'<meta name="viewport" content="width=device-width,initial-scale=1">'
    +'<link rel="stylesheet" href="/assets/css/band.css">'
    +'<link rel="stylesheet" href="/assets/css/admin-refinement.css">'
    +'</head><body><main class="admin-body">'+render(enabled)+'</main></body></html>';
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
                    enabled:params.getAll('ai_openrouter_enabled').at(-1), // PHP uses the last value after the hidden off sentinel
                    keyMatches:params.get('ai_openrouter_key')===fakeKey,
                    keyEmpty:params.get('ai_openrouter_key')==='',
                    csrf:params.get('_csrf')==='fixture-only',
                    provider:params.get('ai_provider'),
                    googleModel:params.getAll('ai_google_model_switches[gemma-4-31b-it]').at(-1),
                    googleIds:params.get('ai_google_models').replace(/\r/g,''),
                    routerIds:params.get('ai_openrouter_models').replace(/\r/g,''),
                });
                return route.fulfill({status:200,contentType:'text/html',body:renderPage(true)});
            }
            if(url.pathname.startsWith('/assets/')){
                const name=url.pathname.replace(/^\//,'');
                if(['assets/css/band.css','assets/css/admin-refinement.css'].includes(name))
                    return route.fulfill({contentType:'text/css',body:readFileSync(path.join(root,name))});
            }
            if(url.pathname==='/admin/integrations')
                return route.fulfill({contentType:'text/html',body:renderPage(false)});
            return route.fulfill({status:404,body:'Fixture route not available'});
        });
        await page.goto(origin+'/admin/integrations');
        const enabled=page.getByRole('checkbox',{name:'Enable OpenRouter Free'});
        const key=page.getByLabel('OpenRouter API Key',{exact:true});
        assert.equal(await enabled.isChecked(),false,'Provider must default off.');
        assert.equal(await key.getAttribute('type'),'password');
        assert.equal(await key.inputValue(),'','Stored key may never render as a password value.');
        const google=page.getByRole('checkbox',{name:'Enable Google models'});
        assert.equal(await google.isChecked(),true);
        await page.getByRole('checkbox',{name:'gemma-4-31b-it',exact:true}).uncheck();
        await page.getByLabel('Google model IDs in fallback order').fill('gemma-4-31b-it\ngemma-4-26b-a4b-it\ncustom-google-model');
        await page.getByLabel('OpenRouter model IDs in fallback order').fill('openrouter/free\nvendor/model:free');
        await enabled.check();
        await key.fill(fakeKey);
        await page.getByRole('button',{name:'Save Integrations'}).click();
        assert.deepEqual(sent,[{enabled:'1',keyMatches:true,keyEmpty:false,csrf:true,provider:'google',googleModel:'0',googleIds:'gemma-4-31b-it\ngemma-4-26b-a4b-it\ncustom-google-model',routerIds:'openrouter/free\nvendor/model:free'}]);
        assert.equal(await page.getByRole('checkbox',{name:'Enable OpenRouter Free'}).isChecked(),true);
        assert.equal(await page.getByLabel('OpenRouter API Key',{exact:true}).inputValue(),'');
        await page.getByRole('checkbox',{name:'Enable OpenRouter Free'}).uncheck();
        await page.getByRole('button',{name:'Save Integrations'}).click();
        assert.equal(sent.length,2);
        assert.equal(sent[1].enabled,'0');
        assert.equal(sent[1].keyEmpty,true,'Blank input preserves the saved key in the backend.');
        assert.equal(sent[1].csrf,true);
        const overflow=await page.evaluate(()=>({
            viewport:innerWidth,
            width:document.documentElement.scrollWidth,
            offenders:[...document.querySelectorAll('body *')].filter(element=>{
                const bounds=element.getBoundingClientRect(),display=getComputedStyle(element).display;
                return display!=='none' && bounds.width>0 && bounds.right>innerWidth+1;
            }).slice(0,8).map(element=>({tag:element.tagName,id:element.id,
                className:String(element.className||'').slice(0,70),right:Math.round(element.getBoundingClientRect().right)}))
        }));
        assert.equal(overflow.width<=overflow.viewport+1,true,
            'Integrations must fit the phone viewport: '+JSON.stringify(overflow));
        assert.deepEqual(errors,[]);
    }finally{await context.close();await browser.close();}
});
