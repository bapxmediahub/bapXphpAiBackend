(() => {
    window.bindBlogEnhancement = (form, field, read, write, generation) => {
        const button = document.getElementById('enhance-' + field);
        const panel = document.getElementById('be-' + field + '-rewrite');
        panel.append(document.getElementById('be-rewrite-review').content.cloneNode(true));
        const status = panel.querySelector('[role=status]');
        const review = panel.querySelector('.be-rewrite__review');
        const source = panel.querySelector('.be-rewrite__source');
        const preview = panel.querySelector('pre');
        const apply = panel.querySelector('.be-rewrite__apply');
        let proposal;
        function clear() {
            proposal = null;
            preview.textContent = '';
            review.hidden = true;
        }
        form.addEventListener('blog:open', () => { clear(); status.textContent = ''; });
        panel.querySelector('.be-rewrite__dismiss').addEventListener('click', () => {
            clear();
            status.textContent = 'Suggestion dismissed.';
            button.focus();
        });
        apply.addEventListener('click', () => {
            if (!proposal) return;
            if (proposal.generation === generation() && proposal.original === read() && (!proposal.generate || proposal.title===form.elements.title.value)) {
                write(proposal.text);
                status.textContent = 'Applied to the ' + field + '. Save the article when ready.';
            } else {
                status.textContent = 'The draft changed. Request a new suggestion for the current ' + field + '.';
            }
            clear();
            button.focus();
        });

        button.addEventListener('click', async () => {
            const original = read();
            const opened = generation();
            clear();
            const generate = field === 'content' && !original.trim();
            const title = form.elements.title.value;
            if (generate && !title.trim()) { status.textContent='Enter an article title first.'; return; }
            if (!generate && !original.trim()) { status.textContent = 'Enter some ' + field + ' first.'; return; }
            button.disabled = true;
            status.textContent = generate ? 'Requesting an AI draft…' : 'Requesting an AI suggestion…';
            let candidate;
            let message;
            try {
                const response = await fetch(generate ? '/admin/blog/ai-draft' : '/admin/blog/enhance-' + field, {
                    method:'POST', credentials:'same-origin', headers:{Accept:'application/json'},
                    body: new URLSearchParams({[field]:original, title:generate ? title : form.elements.title.value, _csrf:form.elements._csrf.value})
                });
                const data = await response.json();
                if (response.ok && data.source === 'provider' && typeof data[field] === 'string' && data[field].trim()) {
                    candidate = {text:data[field], label:'AI suggestion'};
                    message = data[field] === original ? 'The provider suggested the existing text.' : 'Review the AI suggestion before applying it.';
                } else {
                    message = typeof data.error === 'string' ? data.error : 'The service returned no usable suggestion.';
                }
            } catch {
                message = 'The editor could not retrieve an AI suggestion.';
            } finally { button.disabled = false; }
            if (opened !== generation() || form.hidden) return;
            if (original !== read() || (generate && title !== form.elements.title.value)) { status.textContent = 'The draft changed while the request was running. Request a new suggestion.'; return; }
            status.textContent = message;
            if (!candidate?.text || candidate.text === original) return;
            proposal = {original, generation:opened, text:candidate.text, generate,title};
            source.textContent = candidate.label;
            preview.textContent = candidate.text;
            apply.textContent = 'Apply to ' + field;
            review.hidden = false;
        });
    };
})();
