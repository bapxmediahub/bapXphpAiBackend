<?php
namespace App\Services;

final class SettingsService {
    public const SETTINGS_ID = 'app_settings';

    /** Modules that can be switched off from Admin → Site Settings. */
    public const MODULES = [
        'consult' => 'Consultation',
        'shop'    => 'Ecommerce',
        'blog'    => 'Blog',
    ];

    /** Request-level memo. DatabaseService has no caching and every read is a full table scan. */
    private static ?array $cache = null;

    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    private function load(): array {
        if (self::$cache !== null) return self::$cache;
        try {
            $rows = $this->store->read('settings');
        } catch (\Throwable) {
            return self::$cache = [];
        }
        // Always prefer the canonical record. `read()` returns an unordered SELECT *,
        // so `[0]` can be a stale duplicate row with different (or plaintext) values.
        foreach ($rows as $row) {
            if ((string)($row['id'] ?? '') === self::SETTINGS_ID) return self::$cache = $row;
        }
        return self::$cache = ($rows[0] ?? []);
    }

    public static function flushCache(): void { self::$cache = null; }

    public function public(): array {
        $settings = $this->load();
        return $settings ?: ['shipping_mode' => 'free', 'flat_rate' => 0];
    }

    public function admin(): array { return $this->load(); }

    /**
     * Module on/off flags. Absent means enabled, so existing installs are unaffected.
     * @return array<string,bool>
     */
    public function modules(): array {
        $settings = $this->load();
        $modules = [];
        foreach (array_keys(self::MODULES) as $key) {
            $modules[$key] = (string)($settings['module_' . $key] ?? '1') !== '0';
        }
        return $modules;
    }

    public function moduleEnabled(string $key): bool {
        return $this->modules()[$key] ?? true;
    }

    public function savePublic(array $settings): void {
        $settings['id'] = self::SETTINGS_ID;
        $this->store->upsert('settings', $settings, 'id');
        self::flushCache();
    }
}
