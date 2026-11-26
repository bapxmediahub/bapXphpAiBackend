(() => {
    // Published business facts are available on every public page, including /.
    const registry = document.modelContext;
    const node = document.getElementById('bapx-public-business-schema');
    if (typeof registry?.registerTool !== 'function' || !node) return;
    let contracts;
    try { contracts = JSON.parse(node.textContent); } catch { return; }
    if (!contracts?.contact?.inputSchema || !contracts.contact.outputSchema
        || !contracts?.services?.inputSchema || !contracts.services.outputSchema) return;

    const lifecycle = new AbortController();
    let ready = false;
    window.addEventListener('pagehide', event => {
        if (!event.persisted) lifecycle.abort();
    });
    const object = value => value && typeof value === 'object' && !Array.isArray(value);
    const keys = (value, allowed) => object(value)
        && Object.keys(value).every(key => allowed.includes(key));
    const text = (value, max) => typeof value === 'string' && Array.from(value).length <= max;
    const pageInput = input => {
        if (!keys(input, ['query', 'offset', 'limit'])) throw new TypeError('Invalid service search arguments.');
        const {query = '', offset = 0, limit = 20} = input;
        if (!text(query, 120) || !Number.isSafeInteger(offset) || offset < 0 || offset > 2147483647
            || !Number.isInteger(limit) || limit < 1 || limit > 20) {
            throw new TypeError('Use a short query, nonnegative offset and limit 1 to 20.');
        }
        return {q: query, offset, limit};
    };
    const contact = data => {
        if (!keys(data, ['name', 'path', 'phones', 'email', 'hours', 'servicePath'])
            || !text(data.name, 160) || !data.name.trim() || data.path !== '/contact'
            || !text(data.email, 254) || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)
            || data.servicePath !== '/contact#contact-form'
            || !Array.isArray(data.phones) || data.phones.length > 2
            || !data.phones.every(phone => keys(phone, ['label', 'number'])
                && text(phone.label, 32) && typeof phone.number === 'string' && /^\+[0-9]{7,15}$/.test(phone.number))
            || !Array.isArray(data.hours) || data.hours.length > 2
            || !data.hours.every(row => keys(row, ['days', 'hours']) && text(row.days, 40) && text(row.hours, 40))) {
            throw new TypeError('Invalid published contact result.');
        }
        return data;
    };
    const services = data => {
        if (!keys(data, ['services', 'total', 'nextOffset'])
            || !Array.isArray(data.services) || data.services.length > 4
            || !Number.isInteger(data.total) || data.total < 0 || data.total > 4
            || !(data.nextOffset === null || (Number.isInteger(data.nextOffset) && data.nextOffset >= 0 && data.nextOffset < data.total))
            || !data.services.every(service => keys(service, ['name', 'description', 'path'])
                && text(service.name, 160) && service.name.trim() && text(service.description, 280)
                && typeof service.path === 'string'
                && /^\/contact\?subject=(product|temple|order|general)#contact-form$/.test(service.path))) {
            throw new TypeError('Invalid published services result.');
        }
        return data;
    };
    const get = async (path, params = {}, signal) => {
        // A tool can be discovered while its sibling is still registering.
        // Do not execute a partially installed business capability group.
        if (!ready || lifecycle.signal.aborted) throw new Error('Public business browser tools unavailable.');
        const url = new URL(path, window.location.origin);
        for (const [key, value] of Object.entries(params)) url.searchParams.set(key, String(value));
        const response = await fetch(url, {method: 'GET', credentials: 'omit',
            headers: {Accept: 'application/json'}, signal: signal ? AbortSignal.any([lifecycle.signal, signal]) : lifecycle.signal});
        if (!response.ok) throw new Error('Published business information unavailable.');
        return response.json();
    };
    const definitions = [
        {
            name: 'get_public_contact',
            title: 'Get published contact details',
            description: 'Get the business name, public phone numbers, support email, opening hours and enquiry page. Use this for how to contact the business. Reads published information only; does not send a message.',
            ...contracts.contact,
            async execute(input = {}, { signal } = {}) {
                if (!keys(input, []) || Object.keys(input).length) throw new TypeError('Contact lookup takes no arguments.');
                return contact(await get('/api/knowledge/contact', {}, signal));
            }
        },
        {
            name: 'list_public_services',
            title: 'List published services and enquiry options',
            description: 'List or search the existing product enquiry, temple guidance, order support and general enquiry options. Returns descriptions and contact-form links. No booking or order changes. Follow nextOffset for more.',
            ...contracts.services,
            async execute(input = {}, { signal } = {}) {
                return services(await get('/api/knowledge/services', pageInput(input), signal));
            }
        }
    ];
    (async () => {
        try {
            for (const definition of definitions) {
                if (lifecycle.signal.aborted) return;
                await registry.registerTool({
                    ...definition, annotations: {readOnlyHint: true, untrustedContentHint: true}
                }, {signal: lifecycle.signal});
            }
            ready = !lifecycle.signal.aborted;
        } catch {
            // Registration is asynchronous; if either declaration fails,
            // abort the shared lifetime to remove any installed sibling.
            lifecycle.abort();
            console.warn('Public business browser tools could not be registered.');
        }
    })();
})();
