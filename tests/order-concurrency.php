<?php
declare(strict_types=1);

// Use the disposable CI database exclusively. Each worker gets its own connection.
foreach ([
    'BAPX_MYSQL_HOST' => '127.0.0.1',
    'BAPX_MYSQL_PORT' => '33068',
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

$store = new DatabaseService(true);
if (($argv[1] ?? '') === 'worker') {
    echo "ready\n";
    fflush(STDOUT);
    $result = $store->saveVerifiedOrder([
        'id' => $argv[2],
        'payment_id' => $argv[3],
        'status' => 'on_hold',
    ], true);
    echo json_encode($result, JSON_THROW_ON_ERROR), "\n";
    exit;
}

$pdo = $store->connection();
$pdo->exec('CREATE TABLE orders (
    id VARCHAR(64) PRIMARY KEY, _data JSON NOT NULL, _owner VARCHAR(255),
    _status VARCHAR(64), _created_at VARCHAR(40), _updated_at VARCHAR(40)
)');

$race = static function (string $firstPayment, string $secondPayment, string $prefix) use ($pdo): array {
    if ((int)$pdo->query("SELECT GET_LOCK(CONCAT('sps:orders:', SHA1(DATABASE())), 0)")->fetchColumn() !== 1) {
        throw new RuntimeException('Could not acquire fixture barrier.');
    }
    $workers = [];
    try {
        foreach ([$firstPayment, $secondPayment] as $index => $payment) {
            $process = proc_open([PHP_BINARY, __FILE__, 'worker', $prefix . $index, $payment], [
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
