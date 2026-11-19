const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');
const { createServer } = require('node:http');
const { createInterface } = require('node:readline');
const { once } = require('node:events');
const { test } = require('node:test');

test('local catalogue MCP checks the current shop settings and publishes only visible product fields', { timeout: 15000 }, async () => {
    let enabled = true;
    let online = true;
    const records = [
        { id: '1', slug: 'pendant', name: 'Pendant', price: 400, private_cost: 20 },
        { id: '2', slug: 'ring', name: 'Ring', price: 100, stock_status: 'out_of_stock' },
        { id: '3', slug: 'draft', name: 'Preview', price: 300, status: 'draft' }
    ];
    const reads = new Set();
    const bridge = createServer(async (request, response) => {
        let body = '';
        for await (const chunk of request) body += chunk;
        const { query } = JSON.parse(body);
        reads.add(query);
        const rows = query === 'SELECT * FROM settings'
            ? [{ id: 'app_settings', module_shop: enabled ? '1' : '0' }]
            : records;
        response.writeHead(online ? 200 : 503, { 'Content-Type': 'application/json' });
        response.end(JSON.stringify({ success: online, data: rows.map(row => ({ id: row.id, _data: JSON.stringify(row) })) }));
    });
    bridge.listen(0, '127.0.0.1');
    await once(bridge, 'listening');
    const child = spawn('./bapXphp', ['mcp:stdio'], {
        env: { ...process.env, BAPX_TEST_MODE: '0', BAPX_MYSQL_HOST: '127.0.0.1', BAPX_MYSQL_PORT: '1',
            BAPX_MYSQL_DB: 'fixture', BAPX_MYSQL_USER: 'fixture', BAPX_MYSQL_PASS: 'fixture',
            BAPX_REMOTE_DB_URL: 'http://127.0.0.1:' + bridge.address().port + '/remotedb' },
        stdio: ['pipe', 'pipe', 'inherit']
    });
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
        assert.deepEqual(list.map(tool => tool.name), ['search_public_products']);
        assert.equal(list[0].annotations.readOnlyHint, true);
        assert.equal((await request('tools/call', { name: 'admin_report' })).error.code, -32602);
        assert.equal((await call({ role: 'owner' })).result.isError, true);
        assert.equal((await call({ limit: 21 })).result.isError, true);
        assert.equal((await call({ query: null })).result.isError, true);
        assert.equal((await call([])).error.code, -32602);
        const first = (await call({ limit: 1 })).result.structuredContent;
        assert.deepEqual(first, { products: [{ name: 'Pendant', path: '/product/pendant', priceInr: 400, available: true }], total: 2, nextOffset: 1 });
        const last = (await call({ offset: first.nextOffset })).result.structuredContent;
        assert.deepEqual(last, { products: [{ name: 'Ring', path: '/product/ring', priceInr: 100, available: false }], total: 2, nextOffset: null });
        assert.deepEqual((await call({ limit: 1 })).result.structuredContent, first);
        records[0].status = 'hidden';
        assert.equal((await call({ query: 'Pendant' })).result.structuredContent.total, 0);
        enabled = false;
        assert.deepEqual((await request('tools/list')).result.tools, []);
        assert.equal((await call({})).error.code, -32602);
        enabled = true;
        assert.equal((await call({})).result.structuredContent.total, 1);
        online = false;
        assert.equal((await request('tools/list')).error.code, -32603);
        assert.equal((await call({})).error.code, -32603);
        assert.deepEqual([...reads].sort(), ['SELECT * FROM products', 'SELECT * FROM settings']);
    } finally {
        child.stdin.end();
        const [code] = await once(child, 'exit');
        await new Promise(resolve => bridge.close(resolve));
        assert.equal(code, 0);
    }
});
