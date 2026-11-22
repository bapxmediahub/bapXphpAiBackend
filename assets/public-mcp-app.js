/* MCP Apps iframe protocol 2026-01-26; independent of the outer MCP transport version.
 * https://apps.extensions.modelcontextprotocol.io/api/documents/overview.html
 * Public A2UI is read-only. The optional fixed shopping template calls only its named scoped
 * server tools after a human action; all calls pass through the host, never direct fetch.
 */
(() => {
    'use strict';
    const status = document.getElementById('mcp-status');
    const cards = document.getElementById('mcp-cards');
    const fallback = document.getElementById('mcp-fallback');
    const configNode = document.getElementById('bapx-mcp-config');
    if (!status || !cards || !fallback || !configNode) return;
    let origin;
    let shoppingEnabled = false;
    let deliveryStates = [];
    let canShop = true;
    try {
        const config = JSON.parse(configNode.textContent);
        const url = new URL(config.publicBaseUrl);
        if (url.protocol !== 'https:' || url.username || url.password || url.search || url.hash || url.pathname !== '/') throw Error();
        origin = url.origin;
        shoppingEnabled = config.shopping === true;
        if (shoppingEnabled && (!Array.isArray(config.deliveryStates) || config.deliveryStates.length > 50
            || !config.deliveryStates.every(value => typeof value === 'string' && value.length <= 80)
            || new Set(config.deliveryStates).size !== config.deliveryStates.length)) throw Error();
        deliveryStates = config.deliveryStates || [];
        canShop = config.canShop !== false;
    } catch (_) {
        status.textContent = 'Public information could not be opened. See the chat answer.';
        return;
    }
    if (window.parent === window) {
        status.textContent = 'Open this view from a compatible chat application.';
        return;
    }

    const version = '2026-01-26';
    const initId = 'bapx-ui-initialize';
    const pending = new Map();
    let ready = false;
    let stopped = false;
    let hostCapabilities = {};
    let nextId = 0;
    let lastSize = '';
    let displayRevision = 0;
    let linkAttempt = 0;
    const linkError = 'The link could not be opened. Use the website link in the chat answer.';
    let resizeFrame = 0;
    const send = message => window.parent.postMessage({ jsonrpc: '2.0', ...message }, '*');
    const notify = (method, params) => send({ method, ...(params === undefined ? {} : { params }) });
    const showFallback = result => {
        const text = Array.isArray(result?.content)
            ? result.content.filter(x => x?.type === 'text' && typeof x.text === 'string').map(x => x.text).join('\n')
            : '';
        fallback.textContent = text.replace(/\b(?:cart|payment)_[a-f0-9]{32}\.[a-f0-9]{64}\b/g, '[shopping access omitted]').slice(0, 65536);
        fallback.hidden = !fallback.textContent;
    };
    const resize = () => {
        if (!ready || stopped || resizeFrame) return;
        resizeFrame = requestAnimationFrame(() => {
            resizeFrame = 0;
            const height = Math.min(12000, Math.ceil(document.body.getBoundingClientRect().height));
            const width = Math.ceil(document.documentElement.clientWidth);
            const key = `${width}:${height}`;
            if (height > 0 && width > 0 && key !== lastSize) {
                lastSize = key;
                notify('ui/notifications/size-changed', { width, height });
            }
        });
    };
    const applyContext = context => {
        if (context?.theme === 'light' || context?.theme === 'dark') {
            document.documentElement.style.colorScheme = context.theme;
        }
        resize();
    };
    const request = (method, params) => new Promise((resolve, reject) => {
        if (!ready || stopped) { reject(Error('The view is not ready.')); return; }
        const id = `bapx-ui-${++nextId}`;
        const timer = setTimeout(() => {
            pending.delete(id);
            reject(Error('The chat application did not respond.'));
        }, method === 'tools/call' ? 45000 : 10000);
        pending.set(id, { resolve, reject, timer });
        send({ id, method, params });
    });
    const openLink = async target => {
        const revision = displayRevision;
        const attempt = ++linkAttempt;
        const current = () => !stopped && revision === displayRevision && attempt === linkAttempt;
        try {
            const url = new URL(target);
            if (!hostCapabilities.openLinks || url.origin !== origin || url.username || url.password) throw Error();
            const result = await request('ui/open-link', { url: url.href });
            if (result?.isError) throw Error('The host declined this link.');
            if (current() && status.textContent === linkError) {
                status.textContent = '';
                resize();
            }
            return true;
        } catch (_) {
            if (current()) {
                status.textContent = linkError;
                resize();
            }
            return false;
        }
    };
    const shopping = shoppingEnabled && window.BapxShopping ? window.BapxShopping.create({
        container: cards, origin, request, openLink, resize, deliveryStates, canShop,
        capabilities: () => hostCapabilities,
        setStatus: message => { status.textContent = message; }
    }) : null;
    const observer = typeof ResizeObserver === 'function' ? new ResizeObserver(resize) : null;
    observer?.observe(document.body);
    const initTimer = setTimeout(() => {
        if (!ready) status.textContent = 'The view could not connect. See the chat answer for this information.';
    }, 10000);

    const receive = event => {
        // The immediate parent is the host's sandbox proxy. Its opaque origin can differ between hosts.
        if (stopped || event.source !== window.parent) return;
        const message = event.data;
        if (!message || typeof message !== 'object' || message.jsonrpc !== '2.0') return;
        if (message.method === 'ui/resource-teardown' && message.id !== undefined) {
            stopped = true;
            shopping?.teardown();
            clearTimeout(initTimer);
            observer?.disconnect();
            if (resizeFrame) cancelAnimationFrame(resizeFrame);
            pending.forEach(entry => { clearTimeout(entry.timer); entry.reject(Error('The view closed.')); });
            pending.clear();
            window.removeEventListener('message', receive);
            send({ id: message.id, result: {} });
            return;
        }
        if (message.id === initId && !message.method && !ready) {
            clearTimeout(initTimer);
            const result = message.result;
            if (message.error || result?.protocolVersion !== version || !result.hostCapabilities
                || typeof result.hostCapabilities !== 'object' || !result.hostInfo
                || typeof result.hostInfo.name !== 'string' || typeof result.hostInfo.version !== 'string') {
                status.textContent = 'This chat application could not open the view. See the text answer.';
                return;
            }
            hostCapabilities = result.hostCapabilities;
            ready = true;
            status.textContent = 'Waiting for public information…';
            notify('ui/notifications/initialized');
            applyContext(result.hostContext);
            resize();
            return;
        }
        if (message.id !== undefined && !message.method && pending.has(message.id)) {
            const entry = pending.get(message.id);
            pending.delete(message.id);
            clearTimeout(entry.timer);
            if (message.error) entry.reject(Error('The requested action was declined.'));
            else entry.resolve(message.result);
            return;
        }
        if (!ready) return;
        if (message.method === 'ping' && message.id !== undefined) {
            send({ id: message.id, result: {} });
            return;
        }
        if (message.id !== undefined) {
            send({ id: message.id, error: { code: -32601, message: 'Method not found.' } });
            return;
        }
        if (message.method === 'ui/notifications/host-context-changed') {
            applyContext(message.params);
            return;
        }
        if (message.method === 'ui/notifications/tool-cancelled') {
            displayRevision++;
            shopping?.cancel();
            cards.replaceChildren();
            fallback.hidden = true;
            status.textContent = 'This request was cancelled.';
            resize();
            return;
        }
        if (message.method === 'ui/notifications/tool-input') {
            shopping?.onToolInput(message.params);
            return;
        }
        if (message.method !== 'ui/notifications/tool-result') return;
        const result = message.params;
        displayRevision++;
        showFallback(result);
        if (shopping?.render(result)) {
            fallback.hidden = true;
            resize();
            return;
        }
        cards.replaceChildren();
        const surface = result?._meta?.['in.bapx/publicSurface'];
        let rendered = false;
        if (!result?.isError && surface && window.BapxA2ui) {
            try {
                rendered = window.BapxA2ui.render(cards, surface, {
                    publicBaseUrl: origin,
                    ...(hostCapabilities.openLinks ? { openUrl: openLink } : {})
                }) !== false;
            } catch (_) { rendered = false; }
        }
        if (rendered) {
            status.textContent = '';
            fallback.hidden = true;
            if (!hostCapabilities.openLinks) cards.querySelectorAll('a').forEach(link => {
                link.target = '_blank'; link.rel = 'noopener noreferrer';
            });
        } else {
            status.textContent = result?.isError ? 'This information is unavailable.' : 'Public information';
            if (!fallback.textContent) status.textContent = 'See the chat answer for this information.';
        }
        resize();
    };
    window.addEventListener('message', receive);
    send({ id: initId, method: 'ui/initialize', params: {
        protocolVersion: version,
        appInfo: { name: shoppingEnabled ? 'bapx-public-shopping' : 'bapx-public-knowledge', version: '1.0.0' },
        appCapabilities: { availableDisplayModes: ['inline'] }
    } });
})();
