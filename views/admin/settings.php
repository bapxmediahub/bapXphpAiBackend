<div class="admin-header">
    <h1><?= e($title ?? 'Settings') ?></h1>
    <a href="/admin" class="btn btn-sm btn-ghost">← Dashboard</a>
</div>
<div class="admin-card" style="border-left:4px solid var(--color-gold);">
    <h2><svg class="icon icon--sm" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg> Protected Settings</h2>
    <p>Live secrets are stored outside Git-tracked files. Use the Integrations page to manage API keys safely.</p>
</div>
<div class="admin-card">
    <h2>Shipping Configuration</h2>
    <div class="admin-form__row">
        <div class="form-group">
            <label>Shipping Mode</label>
            <select><option>Flat rate</option><option>Free shipping</option></select>
        </div>
        <div class="form-group">
            <label>Flat Rate (₹)</label>
            <input type="number" placeholder="50">
        </div>
    </div>
    <button class="btn btn-primary btn-sm">Save Settings</button>
</div>
