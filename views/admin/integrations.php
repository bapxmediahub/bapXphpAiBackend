<div class="admin-card" style="border-left:4px solid var(--color-gold); margin-bottom:var(--space-lg);">
    <h2 style="font-size:1rem; margin:0 0 var(--space-sm);"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg> API Setup</h2>
    <p style="margin:0; color:var(--color-text-muted); font-size:0.9rem;">These settings are for the website owner only. Customers will only see shop, booking, text session, and direct call session screens.</p>
</div>
<div class="admin-card">
    <form method="post" action="/admin/integrations/save" class="admin-form">
        <h2 style="font-size:1rem; margin:0 0 var(--space-sm);">Razorpay Payments</h2>
        <p style="margin:0 0 var(--space-md); color:var(--color-text-muted); font-size:0.85rem;">
            Add the live Key ID and Key Secret from Razorpay Dashboard. Use this for ecommerce payments and credit top-ups.
            <a href="https://razorpay.com/docs/payments/dashboard/account-settings/api-keys/" target="_blank" rel="noopener">Razorpay API key guide</a>
        </p>
        <div class="admin-form__row">
            <label>Razorpay Key ID<input name="razorpay_key_id" value="<?= e($secrets['razorpay_key_id']??'') ?>" placeholder="rzp_live_xxxx"></label>
            <label>Razorpay Key Secret<input name="razorpay_key_secret" value="<?= e($secrets['razorpay_key_secret']??'') ?>" placeholder="Paste live key secret"></label>
        </div>
        <p style="margin:var(--space-xs) 0 0; color:var(--color-text-muted); font-size:0.8rem;">Keep test keys for testing only. Switch to live keys before accepting real customer payments.</p>

        <h2 style="font-size:1rem; margin:var(--space-xl) 0 var(--space-sm);">Google Login</h2>
        <p style="margin:0 0 var(--space-md); color:var(--color-text-muted); font-size:0.85rem;">
            Optional customer login. Create an OAuth client in Google Cloud and add this callback URL: <code><?= e(((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'your-domain.com') . '/auth/google/callback') ?></code>.
            <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">Google Credentials</a>
        </p>
        <div class="admin-form__row">
            <label>Google Client ID<input name="google_client_id" value="<?= e($secrets['google_client_id']??'') ?>" placeholder="xxxx.apps.googleusercontent.com"></label>
            <label>Google Client Secret<input name="google_client_secret" value="<?= e($secrets['google_client_secret']??'') ?>" placeholder="GOCSPX-xxxx"></label>
        </div>
        <p style="margin:var(--space-xs) 0 0; color:var(--color-text-muted); font-size:0.8rem;">Only sign-in permissions are used. Calendar and Google Meet are not used for this platform.</p>

        <h2 style="font-size:1rem; margin:var(--space-xl) 0 var(--space-sm);">Support Bot</h2>
        <p style="margin:0 0 var(--space-md); color:var(--color-text-muted); font-size:0.85rem;">
            Configure the Google AI API for a helper bot that can guide customers to products, explain how to use products, help add items to cart, or suggest booking a text or direct call session with an astrologer.
            <a href="https://ai.google.dev/gemini-api/docs/api-key" target="_blank" rel="noopener">Google AI API key guide</a>
        </p>
        <div class="admin-form__row">
            <label>Google API Key<input name="support_bot_google_api_key" value="<?= e($secrets['support_bot_google_api_key']??'') ?>" placeholder="AIza..."></label>
            <label>Model<input name="support_bot_model" value="<?= e($secrets['support_bot_model']??'gemma-4-31b-it') ?>" placeholder="gemma-4-31b-it"></label>
        </div>
        <input type="hidden" name="support_bot_purge_policy" value="always_purge">
        <p style="margin:var(--space-xs) 0 0; color:var(--color-text-muted); font-size:0.8rem;">The app builds the endpoint automatically from the model: <code>https://generativelanguage.googleapis.com/v1beta/models/<?= e($secrets['support_bot_model']??'gemma-4-31b-it') ?>:generateContent</code>. Google documents API-key authentication with the <code>x-goog-api-key</code> header. Free API access is subject to Google account, region, model, and rate-limit rules; it should not be treated as unlimited.</p>
        <p style="margin:var(--space-xs) 0 0; color:var(--color-text-muted); font-size:0.8rem;">Privacy mode: <strong>always_purge</strong>. Bot task data and conversation scratch data should be deleted after the support task finishes.</p>

        <div class="admin-card" style="background:var(--color-bg-alt); margin-top:var(--space-xl); padding:var(--space-md);">
            <h3 style="font-size:0.9rem; margin:0 0 var(--space-sm);">Platform Scope</h3>
            <p style="margin:0; color:var(--color-text-muted); font-size:0.85rem;">This site is ecommerce plus direct astrology services. It supports product sales, text sessions, and direct call sessions. Video calls, Google Meet, and Google Calendar setup are intentionally skipped.</p>
        </div>
        <button class="btn btn-primary" style="margin-top:var(--space-lg);">Save Integrations</button>
    </form>
</div>
