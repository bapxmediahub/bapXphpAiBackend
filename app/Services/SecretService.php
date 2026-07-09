<?php
namespace App\Services;
final class SecretService {
    public function all(): array {
        $env = $this->envSecrets();
        try {
            $db = new DatabaseService();
            $rows = $db->read('secrets');
            $stored = [];
            foreach ($rows as $r) {
                unset($r['id']);
                $stored = array_merge($stored, $r);
            }
            return $this->normalize(array_merge($stored, array_filter($env, fn($v) => $v !== '')));
        } catch (\Throwable) {
            return $this->normalize($env);
        }
    }
    public function save(array $values): void {
        $db = new DatabaseService();
        $record = $this->normalize($values);
        $record['id'] = 'app_secrets';
        $db->upsert('secrets', $record, 'id');
    }
    public function saveSecret(string $key, string $value): void {
        $all = $this->all();
        $all[$key] = $value;
        $this->save($all);
    }
    private function envSecrets(): array {
        return [
            'google_client_id' => (string)(getenv('GOOGLE_CLIENT_ID') ?: ''),
            'google_client_secret' => (string)(getenv('GOOGLE_CLIENT_SECRET') ?: ''),
            'razorpay_mode' => (string)(getenv('RAZORPAY_MODE') ?: ''),
            'razorpay_test_key_id' => (string)(getenv('RAZORPAY_TEST_KEY_ID') ?: ''),
            'razorpay_test_key_secret' => (string)(getenv('RAZORPAY_TEST_KEY_SECRET') ?: ''),
            'razorpay_live_key_id' => (string)(getenv('RAZORPAY_LIVE_KEY_ID') ?: ''),
            'razorpay_live_key_secret' => (string)(getenv('RAZORPAY_LIVE_KEY_SECRET') ?: ''),
            'razorpay_key_id' => (string)(getenv('RAZORPAY_KEY_ID') ?: ''),
            'razorpay_key_secret' => (string)(getenv('RAZORPAY_KEY_SECRET') ?: ''),
            'stripe_secret_key' => (string)(getenv('STRIPE_SECRET_KEY') ?: ''),
            'meta_pixel_id' => (string)(getenv('META_PIXEL_ID') ?: ''),
            'google_analytics_id' => (string)(getenv('GOOGLE_ANALYTICS_ID') ?: ''),
            'google_ads_id' => (string)(getenv('GOOGLE_ADS_ID') ?: ''),
            'google_site_verification' => (string)(getenv('GOOGLE_SITE_VERIFICATION') ?: ''),
            'seo_site_name' => (string)(getenv('SEO_SITE_NAME') ?: ''),
            'seo_default_og_image' => (string)(getenv('SEO_DEFAULT_OG_IMAGE') ?: ''),
            'seo_twitter_handle' => (string)(getenv('SEO_TWITTER_HANDLE') ?: ''),
            'smtp_host' => (string)(getenv('SMTP_HOST') ?: ''),
            'smtp_port' => (string)(getenv('SMTP_PORT') ?: ''),
            'smtp_encryption' => (string)(getenv('SMTP_ENCRYPTION') ?: ''),
            'smtp_username' => (string)(getenv('SMTP_USERNAME') ?: ''),
            'smtp_password' => (string)(getenv('SMTP_PASSWORD') ?: ''),
            'mail_from_email' => (string)(getenv('MAIL_FROM_EMAIL') ?: ''),
            'mail_from_name' => (string)(getenv('MAIL_FROM_NAME') ?: ''),
            'admin_notification_email' => (string)(getenv('ADMIN_NOTIFICATION_EMAIL') ?: ''),
            'support_bot_google_api_key' => (string)(getenv('SUPPORT_BOT_GOOGLE_API_KEY') ?: ''),
            'support_bot_model' => (string)(getenv('SUPPORT_BOT_MODEL') ?: ''),
            'support_bot_purge_policy' => (string)(getenv('SUPPORT_BOT_PURGE_POLICY') ?: ''),
            'remote_db_token' => (string)(getenv('BAPX_REMOTE_DB_TOKEN') ?: ''),
        ];
    }
    private function normalize(array $values): array {
        $legacyId = trim((string)($values['razorpay_key_id'] ?? ''));
        $legacySecret = trim((string)($values['razorpay_key_secret'] ?? ''));
        $testId = trim((string)($values['razorpay_test_key_id'] ?? ''));
        $testSecret = trim((string)($values['razorpay_test_key_secret'] ?? ''));
        $liveId = trim((string)($values['razorpay_live_key_id'] ?? ''));
        $liveSecret = trim((string)($values['razorpay_live_key_secret'] ?? ''));
        $mode = strtolower(trim((string)($values['razorpay_mode'] ?? '')));
        if (!in_array($mode, ['test', 'live'], true)) {
            $mode = str_starts_with($legacyId, 'rzp_live_') || ($liveId !== '' && $testId === '') ? 'live' : 'test';
        }
        if ($legacyId !== '' || $legacySecret !== '') {
            if ($mode === 'live' && $liveId === '' && $liveSecret === '') { $liveId = $legacyId; $liveSecret = $legacySecret; }
            elseif ($mode === 'test' && $testId === '' && $testSecret === '') { $testId = $legacyId; $testSecret = $legacySecret; }
        }
        $values['razorpay_mode'] = $mode;
        $values['razorpay_test_key_id'] = $testId;
        $values['razorpay_test_key_secret'] = $testSecret;
        $values['razorpay_live_key_id'] = $liveId;
        $values['razorpay_live_key_secret'] = $liveSecret;
        $values['razorpay_key_id'] = $mode === 'live' ? $liveId : $testId;
        $values['razorpay_key_secret'] = $mode === 'live' ? $liveSecret : $testSecret;
        return $values;
    }
}
