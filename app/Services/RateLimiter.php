<?php
namespace App\Services;
final class RateLimiter {
    private string $file;
    private array $data;
    public function __construct() {
        $this->file = storage_path('logs/rate_limit.php');
        $this->data = is_file($this->file) ? require $this->file : [];
    }
    public function check(string $key, int $maxAttempts = 5, int $windowSeconds = 60): bool {
        $now = time();
        $this->clean($key, $now, $windowSeconds);
        $attempts = $this->data[$key] ?? [];
        return count($attempts) < $maxAttempts;
    }
    public function hit(string $key): void {
        $now = time();
        $this->data[$key][] = $now;
        $this->save();
    }
    public function getRemaining(string $key, int $maxAttempts = 5, int $windowSeconds = 60): int {
        $now = time();
        $this->clean($key, $now, $windowSeconds);
        $attempts = $this->data[$key] ?? [];
        return max(0, $maxAttempts - count($attempts));
    }
    private function clean(string $key, int $now, int $windowSeconds): void {
        if (isset($this->data[$key])) {
            $this->data[$key] = array_values(array_filter($this->data[$key], fn($ts) => $ts > $now - $windowSeconds));
            if (empty($this->data[$key])) unset($this->data[$key]);
        }
    }
    private function save(): void {
        $content = '<?php return ' . var_export($this->data, true) . ';';
        $dir = dirname($this->file);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        file_put_contents($this->file, $content, LOCK_EX);
    }
}
