(() => {
    const form = document.getElementById('page-draft-form');
    if (!form?.dataset.draftOwner) return;
    const prefix = 'bapx:page-draft:' + form.dataset.draftOwner + ':';
    const key = prefix + (form.elements.original_slug.value || ':new');
    const revision = form.elements.expected_revision.value;
    const fields = ['title', 'slug', 'content'];
    const recovery = document.getElementById('page-draft-recovery');
    const message = document.getElementById('page-draft-message');
    const status = document.getElementById('page-draft-status');
    const restore = document.getElementById('page-draft-restore');
    const download = document.getElementById('page-draft-download');
    const discard = document.getElementById('page-draft-discard');
    const edit = document.getElementById('page-draft-fields');
    const values = () => Object.fromEntries(fields.map(name => [name, form.elements[name].value]));
    const valid = item => item && typeof item.revision === 'string'
        && typeof item.token === 'string' && /^[a-f0-9]{32}$/.test(item.token)
        && fields.every(name => typeof item.values?.[name] === 'string');
    let token = '', candidate = null, timer, dirty = false, submitting = false;
    const read = () => JSON.parse(localStorage.getItem(key));
    const unavailable = () => {status.textContent = 'Browser draft storage is unavailable. Save or copy your work.';};
    function saveDraft() {
        clearTimeout(timer);
        if (!dirty || candidate) return;
        try {
            const existing = read();
            if ((existing?.token || '') !== token) {
                status.textContent = 'Another tab changed this browser draft. Save or copy this edit before reloading.';
                return;
            }
            const current = values();
            if (new TextEncoder().encode(current.content).length > 65536
                || current.title.length > 320 || current.slug.length > 32) {
                status.textContent = 'Draft exceeds the page limits. Copy your work before leaving.';
                return;
            }
            const next = Array.from(crypto.getRandomValues(new Uint8Array(16)), b => b.toString(16).padStart(2, '0')).join('');
            localStorage.setItem(key, JSON.stringify({revision, token:next, values:current}));
            token = next;
            dirty = false;
            status.textContent = 'Draft saved in this browser. Use Save page to update the site.';
        } catch { unavailable(); }
    }
    function closeRecovery() {
        candidate = null;
        recovery.hidden = true;
        edit.inert = false;
        form.elements.title.focus();
    }
    try {
        const saved = form.dataset.savedDraft;
        if (saved) {
            for (const name of Object.keys(localStorage).filter(name => name.startsWith(prefix))) {
                try {
                    if (JSON.parse(localStorage.getItem(name))?.token === saved) localStorage.removeItem(name);
                } catch { /* Ignore malformed unrelated browser copies. */ }
            }
        }
        const stored = read();
        if (valid(stored)) {
            const current = stored.revision === revision;
            // A preview/validation response already shows the submitted text;
            // do not hide it or silently discard a distinct concurrent draft.
            if (current && form.dataset.submitted === '1'
                && fields.every(name => stored.values[name] === form.elements[name].value)) {
                token = stored.token;
            } else {
                candidate = stored;
                message.textContent = current
                    ? 'Restore or discard the browser draft to continue editing. Publication status is not restored.'
                    : 'This page changed since the browser draft. Download it before discarding; newer saved content cannot be overwritten by recovery.';
                restore.hidden = !current;
                recovery.hidden = false;
                edit.inert = true;
                (current ? restore : download).focus();
            }
        }
    } catch { unavailable(); }
    restore.addEventListener('click', () => {
        if (!candidate || candidate.revision !== revision) return;
        for (const name of fields) {
            if (!form.elements[name].readOnly) form.elements[name].value = candidate.values[name];
        }
        token = candidate.token;
        closeRecovery();
        status.textContent = 'Browser draft restored. Review visibility and use Save page when ready.';
    });
    download.addEventListener('click', () => {
        if (!candidate) return;
        const blob = new Blob(['# ', candidate.values.title, '\n\n', candidate.values.content], {type:'text/markdown;charset=utf-8'});
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url; link.download = 'page-draft.md'; link.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    });
    discard.addEventListener('click', () => {
        if (!candidate) return;
        try {
            if (read()?.token !== candidate.token) {
                status.textContent = 'Another tab changed this browser draft. Reload before discarding.';
                return;
            }
            localStorage.removeItem(key);
        } catch { unavailable(); return; }
        token = '';
        closeRecovery();
        status.textContent = 'Browser copy discarded. The saved page is unchanged.';
    });
    form.addEventListener('input', event => {
        if (!fields.includes(event.target.name)) return;
        dirty = true;
        clearTimeout(timer);
        timer = setTimeout(saveDraft, 300);
    });
    form.addEventListener('submit', event => {
        if (candidate) { event.preventDefault(); return; }
        saveDraft();
        form.elements.browser_draft_token.value = dirty ? '' : token;
        submitting = true;
    });
    window.addEventListener('pagehide', saveDraft);
    window.addEventListener('beforeunload', event => {
        saveDraft();
        if (dirty && !submitting) {event.preventDefault(); event.returnValue = '';}
    });
})();
