<div class="admin-card">
    <h2 style="font-size:1.1rem; margin:0 0 var(--space-sm);">AI Agent (bapXcli)</h2>
    <p style="margin:0 0 var(--space-lg); color:var(--color-text-muted); font-size:0.9rem;">
        Ask questions about your site — users, orders, revenue, products, consultations.
        Configure the AI model in <a href="/admin/integrations">Integrations</a>
        (keys: <code>google_api_key</code>, <code>model</code>).
    </p>
    <?php if (empty($modelConfig['api_key'])): ?>
        <div style="background:var(--color-warning-bg, #fff3cd); border:1px solid var(--color-warning-border, #ffc107); border-radius:var(--radius-md); padding:var(--space-md); margin-bottom:var(--space-lg);">
            <strong>AI model not configured.</strong>
            Go to <a href="/admin/integrations">Admin → Integrations</a> and save the AI model secrets first.
        </div>
    <?php endif; ?>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-md); margin-bottom:var(--space-lg);">
        <div style="background:var(--color-bg-alt); padding:var(--space-md); border-radius:var(--radius-md);">
            <small style="color:var(--color-text-muted);">Model</small>
            <div style="font-weight:600;"><?= e($modelConfig['provider'] ?? 'google') ?> / <?= e($modelConfig['model'] ?? 'gemini-2.5-flash') ?></div>
        </div>
        <div style="background:var(--color-bg-alt); padding:var(--space-md); border-radius:var(--radius-md);">
            <small style="color:var(--color-text-muted);">Endpoint</small>
            <div style="font-weight:600; font-size:0.8rem; word-break:break-all;"><?= e($modelConfig['endpoint'] ?? '—') ?></div>
        </div>
    </div>
</div>

<div class="admin-card">
    <div id="agent-messages" style="display:flex; flex-direction:column; gap:var(--space-md); margin-bottom:var(--space-lg); min-height:100px; max-height:400px; overflow-y:auto; padding:var(--space-sm);">
        <div class="agent-message agent-message--system" style="padding:var(--space-sm) var(--space-md); background:var(--color-bg-alt); border-radius:var(--radius-md); font-size:0.85rem; color:var(--color-text-muted);">
            Ask me about your site — users, orders, revenue, products, or anything about your business.
        </div>
    </div>
    <form id="agent-form" method="post" action="/admin/agent/ask" style="display:flex; gap:var(--space-sm);" onsubmit="return askAgent(event)">
        <input type="text" id="agent-input" name="message" placeholder="e.g. How many users? What's the revenue? Create a blog post about Diwali..." required style="flex:1; padding:var(--space-sm) var(--space-md); border:1px solid var(--color-border); border-radius:var(--radius-md); font-size:0.9rem;">
        <button type="submit" class="btn btn-primary" id="agent-submit">Ask</button>
    </form>
</div>

<script>
async function askAgent(e) {
    e.preventDefault();
    const input = document.getElementById('agent-input');
    const submit = document.getElementById('agent-submit');
    const messages = document.getElementById('agent-messages');
    const msg = input.value.trim();
    if (!msg) return false;
    input.disabled = true;
    submit.disabled = true;
    submit.textContent = 'Thinking...';
    messages.innerHTML += '<div class="agent-message agent-message--user" style="padding:var(--space-sm) var(--space-md); background:var(--color-maroon); color:#fff; border-radius:var(--radius-md); font-size:0.85rem; align-self:flex-end; max-width:80%;">' + escapeHtml(msg) + '</div>';
    input.value = '';
    try {
        const resp = await fetch('/admin/agent/ask', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'message='+encodeURIComponent(msg) + '&csrf_token=' + encodeURIComponent(document.querySelector('input[name="csrf_token"]')?.value || '') });
        const data = await resp.json();
        if (data.error) {
            messages.innerHTML += '<div class="agent-message agent-message--error" style="padding:var(--space-sm) var(--space-md); background:var(--color-error-bg, #f8d7da); border:1px solid var(--color-error, #dc3545); border-radius:var(--radius-md); font-size:0.85rem; color:var(--color-error, #dc3545);">Error: ' + escapeHtml(data.error) + '</div>';
        } else {
            messages.innerHTML += '<div class="agent-message agent-message--bot" style="padding:var(--space-sm) var(--space-md); background:var(--color-bg-alt); border-radius:var(--radius-md); font-size:0.85rem; line-height:1.6;">' + marked(data.answer || '') + '</div>';
        }
    } catch (err) {
        messages.innerHTML += '<div class="agent-message agent-message--error" style="padding:var(--space-sm) var(--space-md); background:var(--color-error-bg, #f8d7da); border:1px solid var(--color-error, #dc3545); border-radius:var(--radius-md); font-size:0.85rem; color:var(--color-error, #dc3545);">Network error. Check console.</div>';
    }
    messages.scrollTop = messages.scrollHeight;
    input.disabled = false;
    submit.disabled = false;
    submit.textContent = 'Ask';
    return false;
}
function escapeHtml(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function marked(s) { return s.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>'); }
</script>
