(() => {
    const form = document.getElementById('page-draft-form');
    if (!form?.dataset.draftOwner) return;
    const prefix = 'bapx:page-draft:' + form.dataset.draftOwner + ':';
    const key = prefix + (form.elements.original_slug.value || ':new');
    const revision = form.elements.expected_revision.value;
    const requiredFields = ['title', 'slug', 'content'];
    const fields = [...requiredFields, 'header_image_url', 'header_image_alt'];
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
        && requiredFields.every(name => typeof item.values?.[name] === 'string')
        && fields.every(name => item.values[name] === undefined || typeof item.values[name] === 'string');
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
                || current.title.length > 320 || current.slug.length > 32
                || current.header_image_url.length > 512 || current.header_image_alt.length > 510) {
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
                && fields.every(name => stored.values[name] === undefined || stored.values[name] === form.elements[name].value)) {
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
        const header = form.elements.header_image_url;
        const storedHeader = candidate.values.header_image_url;
        const missingHeader = storedHeader !== undefined
            && !Array.from(header.options).some(option => option.value === storedHeader);
        for (const name of fields) {
            if (name.startsWith('header_image_') && missingHeader) continue;
            if (!form.elements[name].readOnly && candidate.values[name] !== undefined) {
                form.elements[name].value = candidate.values[name];
            }
        }
        form.dispatchEvent(new Event('page-draft-restored'));
        token = candidate.token;
        closeRecovery();
        status.textContent = missingHeader
            ? 'Text restored. The draft header image is unavailable. Review your selection before saving.'
            : 'Browser draft restored. Review visibility and use Save page when ready.';
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

(() => {
    const form = document.getElementById('page-draft-form');
    const header = form?.elements.header_image_url;
    if (!header) return;

    const image = document.getElementById('page-header-thumbnail');
    const file = document.getElementById('page-header-file');
    const upload = document.getElementById('page-header-upload');
    const status = document.getElementById('page-header-upload-status');
    const pathPattern = /^\/assets\/images\/media\/[0-9]{4}\/[0-9]{2}\/[a-z0-9][a-z0-9._-]*\.(png|jpe?g|webp|gif)$/i;
    const validPath = path => typeof path === 'string' && path.length <= 512
        && !path.includes('..') && pathPattern.test(path);
    let selection = 0;

    function refreshImage() {
        selection++;
        const path = header.value;
        image.hidden = !validPath(path);
        if (image.hidden) image.removeAttribute('src');
        else image.src = path;
    }

    header.addEventListener('input', refreshImage);
    form.addEventListener('page-draft-restored', refreshImage);
    document.getElementById('page-header-upload-controls').hidden = false;
    upload.addEventListener('click', async () => {
        if (!file.files.length) {
            status.textContent = 'Choose an image file.';
            file.focus();
            return;
        }
        const startedAt = selection;
        const data = new FormData();
        data.append('media_files[]', file.files[0]);
        data.append('context', 'shared');
        data.append('_csrf', form.elements._csrf.value);
        upload.disabled = file.disabled = true;
        status.textContent = 'Uploading image…';
        try {
            const response = await fetch('/admin/media/upload', {
                method: 'POST', body: data, credentials: 'same-origin', headers: {Accept: 'application/json'},
            });
            const result = await response.json();
            const path = result.files?.[0]?.url;
            if (!response.ok || !validPath(path)) {
                status.textContent = typeof result.error === 'string' ? result.error : 'Upload could not be confirmed. Check the Media Library.';
                return;
            }
            if (!Array.from(header.options).some(option => option.value === path)) {
                header.add(new Option(path.split('/').pop(), path));
            }
            if (selection !== startedAt) {
                status.textContent = 'Image uploaded. Your current header selection was kept.';
                return;
            }
            header.value = path;
            header.dispatchEvent(new Event('input', {bubbles: true}));
            status.textContent = 'Image uploaded and selected. Save page to apply it.';
        } catch {
            status.textContent = 'Upload could not be confirmed. Check the Media Library.';
        } finally {
            file.value = '';
            upload.disabled = file.disabled = false;
        }
    });
})();
