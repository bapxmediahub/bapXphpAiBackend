<?php
namespace App\Services;

final class ContactService {
    private JsonStoreService $store;

    public function __construct() {
        $this->store = new JsonStoreService('contact_submissions');
    }

    public function all(): array {
        $items = $this->store->all();
        usort($items, fn($a, $b) => ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0));
        return $items;
    }

    public function find(string $id): ?array {
        return $this->store->find($id);
    }

    public function save(array $data): string {
        $id = $data['id'] ?? uniqid('contact_', true);
        $data['id'] = $id;
        $data['created_at'] = $data['created_at'] ?? time();
        $data['status'] = $data['status'] ?? 'new';
        $this->store->save($id, $data);
        return $id;
    }

    public function updateStatus(string $id, string $status): void {
        $item = $this->store->find($id);
        if ($item) {
            $item['status'] = $status;
            $this->store->save($id, $item);
        }
    }

    public function delete(string $id): void {
        $this->store->delete($id);
    }

    public function count(): int {
        return count($this->store->all());
    }

    public function unreadCount(): int {
        return count(array_filter($this->store->all(), fn($item) => ($item['status'] ?? 'new') === 'new'));
    }
}
