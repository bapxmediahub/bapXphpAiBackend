(() => {
    // Schemas come from the PHP PublicKnowledgeService; keep one contract.
    const registry = document.modelContext;
    const node = document.getElementById('bapx-public-knowledge-schema');
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
            || Object.keys(data).some(key => !['articles', 'total', 'nextOffset'].includes(key))
            || !Array.isArray(data.articles) || !Number.isSafeInteger(data.total)
            || data.total < 0 || data.articles.length > 20
            || !(data.nextOffset === null
                || (Number.isSafeInteger(data.nextOffset) && data.nextOffset >= 0))) {
            throw new TypeError('Invalid public article result.');
        }
        for (const article of data.articles) {
            if (!article || typeof article.title !== 'string' || !article.title.trim()
                || Object.keys(article).some(key => !['title', 'path', 'category', 'score'].includes(key))
                || typeof article.path !== 'string'
                || !/^\/blog\/[a-z0-9-]{1,120}$/.test(article.path)
                || typeof article.category !== 'string'
                || !Number.isInteger(article.score) || article.score < 1) {
                throw new TypeError('Invalid public article record.');
            }
        }
        return data;
    };

    const register = async () => { await registry.registerTool({
        name: 'search_public_articles',
        title: 'Search published articles and guides',
        description: 'Search publicly published blog posts and customer help guides. Returns only titles, paths, categories and relevance; not drafts, private admin help or agent sessions. Pass nextOffset to continue.',
        inputSchema: contract.inputSchema,
        outputSchema: contract.outputSchema,
        annotations: { readOnlyHint: true, untrustedContentHint: true },
        async execute(input = {}) {
            if (!input || typeof input !== 'object' || Array.isArray(input)
                || Object.keys(input).some(k => !['query', 'offset', 'limit'].includes(k))) {
                throw new TypeError('Invalid public article search arguments.');
            }
            const { query = '', offset = 0, limit = 5 } = input;
            if (typeof query !== 'string' || query.length > 120
                || !Number.isSafeInteger(offset) || offset < 0 || offset > 2147483647
                || !Number.isInteger(limit) || limit < 1 || limit > 20) {
                throw new TypeError('Use a short query, nonnegative offset and limit 1 to 20.');
            }
            const url = new URL('/api/knowledge/articles', window.location.origin);
            url.searchParams.set('q', query);
            url.searchParams.set('offset', String(offset));
            url.searchParams.set('limit', String(limit));
            const response = await fetch(url, {
                method: 'GET',
                credentials: 'omit',
                headers: { Accept: 'application/json' },
                signal: lifecycle.signal
            });
            if (!response.ok) throw new Error('Public article search unavailable.');
            return validate(await response.json());
        }
    }, { signal: lifecycle.signal }); };
    register().catch(() => {
        console.warn('Public article browser tool could not be registered.');
    });
})();
