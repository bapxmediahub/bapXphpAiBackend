const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function loadApp(fetchImpl = async () => ({ ok: true, json: async () => ({}) })) {
  const root = { children: [], appendChild(node) { this.children.push(node); } };
  const context = {
    console,
    fetch: fetchImpl,
    localStorage: {
      data: {},
      getItem(key) { return this.data[key] ?? null; },
      setItem(key, value) { this.data[key] = value; },
    },
    history: { pushState(_state, _title, path) { context.window.location.pathname = path.split('?')[0] || '/'; context.window.location.search = path.includes('?') ? '?' + path.split('?')[1] : ''; } },
    window: { location: { pathname: '/', search: '' }, scrollTo() {} },
    document: {
      getElementById(id) { return id === 'root' ? root : null; },
      querySelectorAll() { return []; },
      addEventListener() {},
      createElement() { return { textContent: '', innerHTML: '' }; },
    },
    URLSearchParams,
  };
  vm.createContext(context);
  const code = fs.readFileSync('assets/js/app.js', 'utf8') + '\nglobalThis.__app = { API, Router, Cart };';
  vm.runInContext(code, context);
  return { ...context.__app, context, root };
}

async function testDynamicRouteCapturesSlug() {
  const { Router, context } = loadApp();
  let captured = null;
  Router.reg('/product/{slug}', (_root, slug) => { captured = slug; });
  Router.reg('*', () => { captured = 'not-found'; });
  context.window.location.pathname = '/product/varahi-amman-dollar';
  Router.render();
  assert.equal(captured, 'varahi-amman-dollar');
}

async function testApiPostRejectsFailedResponses() {
  const { API } = loadApp(async () => ({ ok: false, status: 400, json: async () => ({ error: 'Invalid amount' }) }));
  await assert.rejects(
    () => API.post('/checkout/create-order', { total: 15 }),
    /Invalid amount/
  );
}

(async () => {
  await testDynamicRouteCapturesSlug();
  await testApiPostRejectsFailedResponses();
  console.log('PASS frontend router and API error handling');
})();
