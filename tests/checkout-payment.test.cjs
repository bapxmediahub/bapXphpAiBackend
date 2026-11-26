'use strict';
const assert = require('node:assert/strict');
const { test } = require('node:test');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const html = execFileSync(process.env.PHP_BINARY || 'php', ['tests/fixtures/checkout-payment.php'], { cwd: root, encoding: 'utf8' });
const script = [...html.matchAll(/<script>([\s\S]*?)<\/script>/g)].map(match => match[1]).find(value => value.includes('new Razorpay'));
assert.ok(script, 'Render the actual checkout callback, not a replacement implementation');

function fixture(verification) {
    const nodes = new Map();
    const node = key => {
        if (!nodes.has(key)) {
            const listeners = new Map();
            const classes = new Set();
            nodes.set(key, { value: '', disabled: false, textContent: '', checked: false,
                classList: { add: value => classes.add(value), remove: value => classes.delete(value), contains: value => classes.has(value) },
                addEventListener: (name, callback) => listeners.set(name, callback),
                click() { return this.disabled ? Promise.resolve() : listeners.get('click')?.({ preventDefault() {} }); },
                reportValidity: () => true, scrollIntoView() {} });
        }
        return nodes.get(key);
    };
    const fields = { name:'Fixture Person',email:'fixture@example.test',phone:'9876543210',address:'Fixture Street',
        city:'Chennai',pincode:'600001',state:'Tamil Nadu' };
    const form = { querySelector: selector => {
        const field = /name="([^"]+)"/.exec(selector)?.[1];
        return Object.hasOwn(fields, field) ? Object.assign(node(field), { value: fields[field] }) : null;
    } };
    const calls = [];
    let gateway;
    const window = { location: { href: '' } };
    const context = {
        URLSearchParams, console, window,
        document: {
            getElementById: id => id === 'saved-address' ? null : node(id),
            querySelector: selector => selector === '.checkout-form' ? form : { value:'razorpay' }
        },
        showToast() {},
        Razorpay: function (options) { gateway = { options, events:{}, open() {}, on(name, callback) { this.events[name] = callback; } }; return gateway; },
        fetch: async (url, options) => {
            calls.push({ url, fields:Object.fromEntries(options.body) });
            if (url === '/checkout/create-order') return { ok:true, json:async () => ({ order_id:'order_fixture',amount:115,currency:'INR' }) };
            assert.equal(url, '/payment/verify');
            return verification(calls.filter(call => call.url === '/payment/verify').length);
        }
    };
    node('csrf-token').value = 'fixture-csrf';
    vm.runInNewContext(script, context, { filename:'rendered-checkout.js' });
    return { node, calls, window, async start() { await node('pay-now').click(); assert.ok(gateway); return gateway; } };
}
const ok = () => ({ ok:true, json:async () => ({ verified:true,order_id:'0123456789abcdef' }) });
const receipt = { razorpay_payment_id:'pay_fixture',razorpay_signature:'fixture-signature' };

test('late verification network failure stays visible and retries the same payment without creating another', async () => {
    const page = fixture(attempt => { if (attempt === 1) throw Error('Network unavailable'); return ok(); });
    const gateway = await page.start();
    assert.equal(gateway.options.amount,115,'The gateway receives the server-returned integer paise');
    assert.equal(Object.hasOwn(page.calls.find(call => call.url === '/checkout/create-order').fields,'amount'),false,'Browser must not supply an authoritative amount');

    await assert.doesNotReject(gateway.options.handler(receipt));
    assert.equal(page.node('pay-failed').classList.contains('is-open'), true);
    assert.match(page.node('pay-failed-title').textContent, /needs checking/);
    assert.match(page.node('pay-failed-next').textContent, /Do not make another payment/);
    assert.equal(page.node('pay-now').disabled, true);
    assert.equal(page.node('pay-retry').disabled, false);
    assert.equal(page.node('pay-retry').textContent, 'Check payment status');
    gateway.options.modal.ondismiss();
    assert.equal(page.node('pay-now').disabled, true, 'Closing the provider after its callback must not unlock a second payment');
    await page.node('pay-retry').click();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(page.calls.filter(call => call.url === '/checkout/create-order').length, 1);
    const attempts = page.calls.filter(call => call.url === '/payment/verify');
    assert.equal(attempts.length, 2);
    assert.deepEqual(attempts[0].fields, attempts[1].fields);
    assert.deepEqual(Object.keys(attempts[0].fields).sort(), ['_csrf','razorpay_order_id','razorpay_payment_id','razorpay_signature']);
    assert.equal(page.window.location.href, '/account/orders/0123456789abcdef?placed=1');
});

test('verified payment with unavailable order persistence gets a truthful recovery state', async () => {
    const page = fixture(() => ({ ok:false, json:async () => ({ verified:false,payment_verified:true,error:'Order confirmation is temporarily unavailable.' }) }));
    const gateway = await page.start();
    await assert.doesNotReject(gateway.options.handler(receipt));
    assert.equal(page.node('pay-failed-title').textContent, 'Payment verified; order confirmation pending');
    assert.doesNotMatch(page.node('pay-failed-reason').textContent + page.node('pay-failed-next').textContent, /No money|nothing has been ordered/i);
    assert.equal(page.node('pay-now').disabled, true);
    assert.equal(page.node('pay-retry').disabled, false);
    assert.equal(page.window.location.href, '');
});

test('invalid verification response is contained, and a provider failure never promises no debit', async () => {
    const page = fixture(() => ({ ok:false, json:async () => { throw Error('Invalid response'); } }));
    const gateway = await page.start();
    await assert.doesNotReject(gateway.options.handler(receipt));
    assert.equal(page.node('pay-retry').disabled, false);
    assert.match(page.node('pay-failed-title').textContent, /needs checking/);
    const failed = fixture(ok);
    const failureGateway = await failed.start();
    failureGateway.events['payment.failed']({ error:{ description:'Provider declined this attempt.' } });
    assert.equal(failed.node('pay-now').disabled, false);
    assert.match(failed.node('pay-failed-next').textContent, /Check your bank or payment app/);
    assert.doesNotMatch(failed.node('pay-failed-reason').textContent, /No money|nothing has been ordered/i);
});
