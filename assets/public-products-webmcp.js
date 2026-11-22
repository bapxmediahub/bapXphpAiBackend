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
            || Object.keys(data).some(key => !['products', 'total', 'nextOffset'].includes(key))
            || !Array.isArray(data.products)
            || data.products.length > 20
            || !Number.isSafeInteger(data.total) || data.total < 0
            || !(data.nextOffset === null ||
                (Number.isSafeInteger(data.nextOffset) && data.nextOffset >= 0))) {
            throw new TypeError('Invalid public catalogue result.');
        }
        for (const product of data.products) {
            if (!product || typeof product !== 'object'
                || Object.keys(product).some(key => !['name', 'path', 'priceInr', 'available', 'description', 'imagePath'].includes(key))
                || typeof product.name !== 'string' || !product.name.trim()
                || Array.from(product.name).length > 160
                || !safeProductPath(product.path)
                || typeof product.priceInr !== 'number'
                || !Number.isFinite(product.priceInr) || product.priceInr < 0
                || typeof product.available !== 'boolean'
                || typeof product.description !== 'string' || Array.from(product.description).length > 280
                || (product.imagePath !== undefined && !safeImage(product.imagePath))) {
                throw new TypeError('Invalid public catalogue product.');
            }
        }
        return data;
    };
    const safeProductPath = path => {
        if (typeof path !== 'string' || !/^\/product\/[A-Za-z0-9%_.-]+$/.test(path)) return false;
        try {
            return /^[\p{L}\p{N}_][\p{L}\p{N}._ -]*$/u.test(decodeURIComponent(path.slice('/product/'.length)));
        } catch { return false; }
    };
    const safeImage = path => {
        if (typeof path !== 'string' || path.length > 512
            || !/^\/assets\/images\/[A-Za-z0-9%_./-]+\.(?:avif|gif|jpe?g|png|webp)$/i.test(path)) return false;
        try {
            return path.slice('/assets/images/'.length).split('/').every(segment =>
                /^[\p{L}\p{N}_][\p{L}\p{N}._ -]*$/u.test(decodeURIComponent(segment)));
        } catch { return false; }
    };

    // Native implementations may return void; the official polyfill returns a Promise.
    const register = async () => { await registry.registerTool({
        name: 'search_public_products',
        title: 'Search full public product catalogue',
        description: 'Search the full published product catalogue by name or description. Returns current INR prices, availability, short descriptions, safe product images and paths. Follow nextOffset. For up to four display cards, use present_public_knowledge with view products.',
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
    }, { signal: lifecycle.signal }); };
    register().catch(() => {
        console.warn('Public catalogue browser tool could not be registered.');
    });
})();
