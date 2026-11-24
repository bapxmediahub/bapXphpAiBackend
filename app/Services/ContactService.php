<?php
namespace App\Services;

final class ContactService {
    private DatabaseService $store;

    public function __construct(?DatabaseService $store = null) {
        $this->store = $store ?? new DatabaseService();
    }

    public function all(): array {
        $items = $this->store->read('contact_submissions');
        usort($items, fn($a, $b) => ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0));
        return $items;
    }

    public function find(string $id): ?array {
        foreach ($this->all() as $item) {
            if (($item['id'] ?? '') === $id) return $item;
        }
        return null;
    }

    /** Bounded public enquiry fields. Preserve Unicode and never trust client-side validation. */
    public static function validationErrors(array $data, array $subjects): array {
        $errors = [];
        foreach (['name'=>120, 'email'=>254, 'phone'=>40, 'message'=>4000] as $field=>$limit) {
            $value = $data[$field] ?? '';
            if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $limit) {
                $errors[$field] = 'Enter valid text within the displayed limit.';
            }
        }
        if (!isset($errors['name']) && trim((string)($data['name'] ?? '')) === '') $errors['name'] = 'Enter your name.';
        if (!isset($errors['email']) && !filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
        if (!is_string($data['subject'] ?? null) || !isset($subjects[$data['subject']])) $errors['subject'] = 'Select a subject.';
        if (!isset($errors['message']) && mb_strlen(trim((string)($data['message'] ?? ''))) < 12)
            $errors['message'] = 'Describe your enquiry in at least 12 characters.';
        return $errors;
    }

    public function save(array $data): string {
        $id = $data['id'] ?? uniqid('contact_', true);
        $data['id'] = $id;
        $data['created_at'] = $data['created_at'] ?? time();
        $data['status'] = $data['status'] ?? 'new';
        $this->store->upsert('contact_submissions', $data);
        (new MailStorageService($this->store))->recordInboxFromContact($data);
        return $id;
    }

    public function updateStatus(string $id, string $status): void {
        $item = $this->find($id);
        if ($item) {
            $item['status'] = $status;
            $this->store->upsert('contact_submissions', $item);
        }
    }

    public function delete(string $id): void {
        $this->store->delete('contact_submissions', $id);
    }

    public function count(): int {
        return count($this->all());
    }

    public function unreadCount(): int {
        return count(array_filter($this->all(), fn($item) => ($item['status'] ?? 'new') === 'new'));
    }
}
