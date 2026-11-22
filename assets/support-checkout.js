/* Native Support adapter for the fixed shared shopping/checkout components.
 * No model-selected commands, owner cookies, saved form HTML or private report scope.
 */
(() => {
    'use strict';
    const panel = document.getElementById('support-panel');
    const log = document.getElementById('support-log');
    const compose = document.getElementById('support-form');
    const pane = document.getElementById('support-commerce');
    const container = document.getElementById('support-commerce-content');
    const status = document.getElementById('support-commerce-status');
    const toggle = document.getElementById('support-commerce-toggle');
    const back = document.getElementById('support-commerce-back');
    const reset = document.getElementById('support-commerce-reset');
    const recoveryNote = document.getElementById('support-commerce-recovery');
    const configNode = document.getElementById('bapx-support-commerce-config');
    if (!panel || !log || !compose || !pane || !container || !status || !toggle || !back || !configNode
        || !window.BapxShopping || !window.BapxCheckout) return;
    let origin;
    let protocol;
    let deliveryStates;
    let canShop;
    try {
        const config = JSON.parse(configNode.textContent);
        const url = new URL(config.publicOrigin);
        if (url.protocol !== 'https:' || url.origin !== window.location.origin || url.pathname !== '/'
            || url.username || url.password || url.search || url.hash || config.protocolVersion !== '2026-07-28') throw Error();
        if (!Array.isArray(config.deliveryStates) || config.deliveryStates.length < 1 || config.deliveryStates.length > 50
            || new Set(config.deliveryStates).size !== config.deliveryStates.length
            || !config.deliveryStates.every(value => typeof value === 'string' && value.length > 0 && [...value].length <= 80
                && !/[\u0000-\u001f\u007f]/.test(value))) throw Error();
        if (config.canShop !== undefined && typeof config.canShop !== 'boolean') throw Error();
        origin = url.origin;
        protocol = config.protocolVersion;
        deliveryStates = config.deliveryStates;
        canShop = config.canShop !== false;
    } catch (_) { return; }
    const names = new Set(['search_public_products','create_cart','get_cart','set_cart_quantity',
        'review_checkout','prepare_checkout_payment','get_checkout_status']);
    const controllers = new Set();
    const nativeWaiters = new Set();
    const commerceKinds = Object.freeze({ create_cart:'cart',get_cart:'cart',set_cart_quantity:'cart',
        review_checkout:'review',prepare_checkout_payment:'payment',get_checkout_status:'status' });
    const cartPattern = /^cart_[a-f0-9]{32}\.[a-f0-9]{64}$/;
    const paymentPattern = /^payment_[a-f0-9]{32}\.[a-f0-9]{64}$/;
    let nextId = 0;
    let lifecycleVersion = 0;
    let selectionBusy = false;
    let restoring = false;
    let stopped = false;
    let returnFocus = null;
    let recoveryTimer = 0;
    let recoverySnapshot = null;
    const storageKey = 'bapx-support-commerce:v1:' + origin;
    const exact = (value,keys) => value && typeof value === 'object' && !Array.isArray(value)
        && Object.keys(value).length === keys.length && keys.every(key => Object.hasOwn(value,key));
    const expiry = (value,maximumSeconds) => {
        if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/.test(value)) throw Error();
        const time = Date.parse(value);
        if (!Number.isFinite(time) || new Date(time).toISOString().replace('.000Z','Z') !== value
            || time > Date.now() + (maximumSeconds + 120) * 1000) throw Error();
        return time;
    };
    const validatedRecovery = value => {
        if (!exact(value,['version','currentCart','buyNowCart','checkout']) || value.version !== 1) throw Error();
        const cart = entry => {
            if (entry === null) return null;
            if (!exact(entry,['cartToken','revision','expiresAt']) || typeof entry.cartToken !== 'string'
                || !/^cart_[a-f0-9]{32}\.[a-f0-9]{64}$/.test(entry.cartToken)
                || !Number.isSafeInteger(entry.revision) || entry.revision < 0 || entry.revision > 1000000) throw Error();
            if (expiry(entry.expiresAt,86400) <= Date.now()) return null;
            return { cartToken:entry.cartToken,revision:entry.revision,expiresAt:entry.expiresAt };
        };
        const currentCart = cart(value.currentCart), buyNowCart = cart(value.buyNowCart);
        if (currentCart && currentCart.cartToken === buyNowCart?.cartToken) throw Error();
        let checkout = null;
        if (value.checkout !== null) {
            const entry = value.checkout;
            if (!exact(entry,['paymentToken','expiresAt','kind']) || typeof entry.paymentToken !== 'string'
                || !/^payment_[a-f0-9]{32}\.[a-f0-9]{64}$/.test(entry.paymentToken)
                || !['current','buy_now'].includes(entry.kind)) throw Error();
            if (expiry(entry.expiresAt,7 * 86400) > Date.now()) checkout = { paymentToken:entry.paymentToken,expiresAt:entry.expiresAt,kind:entry.kind };
        }
        return { version:1,currentCart,buyNowCart,checkout };
    };
    const storageUnavailable = () => {
        if (recoveryNote) recoveryNote.textContent = 'Shopping works in this tab, but this browser could not save its recovery access. Keep this tab open until payment confirmation.';
    };
    const saveRecovery = value => {
        if (stopped) return;
        clearTimeout(recoveryTimer);
        let snapshot;
        try { snapshot = validatedRecovery(value); }
        catch (_) { return; } // A module may never broaden what native storage accepts.
        recoverySnapshot = snapshot;
        const entries = [snapshot.currentCart,snapshot.buyNowCart,snapshot.checkout].filter(Boolean);
        try {
            if (entries.length) {
                const encoded = JSON.stringify(snapshot);
                if (encoded.length > 2048) throw Error();
                sessionStorage.setItem(storageKey,encoded);
                if (recoveryNote) recoveryNote.textContent = 'This browser tab can recover cart and payment status after reload. Delivery details are not saved in the chat.';
            } else {
                sessionStorage.removeItem(storageKey);
                if (recoveryNote) recoveryNote.textContent = 'There is no saved chat shopping access in this tab. Delivery details are not saved in the chat.';
            }
        } catch (_) { storageUnavailable(); }
        if (entries.length) recoveryTimer = setTimeout(() => {
            if (!stopped && recoverySnapshot) saveRecovery(recoverySnapshot);
        },Math.max(1,Math.min(...entries.map(entry => Date.parse(entry.expiresAt))) - Date.now() + 10));
    };
    const setStatus = message => { if (!stopped) status.textContent = String(message || ''); };
    const safeProduct = value => {
        if (typeof value !== 'string' || value.length > 2048 || !/^\/product\/[A-Za-z0-9%_.-]+$/.test(value)) return null;
        try {
            const slug = decodeURIComponent(value.slice(9));
            return /^[\p{L}\p{N}_][\p{L}\p{N}\p{M}._ -]*$/u.test(slug) ? slug : null;
        } catch (_) { return null; }
    };
    const request = async (method, params) => {
        if (stopped || method !== 'tools/call' || !params || !names.has(params.name)
            || (!canShop && params.name !== 'get_checkout_status')
            || !params.arguments || typeof params.arguments !== 'object' || Array.isArray(params.arguments)) throw Error('Unsupported chat shopping action.');
        const id = 'native-support-' + (++nextId);
        const version = lifecycleVersion;
        const controller = new AbortController();
        controllers.add(controller);
        const timer = setTimeout(() => controller.abort(), 30000);
        try {
            const response = await fetch('/api/mcp', {
                method:'POST', credentials:'omit', cache:'no-store', redirect:'error', referrerPolicy:'no-referrer', signal:controller.signal,
                headers:{ 'Content-Type':'application/json', Accept:'application/json, text/event-stream',
                    'MCP-Protocol-Version':protocol, 'Mcp-Method':method, 'Mcp-Name':params.name },
                body:JSON.stringify({ jsonrpc:'2.0',id,method,params:{ name:params.name,arguments:params.arguments,
                    _meta:{ 'io.modelcontextprotocol/protocolVersion':protocol,
                        'io.modelcontextprotocol/clientCapabilities':{},
                        'io.modelcontextprotocol/clientInfo':{ name:'bapx-native-support',version:'1.0' } } } })
            });
            const text = await response.text();
            if (text.length > 500000) throw Error('Shopping response is too large.');
            const body = JSON.parse(text);
            if (stopped || version !== lifecycleVersion || !response.ok || body?.jsonrpc !== '2.0' || body.id !== id || body.error
                || !body.result || typeof body.result !== 'object') throw Error('Shopping could not be confirmed.');
            return body.result;
        } finally { clearTimeout(timer); controllers.delete(controller); }
    };
    const openLink = async target => {
        if (stopped) return false;
        try {
            const url = new URL(target);
            // Payment authorization stays on the merchant-controlled page. This
            // bridge cannot open a model-supplied provider or third-party URL.
            if (url.protocol !== 'https:' || url.username || url.password) throw Error();
            const publicPage = url.origin === origin && !url.hash && (
                (!url.search && (safeProduct(url.pathname) || ['/shop','/cart','/checkout','/contact'].includes(url.pathname)))
                || (url.pathname === '/cart/agent-checkout' && /^\?ticket=checkout_[a-f0-9]{32}\.[a-f0-9]{64}$/.test(url.search))
                || (url.pathname === '/checkout/payment' && /^\?ticket=pay_[a-f0-9]{32}\.[a-f0-9]{64}$/.test(url.search)));
            if (!publicPage) throw Error();
            window.open(url.href, '_blank', 'noopener,noreferrer');
            return true;
        } catch (_) { setStatus('This link could not be opened. Use the verified link shown with your checkout.'); return false; }
    };
    const shopping = window.BapxShopping.create({ container,origin,request,openLink,setStatus,onRecoveryState:saveRecovery,deliveryStates,canShop,
        capabilities:() => ({ serverTools:{},openLinks:{} }), resize:() => {} });
    const showPane = trigger => {
        returnFocus = trigger || toggle;
        pane.hidden = false;
        log.hidden = true;
        compose.hidden = true;
        panel.dataset.commerceOpen = 'true';
        toggle.setAttribute('aria-expanded','true');
        pane.querySelector('h2')?.focus();
    };
    const showChat = () => {
        pane.hidden = true;
        log.hidden = false;
        compose.hidden = false;
        delete panel.dataset.commerceOpen;
        toggle.setAttribute('aria-expanded','false');
        if (returnFocus?.isConnected) returnFocus.focus(); else toggle.focus();
    };
    toggle.disabled = false;
    if (!canShop) toggle.textContent = 'Checkout status';
    toggle.addEventListener('click', () => { showPane(toggle); shopping.open(); });
    back.addEventListener('click', showChat);
    const busy = () => selectionBusy || restoring || (typeof shopping.busy === 'function' && shopping.busy());
    const signalNativeIdle = cancelled => {
        if (!cancelled && (selectionBusy || restoring)) return;
        for (const finish of nativeWaiters) finish(!cancelled && !stopped);
    };
    const waitForNativeIdle = signal => {
        if (stopped || signal?.aborted) return Promise.resolve(false);
        if (!selectionBusy && !restoring) return Promise.resolve(true);
        return new Promise(resolve => {
            const cancelled = () => finish(false);
            const finish = value => { clearTimeout(timer); nativeWaiters.delete(finish); signal?.removeEventListener('abort',cancelled); resolve(value); };
            const timer = setTimeout(() => finish(false),45000);
            nativeWaiters.add(finish);
            signal?.addEventListener('abort',cancelled,{once:true});
        });
    };
    if (reset) reset.addEventListener('click', () => {
        if (busy()) { setStatus('Wait for the current cart or checkout action to finish.'); return; }
        shopping.cancel();
        saveRecovery({version:1,currentCart:null,buyNowCart:null,checkout:null});
        if (recoveryNote) recoveryNote.textContent = 'Chat shopping access was cleared from this tab. Existing orders or payments were not cancelled.';
        shopping.open();
    });
    const selectProduct = async (button, path, action) => {
        const slug = safeProduct(path);
        if (!slug || stopped || !canShop) return;
        showPane(button);
        if (busy()) { setStatus('Wait for the current cart or checkout action to finish.'); return; }
        selectionBusy = true;
        const version = lifecycleVersion;
        container.inert = true;
        button.disabled = true;
        setStatus('Checking the current published product…');
        try {
            const result = await request('tools/call', { name:'search_public_products',arguments:{ slugs:[slug],limit:1 } });
            if (stopped || version !== lifecycleVersion) return;
            if (result.isError || !await shopping.render(result)) throw Error();
            container.inert = false;
            if (!shopping.productAction(slug,action)) setStatus('This product cannot be added right now. Review its current availability in this pane.');
        } catch (_) {
            if (!stopped && version === lifecycleVersion) setStatus('The current product could not be opened. Your cart was not changed by this lookup. Try again.');
        } finally {
            if (version === lifecycleVersion) {
                selectionBusy = false;
                container.inert = false;
                button.disabled = false;
                signalNativeIdle(false);
            }
        }
    };
    const decorate = () => {
        if (stopped) return;
        if (!canShop) {
            log.querySelectorAll('.support-commerce-actions').forEach(actions => actions.remove());
            return;
        }
        for (const card of log.querySelectorAll('.bapx-a2ui__card')) {
            if (card.querySelector('.support-commerce-actions')) continue;
            const link = [...card.querySelectorAll('a[href]')].find(anchor => safeProduct(anchor.getAttribute('href')));
            const path = link?.getAttribute('href');
            if (!path) continue;
            const actions = document.createElement('div');
            actions.className = 'support-commerce-actions';
            for (const [label,action] of [['Add to cart','add'],['Buy now','buy_now'],['Checkout','checkout']]) {
                const button = document.createElement('button');
                button.type = 'button'; button.textContent = label;
                button.dataset.supportCommerce = action;
                button.dataset.productPath = path;
                actions.append(button);
            }
            card.append(actions);
        }
    };
    log.addEventListener('click', event => {
        const button = event.target.closest?.('button[data-support-commerce]');
        if (!button || !log.contains(button) || button.disabled || stopped || !canShop) return;
        const action = button.dataset.supportCommerce;
        if (action === 'checkout') {
            showPane(button);
            if (busy()) { setStatus('Wait for the current cart or checkout action to finish.'); return; }
            shopping.checkout();
        } else if (['add','buy_now'].includes(action)) void selectProduct(button,button.dataset.productPath,action);
    });
    // Delegated controls remain usable after the existing Support HTML replay.
    // Only public paths/buttons are persisted; the private form and capabilities are outside the log.
    const observer = new MutationObserver(decorate);
    observer.observe(log,{ childList:true,subtree:true });
    decorate();
    const restoreSaved = () => { try {
        const saved = sessionStorage.getItem(storageKey);
        if (saved) {
            if (saved.length > 2048) throw Error();
            const snapshot = validatedRecovery(JSON.parse(saved));
            saveRecovery(snapshot);
            if (snapshot.currentCart || snapshot.buyNowCart || snapshot.checkout) {
                restoring = true;
                const version = lifecycleVersion;
                if (recoveryNote) recoveryNote.textContent = 'Checking saved cart and payment access with the server…';
                Promise.resolve(shopping.restore(snapshot)).catch(() => {
                    if (!stopped && version === lifecycleVersion) setStatus('Saved shopping access could not be refreshed. Check again before making a new payment.');
                }).finally(() => { if (version === lifecycleVersion) { restoring = false; signalNativeIdle(false); } });
            }
        }
    } catch (_) {
        try { sessionStorage.removeItem(storageKey); }
        catch (_) { storageUnavailable(); }
        if (recoveryNote && !recoveryNote.textContent) recoveryNote.textContent = 'Saved chat shopping could not be restored. No saved product or payment state was trusted.';
    } };
    // Browser WebMCP callers pass the completed canonical MCP result. Only the
    // fixed public commerce family reaches the shared controller; delivery,
    // coupon input, text content and unrelated App metadata are not retained.
    window.BapxSupportCheckout = Object.freeze({
        async acceptToolResult(name,args,result,options = {}) {
            const unavailable = { rendered:false };
            const signal = options?.signal;
            if (signal && (typeof signal.aborted !== 'boolean' || typeof signal.addEventListener !== 'function'
                || typeof signal.removeEventListener !== 'function')) return unavailable;
            if (stopped || !Object.hasOwn(commerceKinds,name) || (!canShop && name !== 'get_checkout_status')
                || signal?.aborted
                || typeof shopping.acceptToolResult !== 'function' || !args || typeof args !== 'object' || Array.isArray(args)
                || !result || typeof result !== 'object' || result.isError
                || result._meta?.['in.bapx/shoppingView'] !== commerceKinds[name]
                || !result.structuredContent || typeof result.structuredContent !== 'object' || Array.isArray(result.structuredContent)) return unavailable;
            const input = {};
            if (name === 'create_cart' && (typeof result.structuredContent.cartToken !== 'string'
                || !cartPattern.test(result.structuredContent.cartToken))) return unavailable;
            if (name !== 'create_cart' && name !== 'get_checkout_status') {
                if (typeof args.cartToken !== 'string' || !cartPattern.test(args.cartToken)) return unavailable;
                input.cartToken = args.cartToken;
            }
            if (['set_cart_quantity','review_checkout','prepare_checkout_payment'].includes(name)) {
                if (!Number.isSafeInteger(args.expectedRevision) || args.expectedRevision < 0 || args.expectedRevision > 1000000) return unavailable;
                input.expectedRevision = args.expectedRevision;
            }
            if (name === 'get_checkout_status') {
                if (typeof args.paymentToken !== 'string' || !paymentPattern.test(args.paymentToken)) return unavailable;
                input.paymentToken = args.paymentToken;
            }
            if (name === 'prepare_checkout_payment') {
                if (typeof args.quoteId !== 'string' || !/^quote_[a-f0-9]{32}$/.test(args.quoteId) || args.confirm !== true) return unavailable;
                input.quoteId = args.quoteId;
                input.confirm = true;
                if (result.structuredContent.quoteId !== args.quoteId
                    || typeof result.structuredContent.paymentToken !== 'string' || !paymentPattern.test(result.structuredContent.paymentToken)
                    || result.structuredContent.paymentToken.slice(8,40) !== result.structuredContent.attemptId) return unavailable;
            }
            if (name !== 'create_cart' && result.structuredContent.cartToken !== undefined
                && result.structuredContent.cartToken !== input.cartToken) return unavailable;
            if (name === 'review_checkout' && result.structuredContent.cart?.cartToken !== undefined
                && result.structuredContent.cart.cartToken !== input.cartToken) return unavailable;
            if (name === 'get_checkout_status' && result.structuredContent.paymentToken !== undefined
                && result.structuredContent.paymentToken !== input.paymentToken) return unavailable;
            args = input;
            try {
                const encoded = JSON.stringify({ structuredContent:result.structuredContent,
                    _meta:{ 'in.bapx/shoppingView':commerceKinds[name] } });
                if (encoded.length > 500000) return unavailable;
                result = JSON.parse(encoded);
            } catch (_) { return unavailable; }
            const version = lifecycleVersion;
            if (!await waitForNativeIdle(signal) || stopped || version !== lifecycleVersion || signal?.aborted) return unavailable;
            try {
                const rendered = await shopping.acceptToolResult(name,args,result,{signal});
                if (!rendered || stopped || version !== lifecycleVersion || signal?.aborted) return unavailable;
                panel.hidden = false;
                document.querySelector('.support-fab')?.setAttribute('aria-expanded','true');
                showPane(toggle);
                return { rendered:true };
            } catch (_) { return unavailable; }
        }
    });
    restoreSaved();
    window.addEventListener('pagehide', event => {
        stopped = true;
        lifecycleVersion++;
        selectionBusy = false;
        restoring = false;
        signalNativeIdle(true);
        clearTimeout(recoveryTimer);
        observer.disconnect();
        for (const controller of controllers) controller.abort();
        if (event.persisted) shopping.cancel(); else shopping.teardown();
        container.replaceChildren();
    });
    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        stopped = false;
        container.inert = false;
        log.querySelectorAll('button[data-support-commerce]').forEach(button => { button.disabled = false; });
        observer.observe(log,{childList:true,subtree:true});
        decorate();
        restoreSaved();
    });
})();
