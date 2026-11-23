'use strict';
// Self-contained, no-credential Chromium QA on the actual PHP chat views.
// Runs in Actions or on an authorized dev workstation; never submits orders or owner writes.
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {readFileSync,existsSync,mkdirSync}=require('node:fs');
const path=require('node:path');
const {test}=require('node:test');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'..');
const origin='https://native-chat-qa.example.test';
const output=path.join(root,'qa-chat-ui-artifacts');
mkdirSync(output,{recursive:true});
function php(file){return execFileSync('php',['-r',file],{cwd:root,encoding:'utf8',maxBuffer:3*1024*1024});}
const support=JSON.parse(execFileSync('php',['tests/fixtures/support-checkout.php'],{cwd:root,encoding:'utf8',maxBuffer:3*1024*1024}));
const productSurface=JSON.parse(php([
    "putenv('BAPX_TEST_MODE=1'); require 'app/bootstrap.php';",
    "$items=[];for($i=1;$i<=3;$i++)$items[]=['name'=>'Verified fixture product '.$i,",
    "'path'=>'/product/fixture-product-'.$i,'priceInr'=>899+$i,'available'=>true,",
    "'description'=>'A product from the synthetic browser fixture.'];",
    "$reference=(new \\App\\Services\\PublicA2uiService())->fromResult('products',",
    "['products'=>$items,'total'=>3,'nextOffset'=>null]);",
    "$answer=(new \\App\\Services\\AgentAnswerPresentationService())->responseData(",
    "'Three published fixture products are available.','support',[],$reference);",
    "echo json_encode($answer['a2ui'],JSON_THROW_ON_ERROR);"
].join(' ')));
const adminHtml=php([
    "putenv('BAPX_TEST_MODE=1'); require 'app/bootstrap.php';",
    "$csrf='fixture'; $attachables=[]; $modelConfig=['configured'=>true];",
    "$agentCapabilities=\\App\\Services\\EnterpriseToolRegistry::capabilities(['role'=>'admin']);",
    "$agentCapabilityLimits=\\App\\Services\\EnterpriseToolRegistry::capabilityLimits();",
    "include 'views/admin/agent.php';"
].join(' '));
const adminSurface=JSON.parse(php([
    "putenv('BAPX_TEST_MODE=1'); require 'app/bootstrap.php';",
    "echo json_encode((new \\App\\Services\\AgentAnswerPresentationService())->present(",
    "'Recorded owner report is available.','admin'),JSON_THROW_ON_ERROR);"
].join(' ')));
const files=new Set([
  'band.css','a2ui-public.css','admin-refinement.css','admin-workspaces.css',
  'chat-commerce.css','support-checkout.css','a2ui-public-renderer.js',
  'agent-status.js','checkout-form.js','public-mcp-shopping.js','support-checkout.js'
]);
async function route(page,html){
  await page.route('**/*',request=>{
    const url=new URL(request.request().url());
    if(url.origin!==origin)return request.abort();
    if(url.pathname==='/'||url.pathname==='/admin/agent')return request.fulfill({contentType:'text/html',body:html});
    if(url.pathname.startsWith('/assets/')&&files.has(path.basename(url.pathname))){
      const target=path.join(root,url.pathname);
      if(existsSync(target))return request.fulfill({contentType:target.endsWith('.css')?'text/css':'application/javascript',body:readFileSync(target)});
    }
    return request.fulfill({status:404,body:'Fixture asset not found'});
  });
}
async function snap(page,label){await page.screenshot({path:path.join(output,label+'.png'),fullPage:false});}
async function browser(){return chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||undefined,args:['--no-sandbox']});}

for(const width of [375,1280])test('Existing Support panel compact composer and swipeable product cards '+width+'px',{timeout:45000},async()=>{
  const b=await browser(),page=await b.newPage({viewport:{width,height:width===375?812:900}});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  try{
    await route(page,support.html);await page.goto(origin,{waitUntil:'load'});
    await page.getByRole('button',{name:'Support',exact:true}).click();
    const rendered=await page.evaluate(surface=>{
      const target=document.createElement('section');
      document.getElementById('support-log').appendChild(target);
      return window.BapxA2ui.render(target,surface,{scope:'support'});
    },productSurface);
    assert.equal(rendered,true,'Actual A2UI renderer failed');
    const info=await page.evaluate(()=>{
      const panel=document.querySelector('#support-panel').getBoundingClientRect();
      const log=document.querySelector('#support-log').getBoundingClientRect();
      const input=document.querySelector('#support-form textarea').getBoundingClientRect();
      const send=document.querySelector('#support-form button').getBoundingClientRect();
      const rail=document.querySelector('.bapx-a2ui__items');
      return {panel:{x:panel.x,right:panel.right,height:panel.height},
        logHeight:log.height,textareaHeight:input.height,sameRow:Math.abs(input.bottom-send.bottom)<12,
        railOverflow:getComputedStyle(rail).overflowX,railScroll:rail.scrollWidth,railWidth:rail.clientWidth,
        role:rail.getAttribute('role'),label:rail.getAttribute('aria-label'),
        viewport:document.documentElement.scrollWidth};
    });
    console.log('SUPPORT_LAYOUT '+JSON.stringify({width,...info}));
    assert.ok(info.panel.x>=-1&&info.panel.right<=width+1&&info.panel.height<=(width===375?812:900));
    assert.ok(info.logHeight>=100,'Conversation area must not be crushed by composer');
    assert.ok(info.textareaHeight<=70&&info.sameRow,'Composer must fit message and Send on one row');
    assert.equal(info.railOverflow,'auto');
    assert.ok(info.railScroll>info.railWidth,'Multiple products must scroll inside one answer canvas');
    assert.equal(info.role,'region');assert.match(info.label,/arrow keys/);
    assert.ok(info.viewport<=width+2,'Support must not overflow page horizontally');
    await snap(page,'support-'+width);
    await page.locator('.bapx-a2ui__items').focus();
    await page.keyboard.press('ArrowRight');
    await page.waitForFunction(()=>document.querySelector('.bapx-a2ui__items').scrollLeft>0,null,{timeout:2000}).catch(()=>{});
    const scroll=await page.locator('.bapx-a2ui__items').evaluate(x=>x.scrollLeft);
    assert.ok(scroll>0,'Focused product rail must respond to keyboard browsing');
    assert.deepEqual(errors,[]);
  }finally{await b.close();}
});

for(const width of [375,1280])test('Existing Enterprise chat shows a full-width grounded A2UI answer '+width+'px',{timeout:45000},async()=>{
  const b=await browser(),page=await b.newPage({viewport:{width,height:width===375?812:900}});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  try{
    const html='<html><head><meta name="viewport" content="width=device-width,initial-scale=1">'
       +'<link rel="stylesheet" href="/assets/css/band.css">'
       +'<link rel="stylesheet" href="/assets/css/admin-refinement.css">'
       +'<link rel="stylesheet" href="/assets/css/admin-workspaces.css">'
       +'</head><body><main style="padding:12px;min-width:0">'+adminHtml+'</main></body></html>';
    await route(page,html);await page.goto(origin+'/admin/agent',{waitUntil:'load'});
    const rendered=await page.evaluate(surface=>{
      const log=document.getElementById('agent-messages');
      const row=document.createElement('div');row.className='agent-row agent-row--bot';
      const avatar=document.createElement('div');avatar.className='agent-avatar agent-avatar--bot';avatar.textContent='AI';
      const bubble=document.createElement('div');bubble.className='agent-bubble agent-bubble--bot';
      row.append(avatar,bubble);log.appendChild(row);
      return window.BapxA2ui.render(bubble,surface,{scope:'admin'});
    },adminSurface);
    assert.equal(rendered,true,'Owner answer projection could not render');
    const data=await page.evaluate(()=>{
      const row=document.querySelector('.agent-row--bot').getBoundingClientRect();
      const bubble=document.querySelector('.agent-row--bot .agent-bubble').getBoundingClientRect();
      return {rowWidth:row.width,bubbleWidth:bubble.width,viewport:document.documentElement.scrollWidth};
    });
    console.log('ENTERPRISE_LAYOUT '+JSON.stringify({width,...data}));
    assert.ok(data.bubbleWidth>=data.rowWidth*.68,'Grounded report must not sit in a narrow bubble');
    assert.ok(data.viewport<=width+2,'Enterprise chat cannot overflow its viewport');
    await snap(page,'enterprise-'+width);
    assert.deepEqual(errors,[]);
  }finally{await b.close();}
});
