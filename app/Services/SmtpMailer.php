<?php
namespace App\Services;

final class SmtpMailer {
    public function __construct(private array $settings) {}

    /**
     * SMTP only. The PHP mail() fallback was removed: it silently downgraded delivery
     * whenever SMTP credentials were wrong, so a misconfiguration looked like success
     * while messages went out unauthenticated with far worse deliverability.
     */
    public function configured(): bool {
        return $this->smtpConfigured();
    }

    public function fromEmail(): string {
        $from = trim((string)($this->settings['mail_from_email'] ?? ''));
        if ($from !== '') return $from;
        $from = trim((string)($this->settings['smtp_from_email'] ?? ''));
        if ($from !== '') return $from;
        return 'noreply@' . $this->mailDomain();
    }

    public function transport(): string {
        return 'smtp';
    }

    private function smtpConfigured(): bool {
        return !empty($this->settings['smtp_host'])
            && !empty($this->settings['smtp_port'])
            && !empty($this->settings['smtp_username'])
            && !empty($this->settings['smtp_password'])
            && $this->fromEmail() !== '';
    }

    public function buildMessage(string $to, string $subject, string $html): string {
        $fromEmail = $this->fromEmail();
        $fromName = $this->fromName();
        // Replies go to the sending mailbox (support@), not the admin's personal inbox.
        // admin_notification_email is the destination for admin *notifications*, which
        // is a different thing from where a customer's reply should land.
        $replyTo = $this->replyToEmail();
        $boundary = 'sps_' . bin2hex(random_bytes(12));
        $plain = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)));
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'To: ' . $to,
            'From: ' . $this->formatAddress($fromEmail, $fromName),
            'Reply-To: ' . $replyTo,
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        return implode("\r\n", $headers)
            . "\r\n\r\n--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n"
            . $plain
            . "\r\n\r\n--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n"
            . $html
            . "\r\n\r\n--{$boundary}--\r\n";
    }

    public function send(string $to, string $subject, string $html): void {
        if (!$this->configured()) {
            throw new \RuntimeException(
                'SMTP is not configured. Set host, port, username, password and From Email in Admin → Integrations.'
            );
        }
        $host = (string)$this->settings['smtp_host'];
        $port = (int)$this->settings['smtp_port'];
        $secure = strtolower((string)($this->settings['smtp_encryption'] ?? 'tls'));
        $target = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $socket = @stream_socket_client($target, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        if (!$socket) {
            throw new \RuntimeException(
                "Could not connect to {$host}:{$port} ({$errstr}). Check the host and port, and that the "
                . "server allows outbound SMTP — many hosts block ports 465 and 587 by default."
            );
        }
        stream_set_timeout($socket, 20);
        $this->expect($socket, [220]);
        $this->command($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);
        if ($secure === 'tls') {
            $this->command($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('Unable to start SMTP TLS.');
            }
            $this->command($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);
        }
        $this->command($socket, 'AUTH LOGIN', [334]);
        $this->command($socket, base64_encode((string)$this->settings['smtp_username']), [334]);
        $this->command($socket, base64_encode((string)$this->settings['smtp_password']), [235]);
        $from = $this->fromEmail();
        $this->command($socket, 'MAIL FROM:<' . $from . '>', [250]);
        $this->command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        $this->command($socket, 'DATA', [354]);
        // Dot-stuffing (RFC 5321 §4.5.2): a body line starting with "." would otherwise
        // be read as the end-of-data marker, silently truncating the message.
        $body = preg_replace('/^\./m', '..', $this->buildMessage($to, $subject, $html));
        fwrite($socket, $body . "\r\n.\r\n");
        $this->expect($socket, [250]);
        $this->command($socket, 'QUIT', [221]);
        fclose($socket);
    }

    private function formatAddress(string $email, string $name): string {
        return $name !== '' ? $name . ' <' . $email . '>' : $email;
    }


    private function replyToEmail(): string {
        $replyTo = trim((string)($this->settings['mail_reply_to'] ?? ''));
        return $replyTo !== '' ? $replyTo : $this->fromEmail();
    }

    private function fromName(): string {
        return trim((string)($this->settings['mail_from_name'] ?? $this->settings['smtp_from_name'] ?? 'Sri Panchami Spiritual'));
    }

    private function mailDomain(): string {
        $domain = trim((string)($this->settings['mail_from_domain'] ?? ''));
        if ($domain !== '') return $this->cleanDomain($domain);
        $appUrl = (string)(getenv('APP_URL') ?: '');
        $host = parse_url($appUrl, PHP_URL_HOST);
        if ($host && !str_contains($host, 'example')) return $this->cleanDomain($host);
        $siteSettings = (new SettingsService())->admin();
        $adminEmail = (string)($siteSettings['admin_email'] ?? '');
        if ($adminEmail === '') $adminEmail = (string)(getenv('ADMIN_EMAIL') ?: '');
        if (str_contains($adminEmail, '@')) return $this->cleanDomain(substr(strrchr($adminEmail, '@'), 1));
        $httpHost = (string)($_SERVER['HTTP_HOST'] ?? 'localhost.example');
        return $this->cleanDomain($httpHost);
    }

    private function cleanDomain(string $domain): string {
        $domain = strtolower(trim(preg_replace('/:\d+$/', '', $domain) ?? ''));
        return $domain !== '' ? $domain : 'localhost.example';
    }

    private function command($socket, string $command, array $codes): string {
        fwrite($socket, $command . "\r\n");
        return $this->expect($socket, $codes);
    }

    private function expect($socket, array $codes): string {
        $response = '';
        do {
            $line = fgets($socket, 515);
            if ($line === false) break;
            $response .= $line;
            $done = strlen($line) >= 4 && $line[3] === ' ';
        } while (empty($done));

        // An empty response means the socket timed out or was closed rather than the
        // server replying. Reporting a bare "SMTP error:" here hides the real cause.
        if (trim($response) === '') {
            $meta = stream_get_meta_data($socket);
            throw new \RuntimeException(!empty($meta['timed_out'])
                ? 'SMTP timed out waiting for the server. The port is most likely blocked by the host or a firewall.'
                : 'SMTP connection closed by the server before it replied. Check the port and encryption setting (465 uses SSL, 587 uses TLS/STARTTLS).');
        }

        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            // Hint is provider-aware: a Gmail app-password message is misleading when the
            // server is Hostinger, and vice versa.
            $host = strtolower((string)($this->settings['smtp_host'] ?? ''));
            $isGoogle = str_contains($host, 'gmail') || str_contains($host, 'google');
            $authHint = $isGoogle
                ? ' Gmail requires a 16-character App Password with 2-Step Verification enabled, not the account password.'
                : ' Check that this mailbox exists in your hosting control panel and that the password is exactly the mailbox password. The username must be the full email address.';
            $hint = match (true) {
                in_array($code, [535, 534], true) => ' Authentication was rejected.' . $authHint,
                in_array($code, [553, 550, 554], true) => ' The sender address was rejected. From Email must match the authenticated mailbox.',
                default => '',
            };
            throw new \RuntimeException('SMTP error ' . $code . ': ' . trim($response) . $hint);
        }
        return $response;
    }
}
