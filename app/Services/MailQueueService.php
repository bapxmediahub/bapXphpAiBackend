<?php
namespace App\Services;

final class MailQueueService {
    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    /**
     * Transactional email shell built to the conventions real ecommerce senders use:
     * a single column capped at 600px, 16px body text, and a call to action at least
     * 44px tall — most transactional mail is read on a phone, and anything under 14px
     * is unreadable there.
     *
     * Structure is nested presentation tables with inline styles. Email clients strip
     * <style> blocks, ignore flexbox and grid, and Outlook drops margins on <div>, so
     * the site's stylesheet cannot be reused directly. The brand tokens below are
     * copied from assets/css/band.css so the mail matches the site.
     */
    private const BRAND = [
        'maroon'      => '#3a0003',
        'maroon_deep' => '#240002',
        'gold'        => '#d1b368',
        'gold_light'  => '#f3e8c9',
        'bg'          => '#faf7f0',
        'bg_alt'      => '#f7f0e4',
        'border'      => '#d8ccb7',
        'ink'         => '#222222',
        'muted'       => '#91877c',
        'serif'       => "Georgia,'Times New Roman',serif",
        'sans'        => "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif",
    ];

    private function wrapHtml(string $inner, string $audience = 'customer'): string {
        $b = self::BRAND;
        $isAdmin = $audience === 'admin';
        $settings = (new SettingsService())->public();
        $siteName = 'Sri Panchami Spiritual';
        $siteUrl = rtrim($this->siteUrl('/'), '/');
        $logoUrl = trim((string)($settings['logo_url'] ?? ''));
        if ($logoUrl === '') $logoUrl = $siteUrl . '/assets/images/logo-square.jpeg';
        if (str_starts_with($logoUrl, '/')) $logoUrl = $siteUrl . $logoUrl;

        $legal = [];
        if (!empty($settings['gst_legal_name'])) $legal[] = e((string)$settings['gst_legal_name']);
        if (!empty($settings['gst_address']))    $legal[] = e((string)$settings['gst_address']);
        if (!empty($settings['gstin']))          $legal[] = 'GSTIN ' . e((string)$settings['gstin']);
        $legalHtml = $legal ? '<br>' . implode('<br>', $legal) : '';

        return '<!doctype html>'
            . '<html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="x-apple-disable-message-reformatting">'
            . '<meta name="color-scheme" content="light dark"><meta name="supported-color-schemes" content="light dark">'
            . '<title>' . e($siteName) . '</title></head>'
            . '<body style="margin:0;padding:0;width:100%;background:' . $b['bg'] . ';-webkit-text-size-adjust:100%;">'

            // Preheader: the grey text a client shows next to the subject. Hidden in the
            // body so it never appears twice.
            . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . e($siteName) . '</div>'

            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . $b['bg'] . ';">'
            . '<tr><td align="center" style="padding:28px 12px;">'

            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid ' . $b['border'] . ';border-radius:14px;overflow:hidden;">'

            // Header
            . '<tr><td align="center" style="background:' . ($isAdmin ? $b['maroon_deep'] : $b['maroon']) . ';padding:22px 24px;border-bottom:3px solid ' . $b['gold'] . ';">'
            . '<img src="' . e($logoUrl) . '" width="' . ($isAdmin ? 44 : 64) . '" height="' . ($isAdmin ? 44 : 64) . '" alt="' . e($siteName) . '" '
            . 'style="display:block;margin:0 auto 10px;width:' . ($isAdmin ? 44 : 64) . 'px;height:' . ($isAdmin ? 44 : 64) . 'px;border-radius:50%;border:2px solid ' . $b['gold'] . ';">'
            . '<div style="font-family:' . $b['serif'] . ';font-size:' . ($isAdmin ? 16 : 19) . 'px;font-weight:bold;color:' . $b['gold'] . ';letter-spacing:0.4px;">'
            . e($siteName) . '</div>'
            // Admin mail is labelled so it is obvious at a glance in a shared inbox that
            // this is a store notification, not something a customer received.
            . ($isAdmin ? '<div style="margin-top:6px;font-family:' . $b['sans'] . ';font-size:11px;letter-spacing:1.4px;text-transform:uppercase;color:#c9b89a;">Store notification</div>' : '')
            . '</td></tr>'

            // Body — 16px, the floor for comfortable reading on a phone
            . '<tr><td style="padding:30px 28px 10px;font-family:' . $b['sans'] . ';font-size:16px;line-height:1.65;color:' . $b['ink'] . ';">'
            . $inner . '</td></tr>'

            // Footer
            . '<tr><td style="padding:10px 28px 26px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
            . '<td style="border-top:1px solid ' . $b['border'] . ';padding-top:18px;font-family:' . $b['sans'] . ';font-size:12px;line-height:1.7;color:' . $b['muted'] . ';">'
            . ($isAdmin
                ? '<strong style="color:' . $b['ink'] . ';font-family:' . $b['serif'] . ';font-size:14px;">Store notification</strong>'
                  . '<br>Sent to the store owner. Customers do not receive this email.'
                  . '<br><a href="' . e($siteUrl . '/admin') . '" style="color:' . $b['maroon'] . ';text-decoration:underline;">Open the admin panel</a>'
                : '<strong style="color:' . $b['ink'] . ';font-family:' . $b['serif'] . ';font-size:14px;">' . e($siteName) . '</strong>' . $legalHtml
                  . '<br><br>Need help? Reply to this email or write to '
                  . '<a href="mailto:support@sripanchamispiritual.com" style="color:' . $b['maroon'] . ';text-decoration:underline;">support@sripanchamispiritual.com</a>'
                  . '<br><a href="' . e($siteUrl) . '" style="color:' . $b['maroon'] . ';text-decoration:underline;">' . e(preg_replace('#^https?://#', '', $siteUrl)) . '</a>')
            . '</td></tr></table></td></tr>'

            . '</table>'
            . '<div style="font-family:' . $b['sans'] . ';font-size:11px;color:' . $b['muted'] . ';padding:14px 8px 0;max-width:600px;">'
            . ($isAdmin
                ? 'Automated store notification. Change the recipient in Admin &rarr; Integrations.'
                : 'You received this because you have an account or placed an order with ' . e($siteName) . '.')
            . '</div>'
            . '</td></tr></table></body></html>';
    }

    /** Heading for the one thing the email is about. */
    public static function heading(string $text): string {
        return '<h1 style="margin:0 0 14px;font-family:' . self::BRAND['serif'] . ';font-size:22px;line-height:1.3;'
            . 'font-weight:bold;color:' . self::BRAND['maroon'] . ';">' . e($text) . '</h1>';
    }

    /** Key/value panel for order and appointment details. */
    public static function details(array $rows): string {
        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
            . 'style="margin:18px 0;background:' . self::BRAND['bg_alt'] . ';border:1px solid ' . self::BRAND['border'] . ';border-radius:10px;">';
        foreach ($rows as $label => $value) {
            if (trim((string)$value) === '') continue;
            $html .= '<tr>'
                . '<td style="padding:9px 16px;font-family:' . self::BRAND['sans'] . ';font-size:13px;color:' . self::BRAND['muted'] . ';white-space:nowrap;">' . e((string)$label) . '</td>'
                . '<td align="right" style="padding:9px 16px;font-family:' . self::BRAND['sans'] . ';font-size:14px;font-weight:600;color:' . self::BRAND['ink'] . ';">' . $value . '</td>'
                . '</tr>';
        }
        return $html . '</table>';
    }

    /** Absolute URL for links in email; relative paths are useless in an inbox. */
    private function siteUrl(string $path = '/'): string {
        $base = rtrim((string)((require app_path('config/database.php'))['app_url'] ?? ''), '/');
        if ($base === '') $base = 'https://sripanchamispiritual.com';
        return $base . '/' . ltrim($path, '/');
    }

    /**
     * Call to action. Outlook ignores padding on <a>, so the colour sits on a table
     * cell; 44px minimum height is the accepted mobile tap target.
     */
    public static function button(string $label, string $url): string {
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0;">'
            . '<tr><td align="center" bgcolor="' . self::BRAND['maroon'] . '" style="border-radius:999px;height:46px;">'
            . '<a href="' . e($url) . '" style="display:inline-block;padding:13px 30px;font-family:' . self::BRAND['sans'] . ';'
            . 'font-size:15px;font-weight:600;color:' . self::BRAND['gold'] . ';text-decoration:none;border-radius:999px;">'
            . e($label) . '</a></td></tr></table>';
    }

    public function all(): array {
        return $this->store->read('mail_queue');
    }

    /**
     * $audience selects the template. Both are sent from the same authenticated
     * mailbox (support@); only the layout and footer differ, so the owner can tell a
     * store notification from something a customer received.
     */
    public function enqueue(string $type, string $to, string $subject, string $html, ?\DateTimeImmutable $availableAt = null, array $meta = [], string $audience = 'customer'): array {
        $html = $this->wrapHtml($html, $audience);
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
        $subject = 'Order confirmed — Sri Panchami Spiritual';
        $html = self::heading('Thank you, your order is confirmed')
            . '<p>Vanakkam ' . e((string)($order['customer_name'] ?? '')) . ', we have received your payment and are preparing your order.</p>'
            . self::details([
                'Order'   => e((string)($order['id'] ?? '')),
                'Invoice' => e((string)($order['invoice_number'] ?? '')),
                'Total'   => '₹' . e((string)($order['total'] ?? 0)),
            ])
            . self::button('View your order', $this->siteUrl('/account/dashboard/orders'))
            . $invoiceHtml;
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
            return $this->enqueue('admin_notification', $admin, $subject, $html, null, $meta, 'admin');
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
            self::heading('Your payment did not go through')
            . '<p>Vanakkam ' . e((string)($order['customer_name'] ?? '')) . ', your payment could not be completed, '
            . 'so the order was not placed and <strong>you have not been charged</strong>.</p>'
            . '<p>Your cart has been kept, so nothing needs rebuilding.</p>'
            . self::button('Complete your order', $this->siteUrl('/checkout'))
            . '<p style="color:#7b6b63;font-size:13px;">Reply to this email if you would like help.</p>' . $detail,
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
                    self::heading($title)
                    . ($excerpt !== '' ? '<p>' . e($excerpt) . '</p>' : '')
                    . self::button('Read the article', $this->siteUrl('/blog/' . $slug)),
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
        $trackingId = trim((string)($order['tracking_id'] ?? ''));
        $trackingUrl = trim((string)($order['tracking_url'] ?? ''));
        $courier = trim((string)($order['courier_name'] ?? ''));
        // Domestic versus international changes what we can honestly promise.
        $country = strtolower(trim((string)($order['shipping_country'] ?? $order['country'] ?? 'india')));
        $isDomestic = $country === '' || str_contains($country, 'india');
        $window = $isDomestic
            ? \App\Services\OrderService::DELIVERY_DAYS_DOMESTIC
            : \App\Services\OrderService::DELIVERY_DAYS_INTERNATIONAL;

        $html = self::heading('Your order is on its way')
            . '<p>We have dispatched your order. Delivery usually takes <strong>' . e($window) . '</strong> from today.</p>'
            . self::details(array_filter([
                'Order'       => e((string)($order['id'] ?? '')),
                'Courier'     => $courier !== '' ? e($courier) : '',
                'Tracking ID' => $trackingId !== '' ? e($trackingId) : '',
            ]))
            . ($trackingUrl !== ''
                ? self::button('Track your parcel', $trackingUrl)
                  . '<p style="font-size:13px;color:#91877c;">Tracking can take a few hours to show its first update.</p>'
                : self::button('View your order', $this->siteUrl('/account/dashboard/orders')))
            . '<p style="color:#91877c;font-size:13px;">We will ask for a review once you have had time to use it.</p>';
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
