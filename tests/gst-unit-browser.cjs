'use strict';

const assert = require('node:assert/strict');
const {execFileSync, spawn} = require('node:child_process');
const {once} = require('node:events');
const {mkdtemp, readFile, rm} = require('node:fs/promises');
const {createServer} = require('node:http');
const net = require('node:net');
const os = require('node:os');
const path = require('node:path');
const {test} = require('node:test');
const {chromium} = require('playwright');

test('owner edits GST units and reconciles frozen invoice quantities in Chromium', {timeout:45000}, async t => {
    const root = path.resolve(__dirname, '..');
    const sessions = await mkdtemp(path.join(os.tmpdir(), 'bapx-gst-unit-'));
    const profile = {id:'app_settings', site_display_name:'GST Unit Fixture'};
    const product = {id:'fixture-item', slug:'fixture-item', name:'Fixture product', stock_status:'draft', status:'hidden',
        price:100, hsn_code:'3307', gst_rate:12};
    const taxLine = {name:'Fixture product', hsn_code:'3307', qty:3, gst_rate:12,
        taxable_value:250, cgst:15, sgst:15, igst:0, gross_value:280};
    const order = {id:'gst-fixture-order', invoice_number:'SPS/2627/00001', invoice_date:'2026-11-02', shipping_state:'Tamil Nadu',
        place_of_supply:'Tamil Nadu', supply_type:'intrastate', supplier:{gstin:'33ABCDE1234F1Z5', state:'Tamil Nadu', state_code:'33'},
        tax_lines:[{...taxLine, uqc:'KGS', uqc_quantity:'0.750'}, {...taxLine, uqc:'PCS', uqc_quantity:'3.000'}, taxLine]};
    let writes = 0;
    let server, serverClosed, browser;
    const bridge = createServer(async (request, response) => {
        const chunks = [];
        for await (const chunk of request) chunks.push(chunk);
        const input = JSON.parse(Buffer.concat(chunks));
        response.setHeader('Content-Type', 'application/json');
        const ok = data => response.end(JSON.stringify({success:true, ...data}));
        if (input.password !== 'gst-unit-fixture') return response.writeHead(403).end('{}');
        if (typeof input.query === 'string') {
            const table = /\bFROM (\w+)/i.exec(input.query)?.[1];
            const rows = ({settings:[profile], products:[product], orders:[order]})[table] ?? [];
            const matching = input.query.includes('WHERE id = ?') ? rows.filter(row => row.id === input.params[0]) : rows;
            return ok({data:/^SELECT COUNT\(/i.test(input.query) ? [{total:matching.length}] :
                matching.map(record => ({id:record.id, _data:JSON.stringify(record)}))});
        }
        if (input.action === 'save_catalog_product' && input.collection === 'products' && input.record.id === product.id) {
            writes++;
            Object.assign(product, input.record);
            return ok({record:product});
        }
        if (input.action === 'upsert' && input.collection === 'audit_events') return ok({record:input.record});
        response.writeHead(400).end('{}');
    });
    t.after(async () => {
        try {
            await browser?.close();
        } finally {
            if (server) {
                server.kill();
                await serverClosed;
            }
            if (bridge.listening) {
                const closed = new Promise(resolve => bridge.close(resolve));
                bridge.closeAllConnections();
                await closed;
            }
            await rm(sessions, {recursive:true, force:true});
        }
    });
    bridge.listen(0, '127.0.0.1');
    await once(bridge, 'listening');
    const socket = net.createServer().listen(0, '127.0.0.1');
    await once(socket, 'listening');
    const port = socket.address().port;
    await new Promise(resolve => socket.close(resolve));
    const origin = 'http://127.0.0.1:' + port;
    const env = {...process.env, BAPX_TEST_MODE:'0', APP_URL:origin,
        BAPX_MYSQL_HOST:'127.0.0.1', BAPX_MYSQL_PORT:'1', BAPX_MYSQL_DB:'fixture',
        BAPX_MYSQL_USER:'fixture', BAPX_MYSQL_PASS:'gst-unit-fixture',
        BAPX_REMOTE_DB_URL:'http://localhost:' + bridge.address().port + '/remotedb'};
    const options = ['-d', 'session.save_path=' + sessions];
    const sessionId = 'gst-unit-admin-fixture';
    execFileSync('php', [...options, '-r',
        'session_id($argv[1]); session_start(); $_SESSION["user"]=["id"=>"fixture","role"=>"admin"]; session_write_close();',
        sessionId], {cwd:root,env});
    server = spawn('php', [...options,'-S','127.0.0.1:' + port,'index.php'], {cwd:root,env,stdio:'ignore'});
    serverClosed = new Promise(resolve => server.once('close', resolve));
    browser = await chromium.launch({executablePath:process.env.CHROMIUM_PATH || undefined});
    const context = await browser.newContext({serviceWorkers:'block', reducedMotion:'reduce'});
    context.setDefaultTimeout(7000);
    await context.route('https://**/*', route => route.abort());
    const page = await context.newPage();
    const report = origin + '/admin/tax-report?report=tax&from=2026-11-01&to=2026-11-30';
    await page.goto(report);
    assert.equal(new URL(page.url()).pathname, '/login');
    await context.addCookies([{name:'PHPSESSID',value:sessionId,url:origin}]);
    const edit = origin + '/admin/products?edit=' + product.id;
    const form = page.locator('#product-form');
    const unit = form.getByLabel('GST unit (UQC)');
    const quantity = form.getByLabel('GST quantity per sale item');
    const save = form.getByRole('button', {name:'Update Product',exact:true});

    for (const width of [1280,375]) {
        await page.setViewportSize({width,height:760});
        await page.goto(edit);
        const code = width === 1280 ? 'KGS' : 'PCS';
        const value = width === 1280 ? '0.250' : '1.000';
        await unit.selectOption(code);
        if (width === 1280) {
            await save.click();
            await page.getByText(/Select a GST unit and enter a positive quantity/).waitFor();
            assert.equal(writes, 0, 'Partial configuration is rejected before persistence');
            assert.equal(await unit.inputValue(), code, 'An invalid submit preserves the edit draft');
        }
        await quantity.fill(value);
        if (width === 1280) await save.click(); else await save.press('Enter');
        await page.waitForURL(origin + '/admin/products');
        assert.equal(product.gst_uqc, code);
        assert.equal(product.gst_qty_per_unit, value);
        await page.goto(edit);
        assert.equal(await unit.inputValue(), code);
        assert.equal(await quantity.inputValue(), value);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));

        await page.goto(report);
        const summary = page.getByText('HSN reconciliation by recipient, unit and tax rate', {exact:true});
        await summary.press('Space');
        const table = page.getByRole('region', {name:'HSN reconciliation',exact:true});
        assert.deepEqual(await table.locator('tbody tr').evaluateAll(rows =>
            rows.map(row => Array.from(row.cells).slice(0,4).map(cell => cell.textContent.trim()))),
            [['3307','B2C','Unrecorded','—'],['3307','B2C','KGS','0.750'],['3307','B2C','PCS','3.000']]);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    }
    const [csvDownload] = await Promise.all([
        page.waitForEvent('download'), page.getByRole('link',{name:'Download invoice lines CSV'}).click(),
    ]);
    const csv = await readFile(await csvDownload.path(), 'utf8');
    assert.match(csv, /Review,UQC,"Quantity in UQC"/);
    assert.match(csv, /,KGS,0\.750\r?\n/);
    assert.match(csv, /,PCS,3\.000\r?\n/);
    assert.match(csv, /Missing or invalid issued GST unit or quantity/);
    await page.goto(origin + '/admin/orders/' + order.id + '/invoice');
    await page.getByText('0.750 KGS',{exact:true}).waitFor();
    await page.getByText('3.000 PCS',{exact:true}).waitFor();

    await page.goto(edit);
    await unit.selectOption('');
    await quantity.fill('');
    await save.press('Enter');
    await page.waitForURL(origin + '/admin/products');
    assert.equal(product.gst_uqc, '');
    assert.equal(product.gst_qty_per_unit, '');
    assert.equal(order.tax_lines[0].uqc, 'KGS', 'Clearing catalogue fields cannot rewrite an issued invoice');
});
