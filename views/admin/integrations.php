<div class="admin-card" style="border-left:4px solid var(--color-gold); margin-bottom:var(--space-lg);">
    <h2 style="font-size:1rem; margin:0 0 var(--space-sm);"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg> API Setup</h2>
    <p style="margin:0; color:var(--color-text-muted); font-size:0.9rem;">These settings are for the website owner only. Customers will only see shop, booking, text session, and direct call session screens. All site secrets (payments, email, analytics, and AI) are stored encrypted in the project secret store and managed from this page &mdash; they are never kept in <code>.env</code>.</p>
</div>
<div class="admin-card">
    <form method="post" action="/admin/integrations/save" class="admin-form">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

        <h2 style="font-size:1rem; margin:0 0 var(--space-sm);"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg> Remote DB Password</h2>
        <p style="margin:0 0 var(--space-md); color:var(--color-text-muted); font-size:0.85rem;">
            Set a password to protect the <code>/remoteDB</code> endpoint. When set, every remote call must include this password.
            Can also be set via <code>REMOTE_DB_PASSWORD</code> in <code>.env</code>. Leave blank for no password (backward compatible).
        </p>
        <div class="admin-form__row">
            <label>Remote DB Password<input type="password" name="remote_db_password" value="<?= e($secrets['remote_db_password']??'') ?>" placeholder="Leave blank to disable password protection" autocomplete="new-password"></label>
        </div>
        <p style="margin:var(--space-xs) 0 var(--space-lg); color:var(--color-text-muted); font-size:0.8rem;">The <code>/remoteDB</code> endpoint is used by the remote database bridge when MySQL is unreachable directly. Protecting it prevents unauthorized data access or mutation.</p>

        <h2 style="font-size:1rem; margin:0 0 var(--space-sm);">Razorpay Payments</h2>
        <p style="margin:0 0 var(--space-md); color:var(--color-text-muted); font-size:0.85rem;">
            Add test and live keys from Razorpay Dashboard, then choose which mode checkout and wallet top-ups should use.
            <a href="https://razorpay.com/docs/payments/dashboard/account-settings/api-keys/" target="_blank" rel="noopener">Razorpay API key guide</a>
        </p>
        <div class="admin-form__row">
            <label>Razorpay Mode
                <select name="razorpay_mode">
                    <option value="test" <?= (($secrets['razorpay_mode']??'test') === 'test') ? 'selected' : '' ?>>Test mode</option>
                    <option value="live" <?= (($secrets['razorpay_mode']??'test') === 'live') ? 'selected' : '' ?>>Live mode</option>
                </select>
            </label>
            <label>Active Key ID
                <input value="<?= e($secrets['razorpay_key_id']??'') ?>" readonly placeholder="Selected mode key id">
            </label>
        </div>
        <div class="admin-form__row">
            <label>Test Key ID<input name="razorpay_test_key_id" value="<?= e($secrets['razorpay_test_key_id']??'') ?>" placeholder="rzp_test_xxxx"></label>
            <label>Test Key Secret<input name="razorpay_test_key_secret" value="<?= e($secrets['razorpay_test_key_secret']??'') ?>" placeholder="Paste test key secret"></label>
        </div>
        <div class="admin-form__row">
            <label>Live Key ID<input name="razorpay_live_key_id" value="<?= e($secrets['razorpay_live_key_id']??'') ?>" placeholder="rzp_live_xxxx"></label>
            <label>Live Key Secret<input name="razorpay_live_key_secret" value="<?= e($secrets['razorpay_live_key_secret']??'') ?>" placeholder="Paste live key secret"></label>
        </div>
        <p style="margin:var(--space-xs) 0 0; color:var(--color-text-muted); font-size:0.8rem;">Test mode uses test keys for trial payments. Switch to live mode only when production keys are saved and real customer payments are ready.</p>

        <h2 style="font-size:1rem; margin:var(--space-xl) 0 var(--space-sm);">Stripe Payments</h2>
        <p style="margin:0 0 var(--space-md); color:var(--color-text-muted); font-size:0.85rem;">
            Optional Stripe payment gateway. Enter your Stripe secret key to enable Stripe as an alternative payment method.
            <a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener">Stripe Dashboard</a>
        </p>
        <div class="admin-form__row">
            <label>Stripe Secret Key<input type="password" name="stripe_secret_key" value="<?= e($secrets['stripe_secret_key']??'') ?>" placeholder="sk_live_xxxx or sk_test_xxxx" autocomplete="new-password"></label>
        </div>
        <p style="margin:var(--space-xs) 0 0; color:var(--color-text-muted); font-size:0.8rem;">Stripe is available as an alternative to Razorpay. The secret key is stored encrypted and never kept in <code>.env</code>.</p>

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

        <h2 style="font-size:1rem; margin:var(--space-xl) 0 var(--space-sm);">AI Model (Agent + Support Bot)</h2>
        <p style="margin:0 0 var(--space-md); color:var(--color-text-muted); font-size:0.85rem;">
            Configure an OpenAI-compatible API for the site AI agent and the support bot.
            Works with OpenAI, OpenRouter, or any OpenAI-compatible provider.
        </p>
        <div class="admin-form__row">
            <label>Agent Name<input name="agent_name" value="<?= e($secrets['agent_name']??'Agent') ?>" placeholder="Agent"></label>
            <label>API Endpoint (base URL)<input name="api_endpoint" value="<?= e($secrets['api_endpoint']??'') ?>" placeholder="https://api.openai.com/v1"></label>
            <label>API Key<input type="password" name="agent_api_key" value="<?= e($secrets['agent_api_key']??$secrets['support_bot_google_api_key']??'') ?>" placeholder="sk-... or AIza..." autocomplete="new-password"></label>
        </div>
        <div class="admin-form__row">
            <label>Model<input name="agent_model" value="<?= e($secrets['agent_model']??$secrets['support_bot_model']??'gemma-4-31b-it') ?>" placeholder="gemma-4-31b-it"></label>
        </div>
        <input type="hidden" name="support_bot_purge_policy" value="always_purge">
        <p style="margin:var(--space-xs) 0 0; color:var(--color-text-muted); font-size:0.85rem;">
            Current model: <strong><?= e($secrets['agent_model'] ?? $secrets['support_bot_model'] ?? 'gemma-4-31b-it') ?></strong>.
            OpenRouter: endpoint <code>https://openrouter.ai/api/v1</code>.
            Google: endpoint <code>https://generativelanguage.googleapis.com/v1beta/models/</code>.
        </p>

        <h2 style="font-size:1rem; margin:var(--space-xl) 0 var(--space-sm);">WebRTC TURN Server</h2>
        <p style="margin:0 0 var(--space-md); color:var(--color-text-muted); font-size:0.85rem;">
            Optional TURN server for WebRTC call connectivity when peer-to-peer STUN fails (firewall/NAT). Run a coturn server and enter its credentials here.
        </p>
        <div class="admin-form__row">
            <label>TURN Server URL<input name="turn_server_url" value="<?= e($secrets['turn_server_url']??'') ?>" placeholder="turn:example.com:3478"></label>
            <label>TURN Username<input name="turn_username" value="<?= e($secrets['turn_username']??'') ?>" placeholder="turnuser"></label>
        </div>
        <div class="admin-form__row">
            <label>TURN Credential<input type="password" name="turn_credential" value="<?= e($secrets['turn_credential']??'') ?>" placeholder="TURN shared secret" autocomplete="new-password"></label>
        </div>
        <p style="margin:var(--space-xs) 0 0; color:var(--color-text-muted); font-size:0.8rem;">When configured, the TURN server is added to ICE servers for all WebRTC calls. Leave blank to use STUN only.</p>

        <h2 style="font-size:1rem; margin:var(--space-xl) 0 var(--space-sm);">Meta Pixel (Facebook Ads)</h2>
        <p style="margin:0 0 var(--space-md); color:var(--color-text-muted); font-size:0.85rem;">
            Optional Facebook/Meta Ads conversion tracking and retargeting. Enter your Pixel ID to enable Meta tracking across all pages.
            <a href="https://www.facebook.com/events_manager/pixel/" target="_blank" rel="noopener">Meta Events Manager</a>
        </p>
        <div class="admin-form__row">
            <label>Meta Pixel ID<input name="meta_pixel_id" value="<?= e($secrets['meta_pixel_id']??'') ?>" placeholder="1234567890"></label>
        </div>
        <p style="margin:var(--space-xs) 0 0; color:var(--color-text-muted); font-size:0.8rem;">The pixel base code and PageView event will be injected into the site head automatically.</p>

        <h2 style="font-size:1rem; margin:var(--space-xl) 0 var(--space-sm);">Google Site Kit (Analytics, Ads & Search Console)</h2>
        <p style="margin:0 0 var(--space-md); color:var(--color-text-muted); font-size:0.85rem;">
            Enable Google Analytics 4 for SEO insights, Google Ads for conversion tracking, and Search Console verification. Uses a single gtag.js snippet.
            <a href="https://analytics.google.com/" target="_blank" rel="noopener">Google Analytics</a> &middot;
            <a href="https://search.google.com/search-console" target="_blank" rel="noopener">Google Search Console</a>
        </p>
        <div class="admin-form__row">
            <label>GA4 Measurement ID<input name="google_analytics_id" value="<?= e($secrets['google_analytics_id']??'') ?>" placeholder="G-XXXXXXXXXX"></label>
            <label>Google Ads ID<input name="google_ads_id" value="<?= e($secrets['google_ads_id']??'') ?>" placeholder="AW-XXXXXXXXX"></label>
        </div>
        <div class="admin-form__row">
            <label>Search Console Verification<input name="google_site_verification" value="<?= e($secrets['google_site_verification']??'') ?>" placeholder="google-site-verification=xxxxxxxxxx"></label>
        </div>
        <p style="margin:var(--space-xs) 0 0; color:var(--color-text-muted); font-size:0.8rem;">Google Analytics, Ads conversion, and Search Console meta tags are injected into the site head only when configured.</p>

        <h2 style="font-size:1rem; margin:var(--space-xl) 0 var(--space-sm);">SEO Defaults</h2>
        <p style="margin:0 0 var(--space-md); color:var(--color-text-muted); font-size:0.85rem;">
            Configure default SEO metadata used across all pages. These can be overridden per page automatically by the SEO service.
        </p>
        <div class="admin-form__row">
            <label>Site Name<input name="seo_site_name" value="<?= e($secrets['seo_site_name']??'') ?>" placeholder="Sri Panchami Spiritual"></label>
            <label>Twitter Handle<input name="seo_twitter_handle" value="<?= e($secrets['seo_twitter_handle']??'') ?>" placeholder="@sps"></label>
        </div>
        <div class="admin-form__row">
            <label>Default OG Image URL<input name="seo_default_og_image" value="<?= e($secrets['seo_default_og_image']??'') ?>" placeholder="https://example.com/og-image.jpg"></label>
        </div>
        <p style="margin:var(--space-xs) 0 0; color:var(--color-text-muted); font-size:0.8rem;">Each public page automatically gets a unique title, description, and OG tags. Site Name is used in JSON-LD structured data and page titles.</p>

        <h2 style="font-size:1rem; margin:var(--space-xl) 0 var(--space-sm);">Outbound Email (SMTP)</h2>
        <p style="margin:0 0 var(--space-md); color:var(--color-text-muted); font-size:0.85rem;">
            Transactional email (order confirmations, shipment, review requests) is sent through SMTP when configured. Credentials are stored encrypted in the project secret store and are never read from <code>.env</code>. Leave blank to fall back to PHP <code>mail()</code> with a domain-local <code>noreply@</code> sender.
        </p>
        <div class="admin-form__row">
            <label>SMTP Host<input name="smtp_host" value="<?= e($secrets['smtp_host']??'') ?>" placeholder="smtp.hostinger.com"></label>
            <label>SMTP Port<input name="smtp_port" value="<?= e($secrets['smtp_port']??'') ?>" placeholder="465"></label>
        </div>
        <div class="admin-form__row">
            <label>Encryption
                <select name="smtp_encryption">
                    <option value="ssl" <?= (($secrets['smtp_encryption']??'ssl') === 'ssl') ? 'selected' : '' ?>>SSL (465)</option>
                    <option value="tls" <?= (($secrets['smtp_encryption']??'ssl') === 'tls') ? 'selected' : '' ?>>TLS / STARTTLS (587)</option>
                </select>
            </label>
            <label>SMTP Username<input name="smtp_username" value="<?= e($secrets['smtp_username']??'') ?>" placeholder="support@your-domain.com"></label>
        </div>
        <div class="admin-form__row">
            <label>SMTP Password<input type="password" name="smtp_password" value="<?= e($secrets['smtp_password']??'') ?>" placeholder="SMTP password" autocomplete="new-password"></label>
            <label>From Email<input name="mail_from_email" value="<?= e($secrets['mail_from_email']??'') ?>" placeholder="support@your-domain.com"></label>
        </div>
        <div class="admin-form__row">
            <label>From Name<input name="mail_from_name" value="<?= e($secrets['mail_from_name']??'Sri Panchami Spiritual') ?>" placeholder="Sri Panchami Spiritual"></label>
            <label>Admin Notification Email<input name="admin_notification_email" value="<?= e($secrets['admin_notification_email']??'') ?>" placeholder="admin@your-domain.com"></label>
        </div>
        <p style="margin:var(--space-xs) 0 0; color:var(--color-text-muted); font-size:0.8rem;">Use the mailbox created in your hosting control panel (for example Hostinger: <code>smtp.hostinger.com</code>, ports 465 SSL or 587 TLS, authentication required). The cron job <code>cli/process-mail-queue.php</code> reads these settings from the secret store.</p>

        <div class="admin-card" style="background:var(--color-bg-alt); margin-top:var(--space-xl); padding:var(--space-md);">
            <h3 style="font-size:0.9rem; margin:0 0 var(--space-sm);">Platform Scope</h3>
            <p style="margin:0; color:var(--color-text-muted); font-size:0.85rem;">This site is ecommerce plus direct astrology services. It supports product sales, text sessions, and direct call sessions. Video calls, Google Meet, and Google Calendar setup are intentionally skipped.</p>
        </div>
        <button class="btn btn-primary" style="margin-top:var(--space-lg);">Save Integrations</button>
    </form>
</div>
