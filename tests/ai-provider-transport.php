<?php
declare(strict_types=1);

use App\Services\{AiClient, AgentTraceService, SecretService};

// Run through real cURL against a loopback-only HTTP fixture. Never read provider keys.
if (PHP_SAPI !== 'cli' && !(PHP_SAPI === 'cli-server' && getenv('BAPX_PROVIDER_TRANSPORT_FIXTURE') === '1')) {
    http_response_code(404);
    return;
}
if (PHP_SAPI === 'cli-server') {
    [$scenario, $model] = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'), 2);
    $primary = str_starts_with($model, 'gemma-4-31b-it:');
    header('Content-Type: application/json');
    if($scenario==='cycle'){
        $payload=json_decode((string)file_get_contents('php://input'),true);
        $id=$model==='chat/completions'?($payload['model']??''):explode(':',$model)[0];
        $dir=getenv('BAPX_CYCLE_FIXTURE_DIR');
        if(!is_string($dir)||!is_dir($dir)){http_response_code(400);echo '{}';return;}
        $key=$dir.'/'.hash('sha256',$id);
        $count=(int)@file_get_contents($key)+1;
        file_put_contents($key,(string)$count,LOCK_EX);
        if($id==='gemma-4-31b-it'&&$count===4){
            echo json_encode(['candidates'=>[['content'=>['parts'=>[['text'=>'<final_answer>connection ok</final_answer>']]]]]]);return;
        }
        http_response_code(503);echo '{"error":{"status":"UNAVAILABLE"}}';return;
    }
    if (in_array($scenario, ['auth_ordered', 'auth_independent', 'auth_cycle'], true)) {
        if ($model === 'chat/completions') {
            $payload = json_decode((string)file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
            $status = $scenario === 'auth_cycle'
                ? (($payload['model'] ?? '') === 'fixture/first' ? 503 : 401) : 401;
        } elseif ($scenario === 'auth_independent') {
            echo json_encode(['candidates'=>[['content'=>['parts'=>[['text'=>'<final_answer>independent connection ok</final_answer>']]]]]]);
            return;
        } else {
            $status = match ($scenario) {
                'auth_cycle' => 403,
                default => $primary ? 503 : 429,
            };
            if ($status !== 403) {
                header('Retry-After: 60');
            }
        }
        http_response_code($status);
        echo '{"error":{"message":"private-fixture-auth-details"}}';
        return;
    }
    if($scenario==='body_retry'||$scenario==='daily_quota'){
        $file=getenv('BAPX_CYCLE_FIXTURE_DIR').'/'.$scenario;
        $count=(int)@file_get_contents($file)+1;file_put_contents($file,(string)$count,LOCK_EX);
        if($count===1||$scenario==='daily_quota'){
            http_response_code(429);header('Retry-After: 1');
            echo json_encode(['error'=>['details'=>[
                ['@type'=>'type.googleapis.com/google.rpc.RetryInfo','retryDelay'=>'1.25s'],
                ['@type'=>'type.googleapis.com/google.rpc.QuotaFailure','violations'=>[['quotaId'=>$scenario==='daily_quota'?'RequestsPerDay':'RequestsPerMinute','quotaValue'=>'10','subject'=>'private-fixture-project']]]
            ]]]);
            return;
        }
        echo json_encode(['candidates'=>[['content'=>['parts'=>[['text'=>'<final_answer>connection ok</final_answer>']]],'finishReason'=>'STOP']],'usageMetadata'=>['promptTokenCount'=>3,'candidatesTokenCount'=>2,'totalTokenCount'=>5],'modelVersion'=>'gemma-4-31b-it']);
        return;
    }
    if($scenario==='cooldown') {
        if($primary){http_response_code(503);echo '{"error":{"status":"UNAVAILABLE"}}';return;}
        echo json_encode(['candidates'=>[['content'=>['parts'=>[['text'=>'<final_answer>connection ok</final_answer>']]]]]]);return;
    }
    if($scenario==='bad_schema') {
        http_response_code(400);
        echo json_encode(['error'=>['status'=>'INVALID_ARGUMENT','message'=>'function_declarations parameters_json_schema: private-fixture-raw-error']]);
        return;
    }
    if($scenario==='tool_probe') {
        $request=json_decode((string)file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
        $tool=$request['tools'][0]['functionDeclarations'][0]??[];
        $objects=json_decode((string)file_get_contents('php://input'));
        if(!is_object($objects->tools[0]->functionDeclarations[0]->parametersJsonSchema->properties??null) || (!$primary && isset($objects->generationConfig->thinkingConfig))) {
            http_response_code(400);echo '{"error":"object shape or thinking config"}';return;
        }
        if(isset($tool['parameters'])||($tool['parametersJsonSchema']['additionalProperties']??null)!==false) {
            http_response_code(400);echo '{"error":"wrong schema"}';return;
        }
        $contents=$request['contents'];
        if(count($contents)===1){
            echo json_encode(['candidates'=>[['content'=>['parts'=>[['functionCall'=>['name'=>'connection_probe','args'=>(object)[],'id'=>'fixture-call']]]]]]]);
        } else {
            $response=end($contents)['parts'][0]['functionResponse']??[];
            $previous=$contents[count($contents)-2]['parts'][0]['functionCall']??[];
            if(!is_object($objects->contents[count($contents)-2]->parts[0]->functionCall->args??null)||($response['id']??'')!=='fixture-call'||($previous['id']??'')!=='fixture-call'||($response['response']['challenge']??'')!=='fixture-observed-challenge'){
                http_response_code(400);echo '{"error":"missing correlated response"}';return;
            }
            echo json_encode(['candidates'=>[['content'=>['parts'=>[['text'=>'<final_answer>fixture-observed-challenge</final_answer>']]]]]]);
        }
        return;
    }
    if($scenario==='tool_discovery') {
        $request=json_decode((string)file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
        $names=array_column($request['tools'][0]['functionDeclarations']??[],'name');
        $contents=$request['contents'];
        $n=count($contents);
        $response=end($contents)['parts'][0]['functionResponse']??[];
        if($n===1 && $names===['read_runtime_skill','tool_search']) {
            $parts=[['functionCall'=>['name'=>'tool_search','args'=>['query'=>'public_policy'],'id'=>'fixture-discovery']]];
        } elseif($n===3 && in_array('public_policy',$names,true) && ($response['id']??'')==='fixture-discovery' && ($response['response']['matches'][0]['name']??'')==='public_policy') {
            $parts=[['functionCall'=>['name'=>'public_policy','args'=>['key'=>'shipping_cost'],'id'=>'fixture-policy']]];
        } elseif($n===5 && ($response['id']??'')==='fixture-policy' && ($response['response']['value']??'')==='Shipping is calculated at checkout.') {
            $parts=[['text'=>'<final_answer>Shipping is calculated at checkout.</final_answer>']];
        } else { http_response_code(400);echo '{"error":"discovery exchange invalid"}';return; }
        echo json_encode(['candidates'=>[['content'=>['parts'=>$parts]]]]);
        return;
    }
    if ($scenario === 'slow' && $primary) usleep(6_350_000);
    if ($scenario === 'stalled' && $primary) usleep(3_300_000);
    if (($scenario === 'stalled' && !$primary) || ($scenario === 'retry' && $primary) || $scenario === 'locked') {
        http_response_code(503);
        if ($scenario === 'retry') header('Retry-After: 1');
    }
    if ($scenario === 'quota') {
        http_response_code(429);
        header('Retry-After: '.gmdate('D, d M Y H:i:s \G\M\T', time() + 60));
    }
    if ($scenario === 'denied') http_response_code(403);
    echo json_encode(['fixture' => 'private-provider-placeholder']);
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
    $route = new ReflectionMethod(AiClient::class, 'postGoogleRouted');
    $request = static function (string $scenario, int $budgetMs = 26000, bool $locked = false) use ($route, $address): array {
        $client = new AiClient();
        $status = null;
        $model = SecretService::PRIMARY_MODEL;
        $start = hrtime(true);
        $args = ['http://'.$address.'/'.$scenario, &$model, '{}', ['Content-Type: application/json'],
            &$status, &$locked, $start + $budgetMs * 1000000];
        $route->invokeArgs($client, $args);
        return [$status, $client->failureCategory(), $client->transferTimings(), (hrtime(true) - $start) / 1000000, $locked];
    };

    [$status, $reason, $attempts] = $request('slow');
    check($status === 200 && $reason === null && count($attempts) === 1, 'A slow primary can complete a single request');
    check($attempts[0]['total_ms'] > 6000 && $attempts[0]['timeout_ms'] === 12000, 'The primary can wait beyond the former 6s cutoff');
    check($attempts[0]['first_byte_ms'] > 0, 'A received response records time to first byte');

    [$status, $reason, $attempts] = $request('stalled', 6000);
    check($status === 503 && $reason === 'provider_server', 'The actual upstream fallback response is retained');
    check(array_column($attempts, 'model') === [SecretService::PRIMARY_MODEL, SecretService::FALLBACK_MODEL], 'A stalled primary is not replayed');
    check($attempts[0]['curl_error'] === 28 && !isset($attempts[0]['first_byte_ms']), 'A timeout before a response has no first-byte value');
    check(!str_contains(json_encode($attempts), 'private-provider-placeholder'), 'Attempt diagnostics exclude provider content');

    [$status, $reason, $attempts, $elapsed, $locked] = $request('retry');
    check($status === 200 && $reason === null && $locked && count($attempts) === 2 && $elapsed >= 1000, 'Honor Retry-After before recovering on fallback');
    [$status, $reason, $attempts] = $request('quota', 6000);
    check($status === 429 && $reason === 'rate_limit' && count($attempts) === 1, 'Retry-After beyond the deadline retains the quota response');
    [$status, $reason, $attempts] = $request('denied');
    check($status === 403 && $reason === 'authentication' && count($attempts) === 1, 'Authentication failures are terminal');
    [$status, $reason, $attempts] = $request('locked', 6000, true);
    check($status === 503 && count($attempts) === 1, 'A locked tool exchange cannot move to another model');
    [$status, $reason, $attempts] = $request('slow', 0);
    check($status === 0 && $reason === 'request_deadline' && $attempts === [], 'Local deadline expiry is not a network timeout');
    check(!AiClient::canFailOver(0, 60) && !AiClient::canFailOver(501, 0), 'Certificate and unsupported protocol failures are terminal');

    // The provider switch is valid only before any executed tool call.
    $gate=new ReflectionMethod(AiClient::class,'routerNeedsGoogleFallback');
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

    // Exercise the actual Google two-turn wire exchange against the fixture;
    // no production key, live business data or provider account.
    $registry=new class extends \App\Services\AgentToolRegistry {
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
    $complete=new ReflectionMethod(AiClient::class,'completeGoogleTools');
    $config=['provider'=>'google','endpoint'=>'http://'.$address.'/tool_probe','apiKey'=>'fixture-key',
        'model'=>SecretService::PRIMARY_MODEL,'models'=>[SecretService::PRIMARY_MODEL],'configured'=>true];
    $reply=$complete->invoke($client,$config,'Call the tool.', $registry,256,0.0,2,null,hrtime(true)+6000000000);
    check(AiClient::probeSucceeded($reply,'fixture-observed-challenge',$client->toolCalls(),true),'Actual tool call, correlated response and final text must all succeed');
    check(count($client->transferTimings())===2,'Expected exactly two provider transfers');
    $client=new AiClient();
    $config['model']='custom-google-model';$config['models']=[$config['model']];
    $reply=$complete->invoke($client,$config,'Call the tool.', $registry,256,0.0,2,null,hrtime(true)+6000000000);
    check(AiClient::probeSucceeded($reply,'fixture-observed-challenge',$client->toolCalls(),true),'Custom Google model preserves JSON object shapes while omitting thinking configuration');
    $client=new AiClient();
    $config['model']=SecretService::PRIMARY_MODEL;$config['models']=[$config['model']];
    $registry=new \App\Services\RuntimeAgentToolRegistry('support',null,new \App\Services\SupportToolRegistry(null),'');
    $config['endpoint']='http://'.$address.'/tool_discovery';
    $reply=$complete->invoke($client,$config,'Find the appropriate tool.', $registry,256,0.0,2,null,hrtime(true)+6000000000);
    check($reply==='Shipping is calculated at checkout.'&&$client->toolCalls()===2,'Deferred discovery must update subsequent wire declarations and execute the actual policy tool');
    check(count($client->transferTimings())===3,'Expected exactly three provider transfers for discovery and execution');
    // Ordinary generation avoids only a previously observed transient failure.
    // Explicit diagnostics still contact the selected endpoint, with no fake attempts.
    $healthDir=sys_get_temp_dir().'/bapx-ai-health-fixture-'.bin2hex(random_bytes(6));
    mkdir($healthDir,0700);
    $healthFile=$healthDir.'/state.json';
    try{
        $health=new \App\Services\AiProviderHealthService($healthFile);
        $plan=[['provider'=>'google','endpoint'=>'http://'.$address.'/cooldown','apiKey'=>'fixture-key',
            'model'=>SecretService::PRIMARY_MODEL,'models'=>SecretService::GOOGLE_MODELS,'configured'=>true]];
        $routeAll=new ReflectionMethod(AiClient::class,'route');
        $client=new AiClient(health:$health);
        $reply=$routeAll->invoke($client,'Reply connection ok',256,0.0,null,null,0,$plan);
        check($reply==='connection ok'&&array_column($client->transferTimings(),'status')===[503,503,503,200],'Three real failures before recovering on fallback');
        $client=new AiClient(health:new \App\Services\AiProviderHealthService($healthFile));
        $reply=$routeAll->invoke($client,'Reply connection ok',256,0.0,null,null,0,$plan);
        check($reply==='connection ok'&&array_column($client->transferTimings(),'model')===[SecretService::PRIMARY_MODEL,SecretService::PRIMARY_MODEL,SecretService::PRIMARY_MODEL,SecretService::FALLBACK_MODEL],'Fresh request starts at primary despite prior transient failures');
        $health->record($plan[0],SecretService::PRIMARY_MODEL,429,0,60000);
        $health->record($plan[0],SecretService::FALLBACK_MODEL,429,0,60000);
        $client=new AiClient(health:$health);
        $reply=$routeAll->invoke($client,'Reply connection ok',256,0.0,null,null,0,$plan);
        check(AiClient::isError($reply)&&$client->failureCategory()==='provider_cooldown'&&$client->transferTimings()===[],'All cooled models return honest local outcome without transfers');
        $diagnosticPlan=$plan;$diagnosticPlan[0]['models']=[SecretService::FALLBACK_MODEL];$diagnosticPlan[0]['model']=SecretService::FALLBACK_MODEL;
        $reply=$routeAll->invoke($client,'Reply connection ok',256,0.0,null,null,0,$diagnosticPlan,true);
        check($reply==='connection ok'&&count($client->transferTimings())===1,'Explicit diagnostic bypasses cooldown');
        check($health->availablePlan($plan)[0]['models']===[SecretService::FALLBACK_MODEL],'Successful diagnostic clears only its own cooldown');
        $bad=$plan;$bad[0]['endpoint']='http://'.$address.'/bad_schema';$client=new AiClient(health:$health);
        $reply=$routeAll->invoke($client,'Reply connection ok',256,0.0,null,null,0,$bad);
        $attempts=$client->transferTimings();
        check(AiClient::isError($reply)&&count($attempts)===2&&($attempts[0]['request_error_hint']??null)==='tool_schema'&&($attempts[1]['request_error_hint']??null)==='tool_schema','HTTP 400 receives an allowlisted hint without repeating the invalid request');
        check(!str_contains(json_encode($attempts),'private-fixture-raw-error')&&!str_contains($reply,'private-fixture-raw-error'),'Provider error body never reaches output');
        check($health->availablePlan($bad)===$bad,'Request/schema failure never cools an endpoint');
    }finally{
        foreach(glob($healthDir.'/*')?:[] as $file)unlink($file);
        rmdir($healthDir);
    }
    // Exercise the real synchronous fallback loop and both wire adapters over loopback.
    // The production compatible endpoint's DNS/HTTPS guard is unchanged.
    AgentTraceService::begin(str_repeat('b',16),null,AiClient::agentLimits());
    $client=new AiClient();
    $plan=[
        ['provider'=>'google','endpoint'=>'http://'.$address.'/cycle','apiKey'=>'fixture','model'=>SecretService::PRIMARY_MODEL,'models'=>SecretService::GOOGLE_MODELS,'configured'=>true],
        ['provider'=>'openrouter','endpoint'=>'http://'.$address.'/cycle','apiKey'=>'fixture','model'=>'openrouter/free','models'=>['openrouter/free'],'configured'=>true],
    ];
    $googleText=new ReflectionMethod(AiClient::class,'completeGoogleText');
    $compatibleLoop=new ReflectionMethod(AiClient::class,'completeCompatibleLoop');
    $ordered=new ReflectionMethod(AiClient::class,'completeWithFallbacks');
    $runOrdered = static function (AiClient $client, array $plan) use ($ordered, $googleText, $compatibleLoop): string {
        $deadline = hrtime(true) + 40_000_000_000;
        return $ordered->invoke($client, $plan, $deadline, true,
            static function (array $cfg) use ($client, $googleText, $compatibleLoop, $deadline): string {
                return $cfg['provider'] === 'google' ? $googleText->invoke($client, $cfg, 'Fixture', 32, 0.0, $deadline)
                    : $compatibleLoop->invoke($client, $cfg, 'Fixture', 32, null, null, 0, $deadline, false, [CURLOPT_PROXY=>'',CURLOPT_FOLLOWLOCATION=>false]);
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

    foreach (['auth_ordered', 'auth_independent', 'auth_cycle'] as $scenario) {
        $endpoint = 'http://'.$address.'/'.$scenario;
        $google = ['provider'=>'google','endpoint'=>$endpoint,'apiKey'=>'fixture-google',
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
        } elseif ($scenario === 'auth_independent') {
            check(array_column($attempts,'status') === [401,200] && $reply === 'independent connection ok',
                'A rejected key skips its own models and leaves the next connection eligible');
        } else {
            check(array_column($attempts,'status') === [503,503,503,401,403,403]
                && array_column($attempts,'cycle') === array_fill(0,6,1),
                'A later 401 prevents an earlier model on that connection from re-entering cycle two');
            check(str_contains($reply,'Google connection permissions') && !str_contains($reply,'API Key'),
                '403 guides the owner to connection permissions');
        }
    }
    echo "PASS credential rejection skips the connection within both cycles and identifies the right field\n";

    // Structured Google RetryInfo can exceed a Retry-After header. The client
    // must wait the full minimum and retain only bounded quota metadata.
    foreach(['body_retry','daily_quota'] as $scenario){
        AgentTraceService::begin(bin2hex(random_bytes(8)),null,AiClient::agentLimits());
        $client=new AiClient();
        $cfg=[['provider'=>'google','endpoint'=>'http://'.$address.'/'.$scenario,'apiKey'=>'fixture','model'=>SecretService::PRIMARY_MODEL,'models'=>[SecretService::PRIMARY_MODEL],'configured'=>true]];
        $routeAll=new ReflectionMethod(AiClient::class,'route');
        $start=hrtime(true);
        $reply=$routeAll->invoke($client,'Fixture',64,0.0,null,null,0,$cfg,true);
        $attempts=$client->transferTimings();
        check(($attempts[0]['retry_after_ms']??null)===1250,'Body RetryInfo is not truncated to smaller header');
        check(($attempts[0]['quota_kind']??null)===($scenario==='daily_quota'?'daily':'minute'),'Safe quota classification retained');
        check(!str_contains(json_encode($attempts),'private-fixture-project'),'Quota subject is never persisted');
        if($scenario==='body_retry'){
            check($reply==='connection ok'&&array_column($attempts,'status')===[429,200],'Temporary quota recovers after the indicated delay');
            check((hrtime(true)-$start)/1000000>=1250,'Measured wait honors structured provider minimum');
            check(($attempts[1]['usage']['total_tokens']??null)===5&&($attempts[1]['finish_reason']??null)==='STOP','Response usage and finish reason retained');
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
