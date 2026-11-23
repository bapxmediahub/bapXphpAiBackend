<?php
namespace App\Services;
final class SecretService {
    private const REPORT_GRANT = 'google_reporting_grant';
    public const PRIMARY_MODEL = 'gemma-4-31b-it';
    public const FALLBACK_MODEL = 'gemma-4-26b-a4b-it';
    public const GOOGLE_MODELS = [self::PRIMARY_MODEL, self::FALLBACK_MODEL];
    public const OPENROUTER_ENDPOINT = 'https://openrouter.ai/api/v1';
    public const OPENROUTER_MODEL = 'openrouter/free';
    public const GOOGLE_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct(private ?DatabaseService $store = null) {}
    public function razorpayReadyForCurrentHost(array $secrets): bool {
        return !empty($secrets['razorpay_key_id']) && !empty($secrets['razorpay_key_secret']);
    }
    public function all(): array {
        $env = $this->envSecrets();
        try {
            $db = $this->store ?? new DatabaseService();
            $rows = $db->read('secrets');
            usort($rows, fn(array $a, array $b): int => (($a['id'] ?? '') === 'app_secrets' ? 1 : 0) <=> (($b['id'] ?? '') === 'app_secrets' ? 1 : 0));
            $stored = [];
            foreach ($rows as $r) {
                if (($r['id'] ?? null) === self::REPORT_GRANT || str_starts_with((string)($r['id'] ?? ''), McpClientService::PREFIX)) continue;
                $stored = array_merge($stored, $this->decodeRecord($r));
            }
            return $this->normalize(array_replace(array_filter($env, fn($value) => $value !== ''), $stored));
        } catch (\Throwable) {
            return $this->normalize($env);
        }
    }
    /**
     * Merges into the stored secrets rather than replacing them.
     *
     * The Integrations form does not contain every secret — razorpay_key_id,
     * razorpay_key_secret, ai_api_key, and the model settings are
     * all absent from it. Replacing the whole blob with the posted fields silently
     * destroyed those, so saving SMTP settings wiped the live payment keys and the AI
     * key. Only keys actually present in the submission are changed.
     */
    public function save(array $values): void {
        $db = $this->store ?? new DatabaseService();
        unset($values['_csrf']);
        $existing = [];
        try {
            // Same precedence as all(): app_secrets is merged last so it wins. Without
            // this ordering the stale secrets-primary row overwrote current values on
            // every save — it silently reverted a freshly saved SMTP password.
            $rows = $db->read('secrets');
            usort($rows, fn(array $a, array $b): int => (($a['id'] ?? '') === 'app_secrets' ? 1 : 0) <=> (($b['id'] ?? '') === 'app_secrets' ? 1 : 0));
            foreach ($rows as $row) {
                if (($row['id'] ?? null) === self::REPORT_GRANT || str_starts_with((string)($row['id'] ?? ''), McpClientService::PREFIX)) continue;
                $existing = array_merge($existing, $this->decodeRecord($row));
            }
        } catch (\Throwable) {
            // A read failure must not turn into a wipe: without the current values a
            // merge is impossible, so refuse rather than overwrite with a subset.
            throw new \RuntimeException('Could not read existing settings, so nothing was saved. Try again.');
        }
        foreach (['ai_api_key', 'ai_compatible_key', 'ai_openrouter_key'] as $field) {
            if (array_key_exists('ai_provider', $values) && ($values[$field] ?? null) === '') unset($values[$field]);
        }
        if (isset($values['ai_openrouter_enabled']) && !in_array($values['ai_openrouter_enabled'], ['0','1'], true)) {
            throw new \InvalidArgumentException('Invalid OpenRouter toggle.');
        }
        $merged = array_replace($existing, $values);
        $config = self::compatibleConfig($merged);
        $oldEndpoint = self::compatibleConfig($existing)['endpoint'];
        if ($oldEndpoint !== $config['endpoint'] && !array_key_exists('ai_compatible_key', $values)) {
            unset($merged['ai_compatible_key']);
            $config = self::compatibleConfig($merged);
        }
        if (($merged['ai_provider'] ?? '') === 'openai_compatible') {
            if (!$config['configured']) {
                throw new \InvalidArgumentException('Enter a public HTTPS API URL, an API key and one to five valid model IDs.');
            }
            $merged['ai_compatible_url'] = $config['endpoint'];
            $merged['ai_compatible_models'] = implode("\n", $config['models']);
        }
        $db->upsert('secrets', $this->encryptedRecord('app_secrets', $this->normalize($merged)));
    }

    /** Ordinary integration saves must never copy the OAuth grant into app_secrets. */
    public function googleReporting(): array {
        $record = ($this->store ?? new DatabaseService())->find('secrets', self::REPORT_GRANT);
        return $record ? $this->decodeRecord($record) + ['revision'=>$record['iv']] : [];
    }

    public function saveGoogleGrant(string $token): void {
        $record = $this->encryptedRecord(self::REPORT_GRANT, [
            'refresh_token'=>$token, 'generation'=>bin2hex(random_bytes(16)), 'property'=>'', 'site'=>'',
        ]);
        $result = ($this->store ?? new DatabaseService())->insertIfAbsent('secrets', $record);
        if (!$result['inserted']) throw new \DomainException('Reporting was connected in another session. Reload its settings.');
    }

    public function saveGoogleTargets(array $grant, string $property, string $site): void {
        $revision = $grant['revision'];
        unset($grant['revision']);
        $grant['property'] = $property;
        $grant['site'] = $site;
        $this->changeGoogleGrant($revision, $this->encryptedRecord(self::REPORT_GRANT, $grant));
    }

    /** Google has already revoked this grant. A concurrent property save changes its IV, not its identity. */
    public function clearRevokedGoogleGrant(array $revoked): void {
        $db = $this->store ?? new DatabaseService();
        $revision = $revoked['revision'];
        for ($attempt = 0; $attempt < 5; $attempt++) {
            if ($db->changeGoogleReportingRecord($revision, null)) return;
            $current = $this->googleReporting();
            if (($current['generation'] ?? null) !== $revoked['generation']) return;
            $revision = $current['revision'];
        }
        throw new \DomainException('Google revoked reporting, but settings kept changing. Reload and disconnect to finish clearing the local grant.');
    }

    private function changeGoogleGrant(string $revision, ?array $record): void {
        $changed = ($this->store ?? new DatabaseService())->changeGoogleReportingRecord($revision, $record);
        if (!$changed) throw new \DomainException('The reporting settings changed during this request. Reload and retry.');
    }

    private function encryptedRecord(string $id, array $values): array {
        $iv = random_bytes(16);
        $plain = json_encode($values, JSON_THROW_ON_ERROR);
        $cipher = openssl_encrypt($plain, 'aes-256-cbc', $this->key(), OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) throw new \RuntimeException('Unable to encrypt integration settings.');
        return ['id' => $id, 'iv' => base64_encode($iv), 'ciphertext' => base64_encode($cipher)];
    }
    public function saveSecret(string $key, string $value): void {
        $all = $this->all();
        $all[$key] = $value;
        $this->save($all);
    }
    public function getModelConfig(): array {
        $secrets = $this->all();
        if (($secrets['ai_openrouter_enabled'] ?? '0') === '1') {
            $key = trim((string)($secrets['ai_openrouter_key'] ?? ''));
            if ($key !== '' && !preg_match('/[\r\n]/', $key)) return [
                'provider'=>'openai_compatible', 'endpoint'=>self::OPENROUTER_ENDPOINT,
                'apiKey'=>$key, 'model'=>self::OPENROUTER_MODEL,
                'models'=>[self::OPENROUTER_MODEL], 'configured'=>true,
                'google_fallback'=>true,
            ];
        }
        if (($secrets['ai_provider'] ?? '') === 'openai_compatible') return self::compatibleConfig($secrets);
        $apiKey = $this->firstValue($secrets, ['ai_api_key', 'agent_api_key', 'support_bot_google_api_key'], ['AI_API_KEY', 'AGENT_API_KEY', 'BAPX_AI_API_KEY']);
        $model = $this->firstValue($secrets, ['agent_model', 'support_bot_model'], ['AGENT_MODEL', 'BAPX_AI_MODEL']);
        // The native Google configuration uses the two hosted Gemma 4 models.
        // Google does not publish a Gemma 4 27B Gemini API identifier; the documented
        // smaller hosted model is the 26B A4B variant.
        // https://ai.google.dev/gemma/docs/core/gemma_on_gemini_api#supported-models
        if (!in_array($model, self::GOOGLE_MODELS, true)) $model = self::PRIMARY_MODEL;
        $endpoint = self::GOOGLE_ENDPOINT;
        $provider = 'google';
        return compact('provider', 'model', 'endpoint', 'apiKey') + ['configured' => $apiKey !== ''];
    }
    /** Google is independent of the selected OpenRouter/compatible provider. */
    public function googleModelConfig(): array {
        $secrets=$this->all();
        $key=$this->firstValue($secrets,['ai_api_key','agent_api_key','support_bot_google_api_key'],['AI_API_KEY','AGENT_API_KEY','BAPX_AI_API_KEY']);
        $model=$this->firstValue($secrets,['agent_model','support_bot_model'],['AGENT_MODEL','BAPX_AI_MODEL']);
        if(!in_array($model,self::GOOGLE_MODELS,true))$model=self::PRIMARY_MODEL;
        return ['provider'=>'google','endpoint'=>self::GOOGLE_ENDPOINT,'apiKey'=>$key,'model'=>$model,'configured'=>$key!==''];
    }
    public static function validModelId(string $model): bool {
        return preg_match('~\A[A-Za-z0-9][A-Za-z0-9._:/@+\-]{0,199}\z~', $model) === 1;
    }

    public static function compatibleConfig(array $settings): array {
        $endpoint = rtrim(trim((string)($settings['ai_compatible_url'] ?? '')), '/');
        if (str_ends_with($endpoint, '/chat/completions')) $endpoint = substr($endpoint, 0, -strlen('/chat/completions'));
        $parts = parse_url($endpoint);
        $host = $parts['host'] ?? '';
        $validEndpoint = filter_var($endpoint, FILTER_VALIDATE_URL)
            && ($parts['scheme'] ?? '') === 'https'
            && str_contains($host, '.')
            && !filter_var($host, FILTER_VALIDATE_IP)
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['query']) && !isset($parts['fragment']);
        $models = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string)($settings['ai_compatible_models'] ?? ''))), 'strlen')));
        $validModels = count($models) >= 1 && count($models) <= 5
            && count(array_filter($models, self::validModelId(...))) === count($models);
        $apiKey = trim((string)($settings['ai_compatible_key'] ?? ''));
        $configured = $validEndpoint && $validModels && $apiKey !== '' && !preg_match('/[\r\n]/', $apiKey);
        return [
            'provider' => 'openai_compatible',
            'endpoint' => $validEndpoint ? $endpoint : '',
            'apiKey' => $configured ? $apiKey : '',
            'models' => $validModels ? $models : [],
            'model' => $validModels ? $models[0] : '',
            'configured' => $configured,
        ];
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
            // The database password is the single shared secret. There is no separate
            // remote_db_password or remote_db_token to keep in sync.
            $db = require app_path('config/database.php');
            $configured = trim((string)($db['pass'] ?? ''));
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
            'razorpay_test_webhook_secret' => (string)(getenv('RAZORPAY_TEST_WEBHOOK_SECRET') ?: ''),
            'razorpay_live_key_id' => (string)(getenv('RAZORPAY_LIVE_KEY_ID') ?: ''),
            'razorpay_live_key_secret' => (string)(getenv('RAZORPAY_LIVE_KEY_SECRET') ?: ''),
            'razorpay_live_webhook_secret' => (string)(getenv('RAZORPAY_LIVE_WEBHOOK_SECRET') ?: ''),
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
            'ai_api_key' => (string)(getenv('AI_API_KEY') ?: getenv('AGENT_API_KEY') ?: getenv('SUPPORT_BOT_GOOGLE_API_KEY') ?: ''),
            'agent_model' => (string)(getenv('AGENT_MODEL') ?: getenv('SUPPORT_BOT_MODEL') ?: ''),
            'api_endpoint' => (string)(getenv('BAPX_AI_ENDPOINT') ?: ''),
            'support_bot_purge_policy' => (string)(getenv('SUPPORT_BOT_PURGE_POLICY') ?: ''),
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
