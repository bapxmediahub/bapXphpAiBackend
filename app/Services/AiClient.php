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
    private const MODEL_ATTEMPT_TIMEOUT_MS = 12000;
    private const GENERATION_TIMEOUT_NS = 110_000_000_000;
    public const ATTEMPTS_PER_MODEL = 3;
    public const MAX_PROVIDER_CYCLES = 2;
    public const MAX_TRANSFERS = 32;
    public const CLIENT_TIMEOUT_MS = 120000;
    public static function agentLimits(): array {
        return ['max_model_calls'=>self::MAX_TRANSFERS,'max_duration_ms'=>115000];
    }
    private const FINALIZE_RESERVE_MS = 2000;
    private const MIN_ATTEMPT_MS = 1000;
    private const MAX_TOOL_CALLS_PER_ROUND = 8;
    private const ERROR_PREFIXES = ['AI request failed', 'No AI API key'];
    private int $transportError = 0;
    private ?int $retryAfterMs = null;
    private ?string $quotaKind = null;
    private int $inferenceRound = 1;
    private ?string $failureCategory = null;
    private array $transferTimings = [];
    private int $toolCalls = 0;
    private bool $toolExchangeStarted = false;
    private int $cycle = 1;
    private int $modelAttempt = 1;
    private array $activeConnection = [];
    // Health identity needs the selected credential. Keep it out of transfer diagnostics.
    private array $activeHealthConnection = [];
    private bool $bypassProviderHolds = false;
    // Request-local observations only. Never store raw tool data or send it to
    // a different credential/endpoint as part of recovery.
    private bool $captureReadEvidence = false;
    private bool $readEvidenceEligible = true;
    private bool $synthesisRecovery = false;
    private array $readEvidence = [];
    private ?string $exchangeModel = null;


    /** Bounded operational category; never includes provider messages or credentials. */
    public function failureCategory(): ?string { return $this->failureCategory; }

    /** Model identifier and numeric milestones for each attempt. */
    public function transferTimings(): array { return $this->transferTimings; }
    public function toolCalls(): int { return $this->toolCalls; }

    public static function classifyFailure(int $status, int $transportError): string {
        return match (true) {
            $transportError === 28 => 'timeout',
            $transportError !== 0 || $status === 0 => 'transport',
            $status === 401 || $status === 403 => 'authentication',
            $status === 429 => 'rate_limit',
            $status >= 500 => 'provider_server',
            default => 'provider_request',
        };
    }

    /** DNS/connect, interrupted transfers, and transient HTTP failures can recover.
     * An alternate model on the same endpoint cannot fix TLS verification or credentials. */
    public static function canFailOver(int $status, int $transportError): bool
    {
        if (in_array($status, [400,401,403,422], true)) return false;
        if ($transportError !== 0) return in_array($transportError, [5,6,7,18,28,52,55,56,92], true);
        return in_array($status, [0,404,408,429,500,502,503,504], true);
    }

    public function __construct(private SecretService $secrets = new SecretService(),
        private ?AiProviderHealthService $health = null) {
        if (getenv('BAPX_TEST_MODE')!=='1') $this->health ??= new AiProviderHealthService();
    }

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
     * Every connection uses Chat Completions function calls and tool result
     * messages. Provider-specific continuation fields stay on the active connection.
     */
    public function completeWithTools(
        string|AgentModelTurn $prompt,
        AgentToolRegistry|RuntimeAgentToolRegistry $tools,
        int $maxTokens = 1024,
        float $temperature = 0.3,
        int $maxRounds = 4,
        ?string $modelOverride = null
    ): string {
        return $this->route($prompt,$maxTokens,$temperature,$modelOverride,$tools,$maxRounds);
    }

    /** Model text, or a sentence explaining why there is none. */
    public function complete(string $prompt, int $maxTokens = 1024, float $temperature = 0.3, ?string $modelOverride = null): string
    {
        return $this->route($prompt,$maxTokens,$temperature,$modelOverride,null,0);
    }

    /** Diagnostic choices retain connection identity even when model IDs overlap. */
    public static function probeTargets(array $plan): array {
        $targets=[];
        foreach($plan as $connection) {
            foreach($connection['models'] as $model) {
                $targets[]=['target'=>$connection['provider'].'|'.$model,'provider'=>$connection['provider'],'model'=>$model];
            }
        }
        return $targets;
    }

    public static function probePlan(array $plan,string $target): array {
        foreach($plan as $connection) {
            foreach($connection['models'] as $model) {
                if($target===$connection['provider'].'|'.$model) {
                    $connection['models']=[$model];$connection['model']=$model;
                    return [$connection];
                }
            }
        }
        return [];
    }

    public static function probeSucceeded(string $answer,string $expected,int $calls,bool $withTool): bool {
        return !self::isError($answer)&&hash_equals($expected,trim($answer))&&(!$withTool||$calls>0);
    }

    /** Test real inference, optionally a synthetic read-only tool round trip. No business records. */
    public function probe(string $target='',bool $withTool=false): array {
        $plan=$this->secrets->getProviderPlan();
        if($target!=='')$plan=self::probePlan($plan,$target);
        $expected=$withTool?'probe-'.bin2hex(random_bytes(8)):'connection ok';
        $registry=$withTool?new class($expected) extends AgentToolRegistry {
            public function __construct(private string $challenge) {}
            public function declarations(): array {
                return [['name'=>'connection_probe','description'=>'Return the synthetic verification code. No business data is accessed.',
                    'parameters'=>['type'=>'object','properties'=>(object)[],'additionalProperties'=>false]]];
            }
            public function run(string $name,array $args): array {
                if($name!=='connection_probe'||$args!==[])return ['error'=>'Invalid diagnostic call'];
                return ['challenge'=>$this->challenge];
            }
        }:null;
        $prompt=$withTool?'Call connection_probe with no arguments, then return exactly its challenge value as your final answer.':'Reply with exactly: connection ok';
        $answer=$this->route($prompt,256,0.0,null,$registry,$withTool?2:0,$plan,true);
        $ok=self::probeSucceeded($answer,$expected,$this->toolCalls,$withTool);
        $attempts=$this->transferTimings;$last=end($attempts)?:[];
        $reason=$ok?null:($this->failureCategory??($withTool&&$this->toolCalls===0?'tool_not_called':'unexpected_probe_answer'));
        return ['ok'=>$ok,'mode'=>$withTool?'tools':'text','model'=>$last['model']??($plan[0]['model']??''),
            'provider'=>$last['provider']??($plan[0]['provider']??''),
            'message'=>$ok?($withTool?'Verified tool round trip.':'Verified text response.'):self::probeFailureMessage($reason,$last),
            'reason'=>$reason,'timings'=>$attempts,'tool_calls'=>$withTool?$this->toolCalls:null];
    }

    private static function probeFailureMessage(string $reason, array $last): string
    {
        if ($reason === 'authentication' && ($last['curl_error'] ?? null) === 0
            && in_array($last['status'] ?? null, [401, 403], true)) {
            // Use the observed connection and status, never the upstream body or URL.
            return self::describeFailure($last['status'], false, 0, $last['provider'] ?? null);
        }
        return $reason === 'not_configured' ? 'No matching enabled connection.' : 'Probe failed: '.$reason.'.';
    }

    private function route(string|AgentModelTurn $prompt,int $maxTokens,float $temperature,?string $override,
        AgentToolRegistry|RuntimeAgentToolRegistry|null $tools,int $rounds,?array $selectedPlan=null,bool $diagnostic=false): string {
        $this->failureCategory=null;$this->transferTimings=[];$this->toolCalls=0;$this->toolExchangeStarted=false;$this->activeConnection=[];$this->activeHealthConnection=[];
        $this->cycle=1;$this->modelAttempt=1;$this->inferenceRound=1;$this->quotaKind=null;$this->retryAfterMs=null;
        $this->bypassProviderHolds = $diagnostic || $override !== null;
        $this->captureReadEvidence = !$this->bypassProviderHolds && $prompt instanceof AgentModelTurn
            && $tools instanceof RuntimeAgentToolRegistry;
        $this->readEvidenceEligible = true;
        $this->synthesisRecovery = false;
        $this->readEvidence = [];
        $this->exchangeModel = null;

        $plan=$selectedPlan??$this->secrets->getProviderPlan();
        // A deliberate diagnostic model probe must not silently switch model or provider.
        if($override!==null){
            $plan=array_values(array_filter($plan,static fn(array $c):bool=>in_array($override,$c['models'],true)));
            $plan=array_slice($plan,0,1);
            if($plan){$plan[0]['models']=[$override];$plan[0]['model']=$override;}
        }
        if(!$plan){$this->failureCategory='not_configured';return 'No AI API key or enabled model is configured. Check Admin → Integrations.';}
        $configuredPlan = $plan;
        if (!$this->bypassProviderHolds && $this->health !== null) {
            // Prefer ready models. If all are held, let the existing bounded
            // gate decide whether a short hold can expire during this turn.
            $plan = $this->health->availablePlan($plan, null, true) ?: $plan;
        }
        if(!function_exists('curl_init')){$this->failureCategory='transport';return 'AI request failed: cURL is not available on this server.';}
        $deadline=hrtime(true)+self::GENERATION_TIMEOUT_NS;
        $chatPrompt = is_string($prompt) ? $this->withAnswerFence($prompt) : $prompt;
        try {
            $answer = $this->completeWithFallbacks($plan, $deadline, !$diagnostic && $override === null,
                fn(array $config): string => $this->completeCompatible(
                    $config, $chatPrompt, $maxTokens, $override, $tools, $rounds, $deadline, temperature: $temperature
                ));
            if ($this->captureReadEvidence && $prompt instanceof AgentModelTurn) {
                return $this->recoverReadSynthesis($answer,$configuredPlan,$prompt,$maxTokens,$temperature,$deadline);
            }
            return $answer;
        } finally {
            // Raw observations belong only to this request, not traces or the next turn.
            $this->readEvidence = [];
            $this->captureReadEvidence = false;
        }
    }

    /** A new text-only request, not a resumed or replayed tool exchange.
     * At most one alternate explicitly configured model on the original
     * connection. The same provider hold and trace budgets still apply. */
    private function recoverReadSynthesis(string $answer,array $plan,AgentModelTurn $prompt,
        int $maxTokens,float $temperature,int $deadline): string {
        if (!$this->toolExchangeStarted || !$this->readEvidenceEligible || $this->exchangeModel === null
            || $this->readEvidence === [] || !in_array($this->failureCategory,
                ['timeout','transport','provider_server','rate_limit','provider_cooldown'],true)
            || in_array($this->quotaKind,['daily','zero'],true)
            || self::remainingProviderMs($deadline)<self::MIN_ATTEMPT_MS
            || count($this->transferTimings)>=self::MAX_TRANSFERS) return $answer;
        $last=end($this->transferTimings)?:[];
        if ($this->failureCategory!=='provider_cooldown'
            && !self::canFailOver((int)($last['status']??0),(int)($last['curl_error']??0))) return $answer;
        if (!array_filter($this->readEvidence,static fn(array $item):bool=>!in_array($item['name'],['tool_search','read_runtime_skill'],true))) return $answer;
        foreach ($plan as $connection) {
            if ($connection['provider']!==($this->activeHealthConnection['provider']??null)
                || $connection['endpoint']!==($this->activeHealthConnection['endpoint']??null)
                || !hash_equals($connection['apiKey'],(string)($this->activeHealthConnection['apiKey']??''))) continue;
            foreach ($connection['models'] as $model) {
                if ($model===$this->exchangeModel || $model===SecretService::OPENROUTER_MODEL
                    || ($connection['provider']==='openrouter' && !str_ends_with($model,':free'))
                    || ($this->health!==null && $this->health->providerHoldRemainingMs($connection,$model)>0)) continue;
                $connection['models']=[$model];
                $connection['model']=$model;
                $this->synthesisRecovery=true;
                $this->inferenceRound++;
                $this->modelAttempt=1;
                $span=AgentTraceService::startSpan('retrieval','read_evidence_synthesis');
                try {
                    $result=$this->completeCompatible($connection,$prompt->withReadEvidence($this->readEvidence),
                        $maxTokens,null,null,0,$deadline,temperature:$temperature);
                    AgentTraceService::endSpan($span,$this->failureCategory===null && !self::isError($result) && trim($result)!==''?'complete':'error');
                    return $result;
                } catch (\Throwable $error) {
                    AgentTraceService::endSpan($span,'error');
                    throw $error;
                }
            }
            break;
        }
        return $answer;
    }

    private function captureCompletedRead(AgentToolRegistry|RuntimeAgentToolRegistry $tools,string $name,mixed $args,array $result): void {
        if (!$this->captureReadEvidence || !$this->readEvidenceEligible) return;
        if (!$tools instanceof RuntimeAgentToolRegistry || !$tools->permitsReadSynthesis($name)
            || !is_array($args) || isset($result['error']) || count($this->readEvidence)>=16) {
            $this->readEvidenceEligible=false;
            $this->readEvidence=[];
            return;
        }
        $this->readEvidence[]=['name'=>$name,'arguments'=>(object)$args,'result'=>$result];
        if (strlen(json_encode($this->readEvidence,JSON_THROW_ON_ERROR))>131072) {
            $this->readEvidenceEligible=false;
            $this->readEvidence=[];
        }
    }

    /** Direct synchronous fallback loop; no scheduled jobs or worker.
     * The wire loop receives one model and preserves its tool exchange. */
    private function completeWithFallbacks(array $plan,int $deadline,bool $repeat,callable $generate): string {
        $eligible=[];
        foreach($plan as $connectionIndex=>$connection)foreach($connection['models'] as $model){
            $single=$connection;$single['models']=[$model];$single['model']=$model;
            $eligible[]=[$connectionIndex,$single];
        }
        $answer='';$nextDelay=0;$notBefore=[];$rejectedConnections=[];
        for($cycle=1;$cycle<=($repeat?self::MAX_PROVIDER_CYCLES:1)&&$eligible!==[];$cycle++){
            $this->cycle=$cycle;$next=[];
            foreach($eligible as [$connectionIndex,$config]){
                if(isset($rejectedConnections[$connectionIndex]))continue;
                // A different request may have observed a hold since route selection.
                // Skip long holds before spending a model-switch backoff on them.
                if (!$this->providerHoldAllowsAttempt($config, $config['model'], $deadline, false)) {
                    $answer = $this->failureMessage(0, false);
                    continue;
                }
                $identity=$connectionIndex."\0".$config['model'];
                $delay=max($nextDelay,(int)ceil((($notBefore[$identity]??0)-hrtime(true))/1_000_000));
                if($delay>0&&!$this->waitForRetry($delay,$deadline)){
                    $this->failureCategory='request_deadline';return $this->failureMessage(0,false);
                }
                if(self::remainingProviderMs($deadline)<self::MIN_ATTEMPT_MS){
                    $this->failureCategory='request_deadline';return $this->failureMessage(0,false);
                }
                $this->activeConnection=['provider'=>$config['provider'],'endpoint'=>$config['endpoint']];
                $this->activeHealthConnection = $this->activeConnection + ['apiKey' => $config['apiKey']];
                $before=count($this->transferTimings);
                $this->inferenceRound=1;
                $answer=$generate($config);
                // A started tool exchange stays on the original model, including
                // its retries. Never restart it through another provider.
                if(!$this->canTryAnotherConnection())return $answer;
                // Invalid model IDs, credentials, schema errors and quota/long
                // Retry-After failures are not retried in the following cycle.
                $last=end($this->transferTimings)?:[];
                $observed=count($this->transferTimings)>$before
                    &&($last['model']??null)===$config['model']
                    &&($last['provider']??null)===$config['provider'];
                // Model IDs in one connection share its credential. An actual 401
                // rejects that credential for this request, including later cycles.
                if($observed&&$last['status']===401&&$last['curl_error']===0){
                    $rejectedConnections[$connectionIndex]=true;
                }
                $retryable=$observed&&$this->retryableInferenceFailure((int)($last['status']??0));
                if($retryable){
                    $next[]=[$connectionIndex,$config];
                    $notBefore[$identity]=hrtime(true)+($this->retryAfterMs??0)*1000000;
                }
                $nextDelay=self::backoffMs($retryable?3:1);
            }
            $eligible=$next;
            if($eligible!==[])$nextDelay=max($nextDelay,self::backoffMs(3));
        }
        return $answer!==''?$answer:'AI request failed: no enabled provider completed the request.';
    }

    /**
     * OpenAI Chat Completions tool calls are replayed with the original assistant
     * tool-call IDs and one role=tool result per call.
     * https://developers.openai.com/api/docs/guides/function-calling
     */
    private function completeCompatible(
        array $config,
        string|AgentModelTurn $prompt,
        int $maxTokens,
        ?string $modelOverride,
        AgentToolRegistry|RuntimeAgentToolRegistry|null $tools = null,
        int $maxRounds = 0,
        ?int $deadline = null,
        bool $laterConnection = false,
        float $temperature = 0.3
    ): string {
        if (!$config['configured']) {
            $this->failureCategory = 'not_configured';
            return 'No AI API key or valid provider configuration is available in Admin → Integrations.';
        }
        if (!function_exists('curl_init')) {
            $this->failureCategory = 'transport';
            return 'AI request failed: cURL is not available on this server.';
        }
        $deadline ??= hrtime(true) + self::GENERATION_TIMEOUT_NS;
        $options = [
            CURLOPT_PROXY => '',
            CURLOPT_FOLLOWLOCATION => false,
        ];
        if ($config['provider'] === 'google') {
            // SecretService supplies the fixed Google endpoint and its own key.
            $config['temperature'] = $temperature;
        } else {
            $host = parse_url($config['endpoint'], PHP_URL_HOST);
            $port = parse_url($config['endpoint'], PHP_URL_PORT) ?? 443;
            $addresses = dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
            $resolve = [];
            foreach ($addresses as $record) {
                $ip = $record['ip'] ?? $record['ipv6'] ?? '';
                if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)) continue;
                $resolve[] = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
            }
            if ($resolve === []) {
                $this->failureCategory = 'provider_request';
                return 'AI request failed: the compatible endpoint must resolve to a public address.';
            }
            // Pin validated addresses between lookup and connection.
            $options[CURLOPT_RESOLVE] = [$host . ':' . $port . ':' . implode(',', array_unique($resolve))];
        }
        return $this->completeCompatibleLoop($config,$prompt,$maxTokens,$modelOverride,$tools,$maxRounds,$deadline,$laterConnection,$options);
    }

    /** Private wire loop for the configured Chat Completions connection. */
    private function completeCompatibleLoop(array $config,string|AgentModelTurn $prompt,int $maxTokens,?string $modelOverride,
        AgentToolRegistry|RuntimeAgentToolRegistry|null $tools,int $maxRounds,int $deadline,bool $laterConnection,array $options): string {
        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $config['apiKey']];
        $url = $config['endpoint'] . '/chat/completions';
        $models = in_array($modelOverride, $config['models'], true) ? [$modelOverride] : $config['models'];
        $officialOpenAi = parse_url($url, PHP_URL_HOST) === 'api.openai.com';
        $tokenField = $config['tokenField'] ?? ($officialOpenAi ? 'max_completion_tokens' : 'max_tokens');
        $usesGoogleContinuation = $config['provider'] === 'google';
        $usesOpenRouterContinuation = $config['provider'] === 'openrouter';
        $dialogue = $prompt instanceof AgentModelTurn ? $prompt->dialogue() : [['role' => 'user', 'content' => $prompt]];
        $status = 0;
        $body = false;
        foreach ($models as $index => $model) {
            $requestModel = $model;
            $usesFreeRouter = $usesOpenRouterContinuation && $model === SecretService::OPENROUTER_MODEL;
            $messages = $dialogue;
            if ($prompt instanceof AgentModelTurn) {
                // OpenAI documents developer messages for o1 and newer models.
                // Use system for legacy and general Chat Completions endpoints.
                // https://developers.openai.com/api/reference/resources/chat/subresources/completions/methods/create
                $developerRole = $officialOpenAi && preg_match('/^(?:o[134]|gpt-[56])(?:[.-]|$)/D', $model) === 1;
                array_unshift($messages, [
                    'role' => $developerRole ? 'developer' : 'system',
                    'content' => $this->withAnswerFence($prompt->instructions),
                ]);
            }
            $seenCalls=[];
            for ($round = 0; $round <= $maxRounds; $round++) {
                if (!$this->synthesisRecovery) $this->inferenceRound=$round+1;
                $declarations = $tools === null ? [] : array_map(
                    static fn(array $declaration): array => ['type' => 'function', 'function' => $declaration],
                    $tools->declarations()
                );
                $advertised=array_map(static fn(array $declaration):string=>$declaration['function']['name'],$declarations);
                $payload = ['model' => $requestModel, 'messages' => $messages];
                $payload[$tokenField] = $maxTokens;
                if ($usesGoogleContinuation && isset($config['temperature'])) {
                    $payload['temperature'] = $config['temperature'];
                }
                if ($usesGoogleContinuation && in_array($model, SecretService::GOOGLE_MODELS, true)) {
                    // Preserve the exact supported level on the hosted Gemma models.
                    // https://aistudio.google.com/docs/openai#thinking
                    $payload['extra_body'] = ['google' => ['thinking_config' => ['thinking_level' => 'minimal']]];
                }
                // Luna tool calling on Chat Completions requires reasoning_effort=none.
                // https://developers.openai.com/api/docs/models/gpt-6-luna
                if ($officialOpenAi && $tools !== null
                    && preg_match('/^gpt-6-luna(?:-\d{4}-\d{2}-\d{2})?$/D', $model) === 1) {
                    $payload['reasoning_effort'] = 'none';
                }
                if ($declarations !== []) {
                    $payload['tools'] = $declarations;
                    $payload['tool_choice'] = $round < $maxRounds ? 'auto' : 'none';
                }
                $hasModelAlternate = $round === 0 && $index + 1 < count($models);
                $hasAlternate = $hasModelAlternate || ($round === 0 && $laterConnection
                    && $this->toolCalls === 0);
                $wire=json_encode($payload,JSON_THROW_ON_ERROR);
                $body=$this->postWithRetries($url,$wire,$headers,$status,$requestModel,$deadline,$hasAlternate,$options);
                if ($status !== 200 || $body === false) {
                    if ($hasModelAlternate && $this->waitForFailover($status, $deadline, $index)) break;
                    return $this->failureMessage($status, $body);
                }
                $response = json_decode($body);
                if ($usesFreeRouter && $round > 0 && ($response->model ?? null) !== $requestModel) {
                    return $this->incompleteResponse();
                }
                $choices = $response->choices ?? null;
                $choice = is_array($choices) ? ($choices[0] ?? null) : null;
                $message = $choice->message ?? null;
                $wireCalls = $message->tool_calls ?? [];
                // Only a completed assistant turn can produce an answer or dispatch tools.
                $expectedFinish = $wireCalls === [] ? 'stop' : 'tool_calls';
                if (!($message instanceof \stdClass) || ($message->role ?? null) !== 'assistant'
                    || !is_array($wireCalls) || ($choice->finish_reason ?? null) !== $expectedFinish) {
                    return $this->incompleteResponse();
                }
                $calls = array_map(static fn(mixed $call): array => [
                    'id' => $call->id ?? null,
                    'type' => $call->type ?? null,
                    'function' => [
                        'name' => $call->function->name ?? null,
                        'arguments' => $call->function->arguments ?? null,
                    ],
                ], $wireCalls);
                $message = get_object_vars($message);
                if($calls!==[]&&($tools===null||$round===$maxRounds))return $this->incompleteResponse();
                if ($calls === []) {
                    $refusal = $message['refusal'] ?? null;
                    if (is_string($refusal) && trim($refusal) !== '') return $refusal;
                    $answer=is_string($message['content']??null)?$message['content']:'';
                    if (trim($answer) !== '') return self::fencedAnswer($answer) ?? $answer;
                    $this->failureCategory = 'empty_response';
                    if ($round === 0) break;
                    return '';
                }
                if(count($calls)>self::MAX_TOOL_CALLS_PER_ROUND||!self::validCallBatch($calls,$seenCalls)) {
                    $this->failureCategory = 'provider_response';
                    return 'AI request failed: the model returned an invalid set of lookups.';
                }
                if ($usesFreeRouter && $round === 0) {
                    $resolvedModel = $response->model ?? null;
                    if (!is_string($resolvedModel) || !SecretService::validModelId($resolvedModel)
                        || !str_contains($resolvedModel, '/') || !str_ends_with($resolvedModel, ':free')) {
                        return $this->incompleteResponse();
                    }
                    $requestModel = $resolvedModel;
                }
                $assistant = ['role' => 'assistant', 'tool_calls' => []];
                if (array_key_exists('content', $message) && (is_string($message['content']) || $message['content'] === null)) {
                    $assistant['content'] = $message['content'];
                }
                // Continuation extensions stay on the connection that returned them.
                if ($usesGoogleContinuation) {
                    $remaining = 262144;
                    $extension = self::googleContinuation($message['extra_content'] ?? null, $remaining);
                    if ($extension === null) return $this->incompleteResponse();
                    $assistant += $extension;
                    foreach ($wireCalls as $index => $wireCall) {
                        $extension = self::googleContinuation($wireCall->extra_content ?? null, $remaining);
                        if ($extension === null) return $this->incompleteResponse();
                        $calls[$index] += $extension;
                    }
                }
                foreach($usesOpenRouterContinuation ? ['reasoning_content','reasoning_details'] : [] as $field){
                    if(!isset($message[$field]))continue;
                    $value=$message[$field];
                    if(($field==='reasoning_content'&&!is_string($value))
                        ||($field==='reasoning_details'&&!is_array($value))
                        ||strlen(json_encode($value,JSON_THROW_ON_ERROR))>262144)return $this->incompleteResponse();
                    $assistant[$field]=$value;
                }

                $this->exchangeModel ??= $requestModel;
                $this->toolExchangeStarted=true;
                $responses = [];
                foreach ($calls as $call) {
                    $id = $call['id'] ?? '';
                    $name = $call['function']['name'] ?? '';
                    $arguments = $call['function']['arguments'] ?? '';
                    // Chat Completions supplies a JSON object encoded as a string.
                    // https://developers.openai.com/api/reference/resources/chat/subresources/completions/methods/create
                    $args=str_starts_with(ltrim($arguments),'{')?json_decode($arguments,true,64):null;
                    $result=$this->executeDeclaredTool($tools,$advertised,$name,$args);
                    $this->captureCompletedRead($tools,$name,$args,$result);
                    $assistant['tool_calls'][] = $call;
                    $responses[] = ['role' => 'tool', 'tool_call_id' => $id,
                        'content' => json_encode($result, JSON_THROW_ON_ERROR)];
                }
                if ($responses === []) {
                    $this->failureCategory = 'empty_response';
                    return '';
                }
                $messages[] = $assistant;
                array_push($messages, ...$responses);
            }
        }
        return $status === 200 ? '' : $this->failureMessage($status, $body);
    }

    /** A malformed or repeated ID cannot execute part of a tool batch. */
    private static function validCallBatch(array $calls,array &$seen): bool {
        foreach($calls as $call){
            if(!is_array($call))return false;
            $name=$call['function']['name']??null;
            if(!is_string($name)||!preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]{0,63}$/D',$name))return false;
            if(($call['type']??null)!=='function'||!is_string($call['function']['arguments']??null))return false;
            $id=$call['id']??null;
            if(!is_string($id)||$id===''||strlen($id)>256||isset($seen[$id]))return false;
            $seen[$id]=true;
        }
        return true;
    }

    /** Preserve Google's opaque Chat Completions signature at its original location. */
    private static function googleContinuation(mixed $extra, int &$remaining): ?array
    {
        $google = $extra->google ?? null;
        if (!$google instanceof \stdClass || !property_exists($google, 'thought_signature')) return [];
        $signature = $google->thought_signature;
        if (!is_string($signature) || $signature === '' || strlen($signature) > $remaining) return null;
        $remaining -= strlen($signature);
        return ['extra_content' => ['google' => ['thought_signature' => $signature]]];
    }

    /** Shared execution boundary for every supported provider. */
    private function executeDeclaredTool(AgentToolRegistry|RuntimeAgentToolRegistry $tools,array $advertised,string $name,mixed $args): array {
        if(!is_array($args)||($args!==[]&&array_is_list($args))||!in_array($name,$advertised,true)||!$tools->has($name)){
            $span=AgentTraceService::startSpan('retrieval','tool_call_rejected');
            AgentTraceService::endSpan($span,'error');
            return ['error'=>'Invalid tool call','guidance'=>'Use advertised functions and JSON object arguments. Consult the authorized declarations; tool_search can explain available capabilities.'];
        }
        $result=$tools->run($name,$args);
        if(!isset($result['error']))$this->toolCalls++;
        return $result;
    }

    private function incompleteResponse(): string {
        $this->failureCategory='provider_response';
        return 'AI request failed: the model response was incomplete. Please try the question again.';
    }

    /** Cross-provider recovery is allowed only before a tool result has been executed.
     * A partial tool exchange must never be replayed through another model/provider. */
    private function canTryAnotherConnection(): bool {
        return !$this->toolExchangeStarted && $this->toolCalls === 0 && $this->failureCategory !== null
            && !in_array($this->failureCategory, ['provider_budget','request_deadline'], true);
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

    /** True when the string is one of this client's failure sentences, not an answer. */
    public static function isError(string $text): bool
    {
        foreach (self::ERROR_PREFIXES as $prefix) {
            if (str_starts_with($text, $prefix)) return true;
        }
        return false;
    }

    private static function remainingProviderMs(int $deadline): int
    {
        $local = (int)(($deadline - hrtime(true)) / 1_000_000);
        return min($local, AgentTraceService::remainingMs() - self::FINALIZE_RESERVE_MS);
    }

    /** Retry a recoverable inference transfer, never a business action.
     * Explicit quota or a long provider Retry-After is a provider hold. */
    private function retryableInferenceFailure(int $status): bool {
        return !in_array($this->failureCategory,['request_deadline','provider_budget','provider_cooldown'],true)
            &&!in_array($this->quotaKind,['daily','zero'],true)
            &&$status!==404&&($this->retryAfterMs??0)<=8000
            &&($status!==429||($this->retryAfterMs??0)>0)
            &&self::canFailOver($status,$this->transportError);
    }

    /** Exactly three attempts on the same immutable payload unless successful,
     * non-retryable, or the shared request budget is exhausted. */
    private function postWithRetries(string $url,string $payload,array $headers,?int &$status,string $model,
        int $deadline,bool $hasAlternate,array $options=[]): string|false {
        $body=false;
        $maxAttempts=$this->synthesisRecovery?1:self::ATTEMPTS_PER_MODEL;
        for($attempt=1;$attempt<=$maxAttempts;$attempt++){
            $this->modelAttempt=$attempt;
            $body=$this->post($url,$payload,$headers,$status,$model,$deadline,$hasAlternate,$options);
            if($status===200&&$body!==false)return $body;
            if($attempt===$maxAttempts||!$this->retryableInferenceFailure($status))break;
            if(!$this->waitForRetry(self::backoffMs($attempt,$this->retryAfterMs),$deadline))break;
        }
        return $body;
    }

    /** Google REST exponential backoff example: 1s, 2s, 4s, plus jitter.
     * Retry-After is a minimum. The attempt count and deadline are app policy.
     * https://ai.google.dev/gemini-api/docs/troubleshooting#retry-strategy
     * https://openrouter.ai/docs/api_reference/errors-and-debugging
     * https://github.com/openai/openai-python#retries
     * https://ai-sdk.dev/docs/ai-sdk-core/settings
     */
    private static function backoffMs(int $attempt,?int $retryAfterMs=null): int {
        return max(1000*(2**min(3,max(0,$attempt-1))),$retryAfterMs??0)+random_int(0,250);
    }

    private function waitForRetry(int $delay,int $deadline): bool {
        if($delay+self::MIN_ATTEMPT_MS>self::remainingProviderMs($deadline))return false;
        DatabaseService::releaseIdleConnection();
        $span=AgentTraceService::startSpan('retrieval','provider_backoff');
        usleep($delay*1000);
        AgentTraceService::endSpan($span,'complete');
        return true;
    }

    private function waitForFailover(int $status,int $deadline,int $index): bool {
        if(in_array($this->failureCategory,['request_deadline','provider_budget','provider_cooldown'],true)
            ||!self::canFailOver($status,$this->transportError))return false;
        return $this->waitForRetry(self::backoffMs($index+1,$this->retryAfterMs),$deadline);
    }

    /** Recheck immediately before a transfer, including after another worker's update.
     * Short hints can recover within this turn. Long holds leave other candidates eligible.
     * No model-attempt span or transfer is recorded for a local hold stop. */
    private function providerHoldAllowsAttempt(array $connection, string $model, int $deadline, bool $wait = true): bool {
        if ($this->bypassProviderHolds || $this->health === null) return true;
        while (($remaining = $this->health->providerHoldRemainingMs($connection, $model)) > 0) {
            if ($remaining > 8000 || $remaining + self::MIN_ATTEMPT_MS > self::remainingProviderMs($deadline)) {
                $this->failureCategory = 'provider_cooldown';
                return false;
            }
            if (!$wait) return true;
            if (!$this->waitForRetry($remaining, $deadline)) {
                $this->failureCategory = 'provider_cooldown';
                return false;
            }
            // A concurrent request may have extended the hold while this one waited.
        }
        return true;
    }

    private function failureMessage(int $status, string|false $body): string
    {
        return match ($this->failureCategory) {
            'request_deadline' => 'AI request failed: the time available for this request was exhausted.',
            'provider_budget' => 'AI request failed: the model call or token limit for this request was reached.',
            'provider_cooldown' => 'AI request failed: a provider hold currently prevents this model attempt. Try again shortly or use an integration diagnostic.',
            default => self::describeFailure($status, $body, $this->transportError, $this->activeConnection['provider'] ?? null),
        };
    }

    private function post(string $url, string $payload, array $headers, ?int &$status, string $model,
        int $deadline, bool $hasAlternate, array $options = []): string|false
    {
        $this->retryAfterMs = null;
        $this->quotaKind = null;
        $this->transportError = 0;
        $status = 0;
        if(count($this->transferTimings)>=self::MAX_TRANSFERS){
            $this->failureCategory='provider_budget';return false;
        }
        if (self::remainingProviderMs($deadline) < self::MIN_ATTEMPT_MS) {
            $this->failureCategory = 'request_deadline';
            return false;
        }
        if (!$this->providerHoldAllowsAttempt($this->activeHealthConnection, $model, $deadline)) return false;
        DatabaseService::releaseIdleConnection();
        try {
            $span = AgentTraceService::startSpan('model_attempt', $model);
        } catch (AgentBudgetException $error) {
            if (!in_array($error->reason, ['request_deadline','provider_budget'], true)) throw $error;
            $this->failureCategory = $error->reason;
            return false;
        }
        $available = self::remainingProviderMs($deadline);
        if ($available < self::MIN_ATTEMPT_MS) {
            $this->failureCategory = 'request_deadline';
            AgentTraceService::endSpan($span, 'skipped');
            return false;
        }
        // Leave a second model time to answer if the preferred one stalls.
        $timeoutMs = min(self::MODEL_ATTEMPT_TIMEOUT_MS, $hasAlternate ? max(self::MIN_ATTEMPT_MS, intdiv($available, 2)) : $available);
        $ch = curl_init($url);
        curl_setopt_array($ch, $options + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HEADERFUNCTION => function ($handle, string $line): int {
                if (str_starts_with($line, 'HTTP/')) $this->retryAfterMs = null;
                if (stripos($line, 'Retry-After:') === 0) {
                    $value = trim(substr($line, strlen('Retry-After:')));
                    if (ctype_digit($value)) {
                        $seconds = (int)$value;
                    } else {
                        $date = \DateTimeImmutable::createFromFormat('D, d M Y H:i:s \\G\\M\\T', $value, new \DateTimeZone('GMT'));
                        $seconds = $date === false ? 0 : $date->getTimestamp() - time();
                    }
                    $this->retryAfterMs = 1000 * max(0, min(86400, $seconds));
                }
                return strlen($line);
            },
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => min(10000, $timeoutMs),
        ]);
        $body = curl_exec($ch);
        $this->transportError = curl_errno($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // Cumulative elapsed time since cURL started this transfer.
        // https://curl.se/libcurl/c/CURLINFO_PRETRANSFER_TIME.html
        // https://curl.se/libcurl/c/CURLINFO_STARTTRANSFER_TIME.html
        $milestones = AgentTraceService::attemptTimings([
            'dns_ms' => 1000 * curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME),
            'connect_ms' => 1000 * curl_getinfo($ch, CURLINFO_CONNECT_TIME),
            'ready_ms' => 1000 * curl_getinfo($ch, CURLINFO_PRETRANSFER_TIME),
            'first_byte_ms' => 1000 * curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME),
        ]);
        // Keep only an allowlisted diagnostic hint, never the provider body/message.
        $hint=self::requestErrorHint($status,$body);
        $diagnosticFields=$hint===null?[]:['request_error_hint'=>$hint];
        if(($this->activeConnection['provider']??null)==='google'){
            $details=self::googleErrorDetails($status,$body);
            $this->quotaKind=$details['quota_kind']??null;
            if(isset($details['retry_after_ms']))$this->retryAfterMs=max($this->retryAfterMs??0,$details['retry_after_ms']);
            $diagnosticFields+=$details;
        }
        if($this->retryAfterMs!==null)$diagnosticFields['retry_after_ms']=$this->retryAfterMs;
        $diagnosticFields['inference_round']=$this->inferenceRound;
        $diagnosticFields['inference_phase']=$this->synthesisRecovery?'synthesis_recovery':($this->toolExchangeStarted?'continuation':'initial');
        $diagnosticFields+=self::responseMetadata($status,$body);
        $usage=AgentTraceService::usageFromResponse($body);
        $diagnosticFields['usage']=$usage;
        $this->health?->record($this->activeHealthConnection,$model,$status,$this->transportError,$this->retryAfterMs);
        $this->transferTimings[] = [
            'model' => $model,
            'cycle'=>$this->cycle,'attempt_in_model'=>$this->modelAttempt,
            'status' => $status,
            'curl_error' => $this->transportError,
            'timeout_ms' => $timeoutMs,
            'total_ms' => 1000 * curl_getinfo($ch, CURLINFO_TOTAL_TIME),
        ] + $this->activeConnection + $diagnosticFields + $milestones;
        AgentTraceService::endSpan($span,($body===false||$status<200||$status>=300)?'error':'complete',[
            'http_status'=>$status, 'curl_error'=>$this->transportError, 'timeout_ms'=>$timeoutMs,
            'cycle'=>$this->cycle,'attempt_in_model'=>$this->modelAttempt,'provider'=>$this->activeConnection['provider']??null,
            'request_sha256'=>hash('sha256',$payload),
            'usage'=>$usage,
        ] + $diagnosticFields + $milestones);
        unset($ch);
        $this->failureCategory = ($body === false || $status !== 200) ? self::classifyFailure($status, $this->transportError) : null;
        return $body;
    }

    /** Google REST error details use ProtoJSON Duration and typed quota violations.
     * Retain fixed categories only, never provider descriptions or project identifiers.
     * https://github.com/googleapis/googleapis/blob/master/google/rpc/error_details.proto
     * https://protobuf.dev/programming-guides/json/
     */
    public static function googleErrorDetails(int $status,string|false $body): array {
        if(!in_array($status,[429,503],true)||!is_string($body)||strlen($body)>65536)return [];
        $error=json_decode($body,true)['error']??null;
        if(!is_array($error))return [];
        $safe=[];$quota=[];
        foreach(array_slice((array)($error['details']??[]),0,16) as $detail){
            if(!is_array($detail))continue;
            $type=$detail['@type']??null;
            if($type==='type.googleapis.com/google.rpc.RetryInfo'){
                $delay=$detail['retryDelay']??null;
                if(is_string($delay)&&preg_match('/^([0-9]{1,12})(?:\\.([0-9]{1,9}))?s$/D',$delay,$match)){
                    $ms=(int)min(86400000,ceil((float)substr($delay,0,-1)*1000));
                    $safe['retry_after_ms']=max($safe['retry_after_ms']??0,$ms);
                }
            }
            if($status===429&&$type==='type.googleapis.com/google.rpc.QuotaFailure'){
                foreach(array_slice((array)($detail['violations']??[]),0,16) as $violation){
                    if(!is_array($violation))continue;
                    $value=$violation['quotaValue']??null;
                    if($value===0||$value==='0')$quota['zero']=true;
                    $id=is_string($violation['quotaId']??null)?strtolower(preg_replace('/[^A-Za-z0-9]/','',$violation['quotaId'])):'';
                    $quota[str_contains($id,'perday')?'daily':(str_contains($id,'perminute')?'minute':'other')]=true;
                }
            }
        }
        foreach(['zero','daily','minute','other'] as $kind)if(isset($quota[$kind])){$safe['quota_kind']=$kind;break;}
        return $safe;
    }

    /** A successful HTTP transfer may be a tool request, not an answer.
     * Only bounded structural metadata is retained; no prompt or raw response.
     */
    private static function responseMetadata(int $status,string|false $body): array {
        if($status!==200||!is_string($body))return [];
        $response=json_decode($body,true);
        if(!is_array($response))return ['response_kind'=>'invalid'];
        $choice=is_array($response['choices']??null)?($response['choices'][0]??null):null;
        $message=is_array($choice)?($choice['message']??null):null;
        $calls=is_array($message['tool_calls']??null)?$message['tool_calls']:[];
        $finish=$choice['finish_reason']??null;
        $safe=['response_kind'=>$calls!==[]?'tool_calls':(is_array($message)?'text_or_empty':'invalid')];
        $safe['requested_tool_count']=min(100,count($calls));
        if(is_string($finish)&&in_array($finish,['stop','length','tool_calls','content_filter','function_call','error'],true))$safe['finish_reason']=$finish;
        $actual=$response['model']??null;
        if(is_string($actual)&&SecretService::validModelId($actual)&&!preg_match('/^(?:sk-|AIza)/i',$actual))$safe['response_model']=$actual;
        return $safe;
    }

    public const REQUEST_ERROR_HINTS=['tool_schema','tool_history','generation_config','invalid_argument'];

    /** Heuristic from an error response, not a determination of root cause. */
    public static function requestErrorHint(int $status,string|false $body): ?string {
        if(!in_array($status,[400,422],true)||!is_string($body)||strlen($body)>65536)return null;
        $error=json_decode($body,true)['error']??null;
        if(!is_array($error))return null;
        $message=is_string($error['message']??null)?strtolower($error['message']):'';
        if(preg_match('/thought.?signature|function.?response|tool.?call.?id|tool.?response|function.?call.?history/',$message))return 'tool_history';
        if(preg_match('/function.?declarations?|parameters.?json.?schema|tool.?schema|json.?schema|invalid schema/',$message))return 'tool_schema';
        if(preg_match('/thinking.?config|thinking.?level|generation.?config|temperature|max.?output.?tokens/',$message))return 'generation_config';
        if(($error['status']??null)==='INVALID_ARGUMENT')return 'invalid_argument';
        return null;
    }

    /**
     * Provide actionable status guidance without exposing provider response bodies,
     * which may contain sensitive request data or infrastructure details.
     */
    public static function describeFailure(int $status, $body, int $transportError = 0, ?string $provider = null): string
    {
        $keyLabel = match ($provider) {
            'google' => 'Google API Key',
            'openrouter' => 'OpenRouter API Key',
            'openai_compatible' => 'Compatible API Key',
            default => 'provider API key',
        };
        $providerLabel = match ($provider) {
            'google' => 'Google',
            'openrouter' => 'OpenRouter',
            'openai_compatible' => 'OpenAI-compatible',
            default => 'AI provider',
        };
        $hint = match (true) {
            $status === 400 => ' The provider rejected the request. Check the integration configuration.',
            $status === 401 => ' The provider rejected authentication. Check the '.$keyLabel.' in Admin → Integrations.',
            $status === 403 => ' Access was denied. Check the '.$providerLabel.' connection permissions in Admin → Integrations.',
            $status === 404 => ' The configured model or endpoint was not found. Check the integration configuration.',
            $status === 429 => ' The provider rate limit or quota was exceeded.',
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
