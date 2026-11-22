(() => {
    // Browser WebMCP and ordinary HTTP clients use the same named PHP actions.
    // https://developer.chrome.com/docs/ai/webmcp/imperative-api
    const registry = document.modelContext;
    const node = document.getElementById('bapx-public-commerce-schema');
    if (typeof registry?.registerTool !== 'function' || !node) return;
    const names = ['create_cart', 'get_cart', 'set_cart_quantity', 'review_checkout',
        'prepare_checkout_payment', 'get_checkout_status'];
    const record = value => value !== null && typeof value === 'object' && !Array.isArray(value);
    let config;
    try {
        config = JSON.parse(node.textContent);
        const origin = new URL(config.publicOrigin);
        if (origin.protocol !== 'https:' || origin.origin !== config.publicOrigin
            || origin.origin !== window.location.origin || origin.username || origin.password
            || typeof config.protocolVersion !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(config.protocolVersion)
            || !Array.isArray(config.tools)
            || !(config.tools.length === names.length || (config.tools.length === 1 && config.tools[0].name === 'get_checkout_status'))
            || new Set(config.tools.map(tool => tool.name)).size !== config.tools.length
            || !config.tools.every(tool => names.includes(tool.name) && record(tool.inputSchema)
                && record(tool.outputSchema) && typeof tool.description === 'string')) return;
    } catch { return; }

    const lifetime = new AbortController();
    let sequence = 0;
    window.addEventListener('pagehide', event => { if (!event.persisted) lifetime.abort(); });

    // The registry emits this bounded subset of JSON Schema. PHP still validates
    // every call and authorizes capabilities; these checks protect the browser UI.
    function valid(schema, value, depth = 0) {
        if (!record(schema) || depth > 12) return false;
        const types = Array.isArray(schema.type) ? schema.type : [schema.type];
        const type = value === null ? 'null' : Array.isArray(value) ? 'array' : typeof value;
        if (!types.includes(type) && !(type === 'number' && types.includes('integer') && Number.isSafeInteger(value))) return false;
        if (Object.hasOwn(schema, 'const') && schema.const !== value) return false;
        if (Array.isArray(schema.enum) && !schema.enum.includes(value)) return false;
        if (value === null) return true;
        if (type === 'object') {
            const properties = schema.properties ?? {};
            if (!record(properties) || (schema.required ?? []).some(key => !Object.hasOwn(value, key))) return false;
            if (schema.additionalProperties === false && Object.keys(value).some(key => !Object.hasOwn(properties, key))) return false;
            return Object.entries(value).every(([key, item]) => !Object.hasOwn(properties, key) || valid(properties[key], item, depth + 1));
        }
        if (type === 'array') return value.length >= (schema.minItems ?? 0)
            && value.length <= (schema.maxItems ?? 100) && value.every(item => valid(schema.items, item, depth + 1));
        if (type === 'number') return Number.isFinite(value) && value >= (schema.minimum ?? -Infinity)
            && value <= (schema.maximum ?? Infinity);
        if (type === 'string') {
            if ([...value].length < (schema.minLength ?? 0) || [...value].length > (schema.maxLength ?? 65536)
                || (schema.pattern && !new RegExp(schema.pattern, 'u').test(value))) return false;
            if (schema.format === 'date-time' && !Number.isFinite(Date.parse(value))) return false;
            if (schema.format === 'uri') {
                try { if (new URL(value).protocol !== 'https:') return false; } catch { return false; }
            }
        }
        return true;
    }

    function requestSignal(executionSignal) {
        const controller = new AbortController();
        const sources = [lifetime.signal, executionSignal].filter(Boolean);
        const abort = () => controller.abort();
        for (const source of sources) {
            if (typeof source.addEventListener !== 'function') throw new TypeError('Invalid execution signal.');
            if (source.aborted) controller.abort();
            else source.addEventListener('abort', abort, { once: true });
        }
        const timer = setTimeout(abort, 30000);
        return { signal: controller.signal, received() { clearTimeout(timer); }, close() {
            clearTimeout(timer);
            for (const source of sources) source.removeEventListener('abort', abort);
        } };
    }

    async function call(tool, args = {}, options = {}) {
        if (!valid(tool.inputSchema, args)) throw new TypeError('Use the documented arguments for this shopping action.');
        const id = 'webmcp-commerce-' + (++sequence);
        const body = JSON.stringify({ jsonrpc: '2.0', id, method: 'tools/call', params: {
            name: tool.name, arguments: args, _meta: {
                'io.modelcontextprotocol/protocolVersion': config.protocolVersion,
                'io.modelcontextprotocol/clientCapabilities': {},
                'io.modelcontextprotocol/clientInfo': { name: 'bapx-browser-commerce', version: '1.0' }
            }
        } });
        if (new TextEncoder().encode(body).length > 65536) throw new TypeError('Shopping request is too large.');
        const execution = requestSignal(options.signal);
        try {
            if (execution.signal.aborted) throw new Error('Shopping action was cancelled before it started.');
            const response = await fetch('/api/mcp', { method: 'POST', credentials: 'omit', cache: 'no-store',
                redirect: 'error', referrerPolicy: 'no-referrer', signal: execution.signal,
                headers: { 'Content-Type': 'application/json', Accept: 'application/json, text/event-stream',
                    'MCP-Protocol-Version': config.protocolVersion, 'Mcp-Method': 'tools/call', 'Mcp-Name': tool.name }, body });
            const text = await response.text();
            if (text.length > 500000) throw new Error('Shopping response is too large.');
            const envelope = JSON.parse(text);
            if (execution.signal.aborted || !response.ok || envelope?.jsonrpc !== '2.0' || envelope.id !== id
                || envelope.error || !record(envelope.result)) throw new Error('Shopping result could not be confirmed.');
            const result = envelope.result;
            if (result.isError) {
                const code = result._meta?.['in.bapx/shoppingError']?.code;
                const detail = result.content?.find(item => item.type === 'text')?.text;
                const error = new Error(typeof detail === 'string' && detail.length <= 500 ? detail : 'Shopping action could not be completed.');
                error.name = 'ShoppingActionError';
                if (typeof code === 'string' && /^[a-z_]{1,64}$/.test(code)) error.code = code;
                throw error;
            }
            if (!valid(tool.outputSchema, result.structuredContent)) throw new Error('Shopping returned an invalid result.');
            execution.received();
            // This promise also waits for any earlier human operation to finish.
            // No tool opens a payment page or stores delivery details in history.
            if (typeof window.BapxSupportCheckout?.acceptToolResult === 'function') {
                await window.BapxSupportCheckout.acceptToolResult(tool.name, args, result, { signal: execution.signal });
            }
            if (execution.signal.aborted) throw new Error('Shopping action ended before its result was received.');
            return result.structuredContent;
        } catch (error) {
            if (error?.name === 'ShoppingActionError') throw error;
            // Fetch cancellation does not roll back a server write. Do not claim
            // failure means no change, and never repeat a payment automatically.
            throw new Error('Shopping result is unconfirmed. Read the existing cart or payment status before another change; do not create a second payment.');
        } finally { execution.close(); }
    }

    // A partial registration is unusable as a checkout contract. Abort the group
    // together if either native registration or the official polyfill rejects.
    (async () => {
        try {
            for (const tool of config.tools) {
                if (lifetime.signal.aborted) return;
                await registry.registerTool({ name: tool.name, title: tool.title, description: tool.description,
                    inputSchema: tool.inputSchema, outputSchema: tool.outputSchema,
                    annotations: { readOnlyHint: tool.annotations?.readOnlyHint === true,
                        consequentialHint: tool.annotations?.readOnlyHint !== true, untrustedContentHint: true },
                    execute: (args, options) => call(tool, args, options)
                }, { signal: lifetime.signal });
            }
        } catch {
            lifetime.abort();
            console.warn('Public shopping browser tools could not be registered.');
        }
    })();
})();
