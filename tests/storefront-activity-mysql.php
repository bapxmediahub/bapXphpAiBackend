<?php
declare(strict_types=1);

use App\Controllers\RemoteDbController;
use App\Services\{DatabaseService, SiteTelemetryService};

if (PHP_SAPI !== 'cli' && (PHP_SAPI !== 'cli-server' || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1')) {
    http_response_code(404);
    exit;
}
require dirname(__DIR__).'/app/bootstrap.php';

$config = require app_path('config/database.php');
if (!in_array($config['host'], ['127.0.0.1', 'localhost', '::1'], true) || getenv('BAPX_TEST_MODE') === '1') {
    throw new RuntimeException('Use a disposable loopback MySQL or MariaDB fixture database.');
}

function directSamples(): array {
    return [
        ['cart_add', '2026-11-01T00:00:00+05:30'],
        ['cart_add', '2026-11-01T00:00:00.999+0530'],
        ['cart_add', '2026-10-31T18:29:59Z'],
        ['cart_add', '2026-11-01T00:29:59+06:00'],
        ['checkout_initiated', '2026-10-31T18:30:00Z'],
        ['checkout_initiated', '2026-10-31T15:00:00-04:00'],
        ['checkout_initiated', '2026-11-01 00:00:00'],
        ['checkout_initiated', '2026-11-01T00:00'],
        ['checkout_initiated', '2026-11-01'],
        ['checkout_initiated', '2026-11-01 00:00:00 Asia/Kolkata'],
        ['checkout_initiated', '20261101'],
        ['checkout_initiated', '2026-11-01T24:00:00+05:30'],
        ['checkout_initiated', '2026-02-31T23:59:59-23:59'],
        ['checkout_initiated', '2026-10-31 23:59:59'],
        ['checkout_initiated', 'not a timestamp'],
        ['checkout_initiated', '2026-13-01T00:00:00Z'],
        ['checkout_initiated', null],
        ['Cart_Add', '2026-11-02T00:00:00+05:30'],
        ['cart_add ', '2026-11-02T00:00:00+05:30'],
        ['unknown', '2026-11-02T00:00:00+05:30'],
        ['cart_add', '2026-11-02T00:00:00+05:30', 'Commerce.Event'],
        ['cart_add', '2026-11-02T00:00:00+05:30', 'support.event'],
    ];
}

function bridgeSamples(int $cutoff): array {
    return [
        ['cart_add', date('c', $cutoff)],
        ['cart_add', gmdate('Y-m-d\TH:i:s\Z', $cutoff - 1)],
        ['checkout_initiated', date('Y-m-d H:i:s e', $cutoff)],
        ['checkout_initiated', date('Ymd', $cutoff + 86400)],
        ['checkout_initiated', 'not a timestamp'],
    ];
}

function seedAudit(PDO $pdo, array $samples, int $cutoff, int $recentRows = 510): array {
    $pdo->exec('CREATE TEMPORARY TABLE audit_events (id VARCHAR(36) PRIMARY KEY, _data JSON NOT NULL, _created_at DATETIME)');
    $insert = $pdo->prepare('INSERT INTO audit_events VALUES (?, ?, ?)');
    $pdo->beginTransaction();
    $rows = [];
    foreach ($samples as $index => [$event, $date]) {
        $row = ['event' => $samples[$index][2] ?? 'commerce.event', 'meta' => ['event' => $event],
            'actor_email' => 'private-fixture@example.test'];
        if ($date !== null) $row['created_at'] = $date;
        $rows[] = $row;
        $insert->execute(['audit-'.$index, json_encode($row, JSON_THROW_ON_ERROR), $index % 2 ? '2000-01-01 00:00:00' : null]);
    }
    $pagedEvent = ['event' => 'commerce.event', 'meta' => ['event' => 'cart_add'],
        'created_at' => date('c', $cutoff), 'actor_email' => 'private-fixture@example.test'];
    for ($index = 0; $index < $recentRows; $index++) {
        $rows[] = $pagedEvent;
        $insert->execute(['paged-'.$index, json_encode($pagedEvent, JSON_THROW_ON_ERROR), null]);
    }
    $oldEvent = array_replace($pagedEvent, ['created_at' => '2024-05-01T00:00:00+05:30']);
    $unrelatedEvent = array_replace($pagedEvent, ['event' => 'agent.run',
        'meta' => ['event' => 'cart_add', 'trace_fixture' => str_repeat('x', 5000)]]);
    for ($index = 0; $index < 1000; $index++) {
        $insert->execute(['old-'.$index, json_encode($oldEvent, JSON_THROW_ON_ERROR), null]);
        $insert->execute(['unrelated-'.$index, json_encode($unrelatedEvent, JSON_THROW_ON_ERROR), null]);
    }
    $pdo->commit();
    return $rows;
}

if (PHP_SAPI === 'cli-server') {
    if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) !== '/remotedb') {
        http_response_code(404);
        exit;
    }
    header('Content-Type: application/json');
    if (isset($_GET['malformed'])) {
        echo '{"success":true,"result":{"cart_add":"0","checkout_initiated":0}}';
        exit;
    }
    $cutoff = (int)getenv('BAPX_STOREFRONT_FIXTURE_CUTOFF');
    $empty = isset($_GET['empty']);
    $pdo = (new DatabaseService(true))->connection();
    seedAudit($pdo, $empty ? [] : bridgeSamples($cutoff), $cutoff, $empty ? 0 : 510);
    if (isset($_GET['query_failed'])) $pdo->exec('ALTER TABLE audit_events DROP COLUMN _data');
    $request = json_decode(file_get_contents('php://input'), true, flags: JSON_THROW_ON_ERROR);
    ob_start();
    (new RemoteDbController())();
    $response = ob_get_clean();
    $receipt = [
        'keys' => array_keys($request),
        'action' => $request['action'] ?? null,
        'collection' => $request['collection'] ?? null,
        'since' => $request['since'] ?? null,
        'response' => json_decode($response, true, flags: JSON_THROW_ON_ERROR),
    ];
    file_put_contents((string)getenv('BAPX_STOREFRONT_FIXTURE_RECEIPTS'),
        json_encode($receipt, JSON_THROW_ON_ERROR)."\n", FILE_APPEND);
    echo $response;
    exit;
}

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$store = new DatabaseService(true);
$pdo = $store->connection();
$cutoff = strtotime('2026-11-01T00:00:00+05:30');
$rows = seedAudit($pdo, directSamples(), $cutoff);
$emptyCutoff = strtotime('2026-12-01T00:00:00Z');
$empty = ['cart_add' => 0, 'checkout_initiated' => 0];
$expected = SiteTelemetryService::counts($rows, $cutoff);
check($expected === ['cart_add' => 512, 'checkout_initiated' => 8], 'The PHP oracle fixture has the intended matches');
$edgeCutoff = strtotime('2026-03-04T00:00:00Z');

$bufferedAttribute = defined('Pdo\Mysql::ATTR_USE_BUFFERED_QUERY')
    ? \Pdo\Mysql::ATTR_USE_BUFFERED_QUERY
    : \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY;
foreach (['+00:00', '-07:00'] as $sqlTimeZone) {
    $pdo->exec('SET SESSION time_zone = '.$pdo->quote($sqlTimeZone));
    $before = (int)$pdo->query("SHOW SESSION STATUS LIKE 'Com_select'")->fetchColumn(1);
    check($store->storefrontActivitySince($cutoff) === $expected, 'Direct counts match the PHP oracle in both SQL session timezones');
    $after = (int)$pdo->query("SHOW SESSION STATUS LIKE 'Com_select'")->fetchColumn(1);
    check($after - $before === 1, 'The direct database counts over 500 qualifying rows using one SELECT');
    check($pdo->getAttribute($bufferedAttribute) === true, 'The shared PDO connection restores its buffered mode');
}
check($store->storefrontActivitySince($emptyCutoff) === $empty, 'An empty period returns both zero counts');
check($store->storefrontActivitySince($edgeCutoff) === SiteTelemetryService::counts($rows, $edgeCutoff),
    'The conservative cutoff preserves PHP date normalization');
$pdo->setAttribute($bufferedAttribute, false);
check($store->storefrontActivitySince($cutoff) === $expected, 'Pre-existing unbuffered mode preserves counts');
check($pdo->getAttribute($bufferedAttribute) === false, 'The caller’s unbuffered mode is restored');
$pdo->setAttribute($bufferedAttribute, true);
$pdo->exec('ALTER TABLE audit_events DROP COLUMN _data');
try {
    $store->storefrontActivitySince($cutoff);
    throw new RuntimeException('A failed SELECT must propagate');
} catch (PDOException) {
    check($pdo->getAttribute($bufferedAttribute) === true, 'The original buffering mode is restored on query failure');
}

$remoteCutoff = SiteTelemetryService::recentCutoff();
$receiptFile = tempnam(sys_get_temp_dir(), 'storefront-test-receipts-');
if ($receiptFile === false) throw new RuntimeException('Could not allocate loopback receipts.');
putenv('BAPX_STOREFRONT_FIXTURE_CUTOFF='.$remoteCutoff);
putenv('BAPX_STOREFRONT_FIXTURE_RECEIPTS='.$receiptFile);

$listener = stream_socket_server('tcp://127.0.0.1:0');
if ($listener === false) throw new RuntimeException('Could not reserve the loopback fixture address.');
$address = stream_socket_get_name($listener, false);
fclose($listener);
$log = tmpfile();
$process = proc_open([PHP_BINARY, '-S', $address, __FILE__], [0 => ['pipe', 'r'], 1 => $log, 2 => $log], $pipes);
if (!is_resource($process)) throw new RuntimeException('Could not start the loopback database bridge.');
fclose($pipes[0]);
try {
    $port = (int)substr(strrchr($address, ':'), 1);
    for ($attempt = 0; $attempt < 50; $attempt++) {
        if ($probe = @fsockopen('127.0.0.1', $port)) { fclose($probe); break; }
        usleep(100000);
    }
    DatabaseService::releaseIdleConnection();
    $_SERVER['BAPX_MYSQL_PORT'] = '1';
    $bridge = 'http://'.$address.'/remotedb';
    $_SERVER['BAPX_REMOTE_DB_URL'] = $bridge;
    $remote = new DatabaseService();
    $pdo->exec('DROP TEMPORARY TABLE audit_events');
    $remoteRows = seedAudit($pdo, bridgeSamples($remoteCutoff), $remoteCutoff);
    $remoteExpected = SiteTelemetryService::counts($remoteRows, $remoteCutoff);
    check($remoteExpected === ['cart_add' => 511, 'checkout_initiated' => 2], 'Bridge fixture covers the exact second and legacy forms');
    check($remote->storefrontActivitySince($remoteCutoff) === $remoteExpected, 'The authenticated bridge preserves the exact counts');
    $calls = file($receiptFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    check(count($calls) === 1, 'One HTTP bridge request counts over 500 qualifying rows');
    $receipt = json_decode($calls[0], true, flags: JSON_THROW_ON_ERROR);
    check($receipt === [
        'keys' => ['action', 'collection', 'since', 'password'],
        'action' => 'count_storefront_activity',
        'collection' => 'audit_events',
        'since' => $remoteCutoff,
        'response' => ['success' => true, 'result' => $remoteExpected],
    ], 'The bridge receives only the fixed cutoff and returns only two integer counts');

    $_SERVER['BAPX_REMOTE_DB_URL'] = $bridge.'?empty';
    check((new DatabaseService())->storefrontActivitySince($remoteCutoff) === $empty, 'The bridge preserves a valid empty period');
    $failures = [
        [$bridge, $remoteCutoff - 120],
        [$bridge.'?malformed', $remoteCutoff],
        [$bridge.'?query_failed', $remoteCutoff],
        ['http://'.$address.'/missing', $remoteCutoff],
        ['http://127.0.0.1:1/remotedb', $remoteCutoff],
    ];
    foreach ($failures as [$invalidBridge, $invalidCutoff]) {
        $_SERVER['BAPX_REMOTE_DB_URL'] = $invalidBridge;
        try {
            (new DatabaseService())->storefrontActivitySince($invalidCutoff);
        } catch (RuntimeException) {
            continue;
        }
        throw new RuntimeException('A bad cutoff, invalid response or unreachable bridge must surface a failure.');
    }

    $probe = curl_init($bridge);
    curl_setopt_array($probe, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    $payload = ['action' => 'count_storefront_activity', 'collection' => 'audit_events', 'since' => (string)$remoteCutoff];
    foreach ([$config['pass'] => 422, 'wrong-fixture-password' => 401] as $password => $status) {
        curl_setopt($probe, CURLOPT_POSTFIELDS, json_encode($payload + ['password' => $password], JSON_THROW_ON_ERROR));
        check(curl_exec($probe) !== false && curl_getinfo($probe, CURLINFO_HTTP_CODE) === $status,
            'This action rejects noninteger cutoffs and unauthenticated reads');
    }
} finally {
    proc_terminate($process);
    proc_close($process);
    fclose($log);
    unlink($receiptFile);
}

echo "PASS storefront audit counts through direct SQL and the authenticated loopback bridge.\n";
