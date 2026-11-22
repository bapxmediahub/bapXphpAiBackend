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
    const PUBLIC_DATA = /^\/(?:heading|notice|items\/[0-3]\/(?:title|detail|path|description|imagePath|priceLabel|availability|imagePaths\/[0-2]|highlights\/[0-3]|specifications\/[0-5]\/(?:label|value)))$/;
    const ANSWER_DATA = /^\/(?:heading|sections\/[0-7]\/body|facts\/[01]\/(?:title|context|metrics\/[0-6]\/(?:label|value))|reports\/0\/(?:title|context|basis|coverage|rows\/[0-5]\/(?:title|values\/[0-4]\/(?:label|value)))|actions\/[0-5]\/(?:label|path))$/;
    const PRESENTATIONS = Object.freeze({products:['product_grid','product_list','product_detail','product_compare'],
        articles:['article_list'],contact:['contact_panel'],services:['service_tiles']});
    const smallText = (text,max) => typeof text === 'string' && length(text) <= max;
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
            const publicKind = /^bapx_(products|articles|contact|services)_[a-f0-9]{16}$/.exec(surface?.surfaceId || '')?.[1];
            const publicSurface = Boolean(publicKind);
            const answerSurface = new RegExp('^bapx_' + scope + '_[a-f0-9]{16}$').test(surface?.surfaceId || '') && scope !== 'public';
            const expectedSurface = scope === 'public' ? publicSurface : index === 0 ? answerSurface : publicSurface;
            if (message?.version !== '1.0' || !surface || surface.catalogId !== CATALOG
                || !expectedSurface
                || !Array.isArray(surface.components) || surface.components.length < 2 || surface.components.length > (answerSurface ? 224 : 96)
                || !surface.dataModel || typeof surface.dataModel !== 'object' || Array.isArray(surface.dataModel)
                || typeof surface.dataModel.heading !== 'string' || length(surface.dataModel.heading) > 200) {
                throw new TypeError('Untrusted A2UI surface or catalog.');
            }
            const data = surface.dataModel;
            const presentation = data.presentation || 'legacy';
            if (data.presentation !== undefined && (typeof data.presentation !== 'string'
                || !(publicSurface ? PRESENTATIONS[publicKind] : ['answer_sections','report_summary']).includes(data.presentation))) {
                throw new TypeError('Invalid presentation family.');
            }
            if (publicSurface) {
                if (data.notice !== undefined && !smallText(data.notice,160)) throw new TypeError('Invalid public notice.');
                if (!Array.isArray(data.items) || data.items.length > 4 || !data.items.every(item =>
                    item && typeof item === 'object' && typeof item.title === 'string' && length(item.title) <= 200
                    && typeof item.detail === 'string' && length(item.detail) <= 240 && safePublicPath(item.path)
                    && (item.description === undefined || (typeof item.description === 'string' && length(item.description) <= 240))
                    && (item.imagePath === undefined || safeImagePath(item.imagePath)))) throw new TypeError('Invalid public item.');
                if (data.sections !== undefined || data.facts !== undefined || data.reports !== undefined || data.actions !== undefined) {
                    throw new TypeError('Private data is not a public view.');
                }
                if (presentation !== 'legacy' && publicKind !== 'products' && data.items.some(item =>
                    publicKind === 'articles' ? !/^\/blog\/[a-z0-9-]{1,120}$/.test(item.path)
                        : publicKind === 'services' ? !/^\/contact\?subject=(?:product|temple|order|general)#contact-form$/.test(item.path)
                        : !/^\/contact(?:#contact-form)?$/.test(item.path))) throw new TypeError('Mismatched public destination.');
                if (publicKind === 'products' && presentation !== 'legacy') {
                    if ((presentation === 'product_detail' && data.items.length !== 1)
                        || (presentation === 'product_compare' && (data.items.length < 2 || data.items.length > 3))
                        || new Set(data.items.map(item=>item.path)).size !== data.items.length
                        || !data.items.every(item => item.path.startsWith('/product/')
                            && typeof item.priceInr === 'number' && Number.isFinite(item.priceInr) && item.priceInr >= 0 && item.priceInr <= 1e15
                            && smallText(item.priceLabel,80) && typeof item.available === 'boolean'
                            && item.availability === (item.available ? 'Available' : 'Unavailable')
                            && Array.isArray(item.imagePaths) && item.imagePaths.length <= 3
                            && new Set(item.imagePaths).size === item.imagePaths.length && item.imagePaths.every(safeImagePath)
                            && (item.imagePaths.length === 0 ? item.imagePath === undefined : item.imagePaths[0] === item.imagePath)
                            && Array.isArray(item.highlights) && item.highlights.length <= 4 && item.highlights.every(text=>smallText(text,160))
                            && Array.isArray(item.specifications) && item.specifications.length <= 6
                            && item.specifications.every(spec=>spec && smallText(spec.label,80) && smallText(spec.value,240)))) {
                        throw new TypeError('Invalid product layout data.');
                    }
                    if (presentation === 'product_compare' && data.items.some(item=>JSON.stringify(item.specifications.map(spec=>spec.label))
                        !== JSON.stringify(data.items[0].specifications.map(spec=>spec.label)))) throw new TypeError('Comparison fields do not match.');
                }
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
                if (facts.some(fact=>fact.primary !== undefined && (!Number.isInteger(fact.primary) || fact.primary < 0 || fact.primary >= fact.metrics.length))) {
                    throw new TypeError('Invalid primary fact.');
                }
                const reports = data.reports || [];
                if (!Array.isArray(reports) || reports.length > 1 || (scope !== 'admin' && reports.length)
                    || !reports.every(report => report && ['products','stock','customers','coupons'].includes(report.kind)
                        && smallText(report.title,80) && smallText(report.context,320) && smallText(report.basis,500) && smallText(report.coverage,160)
                        && Array.isArray(report.rows) && report.rows.length <= 6
                        && report.rows.every(row => row && smallText(row.title,160) && Array.isArray(row.values) && row.values.length <= 5
                            && row.values.every(value=>value && smallText(value.label,80) && smallText(value.value,80))))) {
                    throw new TypeError('Invalid recorded report.');
                }
                if (presentation === 'report_summary' && !facts.length && !reports.length) throw new TypeError('A report requires recorded evidence.');
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
                if (!def || depth > 10 || parents.has(id) || ++renderedNodes > (answerSurface ? 320 : 160)) throw new TypeError('Invalid component graph.');
                const stack = new Set(parents); stack.add(id);
                let element;
                switch (def.component) {
                    case 'Column':
                        if (!Array.isArray(def.children) || def.children.length > 12) throw new TypeError('Invalid children.');
                        if (/^extra_images_[0-3]$/.test(id)) {
                            if (presentation !== 'product_detail' || def.children.length < 1 || def.children.length > 2
                                || def.children.some(child=>nodes.get(child)?.component !== 'Image')) throw new TypeError('Invalid product photo disclosure.');
                            // Native disclosure remains keyboard-operable after Support restores saved HTML.
                            element = document.createElement('details'); element.className = 'bapx-a2ui__gallery';
                            const summary = document.createElement('summary');summary.textContent = 'More product photos (' + def.children.length + ')';
                            element.appendChild(summary);
                        } else {
                            element = document.createElement('div'); element.className = 'bapx-a2ui__column';
                            if (id === 'items') element.classList.add('bapx-a2ui__items');
                            if (/^body_[0-3]$/.test(id)) element.classList.add('bapx-a2ui__item-body');
                            if (/^highlights_[0-3]$/.test(id)) element.classList.add('bapx-a2ui__highlights');
                            if (/^specifications_[0-3]$/.test(id)) element.classList.add('bapx-a2ui__specifications');
                            if (id === 'actions') element.classList.add('bapx-a2ui__actions');
                            if (id === 'report_rows') element.classList.add('bapx-a2ui__report-rows');
                        }
                        for (const child of def.children) element.appendChild(node(child,depth+1,stack));
                        break;
                    case 'Row':
                        if (!Array.isArray(def.children) || def.children.length !== 2) throw new TypeError('Invalid fact row.');
                        if (publicSurface) {
                            const bindings=def.children.map(child=>nodes.get(child)?.text?.['@path']);
                            const pair= presentation === 'contact_panel' ? /^\/items\/([0-3])\/title$/ :
                                ['product_detail','product_compare'].includes(presentation) ? /^\/items\/([0-3]\/specifications\/[0-5])\/label$/ : null;
                            const match=pair && pair.exec(bindings[0] || '');
                            if (!match || bindings[1] !== '/items/' + match[1] + (presentation === 'contact_panel' ? '/detail' : '/value')) {
                                throw new TypeError('Invalid public detail row.');
                            }
                        }
                        element = document.createElement('div'); element.className = 'bapx-a2ui__row';
                        if (publicSurface) element.classList.add('bapx-a2ui__detail-row');
                        const metric=/^metric_([01])_([0-6])$/.exec(id);
                        if (answerSurface && metric && data.facts?.[+metric[1]]?.primary === +metric[2]) element.classList.add('bapx-a2ui__primary-metric');
                        for (const child of def.children) {
                            if (nodes.get(child)?.component !== 'Text') throw new TypeError('Invalid fact value.');
                            element.appendChild(node(child,depth+1,stack));
                        }
                        break;
                    case 'Card':
                        element = document.createElement('section'); element.className = 'bapx-a2ui__card';
                        if (/^answer_[0-7]$/.test(id)) element.classList.add('bapx-a2ui__answer-section');
                        if (/^fact_[01]$/.test(id)) element.classList.add('bapx-a2ui__fact');
                        if (/^report_row_[0-5]$/.test(id)) element.classList.add('bapx-a2ui__report-item');
                        element.appendChild(node(def.child,depth+1,stack));
                        break;
                    case 'Text': {
                        if (def.variant && def.variant !== 'caption') throw new TypeError('Invalid text variant.');
                        const text = resolve(def.text);
                        const binding = def.text?.['@path'] || '';
                        const max = answerSurface && /^\/sections\/[0-7]\/body$/.test(binding) ? 24000
                            : answerSurface && /^\/(?:facts\/[01]|reports\/0)\/context$/.test(binding) ? 320
                            : answerSurface && binding === '/reports/0/basis' ? 500 : 240;
                        if (length(text) > max) throw new TypeError('Text budget exceeded.');
                        const heading=binding === '/heading' ? 'h2' : /^\/(?:items\/[0-3]|facts\/[01]|reports\/0)\/title$/.test(binding) ? 'h3'
                            : /^\/reports\/0\/rows\/[0-5]\/title$/.test(binding) ? 'h4' : null;
                        element = document.createElement(heading || (def.variant === 'caption' ? 'small' : 'p'));
                        element.className = 'bapx-a2ui__text'; element.textContent = text;
                        if (/^\/items\/[0-3]\/priceLabel$/.test(binding)) element.classList.add('bapx-a2ui__price');
                        if (/^\/items\/[0-3]\/availability$/.test(binding)) {
                            element.classList.add('bapx-a2ui__availability');
                            element.dataset.available=String(data.items[+binding.split('/')[2]].available);
                        }
                        if (binding === '/reports/0/basis' || binding.endsWith('/context') || binding.endsWith('/coverage')) element.classList.add('bapx-a2ui__context');
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
            if (presentation !== 'legacy') section.classList.add('bapx-a2ui--' + presentation.replaceAll('_','-'));
            section.dataset.presentation=presentation;
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
