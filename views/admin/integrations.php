<div class="admin-header">
    <h1>Integrations</h1>
    <a href="/admin" class="btn btn-sm btn-ghost">← Dashboard</a>
</div>
<?php if(!empty($_SESSION['flash'])): ?><div class="flash flash--success" style="margin-bottom:var(--space-lg);"><?= e($_SESSION['flash']); unset($_SESSION['flash']); ?></div><?php endif; ?>
<div class="admin-card" style="border-left:4px solid var(--color-gold);">
    <h2>🔐 API Keys</h2>
    <p>Secrets are encrypted at rest using AES-256-CBC. Never share these values.</p>
</div>
<div class="admin-card">
    <form method="post" action="/admin/integrations/save" class="admin-form">
        <h2 style="margin-bottom:var(--space-lg);">Google OAuth</h2>
        <div class="admin-form__row">
            <label>Google Client ID<input name="google_client_id" value="<?= e($secrets['google_client_id']??'') ?>" placeholder="xxxx.apps.googleusercontent.com"></label>
            <label>Google Client Secret<input name="google_client_secret" value="<?= e($secrets['google_client_secret']??'') ?>" placeholder="GOCSPX-xxxx"></label>
        </div>
        <h2 style="margin:var(--space-xl) 0 var(--space-lg);">Razorpay Payments</h2>
        <div class="admin-form__row">
            <label>Razorpay Key ID<input name="razorpay_key_id" value="<?= e($secrets['razorpay_key_id']??'') ?>" placeholder="rzp_live_xxxx"></label>
            <label>Razorpay Key Secret<input name="razorpay_key_secret" value="<?= e($secrets['razorpay_key_secret']??'') ?>" placeholder="••••••••"></label>
        </div>
        <button class="btn btn-primary" style="margin-top:var(--space-lg);">Save Integrations</button>
    </form>
</div>
