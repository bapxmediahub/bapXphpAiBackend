<?php
namespace App\Services;
final class SecretService {
    private string $file;
    private string $keyFile;
    public function __construct() { $this->file = storage_path('data/settings.secrets.json'); $this->keyFile = storage_path('runtime-key.php'); }
    public function all(): array {
        if (!is_file($this->file)) return [];
        $payload = json_decode(file_get_contents($this->file), true) ?: [];
        if (!$payload) return [];
        $raw = base64_decode($payload['ciphertext']);
        $plain = openssl_decrypt($raw, 'aes-256-cbc', $this->key(), OPENSSL_RAW_DATA, base64_decode($payload['iv']));
        return $plain ? (json_decode($plain, true) ?: []) : [];
    }
    public function save(array $values): void {
        $iv = random_bytes(16);
        $cipher = openssl_encrypt(json_encode($values), 'aes-256-cbc', $this->key(), OPENSSL_RAW_DATA, $iv);
        file_put_contents($this->file, json_encode(['iv'=>base64_encode($iv),'ciphertext'=>base64_encode($cipher)], JSON_PRETTY_PRINT));
    }
    private function key(): string {
        if (!is_file($this->keyFile)) file_put_contents($this->keyFile, '<?php return ' . var_export(bin2hex(random_bytes(32)), true) . ';');
        return hex2bin(require $this->keyFile);
    }
}
