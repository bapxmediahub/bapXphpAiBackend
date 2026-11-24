<div class="admin-card" style="border-left:4px solid var(--color-gold); margin-bottom:var(--space-lg);">
    <h2 style="font-size:1rem; margin:0 0 var(--space-sm);"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg> API Setup</h2>
    <p style="margin:0; color:var(--color-text-muted); font-size:0.9rem;">These settings are for the website owner only. Customers use the shop, product pages, cart, account, temple guides, and editorial content. Public consultation booking is retired. All site secrets (payments, email, analytics, and AI) are stored encrypted in the project secret store and managed from this page &mdash; they are never kept in <code>.env</code>.</p>
    <p><a href="/admin/mcp-clients">Manage local MCP clients</a> that can read paid-sales totals.</p>
</div>
<div class="admin-card">
    <form method="post" action="/admin/integrations/save" class="admin-form">
        <!-- Keep the implicit Enter-key submitter a full save, not the AI-only action. -->
        <button type="submit" hidden tabindex="-1" aria-hidden="true"></button>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

        <div style="margin:0 0 var(--space-lg); padding:var(--space-sm) var(--space-md); border-radius:var(--radius-md); border:1px solid var(--color-border); background:var(--color-bg-alt); font-size:0.85rem; color:var(--color-text-muted);">
            <strong style="color:var(--color-ink);">Database access</strong><br>
            The <code>/remotedb</code> endpoint authenticates with your MySQL password from <code>.env</code>.
            There is nothing to configure here, and no separate token to keep in sync.
        </div>

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
            <label>Test checkout webhook secret<input type="password" name="razorpay_test_webhook_secret" value="<?= e($secrets['razorpay_test_webhook_secret']??'') ?>" autocomplete="new-password"><small>Use the same secret on the Razorpay test webhook for payment_link.paid, payment_link.partially_paid, payment_link.expired and payment_link.cancelled.</small></label>
        </div>
        <div class="admin-form__row">
            <label>Live Key ID<input name="razorpay_live_key_id" value="<?= e($secrets['razorpay_live_key_id']??'') ?>" placeholder="rzp_live_xxxx"></label>
            <label>Live Key Secret<input name="razorpay_live_key_secret" value="<?= e($secrets['razorpay_live_key_secret']??'') ?>" placeholder="Paste live key secret"></label>
            <label>Live checkout webhook secret<input type="password" name="razorpay_live_webhook_secret" value="<?= e($secrets['razorpay_live_webhook_secret']??'') ?>" autocomplete="new-password"><small>Checkout completion endpoint: <code>/api/payments/razorpay-webhook</code>. Configure the matching live webhook before enabling chat payment. No test or live payment is created by saving these settings.</small></label>
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

        <h2 id="google-oauth" style="font-size:1rem; margin:var(--space-xl) 0 var(--space-sm);">Google OAuth</h2>
        <p style="margin:0 0 var(--space-md); color:var(--color-text-muted); font-size:0.85rem;">
            Optional customer login. Create an OAuth client in Google Cloud and add this callback URL: <code><?= e($this->siteUrl('/auth/google/callback')) ?></code>.
            <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">Google Credentials</a>
        </p>
        <div class="admin-form__row">
            <label>Google Client ID<input name="google_client_id" value="<?= e($secrets['google_client_id']??'') ?>" placeholder="xxxx.apps.googleusercontent.com"></label>
            <label>Google Client Secret<input type="password" autocomplete="new-password" name="google_client_secret" value="<?= e($secrets['google_client_secret']??'') ?>" placeholder="GOCSPX-xxxx"></label>
        </div>
        <p style="margin:var(--space-xs) 0 0; color:var(--color-text-muted); font-size:0.8rem;">Customer login requests sign-in permissions. The owner authorizes reporting separately. Calendar and Google Meet are not used for this platform.</p>

        <section id="ai-providers" class="ai-provider-section" aria-labelledby="ai-provider-heading">
        <h2 id="ai-provider-heading">AI Providers and Models</h2>
        <?php
        $googleModels=\App\Services\SecretService::googleModelIds($secrets);
        $googleDisabled=(array)($secrets['ai_google_disabled_models']??[]);
        $selectedProvider=($secrets['ai_provider']??'')==='openai_compatible'?'openai_compatible':'google';
        $googleEnabled=($secrets['ai_google_enabled']??(($selectedProvider==='google'||($secrets['ai_openrouter_enabled']??'0')==='1')?'1':'0'))==='1';
        $compatibleEnabled=($secrets['ai_compatible_enabled']??($selectedProvider==='openai_compatible'?'1':'0'))==='1';
        $googleKey='';
        foreach(['ai_api_key','agent_api_key','support_bot_google_api_key'] as $keyField){
            if(trim((string)($secrets[$keyField]??''))!==''){$googleKey=(string)$secrets[$keyField];break;}
        }
        ?>
        <p>Both agents use enabled connections. The preferred connection runs first, the other enabled connection next, and OpenRouter last. Google model IDs run in their listed order. Each model receives up to three attempts on a transient failure before switching; after the final fallback, a second cycle starts from the first eligible model, within the shared deadline. An interrupted tool exchange stays on its original model.</p>
        <div class="admin-form__row">
            <label>Preferred fallback provider
                <select name="ai_provider">
                    <option value="google" <?= $selectedProvider==='google'?'selected':'' ?>>Google Gemini API</option>
                    <option value="openai_compatible" <?= $selectedProvider==='openai_compatible'?'selected':'' ?>>OpenAI-compatible API</option>
                </select>
            </label>
            <label>Agent Name<input name="agent_name" value="<?= e($secrets['agent_name']??'Agent') ?>"></label>
        </div>
        <fieldset class="ai-openrouter-settings">
            <legend>Google Gemini API</legend>
            <input type="hidden" name="ai_google_enabled" value="0">
            <label class="ai-openrouter-toggle"><input type="checkbox" name="ai_google_enabled" value="1" <?= $googleEnabled?'checked':'' ?>>Enable Google models</label>
            <p class="ai-provider-endpoint"><strong>API endpoint</strong> <code><?= e(\App\Services\SecretService::GOOGLE_ENDPOINT) ?></code></p>
            <label>Google API Key<input type="password" name="ai_api_key" value="" placeholder="Enter or replace API key" autocomplete="new-password"></label>
            <small role="status"><?= e(\App\Services\SecretService::savedKeyLabel($googleKey)) ?></small>
            <label>Google model IDs in fallback order<textarea name="ai_google_models" rows="3" spellcheck="false"><?= e(implode("\n",$googleModels)) ?></textarea></label>
            <small>Enter one to five exact model IDs, one per line. Save to add new IDs. Disable individual models below without removing them.</small>
            <div class="ai-model-switches">
                <?php foreach($googleModels as $id): ?>
                <input type="hidden" name="ai_google_model_switches[<?= e($id) ?>]" value="0">
                <label class="ai-openrouter-toggle"><input type="checkbox" name="ai_google_model_switches[<?= e($id) ?>]" value="1" <?= !in_array($id,$googleDisabled,true)?'checked':'' ?>><?= e($id) ?></label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <fieldset class="ai-openrouter-settings" aria-describedby="openrouter-free-help">
            <legend>OpenRouter</legend>
            <input type="hidden" name="ai_openrouter_enabled" value="0">
            <label class="ai-openrouter-toggle" for="ai-openrouter-enabled">
                <input type="checkbox" id="ai-openrouter-enabled" name="ai_openrouter_enabled" value="1" <?= ($secrets['ai_openrouter_enabled']??'0')==='1'?'checked':'' ?>>Enable OpenRouter Free
            </label>
            <p id="openrouter-free-help">Use a standard OpenRouter inference API key, not a management API key. Choose the free router or specific model IDs. Free models may have usage limits. Other IDs may incur charges.</p>
            <p class="ai-provider-endpoint"><strong>API endpoint</strong> <code><?= e(\App\Services\SecretService::OPENROUTER_ENDPOINT) ?></code></p>
            <label for="ai-openrouter-key">OpenRouter API Key</label>
            <input id="ai-openrouter-key" type="password" name="ai_openrouter_key" value="" placeholder="Enter or replace API key" autocomplete="new-password" minlength="16" maxlength="512" spellcheck="false">
            <small id="ai-openrouter-key-status" role="status"><?= e(\App\Services\SecretService::savedKeyLabel((string)($secrets['ai_openrouter_key']??''))) ?></small>
            <label>OpenRouter model IDs in fallback order<textarea name="ai_openrouter_models" rows="3" spellcheck="false"><?= e($secrets['ai_openrouter_models']??\App\Services\SecretService::OPENROUTER_MODEL) ?></textarea></label>
            <small>Keep openrouter/free for automatic free-model selection, or enter exact IDs of models you authorize.</small>
        </fieldset>
        <fieldset class="ai-openrouter-settings">
            <legend>Custom OpenAI-compatible connection</legend>
            <input type="hidden" name="ai_compatible_enabled" value="0">
            <label class="ai-openrouter-toggle"><input type="checkbox" name="ai_compatible_enabled" value="1" <?= $compatibleEnabled?'checked':'' ?>>Enable custom connection</label>
            <label>API Base URL<input type="url" name="ai_compatible_url" value="<?= e($secrets['ai_compatible_url']??'') ?>" placeholder="https://api.openai.com/v1"></label>
            <label>Compatible API Key<input type="password" name="ai_compatible_key" value="" placeholder="Enter or replace API key" autocomplete="new-password"></label>
            <small role="status"><?= e(\App\Services\SecretService::savedKeyLabel((string)($secrets['ai_compatible_key']??''))) ?></small>
            <label>Model IDs in fallback order<textarea name="ai_compatible_models" rows="3" aria-describedby="compatible-model-help" spellcheck="false"><?= e($secrets['ai_compatible_models']??'') ?></textarea></label>
            <p id="compatible-model-help">Enter one to five exact model IDs, one per line. Use a key issued for this HTTPS endpoint. Changing the endpoint requires its own key.</p>
        </fieldset>
        <button type="submit" name="save_ai_only" value="1" class="btn btn-primary">Save AI Provider Settings</button>
        </section>
        <input type="hidden" name="support_bot_purge_policy" value="always_purge">

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

        <?php $googleTagStatus=(new \App\Integrations\GoogleSiteKit\GoogleSiteKitClient(
            (string)($secrets['google_analytics_id']??''),(string)($secrets['google_ads_id']??''),(string)($secrets['google_site_verification']??'')))->status(); ?>
        <section id="google-tags" aria-labelledby="google-tags-title">
        <h2 id="google-tags-title" style="font-size:1rem; margin:var(--space-xl) 0 var(--space-sm);">Google tags — configuration only</h2>
        <p>These fields place supported Google tags on public pages. They do not connect reporting OAuth, verify Search Console ownership or enable Merchant Center/YouTube Shopping.</p>
        <div class="admin-form__row">
            <label>GA4 Measurement ID / Google tag ID<input name="google_analytics_id" value="<?= e($secrets['google_analytics_id']??'') ?>" placeholder="G-XXXXXXXXXX" maxlength="67" spellcheck="false"><small>Use G- or an existing GT- ID, not a script or GTM container.</small></label>
            <label>Google Ads conversion ID<input name="google_ads_id" value="<?= e($secrets['google_ads_id']??'') ?>" placeholder="AW-123456789" maxlength="23" spellcheck="false"><small>AW- followed by digits; an event conversion label is not a tag ID.</small></label>
        </div>
        <div class="admin-form__row">
            <label>Verification token<input name="google_site_verification" value="<?= e($secrets['google_site_verification']??'') ?>" placeholder="Content value from the verification meta tag" maxlength="512" spellcheck="false"><small>Paste only the content token, not the complete meta tag or google-site-verification= DNS assignment.</small></label>
        </div>
        <ul aria-label="Google configuration status" style="padding-left:1.25rem;line-height:1.7">
        <?php foreach(['analytics'=>'Analytics tag','ads'=>'Ads tag','search_console'=>'Verification token'] as $key=>$label): $state=$googleTagStatus[$key]['configuration']; ?>
            <li data-google-setting="<?= e($key) ?>" data-google-configuration="<?= e($state) ?>"><?= e($label) ?>: <?= e(['missing'=>'not configured','invalid'=>'invalid saved value; it is not rendered','configured'=>'valid format; external verification still required'][$state]) ?></li>
        <?php endforeach; ?>
        </ul>
        <p><a href="/admin/google-reporting">Connect or manage Google reporting</a> to select a GA4 property and Search Console site. Use <a href="https://tagassistant.google.com/" target="_blank" rel="noopener noreferrer">Tag Assistant</a> to inspect tag collection.</p>
        </section>

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
            Transactional email is sent through the saved SMTP mailbox. Credentials are stored in the project secret store. SMTP is required; failed delivery remains visible in Email Outbox for diagnosis and retry.
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
            <label>SMTP Username<input name="smtp_username" value="<?= e($secrets['smtp_username']??'') ?>" placeholder="support@sripanchamispiritual.com"></label>
        </div>
        <div class="admin-form__row">
            <label>SMTP Password<input type="password" name="smtp_password" value="<?= e($secrets['smtp_password']??'') ?>" placeholder="SMTP password" autocomplete="new-password"></label>
            <label>From Email<input name="mail_from_email" value="<?= e($secrets['mail_from_email']??'') ?>" placeholder="support@sripanchamispiritual.com"></label>
        </div>
        <div class="admin-form__row">
            <label>From Name<input name="mail_from_name" value="<?= e($secrets['mail_from_name']??'Sri Panchami Spiritual') ?>" placeholder="Sri Panchami Spiritual"></label>
            <label>Admin Notification Email<input name="admin_notification_email" value="<?= e($secrets['admin_notification_email']??'') ?>" placeholder="sripanchamispiritual@gmail.com"></label>
        </div>
        <p style="margin:var(--space-xs) 0 0; color:var(--color-text-muted); font-size:0.8rem;">
            <strong>Hostinger mailbox:</strong> host <code>smtp.hostinger.com</code>,
            port <code>465</code> with SSL (or <code>587</code> with TLS/STARTTLS).
            <strong>SMTP Username</strong> and <strong>From Email</strong> must both be the
            full mailbox address, e.g. <code>support@sripanchamispiritual.com</code> — providers
            reject a From Email that does not match the authenticated mailbox.
            <strong>Admin Notification Email</strong> is different: it is where <em>you</em> get told
            about new orders and contact enquiries, so it can be a personal inbox such as a Gmail address.
            Customers are always written to from the From Email, and replies come back there.
            Mail is sent immediately when generated — no cron job is involved.
        </p>

        <div class="admin-card" style="background:var(--color-bg-alt); margin-top:var(--space-xl); padding:var(--space-md);">
            <h3 style="font-size:0.9rem; margin:0 0 var(--space-sm);">Platform Scope</h3>
            <p style="margin:0; color:var(--color-text-muted); font-size:0.85rem;">This site is a spiritual-products storefront supported by temple guides and editorial content. Public consultation booking, text sessions, and direct call sessions are retired. Historical service records remain available to the owner in the admin panel.</p>
        </div>
        <button class="btn btn-primary" style="margin-top:var(--space-lg);">Save Integrations</button>
    </form>

    <div class="admin-card" style="margin-top:var(--space-xl);">
        <h2 style="font-size:1rem; margin:0 0 var(--space-sm);">Verify email delivery</h2>
        <p style="margin:0 0 var(--space-md); color:var(--color-text-muted); font-size:0.85rem;">
            Save SMTP settings first. Use the existing Email Templates workspace to send one
            explicitly confirmed, labelled sample through the normal queue. SMTP acceptance
            is not proof of inbox delivery. Do not retry an uncertain result blindly.
        </p>
        <a class="btn btn-secondary" href="/admin/email-templates?template=welcome">Open email test templates</a>
    </div>
</div>

<?php
// Both agents fall back to a canned reply when the provider rejects a call, which is
// right for a customer but left the owner with no way to tell "the model is off" from
// "the model is broken". This asks the provider one real question and prints exactly
// what came back.
$aiTest = $_SESSION['ai_test_result'] ?? null;
unset($_SESSION['ai_test_result']);
?>
<div class="admin-card" style="margin-top:var(--space-lg);">
    <h2 style="font-size:1.1rem; margin:0 0 var(--space-sm);">Test the AI connection</h2>
    <p style="color:var(--color-text-muted); margin:0 0 var(--space-md); font-size:0.9rem;">
        Test saved enabled connections using the same guarded transport as both agents. Text verifies an exact response; the tool test verifies a synthetic read-only round trip without business data.
    </p>
    <?php if ($aiTest): ?>
        <div style="margin-bottom:var(--space-md); padding:var(--space-sm) var(--space-md); border-radius:var(--radius-sm); border:1px solid <?= $aiTest['ok'] ? 'var(--color-success, #15803d)' : 'var(--color-error, #b91c1c)' ?>; background:<?= $aiTest['ok'] ? 'rgba(21,128,61,0.08)' : 'rgba(185,28,28,0.08)' ?>;">
            <strong><?= $aiTest['ok'] ? 'Probe passed' : 'Probe failed' ?></strong>
            <div style="margin-top:var(--space-2xs); font-size:0.82rem; color:var(--color-text-muted);">
                <?= e((string)($aiTest['provider'] ?? '')) ?> · <?= e((string)($aiTest['model'] ?? '')) ?> · <?= e((string)($aiTest['endpoint'] ?? '')) ?>
            </div>
            <div style="margin-top:var(--space-2xs); font-size:0.85rem; white-space:pre-wrap;"><?= e((string)$aiTest['message']) ?></div>
            <?php if (isset($aiTest['tool_calls'])): ?>
                <p>Tool calls completed: <?= (int)$aiTest['tool_calls'] ?></p>
            <?php endif; ?>
            <?php if (!empty($aiTest['timings'])): ?>
                <p>Elapsed from the start of each attempt.</p>
                <ol>
                <?php foreach ($aiTest['timings'] as $timing): ?>
                    <li>
                        <?php if (!empty($timing['model'])): ?><?= e($timing['model']) ?> · <?php endif; ?>
                        HTTP <?= (int)$timing['status'] ?>, cURL <?= (int)$timing['curl_error'] ?>.
                        <?php foreach (['dns_ms' => 'DNS', 'connect_ms' => 'Connection', 'ready_ms' => 'Ready to transfer', 'first_byte_ms' => 'First byte', 'total_ms' => 'Total'] as $key => $label): ?>
                            <?= e($label) ?> <?= ($timing[$key] ?? 0) > 0 ? number_format($timing[$key], 1) . ' ms' : '—' ?>.
                        <?php endforeach; ?>
                    </li>
                <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <form class="admin-form" method="post" action="/admin/integrations/test-ai">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <label for="ai-probe-target">Connection to test</label>
            <select id="ai-probe-target" name="probe">
                <option value="">Enabled fallback route</option>
                <?php foreach (($comparisonModels??[]) as $connection): ?>
                    <option value="<?= e($connection['target']) ?>"><?= e($connection['provider'].' · '.$connection['model']) ?></option>
                <?php endforeach; ?>
            </select>
        <label for="ai-probe-mode">Diagnostic</label>
        <select id="ai-probe-mode" name="probe_mode"><option value="text">Text response</option><option value="tools">Tool round trip</option></select>
        <button class="btn btn-secondary">Test AI Connection</button>
        <small>Tests the saved configuration. A selected provider/model never falls back to another target.</small>
    </form>
</div>
