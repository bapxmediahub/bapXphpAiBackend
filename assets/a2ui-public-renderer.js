(() => {
    'use strict';
    // Server-owned A2UI templates. No HTML, scripts, arbitrary calls or write actions.
    const CATALOG = 'https://a2ui.org/specification/v1_0/catalogs/basic/catalog.json';
    const TYPES = new Set(['Column', 'Row', 'Card', 'Text', 'Button', 'Image']);
    const length = text => [...text].length;
    const truncate = (text,max) => [...text].slice(0,max).join('');
    const COMPONENT_ID = /^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/;
    const PUBLIC_PATH = /^\/(?:blog\/[a-z0-9-]{1,120}|shop|blog|temples|contact(?:\?subject=(?:product|temple|order|general))?(?:#contact-form)?|about|support)$/;
    const safeSegment = segment => {
        if (!/^[A-Za-z0-9%_.-]+$/.test(segment)) return false;
        try { return /^[\p{L}\p{N}_][\p{L}\p{N}\p{M}._ -]*$/u.test(decodeURIComponent(segment)); }
        catch { return false; }
    };
    const PUBLIC_DATA = /^\/(?:heading|items\/[0-3]\/(?:title|detail|path|description|imagePath))$/;
    const ANSWER_DATA = /^\/(?:heading|sections\/[0-7]\/body|facts\/[01]\/(?:title|context|metrics\/[0-6]\/(?:label|value))|actions\/[0-5]\/(?:label|path))$/;
    const safePublicPath = path => typeof path === 'string' && (path.startsWith('/product/')
        ? path.length <= 2048 && safeSegment(path.slice(9)) : PUBLIC_PATH.test(path));
    const safeImagePath = path => typeof path === 'string' && path.length <= 512 && path.startsWith('/assets/images/')
        && /\.(?:avif|gif|jpe?g|png|webp)$/i.test(path) && path.slice(15).split('/').every(safeSegment);
    const safeActionPath = (path,scope) => safePublicPath(path)
        || (scope !== 'public' && /^\/(?:login|cart|checkout|account\/dashboard(?:\/orders|\/install)?)$/.test(path))
        || (scope === 'admin' && /^\/admin\/(?:support-tickets|docs\/[a-z0-9-]{1,80}|tax-report\?report=stock|tax-report\?report=(?:sales|tax|products|customers|coupons)&from=\d{4}-\d{2}-\d{2}&to=\d{4}-\d{2}-\d{2})$/.test(path));

    function validate(payload, options = {}) {
        const scope = options.scope || 'public';
        if (!['public','support','admin'].includes(scope)) throw new TypeError('Unknown host scope.');
        if (options.openUrl !== undefined && typeof options.openUrl !== 'function') throw new TypeError('Invalid host link handler.');
        let origin = null;
        if (options.publicBaseUrl !== undefined) {
            const base = new URL(options.publicBaseUrl);
            if (base.protocol !== 'https:' || base.username || base.password || base.pathname !== '/' || base.search || base.hash) {
                throw new TypeError('Expected a trusted HTTPS public origin.');
            }
            origin = base.origin;
        }
        const publicUrl = path => origin ? new URL(path, origin).href : path;
        if (!payload || typeof payload !== 'object' || JSON.stringify(payload).length > 100000
            || !Array.isArray(payload.messages) || payload.messages.length < 1
            || payload.messages.length > (scope === 'public' ? 1 : 2)) {
            throw new TypeError('Invalid A2UI message budget.');
        }
        const fragment = document.createDocumentFragment();
        for (const [index,message] of payload.messages.entries()) {
            const surface = message?.createSurface;
            const publicSurface = /^bapx_(?:products|articles|contact|services)_[a-f0-9]{16}$/.test(surface?.surfaceId || '');
            const answerSurface = new RegExp('^bapx_' + scope + '_[a-f0-9]{16}$').test(surface?.surfaceId || '') && scope !== 'public';
            const expectedSurface = scope === 'public' ? publicSurface : index === 0 ? answerSurface : publicSurface;
            if (message?.version !== '1.0' || !surface || surface.catalogId !== CATALOG
                || !expectedSurface
                || !Array.isArray(surface.components) || surface.components.length < 2 || surface.components.length > (answerSurface ? 96 : 40)
                || !surface.dataModel || typeof surface.dataModel !== 'object' || Array.isArray(surface.dataModel)
                || typeof surface.dataModel.heading !== 'string' || length(surface.dataModel.heading) > 200) {
                throw new TypeError('Untrusted A2UI surface or catalog.');
            }
            const data = surface.dataModel;
            if (publicSurface) {
                if (!Array.isArray(data.items) || data.items.length > 4 || !data.items.every(item =>
                    item && typeof item === 'object' && typeof item.title === 'string' && length(item.title) <= 200
                    && typeof item.detail === 'string' && length(item.detail) <= 240 && safePublicPath(item.path)
                    && (item.description === undefined || (typeof item.description === 'string' && length(item.description) <= 240))
                    && (item.imagePath === undefined || safeImagePath(item.imagePath)))) throw new TypeError('Invalid public item.');
            } else {
                if (!Array.isArray(data.sections) || data.sections.length < 1 || data.sections.length > 8
                    || !data.sections.every(section => section && typeof section.body === 'string' && length(section.body) <= 24000)
                    || data.sections.reduce((n,section) => n + [...section.body].length,0) > 12000
                    || !Array.isArray(data.actions) || data.actions.length > 6
                    || !data.actions.every(action => action && typeof action.label === 'string' && length(action.label) <= 80
                        && safeActionPath(action.path,scope))) throw new TypeError('Invalid actor answer.');
                const facts = data.facts || [];
                if (!Array.isArray(facts) || facts.length > 2 || (scope !== 'admin' && facts.length)
                    || !facts.every(fact => fact && typeof fact.title === 'string' && length(fact.title) <= 80
                        && typeof fact.context === 'string' && length(fact.context) <= 320
                        && Array.isArray(fact.metrics) && fact.metrics.length > 0 && fact.metrics.length <= 7
                        && fact.metrics.every(metric => metric && typeof metric.label === 'string' && length(metric.label) <= 80
                            && typeof metric.value === 'string' && length(metric.value) <= 80))) throw new TypeError('Invalid recorded facts.');
            }
            const nodes = new Map();
            for (const component of surface.components) {
                if (!component || !COMPONENT_ID.test(component.id || '') || !TYPES.has(component.component)
                    || nodes.has(component.id) || component['@catalogId'] || component['@checks']
                    || component['@accessibility']?.hidden) throw new TypeError('Unknown A2UI component.');
                nodes.set(component.id,component);
            }
            if (nodes.get('root')?.component !== 'Column') throw new TypeError('A2UI root missing.');
            const resolve = value => {
                if (typeof value === 'string') return value;
                const path = value?.['@path'];
                if (typeof path !== 'string' || !(publicSurface ? PUBLIC_DATA : ANSWER_DATA).test(path)) throw new TypeError('Invalid binding.');
                let result = data;
                for (const part of path.slice(1).split('/')) result = result?.[part];
                if (typeof result !== 'string') throw new TypeError('Missing binding.');
                return result;
            };
            let renderedNodes = 0;
            function node(id, depth = 0, parents = new Set()) {
                const def = nodes.get(id);
                if (!def || depth > 8 || parents.has(id) || ++renderedNodes > (answerSurface ? 160 : 80)) throw new TypeError('Invalid component graph.');
                const stack = new Set(parents); stack.add(id);
                let element;
                switch (def.component) {
                    case 'Column':
                        if (!Array.isArray(def.children) || def.children.length > 12) throw new TypeError('Invalid children.');
                        element = document.createElement('div'); element.className = 'bapx-a2ui__column';
                        for (const child of def.children) element.appendChild(node(child,depth+1,stack));
                        break;
                    case 'Row':
                        if (!answerSurface || !Array.isArray(def.children) || def.children.length !== 2) throw new TypeError('Invalid fact row.');
                        element = document.createElement('div'); element.className = 'bapx-a2ui__row';
                        for (const child of def.children) {
                            if (nodes.get(child)?.component !== 'Text') throw new TypeError('Invalid fact value.');
                            element.appendChild(node(child,depth+1,stack));
                        }
                        break;
                    case 'Card':
                        element = document.createElement('section'); element.className = 'bapx-a2ui__card';
                        element.appendChild(node(def.child,depth+1,stack));
                        break;
                    case 'Text': {
                        if (def.variant && def.variant !== 'caption') throw new TypeError('Invalid text variant.');
                        const text = resolve(def.text);
                        const binding = def.text?.['@path'] || '';
                        const max = answerSurface && /^\/sections\/[0-7]\/body$/.test(binding) ? 24000
                            : answerSurface && /^\/facts\/[01]\/context$/.test(binding) ? 320 : 240;
                        if (length(text) > max) throw new TypeError('Text budget exceeded.');
                        element = document.createElement(def.variant === 'caption' ? 'small' : 'p');
                        element.className = 'bapx-a2ui__text'; element.textContent = text;
                        break;
                    }
                    case 'Image': {
                        const path = resolve(def.url);
                        if (!publicSurface || !safeImagePath(path) || def.fit !== 'contain') throw new TypeError('Unsafe public image.');
                        element = document.createElement('img'); element.className = 'bapx-a2ui__image';
                        element.alt = truncate(resolve(def.description),200);
                        element.loading = 'lazy'; element.decoding = 'async'; element.src = publicUrl(path);
                        break;
                    }
                    case 'Button': {
                        const call = def.action?.functionCall;
                        if (call?.['@call'] !== 'openUrl' || def.action?.event) throw new TypeError('Unknown action.');
                        const path = resolve(call.args?.url);
                        if (!(publicSurface ? safePublicPath(path) : safeActionPath(path,scope))) throw new TypeError('Unsafe navigation.');
                        const label = nodes.get(def.child);
                        if (label?.component !== 'Text') throw new TypeError('Invalid button label.');
                        element = document.createElement('a'); element.className = 'bapx-a2ui__link';
                        element.href = publicSurface ? publicUrl(path) : path;
                        element.textContent = truncate(resolve(label.text),80);
                        if (publicSurface && options.openUrl) {
                            const url = new URL(path, origin || window.location.origin).href;
                            element.addEventListener('click',event => {
                                event.preventDefault();
                                try { Promise.resolve(options.openUrl(url)).catch(() => {}); } catch {}
                            });
                        }
                        break;
                    }
                }
                return element;
            }
            const section = document.createElement('section');
            section.className = 'bapx-a2ui' + (answerSurface ? ' bapx-a2ui--answer' : '');
            section.setAttribute('aria-label',data.heading);
            section.appendChild(node('root'));
            fragment.appendChild(section);
        }
        return fragment;
    }

    function render(container,payload,options = {}) {
        if (!(container instanceof Element)) return false;
        try { container.appendChild(validate(payload,options)); return true; }
        catch { return false; } // Original response remains the complete fallback.
    }
    window.BapxA2ui = Object.freeze({render,validate});
})();
