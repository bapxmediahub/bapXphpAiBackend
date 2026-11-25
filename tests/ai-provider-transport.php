<?php
declare(strict_types=1);

use App\Services\{AgentToolRegistry, AgentTraceService, AiClient, RuntimeAgentToolRegistry, SecretService, SupportToolRegistry};

// Run through real cURL against a loopback-only HTTP fixture. Never read provider keys.
if (PHP_SAPI !== 'cli' && !(PHP_SAPI === 'cli-server' && getenv('BAPX_PROVIDER_TRANSPORT_FIXTURE') === '1')) {
    http_response_code(404);
    return;
}
if (PHP_SAPI === 'cli-server') {
    [$scenario, $path] = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'), 2);
    $google = $path === 'v1beta/openai/chat/completions';
    header('Content-Type: application/json');
    $payload = file_get_contents('php://input');
    $request = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    $id = $request['model'] ?? '';
    $messages = $request['messages'] ?? null;
    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || (!$google && $path !== 'chat/completions')
        || !in_array($authorization, ['Bearer fixture', 'Bearer fixture-key', 'Bearer fixture-google',
            'Bearer fixture-compatible', 'Bearer replacement-fixture-key'], true)
        || !is_string($id) || $id === '' || !is_array($messages) || !array_is_list($messages)
        || array_key_exists('contents', $request) || array_key_exists('generationConfig', $request)) {
        http_response_code(400);
        echo '{"error":{"message":"Expected Chat Completions request with bearer authentication"}}';
        return;
    }
    $primary = $id === 'gemma-4-31b-it';
    $respond = static function (?string $content, array $calls = [], array $usage = [], array $extensions = []) use ($id): void {
        $message = ['role'=>'assistant', 'content'=>$content] + $extensions;
        if ($calls !== []) $message['tool_calls'] = $calls;
        $reply = ['model'=>$id, 'choices'=>[['index'=>0, 'message'=>$message,
            'finish_reason'=>$calls === [] ? 'stop' : 'tool_calls']]];
        if ($usage !== []) $reply['usage'] = $usage;
        echo json_encode($reply, JSON_THROW_ON_ERROR);
    };
    $makeCall = static fn(string $id, string $name, array|object $arguments): array =>
        ['id'=>$id, 'type'=>'function', 'function'=>['name'=>$name, 'arguments'=>json_encode($arguments, JSON_THROW_ON_ERROR)]];
    $toolResult = static function (array $message): array {
        if (($message['role'] ?? null) !== 'tool' || !is_string($message['content'] ?? null)) return [];
        $result = json_decode($message['content'], true, 512, JSON_THROW_ON_ERROR);
        return is_array($result) ? $result : [];
    };

    if ($scenario === 'cycle') {
        $dir = getenv('BAPX_CYCLE_FIXTURE_DIR');
        if (!is_string($dir) || !is_dir($dir)) { http_response_code(400); echo '{}'; return; }
        $key = $dir.'/'.hash('sha256', $id);
        $count = (int)@file_get_contents($key) + 1;
        file_put_contents($key, (string)$count, LOCK_EX);
        if ($primary && $count === 4) {
            $respond('<final_answer>connection ok</final_answer>');
            return;
        }
        http_response_code(503);
        echo '{"error":{"status":"UNAVAILABLE"}}';
        return;
    }
    if (in_array($scenario, ['auth_ordered', 'auth_independent', 'auth_cycle'], true)) {
        if (!$google) {
            $status = $scenario === 'auth_cycle' && $id === 'fixture/first' ? 503 : 401;
        } elseif ($scenario === 'auth_independent') {
            $respond('<final_answer>independent connection ok</final_answer>');
            return;
        } else {
            $status = $scenario === 'auth_cycle' ? 403 : ($primary ? 503 : 429);
            if ($status !== 403) header('Retry-After: 60');
        }
        http_response_code($status);
        echo '{"error":{"message":"private-fixture-auth-details"}}';
        return;
    }
    if ($scenario === 'body_retry' || $scenario === 'daily_quota') {
        $file = getenv('BAPX_CYCLE_FIXTURE_DIR').'/'.$scenario;
        $count = (int)@file_get_contents($file) + 1;
        file_put_contents($file, (string)$count, LOCK_EX);
        if ($count === 1 || $scenario === 'daily_quota') {
            http_response_code(429);
            header('Retry-After: 1');
            echo json_encode(['error'=>['details'=>[
                ['@type'=>'type.googleapis.com/google.rpc.RetryInfo','retryDelay'=>'1.25s'],
                ['@type'=>'type.googleapis.com/google.rpc.QuotaFailure',
                    'violations'=>[['quotaId'=>$scenario === 'daily_quota' ? 'RequestsPerDay' : 'RequestsPerMinute',
                        'quotaValue'=>'10','subject'=>'private-fixture-project']]],
            ]]], JSON_THROW_ON_ERROR);
            return;
        }
        $respond('<final_answer>connection ok</final_answer>', usage: ['prompt_tokens'=>3,'completion_tokens'=>2,'total_tokens'=>5]);
        return;
    }
    if ($scenario === 'cooldown' || $scenario === 'short_hold') {
        if ($scenario === 'cooldown' && $primary) {
            http_response_code(503);
            echo '{"error":{"status":"UNAVAILABLE"}}';
            return;
        }
        $respond('<final_answer>connection ok</final_answer>');
        return;
    }
    if ($scenario === 'bad_schema') {
        http_response_code(400);
        echo json_encode(['error'=>['status'=>'INVALID_ARGUMENT','message'=>'Invalid tool schema: private-fixture-raw-error']]);
        return;
    }
    if (in_array($scenario, ['tool_probe', 'tool_probe_bad_signature', 'tool_probe_large_signature'], true)) {
        $tools = json_decode($payload)->tools ?? [];
        $tool = $tools[0] ?? null;
        $knownGemma = $google && in_array($id, ['gemma-4-31b-it','gemma-4-26b-a4b-it'], true);
        $thinking = $request['extra_body'] ?? null;
        if (($tool->type ?? null) !== 'function' || ($tool->function->name ?? null) !== 'connection_probe'
            || !is_object($tool->function->parameters->properties ?? null)
            || ($tool->function->parameters->additionalProperties ?? null) !== false
            || $thinking !== ($knownGemma ? ['google'=>['thinking_config'=>['thinking_level'=>'minimal']]] : null)) {
            http_response_code(400);
            echo '{"error":{"message":"Wrong tool schema"}}';
            return;
        }
        $signature = match ($scenario) {
            'tool_probe_bad_signature' => 7,
            'tool_probe_large_signature' => str_repeat('x', 262145),
            default => base64_encode('opaque-fixture-signature'),
        };
        $extra = ['extra_content'=>['google'=>['thought_signature'=>$signature]]];
        $call = $makeCall('fixture-call', 'connection_probe', (object)[]);
        // Cover both placements without assuming that every Google model returns a signature.
        $wireCall = $primary ? $call + $extra : $call;
        if (count($messages) === 1 && ($request['tool_choice'] ?? null) === 'auto') {
            $respond(null, [$wireCall], extensions: $primary ? [] : $extra);
        } else {
            $previous = $messages[count($messages)-2] ?? [];
            $result = end($messages);
            $expectedCall = $google ? $wireCall : $call;
            $expectedExtra = $google && !$primary ? $extra['extra_content'] : null;
            if (($previous['role'] ?? null) !== 'assistant' || ($previous['tool_calls'] ?? null) !== [$expectedCall]
                || ($previous['extra_content'] ?? null) !== $expectedExtra
                || ($result['tool_call_id'] ?? null) !== 'fixture-call'
                || ($toolResult($result)['challenge'] ?? null) !== 'fixture-observed-challenge') {
                http_response_code(400);
                echo '{"error":{"message":"Wrong tool history or continuation metadata"}}';
                return;
            }
            $respond('<final_answer>fixture-observed-challenge</final_answer>');
        }
        return;
    }
    if ($scenario === 'tool_discovery') {
        $names = array_column(array_column($request['tools'] ?? [], 'function'), 'name');
        $count = count($messages);
        $last = end($messages);
        $result = $last ? $toolResult($last) : [];
        $previous = $messages[$count-2]['tool_calls'][0] ?? [];
        if ($count === 1 && $names === ['read_runtime_skill','tool_search']) {
            $respond(null, [$makeCall('fixture-discovery', 'tool_search', ['query'=>'public_policy'])]);
        } elseif ($count === 3 && in_array('public_policy', $names, true)
            && ($previous['id'] ?? null) === 'fixture-discovery' && ($last['tool_call_id'] ?? null) === 'fixture-discovery'
            && ($result['matches'][0]['name'] ?? null) === 'public_policy') {
            $respond(null, [$makeCall('fixture-policy', 'public_policy', ['key'=>'shipping_cost'])]);
        } elseif ($count === 5 && ($request['tool_choice'] ?? null) === 'none'
            && ($previous['id'] ?? null) === 'fixture-policy' && ($last['tool_call_id'] ?? null) === 'fixture-policy'
            && ($result['value'] ?? null) === 'Shipping is calculated at checkout.') {
            $respond('<final_answer>Shipping is calculated at checkout.</final_answer>');
        } else {
            http_response_code(400);
            echo '{"error":{"message":"Discovery exchange invalid"}}';
        }
        return;
    }
    if ($scenario === 'slow' && $primary) usleep(6_350_000);
    if ($scenario === 'stalled' && $primary) usleep(3_300_000);
    if ($scenario === 'retry') {
        $file = getenv('BAPX_CYCLE_FIXTURE_DIR').'/retry-wire';
        $first = @file_get_contents($file);
        if ($first === false) {
            file_put_contents($file, $payload, LOCK_EX);
            http_response_code(503);
            header('Retry-After: 1');
            echo '{"error":{"message":"private-provider-placeholder"}}';
            return;
        }
        if ($payload !== $first) {
            http_response_code(400);
            echo '{"error":{"message":"Retry changed the original request"}}';
            return;
        }
    }
    $status = match ($scenario) {
        'locked' => 503,
        'quota' => 429,
        'denied' => 403,
        default => 200,
    };
    if ($status !== 200) {
        http_response_code($status);
        if ($scenario === 'quota') {
            header('Retry-After: '.gmdate('D, d M Y H:i:s \G\M\T', time() + 60));
        }
        echo '{"error":{"message":"private-provider-placeholder"}}';
        return;
    }
    $respond('private-provider-placeholder');
    return;
}
putenv('BAPX_TEST_MODE=1');
require dirname(__DIR__).'/app/bootstrap.php';

function check(bool $condition, string $description): void {
    if (!$condition) throw new RuntimeException($description);
}

$socket = stream_socket_server('tcp://127.0.0.1:0');
if (!$socket) throw new RuntimeException('Unable to allocate loopback port');
$address = stream_socket_get_name($socket, false);
fclose($socket);
$cycleDir=sys_get_temp_dir().'/bapx-cycle-fixture-'.bin2hex(random_bytes(8));
mkdir($cycleDir,0700);
$fixture = proc_open([PHP_BINARY, '-S', $address, __FILE__], [
    0 => ['file','/dev/null','r'], 1 => ['file','/dev/null','w'], 2 => ['file','/dev/null','w'],
], $pipes, null, ['BAPX_PROVIDER_TRANSPORT_FIXTURE'=>'1','BAPX_CYCLE_FIXTURE_DIR'=>$cycleDir] + getenv());
if (!is_resource($fixture)) throw new RuntimeException('Loopback provider fixture unavailable');

try {
    $port = (int)substr(strrchr($address, ':'), 1);
    for ($n = 0; $n < 100; $n++) {
        $ready = @fsockopen('127.0.0.1', $port, $errno, $errstr, .1);
        if ($ready) { fclose($ready); break; }
        usleep(20000);
    }
    check((bool)$ready, 'Loopback server did not start');
    $googleEndpoint = static fn(string $scenario): string => 'http://'.$address.'/'.$scenario.'/v1beta/openai';
    $curlOptions = [CURLOPT_PROXY=>'', CURLOPT_FOLLOWLOCATION=>false];
    $transfer = new ReflectionMethod(AiClient::class, 'postWithRetries');
    $request = static function (string $scenario, int $budgetMs = 26000, bool $locked = false) use ($transfer, $googleEndpoint, $curlOptions): array {
        $client = new AiClient();
        if ($locked) (new ReflectionProperty(AiClient::class, 'toolExchangeStarted'))->setValue($client, true);
        $status = null;
        $model = SecretService::PRIMARY_MODEL;
        $payload = json_encode(['model'=>$model, 'messages'=>[['role'=>'user', 'content'=>'Fixture']]], JSON_THROW_ON_ERROR);
        $start = hrtime(true);
        $args = [$googleEndpoint($scenario).'/chat/completions', $payload,
            ['Content-Type: application/json', 'Authorization: Bearer fixture'], &$status, $model,
            $start + $budgetMs * 1000000, false, $curlOptions];
        $transfer->invokeArgs($client, $args);
        return [$status, $client->failureCategory(), $client->transferTimings(), (hrtime(true) - $start) / 1000000];
    };

    [$status, $reason, $attempts] = $request('slow');
    check($status === 200 && $reason === null && count($attempts) === 1, 'A slow primary can complete a single request');
    check($attempts[0]['total_ms'] > 6000 && $attempts[0]['timeout_ms'] === 12000, 'The primary can wait beyond the former 6s cutoff');
    check($attempts[0]['first_byte_ms'] > 0, 'A received response records time to first byte');
    check(!str_contains(json_encode($attempts), 'private-provider-placeholder'), 'Attempt diagnostics exclude provider content');

    [$status, $reason, $attempts] = $request('stalled', 3000);
    check($status === 0 && $reason === 'timeout' && count($attempts) === 1, 'The shared deadline stops retries after one stalled transfer');
    check($attempts[0]['curl_error'] === 28 && !isset($attempts[0]['first_byte_ms']), 'A timeout before a response has no first-byte value');

    [$status, $reason, $attempts, $elapsed] = $request('retry');
    check($status === 200 && $reason === null && array_column($attempts, 'status') === [503,200] && $elapsed >= 1000,
        'Honor Retry-After before retrying the identical Chat Completions payload');
    [$status, $reason, $attempts] = $request('quota', 6000);
    check($status === 429 && $reason === 'rate_limit' && count($attempts) === 1, 'Retry-After beyond the deadline retains the quota response');
    [$status, $reason, $attempts] = $request('denied');
    check($status === 403 && $reason === 'authentication' && count($attempts) === 1, 'Authentication failures are terminal');
    [$status, $reason, $attempts] = $request('locked', 6000, true);
    check($status === 503 && array_column($attempts, 'model') === array_fill(0, 3, SecretService::PRIMARY_MODEL)
        && array_column($attempts, 'inference_phase') === array_fill(0, 3, 'continuation'),
        'Recoverable continuation retries stay on the original model');
    [$status, $reason, $attempts] = $request('slow', 0);
    check($status === 0 && $reason === 'request_deadline' && $attempts === [], 'Local deadline expiry is not a network timeout');
    check(!AiClient::canFailOver(0, 60) && !AiClient::canFailOver(501, 0), 'Certificate and unsupported protocol failures are terminal');

    // The provider switch is valid only before any executed tool call.
    $gate=new ReflectionMethod(AiClient::class,'canTryAnotherConnection');
    $failure=new ReflectionProperty(AiClient::class,'failureCategory');
    $executed=new ReflectionProperty(AiClient::class,'toolCalls');
    $unit=new AiClient();
    $failure->setValue($unit,'rate_limit');
    check($gate->invoke($unit)===true,'OpenRouter rate limit must permit the Google backup.');
    $failure->setValue($unit,'authentication');
    check($gate->invoke($unit)===true,'A revoked OpenRouter key can still use the saved Google key.');
    $executed->setValue($unit,1);
    check($gate->invoke($unit)===false,'An executed tool exchange must not cross providers.');
    $executed->setValue($unit,0);
    $failure->setValue($unit,null);
    check($gate->invoke($unit)===false,'A healthy model must not trigger duplicate inference.');

    // Exercise Chat Completions tool correlation for the Google connection;
    // no production key, live business data or provider account.
    $registry=new class extends AgentToolRegistry {
        public function __construct() {}
        public function declarations(): array {
            return [['name'=>'connection_probe','description'=>'Synthetic fixture',
                'parameters'=>['type'=>'object','properties'=>(object)[],'additionalProperties'=>false]]];
        }
        public function run(string $name,array $args): array {
            return $name==='connection_probe'&&$args===[]?['challenge'=>'fixture-observed-challenge']:['error'=>'Invalid'];
        }
    };
    $client=new AiClient();
    $complete = new ReflectionMethod(AiClient::class, 'completeCompatibleLoop');
    $config = ['provider'=>'google', 'endpoint'=>$googleEndpoint('tool_probe'), 'apiKey'=>'fixture-key',
        'model'=>SecretService::PRIMARY_MODEL, 'models'=>[SecretService::PRIMARY_MODEL], 'configured'=>true];
    $runCompatible = static function (AiClient $client, array $config, string $prompt, AgentToolRegistry|RuntimeAgentToolRegistry $tools) use ($complete, $curlOptions): string {
        return $complete->invoke($client, $config, $prompt, 256, null, $tools, 2, hrtime(true)+6000000000, false, $curlOptions);
    };
    $reply = $runCompatible($client, $config, 'Call the tool.', $registry);
    check(AiClient::probeSucceeded($reply, 'fixture-observed-challenge', $client->toolCalls(), true),
        'Function schema, correlated result and opaque call-level Google signature survive one continuation');
    check(count($client->transferTimings()) === 2, 'Expected exactly two provider transfers');

    $client = new AiClient();
    $customGoogle = $config;
    $customGoogle['model'] = 'custom-google-model';
    $customGoogle['models'] = [$customGoogle['model']];
    $reply = $runCompatible($client, $customGoogle, 'Call the tool.', $registry);
    check(AiClient::probeSucceeded($reply, 'fixture-observed-challenge', $client->toolCalls(), true),
        'A configured Google model preserves its ID and assistant-level Google signature');

    $client = new AiClient();
    $custom = ['provider'=>'openai_compatible', 'endpoint'=>'http://'.$address.'/tool_probe', 'apiKey'=>'fixture-compatible',
        'model'=>'fixture/custom-model', 'models'=>['fixture/custom-model'], 'configured'=>true];
    $reply = $runCompatible($client, $custom, 'Call the tool.', $registry);
    check(AiClient::probeSucceeded($reply, 'fixture-observed-challenge', $client->toolCalls(), true),
        'A custom connection can complete the tool exchange without forwarding Google metadata');

    foreach (['tool_probe_bad_signature'=>$config, 'tool_probe_large_signature'=>$customGoogle] as $scenario=>$invalid) {
        $client = new AiClient();
        $invalid['endpoint'] = $googleEndpoint($scenario);
        $reply = $runCompatible($client, $invalid, 'Call the tool.', $registry);
        check(AiClient::isError($reply) && $client->toolCalls() === 0 && count($client->transferTimings()) === 1,
            'Invalid or oversized Google continuation metadata blocks tool execution');
    }

    $client = new AiClient();
    $registry = new RuntimeAgentToolRegistry('support', null, new SupportToolRegistry(null), '');
    $config['endpoint'] = $googleEndpoint('tool_discovery');
    $reply = $runCompatible($client, $config, 'Find the appropriate tool.', $registry);
    check($reply === 'Shipping is calculated at checkout.' && $client->toolCalls() === 2,
        'Deferred discovery updates Chat Completions declarations and executes the actual policy tool');
    check(count($client->transferTimings()) === 3, 'Expected exactly three provider transfers for discovery and execution');
    // Ordinary generation avoids only a previously observed transient failure.
    // Explicit diagnostics still contact the selected endpoint, with no fake attempts.
    $healthDir=sys_get_temp_dir().'/bapx-ai-health-fixture-'.bin2hex(random_bytes(6));
    mkdir($healthDir,0700);
    $healthFile=$healthDir.'/state.json';
    try{
        $health=new \App\Services\AiProviderHealthService($healthFile);
        $plan=[['provider'=>'google','endpoint'=>$googleEndpoint('cooldown'),'apiKey'=>'fixture-key',
            'model'=>SecretService::PRIMARY_MODEL,'models'=>SecretService::GOOGLE_MODELS,'configured'=>true]];
        $routeAll=new ReflectionMethod(AiClient::class,'route');
        $client=new AiClient(health:$health);
        $reply=$routeAll->invoke($client,'Reply connection ok',256,0.0,null,null,0,$plan);
        check($reply==='connection ok'&&array_column($client->transferTimings(),'status')===[503,503,503,200],'Three real failures before recovering on fallback');
        $client=new AiClient(health:new \App\Services\AiProviderHealthService($healthFile));
        $reply=$routeAll->invoke($client,'Reply connection ok',256,0.0,null,null,0,$plan);
        check($reply==='connection ok'&&array_column($client->transferTimings(),'model')===[SecretService::PRIMARY_MODEL,SecretService::PRIMARY_MODEL,SecretService::PRIMARY_MODEL,SecretService::FALLBACK_MODEL],'Fresh request starts at primary despite prior transient failures');

        $shortHoldPlan = $plan;
        $shortHoldPlan[0]['endpoint'] = $googleEndpoint('short_hold');
        $health->record($shortHoldPlan[0], SecretService::PRIMARY_MODEL, 429, 0, 2500);
        AgentTraceService::begin(bin2hex(random_bytes(8)), null, AiClient::agentLimits());
        try {
            $client = new AiClient(health: $health);
            $reply = $routeAll->invoke($client, 'Reply connection ok', 256, 0.0, null, null, 0, $shortHoldPlan);
            check($reply === 'connection ok' && array_column($client->transferTimings(), 'model') === [SecretService::FALLBACK_MODEL],
                'A ready alternative starts ahead of a model with a short hold');
            check(!in_array('provider_backoff', array_column(AgentTraceService::snapshot()['spans'], 'name'), true),
                'A ready alternative does not spend time waiting for a held model');
        } finally {
            AgentTraceService::reset();
        }

        foreach (SecretService::GOOGLE_MODELS as $model) {
            $health->record($shortHoldPlan[0], $model, 429, 0, 2000);
        }
        AgentTraceService::begin(bin2hex(random_bytes(8)), null, AiClient::agentLimits());
        try {
            $client = new AiClient(health: $health);
            $reply = $routeAll->invoke($client, 'Reply connection ok', 256, 0.0, null, null, 0, $shortHoldPlan);
            check($reply === 'connection ok' && array_column($client->transferTimings(), 'model') === [SecretService::PRIMARY_MODEL],
                'A fresh turn recovers in configured order when all models start under short holds');
            $waits = array_filter(AgentTraceService::snapshot()['spans'], static fn(array $span): bool => $span['name'] === 'provider_backoff');
            check(count($waits) === 1 && reset($waits)['duration_ms'] >= 1000,
                'The real request trace records waiting for expiry before a single provider transfer');
        } finally {
            AgentTraceService::reset();
        }

        $health->record($plan[0],SecretService::PRIMARY_MODEL,429,0,60000);
        $health->record($plan[0],SecretService::FALLBACK_MODEL,429,0,60000);
        $client=new AiClient(health:$health);
        $reply=$routeAll->invoke($client,'Reply connection ok',256,0.0,null,null,0,$plan);
        check(AiClient::isError($reply)&&$client->failureCategory()==='provider_cooldown'&&$client->transferTimings()===[],'All cooled models return honest local outcome without transfers');
        $rotated = $plan;
        $rotated[0]['apiKey'] = 'replacement-fixture-key';
        $rotated[0]['models'] = [SecretService::FALLBACK_MODEL];
        $rotated[0]['model'] = SecretService::FALLBACK_MODEL;
        $client = new AiClient(health: new \App\Services\AiProviderHealthService($healthFile));
        $reply = $routeAll->invoke($client, 'Reply connection ok', 256, 0.0, null, null, 0, $rotated);
        check($reply === 'connection ok' && count($client->transferTimings()) === 1,
            'Replacement credential reaches the provider while the old credential is held');
        check($health->availablePlan($plan) === [], 'Replacement success leaves the old credential holds intact');
        $healthContents = file_get_contents($healthFile);
        $entries = json_decode($healthContents, true)['entries'];
        $diagnostics = json_encode($client->transferTimings());
        check(!str_contains($diagnostics, 'replacement-fixture-key') && !str_contains($healthContents, 'fixture-key'),
            'Neither transport diagnostics nor health storage reveal credentials');
        foreach (array_keys($entries) as $fingerprint) {
            check(!str_contains($diagnostics, $fingerprint), 'Private health identity never enters attempt diagnostics');
        }
        $diagnosticPlan=$plan;$diagnosticPlan[0]['models']=[SecretService::FALLBACK_MODEL];$diagnosticPlan[0]['model']=SecretService::FALLBACK_MODEL;
        $reply=$routeAll->invoke($client,'Reply connection ok',256,0.0,null,null,0,$diagnosticPlan,true);
        check($reply==='connection ok'&&count($client->transferTimings())===1,'Explicit diagnostic bypasses cooldown');
        check($health->availablePlan($plan)[0]['models']===[SecretService::FALLBACK_MODEL],'Successful diagnostic clears only its own cooldown');
        $bad=$plan;$bad[0]['endpoint']=$googleEndpoint('bad_schema');$client=new AiClient(health:$health);
        $reply=$routeAll->invoke($client,'Reply connection ok',256,0.0,null,null,0,$bad);
        $attempts=$client->transferTimings();
        check(AiClient::isError($reply)&&count($attempts)===2&&($attempts[0]['request_error_hint']??null)==='tool_schema'&&($attempts[1]['request_error_hint']??null)==='tool_schema','HTTP 400 receives an allowlisted hint without repeating the invalid request');
        check(!str_contains(json_encode($attempts),'private-fixture-raw-error')&&!str_contains($reply,'private-fixture-raw-error'),'Provider error body never reaches output');
        check($health->availablePlan($bad)===$bad,'Request/schema failure never cools an endpoint');
    }finally{
        foreach(glob($healthDir.'/*')?:[] as $file)unlink($file);
        rmdir($healthDir);
    }
    // Exercise the real synchronous fallback loop through the shared wire adapter over loopback.
    // The production compatible endpoint's DNS/HTTPS guard is unchanged.
    AgentTraceService::begin(str_repeat('b',16),null,AiClient::agentLimits());
    $client=new AiClient();
    $plan=[
        ['provider'=>'google','endpoint'=>$googleEndpoint('cycle'),'apiKey'=>'fixture','model'=>SecretService::PRIMARY_MODEL,'models'=>SecretService::GOOGLE_MODELS,'configured'=>true],
        ['provider'=>'openrouter','endpoint'=>'http://'.$address.'/cycle','apiKey'=>'fixture','model'=>'openrouter/free','models'=>['openrouter/free'],'configured'=>true],
    ];
    $compatibleLoop=new ReflectionMethod(AiClient::class,'completeCompatibleLoop');
    $ordered=new ReflectionMethod(AiClient::class,'completeWithFallbacks');
    $runOrdered = static function (AiClient $client, array $plan) use ($ordered, $compatibleLoop, $curlOptions): string {
        $deadline = hrtime(true) + 40_000_000_000;
        return $ordered->invoke($client, $plan, $deadline, true,
            static function (array $cfg) use ($client, $compatibleLoop, $deadline, $curlOptions): string {
                return $compatibleLoop->invoke($client, $cfg, 'Fixture', 32, null, null, 0, $deadline, false, $curlOptions);
            });
    };
    $reply = $runOrdered($client, $plan);
    $attempts=$client->transferTimings();
    $expected=array_merge(array_fill(0,3,SecretService::PRIMARY_MODEL),array_fill(0,3,SecretService::FALLBACK_MODEL),
        array_fill(0,3,'openrouter/free'),[SecretService::PRIMARY_MODEL]);
    check($reply==='connection ok'&&array_column($attempts,'model')===$expected,'Three tries per model, OpenRouter last, then first model again');
    check(array_column($attempts,'cycle')===array_merge(array_fill(0,9,1),[2]),'Actual cycle numbers retained');
    check(array_column($attempts,'attempt_in_model')===[1,2,3,1,2,3,1,2,3,1],'Per-model retry numbers retained');
    check(array_column($attempts,'status')===array_merge(array_fill(0,9,503),[200]),'Actual provider statuses preserved');
    $spans=array_values(array_filter(AgentTraceService::snapshot()['spans'],static fn(array $span):bool=>$span['type']==='model_attempt'));
    check(count($spans)===10&&($spans[9]['cycle']??null)===2,'All ten observed transfers survive trace budget and metadata projection');
    $waits=array_values(array_filter(AgentTraceService::snapshot()['spans'],static fn(array $span):bool=>$span['name']==='provider_backoff'));
    check(count($waits)===9,'Every failed attempt waits before the next transfer');
    foreach($waits as $i=>$wait)check($wait['duration_ms']>=[1000,2000,4000][$i%3]-5,'Observed exponential backoff before retries and model switches');

    echo "PASS exact three-model ordered retry cycle: ".implode(' -> ',array_column($attempts,'model'))."\n";
    AgentTraceService::reset();

    $probeFailureMessage = new ReflectionMethod(AiClient::class, 'probeFailureMessage');
    foreach (['auth_ordered', 'auth_independent', 'auth_cycle'] as $scenario) {
        $endpoint = 'http://'.$address.'/'.$scenario;
        $google = ['provider'=>'google','endpoint'=>$googleEndpoint($scenario),'apiKey'=>'fixture-google',
            'model'=>SecretService::PRIMARY_MODEL,'models'=>SecretService::GOOGLE_MODELS,'configured'=>true];
        $compatible = ['provider'=>$scenario === 'auth_ordered' ? 'openrouter' : 'openai_compatible',
            'endpoint'=>$endpoint,'apiKey'=>'fixture-compatible','model'=>'fixture/first',
            'models'=>['fixture/first','fixture/second','fixture/third'],'configured'=>true];
        $client = new AiClient();
        $reply = $runOrdered($client, $scenario === 'auth_ordered' ? [$google,$compatible] : [$compatible,$google]);
        $attempts = $client->transferTimings();
        check(!str_contains(json_encode($attempts), 'private-fixture-auth-details')
            && !str_contains($reply, 'private-fixture-auth-details'), 'Raw provider denial details remain private');
        if ($scenario === 'auth_ordered') {
            check(array_column($attempts,'status') === [503,429,401], 'Capacity, quota and a rejected credential remain separate observed transfers');
            check(array_column($attempts,'provider') === ['google','google','openrouter'], 'Remaining models on the rejected OpenRouter connection are skipped');
            check($client->failureCategory() === 'authentication' && str_contains($reply,'OpenRouter API Key')
                && !str_contains($reply,'Google API Key'), 'The owner receives the actual rejected connection field');
            $message = $probeFailureMessage->invoke(null, 'authentication', end($attempts));
            check(str_contains($message, 'OpenRouter API Key') && !str_contains($message, 'Google API Key')
                && !str_contains($message, $endpoint) && !str_contains($message, 'private-fixture-auth-details'),
                'Owner diagnostics preserve the rejected connection hint without upstream details');
        } elseif ($scenario === 'auth_independent') {
            check(array_column($attempts,'status') === [401,200] && $reply === 'independent connection ok',
                'A rejected key skips its own models and leaves the next connection eligible');
            $message = $probeFailureMessage->invoke(null, 'authentication', $attempts[0]);
            check(str_contains($message, 'Compatible API Key'), 'Compatible endpoint rejection identifies its own key field');
        } else {
            check(array_column($attempts,'status') === [503,503,503,401,403,403]
                && array_column($attempts,'cycle') === array_fill(0,6,1),
                'A later 401 prevents an earlier model on that connection from re-entering cycle two');
            check(str_contains($reply,'Google connection permissions') && !str_contains($reply,'API Key'),
                '403 guides the owner to connection permissions');
            $message = $probeFailureMessage->invoke(null, 'authentication', end($attempts));
            check(str_contains($message, 'Google connection permissions') && !str_contains($message, 'API Key'),
                'Owner diagnostic keeps 403 distinct from a rejected key');
        }
    }
    echo "PASS credential rejection skips the connection within both cycles and identifies the right field\n";

    // A concurrent hold observed after a tool executes must stop the locked continuation.
    $lockedHealth = new \App\Services\AiProviderHealthService($cycleDir . '/locked-health.json');
    $lockedConfig = ['provider'=>'google','endpoint'=>$googleEndpoint('tool_probe'),'apiKey'=>'fixture-key',
        'model'=>SecretService::PRIMARY_MODEL,'models'=>SecretService::GOOGLE_MODELS,'configured'=>true];
    $holdingTool = new class($lockedHealth, $lockedConfig) extends AgentToolRegistry {
        public function __construct(private \App\Services\AiProviderHealthService $health, private array $connection) {}
        public function declarations(): array {
            return [['name'=>'connection_probe','description'=>'Synthetic fixture',
                'parameters'=>['type'=>'object','properties'=>(object)[],'additionalProperties'=>false]]];
        }
        public function run(string $name, array $args): array {
            $this->health->record($this->connection, SecretService::PRIMARY_MODEL, 429, 0, 60000);
            return ['challenge'=>'fixture-observed-challenge'];
        }
    };
    AgentTraceService::begin(bin2hex(random_bytes(8)), null, AiClient::agentLimits());
    $client = new AiClient(health: $lockedHealth);
    $routeAll = new ReflectionMethod(AiClient::class, 'route');
    $reply = $routeAll->invoke($client, 'Call the tool.', 256, 0.0, null, $holdingTool, 2, [$lockedConfig]);
    check($client->failureCategory() === 'provider_cooldown' && str_contains($reply, 'provider hold'),
        'A hold recorded during tool execution stops the continuation locally');
    check(count($client->transferTimings()) === 1 && $client->toolCalls() === 1,
        'A held continuation neither transfers nor replays its tool through fallback');
    $modelSpans = array_filter(AgentTraceService::snapshot()['spans'], static fn(array $span): bool => $span['type'] === 'model_attempt');
    check(count($modelSpans) === 1, 'A held continuation has no synthetic model attempt span');
    AgentTraceService::reset();
    AgentTraceService::begin(bin2hex(random_bytes(8)), null, AiClient::agentLimits());
    $client = new AiClient(health: $lockedHealth);
    $reply = $routeAll->invoke($client, 'Call the tool.', 256, 0.0, null, $holdingTool, 2, [$lockedConfig], true);
    check($reply === 'fixture-observed-challenge' && count($client->transferTimings()) === 2,
        'Explicit diagnostics bypass holds at both selection and continuation');
    AgentTraceService::reset();

    // Structured Google RetryInfo can exceed a Retry-After header. The client
    // must wait the full minimum and retain only bounded quota metadata.
    foreach(['body_retry','daily_quota'] as $scenario){
        AgentTraceService::begin(bin2hex(random_bytes(8)),null,AiClient::agentLimits());
        $client=new AiClient(health: new \App\Services\AiProviderHealthService($cycleDir . '/health-' . $scenario . '.json'));
        $cfg=[['provider'=>'google','endpoint'=>$googleEndpoint($scenario),'apiKey'=>'fixture','model'=>SecretService::PRIMARY_MODEL,'models'=>[SecretService::PRIMARY_MODEL],'configured'=>true]];
        $routeAll=new ReflectionMethod(AiClient::class,'route');
        $start=hrtime(true);
        $reply=$routeAll->invoke($client,'Fixture',64,0.0,null,null,0,$cfg);
        $attempts=$client->transferTimings();
        check(($attempts[0]['retry_after_ms']??null)===1250,'Body RetryInfo is not truncated to smaller header');
        check(($attempts[0]['quota_kind']??null)===($scenario==='daily_quota'?'daily':'minute'),'Safe quota classification retained');
        check(!str_contains(json_encode($attempts),'private-fixture-project'),'Quota subject is never persisted');
        if($scenario==='body_retry'){
            check($reply==='connection ok'&&array_column($attempts,'status')===[429,200],'Temporary quota recovers after the indicated delay');
            check((hrtime(true)-$start)/1000000>=1250,'Measured wait honors structured provider minimum');
            check(($attempts[1]['usage']['total_tokens']??null)===5&&($attempts[1]['finish_reason']??null)==='stop','Response usage and finish reason retained');
        }else check(AiClient::isError($reply)&&count($attempts)===1,'Daily quota does not blindly retry within the same request');
        AgentTraceService::reset();
    }
    AgentTraceService::begin(str_repeat('a',16), null, ['max_model_calls' => 1]);
    $prior = AgentTraceService::startSpan('model_attempt', SecretService::PRIMARY_MODEL);
    AgentTraceService::endSpan($prior, 'complete');
    [$status, $reason, $attempts] = $request('slow');
    check($status === 0 && $reason === 'provider_budget' && $attempts === [], 'The trace budget stops execution before cURL');

    echo "PASS loopback provider transport, slow primary, failover, backoff, local budgets and safe attempt timing.\n";
} finally {
    AgentTraceService::reset();
    proc_terminate($fixture);
    proc_close($fixture);
    foreach(glob($cycleDir.'/*')?:[] as $file)unlink($file);
    rmdir($cycleDir);
}
