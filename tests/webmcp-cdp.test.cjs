'use strict';
const assert = require('node:assert/strict');
const {test} = require('node:test');
const {EventEmitter} = require('node:events');
const {inspectPublicContact} = require('./fixtures/webmcp-cdp.cjs');

const expectedNames = ['get_public_contact', 'list_public_services'];
const contact = {name: 'Fixture Business', path: '/contact', email: 'support@example.test'};
const version = {product: 'Chrome/159.0.fixture', protocolVersion: '1.3', revision: '@fixture'};
function fixture({profile = 'toolId', discovery, invoke, unsupported = false} = {}) {
    const session = new EventEmitter();
    session.calls = [];
    const definitions = expectedNames.map(name => ({name, description: 'Public fixture read', frameId: 'main-frame',
        inputSchema: {type: 'object', properties: {}}, annotations: {readOnly: true},
        ...(profile === 'toolId' ? {toolId: 'opaque-' + name} : {})}));
    session.send = async (method, parameters) => {
        session.calls.push({method, parameters});
        if (method === 'Browser.getVersion') return version;
        if (method === 'Page.getFrameTree') return {frameTree: {frame: {id: 'main-frame'}}};
        if (method === 'WebMCP.enable') {
            if (unsupported) throw new Error('Method not found');
            assert.equal(session.listenerCount('WebMCP.toolsAdded'), 1, 'Listener must precede enable');
            assert.equal(session.listenerCount('WebMCP.toolResponded'), 1, 'Completion listener must precede enable');
            if (discovery) discovery(session, definitions);
            else session.emit('WebMCP.toolsAdded', {tools: definitions});
            return {};
        }
        if (method === 'WebMCP.invokeTool') {
            if (invoke) return invoke(session, parameters);
            // Events intentionally precede Promise settlement to cover client-side
            // scheduling, even though the protocol sends its response first.
            session.emit('WebMCP.toolResponded', {invocationId: 'unrelated', status: 'Completed', output: {path: '/private'}});
            session.emit('WebMCP.toolInvoked', {invocationId: 'ours', frameId: 'main-frame', toolName: 'get_public_contact',
                input: '{}', ...(profile === 'toolId' ? {toolId: 'opaque-get_public_contact'} : {})});
            session.emit('WebMCP.toolResponded', {invocationId: 'ours', status: 'Completed', output: contact});
            return {invocationId: 'ours'};
        }
        if (method === 'WebMCP.disable') return {};
        throw new Error('Unexpected protocol method: ' + method);
    };
    return session;
}
function assertClean(session) {
    for (const name of ['toolsAdded', 'toolsRemoved', 'toolInvoked', 'toolResponded'])
        assert.equal(session.listenerCount('WebMCP.' + name), 0, 'CDP event listeners must be removed');
    assert.ok(session.calls.every(({method}) => !method.startsWith('Runtime.')), 'No page-JS fallback');
}

for (const profile of ['toolId', 'frameId+toolName']) {
    test('CDP ' + profile + ' discovers before enable and correlates only its public read', async () => {
        const session = fixture({profile});
        const result = await inspectPublicContact(session, {expectedNames});
        assert.deepEqual(result, {browser: version, profile, names: expectedNames, contact});
        const calls = session.calls.filter(call => call.method === 'WebMCP.invokeTool');
        assert.equal(calls.length, 1);
        assert.deepEqual(calls[0].parameters, profile === 'toolId'
            ? {frameId: 'main-frame', input: {}, toolId: 'opaque-get_public_contact'}
            : {frameId: 'main-frame', input: {}, toolName: 'get_public_contact'});
        assert.equal(session.calls.at(-1).method, 'WebMCP.disable');
        assertClean(session);
    });
}

test('CDP waits for the exact main-frame registry across discovery events', async () => {
    const session = fixture({discovery(session, definitions) {
        session.emit('WebMCP.toolsAdded', {tools: [definitions[0], {...definitions[0], frameId: 'child-frame'}]});
        queueMicrotask(() => session.emit('WebMCP.toolsAdded', {tools: [definitions[1]]}));
    }});
    assert.deepEqual((await inspectPublicContact(session, {expectedNames})).names, expectedNames);
    assertClean(session);
});

for (const [label, alter] of [
    ['extra tool', tools => tools.push({...tools[0], name: 'create_cart'})],
    ['mixed descriptor profiles', tools => { delete tools[1].toolId; }],
    ['invalid toolId', tools => { tools[0].toolId = {}; }],
    ['missing frame', tools => { delete tools[0].frameId; }],
    ['unknown descriptor schema', tools => { tools[0].inputSchema = 'not an object'; }],
]) test('CDP rejects ' + label + ' without invoking a tool', async () => {
    const session = fixture({discovery(session, tools) { alter(tools); session.emit('WebMCP.toolsAdded', {tools}); }});
    await assert.rejects(inspectPublicContact(session, {expectedNames}), /WebMCP CDP diagnostic failed/);
    assert.equal(session.calls.filter(call => call.method === 'WebMCP.invokeTool').length, 0);
    assertClean(session);
});

test('unsupported native CDP is an explicit failure, never a polyfill fallback', async () => {
    const session = fixture({unsupported: true});
    await assert.rejects(inspectPublicContact(session, {expectedNames}), /Chrome\/159.*failed or is unsupported: WebMCP.enable/);
    assert.equal(session.calls.filter(call => call.method === 'WebMCP.invokeTool').length, 0);
    assertClean(session);
});

test('missing discovery times out with no invocation', async () => {
    const session = fixture({discovery() {}});
    await assert.rejects(inspectPublicContact(session, {expectedNames, timeoutMs: 20}), /timed out during exact main-frame/);
    assert.equal(session.calls.filter(call => call.method === 'WebMCP.invokeTool').length, 0);
    assertClean(session);
});

test('unrelated completion cannot satisfy a requested invocation', async () => {
    const session = fixture({invoke(session) {
        session.emit('WebMCP.toolInvoked', {invocationId: 'ours', frameId: 'main-frame', toolId: 'opaque-get_public_contact', input: '{}'});
        session.emit('WebMCP.toolResponded', {invocationId: 'other', status: 'Completed', output: contact});
        return {invocationId: 'ours'};
    }});
    await assert.rejects(inspectPublicContact(session, {expectedNames, timeoutMs: 20}), /timed out during correlated/);
    assert.equal(session.calls.filter(call => call.method === 'WebMCP.invokeTool').length, 1);
    assertClean(session);
});

test('a rejected invocation fails without retrying the other protocol shape', async () => {
    const session = fixture({invoke() { throw new Error('Unsupported invocation parameters'); }});
    await assert.rejects(inspectPublicContact(session, {expectedNames}), /failed or is unsupported: WebMCP.invokeTool/);
    assert.equal(session.calls.filter(call => call.method === 'WebMCP.invokeTool').length, 1);
    assertClean(session);
});

for (const [label, started, completed] of [
    ['wrong tool', {toolId: 'other'}, {}],
    ['wrong frame', {frameId: 'child-frame'}, {}],
    ['unexpected arguments', {input: '{"cartToken":"should-not-be-sent"}'}, {}],
    ['error completion', {}, {status: 'Error'}],
    ['canceled completion', {}, {status: 'Canceled'}],
    ['missing output', {}, {output: undefined}],
    ['malformed output', {}, {output: 'not JSON'}],
]) test('CDP rejects ' + label + ' without retry', async () => {
    const session = fixture({invoke(session) {
        session.emit('WebMCP.toolInvoked', {invocationId: 'ours', frameId: 'main-frame', toolId: 'opaque-get_public_contact', input: '{}', ...started});
        session.emit('WebMCP.toolResponded', {invocationId: 'ours', status: 'Completed', output: contact, ...completed});
        return {invocationId: 'ours'};
    }});
    await assert.rejects(inspectPublicContact(session, {expectedNames}), /WebMCP CDP diagnostic failed/);
    assert.equal(session.calls.filter(call => call.method === 'WebMCP.invokeTool').length, 1);
    assertClean(session);
});

test('CDP rejects a removed registration even if an old completion arrives', async () => {
    const session = fixture({invoke(session) {
        session.emit('WebMCP.toolsRemoved', {tools: [{name: 'get_public_contact', toolId: 'opaque-get_public_contact', frameId: 'main-frame'}]});
        session.emit('WebMCP.toolInvoked', {invocationId: 'ours', frameId: 'main-frame', toolId: 'opaque-get_public_contact', input: '{}'});
        session.emit('WebMCP.toolResponded', {invocationId: 'ours', status: 'Completed', output: contact});
        return {invocationId: 'ours'};
    }});
    await assert.rejects(inspectPublicContact(session, {expectedNames}), /registration changed/);
    assertClean(session);
});
