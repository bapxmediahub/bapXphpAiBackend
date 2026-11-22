'use strict';

const assert = require('node:assert/strict');
const {execFile} = require('node:child_process');
const {chmod, copyFile, mkdir, mkdtemp, rm, writeFile} = require('node:fs/promises');
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
        'tests/run.php': 'test', 'cli/agent-contracts.php': 'contracts',
        'cli/docs-check.php': 'docs', 'cli/validate-project-map.php': 'map',
        'cli/validate-docs-map.php': 'docs-map', 'cli/smoke-local.php': 'smoke',
    })) await write(file, '<?php\necho "FIXTURE:' + marker + '-ok\\n";\n');
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
    for (const stage of ['test', 'contracts', 'docs', 'map', 'docs-map', 'smoke']) {
        assert.ok(failure.output.includes('FIXTURE:' + stage + '-ok'), 'CI did not finish the successful ' + stage + ' fixture');
    }
    for (const message of ['Project index is current', 'Knowledge index is current', 'Code map is current']) {
        assert.ok(failure.output.includes(message), 'An unrelated generated-artifact fixture did not pass');
    }
    assert.doesNotMatch(failure.output, /TESTS FAILED|DOCUMENTATION STALE|REVIEW REQUIRED|MAP VALIDATION FAILED|SMOKE FAILED|INDEX STALE|CODE MAP STALE/);
});
