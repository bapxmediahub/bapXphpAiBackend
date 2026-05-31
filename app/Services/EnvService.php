<?php
namespace App\Services;

final class EnvService {
    public const PATH = '.env';

    public static function load(?string $path = null, bool $overwrite = false): void {
        $values = self::readFile($path ?? app_path(self::PATH));
        foreach ($values as $key => $value) {
            if ($overwrite || getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }

    public static function readFile(string $path): array {
        if (!is_file($path)) return [];
        $values = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            $values[$key] = $value;
        }
        return $values;
    }

    public function raw(): string {
        $path = app_path(self::PATH);
        return is_file($path) ? (file_get_contents($path) ?: '') : '';
    }

    public function saveRaw(string $contents): void {
        $path = app_path(self::PATH);
        $normalized = str_replace(["\r\n", "\r"], "\n", $contents);
        file_put_contents($path, rtrim($normalized) . "\n", LOCK_EX);
        self::load($path, true);
    }

    public function adminCredentials(): array {
        return [
            'username' => getenv('ADMIN_USERNAME') ?: '',
            'email' => getenv('ADMIN_EMAIL') ?: '',
            'password' => getenv('ADMIN_PASSWORD') ?: '',
        ];
    }

    public function saveAdminCredentials(array $data): void {
        $path = app_path(self::PATH);
        $values = self::readFile($path);
        foreach (['ADMIN_USERNAME' => 'admin_username', 'ADMIN_EMAIL' => 'admin_email'] as $envKey => $postKey) {
            $value = trim((string)($data[$postKey] ?? ''));
            if ($value !== '') $values[$envKey] = $value;
        }
        $password = (string)($data['admin_password'] ?? '');
        if ($password !== '') $values['ADMIN_PASSWORD'] = $password;
        $this->writeFile($path, $values);
        self::load($path, true);
    }

    private function writeFile(string $path, array $values): void {
        $ordered = ['APP_NAME', 'APP_URL', 'ADMIN_USERNAME', 'ADMIN_EMAIL', 'ADMIN_PASSWORD'];
        $lines = [
            '# Sri Panchami Spiritual local hosting environment',
            '# Edit these values before using the product in production.',
        ];
        foreach ($ordered as $key) {
            if (array_key_exists($key, $values)) {
                $lines[] = $key . '=' . $this->encode((string)$values[$key]);
                unset($values[$key]);
            }
        }
        foreach ($values as $key => $value) {
            $lines[] = $key . '=' . $this->encode((string)$value);
        }
        file_put_contents($path, implode("\n", $lines) . "\n", LOCK_EX);
    }

    private function encode(string $value): string {
        if ($value === '' || preg_match('/\s|#|=|"/', $value)) {
            return '"' . str_replace('"', '\"', $value) . '"';
        }
        return $value;
    }
}
