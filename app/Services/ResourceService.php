<?php
namespace App\Services;
final class ResourceService {
    public function __construct(private string $collection, private DatabaseService $store = new DatabaseService()) {}
    public function all(): array { return $this->store->read($this->collection); }
    public function save(array $record): array {
        $record['id'] ??= bin2hex(random_bytes(8));
        $record['slug'] ??= strtolower(trim(preg_replace('/[^a-z0-9]+/i','-', $record['name'] ?? $record['code'] ?? $record['id']), '-'));
        return $this->store->upsert($this->collection, $record);
    }
    public function delete(string $id): void { $this->store->delete($this->collection, $id); }
}
