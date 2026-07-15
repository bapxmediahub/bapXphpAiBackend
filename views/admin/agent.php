<div id="audio-activation-banner" style="background:#ff9800; color:#fff; padding:12px; text-align:center; font-weight:bold; position:sticky; top:0; z-index:9999; display:none;">
    ⚠️ Audio is paused. Click anywhere to activate live voice alerts.
</div>

<div class="admin-card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-sm);">
        <h2 style="font-size:1.1rem; margin:0;">AI Agent (bapXcli)</h2>
        <label style="display:flex; align-items:center; gap:6px; font-size:0.85rem; cursor:pointer;">
            <input type="checkbox" id="tts-mute-toggle" onchange="ttsMuted = this.checked" style="width:16px;height:16px;">
            Mute TTS
        </label>
    </div>
    <p style="margin:0 0 var(--space-lg); color:var(--color-text-muted); font-size:0.9rem;">
        Ask questions about your site — users, orders, revenue, products, consultations.
        Configure the AI model in <a href="/admin/integrations">Integrations</a>
        (keys: <code>agent_api_key</code>, <code>agent_model</code>, <code>api_endpoint</code>).
    </p>
    <?php if (empty($modelConfig['api_key'])): ?>
        <div style="background:var(--color-warning-bg, #fff3cd); border:1px solid var(--color-warning-border, #ffc107); border-radius:var(--radius-md); padding:var(--space-md); margin-bottom:var(--space-lg);">
            <strong>AI model not configured.</strong>
            Go to <a href="/admin/integrations">Admin → Integrations</a> and set endpoint, api_key, and model first.
        </div>
    <?php endif; ?>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-md); margin-bottom:var(--space-lg);">
        <div style="background:var(--color-bg-alt); padding:var(--space-md); border-radius:var(--radius-md);">
            <small style="color:var(--color-text-muted);">Model</small>
            <div style="font-weight:600;"><?= e($modelConfig['model'] ?? 'gemma-4-31b-it') ?></div>
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
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="text" id="agent-input" name="message" placeholder="e.g. How many users? What's the revenue? Create a blog post about Diwali..." required style="flex:1; padding:var(--space-sm) var(--space-md); border:1px solid var(--color-border); border-radius:var(--radius-md); font-size:0.9rem;">
        <button type="submit" class="btn btn-primary" id="agent-submit">Ask</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/onnxruntime-web@latest/dist/ort.min.js"></script>
<script>
let ttsSession = null;
let ttsMuted = false;
let lastProcessedTicketId = 0;
let audioContextUnlocked = false;

window.addEventListener('click', () => {
    window.__audioClicked = true;
    if (!audioContextUnlocked) {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        ctx.resume();
        audioContextUnlocked = true;
        const banner = document.getElementById('audio-activation-banner');
        if (banner) banner.style.display = 'none';
    }
}, { once: true });

async function initBrowserTtsEngine() {
    try {
        const banner = document.getElementById('audio-activation-banner');
        if (banner && !window.__audioClicked) banner.style.display = 'block';
        ttsSession = await ort.InferenceSession.create('/storage/kittentts/model_quantized.onnx', {
            executionProviders: ['wasm']
        });
        console.log('KittenTTS engine ready');
    } catch (err) {
        console.warn('KittenTTS not available:', err.message);
    }
}

async function synthesizeAgentVoice(text) {
    if (!ttsSession || ttsMuted) return;
    try {
        const resp = await fetch('/api/tts/tokenize', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ text })
        });
        const data = await resp.json();
        if (!data.success) throw new Error(data.error);
        const tokens = BigInt64Array.from(data.tokens.map(t => BigInt(t)));
        const tensor = new ort.Tensor('int64', tokens, [1, tokens.length]);
        const results = await ttsSession.run({ 'input_ids': tensor });
        const output = results[Object.keys(results)[0]].data;
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const buffer = ctx.createBuffer(1, output.length, 24000);
        buffer.getChannelData(0).set(output);
        const src = ctx.createBufferSource();
        src.buffer = buffer;
        src.connect(ctx.destination);
        src.start();
    } catch (err) {
        console.error('TTS failed:', err);
    }
}

async function startSupportPolling() {
    setInterval(async () => {
        if (!ttsSession || !audioContextUnlocked || ttsMuted) return;
        try {
            const resp = await fetch('/api/support/latest-message');
            const data = await resp.json();
            if (data.success && data.message && data.message.id !== lastProcessedTicketId) {
                lastProcessedTicketId = data.message.id;
                await synthesizeAgentVoice(data.message.text);
            }
        } catch (err) {
            console.error('Polling error:', err);
        }
    }, 5000);
}

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
        const resp = await fetch('/admin/agent/ask', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'message='+encodeURIComponent(msg) + '&_csrf=' + encodeURIComponent(document.querySelector('input[name="_csrf"]')?.value || '') });
        const data = await resp.json();
        if (data.error) {
            messages.innerHTML += '<div class="agent-message agent-message--error" style="padding:var(--space-sm) var(--space-md); background:var(--color-error-bg, #f8d7da); border:1px solid var(--color-error, #dc3545); border-radius:var(--radius-md); font-size:0.85rem; color:var(--color-error, #dc3545);">Error: ' + escapeHtml(data.error) + '</div>';
        } else {
            const msgId = 'msg-' + Date.now();
    messages.innerHTML += '<div class="agent-message agent-message--bot" style="padding:var(--space-sm) var(--space-md); background:var(--color-bg-alt); border-radius:var(--radius-md); font-size:0.85rem; line-height:1.6;"><span id="' + msgId + '">' + marked(data.answer || '') + '</span> <button onclick="synthesizeAgentVoice(document.getElementById(\'' + msgId + '\').innerText)" style="background:none;border:none;cursor:pointer;font-size:1rem;padding:2px 6px;border-radius:4px;" title="Speak response">🔊</button></div>';
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
