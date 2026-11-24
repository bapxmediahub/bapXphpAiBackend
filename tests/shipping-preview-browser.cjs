'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {readFileSync}=require('node:fs');
const path=require('node:path');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'..'), origin='http://shipping-fixture.example.test';
function render(body=''){
    const source="putenv('BAPX_TEST_MODE=1'); require 'app/bootstrap.php'; $csrf='fixture'; $form=[]; "+
        "if($argv[1]!==''){parse_str($argv[1],$form);try{$quote=\\App\\Services\\ShippingService::preview($form['zones'],$form['state'],$form['pin'],$form['subtotal']);}catch(InvalidArgumentException $e){$error=$e->getMessage();}} require 'views/admin/shipping.php';";
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/css/band.css"></head><body><main class="admin-body">'+execFileSync(process.env.PHP_BINARY||'php',['-r',source,body],{cwd:root,encoding:'utf8'})+'</main></body></html>';
}
for(const width of [375,1280]) test('owner dry-run shipping preview at '+width+'px',async()=>{
    const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox']});
    const page=await browser.newPage({viewport:{width,height:850}});
    page.setDefaultTimeout(8000);
    let posts=0;
    try{
        await page.route('**/*',route=>{
            const req=route.request(),u=new URL(req.url());
            assert.equal(u.origin,origin);
            if(u.pathname==='/assets/css/band.css')return route.fulfill({contentType:'text/css',body:readFileSync(path.join(root,'assets/css/band.css'))});
            if(u.pathname==='/admin/shipping')return route.fulfill({contentType:'text/html; charset=utf-8',body:render()});
            if(u.pathname==='/admin/shipping/preview'){
                assert.equal(req.method(),'POST');
                assert.equal(new URLSearchParams(req.postData()).get('_csrf'),'fixture');
                posts++;
                return route.fulfill({contentType:'text/html; charset=utf-8',body:render(req.postData())});
            }
            return route.fulfill({status:404,body:''});
        });
        await page.goto(origin+'/admin/shipping');
        const zone1=page.getByRole('group',{name:'Zone 1',exact:true});
        const zone2=page.getByRole('group',{name:'Zone 2',exact:true});
        await zone1.getByLabel('State',{exact:true}).selectOption('Tamil Nadu');
        await zone1.getByLabel('PIN patterns').fill('641*');
        await zone1.getByLabel('Method').selectOption('free');
        await zone1.getByLabel('Minimum subtotal for free shipping').fill('1000');
        await zone2.getByLabel('State',{exact:true}).selectOption('Tamil Nadu');
        await zone2.getByLabel('Flat fee').fill('75.25');
        await page.getByLabel('Delivery state').selectOption('Tamil Nadu');
        await page.getByLabel('Delivery PIN').fill('641001');
        await page.getByLabel('Hypothetical item subtotal').fill('999.99');
        assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
        await page.getByRole('button',{name:'Preview shipping fee'}).click();
        await page.getByText('minimum subtotal was not met',{exact:false}).waitFor();
        await page.getByLabel('Delivery PIN').fill('638001');
        await page.getByRole('button',{name:'Preview shipping fee'}).click();
        await page.getByText('₹75.25',{exact:true}).waitFor();
        await page.getByLabel('Delivery state').selectOption('Kerala');
        await page.getByRole('button',{name:'Preview shipping fee'}).click();
        await page.getByText('No zone covers the selected delivery address.',{exact:false}).waitFor();
        assert.equal(posts,3);
    }finally{await browser.close();}
});
