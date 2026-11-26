(() => {
    const form = document.getElementById('blog-form');
    if (!form?.dataset.draftOwner) return;

    const prefix = 'bapx:blog-draft:' + form.dataset.draftOwner + ':';
    const editFields = document.getElementById('be-edit-fields');
    const recovery = document.getElementById('be-recovery');
    const message = document.getElementById('be-recovery-message');
    const restore = document.getElementById('be-restore');
    const status = document.getElementById('be-draft-status');
    const submission = document.getElementById('be-draft-token');
    // Keep browser snapshots limited to editorial fields. Publication and email
    // choices require a fresh decision after restoration.
    const fields = [
        'slug', 'title', 'category', 'type', 'template', 'og_image', 'image_alt',
        'source_url', 'summary', 'order', 'excerpt', 'keywords', 'published_at',
        'author', 'content'
    ];
    let key;
    let revision;
    let candidate;
    let token;
    let timer;
    let dirty = false;

    try {
        const savedToken = form.dataset.savedDraft;
        if (savedToken) {
            for (const name of Object.keys(localStorage).filter(name => name.startsWith(prefix))) {
                if (JSON.parse(localStorage.getItem(name))?.token === savedToken) {
                    localStorage.removeItem(name);
                }
            }
        }
    } catch {
        // The editor still posts normally when browser storage is unavailable.
    }

    function saveDraft() {
        clearTimeout(timer);
        if (!dirty || candidate || form.hidden || !key) return;
        window.beSyncMarkdown();
        const values = Object.fromEntries(fields.map(name => [name, form.elements[name].value]));
        try {
            const existing = JSON.parse(localStorage.getItem(key));
            if (existing && existing.token !== token) {
                status.textContent = 'Another tab saved a browser draft. Save or download this edit before reloading.';
                return;
            }
            const nextToken = Array.from(crypto.getRandomValues(new Uint8Array(16)), byte =>
                byte.toString(16).padStart(2, '0')
            ).join('');
            localStorage.setItem(key, JSON.stringify({revision, token: nextToken, values}));
            token = nextToken;
            status.textContent = 'Draft saved in this browser. Use Save to update the article.';
            dirty = false;
        } catch {
            status.textContent = 'Browser draft storage is unavailable. Save or download your work.';
        }
    }

    form.addEventListener('blog:open', () => {
        clearTimeout(timer);
        revision = form.elements.expected_revision.value;
        key = prefix + (form.elements.original_slug.value || ':new');
        token = '';
        dirty = false;
        candidate = null;
        submission.value = '';
        recovery.hidden = true;
        editFields.inert = false;
        status.textContent = '';
        try {
            const stored = JSON.parse(localStorage.getItem(key));
            if (stored && fields.every(name => typeof stored.values?.[name] === 'string')) {
                candidate = stored;
                const current = stored.revision === revision;
                message.textContent = current
                    ? 'Restore or discard the browser draft to continue editing.'
                    : 'This article changed since the browser draft. Download its Markdown before discarding the copy to continue editing.';
                restore.textContent = current ? 'Restore draft' : 'Download browser draft';
                recovery.hidden = false;
                editFields.inert = true;
                restore.focus();
            }
        } catch {
            status.textContent = 'Browser draft storage is unavailable. Save or download your work.';
        }
    });

    restore.addEventListener('click', () => {
        if (!candidate) return;
        if (candidate.revision !== revision) {
            window.beDownloadMarkdown(candidate.values.content);
            return;
        }
        for (const name of fields) form.elements[name].value = candidate.values[name];
        document.getElementById('edit-slug-display').value = candidate.values.slug;
        window.beLoadMarkdown(candidate.values.content);
        token = candidate.token;
        candidate = null;
        recovery.hidden = true;
        editFields.inert = false;
        status.textContent = 'Browser draft restored. Review publication and save when ready.';
        server?.changed();
        document.getElementById('edit-title').focus();
    });

    document.getElementById('be-discard').addEventListener('click', () => {
        try {
            if (JSON.parse(localStorage.getItem(key))?.token === candidate?.token) {
                localStorage.removeItem(key);
            }
        } catch {
            status.textContent = 'Could not remove the browser draft.';
            return;
        }
        candidate = null;
        recovery.hidden = true;
        editFields.inert = false;
        if (dirty) saveDraft();
        document.getElementById('edit-title').focus();
    });

    form.addEventListener('input', event => {
        if (!fields.includes(event.target.name)
            && !['be-document', 'edit-slug-display'].includes(event.target.id)) return;
        dirty = true;
        clearTimeout(timer);
        timer = setTimeout(saveDraft, 300);
    });
    form.addEventListener('submit', () => {
        saveDraft();
        submission.value = token || '';
    });
    const server = window.bapxEditorServerDraft?.({
        form,kind:'blog',localRecovery:recovery,
        capture() {
            window.beSyncMarkdown();
            return Object.fromEntries(fields.map(name=>[name,form.elements[name].value]));
        },
        apply(saved) {
            if (!fields.every(name=>typeof saved[name]==='string')) return false;
            for (const name of fields) {
                const control=form.elements[name];
                if (control.options && !Array.from(control.options).some(option=>option.value===saved[name])) return false;
            }
            if (form.elements.original_slug.value && saved.slug!==form.elements.original_slug.value) return false;
            for (const name of fields) form.elements[name].value=saved[name];
            document.getElementById('edit-slug-display').value=saved.slug;
            window.beLoadMarkdown(saved.content);
            dirty=true; saveDraft(); document.getElementById('edit-title').focus();
            return true;
        },
    });
    form.addEventListener('blog:open',()=>server?.open());
    window.addEventListener('pagehide', saveDraft);
    form.addEventListener('blog:close', event => {
        saveDraft();
        if (dirty) event.preventDefault();
    });
})();
