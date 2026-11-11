// Run with node tests/checkout-address-regression.cjs. This executes the actual
// checkout change handler with synthetic DOM elements; no customer data is used.
const fs = require('fs');
const assert = require('assert');
const src = fs.readFileSync(require('path').join(__dirname, '../views/public/checkout.php'), 'utf8');
const start = src.indexOf("if (savedAddress) savedAddress.addEventListener('change'");
const end = src.indexOf('if (savedAddress && savedAddress.value)', start);
assert(start >= 0 && end > start, 'checkout address handler must exist');
const inputs = Object.fromEntries(['name', 'phone', 'address', 'city', 'pincode', 'state', 'address_name'].map(k => [k, {value: ''}]));
const save = {checked: true};
const document = {querySelector: s => s === '[name="save_address"]' ? save : inputs[s.match(/name="([^"]+)"/)?.[1]]};
const savedAddress = {options: [], selectedIndex: 0, addEventListener: (_, handler) => { savedAddress.change = handler; }};
new Function('document', 'savedAddress', src.slice(start, end))(document, savedAddress);
savedAddress.options = [{dataset: {address: JSON.stringify({state: 'Karnataka'})}}, {dataset: {address: JSON.stringify({city: 'Legacy'})}}];
savedAddress.change();
assert.strictEqual(inputs.state.value, 'Karnataka');
savedAddress.selectedIndex = 1;
savedAddress.change();
assert.strictEqual(inputs.state.value, '', 'a legacy address must clear the previous state');
console.log('PASS saved-address state reset');
