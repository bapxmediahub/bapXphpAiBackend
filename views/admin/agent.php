<div class="admin-card">
    <div class="agent-head">
        <div>
            <h2 class="agent-head__title">AI Agent</h2>
            <p class="agent-head__sub">
                Ask about users, orders, revenue, top customers or products, or paste content to draft a blog post.
            </p>
        </div>
        <div class="agent-head__meta">
            <span class="agent-chip <?= !empty($modelConfig['configured']) ? 'agent-chip--ok' : 'agent-chip--warn' ?>">
                <?= !empty($modelConfig['configured']) ? 'Connected' : 'No API key' ?>
            </span>
            <span class="agent-chip agent-chip--muted"><?= e($modelConfig['model'] ?? 'gemma-4-31b-it') ?></span>
        </div>
    </div>

    <?php if (empty($modelConfig['configured'])): ?>
        <div class="agent-notice">
            <strong>No API key configured.</strong>
            Set <code>ai_api_key</code> in <a href="/admin/integrations">Admin → Integrations</a> to enable the agent.
        </div>
    <?php endif; ?>
</div>

<div class="admin-card agent-shell">
    <div id="agent-messages" class="agent-messages" role="log" aria-live="polite" aria-label="Conversation">
        <div class="agent-row agent-row--bot">
            <div class="agent-avatar agent-avatar--bot" aria-hidden="true">AI</div>
            <div class="agent-bubble agent-bubble--bot">
                Ask me about users, revenue, top customers or products — or give me content and I'll draft a blog post.
            </div>
        </div>
    </div>

    <div class="agent-suggestions" id="agent-suggestions">
        <?php foreach (['Which products sell best?', 'What is the revenue this month?', '/create-blog benefits of rudraksha', '/add-product brass oil lamp'] as $__s): ?>
            <button type="button" class="agent-suggestion" data-q="<?= e($__s) ?>"><?= e($__s) ?></button>
        <?php endforeach; ?>
    </div>

    <div class="agent-composer-wrap">
        <?php // Typing @ opens this. Without it the owner has to remember exact slugs. ?>
        <div id="agent-mentions" class="agent-mentions" role="listbox" aria-label="Attach a page, article or image"></div>
        <form id="agent-form" class="agent-composer" onsubmit="return askAgent(event)">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button type="button" class="agent-attach" id="agent-upload-btn" aria-label="Upload an image"
                    title="Upload an image — it is added to the media library">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
            </button>
            <textarea id="agent-input" name="message" rows="1" required
                      placeholder="Ask anything, @attach a page or image, or type /create-blog…"
                      aria-label="Message"></textarea>
            <button type="submit" class="agent-send" id="agent-submit" aria-label="Send">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </button>
        </form>
    </div>
    <p class="agent-hint">
        Enter to send · Shift+Enter for a new line · <code>@terms</code> or <code>@filename</code> to attach ·
        <code>/create-blog</code> and <code>/add-product</code> to draft
    </p>

    <?php // Uploads here land in the media library, the same as every other admin upload. ?>
    <form id="agent-upload-form" action="/admin/media/upload" method="post" enctype="multipart/form-data" hidden>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="context" value="blog">
        <input type="hidden" name="redirect" value="/admin/agent">
        <input type="file" id="agent-upload-input" name="media_files[]" accept="image/*" multiple>
    </form>
</div>

<script id="agent-attachables" type="application/json"><?= json_encode($attachables ?? [], JSON_UNESCAPED_SLASHES) ?></script>

<style>
.agent-head { display:flex; justify-content:space-between; align-items:flex-start; gap:var(--space-md); flex-wrap:wrap; }
.agent-head__title { font-size:1.05rem; margin:0 0 var(--space-2xs); }
.agent-head__sub { margin:0; font-size:0.85rem; color:var(--color-text-muted); max-width:60ch; }
.agent-head__meta { display:flex; gap:var(--space-xs); align-items:center; flex-wrap:wrap; }
.agent-chip { font-size:0.72rem; font-weight:600; padding:4px 10px; border-radius:999px; border:1px solid var(--color-border); color:var(--color-text-muted); white-space:nowrap; }
.agent-chip--ok { color:#15803d; border-color:#15803d; background:rgba(21,128,61,0.08); }
.agent-chip--warn { color:#b45309; border-color:#b45309; background:rgba(180,83,9,0.08); }
.agent-notice { margin-top:var(--space-md); padding:var(--space-sm) var(--space-md); border-radius:var(--radius-md); border:1px solid #ffc107; background:#fff8e1; font-size:0.85rem; }

.agent-shell { margin-top:var(--space-lg); display:flex; flex-direction:column; }
.agent-messages { display:flex; flex-direction:column; gap:var(--space-md); min-height:220px; max-height:52vh; overflow-y:auto; padding:var(--space-xs) var(--space-2xs) var(--space-md); scroll-behavior:smooth; }
.agent-row { display:flex; gap:var(--space-sm); align-items:flex-start; }
.agent-row--user { flex-direction:row-reverse; }
.agent-avatar { flex:0 0 auto; width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.65rem; font-weight:700; letter-spacing:0.02em; }
.agent-avatar--bot { background:var(--color-gold-light); color:var(--color-maroon); border:1px solid var(--color-gold); }
.agent-avatar--user { background:var(--color-maroon); color:var(--color-gold); }
.agent-bubble { padding:10px 14px; border-radius:14px; font-size:0.88rem; line-height:1.6; max-width:min(680px, 78%); word-wrap:break-word; overflow-wrap:anywhere; }
.agent-bubble--bot { background:var(--color-bg-alt); border:1px solid var(--color-border-light, var(--color-border)); border-top-left-radius:4px; }
.agent-bubble--user { background:var(--color-maroon); color:#fff; border-top-right-radius:4px; }
.agent-bubble--error { background:#fdecea; border:1px solid #dc3545; color:#a71d2a; border-top-left-radius:4px; }
.agent-bubble p:first-child { margin-top:0; } .agent-bubble p:last-child { margin-bottom:0; }
.agent-bubble code { background:rgba(0,0,0,0.06); padding:1px 5px; border-radius:4px; font-size:0.85em; }
.agent-bubble ul, .agent-bubble ol { margin:var(--space-2xs) 0; padding-left:1.1rem; }
.agent-bubble table { border-collapse:collapse; margin:var(--space-2xs) 0; font-size:0.82rem; }
.agent-bubble th, .agent-bubble td { border:1px solid var(--color-border); padding:4px 8px; text-align:left; }

/* The composer sits inside a wrapper so the @ list can float above it. */
.agent-composer-wrap { position:relative; }
.agent-attach { flex:0 0 auto; background:none; border:1px solid var(--color-border); border-radius:10px; width:36px; height:36px; display:flex; align-items:center; justify-content:center; cursor:pointer; color:var(--color-text-muted); }
.agent-attach:hover { color:var(--color-maroon); border-color:var(--color-maroon); }
.agent-mentions { display:none; position:absolute; bottom:calc(100% + 6px); left:0; right:0; max-height:240px; overflow-y:auto; background:var(--color-bg); border:1px solid var(--color-border); border-radius:var(--radius-md); box-shadow:0 8px 24px rgba(0,0,0,0.12); z-index:20; }
.agent-mentions.is-open { display:block; }
.agent-mention { display:flex; justify-content:space-between; gap:var(--space-sm); width:100%; padding:8px 12px; background:none; border:0; text-align:left; cursor:pointer; font-size:0.84rem; }
.agent-mention:hover { background:var(--color-bg-alt); }
.agent-mention__name { font-weight:600; }
.agent-mention__kind { color:var(--color-text-muted); font-size:0.75rem; }

/* A draft is a form, not a sentence — it needs the width a chat bubble caps. */
.agent-bubble:has(.agent-draft) { max-width:100%; width:100%; }
/* A draft the owner reviews. Nothing here has been saved. */
.agent-draft__note { margin:0 0 var(--space-sm); font-size:0.85rem; }
.agent-draft__form { display:flex; flex-direction:column; gap:var(--space-sm); }
.agent-draft__row { display:flex; flex-direction:column; gap:4px; }
.agent-draft__label { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.03em; color:var(--color-text-muted); }
.agent-draft__row input, .agent-draft__row textarea { width:100%; padding:8px 10px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-family:inherit; font-size:0.85rem; }
.agent-draft__row textarea { resize:vertical; line-height:1.6; }
.agent-draft__hint { font-size:0.75rem; color:var(--color-text-muted); }
.agent-draft__actions { margin-top:var(--space-xs); }

.agent-typing { display:inline-flex; gap:4px; align-items:center; }
.agent-typing i { width:6px; height:6px; border-radius:50%; background:var(--color-text-muted); display:inline-block; animation:agentBlink 1.2s infinite ease-in-out; }
.agent-typing i:nth-child(2) { animation-delay:0.18s; } .agent-typing i:nth-child(3) { animation-delay:0.36s; }
@keyframes agentBlink { 0%,80%,100% { opacity:0.25; transform:translateY(0); } 40% { opacity:1; transform:translateY(-2px); } }

.agent-suggestions { display:flex; gap:var(--space-xs); flex-wrap:wrap; padding:0 var(--space-2xs) var(--space-sm); }
.agent-suggestion { font-size:0.78rem; padding:6px 12px; border-radius:999px; border:1px solid var(--color-border); background:var(--color-white); color:var(--color-text-muted); cursor:pointer; transition:all var(--transition-base); font-family:inherit; }
.agent-suggestion:hover { border-color:var(--color-gold); color:var(--color-ink); background:var(--color-gold-light); }

.agent-composer { display:flex; align-items:flex-end; gap:var(--space-xs); border:1px solid var(--color-border); border-radius:22px; padding:6px 6px 6px 16px; background:var(--color-white); transition:border-color var(--transition-base), box-shadow var(--transition-base); }
.agent-composer:focus-within { border-color:var(--color-gold); box-shadow:0 0 0 3px rgba(209,179,104,0.18); }
.agent-composer textarea { flex:1; border:0; outline:0; resize:none; font-family:inherit; font-size:0.9rem; line-height:1.5; padding:8px 0; max-height:160px; background:transparent; color:var(--color-ink); }
.agent-send { flex:0 0 auto; width:36px; height:36px; border-radius:50%; border:1px solid var(--color-gold); background:var(--color-maroon); color:var(--color-gold); display:inline-flex; align-items:center; justify-content:center; cursor:pointer; transition:opacity var(--transition-base); }
.agent-send:disabled { opacity:0.45; cursor:not-allowed; }
.agent-hint { margin:var(--space-2xs) 0 0; font-size:0.72rem; color:var(--color-text-muted); text-align:right; }

@media (max-width: 768px) {
    .agent-bubble { max-width:88%; }
    .agent-messages { max-height:46vh; }
    .agent-hint { display:none; }
}
</style>

<script>
(function () {
    const input = document.getElementById('agent-input');
    const form = document.getElementById('agent-form');
    const messages = document.getElementById('agent-messages');
    const submit = document.getElementById('agent-submit');
    const suggestions = document.getElementById('agent-suggestions');

    function escapeHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Minimal Markdown: escape first, then re-introduce a known-safe subset, so model
    // output can never inject HTML.
    function renderMarkdown(src) {
        let t = escapeHtml(src);
        t = t.replace(/```([\s\S]*?)```/g, (_, c) => '<pre><code>' + c.trim() + '</code></pre>');
        t = t.replace(/`([^`]+)`/g, '<code>$1</code>');
        t = t.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        t = t.replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>');
        const lines = t.split('\n');
        let out = '', list = null;
        for (const raw of lines) {
            const line = raw.trimEnd();
            const ul = line.match(/^\s*[-*]\s+(.*)$/);
            const ol = line.match(/^\s*\d+\.\s+(.*)$/);
            if (ul) { if (list !== 'ul') { if (list) out += '</' + list + '>'; out += '<ul>'; list = 'ul'; } out += '<li>' + ul[1] + '</li>'; continue; }
            if (ol) { if (list !== 'ol') { if (list) out += '</' + list + '>'; out += '<ol>'; list = 'ol'; } out += '<li>' + ol[1] + '</li>'; continue; }
            if (list) { out += '</' + list + '>'; list = null; }
            if (line.trim() === '') { out += ''; continue; }
            out += '<p>' + line + '</p>';
        }
        if (list) out += '</' + list + '>';
        return out;
    }

    function addRow(who, html) {
        const row = document.createElement('div');
        row.className = 'agent-row agent-row--' + who;
        const avatar = document.createElement('div');
        avatar.className = 'agent-avatar agent-avatar--' + (who === 'user' ? 'user' : 'bot');
        avatar.setAttribute('aria-hidden', 'true');
        avatar.textContent = who === 'user' ? 'You' : 'AI';
        const bubble = document.createElement('div');
        bubble.className = 'agent-bubble agent-bubble--' + who;
        bubble.innerHTML = html;
        row.appendChild(avatar); row.appendChild(bubble);
        messages.appendChild(row);
        messages.scrollTop = messages.scrollHeight;
        return bubble;
    }

    function autoGrow() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 160) + 'px';
    }
    input.addEventListener('input', autoGrow);

    // Enter sends, Shift+Enter makes a new line — the convention users expect.
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
    });

    suggestions.addEventListener('click', function (e) {
        const btn = e.target.closest('.agent-suggestion');
        if (!btn) return;
        input.value = btn.dataset.q;
        autoGrow();
        form.requestSubmit();
    });

    // ── Draft forms ────────────────────────────────────────────────────────
    // The agent hands back a filled-in form, never a saved record. It posts to the
    // same route the ordinary admin screens use, so nothing reaches the site until
    // the owner has read the draft and pressed Save.
    function buildDraftForm(draft) {
        const wrap = document.createElement('div');
        wrap.className = 'agent-draft';

        const note = document.createElement('p');
        note.className = 'agent-draft__note';
        note.textContent = draft.note || '';
        wrap.appendChild(note);

        const form = document.createElement('form');
        form.method = 'post';
        form.action = draft.action;
        form.className = 'agent-draft__form';

        const csrf = document.createElement('input');
        csrf.type = 'hidden'; csrf.name = '_csrf';
        csrf.value = document.querySelector('input[name="_csrf"]')?.value || '';
        form.appendChild(csrf);

        (draft.fields || []).forEach(function (field) {
            const row = document.createElement('label');
            row.className = 'agent-draft__row';

            const label = document.createElement('span');
            label.className = 'agent-draft__label';
            label.textContent = field.label + (field.required ? ' *' : '');
            row.appendChild(label);

            const big = field.type === 'textarea' || field.type === 'markdown';
            const input = document.createElement(big ? 'textarea' : 'input');
            if (big) { input.rows = field.type === 'markdown' ? 12 : 3; } else { input.type = 'text'; }
            input.name = field.name;
            input.value = field.value || '';
            if (field.required) input.required = true;
            row.appendChild(input);

            if (field.hint) {
                const hint = document.createElement('span');
                hint.className = 'agent-draft__hint';
                hint.textContent = field.hint;
                row.appendChild(hint);
            }
            form.appendChild(row);
        });

        const actions = document.createElement('div');
        actions.className = 'agent-draft__actions';
        const save = document.createElement('button');
        save.type = 'submit';
        save.className = 'btn btn-primary btn-sm';
        save.textContent = draft.kind === 'blog' ? 'Save blog post' : 'Save product';
        actions.appendChild(save);
        form.appendChild(actions);

        wrap.appendChild(form);
        return wrap;
    }

    // ── @ attachments ──────────────────────────────────────────────────────
    const attachables = JSON.parse(document.getElementById('agent-attachables')?.textContent || '[]');
    const mentionBox = document.getElementById('agent-mentions');

    function closeMentions() { if (mentionBox) mentionBox.classList.remove('is-open'); }

    function openMentions(query) {
        if (!mentionBox) return;
        const q = query.toLowerCase();
        const hits = attachables.filter(a => a.name.toLowerCase().includes(q)).slice(0, 8);
        if (!hits.length) { closeMentions(); return; }
        mentionBox.innerHTML = '';
        hits.forEach(function (hit) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'agent-mention';
            item.innerHTML = '<span class="agent-mention__name">@' + escapeHtml(hit.name) + '</span>'
                + '<span class="agent-mention__kind">' + escapeHtml(hit.kind) + '</span>';
            item.addEventListener('click', function () {
                input.value = input.value.replace(/@([A-Za-z0-9._-]*)$/, '@' + hit.name + ' ');
                closeMentions();
                input.focus();
                autoGrow();
            });
            mentionBox.appendChild(item);
        });
        mentionBox.classList.add('is-open');
    }

    input.addEventListener('input', function () {
        const match = input.value.slice(0, input.selectionStart).match(/@([A-Za-z0-9._-]*)$/);
        if (match) { openMentions(match[1]); } else { closeMentions(); }
    });
    input.addEventListener('blur', function () { setTimeout(closeMentions, 150); });

    // ── Upload ─────────────────────────────────────────────────────────────
    const uploadBtn = document.getElementById('agent-upload-btn');
    const uploadInput = document.getElementById('agent-upload-input');
    if (uploadBtn && uploadInput) {
        uploadBtn.addEventListener('click', function () { uploadInput.click(); });
        uploadInput.addEventListener('change', function () {
            if (uploadInput.files && uploadInput.files.length) {
                document.getElementById('agent-upload-form').submit();
            }
        });
    }

    window.askAgent = async function (e) {
        e.preventDefault();
        const msg = input.value.trim();
        if (!msg) return false;

        addRow('user', escapeHtml(msg).replace(/\n/g, '<br>'));
        input.value = ''; autoGrow();
        input.disabled = true; submit.disabled = true;
        suggestions.style.display = 'none';

        const thinking = addRow('bot', '<span class="agent-typing"><i></i><i></i><i></i></span>');

        try {
            const body = 'message=' + encodeURIComponent(msg)
                + '&_csrf=' + encodeURIComponent(document.querySelector('input[name="_csrf"]')?.value || '');
            const resp = await fetch('/admin/agent/ask', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body,
                credentials: 'same-origin'
            });
            const data = await resp.json().catch(() => ({ error: 'The server returned an unreadable response.' }));
            if (data.error) {
                thinking.className = 'agent-bubble agent-bubble--error';
                thinking.textContent = data.error;
            } else if (data.draft) {
                thinking.innerHTML = '';
                thinking.appendChild(buildDraftForm(data.draft));
            } else {
                thinking.innerHTML = renderMarkdown(data.answer || 'No response.');
            }
            if (data.missing && data.missing.length) {
                addRow('bot', 'I could not find ' + data.missing.map(m => '<code>@' + escapeHtml(m) + '</code>').join(', ')
                    + '. Type <code>@</code> to see what is available.');
            }
        } catch (err) {
            thinking.className = 'agent-bubble agent-bubble--error';
            thinking.textContent = 'Could not reach the server. Check your connection and try again.';
        }

        input.disabled = false; submit.disabled = false;
        input.focus();
        messages.scrollTop = messages.scrollHeight;
        return false;
    };
})();
</script>
