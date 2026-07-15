<?php
namespace App\Services;

final class SupportTicketService {
    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    public function all(): array {
        return $this->store->read('support_tickets');
    }

    public function forUser(string $email): array {
        return array_values(array_filter($this->all(), fn($t) => ($t['customer_email'] ?? '') === $email));
    }

    public function create(string $customerEmail, string $message, string $context = ''): array {
        $ticket = [
            'id' => bin2hex(random_bytes(8)),
            'customer_email' => $customerEmail,
            'message' => $message,
            'context' => $context,
            'status' => 'open',
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];
        return $this->store->upsert('support_tickets', $ticket);
    }

    public function reply(string $ticketId, string $reply): ?array {
        $tickets = $this->all();
        foreach ($tickets as &$t) {
            if (($t['id'] ?? '') === $ticketId) {
                $t['reply'] = $reply;
                $t['status'] = 'answered';
                $t['updated_at'] = date('c');
                $this->store->upsert('support_tickets', $t);
                (new AuditLogService())->record('save', 'support_tickets', $ticketId, ['action' => 'admin_reply']);
                return $t;
            }
        }
        return null;
    }

    public function close(string $ticketId): bool {
        $tickets = $this->all();
        foreach ($tickets as &$t) {
            if (($t['id'] ?? '') === $ticketId) {
                $t['status'] = 'closed';
                $t['updated_at'] = date('c');
                $this->store->upsert('support_tickets', $t);
                (new AuditLogService())->record('save', 'support_tickets', $ticketId, ['action' => 'close']);
                return true;
            }
        }
        return false;
    }
}
