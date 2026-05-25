<?php $settings = $settings ?? []; ?>
<h1><?= e($title ?? 'Settings') ?></h1>
<?php if(!empty($_SESSION['flash'])): ?><p class="notice"><?= e($_SESSION['flash']); unset($_SESSION['flash']); ?></p><?php endif; ?>
<p>Protected settings workspace. SMTP passwords are stored in local JSON storage and are never printed back into the page.</p>

<form method="post" action="/admin/settings/save" class="settings-form">
    <section class="panel">
        <h2>Contact and WhatsApp</h2>
        <div class="form-grid">
            <label>Contact email <input type="email" name="contact_email" value="<?= e($settings['contact_email'] ?? '') ?>"></label>
            <label>Customer phone number <input name="contact_phone" value="<?= e($settings['contact_phone'] ?? '') ?>" placeholder="+91 98765 43210"></label>
            <label>WhatsApp number <input name="whatsapp_number" value="<?= e($settings['whatsapp_number'] ?? '') ?>" placeholder="919876543210"></label>
            <label>Admin notification email <input type="email" name="admin_notification_email" value="<?= e($settings['admin_notification_email'] ?? '') ?>"></label>
        </div>
    </section>

    <section class="panel">
        <h2>SMTP Mail</h2>
        <div class="form-grid">
            <label>SMTP host <input name="smtp_host" value="<?= e($settings['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com"></label>
            <label>SMTP port <input type="number" name="smtp_port" value="<?= e((string)($settings['smtp_port'] ?? 587)) ?>"></label>
            <label>Encryption
                <select name="smtp_encryption">
                    <?php foreach(['tls'=>'TLS','ssl'=>'SSL','none'=>'None'] as $value=>$label): ?>
                        <option value="<?= e($value) ?>" <?= (($settings['smtp_encryption'] ?? 'tls')===$value)?'selected':'' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>SMTP username <input name="smtp_username" value="<?= e($settings['smtp_username'] ?? '') ?>"></label>
            <label>SMTP password <input type="password" name="smtp_password" placeholder="Leave blank to keep current password"></label>
            <label>From email <input type="email" name="smtp_from_email" value="<?= e($settings['smtp_from_email'] ?? '') ?>"></label>
            <label>From name <input name="smtp_from_name" value="<?= e($settings['smtp_from_name'] ?? 'Sri Panchami Spiritual') ?>"></label>
        </div>
    </section>

    <section class="panel">
        <h2>Store Defaults</h2>
        <div class="form-grid">
            <label>Shipping mode <input name="shipping_mode" value="<?= e($settings['shipping_mode'] ?? 'free') ?>"></label>
            <label>Flat rate <input type="number" step="0.01" name="flat_rate" value="<?= e((string)($settings['flat_rate'] ?? 0)) ?>"></label>
            <label>Currency <input name="currency" value="<?= e($settings['currency'] ?? 'INR') ?>"></label>
            <label>Timezone <input name="timezone" value="<?= e($settings['timezone'] ?? 'Asia/Kolkata') ?>"></label>
        </div>
    </section>

    <button>Save settings</button>
</form>

<form method="post" action="/admin/settings/test-smtp" class="panel smtp-test-form">
    <h2>Verify SMTP</h2>
    <label>Send test email to <input type="email" name="test_email" value="<?= e($settings['admin_notification_email'] ?: ($settings['smtp_from_email'] ?? '')) ?>" required></label>
    <button>Send SMTP test</button>
</form>
