<?php
namespace App\Services;

final class TempleService {
    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    public function all(): array {
        return $this->store->read('temples');
    }

    public function findBySlug(string $slug): ?array {
        foreach ($this->all() as $item) {
            if (($item['slug'] ?? '') === $slug) return $item;
        }
        return null;
    }

    public static function directionsUrl(array $temple): ?string {
        // The editor saves map_url; earlier records can still use map_link.
        foreach (['map_url', 'map_link'] as $field) {
            $value = $temple[$field] ?? null;
            if (!is_string($value)) continue;
            $url = trim($value);
            if (filter_var($url, FILTER_VALIDATE_URL) !== false
                && in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                return $url;
            }
        }
        return null;
    }
}
