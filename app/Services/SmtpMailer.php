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

    public function buildMessage(string $to, string $subject, string $html, ?string $messageId = null): string {
        $fromEmail = $this->fromEmail();
        $fromName = $this->fromName();
        // Replies go to the sending mailbox (support@), not the admin's personal inbox.
        // admin_notification_email is the destination for admin *notifications*, which
        // is a different thing from where a customer's reply should land.
        $replyTo = $this->replyToEmail();
        foreach ([$to,$fromEmail,$replyTo] as $address) {
            if (!filter_var($address,FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n\0]/',$address)) throw new \InvalidArgumentException('Invalid email address.');
        }
        foreach ([$subject,$fromName] as $header) {
            if (preg_match('/[\r\n\0]/',$header)) throw new \InvalidArgumentException('Invalid mail header.');
        }
        $messageId ??= '<'.bin2hex(random_bytes(16)).'@'.substr(strrchr($fromEmail,'@'),1).'>';
        if (!preg_match('/^<[A-Za-z0-9._-]{1,100}@[A-Za-z0-9.-]{1,253}>$/D',$messageId)) throw new \InvalidArgumentException('Invalid mail message identity.');
        $boundary = 'sps_' . bin2hex(random_bytes(12));
        $plain = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)));
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: ' . $messageId,
            'To: ' . $to,
            'From: ' . $this->formatAddress($fromEmail, $fromName),
            'Reply-To: ' . $replyTo,
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        return implode("\r\n", $headers)
            . "\r\n\r\n--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . self::encodeBody($plain)
            . "\r\n\r\n--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . self::encodeBody($html)
            . "\r\n\r\n--{$boundary}--\r\n";
    }

    private static function encodeBody(string $body): string {
        // RFC 2045 sections 6.6/6.8: canonical CRLF and at most 76 encoded
        // characters per line. Raw one-line HTML exceeded SMTP line limits and
        // could be rewritten in transit, invalidating the provider's DKIM hash.
        // https://www.rfc-editor.org/rfc/rfc2045#section-6.8
        $canonical = preg_replace('/\r\n|\r|\n/', "\r\n", $body);
        return rtrim(chunk_split(base64_encode($canonical), 76, "\r\n"), "\r\n");
    }

    /** SMTP acknowledgement is transport acceptance, never proof of inbox delivery. */
    public function send(string $to, string $subject, string $html, ?string $messageId = null): array {
        if (!$this->configured()) throw new \RuntimeException('SMTP is not configured.');
        $messageId ??= '<'.bin2hex(random_bytes(16)).'@'.substr(strrchr($this->fromEmail(),'@'),1).'>';
        // Validate the envelope and MIME headers before opening any connection.
        $body = preg_replace('/^\./m','..',$this->buildMessage($to,$subject,$html,$messageId));
        $host = (string)$this->settings['smtp_host'];
        $port = (int)$this->settings['smtp_port'];
        $secure = strtolower((string)($this->settings['smtp_encryption'] ?? 'tls'));
        $target = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $socket = @stream_socket_client($target,$errno,$errstr,20,STREAM_CLIENT_CONNECT);
        if (!$socket) throw new \RuntimeException('Could not connect to configured SMTP host and port.');
        $awaitingAcceptance=false;
        try {
            stream_set_timeout($socket,20);
            $this->expect($socket,[220]);
            $domain=$this->cleanDomain((string)($_SERVER['SERVER_NAME']??'localhost'));
            if (!preg_match('/^[a-z0-9.-]+$/D',$domain)) $domain='localhost';
            $this->command($socket,'EHLO '.$domain,[250]);
            if ($secure === 'tls') {
                $this->command($socket,'STARTTLS',[220]);
                if (!@stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new \RuntimeException('Unable to start SMTP TLS.');
                $this->command($socket,'EHLO '.$domain,[250]);
            }
            $this->command($socket,'AUTH LOGIN',[334]);
            $this->command($socket,base64_encode((string)$this->settings['smtp_username']),[334]);
            $this->command($socket,base64_encode((string)$this->settings['smtp_password']),[235]);
            $this->command($socket,'MAIL FROM:<'.$this->fromEmail().'>',[250]);
            $this->command($socket,'RCPT TO:<'.$to.'>',[250,251]);
            $this->command($socket,'DATA',[354]);
            $awaitingAcceptance=true;
            $this->writeAll($socket,$body."\r\n.\r\n");
            $this->expect($socket,[250]);
            $awaitingAcceptance=false;
            $receipt=['accepted'=>true,'smtp_code'=>250,'message_id'=>$messageId,'accepted_at'=>gmdate('c')];
            // RFC 5321 §6.1: responsibility transferred at positive DATA acknowledgement.
            // A lost QUIT acknowledgement must never convert that into a retryable failure.
            try { stream_set_timeout($socket,2);$this->command($socket,'QUIT',[221]); }
            catch (\Throwable) { $receipt['cleanup_warning']='quit_unconfirmed'; }
            return $receipt;
        } catch (\Throwable $error) {
            if ($awaitingAcceptance && ($error->getCode()<400 || $error->getCode()>599)) {
                throw new SmtpDeliveryUncertainException('SMTP acceptance was not observed. Do not resend until the outcome is checked.',0,$error);
            }
            throw $error;
        } finally { if (is_resource($socket)) fclose($socket); }
    }

    private function writeAll($socket,string $value): void {
        $offset=0;$length=strlen($value);
        while($offset<$length){$written=@fwrite($socket,substr($value,$offset));
            if($written===false||$written===0)throw new \RuntimeException('SMTP connection write failed.');
            $offset+=$written;
        }
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
        $this->writeAll($socket, $command . "\r\n");
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
            throw new \RuntimeException('SMTP error ' . $code . ': ' . trim($response) . $hint, $code);
        }
        return $response;
    }
}
