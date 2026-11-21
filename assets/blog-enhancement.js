(() => {
    function localFormatting(field, value) {
        if (field === 'title') {
            const compact = value.trim().replace(/\s+/gu, ' ');
            const headline = compact.replace(/^[a-z](?=[a-z]*(?:\s|$))/u, letter => letter.toUpperCase());
            return {text: headline, label: 'Local formatting · headline spacing and initial capital'};
        }

        // Split only plain, single-paragraph prose. Preserve Markdown blocks,
        // links, images, code indentation and deliberate line breaks verbatim.
        if (!value || /[\r\n\[\]()`*_<>|#~\\]/u.test(value) || /^(?:\s|[-+]\s|\d+[.)]\s)/u.test(value)
            || /(?:https?:\/\/|www\.)/iu.test(value) || typeof Intl.Segmenter !== 'function') return null;
        const sentences = [...new Intl.Segmenter(undefined, {granularity:'sentence'}).segment(value)];
        if (sentences.length < 2) return null;
        return {text: sentences.map(item => item.segment.trim()).join('\n\n'),
            label:'Local formatting · one paragraph per sentence'};
    }

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
            if (proposal.generation === generation() && proposal.original === read()) {
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
            if (!original.trim()) { status.textContent = 'Enter some ' + field + ' first.'; return; }
            button.disabled = true;
            status.textContent = 'Requesting an AI suggestion…';
            let candidate;
            let message;
            try {
                const response = await fetch('/admin/blog/enhance-' + field, {
                    method:'POST', credentials:'same-origin', headers:{Accept:'application/json'},
                    body: new URLSearchParams({[field]:original, _csrf:form.elements._csrf.value})
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
            if (original !== read()) { status.textContent = 'The draft changed while the request was running. Request a new suggestion.'; return; }
            if (!candidate) {
                candidate = localFormatting(field, original);
                if (candidate?.text && candidate.text !== original) message += ' You can review a local formatting option below.';
            }
            status.textContent = message;
            if (!candidate?.text || candidate.text === original) return;
            proposal = {original, generation:opened, text:candidate.text};
            source.textContent = candidate.label;
            preview.textContent = candidate.text;
            apply.textContent = 'Apply to ' + field;
            review.hidden = false;
        });
    };
})();
