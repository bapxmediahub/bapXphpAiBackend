'use strict';
const {test} = require('node:test');
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const {readFileSync} = require('node:fs');
const {createServer} = require('node:http');
const {once} = require('node:events');
const path = require('node:path');
const {chromium} = require('playwright');

const root = path.resolve(__dirname, '..');
const csrf = 'synthetic-campaign-csrf';
const token = 'synthetic-campaign-token';
const fieldIds = ['detail_1', 'detail_2', 'detail_3'];
const campaignPath = '/pages/courtyard-guide';
const editorPath = '/admin/pages?edit=courtyard-guide';
const longLabel = 'DescribeYourArrivalPreferencesAndAnyAccessibilityRequirements';

function php(operation, data) {
    // Load the production view and pure validation methods in PHP without bootstrap,
    // environment files, network access or a database connection.
    const fixture = String.raw`
        function app_path(string $path = ''): string { return getcwd().'/'.$path; }
        function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
        spl_autoload_register(static function (string $class): void {
            if (str_starts_with($class, 'App\\')) {
                require app_path('app/'.str_replace('\\', '/', substr($class, 4)).'.php');
            }
        });
        use App\Services\AuthoredPageService;
        use App\Services\CampaignLeadService;
        $data = json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR);
        if ($argv[1] === 'definition') {
            try {
                echo json_encode(['definition'=>CampaignLeadService::fromEditor($data['enabled'], $data['fields'])],
                    JSON_THROW_ON_ERROR);
            } catch (InvalidArgumentException $error) {
                echo json_encode(['error'=>$error->getMessage()], JSON_THROW_ON_ERROR);
            }
            return;
        }
        if ($argv[1] === 'validate') {
            echo json_encode(CampaignLeadService::validate($data['definition'], $data['answers']), JSON_THROW_ON_ERROR);
            return;
        }
        $csrf = $data['csrf'];
        $_SESSION = ['csrf_token'=>$csrf];
        $page = $data['page'];
        $html = AuthoredPageService::renderContent($page['content']);
        if ($argv[1] === 'public') {
            $campaignState = $data['state'];
            require app_path('views/public/authored-page.php');
            return;
        }
        $pages = [$page];
        $form = $data['form'];
        $pageDraftOwner = 'fixture-campaign-owner';
        $savedPageDraftToken = $data['savedToken'];
        $error = $data['error'] ?? null;
        if (!empty($data['preview'])) $preview = compact('page', 'html');
        require app_path('views/admin/pages.php');
    `;
    return execFileSync(process.env.PHP_BINARY || 'php', ['-r', fixture, operation, JSON.stringify(data)], {
        cwd: root, encoding: 'utf8',
    });
}

function html(body, admin = false) {
    const styles = '<link rel="stylesheet" href="/assets/css/band.css"><link rel="stylesheet" href="/assets/css/admin-refinement.css">';
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        + styles + '</head><body><main' + (admin ? ' class="admin-body"' : '') + '>' + body + '</main></body></html>';
}

async function startFixture() {
    let page = {slug:'courtyard-guide', title:'Courtyard guide', content:'## Visiting the courtyard', status:'published', revision:'a'.repeat(32)};
    let received = false;
    let savedToken = '';
    const counts = {saved:0, submitted:0, leads:0};
    const editor = (options = {}) => {
        const form = options.form ?? {...page, original_slug:page.slug, expected_revision:page.revision};
        const document = php('admin', {page, form, csrf, savedToken, ...options});
        return {contentType:'text/html', body:html(document,true)};
    };
    const publicPage = (state = {}) => ({
        contentType:'text/html',
        body:html(php('public', {page, csrf, state:{token,...state}})),
    });

    const server = createServer(async (request,response) => {
        const url = new URL(request.url, 'http://127.0.0.1');
        const send = ({status = 200, contentType = 'text/plain', body = '', headers = {}}) => {
            response.writeHead(status, {'Content-Type':contentType,'Cache-Control':'no-store',...headers});
            response.end(body);
        };
        try { await reply(url.pathname,request,send); }
        catch (error) {
            send({status:500,body:String(error)});
        }
    });
    async function reply(pathname, request, send) {
        if (['/assets/css/band.css', '/assets/css/admin-refinement.css', '/assets/page-draft.js'].includes(pathname)) {
            const contentType = pathname.endsWith('.css') ? 'text/css' : 'application/javascript';
            return send({contentType, body:readFileSync(path.join(root,pathname))});
        }
        if (pathname === '/admin/pages') return send(editor());
        if (pathname === '/admin/pages/save') {
            assert.equal(request.method,'POST');
            const payload = await readFields(request);
            assert.equal(payload.get('_csrf'),csrf);
            assert.equal(payload.get('expected_revision'),page.revision);
            const fields = Object.fromEntries(fieldIds.map(id => [id,Object.fromEntries(
                ['type','label','required'].filter(key=>payload.has('campaign_fields['+id+']['+key+']'))
                    .map(key=>[key,payload.get('campaign_fields['+id+']['+key+']')]),
            )]));
            const enabled = payload.get('campaign_enabled');
            const result = JSON.parse(php('definition', {enabled,fields}));
            const form = {...page,original_slug:page.slug,expected_revision:page.revision,
                campaign_enabled:enabled,campaign_fields:fields};
            if (result.error) return send({status:422,...editor({form,error:result.error})});
            const candidate = {...page};
            delete candidate.campaign_form;
            if (result.definition !== null) candidate.campaign_form = result.definition;
            if (payload.get('action') === 'preview') {
                return send(editor({form,page:candidate,preview:true}));
            }
            assert.equal(payload.get('action'),'save');
            counts.saved++;
            savedToken = payload.get('browser_draft_token') || '';
            page = {...candidate,revision:counts.saved.toString(16).padStart(32,'0')};
            return send({status:303,headers:{location:editorPath}});
        }
        if (pathname === campaignPath) {
            const showReceipt = received;
            received = false;
            return send(publicPage({received:showReceipt}));
        }
        if (pathname === campaignPath+'/enquiry') {
            assert.equal(request.method,'POST');
            counts.submitted++;
            const payload = await readFields(request);
            assert.equal(payload.get('_csrf'),csrf);
            if (!Array.isArray(page.campaign_form)) {
                return send({status:409,...publicPage({errors:{form:'This page’s enquiry form has closed.'}})});
            }
            assert.equal(payload.get('revision'),page.revision);
            assert.equal(payload.get('submission_token'),token);
            const answers = Object.fromEntries(['name','email',...fieldIds].filter(id=>payload.has('answers['+id+']'))
                .map(id=>[id,payload.get('answers['+id+']')]));
            const validation = JSON.parse(php('validate', {definition:page.campaign_form,answers}));
            if (Object.keys(validation.errors).length) return send({status:422,...publicPage(validation)});
            counts.leads++;
            received = true;
            return send({status:303,headers:{location:campaignPath+'#campaign-enquiry'}});
        }
        return send({status:404,body:''});
    }
    server.listen(0,'127.0.0.1');
    await once(server,'listening');
    return {origin:'http://127.0.0.1:'+server.address().port,server,counts};
}

async function readFields(request) {
    const chunks = [];
    for await (const chunk of request) chunks.push(chunk);
    return new URLSearchParams(Buffer.concat(chunks).toString('utf8'));
}

test('A completed enquiry receipt survives concurrent form withdrawal',()=>{
    const page = {slug:'courtyard-guide', title:'Courtyard guide', content:'Visiting the courtyard'};
    const document = php('public', {page, csrf, state:{received:true}});
    assert.match(document,/>Enquiry received<\/h2>/);
    assert.doesNotMatch(document,/<form|name="submission_token"/);
});

async function fits(page) {
    const dimensions = await page.evaluate(()=>({content:document.documentElement.scrollWidth, viewport:innerWidth}));
    assert.ok(dimensions.content<=dimensions.viewport+1,JSON.stringify(dimensions));
}

for (const width of [1280,375]) test('Campaign enquiry editor and public submission at '+width+'px',{timeout:30000},async()=>{
    const browser = await chromium.launch({executablePath:process.env.CHROMIUM_PATH||undefined,headless:true,args:['--no-sandbox']});
    let fixture;
    try {
        fixture = await startFixture();
        const {origin,counts} = fixture;
        const activate = control => width === 375 ? control.press('Enter') : control.click();
        const context = await browser.newContext({viewport:{width,height:850},javaScriptEnabled:width!==375});
        context.setDefaultTimeout(5000);
        await context.route('**/*', route=>new URL(route.request().url()).origin === origin
            ? route.continue() : route.abort());
        const editor = await context.newPage();
        const visitor = await context.newPage();
        await visitor.goto(origin+campaignPath);
        assert.equal(await visitor.locator('#campaign-enquiry').count(),0);
        await editor.bringToFront();
        await editor.goto(origin+editorPath);
        const enabled = editor.getByLabel('Show an enquiry form on this page');
        assert.equal(await enabled.isChecked(),false);
        await enabled.press('Space');
        await activate(editor.getByRole('button',{name:'Preview',exact:true}));
        const preview = editor.getByRole('region',{name:'Unsaved preview'});
        assert.equal(await preview.getByRole('textbox').count(),2);
        assert.equal(await preview.getByRole('textbox',{name:'Name (required)'}).isDisabled(),true);
        assert.equal(await preview.locator('form,input[name=submission_token]').count(),0);

        const first = editor.getByRole('group',{name:'Additional field 1'});
        await first.getByLabel('Field type').selectOption('text');
        await first.getByLabel('Require an answer').press('Space');
        await activate(editor.getByRole('button',{name:'Preview',exact:true}));
        assert.match(await editor.getByRole('alert').innerText(), /Each included campaign field needs/);
        assert.equal(await first.getByLabel('Field type').inputValue(),'text');
        assert.equal(await first.getByLabel('Require an answer').isChecked(),true);
        await first.getByLabel('Field label').fill('Arrival time');

        const second = editor.getByRole('group',{name:'Additional field 2'});
        await second.getByLabel('Field type').selectOption('textarea');
        await second.getByLabel('Field label').fill(longLabel);
        const third = editor.getByRole('group',{name:'Additional field 3'});
        await third.getByLabel('Field type').selectOption('tel');
        await third.getByLabel('Field label').fill('Callback number');

        if (width === 1280) {
            await editor.getByText('Draft saved in this browser.', {exact:false}).waitFor();
            assert.equal(counts.saved,0);
            await editor.reload();
            const download = editor.waitForEvent('download');
            await editor.getByRole('button',{name:'Download browser draft'}).click();
            const markdown = readFileSync(await (await download).path(),'utf8');
            assert.match(markdown,/Enquiry form settings[\s\S]*Enabled[\s\S]*Arrival time \(Short text, required\)/);
            await editor.getByRole('button',{name:'Restore draft'}).click();
            assert.equal(await enabled.isChecked(),true);
            assert.equal(await first.getByLabel('Field label').inputValue(),'Arrival time');
            assert.equal(await first.getByLabel('Require an answer').isChecked(),true);
            assert.equal(await second.getByLabel('Field type').inputValue(),'textarea');
            assert.equal(await third.getByLabel('Field type').inputValue(),'tel');
        }

        await editor.getByRole('button',{name:'Preview',exact:true}).press('Enter');
        await preview.getByRole('textbox',{name:'Arrival time (required)'}).waitFor();
        assert.equal(await preview.getByRole('textbox').count(),5);
        assert.equal(await preview.getByRole('textbox',{name:longLabel+' (optional)'}).isDisabled(),true);
        assert.equal(await preview.getByRole('button',{name:'Send enquiry'}).isDisabled(),true);
        assert.equal(await preview.locator('form,input[name=submission_token]').count(),0);
        assert.equal(counts.saved,0);
        await fits(editor);

        await activate(editor.getByRole('button',{name:'Save page'}));
        await editor.waitForURL('**/admin/pages?edit=courtyard-guide');
        assert.equal(counts.saved,1);
        assert.equal(await enabled.isChecked(),true);
        assert.equal(await first.getByLabel('Field label').inputValue(),'Arrival time');

        await visitor.bringToFront();
        await visitor.reload();
        const enquiry = visitor.getByRole('region',{name:'Send an enquiry'});
        const name = enquiry.getByRole('textbox',{name:'Name (required)'});
        const email = enquiry.getByRole('textbox',{name:'Email (required)'});
        const arrival = enquiry.getByRole('textbox',{name:'Arrival time (required)'});
        const note = enquiry.getByRole('textbox',{name:longLabel+' (optional)'});
        const phone = enquiry.getByRole('textbox',{name:'Callback number (optional)'});
        const submit = enquiry.getByRole('button',{name:'Send enquiry'});
        assert.equal(await name.getAttribute('autocomplete'),'name');
        assert.equal(await email.getAttribute('type'),'email');
        assert.equal(await phone.getAttribute('type'),'tel');
        assert.equal(await note.getAttribute('maxlength'),'1000');
        await activate(submit);
        assert.equal(counts.submitted,0);
        await name.fill('Fixture visitor');
        await email.fill('visitor@example.invalid');
        await arrival.fill('   ');
        await note.fill('Please share information about the accessible entrance.');
        await activate(submit);
        await enquiry.getByRole('alert').getByText('Check the highlighted fields', {exact:false}).waitFor();
        assert.equal(await enquiry.evaluate(node=>node===document.activeElement),true);
        assert.equal(await arrival.getAttribute('aria-invalid'),'true');
        assert.equal(await arrival.getAttribute('aria-describedby'),'campaign-field-detail_1-error');
        assert.equal(await email.inputValue(),'visitor@example.invalid');
        assert.equal(await note.inputValue(),'Please share information about the accessible entrance.');
        await activate(enquiry.getByRole('alert').getByRole('link',{name:'Arrival time'}));
        assert.equal(await arrival.evaluate(node=>node===document.activeElement),true);
        await fits(visitor);

        await arrival.fill('10:30');
        const submission = visitor.waitForResponse(response=>response.url().endsWith('/enquiry'));
        await submit.press('Enter');
        assert.equal((await submission).status(),303);
        const receipt = visitor.getByRole('region',{name:'Enquiry received'});
        await receipt.waitFor().catch(error=>{
            throw new Error(`Receipt did not render at ${visitor.url()} after ${counts.submitted} submissions and ${counts.leads} leads.`,{cause:error});
        });
        assert.equal(counts.submitted,2);
        assert.equal(counts.leads,1);
        assert.equal(await receipt.evaluate(node=>node===document.activeElement),true);
        assert.equal(await receipt.locator('form,input').count(),0);
        assert.ok(!(await receipt.innerText()).includes('visitor@example.invalid'));
        await fits(visitor);

        await visitor.goto(origin+campaignPath);
        await name.fill('Fixture visitor');
        await email.fill('visitor@example.invalid');
        await arrival.fill('10:30');
        await editor.bringToFront();
        await enabled.press('Space');
        await activate(editor.getByRole('button',{name:'Save page'}));
        await editor.waitForURL('**/admin/pages?edit=courtyard-guide');
        await visitor.bringToFront();
        await activate(submit);
        const withdrawn = visitor.getByRole('region',{name:'Enquiry unavailable'});
        assert.equal(await withdrawn.getByRole('alert').innerText(),'This page’s enquiry form has closed.');
        assert.equal(await withdrawn.locator('form,input').count(),0);
        assert.equal(await withdrawn.evaluate(node=>node===document.activeElement),true);
        assert.equal(counts.leads,1);

        await visitor.goto(origin+campaignPath);
        assert.equal(await visitor.locator('#campaign-enquiry').count(),0);
    } finally {
        await browser.close();
        if (fixture) await new Promise(resolve=>fixture.server.close(resolve));
    }
});
