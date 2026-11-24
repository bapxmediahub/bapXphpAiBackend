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
    /**
     * Atomic, deployment-local admission for the public contact endpoint.
     * All budgets succeed together or none is consumed. Store only hashed
     * bucket identifiers and bounded timestamps; do not trust forwarded IPs.
     * The separate lock survives atomic state replacement.
     */
    public static function consumeContact(string $ip, string $email, ?string $path = null, ?int $now = null): bool {
        $path ??= storage_path('logs/contact-admission.json');
        $limits = [
            hash('sha256', 'contact:ip:'.$ip) => [3, 120],
            hash('sha256', 'contact:recipient:'.strtolower(trim($email))) => [2, 86400],
            hash('sha256', 'contact:site:hour') => [20, 3600],
            hash('sha256', 'contact:site:day') => [60, 86400],
        ];
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir))
            throw new \RuntimeException('Contact admission storage unavailable.');
        $lock = @fopen($path.'.lock', 'c');
        if ($lock === false) throw new \RuntimeException('Contact admission lock unavailable.');
        try {
            if (!flock($lock, LOCK_EX)) throw new \RuntimeException('Contact admission lock failed.');
            $now ??= time();
            $data = [];
            if (is_file($path)) {
                $raw = @file_get_contents($path, false, null, 0, 262145);
                if ($raw === false || strlen($raw) > 262144)
                    throw new \RuntimeException('Contact admission state unavailable.');
                $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
                if (!is_array($data)) throw new \RuntimeException('Invalid contact admission state.');
            }
            foreach ($data as $key => $times) {
                if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/D', $key) || !is_array($times) || !array_is_list($times))
                    throw new \RuntimeException('Invalid contact admission bucket.');
                foreach ($times as $ts) {
                    if (!is_int($ts) || $ts > $now) throw new \RuntimeException('Invalid contact admission timestamp.');
                }
                $data[$key] = array_values(array_filter($times, static fn(int $ts): bool => $ts > $now-86400));
                if ($data[$key] === []) unset($data[$key]);
            }
            foreach ($limits as $key => [$max, $window]) {
                $active = array_filter($data[$key] ?? [], static fn(int $ts): bool => $ts > $now-$window);
                if (count($active) >= $max) return false;
            }
            foreach ($limits as $key => $_) $data[$key][] = $now;
            $encoded = json_encode($data, JSON_THROW_ON_ERROR);
            $tmp = @tempnam($dir, '.contact-admission-');
            if ($tmp === false) throw new \RuntimeException('Contact admission write unavailable.');
            try {
                if (!@chmod($tmp, 0600) || @file_put_contents($tmp, $encoded) !== strlen($encoded) || !@rename($tmp, $path))
                    throw new \RuntimeException('Contact admission write failed.');
            } finally {
                if (is_file($tmp)) @unlink($tmp);
            }
            return true;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
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
