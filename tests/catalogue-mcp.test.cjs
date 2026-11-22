const assert = require('node:assert/strict');
const { spawn, execFile } = require('node:child_process');
const run = require('node:util').promisify(execFile);
const { createServer } = require('node:http');
const { createInterface } = require('node:readline');
const { once } = require('node:events');
const { test } = require('node:test');

test('local catalogue MCP checks the current shop settings and publishes only visible product fields', { timeout: 15000 }, async () => {
    let enabled = true;
    let online = true;
    const records = [
        { id: '1', slug: 'pendant', name: 'Pendant', price: 400, private_cost: 20,
            description: '<p>Published <strong>pendant</strong> details.</p><script>hiddenCode()</script>',
            image_url: 'https://public.example/assets/images/media/pendant.webp',
            image_urls: ['/assets/images/media/pendant.webp','/assets/images/media/pendant-back.webp'],
            highlights: ['Published pendant highlight'], specifications: { Material: 'Published alloy' } },
        { id: '2', slug: 'ring', name: 'Ring', price: 100, stock_status: 'out_of_stock',
            image_url: 'https://untrusted.invalid/assets/images/ring.webp' },
        { id: '3', slug: 'draft', name: 'Preview', price: 300, status: 'draft' }
    ];
    const reads = new Set();
    const bridge = createServer(async (request, response) => {
        let body = '';
        for await (const chunk of request) body += chunk;
        const { query } = JSON.parse(body);
        reads.add(query);
        const rows = query === "SELECT id, _data FROM settings WHERE id NOT LIKE 'pg:%'"
            ? [{ id: 'app_settings', module_shop: enabled ? '1' : '0' }]
            : records;
        response.writeHead(online ? 200 : 503, { 'Content-Type': 'application/json' });
        response.end(JSON.stringify({ success: online, data: rows.map(row => ({ id: row.id, _data: JSON.stringify(row) })) }));
    });
    bridge.listen(0, '127.0.0.1');
    await once(bridge, 'listening');
    const env = { ...process.env, APP_URL: 'https://public.example', BAPX_TEST_MODE: '0', BAPX_MYSQL_HOST: '127.0.0.1', BAPX_MYSQL_PORT: '1',
        BAPX_MYSQL_DB: 'fixture', BAPX_MYSQL_USER: 'fixture', BAPX_MYSQL_PASS: 'fixture',
        BAPX_REMOTE_DB_URL: 'http://127.0.0.1:' + bridge.address().port + '/remotedb' };
    const child = spawn('./bapXphp', ['mcp:stdio'], { env, stdio: ['pipe', 'pipe', 'inherit'] });
    const cli = async args => JSON.parse((await run('./bapXphp', args, { env })).stdout);
    const lines = createInterface({ input: child.stdout })[Symbol.asyncIterator]();
    let id = 0;
    const meta = { 'io.modelcontextprotocol/protocolVersion': '2026-07-28', 'io.modelcontextprotocol/clientCapabilities': {} };
    const raw = async line => {
        child.stdin.write(line + '\n');
        const { value, done } = await lines.next();
        assert.equal(done, false);
        return JSON.parse(value);
    };
    const request = async (method, params = {}, metadata = meta) => {
        id++;
        const reply = await raw(JSON.stringify({ jsonrpc: '2.0', id, method, params: { _meta: metadata, ...params } }));
        assert.equal(reply.id, id);
        return reply;
    };
    const call = args => request('tools/call', { name: 'search_public_products', arguments: args });
    try {
        assert.equal((await raw('{')).error.code, -32700);
        assert.equal((await raw('x'.repeat(80000))).error.code, -32600);
        assert.equal((await request('tools/list', {}, {})).error.code, -32602);
        const badVersion = await request('server/discover', {}, { ...meta, 'io.modelcontextprotocol/protocolVersion': '1999-01-01' });
        assert.deepEqual(badVersion.error.data.supported, ['2026-07-28']);
        assert.equal(badVersion.error.code, -32022);
        child.stdin.write(JSON.stringify({ jsonrpc: '2.0', method: 'notifications/cancelled' }) + '\n');
        assert.deepEqual((await request('server/discover')).result.supportedVersions, ['2026-07-28']);
        const list = (await request('tools/list')).result.tools;
        assert.deepEqual(list.map(tool => tool.name), ['search_public_products', 'public_policy', 'search_public_articles']);
        assert.deepEqual((await cli(['mcp:tools'])).tools, list);
        const articles = args => request('tools/call', { name: 'search_public_articles', arguments: args });
        assert.equal(list[2].inputSchema.additionalProperties, false);
        assert.deepEqual(list[2].outputSchema.required, ['articles','total','nextOffset']);
        assert.equal((await articles({ query: 'nonexistent-public-article-fixture', limit: 1 })).result.structuredContent.total, 0);
        assert.equal((await articles({ limit: 21 })).result.isError, true);
        assert.equal((await articles({ query: '../.bapX/enterpriseagent.md' })).result.structuredContent.total, 0);
        const policy = args => request('tools/call', { name: 'public_policy', arguments: args });
        const keys = list[1].inputSchema.properties.key.enum;
        assert.deepEqual(keys, ['tracking', 'returns_or_problems', 'shipping_cost', 'payment_methods']);
        for (const key of keys) {
            const answer = (await policy({ key })).result;
            assert.equal(typeof answer.structuredContent.value, 'string');
            assert.ok(answer.structuredContent.value.length > 0);
            assert.deepEqual(await cli(['mcp:policy', key]), answer.structuredContent);
        }
        for (const args of [{}, { key: 'orders' }, { key: ['tracking'] }, { key: 'tracking', role: 'admin' }]) {
            assert.equal((await policy(args)).result.isError, true);
        }
        for (const name of ['own_ticket_status', 'find_order', 'public_catalog']) {
            assert.equal((await request('tools/call', { name, arguments: { role: 'admin' } })).error.code, -32602);
        }
        await assert.rejects(cli(['mcp:policy', 'orders']), { code: 2 });
        await assert.rejects(cli(['mcp:tools', 'admin']), { code: 2 });
        assert.equal(list[0].annotations.readOnlyHint, true);
        assert.equal((await request('tools/call', { name: 'admin_report' })).error.code, -32602);
        assert.equal((await call({ role: 'owner' })).result.isError, true);
        assert.equal((await call({ limit: 21 })).result.isError, true);
        assert.equal((await call({ query: null })).result.isError, true);
        assert.equal((await call([])).error.code, -32602);
        const first = (await call({ limit: 1 })).result.structuredContent;
        assert.deepEqual(first, { products: [{ name: 'Pendant', path: '/product/pendant', productUrl: 'https://public.example/product/pendant', priceInr: 400, available: true,
            description: 'Published pendant details.', imagePath: '/assets/images/media/pendant.webp', imageUrl: 'https://public.example/assets/images/media/pendant.webp' }], total: 2, nextOffset: 1 });
        const last = (await call({ offset: first.nextOffset })).result.structuredContent;
        assert.deepEqual(last, { products: [{ name: 'Ring', path: '/product/ring', productUrl: 'https://public.example/product/ring', priceInr: 100, available: false, description: '' }], total: 2, nextOffset: null });
        assert.deepEqual((await call({ limit: 1 })).result.structuredContent, first);
        const selected=(await call({slugs:['ring','pendant'],limit:2})).result.structuredContent;
        assert.deepEqual(selected.products.map(row=>row.path),['/product/ring','/product/pendant']);
        assert.deepEqual(selected.products[1].imagePaths,['/assets/images/media/pendant.webp','/assets/images/media/pendant-back.webp']);
        assert.deepEqual(selected.products[1].imageUrls,['https://public.example/assets/images/media/pendant.webp','https://public.example/assets/images/media/pendant-back.webp']);
        assert.equal(selected.products[0].imageUrl,undefined,'A foreign image cannot gain an official absolute URL.');
        assert.deepEqual(selected.products[1].highlights,['Published pendant highlight']);
        assert.deepEqual(selected.products[1].specifications,[{label:'Material',value:'Published alloy'}]);
        assert.equal(selected.nextOffset,null);
        assert.equal((await call({slugs:['draft','missing']})).result.structuredContent.total,0);
        for(const args of [{slugs:'pendant'},{slugs:['pendant'],query:'pendant'},
            {slugs:['pendant'],offset:1},{slugs:['ring','pendant'],limit:1},{slugs:['../private']}]) {
            assert.equal((await call(args)).result.isError,true);
        }
        for (const image of ['/assets/images/../private.png', '/assets/images/%2e%2e/private.png',
            '/assets/images/%252e%252e/private.png', '/assets/images/media/a%2fb.webp',
            'https://public.example:444/assets/images/pendant.webp',
            'https://public.example@untrusted.invalid/assets/images/pendant.webp',
            '//public.example/assets/images/pendant.webp', '/assets/images/pendant.svg',
            '/assets/images/pendant.webp?redirect=private', 'data:image/png;base64,AAAA']) {
            records[0].image_url = image;
            assert.equal((await call({ query: 'pendant' })).result.structuredContent.products[0].imagePath, undefined);
        }
        records[0].image_url = '/assets/images/media/pendant%20photo.webp';
        records[0].description = '<p>' + 'x'.repeat(400) + '</p>';
        const bounded = (await call({ query: 'pendant' })).result.structuredContent.products[0];
        assert.equal(bounded.imagePath, '/assets/images/media/pendant%20photo.webp');
        assert.equal(bounded.description.length, 280);
        records[0].name = '𐐀'.repeat(161);
        assert.equal((await call({ query: 'pendant' })).result.structuredContent.products[0].name, '𐐀'.repeat(160));
        records[0].name = 'Pendant';
        for (const slug of ['..', '../admin', '%2e%2e', 'item%2Fprivate', 'x'.repeat(2040), 'தமிழ்'.repeat(46)]) {
            records[0].slug = slug;
            const safePage = (await call({ limit: 1 })).result.structuredContent;
            assert.equal(safePage.total, 1);
            assert.equal(safePage.products[0].path, '/product/ring');
            assert.equal(safePage.nextOffset, null);
        }
        records[0].slug = '珊瑚 pendant';
        assert.equal((await call({ query: 'pendant' })).result.structuredContent.products[0].path, '/product/%E7%8F%8A%E7%91%9A%20pendant');
        records[0].slug = 'தமிழ் pendant';
        records[0].image_url = '/assets/images/media/தமிழ்.png';
        const tamil = (await call({ query: 'pendant' })).result.structuredContent.products[0];
        assert.equal(tamil.path, '/product/%E0%AE%A4%E0%AE%AE%E0%AE%BF%E0%AE%B4%E0%AF%8D%20pendant');
        assert.equal(tamil.imagePath, '/assets/images/media/%E0%AE%A4%E0%AE%AE%E0%AE%BF%E0%AE%B4%E0%AF%8D.png');
        records[0].slug = 'pendant';
        records[0].status = 'hidden';
        assert.equal((await call({ query: 'Pendant' })).result.structuredContent.total, 0);
        enabled = false;
        assert.deepEqual((await request('tools/list')).result.tools, [list[1], list[2]]);
        assert.deepEqual((await cli(['mcp:tools'])).tools, [list[1], list[2]]);
        assert.equal((await policy({ key: 'tracking' })).result.isError, undefined);
        assert.equal((await call({})).error.code, -32602);
        enabled = true;
        assert.equal((await call({})).result.structuredContent.total, 1);
        online = false;
        assert.equal((await request('tools/list')).error.code, -32603);
        await assert.rejects(cli(['mcp:tools']), { code: 1 });
        assert.deepEqual((await policy({ key: 'tracking' })).result.structuredContent, await cli(['mcp:policy', 'tracking']));
        assert.equal((await call({})).error.code, -32603);
        assert.deepEqual([...reads].sort(), ['SELECT * FROM products', "SELECT id, _data FROM settings WHERE id NOT LIKE 'pg:%'"]);
    } finally {
        child.stdin.end();
        const [code] = await once(child, 'exit');
        await new Promise(resolve => bridge.close(resolve));
        assert.equal(code, 0);
    }
});
