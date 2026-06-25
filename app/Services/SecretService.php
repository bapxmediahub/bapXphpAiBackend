<?php
namespace App\Services;
final class SecretService {
    private string $file;
    private string $keyFile;
    public function __construct() { $this->file = storage_path('data/settings.secrets.json'); $this->keyFile = storage_path('runtime-key.php'); }
    public function all(): array {
        $env = $this->environmentSecrets();
        if (!is_file($this->file)) return $this->normalize($env);
        $payload = json_decode(file_get_contents($this->file), true) ?: [];
        if (!$payload) return $this->normalize($env);
        $raw = base64_decode($payload['ciphertext']);
        $plain = openssl_decrypt($raw, 'aes-256-cbc', $this->key(), OPENSSL_RAW_DATA, base64_decode($payload['iv']));
        $stored = $plain ? (json_decode($plain, true) ?: []) : [];
        return $this->normalize(array_replace($stored, array_filter($env, fn($value) => $value !== '')));
    }
    public function save(array $values): void {
        $iv = random_bytes(16);
        $cipher = openssl_encrypt(json_encode($this->normalize($values)), 'aes-256-cbc', $this->key(), OPENSSL_RAW_DATA, $iv);
        file_put_contents($this->file, json_encode(['iv'=>base64_encode($iv),'ciphertext'=>base64_encode($cipher)], JSON_PRETTY_PRINT));
    }
    private function key(): string {
        if (!is_file($this->keyFile)) file_put_contents($this->keyFile, '<?php return ' . var_export(bin2hex(random_bytes(32)), true) . ';');
        return hex2bin(require $this->keyFile);
    }

    private function environmentSecrets(): array {
        return [
            'razorpay_mode' => (string)(getenv('RAZORPAY_MODE') ?: ''),
            'razorpay_test_key_id' => (string)(getenv('RAZORPAY_TEST_KEY_ID') ?: ''),
            'razorpay_test_key_secret' => (string)(getenv('RAZORPAY_TEST_KEY_SECRET') ?: ''),
            'razorpay_live_key_id' => (string)(getenv('RAZORPAY_LIVE_KEY_ID') ?: ''),
            'razorpay_live_key_secret' => (string)(getenv('RAZORPAY_LIVE_KEY_SECRET') ?: ''),
            'razorpay_key_id' => (string)(getenv('RAZORPAY_KEY_ID') ?: ''),
            'razorpay_key_secret' => (string)(getenv('RAZORPAY_KEY_SECRET') ?: ''),
            'meta_pixel_id' => (string)(getenv('META_PIXEL_ID') ?: ''),
            'google_analytics_id' => (string)(getenv('GOOGLE_ANALYTICS_ID') ?: ''),
            'google_ads_id' => (string)(getenv('GOOGLE_ADS_ID') ?: ''),
            'google_site_verification' => (string)(getenv('GOOGLE_SITE_VERIFICATION') ?: ''),
            'seo_site_name' => (string)(getenv('SEO_SITE_NAME') ?: ''),
            'seo_default_og_image' => (string)(getenv('SEO_DEFAULT_OG_IMAGE') ?: ''),
            'seo_twitter_handle' => (string)(getenv('SEO_TWITTER_HANDLE') ?: ''),
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
            if ($mode === 'live' && $liveId === '' && $liveSecret === '') {
                $liveId = $legacyId;
                $liveSecret = $legacySecret;
            } elseif ($mode === 'test' && $testId === '' && $testSecret === '') {
                $testId = $legacyId;
                $testSecret = $legacySecret;
            }
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
