// Run with PHP, Playwright and Chromium available. All conversations are synthetic.
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const {test, before, after} = require('node:test');
const {chromium} = require('playwright');

const root = path.resolve(__dirname, '..');
const render = `function e($s) { return htmlspecialchars($s, ENT_QUOTES); }
    $csrf='test'; $attachables=[['name'=>'terms','kind'=>'page']]; $modelConfig=['configured'=>true]; include 'views/admin/agent.php';`;
const view = execFileSync('php', ['-r', render], {cwd: root, encoding: 'utf8'});
const origin = 'http://enterprise-agent.test';
const first = 'a'.repeat(32), second = 'b'.repeat(32);
let browser;

before(async () => {
    browser = await chromium.launch({executablePath: process.env.CHROMIUM_PATH || undefined});
});
after(async () => { await browser?.close(); });

for (const width of [1280, 375]) {
    test(`Saved chat selection, cancellation and retry at ${width}px`, async () => {
        const page = await browser.newPage({viewport: {width, height: 800}});
        try {
            await page.route(origin + '/admin/agent', route => route.fulfill({contentType: 'text/html', body: view}));
            await page.goto(origin + '/admin/agent');
            await page.addScriptTag({path: path.join(root, 'assets/agent-status.js')});
            // Deliberately resolve reads out of order, including after cancellation.
            await page.evaluate(({first, second}) => {
                window.reads = [];
                window.followups = [];
                window.fetch = (url, options) => {
                    if (url === '/admin/agent/history') {
                        return Promise.resolve(Response.json({conversations: [
                            {id: first, preview: 'First saved chat'}, {id: second, preview: 'Second saved chat'}
                        ]}));
                    }
                    if (url === '/admin/agent/ask') {
                        window.followups.push(options.body.get('conversation_id'));
                        return Promise.resolve(Response.json({answer: 'Follow-up complete'}));
                    }
                    if (url.startsWith('/admin/agent/history/')) {
                        const savedChat = {
                            conversation_id: url.split('/').pop(),
                            turns: [{question: url.endsWith(first) ? 'First question' : 'Second question', answer: 'Saved answer'}]
                        };
                        return new Promise(resolve => {
                            window.reads.push(status => resolve({ok: status === 200, json: async () => savedChat}));
                        });
                    }
                    throw new Error('Unexpected request');
                };
            }, {first, second});
            const history = page.getByRole('button', {name: 'History', exact: true});
            const entry = index => page.getByRole('button', {name: new RegExp(index + ' saved chat')});
            const transcript = page.getByRole('log');
            const reply = page.getByRole('textbox', {name: 'Message'});
            const finishRead = async (index, status = 200) => {
                await page.evaluate(([index, status]) => window.reads[index](status), [index, status]);
            };
            await history.click();
            await entry('First').press('Enter');
            assert.equal(await page.getByRole('button', {name: 'Send', exact: true}).isDisabled(), true);
            await page.getByRole('button', {name: 'New chat'}).click();
            await finishRead(0);
            assert.equal(await transcript.innerText(), '', 'New chat must stay empty when an older transcript arrives');
            await reply.fill('Fresh question');
            await reply.press('Enter');
            await transcript.getByText('Follow-up complete').waitFor();
            assert.deepEqual(await page.evaluate(() => window.followups), [null]);

            await history.click();
            await entry('First').click();
            await entry('Second').press('Enter');
            await finishRead(2);
            await transcript.getByText('Second question').waitFor();
            await finishRead(1);
            assert.equal(await transcript.getByText('First question').count(), 0);
            await reply.fill('Related question');
            await reply.press('Enter');
            await transcript.getByText('Follow-up complete').waitFor();
            assert.deepEqual(await page.evaluate(() => window.followups), [null, second]);

            await history.click();
            await entry('First').click();
            await finishRead(3, 503);
            await page.getByRole('status').getByText(/could not reopen/i).waitFor();
            await entry('First').press('Enter');
            await finishRead(4);
            await transcript.getByText('First question').waitFor();
            assert.equal(await reply.evaluate(el => el === document.activeElement), true);
        } finally {
            await page.close();
        }
    });
}

test('Composer keeps the draft and transcript while attaching at the cursor or uploading an image', async () => {
    const page = await browser.newPage({viewport:{width:375, height:800}});
    try {
        await page.route(origin + '/admin/agent', route => route.fulfill({contentType:'text/html', body:view}));
        await page.route(origin + '/admin/media/upload', route => {
            assert.equal(route.request().method(), 'POST');
            assert.match(route.request().headers().accept, /application\/json/);
            return route.fulfill({status:201, json:{files:[{url:'/media/uploads/ring.webp'}]}});
        });
        await page.goto(origin + '/admin/agent');
        const input = page.getByRole('textbox', {name:'Message'});
        await input.fill('use @te for this');
        await input.evaluate(el => {
            el.setSelectionRange(7, 7);
            el.dispatchEvent(new Event('input'));
        });
        await input.press('ArrowDown');
        await input.press('Enter');
        assert.equal(await input.inputValue(), 'use @terms  for this');
        await page.locator('#agent-upload-input').setInputFiles({name:'ring.png', mimeType:'image/png', buffer:Buffer.from('fixture')});
        await page.getByText('Images attached to this message.').waitFor();
        assert.equal(await input.inputValue(), 'use @terms  for this @ring.webp ');
        assert.equal(page.url(), origin + '/admin/agent');
        assert.equal(await page.getByRole('button', {name:'Send', exact:true}).isEnabled(), true);
        await page.unroute(origin + '/admin/media/upload');
        await page.route(origin + '/admin/media/upload', route => route.fulfill({status:422, json:{error:'Unsupported file'}}));
        await page.locator('#agent-upload-input').setInputFiles({name:'bad.png', mimeType:'image/png', buffer:Buffer.from('bad')});
        await page.getByText('Unsupported file').waitFor();
        assert.equal(await input.inputValue(), 'use @terms  for this @ring.webp ');
    } finally {
        await page.close();
    }
});

test('Failed question returns to the composer with the recorded turn and no automatic resend', async () => {
    const page = await browser.newPage();
    let sends = 0;
    try {
        await page.route(origin + '/admin/agent', route => route.fulfill({contentType:'text/html', body:view}));
        await page.route(origin + '/admin/agent/ask', route => {
            sends++;
            return route.fulfill({status:502, json:{conversation_id:first, turn_id:second, error:'Provider unavailable'}});
        });
        await page.goto(origin + '/admin/agent');
        await page.addScriptTag({path:path.join(root, 'assets/agent-status.js')});
        const input = page.getByRole('textbox', {name:'Message'});
        await input.fill('Read an owner guide');
        await input.press('Enter');
        const review = page.getByRole('link', {name:'Review this request'});
        await review.waitFor();
        assert.equal(await review.getAttribute('href'), '/admin/agent/review/' + first + '?turn=' + second);
        assert.equal(await input.inputValue(), 'Read an owner guide');
        await page.route(origin + '/admin/agent/history', route => route.fulfill({json:{conversations:[
            {id:second,preview:'Other chat',pending_turn_count:1}, {id:first,preview:'Original chat',pending_turn_count:1}
        ]}}));
        await page.route(origin + '/admin/agent/history/*', route => route.fulfill({json:{
            conversation_id:route.request().url().split('/').pop(),
            turns:route.request().url().endsWith(first) ? [
                {question:'Read an owner guide',answer:'Provider unavailable',outcome:'error',turn_id:second},
                {question:'Legacy question',answer:'Legacy saved answer'}
            ] : []
        }}));
        const history = page.getByRole('button', {name:'History',exact:true});
        await history.click();
        await page.getByRole('button', {name:/Other chat/}).click();
        await page.waitForFunction(() => document.querySelector('#agent-history-panel').hidden);
        assert.equal(await input.inputValue(), '', 'A failed question cannot leak into another conversation');
        await history.click();
        await page.getByRole('button', {name:/Original chat/}).click();
        await page.waitForFunction(() => document.querySelector('#agent-history-panel').hidden);
        assert.equal(await input.inputValue(), 'Read an owner guide', 'Draft stays with its original chat');
        assert.equal(await review.getAttribute('href'), '/admin/agent/review/' + first + '?turn=' + second);
        assert.match(await page.locator('.agent-bubble--error').innerText(), /Provider unavailable/);
        assert.match(await page.locator('.agent-bubble--bot').innerText(), /Legacy saved answer/);
        assert.equal(sends, 1);
    } finally {
        await page.close();
    }
});

const presentations=JSON.parse(execFileSync('php',['tests/fixtures/a2ui-responses.php'],{cwd:root,encoding:'utf8'}));
async function assertStructuredWidth(page) {
    const sizes=await page.locator('.bapx-a2ui--answer').evaluateAll(elements=>elements.map(element=>({
        surface:element.getBoundingClientRect().width,
        row:element.closest('.agent-row').getBoundingClientRect().width,
    })));
    assert.ok(sizes.length>0,'The structured answer must remain in the transcript');
    for(const size of sizes) {
        assert.ok(size.row>0&&size.surface>=Math.min(240,size.row*.6),
            'A live or saved structured answer must receive readable chat width, not its status label\'s intrinsic width');
    }
}
for(const width of [1280,375]) {
    test(`Enterprise recorded report rows survive history at ${width}px`,async()=>{
        const page=await browser.newPage({viewport:{width,height:800}});
        const errors=[];page.on('pageerror',error=>errors.push(error.message));
        let sends=0;
        try {
            await page.route(origin+'/admin/agent',route=>route.fulfill({contentType:'text/html',body:view}));
            await page.route(origin+'/admin/agent/ask',route=>{
                sends++;return route.fulfill({json:{conversation_id:first,answer:'Published report rows are shown below.',a2ui:presentations.commerce}});
            });
            await page.route(origin+'/admin/agent/history',route=>route.fulfill({json:{conversations:[{id:first,preview:'Recorded product report'}]}}));
            await page.route(origin+'/admin/agent/history/'+first,route=>route.fulfill({json:{conversation_id:first,turns:[
                {question:'Show product performance',answer:'Saved product report.',a2ui:presentations.commerceHistory},
            ]}}));
            await page.goto(origin+'/admin/agent');
            await page.addScriptTag({path:path.join(root,'assets/agent-status.js')});
            await page.addScriptTag({path:path.join(root,'assets/a2ui-public-renderer.js')});
            await page.addStyleTag({path:path.join(root,'assets/css/a2ui-public.css')});
            const input=page.getByRole('textbox',{name:'Message'});
            await input.fill('Show product performance');await input.press('Enter');
            await page.locator('.bapx-a2ui__report-item').first().waitFor();
            await assertStructuredWidth(page);
            assert.equal(await page.locator('.bapx-a2ui__report-item').count(),2);
            assert.equal(await page.getByText('Published report rows are shown below.',{exact:true}).count(),1);
            const before=await page.locator('.bapx-a2ui__report-rows').innerText();
            const source=page.getByRole('link',{name:'Open Product performance report',exact:true});
            assert.equal(await source.getAttribute('href'),'/admin/tax-report?report=products&from=2026-11-01&to=2026-11-22');
            await source.focus();assert.equal(await source.evaluate(element=>element===document.activeElement),true);
            await page.getByRole('button',{name:'History',exact:true}).click();
            await page.getByRole('button',{name:/Recorded product report/}).click();
            await page.getByText('Saved product report.',{exact:true}).waitFor();
            await assertStructuredWidth(page);
            assert.equal(await page.locator('.bapx-a2ui__report-rows').innerText(),before,'History must keep exact recorded report rows');
            assert.match(await page.locator('.bapx-a2ui--answer').innerText(),/2026-11-22 10:00:00.000000 UTC/);
            assert.equal(sends,1,'History must not execute the report again');
            assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
            assert.deepEqual(errors,[]);
        } finally {await page.close();}
    });
    test(`Enterprise structured answer, history and complete fallback at ${width}px`, async()=>{
        const page=await browser.newPage({viewport:{width,height:800}});
        const errors=[];page.on('pageerror',error=>errors.push(error.message));
        let sends=0;
        try {
            await page.route(origin+'/admin/agent',route=>route.fulfill({contentType:'text/html',body:view}));
            await page.route(origin+'/admin/agent/ask',route=>{
                sends++;
                const a2ui=structuredClone(sends===2?presentations.product:presentations.enterprise);
                if(sends>2)a2ui.messages[0].version='unsupported';
                return route.fulfill({json:{conversation_id:first,answer:sends===1
                    ?'Sales for the selected period.\n\nThe completed read-only lookup returned ₹1,250.00.'
                    :sends===2?'Complete original answer after public-only presentation.':'Complete original answer after unsupported presentation.',a2ui,
                    run:{sources:[{label:'Sales report',path:'/admin/tax-report?report=sales&from=2026-11-01&to=2026-11-22'}]}}});
            });
            await page.route(origin+'/admin/agent/history',route=>route.fulfill({json:{conversations:[
                {id:first,preview:'Structured saved chat',pending_turn_count:1}
            ]}}));
            await page.route(origin+'/admin/agent/history/'+first,route=>{
                const bad=structuredClone(presentations.history);bad.messages[0].version='unsupported';
                return route.fulfill({json:{conversation_id:first,turns:[
                    {question:'Saved question',answer:'Saved authorized answer.',a2ui:presentations.history},
                    {question:'Legacy saved question',answer:'Legacy full fallback.',a2ui:bad},
                ]}});
            });
            await page.goto(origin+'/admin/agent');
            await page.addScriptTag({path:path.join(root,'assets/agent-status.js')});
            await page.addScriptTag({path:path.join(root,'assets/a2ui-public-renderer.js')});
            await page.addStyleTag({path:path.join(root,'assets/css/a2ui-public.css')});
            const input=page.getByRole('textbox',{name:'Message'});
            await input.fill('Sales for the selected period');await input.press('Enter');
            await page.locator('.bapx-a2ui--answer').waitFor();
            await assertStructuredWidth(page);
            assert.equal(await page.getByText('Sales for the selected period.',{exact:true}).count(),1,'Full flat response is not duplicated');
            const source=page.getByRole('link',{name:'Sales report',exact:true});
            assert.equal(await source.count(),1,'Source action appears once');
            assert.equal(await page.locator('.bapx-a2ui__row').count(),2,'Recorded tool values render as facts');
            assert.equal(await page.getByText('₹1,250.00',{exact:true}).count(),1);
            assert.ok(await source.evaluate(el=>el.getBoundingClientRect().height>=44));
            await source.focus();assert.equal(await source.evaluate(el=>el===document.activeElement),true);
            assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
            await page.getByRole('button',{name:'History',exact:true}).click();
            await page.getByRole('button',{name:/Structured saved chat/}).click();
            await page.getByText('Saved authorized answer.',{exact:true}).waitFor();
            await assertStructuredWidth(page);
            assert.equal(await page.locator('.bapx-a2ui--answer').count(),1);
            assert.equal(await page.getByText('Legacy full fallback.',{exact:true}).count(),1);
            assert.equal(await page.locator('.bapx-a2ui__row').count(),2,'History retains recorded facts');
            assert.match(await page.locator('.bapx-a2ui--answer').innerText(),/2026-11-22 09:30:00.000000 UTC/);
            assert.equal(await page.locator('.bapx-a2ui__image').count(),0,'Restored answers do not retrieve current catalogue data');
            assert.equal(sends,1,'History reads do not resend questions');
            await input.fill('A follow-up');await input.press('Enter');
            await page.getByText('Complete original answer after public-only presentation.',{exact:true}).waitFor();
            assert.equal(await page.locator('.bapx-a2ui--answer').count(),1,'Public-only response cannot replace the full native answer');
            assert.equal(await page.locator('.bapx-a2ui:not(.bapx-a2ui--answer)').count(),0,'Rejected public-only response adds no cards');
            await input.fill('Unsupported response');await input.press('Enter');
            await page.getByText('Complete original answer after unsupported presentation.',{exact:true}).waitFor();
            assert.equal(await page.locator('.bapx-a2ui--answer').count(),1,'Unsupported surface keeps complete text fallback');
            assert.deepEqual(errors,[]);
        } finally {await page.close();}
    });
}
