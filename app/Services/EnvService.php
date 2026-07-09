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
        $values = self::readFileFromString($normalized);
        $this->writeFile($path, $values);
        self::load($path, true);
    }

    private static function readFileFromString(string $contents): array {
        $values = [];
        foreach (explode("\n", $contents) as $line) {
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

    public function adminCredentials(): array {
        $settings = (new SettingsService())->admin();
        if (!empty($settings['admin_email'])) {
            return [
                'username' => $settings['admin_username'] ?? '',
                'email' => $settings['admin_email'] ?? '',
                'password' => $settings['admin_password'] ?? '',
                'source' => 'settings',
            ];
        }
        return [
            'username' => getenv('ADMIN_USERNAME') ?: '',
            'email' => getenv('ADMIN_EMAIL') ?: '',
            'password' => getenv('ADMIN_PASSWORD') ?: '',
            'source' => 'env',
        ];
    }

    public function saveAdminCredentials(array $data): void {
        $settings = (new SettingsService())->public();
        foreach (['admin_username', 'admin_email'] as $key) {
            $value = trim((string)($data[$key] ?? ''));
            if ($value !== '') $settings[$key] = $value;
        }
        $password = (string)($data['admin_password'] ?? '');
        if ($password !== '') $settings['admin_password'] = password_hash($password, PASSWORD_BCRYPT);
        (new SettingsService())->savePublic($settings);
    }

    private function writeFile(string $path, array $values): void {
        $ordered = ['APP_NAME', 'APP_URL'];
        $lines = [];
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
