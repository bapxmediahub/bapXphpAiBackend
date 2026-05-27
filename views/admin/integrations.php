<div class="admin-card" style="border-left:4px solid var(--color-gold); margin-bottom:var(--space-lg);">
    <h2 style="font-size:1rem; margin:0 0 var(--space-sm);"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg> API Keys</h2>
    <p style="margin:0; color:var(--color-text-muted); font-size:0.9rem;">Secrets are encrypted at rest using AES-256-CBC. Never share these values.</p>
</div>
<div class="admin-card">
    <form method="post" action="/admin/integrations/save" class="admin-form">
        <h2 style="font-size:1rem; margin:0 0 var(--space-lg);">Google OAuth</h2>
        <div class="admin-form__row">
            <label>Google Client ID<input name="google_client_id" value="<?= e($secrets['google_client_id']??'') ?>" placeholder="xxxx.apps.googleusercontent.com"></label>
            <label>Google Client Secret<input name="google_client_secret" value="<?= e($secrets['google_client_secret']??'') ?>" placeholder="GOCSPX-xxxx"></label>
        </div>
        <h2 style="font-size:1rem; margin:var(--space-xl) 0 var(--space-lg);">Razorpay Payments</h2>
        <div class="admin-form__row">
            <label>Razorpay Key ID<input name="razorpay_key_id" value="<?= e($secrets['razorpay_key_id']??'') ?>" placeholder="rzp_live_xxxx"></label>
            <label>Razorpay Key Secret<input name="razorpay_key_secret" value="<?= e($secrets['razorpay_key_secret']??'') ?>" placeholder="••••••••"></label>
        </div>
        <button class="btn btn-primary" style="margin-top:var(--space-lg);">Save Integrations</button>
    </form>
</div>
