const assert = require('node:assert/strict');
const { execFile } = require('node:child_process');
const { mkdtemp, writeFile, rm } = require('node:fs/promises');
const { tmpdir } = require('node:os');
const { join, resolve } = require('node:path');
const { promisify } = require('node:util');
const { test } = require('node:test');

const execute = promisify(execFile);
const root = resolve(__dirname, '..');

test('public knowledge controllers validate arguments before reads and expose only public facts', { timeout: 90000 }, async () => {
    const directory = await mkdtemp(join(tmpdir(), 'bapx-public-api-'));
    const fixture = join(directory, 'invoke.php');
    await writeFile(fixture, `<?php
declare(strict_types=1);
putenv('BAPX_TEST_MODE=1');
require getcwd().'/app/bootstrap.php';
$_GET=json_decode($argv[2],true,512,JSON_THROW_ON_ERROR);
$methods=['contact'=>'knowledgeContact','services'=>'knowledgeServices','products'=>'knowledgeProducts','articles'=>'knowledgeArticles','a2ui'=>'knowledgeA2ui'];
register_shutdown_function(static function():void { fwrite(STDERR,"\\nSTATUS:".(int)http_response_code()."\\n"); });
(new App\\Controllers\\ApiController())->{$methods[$argv[1]]}();
`);
    const call = async (method, args) => {
        const result = await execute('php', [fixture, method, JSON.stringify(args)], {
            cwd: root,
            env: { ...process.env, BAPX_TEST_MODE: '1' },
            timeout: 15000,
            maxBuffer: 1024 * 128,
        });
        const status = /STATUS:(\d+)/.exec(result.stderr);
        assert.ok(status, 'Controller must set an explicit response status');
        return { status: Number(status[1]), body: JSON.parse(result.stdout) };
    };
    try {
        const contact = await call('contact', {});
        assert.equal(contact.status, 200);
        assert.deepEqual(Object.keys(contact.body), ['name', 'path', 'phones', 'email', 'hours', 'servicePath']);
        assert.equal(contact.body.path, '/contact');
        assert.equal(contact.body.email, 'support@sripanchamispiritual.com');
        const services = await call('services', { q: 'temple guidance', offset: '0', limit: '1' });
        assert.equal(services.status, 200);
        assert.equal(services.body.total, 1);
        assert.equal(services.body.services[0].path, '/contact?subject=temple#contact-form');
        const selection=await call('products',{slugs:['missing-published-fixture'],limit:'1'});
        assert.equal(selection.status,200);
        assert.deepEqual(selection.body,{products:[],total:0,nextOffset:null});
        for(const view of ['contact','services']) {
            const result=await call('a2ui',{view,limit:'4'});
            assert.equal(result.status,200);
            const surface=result.body.messages[0].createSurface;
            assert.ok(surface.surfaceId.startsWith('bapx_'+view+'_'));
            assert.equal(surface.dataModel.items.length,4);
            assert.ok(surface.dataModel.items.every(item=>item.path.startsWith('/contact')));
        }

        for (const [method, args] of [
            ['contact', { role: 'admin' }],
            ['services', { q: ['private'] }],
            ['services', { q: 'x'.repeat(121) }],
            ['services', { limit: '21' }],
            ['services', { offset: '-1' }],
            ['products', { offset: '2147483648' }],
            ['articles', { offset: '999999999999999999999' }],
            ['products', { limit: '2', actor: 'admin' }],
            ['products', { slugs: 'item' }],
            ['products', { slugs: null }],
            ['products', { slugs: ['../private'] }],
            ['products', { slugs: ['item'], q: 'item' }],
            ['products', { slugs: ['item'], offset: '1' }],
            ['products', { slugs: ['a','b'], limit: '1' }],
            ['a2ui', { view: 'admin', limit: '1' }],
            ['a2ui', { view: 'contact', actor: 'admin' }],
        ]) {
            const response = await call(method, args);
            assert.equal(response.status, 400, method + ' must reject ' + Object.keys(args).join(','));
            assert.deepEqual(Object.keys(response.body), ['error']);
        }
    } finally {
        await rm(directory, { recursive: true, force: true });
    }
});
