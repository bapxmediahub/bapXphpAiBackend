<section class="enterprise-chat" aria-labelledby="enterprise-agent-title">
    <header class="enterprise-chat__intro">
        <h1 id="enterprise-agent-title">Enterprise Agent</h1>
        <p>Ask about business performance, orders, stock status, and customer support.</p>
        <?php if (empty($modelConfig['configured'])): ?>
            <p class="agent-notice">Connect a model in <a href="/admin/integrations">Integrations</a> for questions that need one.</p>
        <?php endif; ?>
    </header>
    <?php if (!empty($agentCapabilities)): ?>
        <details class="agent-capabilities" id="agent-capabilities">
            <summary>What can I ask?</summary>
            <p>Choose a question to edit, or ask for a specific period. Reports show recorded data and link to the admin source.</p>
            <ul class="agent-capabilities__tasks">
                <?php foreach ($agentCapabilities as $capability): ?>
                    <li>
                        <strong><?= e($capability['title']) ?></strong>
                        <span><?= e($capability['description']) ?></span>
                        <?php if (is_string($capability['prompt'])): ?>
                            <button type="button" class="agent-suggestion" aria-label="<?= e('Use ' . $capability['title'] . ' question') ?>" data-capability-prompt="<?= e($capability['prompt']) ?>" hidden>Use this question</button>
                            <noscript><span>Example: <?= e($capability['prompt']) ?></span></noscript>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="agent-capabilities__limits"><?= e($agentCapabilityLimits ?? '') ?></p>
            <a href="/admin/docs/enterprise-agent">Read the Enterprise Agent guide</a>
        </details>
    <?php endif; ?>
    <div class="enterprise-chat__thread">
        <div class="agent-history-actions">
            <button type="button" class="agent-suggestion" id="agent-history-toggle"
                aria-controls="agent-history-panel" aria-expanded="false">History</button>
            <button type="button" class="agent-suggestion enterprise-chat__clear" id="agent-clear">New chat</button>
        </div>
        <section id="agent-history-panel" class="agent-history-panel" aria-label="Your previous chats" hidden>
            <h2>Previous chats</h2>
            <p id="agent-history-status" role="status"></p>
            <div id="agent-history-list" class="agent-history-list"></div>
        </section>
    <div id="agent-messages" class="agent-messages" role="log" aria-live="polite" aria-label="Conversation"></div>

    <div class="agent-suggestions" id="agent-suggestions">
        <?php foreach (array_slice(array_values(array_filter(array_column($agentCapabilities ?? [], 'prompt'), 'is_string')), 0, 4) as $__s): ?>
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
            <textarea id="agent-input" name="message" rows="1" maxlength="2000" required
                      placeholder="Ask about your store…"
                      aria-label="Message" aria-controls="agent-mentions" aria-autocomplete="list" aria-haspopup="listbox"></textarea>
            <button type="submit" class="agent-send" id="agent-submit" aria-label="Send">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </button>
        </form>
    </div>
    <p id="agent-attachment-status" role="status"></p>
    <p class="agent-hint">
        Enter to send · Shift+Enter for a new line · <code>@terms</code> or <code>@filename</code> to attach ·
        <code>/create-blog</code> and <code>/add-product</code> to draft
    </p>
    </div>
</section>

    <?php // Uploads here land in the media library, the same as every other admin upload. ?>
    <form id="agent-upload-form" action="/admin/media/upload" method="post" enctype="multipart/form-data" hidden>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="context" value="blog">
        <input type="hidden" name="redirect" value="/admin/agent">
        <input type="file" id="agent-upload-input" name="media_files[]" accept="image/*" multiple>
    </form>

<script id="agent-attachables" type="application/json"><?= json_encode($attachables ?? [], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>

<style>
.agent-head { display:flex; justify-content:space-between; align-items:flex-start; gap:var(--space-md); flex-wrap:wrap; }
.agent-head__title { font-size:1.05rem; margin:0 0 var(--space-2xs); }
.agent-head__sub { margin:0; font-size:0.85rem; color:var(--color-text-muted); max-width:60ch; }
.agent-head__meta { display:flex; gap:var(--space-xs); align-items:center; flex-wrap:wrap; }
.agent-chip { font-size:0.72rem; font-weight:600; padding:4px 10px; border-radius:999px; border:1px solid var(--color-border); color:var(--color-text-muted); white-space:nowrap; }
.agent-chip--ok { color:#15803d; border-color:#15803d; background:rgba(21,128,61,0.08); }
.agent-chip--warn { color:#b45309; border-color:#b45309; background:rgba(180,83,9,0.08); }
.agent-notice { margin-top:var(--space-md); padding:var(--space-sm) var(--space-md); border-radius:var(--radius-md); border:1px solid #ffc107; background:#fff8e1; font-size:0.85rem; }
.agent-capabilities { margin:var(--space-md) 0; border-block:1px solid var(--color-border); padding:var(--space-sm) 0; }
.agent-capabilities summary { cursor:pointer; font-weight:600; padding:var(--space-xs); }
.agent-capabilities summary:focus-visible, .agent-capabilities button:focus-visible { outline:2px solid var(--color-maroon); outline-offset:3px; }
.agent-capabilities p, .agent-capabilities a { font-size:0.85rem; line-height:1.5; }
.agent-capabilities__tasks { list-style:none; padding:0; display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr)); gap:var(--space-md); }
.agent-capabilities__tasks li { display:flex; flex-direction:column; align-items:flex-start; gap:var(--space-xs); min-width:0; overflow-wrap:anywhere; }
.agent-capabilities__tasks span { font-size:0.82rem; color:var(--color-text-muted); line-height:1.5; }
.agent-capabilities__limits { color:var(--color-text-muted); }
.agent-capabilities [hidden] { display:none !important; }

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

/* User-specific conversation history. [hidden] must defeat any flex author rule. */
.agent-history-actions { display:flex; gap:var(--space-xs); justify-content:flex-end; flex-wrap:wrap; margin:0 0 var(--space-sm); }
.agent-history-panel { border:1px solid var(--color-border); border-radius:var(--radius-md); padding:var(--space-md); margin-bottom:var(--space-md); background:var(--color-bg-alt); max-height:260px; overflow-y:auto; }
.agent-history-panel[hidden] { display:none !important; }
.agent-history-panel h2 { font-size:0.94rem; margin:0 0 var(--space-xs); }
#agent-history-status { margin:0 0 var(--space-xs); }
#agent-history-status:empty { margin:0; }
.agent-history-list { display:flex; flex-direction:column; gap:var(--space-2xs); }
.agent-history-entry { text-align:left; background:var(--color-white); border:1px solid var(--color-border); border-radius:var(--radius-sm); padding:8px 10px; cursor:pointer; overflow-wrap:anywhere; }
.agent-history-entry:hover, .agent-history-entry:focus-visible { border-color:var(--color-gold); }
.agent-history-entry small { color:var(--color-text-muted); display:block; margin-top:3px; }

/* The composer sits inside a wrapper so the @ list can float above it. */
.agent-composer-wrap { position:relative; }
.agent-attach { flex:0 0 auto; background:none; border:1px solid var(--color-border); border-radius:8px; width:44px; height:44px; display:flex; align-items:center; justify-content:center; cursor:pointer; color:var(--color-text-muted); }
.agent-attach:hover { color:var(--color-maroon); border-color:var(--color-maroon); }
.agent-mentions { display:none; position:absolute; bottom:calc(100% + 6px); left:0; right:0; max-height:240px; overflow-y:auto; background:var(--color-bg); border:1px solid var(--color-border); border-radius:var(--radius-md); box-shadow:0 8px 24px rgba(0,0,0,0.12); z-index:20; }
.agent-mentions.is-open { display:block; }
.agent-mention { display:flex; justify-content:space-between; gap:var(--space-sm); width:100%; padding:8px 12px; background:none; border:0; text-align:left; cursor:pointer; font-size:0.84rem; }
.agent-mention:hover, .agent-mention[aria-selected="true"] { background:var(--color-bg-alt); }
.agent-mention__name { font-weight:600; }
.agent-mention__kind { color:var(--color-text-muted); font-size:0.75rem; }

/* A draft is a form, not a sentence — it needs the width a chat bubble caps. */
.agent-bubble:has(.agent-draft) { max-width:100%; width:100%; }
/* Native Enterprise answers are workspaces, not narrow text-message bubbles. */
.agent-row--bot { width:100%; }
.agent-row--bot .agent-bubble {
    width:min(100%,900px);
    max-width:min(100%,900px);
    flex:1 1 auto;
}
.agent-row--user { max-width:100%; }
.agent-row--user .agent-bubble { max-width:min(85%,640px); }
.agent-row--bot .bapx-a2ui { max-width:100%; }
.agent-row--bot .bapx-a2ui__report-rows { min-width:0; }
@media(max-width:768px) {
    .agent-row--bot { gap:6px; }
    .agent-row--bot .agent-avatar { width:25px;height:25px; }
    .agent-row--bot .agent-bubble { padding:10px; min-width:0; }
    .agent-messages { max-height:62dvh; }
}

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

.agent-composer { display:flex; align-items:flex-end; gap:var(--space-xs); border:1px solid var(--color-border); border-radius:var(--radius-sm); padding:6px; background:var(--color-white); transition:border-color var(--transition-base), box-shadow var(--transition-base); }
.agent-composer:focus-within { border-color:var(--color-gold); box-shadow:0 0 0 3px rgba(209,179,104,0.18); }
.agent-composer textarea { flex:1; min-width:0; border:0; outline:0; resize:none; font-family:inherit; font-size:0.9rem; line-height:1.5; padding:8px 0; max-height:160px; background:transparent; color:var(--color-ink); }
.agent-send { flex:0 0 auto; width:44px; height:44px; border-radius:8px; border:1px solid var(--color-gold); background:var(--color-maroon); color:var(--color-gold); display:inline-flex; align-items:center; justify-content:center; cursor:pointer; transition:opacity var(--transition-base); }
.agent-send:disabled { opacity:0.45; cursor:not-allowed; }
.agent-hint { margin:var(--space-2xs) 0 0; font-size:0.72rem; color:var(--color-text-muted); text-align:right; }

@media (max-width: 768px) {
    .agent-bubble { max-width:88%; }
    .agent-messages { max-height:46vh; }
    .agent-hint { display:none; }
}
</style>
<link rel="stylesheet" href="/assets/css/a2ui-public.css?v=<?= filemtime(__DIR__ . '/../../assets/css/a2ui-public.css') ?>">
<script src="/assets/a2ui-public-renderer.js?v=<?= filemtime(__DIR__ . '/../../assets/a2ui-public-renderer.js') ?>"></script>

<script>
(function () {
    const input = document.getElementById('agent-input');
    const form = document.getElementById('agent-form');
    const messages = document.getElementById('agent-messages');
    const submit = document.getElementById('agent-submit');
    const suggestions = document.getElementById('agent-suggestions');
    const clear = document.getElementById('agent-clear');
    const historyToggle = document.getElementById('agent-history-toggle');
    const historyPanel = document.getElementById('agent-history-panel');
    const historyList = document.getElementById('agent-history-list');
    const historyStatus = document.getElementById('agent-history-status');
    let conversationId = null;
    const drafts = new Map();
    function rememberDraft() {
        if (!conversationId) return;
        if (input.value) drafts.set(conversationId, input.value); else drafts.delete(conversationId);
    }
    let historyRequest = null;
    let transcriptRequest = null;

    const hideHistory = () => {
        historyRequest?.abort();
        if (transcriptRequest) {
            transcriptRequest.abort();
            transcriptRequest = null;
            submit.disabled = false;
        }
        historyStatus.textContent = '';
        historyPanel.hidden = true;
        historyToggle.setAttribute('aria-expanded', 'false');
    };

    async function loadHistory(signal, cursor = null) {
        const response = await fetch('/admin/agent/history' + (cursor ? '?cursor='+encodeURIComponent(cursor) : ''), {credentials:'same-origin',cache:'no-store',signal});
        if (!response.ok) throw new Error('Cannot load chat history');
        const data = await response.json();
        if (signal.aborted) return;
        if (!cursor) { historyStatus.textContent = ''; historyList.replaceChildren(); }
        historyList.querySelectorAll('[data-load-more-history]').forEach(el=>el.remove());
        const chats = Array.isArray(data.conversations) ? data.conversations : [];
        if (!chats.length && !cursor && !data.next_cursor) {
            historyStatus.textContent = 'No previous chats yet.';
            return;
        }
        for (const chat of chats) {
            if (!/^[a-f0-9]{32}$/.test(String(chat.id || ''))) continue;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'agent-history-entry';
            const title = document.createElement('span');
            title.textContent = String(chat.preview || 'Conversation').slice(0, 100);
            const meta = document.createElement('small');
            const date = Date.parse(chat.updated_at || '');
            meta.textContent = (Number.isFinite(date) ? new Date(date).toLocaleString() : 'Previous chat')
                + ' · ' + Number(chat.turn_count || 0) + ' message(s)';
            button.append(title, meta);
            button.addEventListener('click', () => restoreConversation(chat.id));
            historyList.appendChild(button);
            if (!Number(chat.pending_turn_count || 0)) {
                const remove=document.createElement('button');remove.type='button';remove.className='agent-suggestion';remove.textContent='Delete this chat';
                remove.addEventListener('click',async()=>{
                    if(submit.disabled||!confirm('Permanently delete this saved chat? Export it first if needed.'))return;
                    remove.disabled=true;
                    const csrf=document.querySelector('#agent-form input[name="_csrf"]')?.value||'';
                    try {const response=await fetch('/admin/agent/history/'+chat.id+'/delete',{method:'POST',credentials:'same-origin',body:new URLSearchParams({_csrf:csrf})});
                        const result=await response.json();if(!response.ok||!result.deleted)throw Error('Delete unavailable');
                        button.remove();remove.remove();if(conversationId===chat.id)clear.click();
                    } catch(error){remove.disabled=false;remove.textContent='Deletion failed — retry';}
                });
                historyList.appendChild(remove);
            }
        }
        if (typeof data.next_cursor === 'string') {
            const more=document.createElement('button');more.type='button';more.dataset.loadMoreHistory='true';more.className='agent-suggestion';more.textContent='Load older chats';
            more.addEventListener('click',async()=>{more.disabled=true;try{await loadHistory(signal, data.next_cursor);}catch(e){more.disabled=false;more.textContent='Retry older chats';}});
            historyList.appendChild(more);
        }
    }

    function renderAnswer(container, answer, surface, sources = []) {
        const rendered = surface && window.BapxA2ui && window.BapxA2ui.render(container, surface, {scope:'admin'});
        if (rendered) return;
        container.innerHTML = renderMarkdown(answer || 'No response.');
        for (const source of sources) {
            if (typeof source.path !== 'string' || !/^\/admin\/(?:support-tickets|docs\/[a-z0-9-]{1,80}|tax-report\?report=stock|tax-report\?report=(?:sales|tax|products|customers|coupons)&from=\d{4}-\d{2}-\d{2}&to=\d{4}-\d{2}-\d{2})$/.test(source.path)) continue;
            const line = document.createElement('p');
            const link = document.createElement('a');
            link.href = source.path;
            link.textContent = source.label;
            line.appendChild(link);
            container.appendChild(line);
        }
    }

    async function restoreConversation(id) {
        if (input.disabled || uploadBtn.disabled || !/^[a-f0-9]{32}$/.test(String(id))) return;
        transcriptRequest?.abort();
        transcriptRequest = new AbortController();
        const {signal} = transcriptRequest;
        submit.disabled = true;
        historyStatus.textContent = 'Opening chat…';
        try {
            const response = await fetch('/admin/agent/history/' + id, {credentials:'same-origin',cache:'no-store',signal});
            if (!response.ok) throw new Error('Conversation is unavailable');
            const data = await response.json();
            if (signal.aborted) return;
            if (data.conversation_id !== id || !Array.isArray(data.turns)) throw new Error('Invalid chat history');
            if (conversationId !== id) {
                rememberDraft();
                input.value = drafts.get(id) || '';
                closeMentions();
                document.getElementById('agent-attachment-status').textContent = '';
                autoGrow();
            }
            messages.replaceChildren();
            conversationId = id;
            for (const turn of data.turns) {
                addRow('user', escapeHtml(String(turn.question || '')).replace(/\n/g,'<br>'));
                if (turn.outcome === 'error') {
                    const bubble = addRow('bot', '');
                    bubble.className = 'agent-bubble agent-bubble--error';
                    showRequestError(bubble, turn.answer, id, turn.turn_id);
                } else {
                    renderAnswer(addRow('bot', ''), String(turn.answer || ''), turn.a2ui);
                }
            }
            clear.hidden = false;
            suggestions.style.display = 'none';
            hideHistory();
            messages.scrollTop = messages.scrollHeight;
            input.focus();
        } catch (error) {
            if (!signal.aborted) {
                transcriptRequest = null;
                submit.disabled = false;
                historyStatus.textContent = 'Could not reopen that chat. Select it again to retry.';
            }
        }
    }

    historyToggle.addEventListener('click', async () => {
        if (input.disabled) return;
        if (!historyPanel.hidden) { hideHistory(); return; }
        historyPanel.hidden = false;
        historyToggle.setAttribute('aria-expanded', 'true');
        historyList.replaceChildren();
        historyStatus.textContent = 'Loading your chats…';
        historyRequest = new AbortController();
        const {signal} = historyRequest;
        try { await loadHistory(signal); }
        catch (error) {
            if (!signal.aborted) historyStatus.textContent = 'Chat history is unavailable. Reopen History to retry.';
        }
    });
    clear.addEventListener('click', function () {
        if (input.disabled) return;
        closeMentions();
        document.getElementById('agent-attachment-status').textContent = '';
        rememberDraft();
        messages.replaceChildren();
        conversationId = null;
        hideHistory();
        suggestions.style.display = '';
        input.value = '';
        autoGrow();
        input.focus();
    });

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
            if (line.trim() === '') continue;
            const heading = line.match(/^#{1,3}\s+(.+)$/);
            if (heading) { out += '<h3>' + heading[1] + '</h3>'; continue; }
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

    function showRequestError(container, error, chatId, turnId) {
        container.textContent = error || 'The request failed.';
        if (!/^[a-f0-9]{32}$/.test(chatId || '') || !/^[a-f0-9]{32}$/.test(turnId || '')) return;
        const line = document.createElement('p');
        const review = document.createElement('a');
        review.href = '/admin/agent/review/' + chatId + '?turn=' + turnId;
        review.textContent = 'Review this request';
        line.appendChild(review);
        container.appendChild(line);
    }

    function appendRunMeta(bubble, run) {
        if (!run || !bubble) return;
        const details = document.createElement('details');
        details.className = 'agent-run';
        const routeLabel = {local:'Local data',model:'Model response',fallback:'Completed lookups',draft:'Draft builder',error:'Failed request'}[run.route] || 'Request';
        const summary = document.createElement('summary');
        summary.textContent = (Math.max(0, Number(run.elapsed_ms) || 0) / 1000).toFixed(2) + ' s';
        summary.setAttribute('aria-label', summary.textContent + ', show request details');
        details.appendChild(summary);
        const body = document.createElement('div');
        body.className = 'agent-run__body';
        const add = function (label, value) {
            const row = document.createElement('div'); row.className = 'agent-run__item';
            row.innerHTML = '<span>' + escapeHtml(label) + '</span><span>' + escapeHtml(value) + '</span>';
            body.appendChild(row);
        };
        add('Route', routeLabel);
        if (run.trace_id) add('Run reference', run.trace_id);
        (run.tools || []).forEach(function (tool) { add('Tool · ' + tool.name, tool.status + (tool.reason ? ' · ' + tool.reason.replaceAll('_', ' ') : '') + ' · ' + tool.duration_ms + ' ms'); });
        (run.attempts || []).forEach(function (attempt, index) {
            add('Model attempt ' + (index + 1) + ' · ' + attempt.model, 'HTTP ' + attempt.status + ' · ' + attempt.duration_ms + ' ms');
        });
        details.appendChild(body); bubble.appendChild(details);
    }

    function autoGrow() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 160) + 'px';
    }
    input.addEventListener('input', autoGrow);

    // Enter sends, Shift+Enter makes a new line — the convention users expect.
    input.addEventListener('keydown', function (e) {
        if (e.isComposing) return;
        if (mentionBox.classList.contains('is-open')) {
            const options = Array.from(mentionBox.children);
            const selected = options.findIndex(item => item.getAttribute('aria-selected') === 'true');
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                const next = e.key === 'ArrowDown' ? (selected + 1) % options.length
                    : (selected <= 0 ? options.length - 1 : selected - 1);
                options.forEach((item, i) => item.setAttribute('aria-selected', String(i === next)));
                input.setAttribute('aria-activedescendant', options[next].id);
                options[next].scrollIntoView({block:'nearest'});
                return;
            }
            if (e.key === 'Escape') { e.preventDefault(); closeMentions(); return; }
            if (e.key === 'Enter' && !e.shiftKey && selected >= 0) {
                e.preventDefault(); options[selected].click(); return;
            }
        }
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
    });

    suggestions.addEventListener('click', function (e) {
        const btn = e.target.closest('.agent-suggestion');
        if (!btn) return;
        input.value = btn.dataset.q;
        autoGrow();
        input.focus();
    });
    document.querySelectorAll('[data-capability-prompt]').forEach(button => { button.hidden = false; });
    document.getElementById('agent-capabilities')?.addEventListener('click', function (event) {
        const button = event.target.closest('[data-capability-prompt]');
        if (!button) return;
        input.value = button.dataset.capabilityPrompt;
        autoGrow();
        input.focus();
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
        csrf.value = document.querySelector('#agent-form input[name="_csrf"]')?.value || '';
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

    function closeMentions() { mentionBox.classList.remove('is-open'); input.removeAttribute('aria-activedescendant'); }

    function openMentions(query) {
        if (!mentionBox) return;
        const q = query.toLowerCase();
        const hits = attachables.filter(a => a.name.toLowerCase().includes(q)).slice(0, 8);
        if (!hits.length) { closeMentions(); return; }
        input.removeAttribute('aria-activedescendant');
        mentionBox.replaceChildren();
        hits.forEach(function (hit, index) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'agent-mention';
            item.id = 'agent-option-' + index;
            item.tabIndex = -1;
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', 'false');
            item.addEventListener('mousedown', event => event.preventDefault());
            item.innerHTML = '<span class="agent-mention__name">@' + escapeHtml(hit.name) + '</span>'
                + '<span class="agent-mention__kind">' + escapeHtml(hit.kind) + '</span>';
            item.addEventListener('click', function () {
                const end = input.selectionStart;
                const token = input.value.slice(0, end).match(/@[A-Za-z0-9._-]*$/);
                if (token) input.setRangeText('@' + hit.name + ' ', end - token[0].length, end, 'end');
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
        uploadInput.addEventListener('change', async function () {
            if (!uploadInput.files.length || input.disabled || submit.disabled) return;
            const status = document.getElementById('agent-attachment-status');
            hideHistory();
            closeMentions();
            uploadBtn.disabled = submit.disabled = clear.disabled = historyToggle.disabled = true;
            suggestions.inert = true;
            status.textContent = 'Uploading to the media library…';
            try {
                const uploadForm = document.getElementById('agent-upload-form');
                const response = await fetch(uploadForm.action, {method:'POST', body:new FormData(uploadForm),
                    headers:{Accept:'application/json'}, credentials:'same-origin'});
                const result = await response.json();
                if (!response.ok || !Array.isArray(result.files)) throw new Error(result.error || 'Upload failed. Select the file again to retry.');
                const names = result.files.map(file => String(file.url || '').split('/').pop());
                const tokens = [];
                for (const name of names) {
                    if (!/^[A-Za-z0-9][A-Za-z0-9._-]{1,80}$/.test(name)) continue;
                    if (!attachables.some(hit => hit.name === name)) attachables.push({name, kind:'image'});
                    tokens.push('@' + name);
                }
                const text = [input.value.trimEnd(), ...tokens].filter(Boolean).join(' ');
                if (text.length <= input.maxLength && tokens.length === names.length) {
                    input.value = text + (text.length < input.maxLength ? ' ' : '');
                    autoGrow();
                    status.textContent = 'Images attached to this message.';
                } else {
                    status.textContent = 'Saved to Media. Shorten the message or choose an image with @.';
                }
            } catch (error) {
                status.textContent = error instanceof SyntaxError ? 'Upload interrupted. Check Media before retrying.' : error.message;
            } finally {
                uploadInput.value = '';
                suggestions.inert = false;
                uploadBtn.disabled = submit.disabled = clear.disabled = historyToggle.disabled = false;
                input.focus();
            }
        });
    }

    window.askAgent = async function (e) {
        e.preventDefault();
        const msg = input.value.trim();
        if (!msg || submit.disabled) return false;
        hideHistory();

        addRow('user', escapeHtml(msg).replace(/\n/g, '<br>'));
        input.value = ''; autoGrow();
        closeMentions();
        document.getElementById('agent-attachment-status').textContent = '';
        uploadBtn.disabled = true;
        input.disabled = true; submit.disabled = true;
        suggestions.style.display = 'none';
        clear.hidden = false;
        clear.disabled = true;
        historyToggle.disabled = true;

        const thinking = addRow('bot', '');
        const progress = window.AgentRequestStatus.start(thinking);
        const response = document.createElement('div');
        thinking.appendChild(response);
        let failed = false;
        const abort = new AbortController();
        const timeout = setTimeout(() => abort.abort(), 35000);

        try {
            const body = new URLSearchParams({
                message: msg,
                _csrf: document.querySelector('#agent-form input[name="_csrf"]')?.value || ''
            });
            if (conversationId) body.set('conversation_id', conversationId);
            const resp = await fetch('/admin/agent/ask', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body,
                credentials: 'same-origin',
                signal: abort.signal
            });
            const data = await resp.json().catch(() => ({ error: 'The server returned an unreadable response.' }));
            if (typeof data.conversation_id === 'string') conversationId = data.conversation_id;
            if (!resp.ok || data.error) {
                failed = true;
                thinking.className = 'agent-bubble agent-bubble--error';
                showRequestError(response, data.error, data.conversation_id, data.turn_id);
            } else if (data.draft) {
                response.appendChild(buildDraftForm(data.draft));
            } else {
                renderAnswer(response, data.answer, data.a2ui, data.run?.sources || []);
            }
            appendRunMeta(thinking, data.run);
            if (data.missing && data.missing.length) {
                addRow('bot', 'I could not find ' + data.missing.map(m => '<code>@' + escapeHtml(m) + '</code>').join(', ')
                    + '. Type <code>@</code> to see what is available.');
            }
        } catch (err) {
            failed = true;
            thinking.className = 'agent-bubble agent-bubble--error';
            response.textContent = 'The response was interrupted. Check History before sending the question again.';
        } finally {
            clearTimeout(timeout);
            progress.finish(failed);
        }

        if (failed && !input.value) { input.value = msg; autoGrow(); }
        uploadBtn.disabled = false;
        input.disabled = false; submit.disabled = false; clear.disabled = false; historyToggle.disabled = false;
        input.focus();
        messages.scrollTop = messages.scrollHeight;
        return false;
    };

})();
</script>
