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

    // Imperative WebMCP registration is scoped to this shop document.
    // https://webmachinelearning.github.io/webmcp/#dom-modelcontext-registertool
    async function register() {
        await registry.registerTool({
            name: 'list_shop_results',
            title: 'List shop results',
            description: 'Read a page of the products shown by the current shop search, category and sort, including displayed prices, availability and product links. Follow nextOffset for more results.',
            inputSchema: {
                type: 'object',
                properties: {
                    offset: { type: 'integer', minimum: 0, maximum: Number.MAX_SAFE_INTEGER, default: 0 },
                    limit: { type: 'integer', minimum: 1, maximum: limit, default: limit }
                },
                additionalProperties: false
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
                for (let i = offset; i < cards.length && products.length < size; i++) {
                    const card = cards[i];
                    const title = card.querySelector('.product-card__title');
                    products.push({
                        name: title.textContent.trim(),
                        url: title.href,
                        price: card.querySelector('.price').textContent.trim(),
                        available: Boolean(card.querySelector('[data-cart-control]'))
                    });
                }
                const end = offset + products.length;
                return { products, total: cards.length, nextOffset: end < cards.length ? end : null };
            }
        }, { signal: lifecycle.signal });
    }
    register().catch(() => console.warn('The shop browser tool could not be registered.'));
})();
