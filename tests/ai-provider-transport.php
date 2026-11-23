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
$fixture = proc_open([PHP_BINARY, '-S', $address, __FILE__], [
    0 => ['file','/dev/null','r'], 1 => ['file','/dev/null','w'], 2 => ['file','/dev/null','w'],
], $pipes, null, ['BAPX_PROVIDER_TRANSPORT_FIXTURE'=>'1'] + getenv());
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
}
