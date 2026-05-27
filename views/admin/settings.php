<div class="admin-header">
    <h1><?= e($title ?? 'Settings') ?></h1>
    <a href="/admin" class="btn btn-sm btn-ghost">← Dashboard</a>
</div>
<div class="admin-card" style="border-left:4px solid var(--color-gold);">
    <h2>⚙️ Protected Settings</h2>
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
