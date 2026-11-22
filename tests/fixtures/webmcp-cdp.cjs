'use strict';

// Test-only CDP client for a browser launched by these fixtures. It is never
// used to attach to a user's browser or to bypass a host's WebMCP permissions.
// Protocol: https://chromedevtools.github.io/devtools-protocol/tot/WebMCP/
// Current toolId shape: https://github.com/ChromeDevTools/devtools-protocol/blob/master/pdl/domains/WebMCP.pdl
// Earlier name shape: https://chromium.googlesource.com/chromium/src/+/7b2b2f1d8406dafea7bd199ebf6844c4f593753d/third_party/blink/public/devtools_protocol/domains/WebMCP.pdl
const assert = require('node:assert/strict');
const object = value => value !== null && typeof value === 'object' && !Array.isArray(value);
const identifier = value => typeof value === 'string' && value.length > 0 && value.length <= 1024;

async function inspectPublicContact(session, {expectedNames, timeoutMs = 8000}) {
    assert.ok(Array.isArray(expectedNames) && expectedNames.length > 0 && expectedNames.length <= 32);
    assert.ok(expectedNames.every(name => /^[a-z][a-z0-9_]{0,79}$/.test(name)));
    assert.equal(new Set(expectedNames).size, expectedNames.length);
    assert.ok(expectedNames.includes('get_public_contact'));
    assert.ok(Number.isInteger(timeoutMs) && timeoutMs > 0 && timeoutMs <= 15000);
    const expected = [...expectedNames].sort();
    const tools = new Map(), invoked = new Map(), responded = new Map(), waiters = new Set();
    let frameId, browser, enabled = false, acceptingCalls = false, failure, phase = 'browser identification';
    let fail;
    const failed = new Promise((_, reject) => { fail = reject; });
    // A synchronous event may fail before the next command/wait attaches its race.
    // The original promise still rejects every command and waiter below.
    failed.catch(() => {});
    const wake = () => { for (const check of [...waiters]) check(); };
    const reject = error => {
        if (failure) return;
        failure = error instanceof Error ? error : new Error(String(error));
        fail(failure);
        wake();
    };
    const timer = setTimeout(() => reject(new Error('WebMCP CDP timed out during ' + phase)), timeoutMs);
    const listen = handler => payload => {
        if (failure) return;
        try { handler(payload); wake(); } catch (error) { reject(error); }
    };
    const descriptors = (event, added) => {
        assert.ok(object(event) && Array.isArray(event.tools) && event.tools.length <= 128,
            'Unsupported WebMCP tools event schema');
        for (const tool of event.tools) {
            assert.ok(object(tool) && identifier(tool.frameId) && typeof tool.name === 'string',
                'Unsupported WebMCP tool descriptor schema');
            if (tool.frameId !== frameId) continue;
            assert.ok(expected.includes(tool.name), 'Unexpected main-frame CDP tool: ' + tool.name);
            if (Object.hasOwn(tool, 'toolId')) assert.ok(identifier(tool.toolId), 'Invalid CDP toolId');
            if (added) {
                assert.equal(typeof tool.description, 'string', 'Missing CDP tool description');
                assert.ok(!Object.hasOwn(tool, 'inputSchema') || object(tool.inputSchema), 'Invalid CDP inputSchema');
                assert.ok(!Object.hasOwn(tool, 'outputSchema') || object(tool.outputSchema), 'Invalid CDP outputSchema');
                assert.ok(!tools.has(tool.name), 'Duplicate main-frame CDP tool: ' + tool.name);
                tools.set(tool.name, tool);
            } else {
                const previous = tools.get(tool.name);
                assert.ok(previous && previous.toolId === tool.toolId, 'Unknown CDP tool removal');
                tools.delete(tool.name);
            }
        }
        const idCount = [...tools.values()].filter(tool => Object.hasOwn(tool, 'toolId')).length;
        assert.ok(idCount === 0 || idCount === tools.size, 'Unsupported mixed WebMCP toolId/name descriptor schema');
    };
    const remember = (target, event) => {
        if (!acceptingCalls) return;
        assert.ok(object(event) && identifier(event.invocationId), 'Unsupported CDP invocation event schema');
        assert.ok(!target.has(event.invocationId), 'Duplicate CDP invocation event');
        assert.ok(target.size < 32, 'Too many unrelated CDP invocation events');
        target.set(event.invocationId, event);
    };
    const listeners = {
        'WebMCP.toolsAdded': listen(event => descriptors(event, true)),
        'WebMCP.toolsRemoved': listen(event => descriptors(event, false)),
        'WebMCP.toolInvoked': listen(event => remember(invoked, event)),
        'WebMCP.toolResponded': listen(event => remember(responded, event)),
    };
    // enable replays already registered tools. Register all listeners first;
    // completion can also arrive before the invoke command's Promise settles.
    for (const [name, handler] of Object.entries(listeners)) session.on(name, handler);
    const command = async (method, parameters = {}) => {
        phase = method;
        if (failure) throw failure;
        try { return await Promise.race([Promise.resolve().then(() => session.send(method, parameters)), failed]); }
        catch (error) {
            if (failure) throw failure;
            throw new Error('WebMCP CDP command failed or is unsupported: ' + method, {cause: error});
        }
    };
    const waitFor = (label, read) => {
        phase = label;
        return new Promise((resolve, rejectWait) => {
            const check = () => {
                try {
                    if (failure) throw failure;
                    const value = read();
                    if (value === undefined) return;
                    waiters.delete(check);
                    resolve(value);
                } catch (error) { waiters.delete(check); rejectWait(error); }
            };
            waiters.add(check);
            check();
        });
    };
    try {
        browser = await command('Browser.getVersion');
        assert.ok(object(browser) && identifier(browser.product) && identifier(browser.protocolVersion)
            && identifier(browser.revision), 'Unsupported Browser.getVersion schema');
        const tree = await command('Page.getFrameTree');
        frameId = tree?.frameTree?.frame?.id;
        assert.ok(identifier(frameId), 'Missing main frame in CDP frame tree');
        await command('WebMCP.enable');
        enabled = true;
        await waitFor('exact main-frame toolsAdded discovery', () => tools.size === expected.length ? true : undefined);
        assert.deepEqual([...tools.keys()].sort(), expected, 'CDP registry must match the expected page registry');
        const contact = tools.get('get_public_contact');
        if (contact.annotations?.readOnly !== undefined) assert.equal(contact.annotations.readOnly, true);
        assert.notEqual(contact.annotations?.consequential, true, 'Contact diagnostic must remain a read');
        const profile = Object.hasOwn(contact, 'toolId') ? 'toolId' : 'frameId+toolName';
        const parameters = {frameId, input: {}};
        if (profile === 'toolId') parameters.toolId = contact.toolId;
        else parameters.toolName = 'get_public_contact';
        acceptingCalls = true;
        // Fixed read only. Never retry a rejected invocation with another shape.
        const invocation = await command('WebMCP.invokeTool', parameters);
        assert.ok(object(invocation) && identifier(invocation.invocationId), 'Missing CDP invocationId');
        const completion = await waitFor('correlated get_public_contact completion', () => {
            const started = invoked.get(invocation.invocationId), completed = responded.get(invocation.invocationId);
            if (!started || !completed) return undefined;
            assert.equal(started.frameId, frameId, 'CDP invocation came from a different frame');
            if (profile === 'toolId') assert.equal(started.toolId, contact.toolId, 'CDP invocation used a different toolId');
            else assert.equal(started.toolName, 'get_public_contact', 'CDP invocation used a different tool');
            if (started.toolName !== undefined) assert.equal(started.toolName, 'get_public_contact');
            assert.equal(typeof started.input, 'string', 'Unsupported CDP invocation input schema');
            assert.deepEqual(JSON.parse(started.input), {}, 'Contact diagnostic must pass no arguments');
            assert.equal(completed.status, 'Completed', 'CDP contact invocation did not complete successfully');
            assert.equal(completed.errorText, undefined, 'CDP contact invocation reported an error');
            assert.equal(completed.exception, undefined, 'CDP contact invocation threw an exception');
            assert.ok(Object.hasOwn(completed, 'output'), 'CDP contact completion has no output');
            return completed;
        });
        const serialized = typeof completion.output === 'string' ? completion.output : JSON.stringify(completion.output);
        assert.ok(typeof serialized === 'string' && serialized.length <= 16384, 'Unbounded CDP contact output');
        const output = JSON.parse(serialized);
        assert.ok(object(output) && output.path === '/contact', 'CDP completion is not public contact data');
        // Navigation, removal or replacement must not pass using an old response.
        assert.equal(tools.get('get_public_contact'), contact, 'CDP contact registration changed during invocation');
        assert.deepEqual([...tools.keys()].sort(), expected);
        await command('WebMCP.disable');
        enabled = false;
        return {browser: {product: browser.product, protocolVersion: browser.protocolVersion, revision: browser.revision},
            profile, names: expected, contact: output};
    } catch (error) {
        const version = browser ? `${browser.product}, CDP ${browser.protocolVersion}` : 'browser version unavailable';
        throw new Error(`WebMCP CDP diagnostic failed (${version}): ${error.message}`, {cause: error});
    } finally {
        clearTimeout(timer);
        for (const [name, handler] of Object.entries(listeners)) session.off(name, handler);
        // The owning page wrapper detaches even after unsupported/failed enable.
        // Do not issue a second invoke or a page-JS fallback on any failure.
        if (enabled) {
            let cleanup;
            try { await Promise.race([Promise.resolve().then(() => session.send('WebMCP.disable')).catch(() => {}),
                new Promise(resolve => { cleanup = setTimeout(resolve, 250); })]); }
            finally { clearTimeout(cleanup); }
        }
    }
}

async function inspectPagePublicContact(page, options) {
    const session = await page.context().newCDPSession(page);
    try { return await inspectPublicContact(session, options); }
    finally { await session.detach(); }
}

module.exports = {inspectPublicContact, inspectPagePublicContact};
