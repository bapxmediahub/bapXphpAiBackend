(() => {
    'use strict';
    // Deliberately narrow A2UI v1.0 Basic catalog renderer for this storefront.
    // No model HTML, script, arbitrary functions, forms, network or mutating actions.
    const CATALOG = 'https://a2ui.org/specification/v1_0/catalogs/basic/catalog.json';
    const TYPES = new Set(['Column', 'Card', 'Text', 'Button']);
    const PATH = /^\/(?:product\/[A-Za-z0-9%_.-]{1,200}|blog\/[a-z0-9-]{1,120})$/;
    const COMPONENT_ID = /^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/;
    const DATA_PATH = /^\/(?:heading|items\/[0-3]\/(?:title|detail|path))$/;

    function validate(payload) {
        if (!payload || typeof payload !== 'object'
            || !Array.isArray(payload.messages) || payload.messages.length !== 1) {
            throw new TypeError('Expected one A2UI message.');
        }
        const message = payload.messages[0];
        const surface = message?.createSurface;
        if (message?.version !== '1.0'
            || !surface || surface.catalogId !== CATALOG
            || typeof surface.surfaceId !== 'string'
            || !/^bapx_(?:products|articles)_[a-f0-9]{16}$/.test(surface.surfaceId)
            || !Array.isArray(surface.components)
            || surface.components.length < 2 || surface.components.length > 30
            || !surface.dataModel || typeof surface.dataModel !== 'object'
            || Array.isArray(surface.dataModel)
            || typeof surface.dataModel.heading !== 'string'
            || !Array.isArray(surface.dataModel.items)
            || surface.dataModel.items.length > 4) {
            throw new TypeError('Untrusted A2UI surface or catalog.');
        }
        const nodes = new Map();
        for (const c of surface.components) {
            if (!c || !COMPONENT_ID.test(c.id || '')
                || !TYPES.has(c.component) || nodes.has(c.id)
                || c['@catalogId'] || c['@checks'] || c['@accessibility']?.hidden) {
                throw new TypeError('Unknown A2UI component.');
            }
            nodes.set(c.id,c);
        }
        if (nodes.get('root')?.component !== 'Column') throw new TypeError('A2UI root missing.');
        const itemValid = i => i && typeof i === 'object'
            && typeof i.title === 'string' && i.title.length <= 200
            && typeof i.detail === 'string' && i.detail.length <= 180
            && typeof i.path === 'string' && PATH.test(i.path);
        if (!surface.dataModel.items.every(itemValid)) throw new TypeError('Invalid A2UI item.');
        const refs=new Set();
        function resolve(value) {
            if (typeof value === 'string') return value;
            const p=value?.['@path'];
            if (typeof p !== 'string' || !DATA_PATH.test(p)) throw new TypeError('Invalid A2UI binding.');
            if (p==='/heading') return surface.dataModel.heading;
            const [, , index, field]=p.split('/');
            return surface.dataModel.items[Number(index)]?.[field] ?? '';
        }
        function node(id, depth=0, parents=new Set()) {
            const def=nodes.get(id);
            if (!def || depth>8 || parents.has(id)) throw new TypeError('Cyclic or missing component.');
            const stack=new Set(parents);stack.add(id);refs.add(id);
            let el;
            switch(def.component) {
                case 'Column': {
                    if (!Array.isArray(def.children) || def.children.length>8) throw new TypeError('Invalid children.');
                    el=document.createElement('div');el.className='bapx-a2ui__column';
                    for(const child of def.children) el.appendChild(node(child,depth+1,stack));
                    break;
                }
                case 'Card': {
                    el=document.createElement('section');el.className='bapx-a2ui__card';
                    el.appendChild(node(def.child,depth+1,stack));
                    break;
                }
                case 'Text': {
                    if (def.variant && def.variant !== 'caption') throw new TypeError('Invalid text variant.');
                    el=document.createElement(def.variant==='caption'?'small':'p');
                    el.className='bapx-a2ui__text';
                    el.textContent=String(resolve(def.text)).slice(0,240);
                    break;
                }
                case 'Button': {
                    const call=def.action?.functionCall;
                    if (call?.['@call']!=='openUrl' || def.action?.event
                        || !def.child || !nodes.has(def.child)) throw new TypeError('Unknown action.');
                    const href=resolve(call.args?.url);
                    if (typeof href!=='string' || !PATH.test(href)
                        || href.includes('%2f') || href.includes('%2F')
                        || href.includes('..')) throw new TypeError('Unsafe A2UI navigation.');
                    // Native same-origin anchor = an explicit human gesture, not a
                    // background tool execution or programmatic purchase.
                    el=document.createElement('a');
                    el.className='bapx-a2ui__link';
                    el.href=href;
                    const label=nodes.get(def.child);
                    if (label.component!=='Text') throw new TypeError('Invalid button label.');
                    el.textContent=String(resolve(label.text)).slice(0,70);
                    refs.add(def.child);
                    break;
                }
            }
            return el;
        }
        const fragment=document.createDocumentFragment();
        fragment.appendChild(node('root'));
        return fragment;
    }

    function render(container, payload) {
        if (!(container instanceof Element)) return false;
        try {
            const safe=validate(payload);
            const section=document.createElement('section');
            section.className='bapx-a2ui';
            section.setAttribute('aria-label','Published references');
            section.appendChild(safe);
            container.appendChild(section);
            return true;
        } catch {
            // The human-readable answer remains intact when a renderer sees a
            // future protocol or an untrusted/non-compliant component.
            return false;
        }
    }
    window.BapxA2ui = Object.freeze({render, validate});
})();