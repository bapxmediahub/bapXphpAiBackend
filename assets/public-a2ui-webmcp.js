(() => {
    // Portable A2UI v1.0 is DATA, not a way to execute arbitrary browser UI code.
    const registry = document.modelContext;
    const node=document.getElementById('bapx-a2ui-schema');
    if (typeof registry?.registerTool!=='function' || !node) return;
    let contract;
    try { contract=JSON.parse(node.textContent); } catch { return; }
    if (!contract?.inputSchema || !contract?.outputSchema) return;
    const lifecycle=new AbortController();
    window.addEventListener('pagehide',event=>{
        if(!event.persisted)lifecycle.abort();
    });
    const register = async () => { await registry.registerTool({
        name:'present_public_knowledge',
        title:'Present public shop or guide cards with A2UI',
        description:'Return a declarative A2UI 1.0 surface from published products or articles. It uses read-only public data and approved native Card, Column, Text and same-site navigation components. No checkout, private guides, admin or agent transcripts.',
        inputSchema:contract.inputSchema,
        outputSchema:contract.outputSchema,
        annotations:{readOnlyHint:true,untrustedContentHint:true},
        async execute(input={}) {
            if(!input || typeof input!=='object' || Array.isArray(input)
                || Object.keys(input).some(k=>!['view','query','limit'].includes(k))) {
                throw new TypeError('Invalid A2UI input.');
            }
            const {view,query='',limit=4}=input;
            if(!['products','articles'].includes(view)
                || typeof query!=='string' || query.length>120
                || !Number.isInteger(limit) || limit<1 || limit>4) {
                throw new TypeError('Choose products or articles and limit 1..4.');
            }
            const url=new URL('/api/knowledge/a2ui',window.location.origin);
            url.searchParams.set('view',view);
            url.searchParams.set('query',query);
            url.searchParams.set('limit',String(limit));
            const response=await fetch(url,{
                method:'GET',credentials:'omit',
                headers:{Accept:'application/json'},signal:lifecycle.signal
            });
            if(!response.ok)throw new Error('Published A2UI presentation unavailable.');
            const payload=await response.json();
            // Execute NO agent-supplied functions. Validate the A2UI message
            // against the same safe local catalog used by our chat UI.
            if(!window.BapxA2ui?.validate)throw new TypeError('A2UI validation unavailable.');
            window.BapxA2ui.validate(payload);
            return payload;
        }
    },{signal:lifecycle.signal}); };
    register().catch(()=>{
        console.warn('A2UI browser tool unavailable.');
    });
})();
