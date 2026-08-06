<?php
namespace App\Services;

/**
 * The one place the product talks to a model provider.
 *
 * There used to be two. AdminController resolved the endpoint and model through
 * SecretService::getModelConfig(), so it used whatever was saved in Admin →
 * Integrations. SupportBotService built its own URL, hardcoding
 * generativelanguage.googleapis.com and defaulting to gemma-4-31b-it, so it ignored
 * that configuration entirely: changing the model in the admin fixed the admin agent
 * and left the customer-facing widget calling a model that answered 404. Every reply
 * on the public site fell back to a canned menu, and nothing in the UI said why.
 *
 * Callers get raw model text and decide how to clean and present it, because the two
 * agents want different fallbacks. Failures come back as a readable sentence rather
 * than an exception — a support widget must not 500 because a key expired — and
 * isError() lets a caller tell a failure from an answer.
 */
final class AiClient
{
    private const ERROR_PREFIXES = ['AI request failed', 'No AI API key'];

    public function __construct(private SecretService $secrets = new SecretService()) {}

    /** True when a key is present, so callers can skip the round trip. */
    public function configured(): bool
    {
        return trim((string)($this->secrets->getModelConfig()['apiKey'] ?? '')) !== '';
    }

    /** Model text, or null when the provider could not be reached. */
    public function completeOrNull(string $prompt, int $maxTokens = 1024, float $temperature = 0.3): ?string
    {
        $answer = $this->complete($prompt, $maxTokens, $temperature);
        if (self::isError($answer)) return null;
        return trim($answer) !== '' ? $answer : null;
    }

    /** Model text, or a sentence explaining why there is none. */
    public function complete(string $prompt, int $maxTokens = 1024, float $temperature = 0.3): string
    {
        $config = $this->secrets->getModelConfig();
        $endpoint = rtrim((string)($config['endpoint'] ?? 'https://api.openai.com/v1'), '/');
        $model = (string)($config['model'] ?? 'gemma-4-31b-it');
        $key = trim((string)($config['apiKey'] ?? ''));
        $provider = (string)($config['provider'] ?? 'openai');

        // Fail fast rather than sending a request that is certain to be rejected — an
        // absent key returned an opaque HTTP 400 that read as a model problem.
        if ($key === '') {
            return 'No AI API key is configured. Set ai_api_key in Admin → Integrations, then try again.';
        }
        if (!function_exists('curl_init')) {
            return 'AI request failed: cURL is not available on this server.';
        }

        // The configured model reasons before answering, and its reasoning is not
        // reliably flagged — sometimes it arrives as a thought part, sometimes as plain
        // prose with headings and numbered drafts. Asking it to fence the answer gives
        // one thing to look for that does not depend on guessing which sentences were
        // thinking. See ANSWER_OPEN below.
        $prompt = $this->withAnswerFence($prompt);

        if ($provider === 'google') {
            $url = $endpoint . '/' . rawurlencode($model) . ':generateContent';
            $payload = json_encode([
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'temperature' => $temperature,
                    'maxOutputTokens' => $maxTokens,
                    // thinkingLevel must be nested inside thinkingConfig; at the top of
                    // generationConfig the endpoint answers HTTP 400 "Unknown name
                    // thinkingLevel". includeThoughts:false is accepted and then ignored
                    // by this model family, and thinkingBudget is rejected. MINIMAL is
                    // the one setting that actually stops the reasoning.
                    //
                    // This matters for cost and for truncation: on short prompts the
                    // thinking is 85-95% of the generated tokens, so it exhausts
                    // maxOutputTokens and the answer is cut off mid-sentence.
                    'thinkingConfig' => ['thinkingLevel' => 'MINIMAL'],
                ],
            ], JSON_UNESCAPED_SLASHES);
            $headers = ['Content-Type: application/json', 'x-goog-api-key: ' . $key];
            $body = $this->post($url, $payload, $headers, $status);
            if ($status !== 200 || $body === false) return self::describeFailure($status, $body);
            $result = json_decode((string)$body, true);
            return self::answerFromParts($result['candidates'][0]['content']['parts'] ?? []);
        }

        $url = $endpoint . '/chat/completions';
        $payload = json_encode([
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => $maxTokens,
        ], JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type: application/json'];
        if ($provider === 'anthropic') {
            $headers[] = 'x-api-key: ' . $key;
            $headers[] = 'anthropic-version: 2023-06-01';
        } else {
            $headers[] = 'Authorization: Bearer ' . $key;
        }
        $body = $this->post($url, $payload, $headers, $status);
        if ($status !== 200 || $body === false) return self::describeFailure($status, $body);
        $result = json_decode((string)$body, true);
        return (string)($result['choices'][0]['message']['content'] ?? '');
    }

    private const ANSWER_OPEN = '<final_answer>';
    private const ANSWER_CLOSE = '</final_answer>';

    /** Tells the model where to put the answer, so the reasoning can be dropped. */
    private function withAnswerFence(string $prompt): string
    {
        return $prompt . "\n\nThink first if you need to. Then write ONLY the finished answer between "
            . self::ANSWER_OPEN . " and " . self::ANSWER_CLOSE
            . ", with nothing after the closing tag. Do not put your reasoning, drafts or notes inside the tags.";
    }

    /**
     * The fenced answer, or null when the model did not fence one.
     *
     * The last fence wins: a model that shows its working sometimes writes the tags out
     * once while planning before using them for real.
     */
    public static function fencedAnswer(string $text): ?string
    {
        $open = preg_quote(self::ANSWER_OPEN, '/');
        $close = preg_quote(self::ANSWER_CLOSE, '/');
        // The negative lookbehind skips the model quoting the tag names back while it
        // plans — "write the answer between `<final_answer>` and `</final_answer>`" —
        // which otherwise matches, and yields the two words between the two mentions.
        $pattern = '/(?<!`)' . $open . '(.*?)(?<!`)' . $close . '/s';
        if (!preg_match_all($pattern, $text, $matches) || empty($matches[1])) return null;

        $candidates = array_values(array_filter(
            array_map('trim', $matches[1]),
            fn(string $c): bool => $c !== ''
        ));
        if ($candidates === []) return null;
        return (string)end($candidates);
    }

    /**
     * The answer, with the model's private reasoning left out.
     *
     * This client used to read parts[0]. For a reasoning model parts[0] is the
     * *thought*, and the answer is a later part:
     *
     *     "parts": [
     *       { "text": "The user wants me to…", "thought": true },
     *       { "text": "the actual answer" }
     *     ]
     *
     * So every reply handed back was the model thinking out loud. That is the whole of
     * the "agent leaks its scaffold" bug, and also the whole of the "agent has stopped
     * answering" bug: AiReplyCleaner correctly recognised the reasoning as scaffold and
     * stripped it, leaving nothing, so both agents fell back to a canned reply. One
     * array index.
     *
     * A response that is nothing but thoughts still returns them rather than an empty
     * string, because an empty answer tells a caller nothing about what went wrong.
     *
     * @param array<int,array<string,mixed>> $parts
     */
    public static function answerFromParts(array $parts): string
    {
        $answer = '';
        $thoughts = '';
        foreach ($parts as $part) {
            $text = (string)($part['text'] ?? '');
            if ($text === '') continue;
            if (!empty($part['thought'])) { $thoughts .= $text; continue; }
            $answer .= $text;
        }
        $answer = trim($answer) !== '' ? trim($answer) : trim($thoughts);
        // The fence is the reliable signal; the thought flag is not always set.
        return self::fencedAnswer($answer) ?? $answer;
    }

    /** True when the string is one of this client's failure sentences, not an answer. */
    public static function isError(string $text): bool
    {
        foreach (self::ERROR_PREFIXES as $prefix) {
            if (str_starts_with($text, $prefix)) return true;
        }
        return false;
    }

    /** @return string|false */
    private function post(string $url, string $payload, array $headers, ?int &$status)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $body;
    }

    /**
     * A failure the admin can act on. The provider's own message is kept, because
     * "the model does not exist" and "the key was rejected" need different fixes and
     * a bare status code told the admin neither.
     */
    public static function describeFailure(int $status, $body): string
    {
        $detail = '';
        $decoded = is_string($body) ? json_decode($body, true) : null;
        if (is_array($decoded)) {
            $detail = (string)($decoded['error']['message'] ?? $decoded['error']['type'] ?? $decoded['message'] ?? '');
        }
        if ($detail === '' && is_string($body) && trim($body) !== '') {
            $detail = mb_substr(trim(strip_tags($body)), 0, 300);
        }
        $hint = match (true) {
            $status === 400 => ' Check the model name and that the API key is set in Admin → Integrations.',
            in_array($status, [401, 403], true) => ' The API key was rejected. Set a valid ai_api_key in Admin → Integrations.',
            $status === 404 => ' The model or endpoint does not exist for this provider.',
            $status === 429 => ' Rate limit or quota exceeded for this API key.',
            $status === 0   => ' The request never reached the provider. Check outbound network access.',
            default => '',
        };
        return trim('AI request failed (HTTP ' . $status . ').' . $hint . ($detail !== '' ? ' ' . $detail : ''));
    }
}
