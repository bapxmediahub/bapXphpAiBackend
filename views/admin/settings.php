<div class="admin-card" style="border-left:4px solid var(--color-gold); margin-bottom:var(--space-lg);">
    <h2 style="font-size:1rem; margin:0 0 var(--space-sm);"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg> Protected Settings</h2>
    <p style="color:var(--color-text-muted); font-size:0.9rem;">Manage API keys in <a href="/admin/integrations">Integrations</a>.</p>
    <div role="navigation" aria-label="Settings sections" class="admin-card__actions">
        <a class="btn btn-ghost" href="#settings-site-profile">Public Site Profile</a>
        <a class="btn btn-ghost" href="#settings-login">Admin Login</a>
        <a class="btn btn-ghost" href="#settings-modules">Site Modules</a>
        <a class="btn btn-ghost" href="#settings-shipping">Shipping Configuration</a>
        <a class="btn btn-ghost" href="#settings-gst">GST Configuration</a>
    </div>
</div>
<section id="settings-site-profile" aria-labelledby="settings-profile-heading" class="admin-card settings-section" tabindex="-1" style="margin-bottom:var(--space-lg);">
    <h2 id="settings-profile-heading">Public site profile</h2>
    <p>These details appear on public pages and are available to assistants. Mail delivery settings are managed in <a href="/admin/integrations">Integrations</a>.</p>
    <?php $profileValues = $siteProfile->valuesForEditor(); ?>
    <form class="admin-form" method="post" action="/admin/settings/save">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="save_site_profile" value="1">
        <div class="admin-form__row">
            <div class="form-group">
                <label for="site-display-name">Display name</label>
                <input id="site-display-name" name="site_display_name" maxlength="160" value="<?= e($profileValues['site_display_name']) ?>">
            </div>
            <div class="form-group">
                <label for="site-public-email">Public email</label>
                <input id="site-public-email" type="email" name="public_contact_email" maxlength="254" value="<?= e($profileValues['public_contact_email']) ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="site-summary">Business summary</label>
            <input id="site-summary" name="site_summary" maxlength="280" value="<?= e($profileValues['site_summary']) ?>">
        </div>
        <div class="form-group">
            <label for="site-story">About story</label>
            <textarea id="site-story" name="site_story" rows="5" maxlength="1800"><?= e($profileValues['site_story']) ?></textarea>
            <small>Separate paragraphs with a blank line.</small>
        </div>
        <p>Use international phone numbers. Leave a contact field blank to hide it.</p>
        <div class="admin-form__row">
            <?php for ($index = 1; $index <= 2; ++$index): $key = 'public_contact_phone_' . $index; ?>
            <div class="form-group">
                <label for="<?= e($key) ?>">Public phone <?= $index ?></label>
                <input id="<?= e($key) ?>" type="tel" name="<?= e($key) ?>" maxlength="32" value="<?= e($profileValues[$key]) ?>">
            </div>
            <?php endfor; ?>
        </div>
        <?php for ($index = 1; $index <= 2; ++$index): ?>
        <div class="admin-form__row">
            <?php foreach (['days' => 'Days', 'times' => 'Hours'] as $part => $label): $key = 'public_hours_' . $index . '_' . $part; ?>
            <div class="form-group">
                <label for="<?= e($key) ?>"><?= e($label) ?> <?= $index ?></label>
                <input id="<?= e($key) ?>" name="<?= e($key) ?>" maxlength="40" value="<?= e($profileValues[$key]) ?>">
            </div>
            <?php endforeach; ?>
        </div>
        <?php endfor; ?>
        <button class="btn btn-primary">Save public site profile</button>
    </form>
</section>
<section id="settings-login" aria-labelledby="settings-login-heading" class="admin-card settings-section" tabindex="-1" style="margin-bottom:var(--space-lg);">
    <h2 id="settings-login-heading" style="font-size:1rem; margin:0 0 var(--space-sm);">Admin Login</h2>
    <p style="margin:0 0 var(--space-lg); color:var(--color-text-muted); font-size:0.9rem;">Admin passwords saved here are hashed before storage.</p>
    <form class="admin-form" method="post" action="/admin/settings/admin-credentials">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="admin-form__row">
            <div class="form-group">
                <label for="settings-admin-username">Admin Username</label>
                <input type="text" id="settings-admin-username" name="admin_username" value="<?= e($adminCredentials['username'] ?? '') ?>" autocomplete="username" required>
            </div>
            <div class="form-group">
                <label for="settings-admin-email">Admin Email</label>
                <input type="email" id="settings-admin-email" name="admin_email" value="<?= e($adminCredentials['email'] ?? '') ?>" autocomplete="email" required>
            </div>
        </div>
        <div class="form-group">
            <label for="settings-admin-password">Admin Password</label>
            <input type="password" id="settings-admin-password" name="admin_password" placeholder="Leave blank to keep current password" autocomplete="new-password">
        </div>
        <button class="btn btn-primary btn-sm">Save Admin Login</button>
    </form>
</section>
<section id="settings-modules" aria-labelledby="settings-modules-heading" class="admin-card settings-section" tabindex="-1">
    <h2 id="settings-modules-heading" style="font-size:1rem; margin:0 0 var(--space-sm);">Site Modules</h2>
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
</section>
<section id="settings-shipping" aria-labelledby="settings-shipping-heading" class="admin-card settings-section" tabindex="-1">
    <h2 id="settings-shipping-heading" style="font-size:1rem; margin:0 0 var(--space-lg);">Shipping Configuration</h2>
    <form class="admin-form" method="post" action="/admin/settings/save">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="admin-form__row">
            <div class="form-group">
                <label for="settings-shipping-mode">Shipping Mode</label>
                <?php $mode = $settings['shipping_mode'] ?? 'free'; ?>
                <select name="shipping_mode" id="settings-shipping-mode">
                    <option value="flat" <?= $mode === 'flat' ? 'selected' : '' ?>>Flat rate</option>
                    <option value="free" <?= $mode === 'free' ? 'selected' : '' ?>>Free shipping</option>
                </select>
            </div>
            <div class="form-group">
                <label for="settings-flat-rate">Flat Rate (₹)</label>
                <input type="number" id="settings-flat-rate" name="flat_rate" value="<?= e((string)($settings['flat_rate'] ?? 0)) ?>" min="0" step="1">
            </div>
        </div>
        <div class="admin-form__row">
            <div class="form-group">
                <label for="settings-currency">Currency</label>
                <input type="text" id="settings-currency" name="currency" value="<?= e($settings['currency'] ?? 'INR') ?>">
            </div>
            <div class="form-group">
                <label for="settings-timezone">Timezone</label>
                <input type="text" id="settings-timezone" name="timezone" value="<?= e($settings['timezone'] ?? 'Asia/Kolkata') ?>">
            </div>
        </div>
        <button class="btn btn-primary btn-sm">Save Shipping Configuration</button>
    </form>
</section>
<section id="settings-gst" aria-labelledby="settings-gst-heading" class="admin-card settings-section" tabindex="-1" style="margin-top:var(--space-lg);">
    <h2 id="settings-gst-heading" style="font-size:1rem; margin:0 0 var(--space-sm);">GST Configuration</h2>
    <p style="margin:0 0 var(--space-lg); font-size:0.85rem; color:var(--color-text-muted);">
        Prices are GST-inclusive. The default rate below applies to any product that does not
        set its own rate on the product form. Confirm the rate and HSN code with your accountant. Supplier details are saved on new invoices; existing issued invoices keep their original details.
    </p>
    <form class="admin-form" method="post" action="/admin/settings/save">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="admin-form__row">
            <div class="form-group">
                <label for="settings-default-gst-rate">Default GST Rate (%)</label>
                <input type="number" id="settings-default-gst-rate" name="default_gst_rate" step="0.01" min="0" max="100"
                       value="<?= e((string)($settings['default_gst_rate'] ?? \App\Services\TaxService::DEFAULT_GST_RATE)) ?>" placeholder="5">
            </div>
            <div class="form-group">
                <label for="settings-default-hsn-code">Default HSN Code</label>
                <input type="text" id="settings-default-hsn-code" name="default_hsn_code" maxlength="8"
                       value="<?= e((string)($settings['default_hsn_code'] ?? \App\Services\TaxService::DEFAULT_HSN_CODE)) ?>" placeholder="8306">
            </div>
        </div>
        <div class="admin-form__row">
            <div class="form-group">
                <label for="supplier-gstin">Supplier GSTIN (tax number)</label>
                <input id="supplier-gstin" type="text" name="gstin" value="<?= e($settings['gstin'] ?? '') ?>" placeholder="33ABCDE1234F1Z5" maxlength="15">
            </div>
            <div class="form-group">
                <label for="settings-gst-state-code">State Code</label>
                <input type="text" id="settings-gst-state-code" name="gst_state_code" value="<?= e($settings['gst_state_code'] ?? '33') ?>" placeholder="33" maxlength="2">
            </div>
        </div>
        <div class="admin-form__row">
            <div class="form-group">
                <label for="settings-gst-legal-name">Legal Name</label>
                <input type="text" id="settings-gst-legal-name" name="gst_legal_name" value="<?= e($settings['gst_legal_name'] ?? '') ?>" placeholder="Registered business name">
            </div>
            <div class="form-group">
                <label for="settings-gst-trade-name">Trade Name</label>
                <input type="text" id="settings-gst-trade-name" name="gst_trade_name" value="<?= e($settings['gst_trade_name'] ?? '') ?>" placeholder="Brand / trade name">
            </div>
        </div>
        <div class="form-group">
            <label for="settings-gst-address">Address</label>
            <textarea id="settings-gst-address" name="gst_address" rows="3" placeholder="Registered business address"><?= e($settings['gst_address'] ?? '') ?></textarea>
        </div>
        <div class="admin-form__row">
            <div class="form-group">
                <label for="settings-gst-state">State</label>
                <input type="text" id="settings-gst-state" name="gst_state" value="<?= e($settings['gst_state'] ?? 'Tamil Nadu') ?>" placeholder="Tamil Nadu" aria-describedby="settings-gst-state-help">
                <small id="settings-gst-state-help" style="color:var(--color-text-muted);">Determines whether a sale is intrastate or interstate.</small>
            </div>
        </div>
        <button class="btn btn-primary btn-sm">Save GST Configuration</button>
    </form>
</section>
<style>
    .settings-section { scroll-margin-block-start: 6rem; }
    @media (max-width: 768px) { .settings-section { scroll-margin-block-start: 8rem; } }
</style>
