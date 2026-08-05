<div class="admin-card" style="border-left:4px solid var(--color-gold); margin-bottom:var(--space-lg);">
    <h2 style="font-size:1rem; margin:0 0 var(--space-sm);"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg> Protected Settings</h2>
    <p style="margin:0; color:var(--color-text-muted); font-size:0.9rem;">Live secrets are stored outside Git-tracked files. Use the Integrations page to manage API keys safely.</p>
</div>
<div class="admin-card" style="margin-bottom:var(--space-lg);">
    <h2 style="font-size:1rem; margin:0 0 var(--space-sm);">Admin Login</h2>
    <p style="margin:0 0 var(--space-lg); color:var(--color-text-muted); font-size:0.9rem;">These credentials are loaded from <code>.env</code>. Change them before production use.</p>
    <form class="admin-form" method="post" action="/admin/settings/admin-credentials">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="admin-form__row">
            <div class="form-group">
                <label>Admin Username</label>
                <input type="text" name="admin_username" value="<?= e($adminCredentials['username'] ?? '') ?>" autocomplete="username" required>
            </div>
            <div class="form-group">
                <label>Admin Email</label>
                <input type="email" name="admin_email" value="<?= e($adminCredentials['email'] ?? '') ?>" autocomplete="email" required>
            </div>
        </div>
        <div class="form-group">
            <label>Admin Password</label>
            <input type="password" name="admin_password" placeholder="Leave blank to keep current password" autocomplete="new-password">
        </div>
        <button class="btn btn-primary btn-sm">Save Admin Login</button>
    </form>
</div>
<div class="admin-card">
    <h2 style="font-size:1rem; margin:0 0 var(--space-sm);">Site Modules</h2>
    <p style="margin:0 0 var(--space-lg); font-size:0.85rem; color:var(--color-text-muted);">
        Switching a module off hides its navigation links and home page section, and makes its
        public pages return 404. Existing data is kept and reappears when you switch it back on.
    </p>
    <form class="admin-form" method="post" action="/admin/settings/save">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <?php $__mods = (new \App\Services\SettingsService())->modules(); ?>
        <?php foreach (\App\Services\SettingsService::MODULES as $__key => $__label): ?>
            <div class="form-group" style="display:flex; flex-direction:row; align-items:center; justify-content:flex-start; gap:var(--space-sm); margin-bottom:var(--space-sm);">
                <input type="hidden" name="module_<?= e($__key) ?>" value="0">
                <input type="checkbox" id="module_<?= e($__key) ?>" name="module_<?= e($__key) ?>" value="1"
                       style="width:18px; height:18px; flex:0 0 auto; margin:0;" <?= $__mods[$__key] ? 'checked' : '' ?>>
                <label for="module_<?= e($__key) ?>" style="margin:0; font-weight:600; text-transform:none; letter-spacing:0; cursor:pointer;"><?= e($__label) ?></label>
            </div>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-primary">Save Modules</button>
    </form>
</div>
<div class="admin-card">
    <h2 style="font-size:1rem; margin:0 0 var(--space-lg);">Shipping Configuration</h2>
    <form class="admin-form" method="post" action="/admin/settings/save">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="admin-form__row">
            <div class="form-group">
                <label>Shipping Mode</label>
                <?php $mode = $settings['shipping_mode'] ?? 'free'; ?>
                <select name="shipping_mode">
                    <option value="flat" <?= $mode === 'flat' ? 'selected' : '' ?>>Flat rate</option>
                    <option value="free" <?= $mode === 'free' ? 'selected' : '' ?>>Free shipping</option>
                </select>
            </div>
            <div class="form-group">
                <label>Flat Rate (₹)</label>
                <input type="number" name="flat_rate" value="<?= e((string)($settings['flat_rate'] ?? 0)) ?>" min="0" step="1">
            </div>
        </div>
        <div class="admin-form__row">
            <div class="form-group">
                <label>Currency</label>
                <input type="text" name="currency" value="<?= e($settings['currency'] ?? 'INR') ?>">
            </div>
            <div class="form-group">
                <label>Timezone</label>
                <input type="text" name="timezone" value="<?= e($settings['timezone'] ?? 'Asia/Kolkata') ?>">
            </div>
        </div>
        <button class="btn btn-primary btn-sm">Save Settings</button>
    </form>
</div>
<div class="admin-card" style="margin-top:var(--space-lg);">
    <h2 style="font-size:1rem; margin:0 0 var(--space-sm);">GST Configuration</h2>
    <p style="margin:0 0 var(--space-lg); font-size:0.85rem; color:var(--color-text-muted);">
        Prices are GST-inclusive. The default rate below applies to any product that does not
        set its own rate on the product form. Confirm the rate and HSN code with your accountant.
    </p>
    <form class="admin-form" method="post" action="/admin/settings/save">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="admin-form__row">
            <div class="form-group">
                <label>Default GST Rate (%)</label>
                <input type="number" name="default_gst_rate" step="0.01" min="0" max="100"
                       value="<?= e((string)($settings['default_gst_rate'] ?? \App\Services\TaxService::DEFAULT_GST_RATE)) ?>" placeholder="5">
            </div>
            <div class="form-group">
                <label>Default HSN Code</label>
                <input type="text" name="default_hsn_code" maxlength="8"
                       value="<?= e((string)($settings['default_hsn_code'] ?? \App\Services\TaxService::DEFAULT_HSN_CODE)) ?>" placeholder="8306">
            </div>
        </div>
        <div class="admin-form__row">
            <div class="form-group">
                <label>GSTIN</label>
                <input type="text" name="gstin" value="<?= e($settings['gstin'] ?? '') ?>" placeholder="33ABCDE1234F1Z5" maxlength="15">
            </div>
            <div class="form-group">
                <label>State Code</label>
                <input type="text" name="gst_state_code" value="<?= e($settings['gst_state_code'] ?? '33') ?>" placeholder="33" maxlength="2">
            </div>
        </div>
        <div class="admin-form__row">
            <div class="form-group">
                <label>Legal Name</label>
                <input type="text" name="gst_legal_name" value="<?= e($settings['gst_legal_name'] ?? '') ?>" placeholder="Registered business name">
            </div>
            <div class="form-group">
                <label>Trade Name</label>
                <input type="text" name="gst_trade_name" value="<?= e($settings['gst_trade_name'] ?? '') ?>" placeholder="Brand / trade name">
            </div>
        </div>
        <div class="form-group">
            <label>Address</label>
            <textarea name="gst_address" rows="3" placeholder="Registered business address"><?= e($settings['gst_address'] ?? '') ?></textarea>
        </div>
        <div class="admin-form__row">
            <div class="form-group">
                <label>State</label>
                <input type="text" name="gst_state" value="<?= e($settings['gst_state'] ?? 'Tamil Nadu') ?>" placeholder="Tamil Nadu">
            </div>
            <div class="form-group">
                <label></label>
                <span style="font-size:0.8rem; color:var(--color-text-muted); display:block; padding-top:0.5rem;">Used for supply-type determination (intrastate vs interstate).</span>
            </div>
        </div>
        <button class="btn btn-primary btn-sm">Save Settings</button>
    </form>
</div>
