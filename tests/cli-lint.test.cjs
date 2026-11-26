'use strict';

const assert = require('node:assert/strict');
const {createHash} = require('node:crypto');
const {execFile} = require('node:child_process');
const {access, chmod, copyFile, mkdir, mkdtemp, readFile, rm, writeFile} = require('node:fs/promises');
const {tmpdir} = require('node:os');
const {dirname, join, resolve} = require('node:path');
const {promisify} = require('node:util');
const {before, test} = require('node:test');

const execute = promisify(execFile);
const sourceRoot = resolve(__dirname, '..');
const valid = '<?php\nfunction valid_fixture(): void {}\n';
const malformed = '<?php\nfunction broken_fixture( {\n';
const env = {PATH: process.env.PATH, LC_ALL: 'C', TERM: 'dumb',
    APP_URL: 'https://fixture.invalid', BAPX_TEST_MODE: '1'};

async function invoke(command, args, cwd) {
    try {
        const result = await execute(command, args, {cwd, env, timeout: 20000, maxBuffer: 1024 * 1024});
        return {status: 0, output: result.stdout + result.stderr};
    } catch (error) {
        if (typeof error.code !== 'number' || error.signal) throw error;
        return {status: error.code, output: (error.stdout || '') + (error.stderr || '')};
    }
}

before(async () => {
    // A wrapper that prints parser diagnostics but exits zero cannot validate this
    // contract. Fail visibly instead of skipping or inferring failure from text.
    const directory = await mkdtemp(join(tmpdir(), 'bapx-native-parser-'));
    try {
        const file = join(directory, 'malformed PHP fixture.php');
        await writeFile(file, malformed);
        const result = await invoke('php', ['-l', file], directory);
        assert.notEqual(result.status, 0, 'This regression requires a real PHP parser that exits nonzero on invalid syntax');
        assert.ok(result.output.includes(file), 'The parser must retain its failed filename');
    } finally {
        await rm(directory, {recursive: true, force: true});
    }
});

async function project(t) {
    const directory = await mkdtemp(join(tmpdir(), 'bapx lint project '));
    t.after(() => rm(directory, {recursive: true, force: true}));
    for (const name of ['app', 'api', 'tests', 'cli', 'views', 'docs']) {
        await mkdir(join(directory, name));
    }
    // Execute the actual launchers, unchanged, in an isolated miniature project.
    // No root override, environment files, application data or production writes.
    for (const name of ['bapXphp', 'cli/bapXphp']) {
        await copyFile(join(sourceRoot, name), join(directory, name));
        await chmod(join(directory, name), 0o755);
    }
    const write = async (name, body) => {
        const file = join(directory, name);
        await mkdir(dirname(file), {recursive: true});
        await writeFile(file, body);
        return file;
    };
    await write('index.php', valid);
    // Only non-lint CI stages are fixture stand-ins. They are valid PHP executed
    // by the same real interpreter; generators produce their expected artifacts.
    for (const [file, marker] of Object.entries({
        'tests/run.php': 'test', 'tests/storefront-search.php': 'storefront-search',
        'cli/agent-contracts.php': 'contracts', 'cli/rea-capabilities.php': 'rea-capabilities',
        'cli/docs-check.php': 'docs', 'cli/validate-project-map.php': 'map',
        'cli/validate-docs-map.php': 'docs-map', 'cli/smoke-local.php': 'smoke',
    })) await write(file, '<?php\necho "FIXTURE:' + marker + '-ok\\n";\n');
    for (const name of [
        'contact-admission', 'campaign-booking', 'campaign-form-http', 'editor-draft', 'media-delete-integration',
        'deployment-revision', 'public-knowledge', 'catalogue-comparison', 'public-product-selection',
        'public-agent-discovery', 'public-mcp', 'remote-cart', 'remote-checkout', 'verified-order',
        'public-mcp-shopping', 'public-mcp-checkout', 'public-a2ui', 'agent-presentation-scope',
        'enterprise-commerce', 'documentation-review',
    ]) await write('tests/' + name + '.php', '<?php\necho "FIXTURE:' + name + '-ok\\n";\n');
    for (const [generator, artifact, content] of [
        ['generate-project-index.php', 'docs/project-index.json', 'fixture project index'],
        ['generate-okf-bundle.php', 'index.yaml', 'fixture knowledge index'],
        ['generate-code-map.php', 'map.mmd', 'fixture code map'],
    ]) {
        await write(artifact, content);
        await write('cli/' + generator, "<?php\nfile_put_contents($argv[2], '" + content + "');\n");
    }
    return {directory, write, run: args => invoke(join(directory, 'bapXphp'), args, directory)};
}

function assertFailure(result, files) {
    assert.notEqual(result.status, 0, 'Malformed PHP must fail the public CLI command');
    for (const file of files) assert.ok(result.output.includes(file), 'Missing failed filename: ' + file);
    assert.doesNotMatch(result.output, /PHP syntax check complete|ALL CHECKS PASSED|No syntax errors detected/);
}

test('lint forwards an explicit file target and preserves PHP failure status', {timeout: 60000}, async t => {
    const fixture = await project(t);
    const good = await fixture.write('outside source/valid file.php', valid);
    const bad = await fixture.write('outside source/broken file.php', malformed);
    assertFailure(await fixture.run(['lint', 'outside source/broken file.php']), [bad]);
    // A valid explicit file must not accidentally scan an unrelated invalid app.
    const unrelated = await fixture.write('app/unrelated broken.php', malformed);
    const success = await fixture.run(['lint', good]);
    assert.equal(success.status, 0);
    assert.ok(success.output.includes(good));
    assert.equal(success.output.includes(unrelated), false);
    const missing = await fixture.run(['lint', 'outside source/missing.php']);
    assert.notEqual(missing.status, 0);
    assert.match(missing.output, /missing\.php/);
    assert.notEqual((await fixture.run(['lint', good, bad])).status, 0, 'Extra targets must not be silently ignored');
});

test('directory lint reports every invalid file and returns zero for quiet valid scans', {timeout: 60000}, async t => {
    const fixture = await project(t);
    const first = await fixture.write('selected directory/broken one.php', malformed);
    const second = await fixture.write('selected directory/nested folder/broken two.php', malformed);
    await fixture.write('selected directory/valid file.php', valid);
    const directory = join(fixture.directory, 'selected directory');
    assertFailure(await fixture.run(['lint', directory]), [first, second]);
    await writeFile(first, valid);
    await writeFile(second, valid);
    const success = await fixture.run(['lint', directory]);
    assert.equal(success.status, 0);
    assert.equal(success.output.trim(), '', 'Filtering valid lint lines must still return success');
});

test('default lint aggregates parser failures across source roots without a success summary', {timeout: 60000}, async t => {
    const fixture = await project(t);
    const first = await fixture.write('app/broken app.php', malformed);
    const second = await fixture.write('views/nested folder/broken view.php', malformed);
    assertFailure(await fixture.run(['lint']), [first, second]);
    await writeFile(first, valid);
    await writeFile(second, valid);
    const success = await fixture.run(['lint']);
    assert.equal(success.status, 0);
    assert.match(success.output, /PHP syntax check complete/);
    assert.doesNotMatch(success.output, /No syntax errors detected/);
    await rm(join(fixture.directory, 'api'), {recursive: true});
    const missingRoot = await fixture.run(['lint']);
    assert.notEqual(missingRoot.status, 0, 'A failed directory traversal must not become a quiet success');
    assert.ok(missingRoot.output.includes(join(fixture.directory, 'api')));
    assert.doesNotMatch(missingRoot.output, /PHP syntax check complete/);
});

test('ci fails only for lint when every unrelated fixture stage succeeds', {timeout: 60000}, async t => {
    const fixture = await project(t);
    const good = await fixture.run(['ci']);
    assert.equal(good.status, 0, good.output);
    assert.match(good.output, /ALL CHECKS PASSED/);
    const first = await fixture.write('api/broken endpoint.php', malformed);
    const second = await fixture.write('views/broken template.php', malformed);
    const failure = await fixture.run(['ci']);
    assertFailure(failure, [first, second]);
    assert.match(failure.output, /LINT FAILED/);
    assert.match(failure.output, /SOME CHECKS FAILED/);
    for (const stage of ['test', 'storefront-search', 'contracts', 'docs', 'map', 'docs-map', 'smoke']) {
        assert.ok(failure.output.includes('FIXTURE:' + stage + '-ok'), 'CI did not finish the successful ' + stage + ' fixture');
    }
    for (const message of ['Project index is current', 'Knowledge index is current', 'Code map is current']) {
        assert.ok(failure.output.includes(message), 'An unrelated generated-artifact fixture did not pass');
    }
    assert.doesNotMatch(failure.output, /TESTS FAILED|STOREFRONT SEARCH FAILED|DOCUMENTATION STALE|REVIEW REQUIRED|MAP VALIDATION FAILED|SMOKE FAILED|INDEX STALE|CODE MAP STALE/);
});

test('ci reports storefront search failure and completes independent gates', {timeout: 60000}, async t => {
    const fixture = await project(t);
    await fixture.write('tests/storefront-search.php',
        '<?php\necho "FIXTURE:storefront-search-error\\n";\nexit(17);\n');
    const failure = await fixture.run(['ci']);
    assert.notEqual(failure.status, 0, failure.output);
    assert.match(failure.output, /FIXTURE:storefront-search-error/);
    assert.match(failure.output, /STOREFRONT SEARCH FAILED/);
    assert.match(failure.output, /SOME CHECKS FAILED/);
    assert.match(failure.output, /PHP syntax check complete/);
    assert.match(failure.output, /FIXTURE:test-ok/);
    assert.match(failure.output, /FIXTURE:smoke-ok/);
    assert.doesNotMatch(failure.output, /LINT FAILED|TESTS FAILED|SMOKE FAILED|ALL CHECKS PASSED/);
});

test('ci executes the portable hosted PHP fixtures and aggregates HTTP fixture failures', {timeout: 60000}, async t => {
    const fixture = await project(t);
    const good = await fixture.run(['ci']);
    assert.equal(good.status, 0, good.output);
    for (const name of [
        'contact-admission', 'campaign-booking', 'campaign-form-http', 'editor-draft', 'media-delete-integration',
        'deployment-revision', 'public-knowledge', 'catalogue-comparison', 'public-product-selection',
        'public-agent-discovery', 'public-mcp', 'remote-cart', 'remote-checkout', 'verified-order',
        'public-mcp-shopping', 'public-mcp-checkout', 'public-a2ui', 'agent-presentation-scope',
        'enterprise-commerce', 'documentation-review',
    ]) assert.match(good.output, new RegExp('FIXTURE:' + name + '-ok'));
    await fixture.write('tests/campaign-form-http.php', '<?php echo "FIXTURE:http-error\\n"; exit(17);');
    await rm(join(fixture.directory, 'tests', 'public-knowledge.php'));
    const failure = await fixture.run(['ci']);
    assert.notEqual(failure.status, 0, failure.output);
    assert.match(failure.output, /PORTABLE FIXTURE FAILED: campaign-form-http/);
    assert.match(failure.output, /PORTABLE FIXTURE FAILED: public-knowledge/);
    assert.match(failure.output, /FIXTURE:documentation-review-ok/);
    assert.match(failure.output, /FIXTURE:smoke-ok/);
    assert.doesNotMatch(failure.output, /ALL CHECKS PASSED/);
});

test('ci reports generator failure, stale index and missing map while still running smoke', {timeout: 60000}, async t => {
    const fixture = await project(t);
    await fixture.write('cli/generate-project-index.php', '<?php fwrite(STDERR, "FIXTURE: unable to generate index\n"); exit(17);');
    await fixture.write('index.yaml', 'outdated knowledge index');
    await rm(join(fixture.directory, 'map.mmd'));
    const result = await fixture.run(['ci']);
    assert.notEqual(result.status, 0, result.output);
    assert.match(result.output, /FIXTURE: unable to generate index/);
    assert.match(result.output, /Project index generation failed/);
    assert.match(result.output, /Knowledge index stale.*index\.yaml/);
    assert.match(result.output, /Code map missing.*map\.mmd/);
    assert.match(result.output, /FIXTURE:smoke-ok/);
    assert.doesNotMatch(result.output, /ALL CHECKS PASSED/);
});

test('map validation reports both maps and concurrent runs use separate scratch outputs', {timeout: 60000}, async t => {
    const fixture = await project(t);
    await fixture.write('cli/validate-project-map.php', '<?php fwrite(STDERR, "FIXTURE: systematic map stale\n"); exit(1);');
    await fixture.write('map.mmd', 'outdated code graph');
    const stale = await fixture.run(['map:val']);
    assert.notEqual(stale.status, 0, stale.output);
    assert.match(stale.output, /FIXTURE: systematic map stale/);
    assert.match(stale.output, /map\.mmd stale/);

    await fixture.write('cli/validate-project-map.php', '<?php echo "FIXTURE: map-ok\n";');
    await fixture.write('map.mmd', 'fixture code map');
    const outputsFile = join(fixture.directory, 'generator-outputs.txt');
    await fixture.write('cli/generate-code-map.php', `<?php
        file_put_contents($argv[1].'/generator-outputs.txt', $argv[2].PHP_EOL, FILE_APPEND | LOCK_EX);
        file_put_contents($argv[2], 'fixture code map');
    `);
    const reports = await Promise.all([fixture.run(['map:val']), fixture.run(['map:val'])]);
    for (const report of reports) assert.equal(report.status, 0, report.output);
    const outputs = (await readFile(outputsFile, 'utf8')).trim().split(/\r?\n/);
    assert.equal(new Set(outputs).size, 2);
    for (const output of outputs) {
        assert.equal(output.startsWith(fixture.directory + '/'), false);
        await assert.rejects(access(output), {code: 'ENOENT'});
    }
});

test('REA coverage rejects stale source/evidence, stale reports and unsupported live claims', {timeout: 60000}, async t => {
    const directory=await mkdtemp(join(tmpdir(),'bapx-rea-ledger-'));
    t.after(()=>rm(directory,{recursive:true,force:true}));
    for (const name of ['cli','app','tests','docs/research/evidence']) await mkdir(join(directory,name),{recursive:true});
    await copyFile(join(sourceRoot,'cli/rea-capabilities.php'),join(directory,'cli/rea-capabilities.php'));
    const hash=value=>createHash('sha256').update(value).digest('hex');
    const file='app/example.php', evidence='tests/example.php';
    await writeFile(join(directory,file),valid);
    await writeFile(join(directory,evidence),valid);
    const digest=hash(file+'\0'+hash(valid)+'\n'+evidence+'\0'+hash(valid)+'\n');
    const index={schema_version:1,sources:[{id:'fixture',revision:'fixture-revision',files:[{path:'upstream.php',sha256:hash(valid)}]}]};
    const indexFile=join(directory,'docs/research/evidence/reference-source-index.json');
    const register=join(directory,'docs/research/capability-register.json');
    const report=join(directory,'docs/research/capability-progress.md');
    await writeFile(indexFile,JSON.stringify(index));
    const row={id:'F01',group:'fixture',title:'Synthetic verified scope',status:'verified',issues:[1],
        references:[{source:'fixture',path:'upstream.php'}],local_sources:[file],acceptance_evidence:[evidence],
        finding:'Synthetic test, never a production claim.',review:{date:'2026-11-26',reason:'Fixture-only review evidence.',source_sha256:digest},
        production_evidence:[{deployed_sha:'a'.repeat(40),date:'2026-11-26',source_sha256:digest,
            scope:'Synthetic fixture only',url:'https://github.com/bapxmediahub/bapXphpAiBackend/issues/1'}]};
    const initial={schema_version:1,baseline_commit:'b'.repeat(40),groups:{fixture:'Fixture'},
        rows:[row,{...row,id:'F02',status:'partial',production_evidence:[]}]};
    const run=args=>invoke('php',['cli/rea-capabilities.php',...args],directory);
    await writeFile(register,JSON.stringify(initial));
    let result=await run(['--render']);assert.equal(result.status,0,result.output);
    assert.match(await readFile(report,'utf8'),/Fixture \| 2 \| 1 \| 1 \| 0 \| 1 \| 50.0% \| 50.0%/);
    assert.equal((await run(['--check'])).status,0);
    await writeFile(report,'outdated');
    assert.match((await run(['--check'])).output,/report stale/);
    await writeFile(join(directory,file),valid+'// changed');
    result=await run(['--check']);assert.notEqual(result.status,0);assert.match(result.output,/F01: mapped source/);assert.match(result.output,/F02: mapped source/);
    assert.equal((await run(['--record','F01','--reason','Reviewed changed fixture source and retained old acceptance.'])).status,0);
    let updated=JSON.parse(await readFile(register,'utf8'));
    assert.equal(updated.rows[0].status,'implemented');assert.equal(updated.rows[0].production_evidence.length,1);
    assert.equal((await run(['--record','F02','--reason','Reviewed changed fixture scope and preserved partial status.'])).status,0);
    assert.equal((await run(['--render'])).status,0);
    assert.match(await readFile(report,'utf8'),/Fixture \| 2 \| 1 \| 1 \| 0 \| 0 \| 50.0% \| 0.0%/);
    updated=JSON.parse(await readFile(register,'utf8'));
    updated.rows[0].status='verified';
    await writeFile(register,JSON.stringify(updated));
    assert.match((await run(['--check'])).output,/Verified status without matching/);
    updated.rows[0].status='implemented';updated.rows[0].references[0].path='unmapped.php';
    await writeFile(register,JSON.stringify(updated));
    assert.match((await run(['--check'])).output,/Unpinned reference/);
    updated.rows[0].references[0].path='upstream.php';updated.rows[1].id='F01';
    await writeFile(register,JSON.stringify(updated));
    assert.match((await run(['--check'])).output,/duplicate capability ID/);
});
