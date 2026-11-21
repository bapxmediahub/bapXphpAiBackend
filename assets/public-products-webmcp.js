(() => {
    // Full public catalogue, separate from the visible-card snapshot on /shop.
    const registry = document.modelContext;
    const node = document.getElementById('bapx-public-products-schema');
    if (typeof registry?.registerTool !== 'function' || !node) return;

    let contract;
    try { contract = JSON.parse(node.textContent); }
    catch { return; }
    if (!contract?.inputSchema || !contract?.outputSchema) return;

    const lifecycle = new AbortController();
    window.addEventListener('pagehide', event => {
        if (!event.persisted) lifecycle.abort();
    });

    const validate = data => {
        if (!data || typeof data !== 'object' || Array.isArray(data)
            || !Array.isArray(data.products)
            || data.products.length > 20
            || !Number.isSafeInteger(data.total) || data.total < 0
            || !(data.nextOffset === null ||
                (Number.isSafeInteger(data.nextOffset) && data.nextOffset >= 0))) {
            throw new TypeError('Invalid public catalogue result.');
        }
        for (const product of data.products) {
            if (!product || typeof product !== 'object'
                || typeof product.name !== 'string' || !product.name.trim()
                || typeof product.path !== 'string'
                || !/^\/product\/[^/?#]+$/.test(product.path)
                || typeof product.priceInr !== 'number'
                || !Number.isFinite(product.priceInr) || product.priceInr < 0
                || typeof product.available !== 'boolean') {
                throw new TypeError('Invalid public catalogue product.');
            }
        }
        return data;
    };

    registry.registerTool({
        name: 'search_public_products',
        title: 'Search full public product catalogue',
        description: 'Search the currently published shop catalogue. Unlike list_shop_results, this queries public server-side records beyond the cards currently in the DOM. Returns current INR prices, availability and product paths. Follow nextOffset.',
        inputSchema: contract.inputSchema,
        outputSchema: contract.outputSchema,
        annotations: { readOnlyHint: true, untrustedContentHint: true },
        async execute(input = {}) {
            if (!input || typeof input !== 'object' || Array.isArray(input)
                || Object.keys(input).some(key => !['query', 'offset', 'limit'].includes(key))) {
                throw new TypeError('Invalid public product search arguments.');
            }
            const { query = '', offset = 0, limit = 20 } = input;
            if (typeof query !== 'string' || query.length > 120
                || !Number.isSafeInteger(offset) || offset < 0 || offset > 2147483647
                || !Number.isInteger(limit) || limit < 1 || limit > 20) {
                throw new TypeError('Use a short query, nonnegative offset and limit 1 to 20.');
            }
            const url = new URL('/api/knowledge/products', window.location.origin);
            url.searchParams.set('q', query);
            url.searchParams.set('offset', String(offset));
            url.searchParams.set('limit', String(limit));
            const response = await fetch(url, {
                method: 'GET',
                credentials: 'omit',
                headers: { Accept: 'application/json' },
                signal: lifecycle.signal
            });
            if (!response.ok) throw new Error('Public product search unavailable.');
            return validate(await response.json());
        }
    }, { signal: lifecycle.signal }).catch(() => {
        console.warn('Public catalogue browser tool could not be registered.');
    });
})();