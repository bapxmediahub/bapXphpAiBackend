<?php
namespace App\Services;

final class SchemaService {
    public function all(): array {
        $path = app_path('storage/schema/collections.json');
        if (!is_file($path)) return ['version'=>0, 'collections'=>[]];
        return json_decode(file_get_contents($path) ?: '{}', true) ?: ['version'=>0, 'collections'=>[]];
    }

    public function collection(string $name): array {
        $schema = $this->all();
        return $schema['collections'][$name] ?? [];
    }

    public function adminFields(string $name, array $fallback = []): array {
        $collection = $this->collection($name);
        return $collection['admin_fields'] ?? $fallback;
    }

    public function agentContextFields(string $name): array {
        $collection = $this->collection($name);
        return $collection['agent_context'] ?? [];
    }
}
