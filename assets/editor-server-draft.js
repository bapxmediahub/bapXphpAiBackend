(() => {
    'use strict';
    // The existing browser draft remains a local fallback. This helper uses
    // the same field capture/restore contracts; it never publishes content.
    window.bapxEditorServerDraft = ({form,kind,capture,apply,localRecovery}) => {
        const panel = form.querySelector('[data-server-draft]');
        if (!panel || !form.dataset.draftOwner) return null;
        const status = panel.querySelector('[data-server-status]');
        const recovery = panel.querySelector('[data-server-recovery]');
        const versions = panel.querySelector('[data-server-versions]');
        const restore = panel.querySelector('[data-server-restore]');
        const download = panel.querySelector('[data-server-download]');
        const downloadCurrent = panel.querySelector('[data-server-download-current]');
        const discard = panel.querySelector('[data-server-discard]');
        const tokenField = form.elements.server_draft_token;
        let epoch = 0, timer, state = null;
        const same = (a,b) => !!a && !!b && Object.keys(a).length === Object.keys(b).length
            && Object.keys(a).every(key => Object.hasOwn(b,key) && a[key] === b[key]);
        function snapshot() {
            // Capture preserves the existing editor's exact allowlist.
            return capture();
        }
        async function request(s,operation,values) {
            const body = new FormData();
            for (const [key,value] of Object.entries({
                _csrf:form.elements._csrf.value, kind,target:s.target,revision:s.revision,
                operation,expected:s.token || '',
            })) body.set(key,value);
            if (values !== undefined) body.set('values',JSON.stringify(values));
            const response = await fetch('/admin/editor-draft',{
                method:'POST',body,credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}
            });
            if (response.redirected) throw new Error('Session expired');
            const result = await response.json();
            if (![200,409].includes(response.status)) throw new Error('Recovery unavailable');
            if (!result || typeof result.outcome !== 'string') throw new Error('Invalid recovery response');
            return result;
        }
        const current = s => state === s && s.epoch === epoch;
        function selected(s) { return s.candidate?.versions?.[Number(versions.value)]; }
        function display(s,draft,message) {
            if (!current(s)) return;
            s.candidate = draft || null;
            s.token = draft?.token || '';
            recovery.hidden = !draft;
            versions.replaceChildren();
            if (draft) {
                draft.versions.forEach((v,i) => {
                    const option = new Option(
                        (i === 0 ? 'Latest · ' : 'Earlier · ') + new Date(v.at*1000).toLocaleString()
                        + (v.revision === s.revision ? '' : ' · older saved revision'),String(i));
                    versions.add(option);
                });
            }
            status.textContent = message;
            updateControls(s);
        }
        function updateControls(s) {
            const item = selected(s);
            restore.disabled = !item || item.revision !== s.revision
                || s.currentRevision !== s.revision || !localRecovery.hidden || s.busy;
            download.disabled = !item || s.busy;
            discard.disabled = !s.candidate || s.busy;
        }
        function schedule(s) {
            if (!current(s) || s.candidate || s.stale) return;
            clearTimeout(timer);
            timer = setTimeout(() => save(s),1500);
        }
        async function load(s) {
            if (s.busy || !current(s)) return;
            s.busy = true;
            status.textContent = 'Checking server recovery…';
            try {
                const result = await request(s,'read');
                if (!current(s)) return;
                if (result.outcome !== 'read') throw new Error('Invalid recovery state');
                s.currentRevision = result.currentRevision;
                s.loaded = true;
                s.stale = s.currentRevision !== s.revision;
                const draft = result.draft;
                if (draft && !s.stale && draft.versions[0].revision === s.revision
                    && same(draft.versions[0].values,snapshot()) && localRecovery.hidden) {
                    s.token = draft.token;
                    s.ack = snapshot();
                    display(s,null,'Server recovery matches this editor.');
                    s.token = draft.token;
                } else {
                    display(s,draft,draft ? 'A server recovery copy is available. Resolve any browser copy first, then restore, download or discard this copy.'
                        : s.stale ? 'The saved content changed. Reload before saving; the browser copy remains available.'
                        : 'Server recovery is ready.');
                }
            } catch {
                if (current(s)) status.textContent = 'Server recovery unavailable. Browser draft recovery remains available.';
            } finally {
                s.busy = false;
                if (current(s)) {
                    updateControls(s);
                    if (s.loaded && s.dirty && !s.candidate && !s.stale) schedule(s);
                }
            }
        }
        async function save(s) {
            if (!current(s) || form.hidden || s.busy || s.candidate || s.stale || !localRecovery.hidden || !s.dirty) return;
            if (!s.loaded) { await load(s); return; }
            const values = snapshot();
            if (same(values,s.ack)) { s.dirty = false; return; }
            s.busy = true;
            s.dirty = false;
            status.textContent = 'Saving server recovery…';
            try {
                const result = await request(s,'write',values);
                if (!current(s)) return;
                if (result.outcome === 'saved' && /^[a-f0-9]{32}$/.test(result.token)) {
                    s.token = result.token;
                    s.ack = values;
                    status.textContent = 'Server recovery saved. Publication still requires Save.';
                } else if (result.outcome === 'conflict') {
                    s.currentRevision = result.currentRevision;
                    display(s,result.draft,'Another editor changed the server copy. Your current browser text has not been overwritten.');
                    // A concurrent discard must not be followed by an automatic recreate.
                    if (!result.draft) {s.loaded=false;s.dirty=false;}
                } else if (result.outcome === 'stale') {
                    s.stale = true;
                    status.textContent = 'Saved content changed. Your browser text is retained; reopen the current revision before publishing.';
                } else throw new Error('Invalid recovery response');
            } catch {
                if (current(s)) status.textContent = 'Server recovery could not be confirmed. Keep your browser draft.';
            } finally {
                s.busy = false;
                if (current(s)) {
                    updateControls(s);
                    if (s.dirty && !s.candidate && !s.stale) schedule(s);
                }
            }
        }
        function changed() {
            const s = state;
            if (!s) return;
            s.dirty = true;
            updateControls(s);
            schedule(s);
        }
        function open() {
            clearTimeout(timer);
            epoch++;
            tokenField.value = '';
            recovery.hidden = true;
            state = {epoch,target:form.elements.original_slug.value,revision:form.elements.expected_revision.value,
                token:'',loaded:false,busy:false,dirty:false,stale:false,candidate:null,ack:snapshot()};
            void load(state);
        }
        versions.addEventListener('change',() => { if (state) updateControls(state); });
        restore.addEventListener('click',() => {
            const s = state, item = s && selected(s);
            if (!s || !item || restore.disabled || s.busy) return;
            if (!same(snapshot(),s.ack) && !confirm('Restoring replaces the current editor fields. Download your current edit first if you need to keep it. Restore anyway?')) return;
            if (!apply(item.values)) {
                status.textContent = 'This recovery copy contains unavailable editor selections. Download it and review the saved fields.';
                return;
            }
            s.ack = s.candidate.versions[0].values;
            s.candidate = null;
            recovery.hidden = true;
            status.textContent = 'Server draft restored. Review all settings and explicitly save when ready.';
            changed();
        });
        function downloadSnapshot(item,filename) {
            const url = URL.createObjectURL(new Blob([JSON.stringify(item,null,2)],{type:'application/json;charset=utf-8'}));
            const link = document.createElement('a');
            link.href=url; link.download=filename; link.click();
            setTimeout(()=>URL.revokeObjectURL(url),1000);
        }
        download.addEventListener('click',() => {
            const item = state && selected(state);
            if (!item || download.disabled) return;
            downloadSnapshot(item,kind+'-editor-recovery.json');
        });
        downloadCurrent.addEventListener('click',() => {
            if (!state || !current(state)) return;
            downloadSnapshot({revision:state.revision,values:snapshot()},kind+'-current-editor.json');
        });
        discard.addEventListener('click',async () => {
            const s = state;
            if (!s || !s.candidate || s.busy || !confirm('Discard the saved server recovery versions? This does not delete published content or the browser draft.')) return;
            s.busy = true; updateControls(s);
            try {
                const result = await request(s,'discard');
                if (!current(s)) return;
                if (result.outcome === 'discarded') {
                    display(s,null,'Server recovery discarded. Your saved content and browser copy are unchanged.');
                    s.ack=snapshot();s.dirty=false;
                } else if (result.outcome === 'conflict') {
                    display(s,result.draft,'The server copy changed. Review the latest recovery before discarding.');
                } else throw new Error('Invalid discard result');
            } catch {
                if (current(s)) status.textContent = 'Server discard could not be confirmed.';
            } finally {
                s.busy=false;
                if (current(s)) updateControls(s);
            }
        });
        form.addEventListener('input',event => {if (!panel.contains(event.target)) changed();});
        form.addEventListener('change',event => {if (!panel.contains(event.target)) changed();});
        // Browser recovery is separate. Never restore server content over a
        // browser candidate that the editor has not explicitly resolved.
        new MutationObserver(() => {if (state) {updateControls(state); if (state.dirty) schedule(state);}})
            .observe(localRecovery,{attributes:true,attributeFilter:['hidden']});
        form.addEventListener('submit',() => {
            const s = state;
            tokenField.value = s && !s.busy && !s.candidate && s.token && same(snapshot(),s.ack) ? s.token : '';
            clearTimeout(timer);
        });
        return {open,changed};
    };
})();
