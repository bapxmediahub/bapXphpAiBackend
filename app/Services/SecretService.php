<?php
namespace App\Services;
final class SecretService {
    public function razorpayReadyForCurrentHost(array $secrets): bool {
        if (empty($secrets['razorpay_key_id']) || empty($secrets['razorpay_key_secret'])) return false;
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? parse_url((string)(getenv('APP_URL') ?: ''), PHP_URL_HOST) ?? ''));
        $isLocal = $host === '' || str_starts_with($host, 'localhost') || str_starts_with($host, '127.0.0.1');
        return $isLocal || ($secrets['razorpay_mode'] ?? '') === 'live';
    }
    public function all(): array {
        $env = $this->envSecrets();
        try {
            $db = new DatabaseService();
            $rows = $db->read('secrets');
            usort($rows, fn(array $a, array $b): int => ($a['id'] === 'app_secrets' ? 1 : 0) <=> ($b['id'] === 'app_secrets' ? 1 : 0));
            $stored = array_filter($env, fn($value) => $value !== '');
            foreach ($rows as $r) {
                unset($r['id']);
                $stored = array_merge($stored, array_filter($r, fn($value) => $value !== ''));
            }
            return $this->normalize($stored);
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
    public function getModelConfig(): array {
        $secrets = $this->all();
        $apiKey = trim((string)($secrets['google_api_key'] ?? $secrets['support_bot_google_api_key'] ?? ''));
        $model = trim((string)($secrets['model'] ?? $secrets['support_bot_model'] ?? 'gemini-2.5-flash'));
        $provider = 'google';
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/';
        if (str_contains($model, 'claude') || str_contains($model, 'opus') || str_contains($model, 'sonnet')) {
            $provider = 'anthropic';
            $endpoint = 'https://api.anthropic.com/v1/messages';
        } elseif (str_contains($model, 'gpt') || str_contains($model, 'o1') || str_contains($model, 'o3')) {
            $provider = 'openai';
            $endpoint = 'https://api.openai.com/v1/chat/completions';
        }
        return compact('provider', 'model', 'endpoint', 'apiKey');
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
            'google_api_key' => (string)(getenv('GOOGLE_API_KEY') ?: ''),
            'model' => (string)(getenv('MODEL') ?: ''),
            'support_bot_purge_policy' => (string)(getenv('SUPPORT_BOT_PURGE_POLICY') ?: ''),
            'remote_db_token' => (string)(getenv('BAPX_REMOTE_DB_TOKEN') ?: ''),
            'turn_server_url' => (string)(getenv('TURN_SERVER_URL') ?: ''),
            'turn_username' => (string)(getenv('TURN_USERNAME') ?: ''),
            'turn_credential' => (string)(getenv('TURN_CREDENTIAL') ?: ''),
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
