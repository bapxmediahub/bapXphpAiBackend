<?php
namespace App\Services;
final class ResourceService {
    public function __construct(private string $collection, private DatabaseService $store = new DatabaseService()) {}
    public function all(): array { return $this->store->read($this->collection); }
    public function save(array $record): array {
        $hadId = isset($record['id']) && (string)$record['id'] !== '';
        $record['id'] ??= bin2hex(random_bytes(8));
        $record['slug'] ??= strtolower(trim(preg_replace('/[^a-z0-9]+/i','-', $record['name'] ?? $record['code'] ?? $record['id']), '-'));

        // Creating a record with a slug that already exists updates that record instead
        // of inserting a second one. Saves now travel over HTTP to the remote database
        // and take seconds, so a second click before the redirect used to produce two
        // rows with different generated ids and the same slug.
        if (!$hadId) {
            $slug = (string)($record['slug'] ?? '');
            if ($slug !== '') {
                $existing = $this->store->find($this->collection, $slug, 'slug');
                if ($existing && !empty($existing['id'])) {
                    $record['id'] = $existing['id'];
                }
            }
        }
        return $this->store->upsert($this->collection, $record);
    }
    public function delete(string $id): void { $this->store->delete($this->collection, $id); }
}
