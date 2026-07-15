<?php
namespace App\Services;

final class MailQueueService {
    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    public function all(): array {
        return $this->store->read('mail_queue');
    }

    public function enqueue(string $type, string $to, string $subject, string $html, ?\DateTimeImmutable $availableAt = null, array $meta = []): array {
        $record = [
            'id' => bin2hex(random_bytes(8)),
            'type' => $type,
            'to' => $to,
            'subject' => $subject,
            'html' => $html,
            'status' => 'pending',
            'available_at' => ($availableAt ?? new \DateTimeImmutable())->format('c'),
            'meta' => $meta,
            'created_at' => date('c'),
        ];
        $saved = $this->store->upsert('mail_queue', $record);
        (new MailStorageService($this->store))->recordQueuedOutbox($saved);
        return $saved;
    }

    public function enqueuePaymentConfirmation(array $order): ?array {
        $to = trim((string)($order['customer_email'] ?? ''));
        if ($to === '') return null;
        $invoiceHtml = '';
        if (!empty($order['invoice_number'])) {
            $invoiceHtml = '<p>Invoice: <strong>' . e((string)($order['invoice_number'] ?? '')) . '</strong> — '
                . '<a href="' . rtrim(($_ENV['APP_URL'] ?? 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')), '/') . '/account/orders/' . e((string)($order['id'] ?? '')) . '/invoice">View invoice</a></p>';
        }
        $subject = 'Sri Panchami Spiritual payment confirmed';
        $html = '<p>Vanakkam ' . e((string)($order['customer_name'] ?? '')) . ',</p>'
            . '<p>Your payment for order ' . e((string)($order['id'] ?? '')) . ' is confirmed.</p>'
            . $invoiceHtml
            . '<p>Total: ₹' . e((string)($order['total'] ?? 0)) . '</p>';
        return $this->enqueue('payment_confirmation', $to, $subject, $html, null, ['order_id' => $order['id'] ?? '']);
    }

    public function enqueueShipmentNotification(array $order): ?array {
        $to = trim((string)($order['customer_email'] ?? ''));
        if ($to === '') return null;
        $subject = 'Sri Panchami Spiritual order shipped';
        $html = '<p>Your order ' . e((string)($order['id'] ?? '')) . ' has been shipped.</p>'
            . '<p>We will ask for your product review after you have had time to receive and use it.</p>';
        return $this->enqueue('shipment_notification', $to, $subject, $html, null, ['order_id' => $order['id'] ?? '']);
    }

    public function enqueueProductReviewRequest(array $order, int $waitDays = 10): ?array {
        $to = trim((string)($order['customer_email'] ?? ''));
        if ($to === '') return null;
        $shippedAt = new \DateTimeImmutable((string)($order['shipped_at'] ?? 'now'));
        $availableAt = $shippedAt->modify('+' . max(1, $waitDays) . ' days');
        $subject = 'How was your Sri Panchami Spiritual product?';
        $html = '<p>We hope your order ' . e((string)($order['id'] ?? '')) . ' reached you well.</p>'
            . '<p>Please share your product rating from your account orders page.</p>';
        return $this->enqueue('product_review_request', $to, $subject, $html, $availableAt, ['order_id' => $order['id'] ?? '']);
    }

    public function due(?\DateTimeImmutable $now = null): array {
        $now ??= new \DateTimeImmutable();
        $due = array_values(array_filter($this->all(), function (array $record) use ($now): bool {
            if (($record['status'] ?? 'pending') !== 'pending') return false;
            $availableAt = new \DateTimeImmutable((string)($record['available_at'] ?? 'now'));
            return $availableAt <= $now;
        }));
        usort($due, fn($a, $b) => strcmp((string)($a['available_at'] ?? ''), (string)($b['available_at'] ?? '')));
        return $due;
    }

    public function markSent(string $id): void {
        $this->updateStatus($id, 'sent', ['sent_at' => date('c')]);
    }

    public function markFailed(string $id, string $error): void {
        $this->updateStatus($id, 'failed', ['last_error' => $error, 'failed_at' => date('c')]);
    }

    public function processDue(SmtpMailer $mailer, ?\DateTimeImmutable $now = null, int $limit = 25): int {
        $sent = 0;
        foreach (array_slice($this->due($now), 0, $limit) as $record) {
            try {
                $mailer->send((string)$record['to'], (string)$record['subject'], (string)$record['html']);
                $this->markSent((string)$record['id']);
                (new MailStorageService($this->store))->updateOutboxForQueue((string)$record['id'], 'sent', [
                    'from_email' => $mailer->fromEmail(),
                    'transport' => $mailer->transport(),
                    'sent_at' => date('c'),
                ]);
                $sent++;
            } catch (\Throwable $error) {
                $this->markFailed((string)$record['id'], $error->getMessage());
                (new MailStorageService($this->store))->updateOutboxForQueue((string)$record['id'], 'failed', [
                    'from_email' => $mailer->fromEmail(),
                    'transport' => $mailer->transport(),
                    'last_error' => $error->getMessage(),
                    'failed_at' => date('c'),
                ]);
            }
        }
        return $sent;
    }

    private function updateStatus(string $id, string $status, array $extra): void {
        $records = $this->all();
        foreach ($records as &$record) {
            if (($record['id'] ?? '') !== $id) continue;
            $record = array_merge($record, $extra, ['status' => $status]);
            break;
        }
        unset($record);
        $this->store->write('mail_queue', $records);
    }
}
