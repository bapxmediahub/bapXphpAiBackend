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

function seedAudit(PDO $pdo): array {
    $pdo->exec('CREATE TEMPORARY TABLE audit_events (id VARCHAR(36) PRIMARY KEY, _data JSON NOT NULL, _created_at DATETIME)');
    $samples = [
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
        'created_at' => '2026-11-01T00:00:00+05:30', 'actor_email' => 'private-fixture@example.test'];
    for ($index = 0; $index < 510; $index++) {
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
        echo '{"success":true,"data":[{"id":"invalid","event_name":"cart_add"}]}';
        exit;
    }
    seedAudit((new DatabaseService(true))->connection());
    (new RemoteDbController())();
    exit;
}

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$store = new DatabaseService(true);
$pdo = $store->connection();
$rows = seedAudit($pdo);
$cutoff = strtotime('2026-11-01T00:00:00+05:30');
$emptyCutoff = strtotime('2026-12-01T00:00:00Z');
$empty = ['cart_add' => 0, 'checkout_initiated' => 0];
$expected = SiteTelemetryService::counts($rows, $cutoff);
check($expected === ['cart_add' => 512, 'checkout_initiated' => 8], 'The PHP oracle fixture has the intended matches');
$edgeCutoff = strtotime('2026-03-04T00:00:00Z');

function activity(DatabaseService $store, int $cutoff): array {
    return SiteTelemetryService::counts($store->storefrontActivityCandidates($cutoff), $cutoff);
}

foreach (['+00:00', '-07:00'] as $sqlTimeZone) {
    $pdo->exec('SET SESSION time_zone = '.$pdo->quote($sqlTimeZone));
    check(activity($store, $cutoff) === $expected, 'Direct SQL counts match the PHP oracle in both SQL session timezones');
}
check(activity($store, $emptyCutoff) === $empty, 'An empty period returns both zero counts');
check(activity($store, $edgeCutoff) === SiteTelemetryService::counts($rows, $edgeCutoff),
    'The conservative cutoff preserves PHP date normalization');
$candidates = iterator_to_array($store->storefrontActivityCandidates($cutoff), false);
check(count($candidates) > 500 && count($candidates) < count($rows), 'The bounded read crosses a page and the date filter removes old records');
foreach ($candidates as $row) {
    check(array_keys($row) === ['event', 'meta', 'created_at'] && array_keys($row['meta']) === ['event'],
        'The projection redacts unrelated audit metadata');
}

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
    check(activity($remote, $cutoff) === $expected, 'Authenticated bridge preserves the exact counts across pages');
    check(activity($remote, $emptyCutoff) === $empty, 'The bridge preserves an empty period as valid zeroes');

    foreach ([$bridge.'?malformed', 'http://'.$address.'/missing', 'http://127.0.0.1:1/remotedb'] as $invalidBridge) {
        $_SERVER['BAPX_REMOTE_DB_URL'] = $invalidBridge;
        try {
            activity(new DatabaseService(), $cutoff);
        } catch (RuntimeException) {
            continue;
        }
        throw new RuntimeException('An invalid, non-200 or unreachable bridge must surface a failure.');
    }
} finally {
    proc_terminate($process);
    proc_close($process);
    fclose($log);
}

echo "PASS storefront audit counts through direct SQL and the authenticated loopback bridge.\n";
