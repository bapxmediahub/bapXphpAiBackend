const assert = require('node:assert/strict');
const { spawn, execFileSync } = require('node:child_process');
const { once } = require('node:events');
const net = require('node:net');
const { createInterface } = require('node:readline');
const { test } = require('node:test');

test('two requests in one stdio process survive loss of the first MySQL connection', { timeout: 15000 }, async () => {
    const port = Number(process.env.BAPX_MYSQL_PORT || 33068);
    const fixtureEnv = {
        ...process.env,
        BAPX_MYSQL_HOST: '127.0.0.1', BAPX_MYSQL_PORT: String(port), BAPX_MYSQL_DB: 'sps_order_test',
        BAPX_MYSQL_USER: 'sps_test', BAPX_MYSQL_PASS: 'sps_ci_fixture', BAPX_TEST_MODE: '0',
        BAPX_REMOTE_DB_URL: 'http://127.0.0.1:1/remotedb'
    };
    execFileSync('php', ['-r', `require 'app/bootstrap.php';
        (new App\\Services\\DatabaseService(true))->connection()->exec(
            'CREATE TABLE IF NOT EXISTS settings (id VARCHAR(64) PRIMARY KEY, _data JSON NOT NULL,
                _owner VARCHAR(255), _status VARCHAR(64), _created_at VARCHAR(40), _updated_at VARCHAR(40))'
        );`], { env: fixtureEnv });

    // Forward only this test's own PDO sockets. Dropping them simulates an idle MySQL disconnect.
    const sockets = new Set();
    let connections = 0;
    const proxy = net.createServer(downstream => {
        const upstream = net.connect({ host: '127.0.0.1', port });
        connections++;
        for (const socket of [upstream, downstream]) {
            sockets.add(socket);
            socket.on('close', () => sockets.delete(socket));
        }
        downstream.on('error', () => upstream.destroy());
        upstream.on('error', () => downstream.destroy());
        downstream.pipe(upstream).pipe(downstream);
    });
    proxy.listen(0, '127.0.0.1');
    await once(proxy, 'listening');
    const child = spawn('./bapXphp', ['mcp:stdio'], {
        env: { ...fixtureEnv, BAPX_MYSQL_PORT: String(proxy.address().port) },
        stdio: ['pipe', 'pipe', 'inherit']
    });
    const replies = createInterface({ input: child.stdout })[Symbol.asyncIterator]();
    const list = async id => {
        child.stdin.write(JSON.stringify({ jsonrpc: '2.0', id, method: 'tools/list', params: { _meta: {
            'io.modelcontextprotocol/protocolVersion': '2026-07-28',
            'io.modelcontextprotocol/clientCapabilities': {}
        } } }) + '\n');
        const reply = JSON.parse((await replies.next()).value);
        assert.equal(reply.id, id);
        assert.equal(reply.error, undefined, reply.error?.message);
        return reply.result.tools;
    };
    try {
        const first = await list(1);
        assert.equal(connections, 1);
        for (const socket of sockets) socket.destroy();
        assert.deepEqual(await list(2), first);
        assert.equal(connections, 2);
    } finally {
        child.stdin.end();
        await once(child, 'exit');
        for (const socket of sockets) socket.destroy();
        await new Promise(resolve => proxy.close(resolve));
    }
});
