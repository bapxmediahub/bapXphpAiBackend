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
    private const PRIMARY_FAILURES_BEFORE_FAILOVER = 3;
    private const PRIMARY_ATTEMPT_TIMEOUT_MS = 6000;
    private const FALLBACK_ATTEMPT_TIMEOUT_MS = 12000;
    private const TOOL_CONVERSATION_TIMEOUT_NS = 32_000_000_000;
    private const ERROR_PREFIXES = ['AI request failed', 'No AI API key'];
    private int $transportError = 0;
    private ?string $failureCategory = null;
    private array $transferTimings = [];
    private int $toolCalls = 0;

    /** Bounded operational category; never includes provider messages or credentials. */
    public function failureCategory(): ?string { return $this->failureCategory; }

    /** Model identifier and numeric milestones for each attempt. */
    public function transferTimings(): array { return $this->transferTimings; }
    public function toolCalls(): int { return $this->toolCalls; }

    public static function classifyFailure(int $status, int $transportError): string {
        return match (true) {
            $transportError === 28 => 'timeout',
            $status === 0 => 'transport',
            $status === 401 || $status === 403 => 'authentication',
            $status === 429 => 'rate_limit',
            $status >= 500 => 'provider_server',
            default => 'provider_request',
        };
    }

    /** @return array{0:string,1:string} primary first, then the other Gemma model */
    public static function googleModelPair(string $preferred): array
    {
        $primary = in_array($preferred, SecretService::GOOGLE_MODELS, true)
            ? $preferred
            : SecretService::PRIMARY_MODEL;
        $fallback = $primary === SecretService::PRIMARY_MODEL
            ? SecretService::FALLBACK_MODEL
            : SecretService::PRIMARY_MODEL;
        return [$primary, $fallback];
    }

    /** @return array<int,array{model:string,timeout_ms:int}> */
    public static function googleAttemptPlan(string $preferred, bool $modelLocked = false): array
    {
        [$primary, $fallback] = self::googleModelPair($preferred);
        if ($modelLocked) {
            return [['model' => $primary, 'timeout_ms' => self::FALLBACK_ATTEMPT_TIMEOUT_MS]];
        }
        $plan = array_fill(0, self::PRIMARY_FAILURES_BEFORE_FAILOVER, [
            'model' => $primary,
            'timeout_ms' => self::PRIMARY_ATTEMPT_TIMEOUT_MS,
        ]);
        $plan[] = ['model' => $fallback, 'timeout_ms' => self::FALLBACK_ATTEMPT_TIMEOUT_MS];
        return $plan;
    }

    /** Authentication and malformed requests cannot be repaired by changing models. */
    public static function canFailOver(int $status, int $transportError): bool
    {
        return $transportError !== 0 || $status === 0 || $status === 404 || $status === 408
            || $status === 429 || $status >= 500;
    }

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

    /**
     * A conversation in which the model may look things up before answering.
     *
     * Without tools the model can only answer from whatever context was pre-computed and
     * pasted into the prompt: it could say how many orders exist, because that number
     * was in the blob, and could not say where order 9426 was, because nobody had
     * thought to include it. A bigger blob does not solve that — it costs tokens on
     * every message, is stale the moment it is built, and still only answers questions
     * somebody anticipated. Here the model is told what it may ask for and asks.
     *
     * The loop is bounded. A model that keeps calling tools would otherwise hold the
     * request open until PHP's time limit, and the owner would watch a spinner.
     *
     * Only Google is wired up: it is the configured provider, and a tool protocol
     * written for a provider nobody uses is a guess that would go stale untested.
     * Other providers fall back to a plain completion, which still answers.
     */
    public function completeWithTools(
        string $prompt,
        AgentToolRegistry $tools,
        int $maxTokens = 1024,
        float $temperature = 0.3,
        int $maxRounds = 4,
        ?string $modelOverride = null
    ): string {
        $this->failureCategory = null;
        $this->transferTimings = [];
        $this->toolCalls = 0;
        $config = $this->secrets->getModelConfig();
        if (($config['provider'] ?? '') !== 'google') return $this->complete($prompt, $maxTokens, $temperature);

        $key = trim((string)($config['apiKey'] ?? ''));
        if ($key === '') { $this->failureCategory = 'not_configured'; return 'No AI API key is configured. Set ai_api_key in Admin → Integrations, then try again.'; }
        if (!function_exists('curl_init')) { $this->failureCategory = 'transport'; return 'AI request failed: cURL is not available on this server.'; }

        $endpoint = rtrim((string)($config['endpoint'] ?? ''), '/');
        $activeModel = $modelOverride ?? (string)($config['model'] ?? SecretService::PRIMARY_MODEL);
        $modelLocked = false;
        $headers = ['Content-Type: application/json', 'x-goog-api-key: ' . $key];

        $contents = [['role' => 'user', 'parts' => [['text' => $this->withAnswerFence($prompt)]]]];

        $deadline = hrtime(true) + self::TOOL_CONVERSATION_TIMEOUT_NS;
        for ($round = 0; $round <= $maxRounds; $round++) {
            $finalRound = $round === $maxRounds;
            $request = [
                'contents' => $contents,
                'tools' => [['functionDeclarations' => $tools->declarations()]],
                'generationConfig' => [
                    'temperature' => $temperature,
                    'maxOutputTokens' => $maxTokens,
                    'thinkingConfig' => ['thinkingLevel' => 'MINIMAL'],
                ],
            ];
            // Keep the tool responses in history while asking for a final text answer.
            // https://ai.google.dev/gemini-api/docs/function-calling
            if ($finalRound) $request['toolConfig'] = ['functionCallingConfig' => ['mode' => 'NONE']];
            $payload = json_encode($request, JSON_UNESCAPED_SLASHES);

            $body = $this->postGoogleRouted(
                $endpoint, $activeModel, $payload, $headers, $status, $modelLocked, $deadline
            );
            if ($status !== 200 || $body === false) return self::describeFailure($status, $body, $this->transportError);

            $result = json_decode((string)$body, true);
            $parts = $result['candidates'][0]['content']['parts'] ?? [];

            $calls = [];
            foreach ($parts as $part) {
                if (isset($part['functionCall']['name'])) $calls[] = $part['functionCall'];
            }
            if ($calls === [] || $finalRound) { $answer = self::answerFromParts($parts); if (trim($answer) === '') $this->failureCategory = 'empty_response'; return $answer; }

            // Keep the model's own turn in the history, or the next request has a
            // function response answering a call the conversation no longer contains.
            $contents[] = ['role' => 'model', 'parts' => self::googleToolHistoryParts($parts)];
            $modelLocked = true;

            $responses = [];
            foreach ($calls as $call) {
                $name = (string)$call['name'];
                $args = is_array($call['args'] ?? null) ? $call['args'] : [];
                $result = $tools->has($name)
                    ? $tools->run($name, $args)
                    : ['error' => 'No such tool: ' . $name];
                if (!isset($result['error'])) $this->toolCalls++;
                $responses[] = self::googleFunctionResponse($call, $result);
            }
            $contents[] = ['role' => 'user', 'parts' => $responses];
        }

        $this->failureCategory = 'empty_response';
        return '';
    }

    /**
     * Preserve Google's object-shaped function arguments when replaying model history.
     *
     * The API returns an empty argument object as `{}`. json_decode(..., true) turns it
     * into PHP's empty array, and json_encode then changes it to `[]`. Google rejects
     * that second-round payload because FunctionCall.args uses the Struct/object shape.
     * Only the empty ambiguous value is converted; real nested argument lists remain
     * lists. Schema: https://ai.google.dev/api/generate-content#FunctionCall
     *
     * @param array<int,array<string,mixed>> $parts
     * @return array<int,array<string,mixed>>
     */
    public static function googleToolHistoryParts(array $parts): array
    {
        foreach ($parts as &$part) {
            if (isset($part['functionCall'])
                && is_array($part['functionCall'])
                && array_key_exists('args', $part['functionCall'])
                && $part['functionCall']['args'] === []) {
                $part['functionCall']['args'] = new \stdClass();
            }
        }
        unset($part);
        return $parts;
    }

    /**
     * Build the object-shaped response paired to a Google function call.
     *
     * @param array<string,mixed> $call
     * @param array<mixed> $result
     * @return array{functionResponse:array<string,mixed>}
     */
    public static function googleFunctionResponse(array $call, array $result): array
    {
        $response = [
            'name' => (string)($call['name'] ?? ''),
            // FunctionResponse.response is also a Struct. Keep list-valued tool output
            // under an object key rather than sending a top-level JSON list.
            'response' => array_is_list($result) ? ['result' => $result] : $result,
        ];
        if (trim((string)($call['id'] ?? '')) !== '') $response['id'] = (string)$call['id'];
        return ['functionResponse' => $response];
    }

    /** Model text, or a sentence explaining why there is none. */
    public function complete(string $prompt, int $maxTokens = 1024, float $temperature = 0.3, ?string $modelOverride = null): string
    {
        $this->failureCategory = null;
        $this->transferTimings = [];
        $config = $this->secrets->getModelConfig();
        $endpoint = rtrim((string)($config['endpoint'] ?? 'https://api.openai.com/v1'), '/');
        $model = $modelOverride ?? (string)($config['model'] ?? SecretService::PRIMARY_MODEL);
        $key = trim((string)($config['apiKey'] ?? ''));
        $provider = (string)($config['provider'] ?? 'openai');

        // Fail fast rather than sending a request that is certain to be rejected — an
        // absent key returned an opaque HTTP 400 that read as a model problem.
        if ($key === '') {
            $this->failureCategory = 'not_configured';
            return 'No AI API key is configured. Set ai_api_key in Admin → Integrations, then try again.';
        }
        if (!function_exists('curl_init')) {
            $this->failureCategory = 'transport';
            return 'AI request failed: cURL is not available on this server.';
        }

        // The configured model reasons before answering, and its reasoning is not
        // reliably flagged — sometimes it arrives as a thought part, sometimes as plain
        // prose with headings and numbered drafts. Asking it to fence the answer gives
        // one thing to look for that does not depend on guessing which sentences were
        // thinking. See ANSWER_OPEN below.
        $prompt = $this->withAnswerFence($prompt);

        if ($provider === 'google') {
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
            $modelLocked = false;
            $body = $this->postGoogleRouted(
                $endpoint, $model, $payload, $headers, $status, $modelLocked
            );
            if ($status !== 200 || $body === false) return self::describeFailure($status, $body, $this->transportError);
            $result = json_decode((string)$body, true);
            $answer = self::answerFromParts($result['candidates'][0]['content']['parts'] ?? []);
            if (trim($answer) === '') $this->failureCategory = 'empty_response';
            return $answer;
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
        if ($status !== 200 || $body === false) return self::describeFailure($status, $body, $this->transportError);
        $result = json_decode((string)$body, true);
        $answer = (string)($result['choices'][0]['message']['content'] ?? '');
        if (trim($answer) === '') $this->failureCategory = 'empty_response';
        return $answer;
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

    /**
     * Try the preferred Gemma model three times before moving to the other one.
     *
     * Attempts are deliberately bounded to 30 seconds in total (3x6s + 12s), below
     * the chat UI's 35-second deadline. After a tool call, subsequent rounds stay on
     * the model that issued it, so model-specific history is never replayed elsewhere. Invalid credentials and malformed payloads fail immediately
     * because switching models cannot repair them.
     *
     * @return string|false
     */
    private function postGoogleRouted(
        string $endpoint,
        string &$activeModel,
        string $payload,
        array $headers,
        ?int &$status,
        bool &$modelLocked,
        ?int $deadline = null
    ) {
        $initialModel = $activeModel;
        $body = false;
        foreach (self::googleAttemptPlan($activeModel, $modelLocked) as $attempt) {
            $timeoutMs = $attempt['timeout_ms'];
            if ($deadline !== null) {
                $timeoutMs = min($timeoutMs, (int)(($deadline - hrtime(true)) / 1_000_000));
                if ($timeoutMs <= 0) {
                    $status = 0;
                    $this->transportError = 28;
                    $this->failureCategory = 'timeout';
                    return false;
                }
            }
            $model = $attempt['model'];
            $url = $endpoint . '/' . rawurlencode($model) . ':generateContent';
            $body = $this->post($url, $payload, $headers, $status, $timeoutMs, true, $model);
            if ($status === 200 && $body !== false) {
                $activeModel = $model;
                if ($model !== $initialModel) $modelLocked = true;
                return $body;
            }
            if (!self::canFailOver($status, $this->transportError)) return $body;
        }
        return $body;
    }

    /** @return string|false */
    private function post(
        string $url,
        string $payload,
        array $headers,
        ?int &$status,
        int $timeoutMs = 20000,
        bool $appendTiming = false,
        ?string $model = null
    )
    {
        if (!$appendTiming) $this->transferTimings = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => min(10000, $timeoutMs),
        ]);
        $body = curl_exec($ch);
        $this->transportError = curl_errno($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // PHP reports these milestones in seconds and uses zero for unreached stages.
        // https://www.php.net/manual/en/function.curl-getinfo.php
        $this->transferTimings[] = [
            'model' => $model,
            'status' => $status,
            'curl_error' => $this->transportError,
            'dns_ms' => 1000 * curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME),
            'connect_ms' => 1000 * curl_getinfo($ch, CURLINFO_CONNECT_TIME),
            'ready_ms' => 1000 * curl_getinfo($ch, CURLINFO_PRETRANSFER_TIME),
            'first_byte_ms' => 1000 * curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME),
            'total_ms' => 1000 * curl_getinfo($ch, CURLINFO_TOTAL_TIME),
        ];
        unset($ch);
        $this->failureCategory = ($body === false || $status !== 200) ? self::classifyFailure($status, $this->transportError) : null;
        return $body;
    }

    /**
     * Provide actionable status guidance without exposing provider response bodies,
     * which may contain sensitive request data or infrastructure details.
     */
    public static function describeFailure(int $status, $body, int $transportError = 0): string
    {
        $hint = match (true) {
            $status === 400 => ' The provider rejected the request. Check the integration configuration.',
            in_array($status, [401, 403], true) => ' The API key was rejected. Set a valid ai_api_key in Admin → Integrations.',
            $status === 404 => ' The configured model or endpoint was not found. Check the integration configuration.',
            $status === 429 => ' Rate limit or quota exceeded for this API key.',
            in_array($status, [500, 503], true) => ' The provider could not complete the request. Try again later.',
            // https://curl.se/libcurl/c/libcurl-errors.html
            // A timeout does not prove the provider never received the request.
            $status === 0 => match ($transportError) {
                28 => ' The AI request timed out before a complete response was received. Try again later; this does not prove the provider was unreachable.',
                5, 6 => ' The server could not resolve the provider or proxy hostname. Check DNS and the configured endpoint.',
                7 => ' The server could not establish a connection to the provider or proxy. Check outbound network access.',
                35 => ' The TLS handshake failed. Check server TLS configuration; do not disable certificate verification.',
                60, 77 => ' TLS certificate verification failed. Check the server CA trust configuration; do not disable certificate verification.',
                default => ' No complete HTTP response was received. Check server transport diagnostics and try again later.',
            },
            default => '',
        };
        return trim('AI request failed (HTTP ' . $status . ').' . $hint);
    }
}
