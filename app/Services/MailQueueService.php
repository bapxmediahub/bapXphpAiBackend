<?php
namespace App\Services;

final class MailQueueService {
    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    private function wrapHtml(string $inner): string {
        $settings = (new SettingsService())->public();
        $logoUrl = $settings['logo_url'] ?? '';
        $siteName = 'Sri Panchami Spiritual';
        $logoHtml = $logoUrl ? "<img src=\"$logoUrl\" alt=\"$siteName\" style=\"max-width:180px;height:auto;margin-bottom:16px;\">" : "<h1 style=\"margin:0 0 16px;font-size:1.5rem;color:#3a0003;\">$siteName</h1>";
        $footerHtml = '<hr style="border:none;border-top:1px solid #e5e5e5;margin:24px 0;">'
            . '<p style="margin:0;font-size:0.8rem;color:#666;">'
            . 'GSTIN: ' . e((string)($settings['gstin'] ?? '')) . '<br>'
            . 'Address: ' . e((string)($settings['gst_address'] ?? '')) . '<br>'
            . 'State: ' . e((string)($settings['gst_state'] ?? '')) . ' (' . e((string)($settings['gst_state_code'] ?? '')) . ')<br>'
            . 'PAN: ' . e((string)($settings['gst_pan'] ?? '')) . '<br>'
            . 'Email: support@sripanchamispiritual.com | Phone: +91-XXXXXXXXXX'
            . '</p>'
            . '<p style="margin-top:16px;font-size:0.75rem;color:#999;">'
            . 'This is an automated email from ' . e($siteName) . '. Please do not reply.'
            . '</p>';

        return '<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;line-height:1.6;color:#222;max-width:600px;margin:0 auto;padding:24px;">'
            . '<div style="text-align:center;padding-bottom:16px;border-bottom:1px solid #e5e5e5;">' . $logoHtml . '</div>'
            . '<div style="padding:24px 0;">'
            . $inner
            . '</div>'
            . '<div style="text-align:center;">' . $footerHtml . '</div>'
            . '</div>';
    }

    public function all(): array {
        return $this->store->read('mail_queue');
    }

    public function enqueue(string $type, string $to, string $subject, string $html, ?\DateTimeImmutable $availableAt = null, array $meta = []): array {
        $html = $this->wrapHtml($html);
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
        // Deliver in this request. There is no cron on the host, so anything that is
        // only queued is never sent. The row is still written first, so a failed send
        // leaves an auditable record with the error rather than vanishing.
        if ($availableAt === null) $this->deliverNow($saved);
        return $saved;
    }

    /**
     * Best-effort immediate delivery. Never throws: a mail failure must not break the
     * checkout, signup or password reset that triggered it.
     */
    public function deliverNow(array $record): bool {
        try {
            $mailer = new SmtpMailer((new SecretService())->all());
            if (!$mailer->configured()) {
                $this->markFailed((string)$record['id'], 'Email delivery is not configured.');
                return false;
            }
            $mailer->send((string)$record['to'], (string)$record['subject'], (string)$record['html']);
            $this->markSent((string)$record['id']);
            (new MailStorageService($this->store))->updateOutboxForQueue((string)$record['id'], 'sent', [
                'from_email' => $mailer->fromEmail(),
                'transport' => $mailer->transport(),
                'sent_at' => date('c'),
            ]);
            return true;
        } catch (\Throwable $error) {
            try {
                $this->markFailed((string)$record['id'], $error->getMessage());
                (new MailStorageService($this->store))->updateOutboxForQueue((string)$record['id'], 'failed', [
                    'last_error' => $error->getMessage(),
                    'failed_at' => date('c'),
                ]);
            } catch (\Throwable) {}
            error_log('Mail delivery failed: ' . $error->getMessage());
            return false;
        }
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
        $sent = $this->enqueue('payment_confirmation', $to, $subject, $html, null, ['order_id' => $order['id'] ?? '']);
        $this->notifyAdmin(
            'New order ' . (string)($order['id'] ?? ''),
            '<p>A new paid order has come in.</p>'
            . '<p>Order: <strong>' . e((string)($order['id'] ?? '')) . '</strong><br>'
            . 'Customer: ' . e((string)($order['customer_name'] ?? '')) . ' &lt;' . e($to) . '&gt;<br>'
            . 'Total: ₹' . e((string)($order['total'] ?? 0)) . '</p>',
            ['order_id' => $order['id'] ?? '']
        );
        return $sent;
    }

    /**
     * Send a copy to the owner. The destination is admin_notification_email, which is
     * deliberately separate from the sending mailbox: customers are written to from
     * support@, while the owner is notified at their own inbox.
     */
    public function notifyAdmin(string $subject, string $html, array $meta = []): ?array {
        $admin = trim((string)((new SecretService())->all()['admin_notification_email'] ?? ''));
        if ($admin === '' || !filter_var($admin, FILTER_VALIDATE_EMAIL)) return null;
        try {
            return $this->enqueue('admin_notification', $admin, $subject . ' - Sri Panchami Spiritual', $html, null, $meta);
        } catch (\Throwable $e) {
            error_log('Admin notification failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * A failed payment left the customer with nothing — no screen, no email — while the
     * owner never learned an attempt had failed. Both are told, and the customer is
     * pointed back at their cart, which is deliberately preserved.
     */
    public function enqueuePaymentFailure(array $order, string $reason = ''): ?array {
        $to = trim((string)($order['customer_email'] ?? ''));
        $detail = $reason !== '' ? '<p>Reason: ' . e($reason) . '</p>' : '';
        $this->notifyAdmin(
            'Payment failed for ' . (string)($order['id'] ?? 'an order'),
            '<p>A payment attempt failed.</p>'
            . '<p>Order: <strong>' . e((string)($order['id'] ?? '')) . '</strong><br>'
            . 'Customer: ' . e((string)($order['customer_name'] ?? '')) . ' &lt;' . e($to) . '&gt;<br>'
            . 'Amount: ₹' . e((string)($order['total'] ?? 0)) . '</p>' . $detail,
            ['order_id' => $order['id'] ?? '']
        );
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return null;
        return $this->enqueue('payment_failed', $to, 'Your payment could not be completed',
            '<p>Vanakkam ' . e((string)($order['customer_name'] ?? '')) . ',</p>'
            . '<p>Your payment for order <strong>' . e((string)($order['id'] ?? '')) . '</strong> could not be completed, '
            . 'so the order has not been placed and you have not been charged.</p>'
            . '<p>Your cart has been kept. You can try again from <a href="/checkout">checkout</a>, '
            . 'or reply to this email and we will help.</p>' . $detail,
            null, ['order_id' => $order['id'] ?? '']);
    }

    /** Security notice so an account holder learns about a sign-in they did not make. */
    public function enqueueLoginNotification(string $email, string $name, string $role): ?array {
        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
        $when = date('d M Y, H:i');
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        return $this->enqueue('login_notification', $email, 'New sign-in to your Sri Panchami Spiritual account',
            '<p>Hello ' . e($name !== '' ? $name : 'there') . ',</p>'
            . '<p>Your account was signed in to on <strong>' . e($when) . '</strong> (IP ' . e($ip) . ').</p>'
            . '<p>If this was you, no action is needed. If it was not, change your password immediately '
            . 'and contact <a href="mailto:support@sripanchamispiritual.com">support@sripanchamispiritual.com</a>.</p>',
            null, ['role' => $role]);
    }

    /**
     * Newsletter for a newly published post. Sent to registered customers; the owner and
     * consultants are skipped so a publish does not mail the whole staff.
     */
    public function enqueueBlogNewsletter(array $post, array $recipients): int {
        $title = trim((string)($post['title'] ?? ''));
        $slug = trim((string)($post['slug'] ?? ''));
        if ($title === '' || $slug === '') return 0;
        $excerpt = trim((string)($post['excerpt'] ?? $post['summary'] ?? ''));
        $sent = 0;
        foreach ($recipients as $email) {
            $email = trim((string)$email);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            try {
                $this->enqueue('blog_newsletter', $email, 'New article: ' . $title,
                    '<h2 style="margin:0 0 8px;">' . e($title) . '</h2>'
                    . ($excerpt !== '' ? '<p>' . e($excerpt) . '</p>' : '')
                    . '<p><a href="/blog/' . e($slug) . '">Read the article</a></p>',
                    null, ['slug' => $slug]);
                $sent++;
            } catch (\Throwable $error) {
                error_log('Newsletter send failed for ' . $email . ': ' . $error->getMessage());
            }
        }
        return $sent;
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
