<?php
declare(strict_types=1);

// Use the disposable CI database exclusively. Each worker gets its own connection.
$remoteWorker = ($argv[4] ?? '') === 'remote';
foreach ([
    'BAPX_MYSQL_HOST' => '127.0.0.1',
    'BAPX_MYSQL_PORT' => $remoteWorker ? '1' : '33068',
    'BAPX_REMOTE_DB_URL' => 'http://127.0.0.1:18753/remotedb',
    'BAPX_MYSQL_DB' => 'sps_order_test',
    'BAPX_MYSQL_USER' => 'sps_test',
    'BAPX_MYSQL_PASS' => 'sps_ci_fixture',
    'BAPX_TEST_MODE' => '0',
] as $name => $value) {
    putenv("$name=$value");
    $_SERVER[$name] = $value;
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\DatabaseService;

$store = new DatabaseService(!$remoteWorker);
if (($argv[1] ?? '') === 'worker') {
    echo "ready\n";
    fflush(STDOUT);
    if ($argv[3] === 'recover') {
        $result = $store->resolveOrderTaxReview($argv[2], 'Tamil Nadu', ['gst_state' => 'Tamil Nadu']);
    } else {
        $result = $store->saveVerifiedOrder([
        'id' => $argv[2],
        'payment_id' => $argv[3],
        'status' => 'on_hold',
        ], true);
    }
    echo json_encode($result, JSON_THROW_ON_ERROR), "\n";
    exit;
}

$pdo = $store->connection();
$pdo->exec('CREATE TABLE orders (
    id VARCHAR(64) PRIMARY KEY, _data JSON NOT NULL, _owner VARCHAR(255),
    _status VARCHAR(64), _created_at VARCHAR(40), _updated_at VARCHAR(40)
)');

$race = static function (string $firstPayment, string $secondPayment, string $prefix, string $mode = 'direct', ?string $recoveryId = null) use ($pdo): array {
    if ((int)$pdo->query("SELECT GET_LOCK(CONCAT('sps:orders:', SHA1(DATABASE())), 0)")->fetchColumn() !== 1) {
        throw new RuntimeException('Could not acquire fixture barrier.');
    }
    $workers = [];
    try {
        foreach ([$firstPayment, $secondPayment] as $index => $payment) {
            $id = $payment === 'recover' ? $recoveryId : $prefix . $index;
            $process = proc_open([PHP_BINARY, __FILE__, 'worker', $id, $payment, $mode], [
                0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
            ], $pipes);
            if (!is_resource($process)) throw new RuntimeException('Could not launch worker.');
            fclose($pipes[0]);
            $workers[] = [$process, $pipes];
        }
        foreach ($workers as [, $pipes]) {
            if (trim((string)fgets($pipes[1])) !== 'ready') throw new RuntimeException('Worker did not start.');
        }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK(CONCAT('sps:orders:', SHA1(DATABASE())))");
    }
    $results = [];
    foreach ($workers as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) throw new RuntimeException($error);
        $results[] = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    }
    return $results;
};

[$first, $second] = $race('pay_same', 'pay_same', 'same');
if ($first['record']['id'] !== $second['record']['id'] || $first['created'] === $second['created'] || count($store->read('orders')) !== 1) {
    throw new RuntimeException('Concurrent callbacks for one payment must return one order with one creator.');
}

[$first, $second] = $race('pay_first', 'pay_second', 'different');
$sequences = array_column($store->read('orders'), 'invoice_sequence');
sort($sequences);
if (!$first['created'] || !$second['created'] || $sequences !== [1, 2, 3]) {
    throw new RuntimeException('Distinct concurrent payments must receive unique, sequential invoices.');
}
echo "Concurrent callback persistence and invoice allocation passed.\n";

// Multiple HTTP workers allow requests to overlap on the actual bridge endpoint.
putenv('PHP_CLI_SERVER_WORKERS=3');
$bridgeLog = tempnam(sys_get_temp_dir(), 'sps-bridge-');
$bridge = proc_open([PHP_BINARY, '-S', '127.0.0.1:18753', 'index.php'], [
    0 => ['pipe', 'r'], 1 => ['file', $bridgeLog, 'a'], 2 => ['file', $bridgeLog, 'a'],
], $pipes, dirname(__DIR__));
if (!is_resource($bridge)) throw new RuntimeException('Could not start test bridge.');
fclose($pipes[0]);
try {
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $socket = @fsockopen('127.0.0.1', 18753);
        if ($socket) break;
        usleep(100000);
    }
    if (!$socket) throw new RuntimeException('Test bridge did not start.');
    fclose($socket);

    [$first, $second] = $race('pay_remote', 'pay_remote', 'remote', 'remote');
    if ($first['record']['id'] !== $second['record']['id'] || $first['created'] === $second['created'] || count($store->read('orders')) !== 4) {
        throw new RuntimeException('Duplicate callbacks through the bridge must return one order.');
    }

    $store->upsert('orders', [
        'id' => 'tax-review',
        'payment_id' => 'pay_review',
        'total' => 105,
        'tax_review_required' => true,
        'items' => [['name' => 'Test pendant', 'qty' => 1, 'line_total' => 105, 'gst_rate' => 5]],
    ]);
    [$paid, $recovered] = $race('pay_overlap', 'recover', 'overlap', 'remote', 'tax-review');
    $sequences = array_column($store->read('orders'), 'invoice_sequence');
    sort($sequences);
    if (!$paid['created'] || $recovered['tax_review_required'] || $recovered['id'] !== 'tax-review' || $sequences !== [1, 2, 3, 4, 5, 6]) {
        throw new RuntimeException('Callback and tax recovery through the bridge must issue distinct invoices.');
    }
    echo "Concurrent remote bridge payment and tax recovery passed.\n";
} finally {
    proc_terminate($bridge);
    proc_close($bridge);
    unlink($bridgeLog);
}
