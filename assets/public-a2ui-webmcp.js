(() => {
    // Portable A2UI v1.0 is DATA, not a way to execute arbitrary browser UI code.
    const registry = document.modelContext;
    const node=document.getElementById('bapx-a2ui-schema');
    if (typeof registry?.registerTool!=='function' || !node) return;
    let contract;
    try { contract=JSON.parse(node.textContent); } catch { return; }
    if (!contract?.inputSchema || !contract?.outputSchema) return;
    const views=contract.inputSchema.properties?.view?.enum;
    if(!Array.isArray(views))return;
    const lifecycle=new AbortController();
    window.addEventListener('pagehide',event=>{
        if(!event.persisted)lifecycle.abort();
    });
    const register = async () => { await registry.registerTool({
        name:'present_public_knowledge',
        title:'Present public business information as cards',
        description:'Return bounded A2UI 1.0 product, article, contact or enquiry cards. Search with query, or select up to three exact raw product slugs for details. Use layout compare with two or three selected slugs; never combine a selection with query. Uses only published data and safe website links.',
        inputSchema:contract.inputSchema,
        outputSchema:contract.outputSchema,
        annotations:{readOnlyHint:true,untrustedContentHint:true},
        async execute(input = {}, { signal } = {}) {
            if(!input || typeof input!=='object' || Array.isArray(input)
                || Object.keys(input).some(k=>!['view','query','limit','layout','slugs'].includes(k))) {
                throw new TypeError('Invalid A2UI input.');
            }
            const {view,query='',limit=4,layout='auto',slugs=[]}=input;
            if(typeof view!=='string' || !views.includes(view)
                || typeof query!=='string' || Array.from(query).length>120
                || !Number.isInteger(limit) || limit<1 || limit>4
                || !['auto','grid','list','detail','compare'].includes(layout)
                || !Array.isArray(slugs) || slugs.length>3 || slugs.length>limit || new Set(slugs).size!==slugs.length
                || Array.from(slugs).some(slug=>typeof slug!=='string'||!slug||!/^[\p{L}\p{N}_][\p{L}\p{N}\p{M}._ -]*$/u.test(slug)||encodeURIComponent(slug).length>2039)
                || (view!=='products'&&(layout!=='auto'||slugs.length))
                || (slugs.length&&query!=='') || (layout==='compare'&&slugs.length<2)
                || (layout==='detail'&&slugs.length>1)) {
                throw new TypeError('Choose a public view and limit 1..4.');
            }
            const url=new URL('/api/knowledge/a2ui',window.location.origin);
            url.searchParams.set('view',view);
            url.searchParams.set('query',query);
            url.searchParams.set('limit',String(limit));
            url.searchParams.set('layout',layout);
            if(slugs.length)url.searchParams.set('slugs',JSON.stringify(slugs));
            const response=await fetch(url,{
                method:'GET',credentials:'omit',
                headers:{Accept:'application/json'},signal: signal ? AbortSignal.any([lifecycle.signal, signal]) : lifecycle.signal
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
