(() => {
    const registry = document.modelContext;
    if (typeof registry?.registerTool !== 'function') return;

    const results = document.querySelector('.shop-results');
    if (!results) return;

    const limit = 20;
    const lifecycle = new AbortController();
    window.addEventListener('pagehide', event => {
        if (!event.persisted) lifecycle.abort();
    });
    const safeSegment = segment => {
        if (!/^[A-Za-z0-9%_.-]+$/.test(segment)) return false;
        try { return /^[\p{L}\p{N}_][\p{L}\p{N}\p{M}._ -]*$/u.test(decodeURIComponent(segment)); }
        catch { return false; }
    };
    const safeProductPath = path => path.length <= 2048 && path.startsWith('/product/')
        && safeSegment(path.slice('/product/'.length));
    const imagePath = image => {
        const source = image?.currentSrc || image?.getAttribute?.('src') || image?.src;
        if (typeof source !== 'string' || !source) return null;
        try {
            const rawPath = source.replace(/^https?:\/\/[^/]+/i, '');
            if (!rawPath.startsWith('/assets/images/') || rawPath.includes('?') || rawPath.includes('#')
                || !rawPath.slice('/assets/images/'.length).split('/').every(segment =>
                    /^[\p{L}\p{N}_][\p{L}\p{N}\p{M}._ -]*$/u.test(decodeURIComponent(segment)))) return null;
            const url = new URL(source, window.location.href);
            const path = url.pathname;
            return url.origin === window.location.origin && !url.username && !url.password && !url.search && !url.hash
                && path.length <= 512 && path.startsWith('/assets/images/')
                && /\.(?:avif|gif|jpe?g|png|webp)$/i.test(path)
                && path.slice('/assets/images/'.length).split('/').every(safeSegment) ? path : null;
        } catch { return null; }
    };

    // Imperative WebMCP registration is scoped to this shop document.
    // https://webmachinelearning.github.io/webmcp/#dom-modelcontext-registertool
    async function register() {
        await registry.registerTool({
            name: 'list_shop_results',
            title: 'List shop results',
            description: 'Read only products displayed in this shop document under its current search, category and sort, including displayed prices, availability, product links and optional visible descriptions/images. Follow nextOffset within this document. Use search_public_products for the full published catalogue or exact product selection; this tool does not search other pages or change the cart.',
            inputSchema: {
                type: 'object',
                properties: {
                    offset: { type: 'integer', minimum: 0, maximum: Number.MAX_SAFE_INTEGER, default: 0 },
                    limit: { type: 'integer', minimum: 1, maximum: limit, default: limit }
                },
                additionalProperties: false
            },
            outputSchema: {
                type: 'object', additionalProperties: false,
                required: ['products', 'total', 'nextOffset'],
                properties: {
                    products: {
                        type: 'array', maxItems: limit,
                        items: {
                            type: 'object', additionalProperties: false,
                            required: ['name', 'url', 'price', 'available'],
                            properties: {
                                name: { type: 'string', minLength: 1, maxLength: 160 },
                                url: { type: 'string', format: 'uri' },
                                price: { type: 'string', minLength: 1, maxLength: 64 },
                                available: { type: 'boolean' },
                                description: { type: 'string', maxLength: 280 },
                                imagePath: { type: 'string', maxLength: 512, pattern: '^/assets/images/[A-Za-z0-9%_./-]+$' }
                            }
                        }
                    },
                    total: { type: 'integer', minimum: 0 },
                    nextOffset: { type: ['integer', 'null'], minimum: 0 }
                }
            },
            annotations: { readOnlyHint: true, untrustedContentHint: true },
            execute(input = {}) {
                if (!input || typeof input !== 'object' || Array.isArray(input)
                    || Object.keys(input).some(key => key !== 'offset' && key !== 'limit')) {
                    throw new TypeError('Use only offset and limit.');
                }
                const { offset = 0, limit: size = limit } = input;
                if (!Number.isSafeInteger(offset) || offset < 0
                    || !Number.isInteger(size) || size < 1 || size > limit) {
                    throw new RangeError('Use a nonnegative integer offset and a limit from 1 to 20.');
                }

                const cards = results.querySelectorAll('.product-card');
                const products = [];
                const end = Math.min(cards.length, offset + size);
                for (let i = offset; i < end; i++) {
                    const card = cards[i];
                    const title = card?.querySelector?.('a.product-card__title[href]');
                    const name = title?.textContent?.trim() || '';
                    const price = card?.querySelector?.('.price')?.textContent?.trim() || '';
                    if (!name || Array.from(name).length > 160 || !price || Array.from(price).length > 64) continue;
                    let url;
                    try {
                        url = new URL(title.href, window.location.href);
                        if (url.origin !== window.location.origin || url.username || url.password
                            || !safeProductPath(url.pathname)
                            || url.search || url.hash) continue;
                    } catch {
                        continue;
                    }
                    // Reject bad DOM data, and advance the inspected-card cursor
                    // even when this particular card cannot be returned.
                    const product = {
                        name,
                        url: url.href,
                        price,
                        available: Boolean(card.querySelector('[data-cart-control]'))
                    };
                    const description = card.querySelector('.product-card__desc')?.textContent;
                    if (typeof description === 'string' && description.trim()) {
                        product.description = Array.from(description.replace(/[\s\u0000-\u001F\u007F]+/gu,' ').trim()).slice(0,280).join('');
                    }
                    const image = imagePath(card.querySelector('.product-card__image img'));
                    if (image !== null) product.imagePath = image;
                    products.push(product);
                }
                return { products, total: cards.length, nextOffset: end < cards.length ? end : null };
            }
        }, { signal: lifecycle.signal });
    }
    register().catch(() => console.warn('The shop browser tool could not be registered.'));
})();
