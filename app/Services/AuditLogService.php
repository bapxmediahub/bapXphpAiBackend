<?php
namespace App\Services;

final class AuditLogService {
    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    public function all(): array {
        return array_reverse($this->store->read('audit_events'));
    }

    public function record(string $action, string $resource, string $resourceId = '', array $details = [], ?array $actor = null): array {
        $actor ??= $_SESSION['user'] ?? [];
        $event = [
            'id' => bin2hex(random_bytes(8)),
            'event' => trim($resource . '.' . $action, '.'),
            'action' => $action,
            'resource' => $resource,
            'resource_id' => $resourceId,
            'status' => 'recorded',
            'actor_email' => $actor['email'] ?? 'system',
            'actor_name' => $actor['name'] ?? '',
            'meta' => $details,
            'created_at' => date('c'),
        ];
        $records = $this->store->read('audit_events');
        $records[] = $event;
        $this->store->write('audit_events', $records);
        return $event;
    }
}
