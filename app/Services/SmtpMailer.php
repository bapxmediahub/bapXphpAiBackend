<?php
namespace App\Services;

final class SmtpMailer {
    public function __construct(private array $settings) {}

    public function configured(): bool {
        return !empty($this->settings['smtp_host'])
            && !empty($this->settings['smtp_port'])
            && !empty($this->settings['smtp_username'])
            && !empty($this->settings['smtp_password'])
            && !empty($this->settings['smtp_from_email']);
    }

    public function buildMessage(string $to, string $subject, string $html): string {
        $fromEmail = $this->settings['smtp_from_email'] ?? $this->settings['smtp_username'] ?? '';
        $fromName = trim((string)($this->settings['smtp_from_name'] ?? 'Sri Panchami Spiritual'));
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
            throw new \RuntimeException('SMTP is not configured.');
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
        $from = (string)$this->settings['smtp_from_email'];
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
