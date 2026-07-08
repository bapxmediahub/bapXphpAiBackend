<?php
namespace App\Services;

final class SmtpMailer {
    public function __construct(private array $settings) {}

    public function configured(): bool {
        return $this->smtpConfigured() || $this->phpMailConfigured();
    }

    public function fromEmail(): string {
        $from = trim((string)($this->settings['mail_from_email'] ?? ''));
        if ($from !== '') return $from;
        $from = trim((string)($this->settings['smtp_from_email'] ?? ''));
        if ($from !== '') return $from;
        return 'noreply@' . $this->mailDomain();
    }

    public function transport(): string {
        return $this->smtpConfigured() ? 'smtp' : 'php_mail';
    }

    private function smtpConfigured(): bool {
        return !empty($this->settings['smtp_host'])
            && !empty($this->settings['smtp_port'])
            && !empty($this->settings['smtp_username'])
            && !empty($this->settings['smtp_password'])
            && $this->fromEmail() !== '';
    }

    private function phpMailConfigured(): bool {
        return function_exists('mail') && $this->fromEmail() !== '';
    }

    public function buildMessage(string $to, string $subject, string $html): string {
        $fromEmail = $this->fromEmail();
        $fromName = $this->fromName();
        $replyTo = trim((string)($this->settings['admin_notification_email'] ?? $fromEmail));
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
            throw new \RuntimeException('Email delivery is not configured.');
        }
        if (!$this->smtpConfigured()) {
            $this->sendWithPhpMail($to, $subject, $html);
            return;
        }
        $host = (string)$this->settings['smtp_host'];
        $port = (int)$this->settings['smtp_port'];
        $secure = strtolower((string)($this->settings['smtp_encryption'] ?? 'tls'));
        $target = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $socket = @stream_socket_client($target, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        if (!$socket) {
            throw new \RuntimeException('SMTP connection failed: ' . $errstr);
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
        fwrite($socket, $this->buildMessage($to, $subject, $html) . "\r\n.\r\n");
        $this->expect($socket, [250]);
        $this->command($socket, 'QUIT', [221]);
        fclose($socket);
    }

    private function formatAddress(string $email, string $name): string {
        return $name !== '' ? $name . ' <' . $email . '>' : $email;
    }

    private function sendWithPhpMail(string $to, string $subject, string $html): void {
        $fromEmail = $this->fromEmail();
        $fromName = $this->fromName();
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->formatAddress($fromEmail, $fromName),
            'Reply-To: ' . trim((string)($this->settings['admin_notification_email'] ?? $fromEmail)),
            'X-Mailer: PHP/' . PHP_VERSION,
        ];
        $ok = @mail($to, $subject, $html, implode("\r\n", $headers), '-f' . $fromEmail);
        if (!$ok) {
            throw new \RuntimeException('PHP mail() delivery failed.');
        }
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
        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new \RuntimeException('SMTP error: ' . trim($response));
        }
        return $response;
    }
}
