<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$mysql = in_array($argv[1] ?? '', ['--mysql', '--begin-worker'], true);
$environment = ['BAPX_TEST_MODE'=>$mysql ? '0' : '1'];
if ($mysql) {
    $environment += ['BAPX_MYSQL_HOST'=>'127.0.0.1', 'BAPX_MYSQL_PORT'=>'33068',
        'BAPX_MYSQL_DB'=>'sps_order_test', 'BAPX_MYSQL_USER'=>'sps_test',
        'BAPX_MYSQL_PASS'=>'sps_ci_fixture', 'BAPX_REMOTE_DB_URL'=>'http://127.0.0.1:1/remotedb',
        'APP_URL'=>'https://booking-fixture.example.test'];
}
foreach ($environment as $key=>$value) {
    putenv($key.'='.$value);
    $_SERVER[$key] = $value;
}
require dirname(__DIR__).'/app/bootstrap.php';

use App\Services\{AgentToolRegistry, AuthoredPageService, CampaignDownloadService, CampaignPaymentService,
    DatabaseService, RemoteCartException, RemoteCheckoutService};

$store = new DatabaseService(true);
if (($argv[1] ?? '') === '--begin-worker') {
    $args = json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR);
    printf("ready\n");
    fflush(STDOUT);
    if (trim((string)fgets(STDIN)) !== 'go') throw new RuntimeException('Missing booking worker barrier.');
    echo json_encode($store->beginCampaignBooking(...$args), JSON_THROW_ON_ERROR);
    exit;
}
require __DIR__.'/fixtures/remote-checkout.php';

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($message);
};
$assert(CampaignPaymentService::fromEditor(null, '100') === null, 'Unchecked payment retained an amount.');
$assert(CampaignPaymentService::fromEditor('1', '105.09') === ['amount_paise'=>10509], 'INR decimal was not exact.');
$assert(CampaignPaymentService::fromEditor('1', '105.9') === ['amount_paise'=>10590], 'One decimal did not become exact paise.');
foreach (['0', '0.99', '-20', '50.125', '1e3', '1,000.00', ['99'], '9999999999999'] as $amount) {
    try {
        CampaignPaymentService::fromEditor('1', $amount);
        $assert(false, 'Invalid amount accepted.');
    } catch (InvalidArgumentException) { $checks++; }
}

$slug = 'book-qa-'.bin2hex(random_bytes(6));
$revision = bin2hex(random_bytes(16));
$nextRevision = bin2hex(random_bytes(16));
$old = ['id'=>bin2hex(random_bytes(16)), 'name'=>'Version one.txt', 'mime'=>'text/plain', 'bytes'=>24];
$new = ['id'=>bin2hex(random_bytes(16)), 'name'=>'Version two.txt', 'mime'=>'text/plain', 'bytes'=>24];
$page = AuthoredPageService::record(['slug'=>$slug, 'status'=>'published', 'title'=>'Booking fixture',
    'content'=>'Published fixture', 'campaign_payment'=>['amount_paise'=>12550],
    'campaign_downloads'=>['enabled'=>true, 'files'=>[$old]]]);
$page['revision'] = $revision;
$owner = CampaignPaymentService::browserOwner(['campaign_payment_key'=>str_repeat('a', 64)]);
$other = CampaignPaymentService::browserOwner(['campaign_payment_key'=>str_repeat('b', 64)]);
$switched = CampaignPaymentService::browserOwner(['campaign_payment_key'=>str_repeat('a', 64), 'user'=>['sub'=>'fixture-other']]);
$now = time();
$records = [];
$ids = [];
$fixture = new RemoteCheckoutFixture();
$pdo = null;

$writePage = static function () use ($mysql, $store, &$page): void {
    if ($mysql) $store->upsert('settings', $page);
};
$operate = static function (string $op, array $args) use (&$records, &$page, &$now): ?array {
    $id = $args['id'];
    $booking = $records[$id] ?? null;
    if ($op === 'read') return $booking;
    if ($op === 'begin') {
        if ($booking !== null) return ['outcome'=>'existing', 'booking'=>$booking];
        $amount = CampaignPaymentService::pageAmount($page);
        if (($page['status'] ?? null) !== 'published' || $page['slug'] !== $args['slug']
            || $page['revision'] !== $args['revision'] || $amount === null) return ['outcome'=>'stale'];
        if ($args['mode'] === null) return ['outcome'=>'unavailable'];
        $records[$id] = CampaignPaymentService::snapshot($page, $id, $args['owner'], $args['mode'], $amount, $now);
        return ['outcome'=>'created', 'booking'=>$records[$id]];
    }
    if ($op === 'claim') {
        $claimed = $booking['status'] !== 'captured' && $booking['reconcile_after'] <= $now;
        if ($claimed) {
            $booking['reconcile_after'] = $now+30;
            $records[$id] = $booking;
        }
        return compact('booking','claimed');
    }
    return $records[$id] = CampaignPaymentService::applyChange($booking, $args['change'], $now);
};
$lookup = static function (string $id) use ($mysql, $store, &$records): array {
    return $mysql ? $store->find('campaign_bookings', $id) : $records[$id];
};
$advance = static function (string $id) use ($mysql, &$pdo, $lookup, &$now): void {
    $now += 31;
    if (!$mysql) return;
    $fresh = $lookup($id);
    $fresh['reconcile_after'] = time()-1;
    $update = $pdo->prepare('UPDATE campaign_bookings SET _data=? WHERE id=?');
    $update->execute([json_encode($fresh, JSON_THROW_ON_ERROR), $id]);
};

try {
    if ($mysql) {
        $pdo = $store->connection();
        if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'sps_order_test') {
            $pdo = null;
            throw new RuntimeException('Refuse a nonfixture database.');
        }
        foreach (['settings','campaign_bookings','orders'] as $table) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS $table (id VARCHAR(64) PRIMARY KEY,_data JSON NOT NULL,
                _owner VARCHAR(255),_status VARCHAR(64),_created_at VARCHAR(40),_updated_at VARCHAR(40)) ENGINE=InnoDB");
        }
    }
    $writePage();
    $payments = new CampaignPaymentService(store:$store, gateway:$fixture->gatewayPort(),
        secrets:fn(): array => $fixture->secrets, operation:$mysql ? null : $operate);
    $notify = new RemoteCheckoutService(store:$store, secrets:fn(): array => $fixture->secrets);
    $send = static function (string $id, string $event, string $secret, ?int $error = null) use ($notify,$payments,$assert): void {
        $body = json_encode(['event'=>$event, 'payload'=>['payment_link'=>['entity'=>['reference_id'=>'book_'.$id,
            'status'=>'paid', 'amount_paid'=>1]]]], JSON_THROW_ON_ERROR);
        try {
            $notify->receiveWebhook($body, hash_hmac('sha256', $body, $secret), $payments);
            $assert($error === null, 'Invalid or unverified notification was acknowledged.');
        } catch (RemoteCartException $failure) {
            $assert($failure->httpStatus === $error, 'Wrong signed notification status.');
        }
    };
    $secret = $fixture->secrets['razorpay_test_webhook_secret'];
    $fixture->secrets['razorpay_live_webhook_secret'] = 'different-synthetic-mode';
    $ordersBefore = $mysql ? (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn() : 0;

    $assert($payments->start($slug, $nextRevision, $owner) === ['outcome'=>'stale'], 'A stale revision could reserve a new payment.');
    $unavailable = new CampaignPaymentService(store:$store, gateway:$fixture->gatewayPort(), secrets:fn()=>[],
        operation:$mysql ? null : $operate);
    $assert($unavailable->start($slug, $revision, $other) === ['outcome'=>'unavailable'], 'Missing webhook credentials started payment.');
    $wrongMode = new CampaignPaymentService(store:$store, gateway:$fixture->gatewayPort(),
        secrets:fn()=>array_replace($fixture->secrets, ['razorpay_test_key_id'=>'rzp_live_wrong_slot']),
        operation:$mysql ? null : $operate);
    $assert($wrongMode->start($slug, $revision, $other) === ['outcome'=>'unavailable'],
        'A live key in the test credential slot started a payment.');
    $assert(!$fixture->gatewayCalls, 'Early rejection reached a provider operation.');

    $fixture->timeoutAfterCreate = true;
    $pending = $payments->start($slug, $revision, $owner);
    $id = $pending['id'];
    $ids[] = $id;
    $assert($pending['status'] === 'uncertain' && $pending['amount_paise'] === 12550
        && $pending['currency'] === 'INR' && $pending['mode'] === 'test', 'An uncertain create lost the frozen approved amount.');
    $assert($pending['fulfillment'] == [$old], 'A paid booking lost the published download version.');
    $assert($payments->start($slug, $revision, $owner)['id'] === $id && count($fixture->gatewayCalls) === 1,
        'Repeated booking created a second provider link.');
    $assert($payments->owned($id, $other) === null && $payments->owned($id, $switched) === null,
        'A different browser or actor could read a private booking.');
    $assert($payments->paidFile($pending, $old['id']) === null, 'Unverified booking granted bytes.');
    $assert(!CampaignDownloadService::canAccess($page, ['campaign_download_grants'=>[$slug=>['revision'=>$revision,'expires'=>time()+60]]]),
        'A paid published revision exposed a free download.');

    $send($id, 'payment_link.paid', 'forged', 401);
    $send($id, 'payment_link.paid', 'different-synthetic-mode', 401);
    $send($id, 'payment_link.paid', $secret, 503);
    $fixture->timeoutAfterCreate = false;
    $advance($id);
    $pending = $payments->reconcile($lookup($id));
    $assert($pending['status'] === 'pending'
        && array_column($fixture->gatewayCalls, 'operation') === ['create_link','find_link'], 'Uncertain POST was not recovered by its reference.');

    $page['revision'] = $nextRevision;
    $page['campaign_payment']['amount_paise'] = 75000;
    $page['campaign_downloads']['files'] = [$new];
    $writePage();
    $assert($payments->start($slug, $revision, $owner)['id'] === $id, 'Existing booking was lost after republishing.');
    $assert($payments->start($slug, $revision, $other) === ['outcome'=>'stale'], 'Another browser could purchase stale content.');

    $linkId = $pending['provider_link_id'];
    $fixture->links[$linkId]['status'] = 'expired';
    $advance($id);
    $send($id, 'payment_link.expired', $secret);
    $expired = $lookup($id);
    $assert($expired['status'] === 'expired' && $payments->paidFile($expired, $old['id']) === null,
        'Expiry incorrectly granted files.');
    $fixture->attempts[$id] = $pending;
    foreach ([['amount'=>12549], ['currency'=>'USD'], ['status'=>'authorized']] as $badPayment) {
        $fixture->markPaid($id, $badPayment);
        $advance($id);
        $send($id, 'payment_link.paid', $secret, 503);
        $assert(!isset($lookup($id)['receipt']), 'Amount, currency or capture mismatch yielded a receipt.');
    }

    $fixture->markPaid($id);
    $advance($id);
    $send($id, 'payment_link.paid', $secret);
    $confirmed = $lookup($id);
    $assert($confirmed['status'] === 'captured' && $confirmed['receipt']['id'] === 'book_'.$id
        && $confirmed['revision'] === $revision && $confirmed['amount_paise'] === 12550,
        'Late verified capture did not retain the original booking.');
    $assert($payments->paidFile($confirmed, $old['id']) == $old && $payments->paidFile($confirmed, $new['id']) === null,
        'A verified receipt did not authorize exactly the frozen file descriptor.');
    $calls = count($fixture->gatewayCalls);
    $send($id, 'payment_link.expired', $secret);
    $send($id, 'payment_link.paid', $secret);
    $assert(count($fixture->gatewayCalls) === $calls && $lookup($id)['receipt'] === $confirmed['receipt'],
        'Repeated and out-of-order notifications changed the receipt.');
    $assert(!str_contains(json_encode($confirmed, JSON_THROW_ON_ERROR), '@'), 'Provider contact data entered the durable receipt.');

    $page['campaign_downloads']['enabled'] = false;
    $writePage();
    $bookingOnly = $payments->start($slug, $nextRevision, $owner);
    $ids[] = $bookingOnly['id'];
    $assert($bookingOnly['fulfillment'] === [] && $bookingOnly['amount_paise'] === 75000,
        'Booking-only payment snapshotted a disabled download or stale amount.');

    if ($mysql) {
        // Two workers open the same published page simultaneously. One may create a link.
        $racingId = bin2hex(random_bytes(16));
        $ids[] = $racingId;
        $job = json_encode([$racingId, $slug, $nextRevision, $other, 'test'], JSON_THROW_ON_ERROR);
        $workers = [];
        for ($i = 0; $i < 2; $i++) {
            $worker = proc_open([PHP_BINARY, __FILE__, '--begin-worker', $job],
                [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes);
            if (!is_resource($worker)) throw new RuntimeException('Unable to start booking concurrency worker.');
            $workers[] = [$worker, $pipes];
        }
        foreach ($workers as [, $pipes]) $assert(trim((string)fgets($pipes[1])) === 'ready', 'Worker did not reach the start barrier.');
        foreach ($workers as [, $pipes]) { fwrite($pipes[0], "go\n"); fclose($pipes[0]); }
        $outcomes = [];
        foreach ($workers as [$worker, $pipes]) {
            $result = json_decode(stream_get_contents($pipes[1]), true, flags: JSON_THROW_ON_ERROR);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $assert(proc_close($worker) === 0 && $result['booking']['id'] === $racingId, 'Worker did not return the frozen booking.');
            $outcomes[] = $result['outcome'];
        }
        sort($outcomes);
        $assert($outcomes === ['created','existing'], 'Concurrent starts reserved two provider creations.');
        $assert((int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn() === $ordersBefore, 'Campaign payment created a shop order.');
    }
    $empty = AgentToolRegistry::commerceReportFromRecords([], [], ['report'=>'products','from'=>'2026-11-01','to'=>'2026-11-15']);
    $assert($empty['total_rows'] === 0 && $empty['rows'] === [], 'A valid empty commerce report became an error.');
    echo 'Campaign booking '.($mysql ? 'MySQL transactions and' : 'state and').' payment fixtures: '.$checks." checks passed.\n";
} finally {
    $fixture->close();
    if ($pdo !== null) {
        $delete = $pdo->prepare('DELETE FROM campaign_bookings WHERE id=?');
        foreach ($ids as $id) $delete->execute([$id]);
        $store->delete('settings', $page['id']);
    }
}
