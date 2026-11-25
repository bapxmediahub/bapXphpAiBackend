<?php
namespace App\Services;

final class MailStorageService {
    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    public function inbox(): array {
        return $this->sorted('mail_inbox');
    }

    public const DELIVERY_STATES = [
        'pending' => 'Pending', 'sending' => 'Sending', 'sent' => 'Sent',
        'failed' => 'Failed', 'needs_review' => 'Needs review', 'unknown' => 'Unclassified',
    ];
    public const OUTBOX_PAGE_SIZES = [10, 25, 50];

    public function outboxPage(array $query, bool $newsletter): array {
        $options = ResourceService::listOptions($query);
        if (!in_array($options['pageSize'], self::OUTBOX_PAGE_SIZES, true)) $options['pageSize'] = 25;
        $state = is_string($query['state'] ?? null) && isset(self::DELIVERY_STATES[$query['state']]) ? $query['state'] : '';
        $value = static fn(string $alias, string $field): string =>
            "NULLIF(JSON_UNQUOTE(JSON_EXTRACT($alias._data, '$.$field')), 'null')";
        // An explicitly cleared canonical field also clears a stale projection value.
        $effective = static fn(string $field): string =>
            "CASE WHEN JSON_CONTAINS_PATH(q._data, 'one', '$.$field') = 1
                  THEN " . $value('q', $field) . ' ELSE ' . $value('o', $field) . ' END';
        $rawState = $effective('status');
        $stateExpression = "CASE WHEN CAST(($rawState) AS BINARY) IN ('pending','sending','sent','failed','needs_review')
                              THEN $rawState ELSE 'unknown' END";
        $messageReference = $effective('message_id');
        $queueReference = $value('o', 'queue_id');
        $source = $value('o', 'source');
        $from = " FROM mail_outbox o LEFT JOIN mail_queue q ON q.id = $queueReference
            AND CAST(q.id AS BINARY) = CAST($queueReference AS BINARY)";
        $filters = [];
        $params = [];
        if ($newsletter) $filters[] = "$source = 'blog_newsletter'";
        if ($state !== '') {
            $filters[] = "($stateExpression) = ?";
            $params[] = $state;
        }
        if ($options['search'] !== '') {
            $recipient = $value('o', 'to_email');
            $subject = $value('o', 'subject');
            $filters[] = "LOCATE(LOWER(?), LOWER(CONCAT_WS(' ', $recipient, $subject, $queueReference, ($messageReference)))) > 0";
            $params[] = $options['search'];
        }
        $where = $filters === [] ? '' : ' WHERE ' . implode(' AND ', $filters);
        $count = (int)($this->store->query("SELECT COUNT(*) AS total$from$where", $params)[0]['total'] ?? 0);
        $paging = ResourceService::pageBounds($options, $count);
        if ($count === 0) return ['items' => [], 'paging' => $paging, 'state' => $state];

        $deliveryState = $effective('delivery_state');
        $errorCode = $effective('error_code');
        $createdAt = $value('o', 'created_at');
        $rows = $this->store->query("SELECT o.id, o._data, ($stateExpression) AS status,
            ($deliveryState) AS delivery_state, ($messageReference) AS message_id, ($errorCode) AS error_code
            $from$where ORDER BY $createdAt DESC, o.id DESC LIMIT {$paging['pageSize']} OFFSET {$paging['offset']}", $params);
        $items = array_map(static function (array $row): array {
            $record = json_decode($row['_data'], true, flags: JSON_THROW_ON_ERROR);
            unset($row['_data']);
            return $row + $record;
        }, $rows);
        return compact('items', 'paging', 'state');
    }

    public function recordInboxFromContact(array $contact): array {
        $id = 'in_' . (string)($contact['id'] ?? bin2hex(random_bytes(6)));
        $createdAt = $contact['created_at'] ?? date('c');
        if (is_numeric($createdAt)) $createdAt = date('c', (int)$createdAt);
        $record = [
            'id' => $id,
            'from_email' => trim((string)($contact['email'] ?? '')),
            'from_name' => trim((string)($contact['name'] ?? '')),
            'to_email' => 'admin',
            'subject' => trim((string)($contact['subject'] ?? 'Contact request')) ?: 'Contact request',
            'body' => trim((string)($contact['message'] ?? '')),
            'status' => 'unread',
            'source' => 'contact_submissions',
            'source_id' => (string)($contact['id'] ?? ''),
            'created_at' => $createdAt,
        ];
        if (isset($contact['campaign_slug'], $contact['campaign_revision'])) {
            $record['campaign_slug'] = $contact['campaign_slug'];
            $record['campaign_revision'] = $contact['campaign_revision'];
        }
        return $this->store->upsert('mail_inbox', $record);
    }

    public function recordQueuedOutbox(array $mail): array {
        $id = 'out_' . (string)($mail['id'] ?? bin2hex(random_bytes(6)));
        return $this->store->upsert('mail_outbox', [
            'id' => $id,
            'queue_id' => (string)($mail['id'] ?? ''),
            'to_email' => (string)($mail['to'] ?? ''),
            'from_email' => (string)($mail['from'] ?? ''),
            'subject' => (string)($mail['subject'] ?? ''),
            'body' => (string)($mail['html'] ?? ''),
            'status' => (string)($mail['status'] ?? 'pending'),
            'transport' => (string)($mail['transport'] ?? 'queued'),
            'source' => (string)($mail['type'] ?? 'mail_queue'),
            'source_id' => (string)(($mail['meta']['order_id'] ?? '') ?: ($mail['id'] ?? '')),
            'created_at' => (string)($mail['created_at'] ?? date('c')),
        ]);
    }

    public function updateOutboxForQueue(string $queueId, string $status, array $extra = []): void {
        $record=$this->store->find('mail_outbox','out_'.$queueId);
        if($record===null)return;
        $this->store->upsert('mail_outbox',array_merge($record,$extra,['status'=>$status]));
    }

    private function sorted(string $collection): array {
        $items = $this->store->read($collection);
        usort($items, fn($a, $b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
        return $items;
    }
}
