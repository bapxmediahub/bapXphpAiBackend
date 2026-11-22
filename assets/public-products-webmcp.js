(() => {
    // Full public catalogue, separate from the visible-card snapshot on /shop.
    const registry = document.modelContext;
    const node = document.getElementById('bapx-public-products-schema');
    if (typeof registry?.registerTool !== 'function' || !node) return;

    let contract;
    try { contract = JSON.parse(node.textContent); }
    catch { return; }
    if (!contract?.inputSchema || !contract?.outputSchema) return;

    // This value is emitted by the page from deployment configuration. A response
    // cannot select its own image origin, and aliases must use the canonical site.
    let publicOrigin = null;
    try {
        const origin = new URL(contract.publicOrigin);
        if (origin.protocol === 'https:' && origin.origin === contract.publicOrigin
            && !origin.username && !origin.password) publicOrigin = origin.origin;
    } catch { /* Old relative-only fixtures and ordinary page rendering still work. */ }
    const officialUrl = (value, path, maxLength) => typeof value === 'string'
        && value.length <= maxLength && publicOrigin !== null && value === publicOrigin + path;

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
                || Object.keys(product).some(key => !['name', 'path', 'productUrl', 'priceInr', 'available', 'description', 'imagePath', 'imageUrl', 'imagePaths', 'imageUrls', 'highlights', 'specifications'].includes(key))
                || typeof product.name !== 'string' || !product.name.trim()
                || Array.from(product.name).length > 160
                || !safeProductPath(product.path)
                || (product.productUrl !== undefined && !officialUrl(product.productUrl,product.path,4096))
                || typeof product.priceInr !== 'number'
                || !Number.isFinite(product.priceInr) || product.priceInr < 0
                || typeof product.available !== 'boolean'
                || typeof product.description !== 'string' || Array.from(product.description).length > 280
                || (product.imagePath !== undefined && !safeImage(product.imagePath))
                || (product.imageUrl !== undefined && (product.imagePath === undefined
                    || !officialUrl(product.imageUrl,product.imagePath,2560)))
                || (product.imagePaths !== undefined && (!Array.isArray(product.imagePaths)
                    || product.imagePaths.length > 3 || new Set(product.imagePaths).size !== product.imagePaths.length
                    || Array.from(product.imagePaths).some(path => !safeImage(path))))
                || (product.imageUrls !== undefined && (!Array.isArray(product.imagePaths)
                    || !Array.isArray(product.imageUrls) || product.imageUrls.length !== product.imagePaths.length
                    || Array.from(product.imageUrls).some((url,index) => !officialUrl(url,product.imagePaths[index],2560))))
                || (product.highlights !== undefined && (!Array.isArray(product.highlights)
                    || product.highlights.length > 4 || product.highlights.some(value => !boundedText(value,160))))
                || (product.specifications !== undefined && (!Array.isArray(product.specifications)
                    || product.specifications.length > 6 || product.specifications.some(row =>
                        !row || typeof row !== 'object' || Array.isArray(row)
                        || Object.keys(row).some(key => !['label','value'].includes(key))
                        || !boundedText(row.label,80) || !boundedText(row.value,240))))) {
                throw new TypeError('Invalid public catalogue product.');
            }
        }
        return data;
    };
    const safeProductPath = path => {
        if (typeof path !== 'string' || path.length > 2048 || !/^\/product\/[A-Za-z0-9%_.-]+$/.test(path)) return false;
        try {
            return /^[\p{L}\p{N}_][\p{L}\p{N}\p{M}._ -]*$/u.test(decodeURIComponent(path.slice('/product/'.length)));
        } catch { return false; }
    };
    const safeImage = path => {
        if (typeof path !== 'string' || path.length > 512
            || !/^\/assets\/images\/[A-Za-z0-9%_./-]+\.(?:avif|gif|jpe?g|png|webp)$/i.test(path)) return false;
        try {
            return path.slice('/assets/images/'.length).split('/').every(segment =>
                /^[\p{L}\p{N}_][\p{L}\p{N}\p{M}._ -]*$/u.test(decodeURIComponent(segment)));
        } catch { return false; }
    };
    const boundedText = (value, max) => typeof value === 'string' && value.trim()
        && Array.from(value).length <= max;
    const safeRawSlug = slug => typeof slug === 'string'
        && /^[\p{L}\p{N}_][\p{L}\p{N}\p{M}._ -]*$/u.test(slug)
        && encodeURIComponent(slug).length + '/product/'.length <= 2048;

    // Native implementations may return void; the official polyfill returns a Promise.
    const register = async () => { await registry.registerTool({
        name: 'search_public_products',
        title: 'Search full public product catalogue',
        description: 'Search the full published catalogue by query and follow nextOffset. Returns current INR prices, availability, short descriptions, safe product images and paths. For exact detail or comparison, pass up to three raw slugs decoded once from returned product paths, with query empty, offset 0 and a limit covering the selection. Only matching published products return, in selected order, with bounded galleries, highlights and specifications. Use present_public_knowledge with view products for cards. list_shop_results only reads the current shop document.',
        inputSchema: contract.inputSchema,
        outputSchema: contract.outputSchema,
        annotations: { readOnlyHint: true, untrustedContentHint: true },
        async execute(input = {}) {
            if (!input || typeof input !== 'object' || Array.isArray(input)
                || Object.keys(input).some(key => !['query', 'offset', 'limit', 'slugs'].includes(key))) {
                throw new TypeError('Invalid public product search arguments.');
            }
            const { query = '', offset = 0, limit = 20, slugs = [] } = input;
            if (typeof query !== 'string' || Array.from(query).length > 120
                || !Number.isSafeInteger(offset) || offset < 0 || offset > 2147483647
                || !Number.isInteger(limit) || limit < 1 || limit > 20
                || !Array.isArray(slugs) || slugs.length > 3 || new Set(slugs).size !== slugs.length
                || Array.from(slugs).some(slug => !safeRawSlug(slug))
                || (slugs.length && (query.trim() || offset !== 0 || limit < slugs.length))) {
                throw new TypeError('Use bounded search arguments, or up to three unique raw slugs with query empty, offset 0 and limit covering the selection.');
            }
            const url = new URL('/api/knowledge/products', window.location.origin);
            url.searchParams.set('q', query);
            url.searchParams.set('offset', String(offset));
            url.searchParams.set('limit', String(limit));
            for (const slug of slugs) url.searchParams.append('slugs[]', slug);
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
