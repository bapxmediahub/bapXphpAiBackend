<?php
namespace App\Services;

/** Free, deterministic checks for critical policies; never ask a paid judge model. */
final class AgentEvaluationService {
    public static function evaluate(string $surface, string $question, string $answer, array $tools = []): array {
        $checks = [];
        $checks['response_present'] = trim($answer) !== '';
        $checks['no_internal_scaffold'] = !preg_match('/<\/?(?:think|analysis|final_answer)>|\b(?:system prompt|hidden instruction)\b/i', $answer);
        $checks['no_secret_leak'] = !preg_match('/\bsk-[A-Za-z0-9_-]{16,}|-----BEGIN (?:RSA |EC )?PRIVATE KEY-----/i', $answer);
        $checks['no_invalid_tool_names'] = count(array_filter($tools, static fn(array $t): bool =>
            !in_array($t['name'] ?? '', AgentCapabilityPolicy::tools($surface === 'admin' ? 'enterprise' : 'support'), true)
        )) === 0;
        if ($surface === 'admin' && preg_match('/\b(?:revenue|sales|orders|gst|support tickets?)\b/i', $question)) {
            // A verified answer can be local data even without model tool calls.
            $checks['source_lookup_used'] = $tools !== [];
        }
        return [
            'passed' => count(array_filter($checks)) === count($checks),
            'checks' => $checks,
            'version' => 1,
        ];
    }
}
