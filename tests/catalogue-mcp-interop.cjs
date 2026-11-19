const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { createRequire } = require('node:module');
const path = require('node:path');
const { test } = require('node:test');

// Install @modelcontextprotocol/client@2.4.0 in a scratch directory and pass that directory as the first argument.
const sdk = createRequire(path.resolve(process.argv[2] || '.', 'package.json'));
const { Client } = sdk('@modelcontextprotocol/client');
const { StdioClientTransport } = sdk('@modelcontextprotocol/client/stdio');

test('official client discovers the CLI schemas and reads public store policies on revision 2026-07-28', async () => {
    const env = { ...process.env, BAPX_TEST_MODE: '1' };
    const cli = args => JSON.parse(execFileSync('./bapXphp', args, { env, encoding: 'utf8' }));
    const client = new Client({ name: 'catalogue-interop', version: '1' }, { versionNegotiation: { mode: { pin: '2026-07-28' } } });
    try {
        await client.connect(new StdioClientTransport({ command: path.resolve('bapXphp'), args: ['mcp:stdio'], env }));
        const { supportedVersions } = await client.discover();
        assert.deepEqual(supportedVersions, ['2026-07-28']);
        const { tools } = await client.listTools();
        assert.deepEqual(tools, cli(['mcp:tools']).tools);
        const policy = tools.find(tool => tool.name === 'public_policy');
        for (const key of policy.inputSchema.properties.key.enum) {
            const result = await client.callTool({ name: policy.name, arguments: { key } });
            assert.deepEqual(result.structuredContent, cli(['mcp:policy', key]));
        }
        const invalid = await client.callTool({ name: policy.name, arguments: { key: 'tracking', role: 'admin' } });
        assert.equal(invalid.isError, true);
        await assert.rejects(client.callTool({ name: 'find_order', arguments: {} }));
    } finally {
        await client.close();
    }
});
