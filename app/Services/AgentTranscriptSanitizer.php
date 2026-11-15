<?php
namespace App\Services;

/** Scrub obvious secrets before persisting user/agent transcript text. */
final class AgentTranscriptSanitizer {
    public static function scrub(string $text): string {
        $patterns = [
            '/\bsk-(?:proj-)?[A-Za-z0-9_-]{12,}\b/',
            '/\bBearer\s+[A-Za-z0-9._~-]{12,}/i',
            '/\b(?:password|api[_ -]?key|access[_ -]?token|secret|razorpay_signature)\s*[:=]\s*[^\s,;]+/i',
            '/-----BEGIN (?:RSA |EC )?PRIVATE KEY-----[\s\S]*?-----END (?:RSA |EC )?PRIVATE KEY-----/i',
        ];
        return preg_replace($patterns,'[REDACTED]', $text) ?? '';
    }
}
