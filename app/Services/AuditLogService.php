<?php
namespace App\Services;

final class AuditLogService {
    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    public function all(): array {
        return array_reverse($this->store->read('audit_events'));
    }

    /** Operational metadata only: never retain prompts, replies, identities or secrets. */
    public function agentRun(string $surface, string $outcome, float $startedAt): void {
        if (!in_array($surface, ['support', 'admin'], true)) return;
        if (!in_array($outcome, ['model', 'fallback', 'private_account', 'draft', 'error'], true)) return;
        $this->record('run', 'agent', $surface, [
            'surface' => $surface,
            'outcome' => $outcome,
            'duration_ms' => max(0, (int)round((microtime(true) - $startedAt) * 1000)),
        ], ['email' => 'system', 'name' => 'Agent monitoring']);
    }

    public static function agentSummary(array $events, ?int $now = null): array {
        $cutoff = ($now ?? time()) - 86400;
        $summary = [];
        foreach (['support', 'admin'] as $surface) {
            $summary[$surface] = ['requests' => 0, 'model' => 0, 'fallback' => 0, 'private_account' => 0, 'draft' => 0, 'error' => 0, 'duration_ms' => 0, 'last_seen' => ''];
        }
        foreach ($events as $event) {
            if (($event['event'] ?? '') !== 'agent.run') continue;
            $meta = $event['meta'] ?? [];
            if (!is_array($meta)) continue;
            $surface = $meta['surface'] ?? '';
            $outcome = $meta['outcome'] ?? '';
            $created = (string)($event['created_at'] ?? '');
            $timestamp = strtotime($created);
            if (!isset($summary[$surface]) || !in_array($outcome, ['model', 'fallback', 'private_account', 'draft', 'error'], true) || $timestamp === false || $timestamp < $cutoff || $timestamp > ($now ?? time())) continue;
            $summary[$surface]['requests']++;
            $summary[$surface][$outcome]++;
            $summary[$surface]['duration_ms'] += max(0, (int)($meta['duration_ms'] ?? 0));
            if ($created > $summary[$surface]['last_seen']) $summary[$surface]['last_seen'] = $created;
        }
        foreach ($summary as &$row) $row['average_ms'] = $row['requests'] ? (int)round($row['duration_ms'] / $row['requests']) : null;
        return $summary;
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
        // Insert just this event. The previous implementation read the whole table,
        // appended one row and rewrote every row, which is O(n) per admin action and
        // risks losing the entire audit history if the rewrite fails part-way through.
        // Audit logging must never break the operation it is recording.
        try {
            $this->store->upsert('audit_events', $event);
        } catch (\Throwable $e) {
            error_log('Audit log write failed: ' . $e->getMessage());
        }
        return $event;
    }
}
