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
            usort($rows, fn(array $a, array $b): int => (($a['id'] ?? '') === 'app_secrets' ? 1 : 0) <=> (($b['id'] ?? '') === 'app_secrets' ? 1 : 0));
            $stored = [];
            foreach ($rows as $r) {
                $stored = array_merge($stored, $this->decodeRecord($r));
            }
            return $this->normalize(array_replace(array_filter($env, fn($value) => $value !== ''), $stored));
        } catch (\Throwable) {
            return $this->normalize($env);
        }
    }
    public function save(array $values): void {
        $db = new DatabaseService();
        $iv = random_bytes(16);
        $plain = json_encode($this->normalize($values), JSON_THROW_ON_ERROR);
        $cipher = openssl_encrypt($plain, 'aes-256-cbc', $this->key(), OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) throw new \RuntimeException('Unable to encrypt integration settings.');
        $record = [
            'id' => 'app_secrets',
            'iv' => base64_encode($iv),
            'ciphertext' => base64_encode($cipher),
        ];
        $db->upsert('secrets', $record, 'id');
    }
    public function saveSecret(string $key, string $value): void {
        $all = $this->all();
        $all[$key] = $value;
        $this->save($all);
    }
    public function getModelConfig(): array {
        $secrets = $this->all();
        $endpoint = $this->firstValue($secrets, ['api_endpoint'], ['BAPX_AI_ENDPOINT']);
        $apiKey = $this->firstValue($secrets, ['agent_api_key', 'support_bot_google_api_key'], ['AGENT_API_KEY', 'BAPX_AI_API_KEY']);
        $model = $this->firstValue($secrets, ['agent_model', 'support_bot_model'], ['AGENT_MODEL', 'BAPX_AI_MODEL']);
        if ($model === '') $model = 'gemma-4-31b-it';
        if ($endpoint === '') {
            if (str_contains($model, 'gemini') || str_contains($model, 'gemma')) {
                $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/';
            } else {
                $endpoint = 'https://api.openai.com/v1';
            }
        }
        $provider = 'openai';
        if (str_contains($endpoint, 'googleapis')) $provider = 'google';
        elseif (str_contains($endpoint, 'anthropic')) $provider = 'anthropic';
        return compact('provider', 'model', 'endpoint', 'apiKey') + ['configured' => $apiKey !== ''];
    }
    private function decodeRecord(array $record): array {
        $iv = base64_decode((string)($record['iv'] ?? ''), true);
        $cipher = base64_decode((string)($record['ciphertext'] ?? ''), true);
        if ($iv !== false && $cipher !== false && $iv !== '' && $cipher !== '') {
            $plain = openssl_decrypt($cipher, 'aes-256-cbc', $this->key(), OPENSSL_RAW_DATA, $iv);
            if ($plain === false) throw new \RuntimeException('Unable to decrypt integration settings.');
            $decoded = json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        }
        unset($record['id'], $record['iv'], $record['ciphertext']);
        return array_filter($record, fn($value) => $value !== '');
    }
    private function key(): string {
        $configured = trim((string)(getenv('SECRET_STORAGE_KEY') ?: ''));
        if ($configured === '') {
            $legacy = storage_path('runtime-key.php');
            if (is_file($legacy)) $configured = (string)require $legacy;
        }
        if ($configured === '') {
            $db = require app_path('config/database.php');
            $configured = trim((string)($db['remote_db_password'] ?? ''));
            if ($configured === '') $configured = trim((string)($db['pass'] ?? ''));
        }
        if ($configured === '') throw new \RuntimeException('Secret storage key is not configured.');
        return hash('sha256', $configured, true);
    }
    private function firstValue(array $values, array $keys, array $envKeys): string {
        foreach ($keys as $key) {
            $value = trim((string)($values[$key] ?? ''));
            if ($value !== '') return $value;
        }
        foreach ($envKeys as $key) {
            $value = trim((string)(getenv($key) ?: ''));
            if ($value !== '') return $value;
        }
        return '';
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
            'agent_api_key' => (string)(getenv('AGENT_API_KEY') ?: getenv('SUPPORT_BOT_GOOGLE_API_KEY') ?: ''),
            'agent_model' => (string)(getenv('AGENT_MODEL') ?: getenv('SUPPORT_BOT_MODEL') ?: ''),
            'api_endpoint' => (string)(getenv('BAPX_AI_ENDPOINT') ?: ''),
            'support_bot_purge_policy' => (string)(getenv('SUPPORT_BOT_PURGE_POLICY') ?: ''),
            'remote_db_password' => (string)(getenv('REMOTE_DB_PASSWORD') ?: ''),
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
