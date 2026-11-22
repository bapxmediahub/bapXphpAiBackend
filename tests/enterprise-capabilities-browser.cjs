// Native PHP view and browser controls. All messages and identities are synthetic.
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const {readFileSync} = require('node:fs');
const path = require('node:path');
const {test, before, after} = require('node:test');
const {chromium} = require('playwright');

const root = path.resolve(__dirname, '..');
const origin = 'http://enterprise-capabilities.test';
const render = [
    "putenv('BAPX_TEST_MODE=1'); require 'app/bootstrap.php';",
    "$csrf='fixture'; $attachables=[]; $modelConfig=['configured'=>true];",
    "$agentCapabilities=\\App\\Services\\EnterpriseToolRegistry::capabilities(['role'=>'admin']);",
    "$agentCapabilityLimits=\\App\\Services\\EnterpriseToolRegistry::capabilityLimits();",
    "include 'views/admin/agent.php';",
].join('\n');
const view = execFileSync('php', ['-r', render], {cwd:root,encoding:'utf8'});
const reportSurfaces = JSON.parse(execFileSync('php', ['-r', [
    "putenv('BAPX_TEST_MODE=1'); require 'app/bootstrap.php';",
    "$presenter=new \\App\\Services\\AgentAnswerPresentationService(); $fixtures=[];",
    "foreach (['products','stock','customers','coupons'] as $type) {",
    "  $scope=['report'=>$type]+($type==='stock'?[]:['from'=>'2026-11-01','to'=>'2026-11-15']);",
    "  $result=\\App\\Services\\AgentToolRegistry::commerceReportFromRecords([],[],$scope);",
    "  $action=['label'=>'Commerce report','path'=>$result['report_url']];",
    "  $fixtures[]=['path'=>$action['path'],'surface'=>$presenter->present('Recorded owner report.','admin',[$action])];",
    "} echo json_encode($fixtures,JSON_THROW_ON_ERROR);",
].join('\n')], {cwd:root,encoding:'utf8'}));
const assets = new Map([
    'assets/css/band.css','assets/css/admin-refinement.css','assets/css/admin-workspaces.css',
    'assets/css/a2ui-public.css','assets/a2ui-public-renderer.js','assets/agent-status.js',
].map(file => ['/' + file, readFileSync(path.join(root, file), 'utf8')]));
const html = '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">'
    + Array.from(assets.keys()).filter(file=>file.endsWith('.css')).map(file=>'<link rel="stylesheet" href="'+file+'">').join('')
    // The real admin layout loads this before a question can be submitted.
    + '<script src="/assets/agent-status.js" defer></script>'
    + '</head><body><main style="padding:16px;min-width:0">' + view + '</main></body></html>';
let browser;
before(async () => { browser = await chromium.launch({executablePath:process.env.CHROMIUM_PATH || undefined}); });
after(async () => { await browser?.close(); });

async function routeFixture(page, requests) {
    await page.route(origin + '/**', route => {
        const url = new URL(route.request().url());
        if (url.pathname === '/admin/agent') return route.fulfill({contentType:'text/html',body:html});
        if (assets.has(url.pathname)) return route.fulfill({
            contentType:url.pathname.endsWith('.css')?'text/css':'application/javascript',body:assets.get(url.pathname),
        });
        if (url.pathname === '/admin/agent/ask') {
            requests.push(route.request().postData());
            return route.fulfill({json:{answer:'Synthetic owner report response.'}});
        }
        return route.fulfill({status:404,body:'Fixture route unavailable'});
    });
}

for (const width of [1280,375]) {
    test('Commerce source links render only in the owner scope at ' + width + 'px', async () => {
        const page = await browser.newPage({viewport:{width,height:900}});
        try {
            const requests = [];
            await routeFixture(page, requests);
            await page.goto(origin + '/admin/agent');
            const results = await page.evaluate(fixtures => {
                const output = document.createElement('section');
                document.querySelector('main').append(output);
                const checks = [];
                for (const fixture of fixtures) {
                    output.replaceChildren();
                    const rendered = window.BapxA2ui.render(output,fixture.surface,{scope:'admin'});
                    const href = output.querySelector('a')?.getAttribute('href');
                    output.textContent = 'Complete answer fallback';
                    const publicRejected = !window.BapxA2ui.render(output,fixture.surface);
                    const supportSurface = structuredClone(fixture.surface);
                    supportSurface.messages[0].createSurface.surfaceId = supportSurface.messages[0].createSurface.surfaceId.replace('bapx_admin_','bapx_support_');
                    const supportRejected = !window.BapxA2ui.render(output,supportSurface,{scope:'support'});
                    checks.push({path:fixture.path,rendered,href,publicRejected,supportRejected,fallback:output.textContent});
                }
                const denied = [
                    '/admin/settings', '/admin/tax-report?report=products', '/admin/tax-report?report=users',
                    '/admin/tax-report?report=stock&from=2026-11-01&to=2026-11-15',
                    '/admin/tax-report?report=coupons&from=2026-11-01&to=2026-11-15&format=csv',
                    'https://evil.invalid/admin/tax-report?report=stock',
                ].map(path => {
                    const forged = structuredClone(fixtures[0].surface);
                    forged.messages[0].createSurface.dataModel.actions[0].path = path;
                    output.textContent = 'Complete answer fallback';
                    return {path,rejected:!window.BapxA2ui.render(output,forged,{scope:'admin'}),fallback:output.textContent};
                });
                return {checks,denied};
            },reportSurfaces);
            for (const result of results.checks) {
                assert.equal(result.rendered,true,result.path);
                assert.equal(result.href,result.path);
                assert.equal(result.publicRejected,true);
                assert.equal(result.supportRejected,true);
                assert.equal(result.fallback,'Complete answer fallback');
            }
            for (const result of results.denied) {
                assert.equal(result.rejected,true,result.path);
                assert.equal(result.fallback,'Complete answer fallback');
            }
            assert.equal(requests.length,0,'Rendering report links must not submit a question.');
            assert.equal(page.url(),origin + '/admin/agent','Rendering cannot navigate automatically.');
        } finally { await page.close(); }
    });

    test('Capability examples fill without sending at ' + width + 'px', async () => {
        const page = await browser.newPage({viewport:{width,height:900}});
        const requests = [], errors = [];
        page.on('pageerror', error=>errors.push(error.message));
        try {
            await routeFixture(page, requests);
            await page.goto(origin + '/admin/agent');
            const input = page.getByRole('textbox', {name:'Message',exact:true});
            await page.locator('#agent-suggestions [data-q]').first().click();
            assert.equal(await input.inputValue(),'What were paid sales and paid orders in the last 30 days?');
            assert.equal(await input.evaluate(el=>el===document.activeElement),true);
            assert.equal(requests.length,0,'A pointer-selected quick example only fills the composer.');
            await page.locator('#agent-suggestions [data-q]').nth(1).press('Enter');
            assert.equal(await input.inputValue(),'Show the product performance report for the last 30 days.');
            assert.equal(await input.evaluate(el=>el===document.activeElement),true);
            assert.equal(requests.length,0,'A keyboard-selected quick example must also wait for Send.');
            // The empty transcript is intentionally hidden by the production CSS.
            assert.equal(await page.locator('#agent-messages').textContent(),'','Quick examples cannot fabricate a conversation.');
            await page.locator('#agent-capabilities summary').press('Enter');
            await page.getByRole('button', {name:'Use Product performance question',exact:true}).press('Enter');
            assert.equal(await input.inputValue(), 'Show the product performance report for the last 30 days.');
            assert.equal(await input.evaluate(el=>el===document.activeElement), true);
            assert.equal(requests.length,0,'Selecting a capability cannot submit or mutate.');
            assert.equal(await page.locator('#agent-messages').textContent(),'','Selecting a capability cannot fabricate a conversation.');
            await page.getByRole('button', {name:'Use Current stock status question',exact:true}).click();
            assert.equal(await input.inputValue(),'Show the current stock status report.');
            assert.equal(requests.length,0,'Pointer input also only fills the composer.');
            await input.press('Enter');
            await page.getByRole('log').getByText('Synthetic owner report response.').waitFor();
            assert.equal(requests.length,1,'An explicit send is the first request.');
            assert.match(await page.locator('.agent-capabilities__limits').innerText(),/live availability and booking are not available/);
            assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true,'Capability guide cannot overflow the mobile viewport.');
            assert.deepEqual(errors,[]);
        } finally { await page.close(); }
    });

    test('Capability guide remains readable without JavaScript at ' + width + 'px', async () => {
        const context = await browser.newContext({javaScriptEnabled:false,viewport:{width,height:900}});
        const page = await context.newPage();
        try {
            await routeFixture(page, []);
            await page.goto(origin + '/admin/agent');
            await page.locator('#agent-capabilities summary').click();
            assert.equal(await page.getByText('Example: Show the current stock status report.',{exact:true}).isVisible(),true);
            assert.equal(await page.getByRole('button',{name:'Use Current stock status question',exact:true}).isVisible(),false);
            assert.equal(await page.getByRole('link',{name:'Read the Enterprise Agent guide'}).getAttribute('href'),'/admin/docs/enterprise-agent');
        } finally { await context.close(); }
    });
}
