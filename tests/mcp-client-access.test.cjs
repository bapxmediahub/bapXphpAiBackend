const assert = require('node:assert/strict');
const { spawn, execFileSync } = require('node:child_process');
const { randomBytes } = require('node:crypto');
const { once } = require('node:events');
const { mkdtemp, rm } = require('node:fs/promises');
const { createServer } = require('node:net');
const { tmpdir } = require('node:os');
const path = require('node:path');
const { createInterface } = require('node:readline');
const { test } = require('node:test');

test('owner issues, uses and revokes a scoped local MCP credential', {timeout:30000}, async () => {
    const root = path.resolve(__dirname, '..');
    const temp = await mkdtemp(path.join(tmpdir(), 'mcp-grant-'));
    const label = 'Fixture ' + randomBytes(10).toString('hex');
    const port = process.env.BAPX_MCP_FIXTURE_PORT || '33068';
    const env = {...process.env, BAPX_MYSQL_HOST:'127.0.0.1', BAPX_MYSQL_PORT:port,
        BAPX_MYSQL_DB:'sps_order_test', BAPX_MYSQL_USER:'sps_test', BAPX_MYSQL_PASS:'sps_ci_fixture',
        BAPX_REMOTE_DB_URL:'http://127.0.0.1:1/remotedb', BAPX_TEST_MODE:'0', BAPX_MCP_CLIENT_TOKEN:'',
        MCP_LABEL:label};
    const sessionOpts = ['-d', 'session.save_path=' + temp];
    const php = (code, extra = {}) => execFileSync('php', sessionOpts, {cwd:root, env:{...env, ...extra}, encoding:'utf8',
        input:"<?php require 'app/bootstrap.php'; $db=new App\\Services\\DatabaseService(true); " + code});
    php(`foreach (['settings','secrets','orders','audit_events'] as $table) {
        $db->connection()->exec("CREATE TABLE IF NOT EXISTS $table (id VARCHAR(64) PRIMARY KEY, _data JSON NOT NULL,
            _owner VARCHAR(255), _status VARCHAR(64), _created_at VARCHAR(40), _updated_at VARCHAR(40))"); }
        session_abort(); session_id('mcpownerfixture');session_start();$_SESSION['user']=['role'=>'admin'];session_write_close();
        session_id('mcpcustomerfixture');session_start();$_SESSION['user']=['role'=>'customer'];session_write_close();`);
    const socket = createServer();
    socket.listen(0,'127.0.0.1');
    await once(socket,'listening');
    const host = '127.0.0.1:' + socket.address().port;
    await new Promise(resolve => socket.close(resolve));
    const server = spawn('php', [...sessionOpts, '-S', host, 'index.php'], {cwd:root,env,stdio:'ignore'});
    const clients = [];
    let grantId;
    const web = (suffix = '', body, session = 'mcpownerfixture') => fetch('http://' + host + '/admin/mcp-clients' + suffix,
        {method:body ? 'POST' : 'GET', body:body && new URLSearchParams(body), redirect:'manual',
            headers:{cookie:'PHPSESSID=' + session}});
    function client(token, useBridge = false) {
        const database = useBridge ? {BAPX_MYSQL_PORT:'1', BAPX_REMOTE_DB_URL:'http://' + host + '/remotedb'} : {};
        const child = spawn('./bapXphp',['mcp:stdio'], {cwd:root, env:{...env, ...database, BAPX_MCP_CLIENT_TOKEN:token}, stdio:['pipe','pipe','ignore']});
        clients.push(child);
        const lines = createInterface({input:child.stdout})[Symbol.asyncIterator]();
        let id = 0;
        const request = async (method, params = {}) => {
            child.stdin.write(JSON.stringify({jsonrpc:'2.0', id:++id, method, params:{...params,_meta:{
                'io.modelcontextprotocol/protocolVersion':'2026-07-28','io.modelcontextprotocol/clientCapabilities':{}}}}) + '\n');
            return JSON.parse((await lines.next()).value);
        };
        return {list:() => request('tools/list'), call:(args = {}, name = 'paid_sales_summary') =>
            request('tools/call',{name, arguments:args})};
    }
    const names = reply => reply.result.tools.map(tool => tool.name);
    try {
        for (let i=0; i<50; i++) {
            try { await web('', undefined, 'guest'); break; } catch { await new Promise(resolve => setTimeout(resolve, 50)); }
        }
        assert.equal((await web('', undefined,'guest')).headers.get('location'), '/login');
        assert.equal((await web('', undefined,'mcpcustomerfixture')).headers.get('location'), '/');
        const form = await (await web()).text();
        const field = name => form.match(new RegExp('name="' + name + '" value="([^"]+)"'))[1];
        const payload = {client_label:label, issue_nonce:field('issue_nonce'), _csrf:field('_csrf')};
        assert.equal((await web('/issue', {...payload,_csrf:'invalid'})).status,302);
        const issued = await web('/issue',payload);
        assert.equal(issued.status,200);
        assert.match(issued.headers.get('cache-control'), /no-store/);
        const token = (await issued.text()).match(/mcp-client-[a-f0-9]{24}\.[a-f0-9]{64}/)?.[0];
        assert.ok(token, 'Expected a single-display credential');
        grantId = token.split('.')[0];
        const grantEnv = {MCP_GRANT_ID:grantId};
        assert.equal((await web('/issue',payload)).status,302);
        assert.ok(!(await (await web()).text()).includes(token));
        assert.equal(php(`$r=$db->find('secrets',getenv('MCP_GRANT_ID'));
            echo (hash_equals(hash('sha256',getenv('BAPX_MCP_CLIENT_TOKEN')),$r['digest'])
                && !str_contains(json_encode($r),getenv('BAPX_MCP_CLIENT_TOKEN'))
                && !isset((new App\\Services\\SecretService($db))->all()['digest'])) ? 'yes' : 'no';`,
            {...grantEnv, BAPX_MCP_CLIENT_TOKEN:token}), 'yes');
        const guest = client('');
        const publicNames = names(await guest.list());
        assert.ok(publicNames.includes('public_policy') && !publicNames.includes('paid_sales_summary'));
        assert.equal((await guest.call({role:'admin'})).error.code,-32602);
        const wrong = client(grantId + '.' + '0'.repeat(64));
        assert.deepEqual(names(await wrong.list()),publicNames);
        assert.equal((await wrong.call()).error.code,-32602);
        const reporting = client(token);
        const discovery = await reporting.list();
        assert.equal(discovery.result.cacheScope,'private');
        assert.deepEqual(names(discovery),[...publicNames,'paid_sales_summary']);
        const cliList = JSON.parse(execFileSync('./bapXphp',['mcp:tools'],{env:{...env,BAPX_MCP_CLIENT_TOKEN:token},cwd:root}));
        assert.deepEqual(cliList.tools.map(tool=>tool.name),publicNames);
        for (const args of [{days:1},{days:91},{days:'7'},{days:null},{order_id:'fixture'},{email:'fixture@example.test'}]) {
            assert.equal((await reporting.call(args)).result.isError,true);
        }
        for (const name of ['find_order','db','own_ticket_status']) assert.equal((await reporting.call({},name)).error.code,-32602);
        const before = (await reporting.call({days:7})).result.structuredContent;
        const bridged = client(token, true);
        assert.deepEqual(names(await bridged.list()), names(discovery));
        assert.deepEqual((await bridged.call({days:7})).result.structuredContent, before);
        php(`$db->upsert('orders',['id'=>getenv('MCP_LABEL'),'created_at'=>date('c'),'status'=>'paid','payment_id'=>'pay_fixture_private',
            'customer_email'=>'fixture-private@example.test','total'=>234.5,'verified_refunds'=>[['amount_paise'=>1450]]]);`);
        const reply = await reporting.call({days:7});
        const value = reply.result.structuredContent;
        assert.deepEqual(Object.keys(value).sort(),['from','grossInr','netInr','paidOrders','refundedInr','to']);
        assert.equal(value.paidOrders-before.paidOrders,1);
        assert.equal(Math.round((value.grossInr-before.grossInr)*100),23450);
        assert.equal(Math.round((value.refundedInr-before.refundedInr)*100),1450);
        assert.equal(Math.round((value.netInr-before.netInr)*100),22000);
        assert.deepEqual(JSON.parse(reply.result.content[0].text),value);
        assert.ok(!JSON.stringify(reply).includes('fixture') && !JSON.stringify(reply).includes(token));
        const activePage = await (await web()).text();
        assert.ok(activePage.includes(label));
        assert.ok(php(`foreach((new App\\Services\\McpClientService($db))->clients() as $client) {
            if($client['id']===getenv('MCP_GRANT_ID')) echo $client['last_used'] ? 'used':'unused'; }`,grantEnv) === 'used');
        assert.equal((await web('/revoke', {client_id:grantId,_csrf:'invalid'})).status,302);
        assert.ok((await reporting.call()).result.structuredContent);
        // Check stored scope and expiry against an already-running process.
        const set = expr => php(`$r=$db->find('secrets',getenv('MCP_GRANT_ID')); ${expr}; $db->upsert('secrets',$r);`,grantEnv);
        set("$r['scope']='unrelated'");
        assert.deepEqual(names(await reporting.list()),publicNames);
        assert.equal((await reporting.call()).error.code,-32602);
        set("$r['scope']='paid_sales_summary'; $r['expires_at']='2000-01-01T00:00:00Z'");
        assert.deepEqual(names(await reporting.list()),publicNames);
        assert.equal((await reporting.call()).error.code,-32602);
        set("$r['expires_at']=date(DATE_ATOM,time()+3600)");
        assert.equal((await web('/revoke',{client_id:grantId,_csrf:payload._csrf})).status,302);
        assert.deepEqual(names(await reporting.list()),publicNames);
        assert.equal((await reporting.call()).error.code,-32602);
        assert.deepEqual(names(await bridged.list()),publicNames);
        assert.equal((await bridged.call()).error.code,-32602);
        assert.ok(!Object.hasOwn(await reporting.call({key:'tracking'},'public_policy'),'error'));
        assert.equal(php(`$r=$db->find('secrets',getenv('MCP_GRANT_ID'));echo empty($r['digest']) && $r['revoked_at'] ? 'revoked' : 'active';`,grantEnv),'revoked');
    } finally {
        for (const child of clients) child.stdin.end();
        server.kill();
        php(`$db->delete('orders',getenv('MCP_LABEL'));
            $q=$db->connection()->prepare("DELETE FROM secrets WHERE JSON_UNQUOTE(JSON_EXTRACT(_data, '$.client_label')) = ?");
            $q->execute([getenv('MCP_LABEL')]); if(getenv('MCP_GRANT_ID')){
            $q=$db->connection()->prepare("DELETE FROM audit_events WHERE JSON_UNQUOTE(JSON_EXTRACT(_data, '$.resource_id')) = ?");
            $q->execute([getenv('MCP_GRANT_ID')]);}`, {MCP_GRANT_ID:grantId || ''});
        // CI shares this disposable schema; later order tests must create their own table.
        php('$db->connection()->exec("DROP TABLE IF EXISTS orders");');
        await rm(temp,{recursive:true,force:true});
    }
});
