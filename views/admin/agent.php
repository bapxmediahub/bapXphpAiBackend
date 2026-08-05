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
            Set <code>agent_api_key</code> in <a href="/admin/integrations">Admin → Integrations</a> to enable the agent.
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
        <?php foreach (['How many users do we have?', 'What is the revenue this month?', 'Which products sell best?', 'How many pending orders?'] as $__s): ?>
            <button type="button" class="agent-suggestion" data-q="<?= e($__s) ?>"><?= e($__s) ?></button>
        <?php endforeach; ?>
    </div>

    <form id="agent-form" class="agent-composer" onsubmit="return askAgent(event)">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <textarea id="agent-input" name="message" rows="1" required
                  placeholder="Ask anything about your store…"
                  aria-label="Message"></textarea>
        <button type="submit" class="agent-send" id="agent-submit" aria-label="Send">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        </button>
    </form>
    <p class="agent-hint">Enter to send · Shift+Enter for a new line</p>
</div>

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
            } else {
                thinking.innerHTML = renderMarkdown(data.answer || 'No response.');
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
