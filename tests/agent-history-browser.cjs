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
