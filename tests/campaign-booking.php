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

use App\Integrations\Razorpay\{PaymentLinkCreationRejected, RazorpayClient};
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

$invalidCredentials = json_encode(['error'=>['code'=>'BAD_REQUEST_ERROR','description'=>'The api key provided is invalid']], JSON_THROW_ON_ERROR);
$accountDisabled = json_encode(['error'=>['code'=>'BAD_REQUEST_ERROR',
    'description'=>'Invalid access: Cannot create a payment link in live mode, as live mode is disabled for merchant.']], JSON_THROW_ON_ERROR);
$duplicate = json_encode(['error'=>['code'=>'BAD_REQUEST_ERROR','description'=>'payment link creation with reference ID already attempted']], JSON_THROW_ON_ERROR);
foreach ([
    [401, $invalidCredentials, true], [400, $accountDisabled, true], [400, $duplicate, false],
    [401, false, false], [401, '<html>Authentication failed</html>', false],
    [401, '{"error":{"description":"Authentication failed"}}', false],
    [500, $invalidCredentials, false],
] as [$status, $body, $definite]) {
    try {
        RazorpayClient::paymentLinkResult($body, $status);
        throw new LogicException('A failed link POST returned a successful result.');
    } catch (RuntimeException $failure) {
        $assert(($failure instanceof PaymentLinkCreationRejected) === $definite, 'Wrong retry classification for provider response.');
    }
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
    if ($op === 'family') {
        if ($booking === null || $booking['owner_hash'] !== $args['owner']) return null;
        $root = $records[$booking['family_id'] ?? $id];
        $rows = CampaignPaymentService::familyRows($root, fn(string $childId) => $records[$childId] ?? null);
        return CampaignPaymentService::familyState($rows,$now) + ['requested'=>$booking];
    }
    if ($op === 'begin') {
        $decision = CampaignPaymentService::reserve($page,$id,$args['slug'],$args['revision'],$args['owner'],
            $args['mode'],$args['freshFrom'] ?? null,$booking,fn(string $childId) => $records[$childId] ?? null,$now);
        if (in_array($decision['outcome'], ['created','retry'], true)) {
            $records[$decision['booking']['id']] = $decision['booking'];
        }
        if (isset($decision['root_update'])) $records[$id] = $decision['root_update'];
        unset($decision['root_update']);
        return $decision;
    }
    if ($op === 'claim') {
        $decision = CampaignPaymentService::claimCheck($booking,$now);
        $records[$id] = $decision['booking'];
        return $decision;
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

    // The same browser can retry an explicit provider rejection after credential repair.
    $errors = true;
    $creates = [];
    $retrySecrets = $fixture->secrets;
    $gateway = $fixture->gatewayPort();
    $retryPayments = new CampaignPaymentService(store:$store, operation:$mysql ? null : $operate,
        secrets:static function () use (&$retrySecrets): array { return $retrySecrets; },
        gateway:static function (string $operation, array $attempt, array $payload) use (&$errors, &$creates, $gateway, $invalidCredentials): array {
            if ($operation === 'create_link') {
                $creates[] = $payload;
                if ($errors) return RazorpayClient::paymentLinkResult($invalidCredentials, 401);
            }
            return $gateway($operation, $attempt, $payload);
        });
    $rejected = $retryPayments->start($slug, $nextRevision, $switched);
    $ids[] = $rejected['id'];
    $assert($rejected['status'] === 'retryable' && $rejected['creation_attempt'] === 1
        && !isset($rejected['provider_link_id']), 'Authentication rejection did not open an explicit retry.');
    $assert($retryPayments->owned($rejected['id'], $owner) === null
        && $retryPayments->reconcile($rejected) === $rejected && count($creates) === 1,
        'Another browser or a status check acquired a provider creation.');

    $retrySecrets = [];
    $assert($retryPayments->start($slug, $nextRevision, $switched) === ['outcome'=>'unavailable'],
        'Missing configuration allowed overlap with a rejected creation.');
    $retrySecrets = ['razorpay_mode'=>'live','razorpay_live_key_id'=>'rzp_live_fixture',
        'razorpay_live_key_secret'=>'synthetic','razorpay_live_webhook_secret'=>'synthetic'];
    $assert($retryPayments->start($slug, $nextRevision, $switched) === ['outcome'=>'unavailable'],
        'A retry changed the approved provider mode.');
    $retrySecrets = $fixture->secrets;
    $page['status'] = 'draft';
    $writePage();
    $assert($retryPayments->start($slug, $nextRevision, $switched) === ['outcome'=>'stale'] && count($creates) === 1,
        'A withdrawn page acquired a retry claim.');
    $page['status'] = 'published';
    $writePage();

    $errors = false;
    $recovered = $retryPayments->start($slug, $nextRevision, $switched);
    $assert($recovered['status'] === 'pending' && $recovered['id'] === $rejected['id']
        && $recovered['creation_attempt'] === 2 && $recovered['amount_paise'] === $rejected['amount_paise']
        && $recovered['fulfillment'] == $rejected['fulfillment'] && $recovered['mode'] === $rejected['mode']
        && count($creates) === 2 && $creates[0]['reference_id'] === $creates[1]['reference_id'],
        'Credential repair did not reopen the identical frozen booking and reference.');
    $assert($retryPayments->start($slug, $nextRevision, $switched)['id'] === $recovered['id'] && count($creates) === 2,
        'Repeated booking after retry created an additional link.');
    $assert(CampaignPaymentService::applyChange($recovered, ['status'=>'uncertain','creation_attempt'=>1], time()) === $recovered,
        'A stale provider observation changed the current attempt.');

    // Fresh attempts are explicit, separate receipts, and require current
    // provider-terminal evidence with no recorded payment, even a failed one.
    $freshOwner = hash('sha256', 'fresh-booking-owner-fixture');
    $first = $payments->start($slug, $nextRevision, $freshOwner);
    $firstId = $first['id'];
    $ids[] = $firstId;
    $firstLink = $first['provider_link_id'];
    $firstOrder = $fixture->links[$firstLink]['order_id'];
    $createsFor = static fn(string $id): int => count(array_filter($fixture->gatewayCalls,
        static fn(array $call): bool => $call['attemptId'] === $id && $call['operation'] === 'create_link'));
    $nextId = CampaignPaymentService::replacementId($firstId, $firstId, $freshOwner);
    $ids[] = $nextId;
    $assert($payments->start($slug,$nextRevision,$freshOwner,freshFrom:$firstId)['id'] === $firstId
        && $createsFor($firstId) === 1 && $createsFor($nextId) === 0, 'A pending booking permitted a fresh link.');
    $assert($payments->start($slug,$nextRevision,$freshOwner,freshFrom:$id) === ['outcome'=>'stale'],
        'A receipt from another booking family authorized a replacement.');
    $assert($payments->start($slug,$nextRevision,$other,freshFrom:$firstId) === ['outcome'=>'stale'],
        'Another browser authorized a replacement.');

    $fixture->links[$firstLink]['status'] = 'expired';
    $fixture->links[$firstLink]['amount_paid'] = 1;
    $advance($firstId);
    $first = $payments->reconcile($lookup($firstId));
    $assert($first['status']==='expired' && !$payments->familyFor($first)['can_replace'],
        'Nonzero provider payment evidence permitted a replacement.');
    $fixture->links[$firstLink]['amount_paid'] = 0;
    $fixture->payments['pay_unresolvedfixture'] = ['id'=>'pay_unresolvedfixture','order_id'=>$firstOrder,'status'=>'authorized',
        'captured'=>false,'amount_refunded'=>0];
    foreach (['authorized','failed','captured','created'] as $paymentStatus) {
        $fixture->payments['pay_unresolvedfixture']['status'] = $paymentStatus;
        $advance($firstId);
        $first = $payments->reconcile($lookup($firstId));
        $assert(!$payments->familyFor($first)['can_replace'], 'A recorded payment permitted a distinct link: '.$paymentStatus);
    }
    unset($fixture->payments['pay_unresolvedfixture']);
    $fixture->gatewayOffline = true;
    $advance($firstId);
    $first = $payments->reconcile($lookup($firstId));
    $assert(!$payments->familyFor($first)['can_replace'], 'A provider failure preserved clean terminal evidence.');
    $fixture->gatewayOffline = false;
    $advance($firstId);
    $first = $payments->reconcile($lookup($firstId));
    $assert($payments->familyFor($first)['can_replace'] && $first['terminal_clear']===true,
        'Verified empty provider order did not permit a fresh attempt.');
    $assert($payments->start($slug,$nextRevision,$freshOwner)['id']===$firstId && $createsFor($nextId)===0,
        'An ordinary review automatically created a new payment.');

    $savedPost = $_POST; $savedSession = $_SESSION;
    try {
        $_SESSION = ['csrf_token'=>'fixture-csrf','campaign_payment_key'=>str_repeat('f',64)];
        $controller = new \App\Controllers\CampaignBookingController($payments);
        foreach ([
            [['_csrf'=>'wrong','revision'=>$nextRevision,'fresh_from'=>$firstId,'confirm_fresh'=>'1'],419],
            [['_csrf'=>'fixture-csrf','revision'=>$nextRevision,'fresh_from'=>$firstId],422],
            [['_csrf'=>'fixture-csrf','revision'=>$nextRevision,'fresh_from'=>[$firstId],'confirm_fresh'=>'1'],422],
            [['_csrf'=>'fixture-csrf','revision'=>$nextRevision,'confirm_fresh'=>'1'],422],
        ] as [$post,$expectedStatus]) {
            $_POST=$post;
            $callsBefore=count($fixture->gatewayCalls);
            ob_start();
            try { $controller->start($slug); } finally { $html=ob_get_clean(); }
            $assert(http_response_code()===$expectedStatus && !str_contains($html,'Open Razorpay')
                && count($fixture->gatewayCalls)===$callsBefore, 'Controller admitted an unconfirmed or invalid replacement.');
        }
    } finally { $_POST=$savedPost; $_SESSION=$savedSession; http_response_code(200); }

    $advance($firstId);
    $second = $payments->start($slug,$nextRevision,$freshOwner,freshFrom:$firstId);
    $assert($second['id']===$nextId && $second['family_id']===$firstId && $second['previous_id']===$firstId
        && $second['reference_id'] !== $first['reference_id'] && $second['status']==='pending'
        && $second['amount_paise']===$first['amount_paise'] && $second['fulfillment']===$first['fulfillment']
        && $createsFor($nextId)===1, 'Explicit replacement did not create a separate frozen receipt.');
    $assert($lookup($firstId)['status']==='expired' && $lookup($firstId)['provider_link_id']===$firstLink,
        'Fresh payment rewrote the previous receipt.');
    $assert($payments->start($slug,$nextRevision,$freshOwner,freshFrom:$firstId)['id']===$nextId
        && $payments->start($slug,$nextRevision,$freshOwner)['id']===$nextId && $createsFor($nextId)===1,
        'A repeated confirmation created another provider payment.');
    $assert($payments->owned($nextId,$other)===null, 'Another browser accessed a replacement receipt.');

    // A signed paid notification is durable even while verification is unavailable.
    $fixture->attempts[$firstId] = $first;
    $fixture->markPaid($firstId);
    $fixture->gatewayOffline = true;
    $advance($firstId);
    $send($firstId,'payment_link.paid',$secret,503);
    $family = $payments->familyFor($second);
    $assert($family['blocked'] && in_array($firstId,$family['blocked_ids'],true) && !$family['can_replace'],
        'A late unresolved payment did not suspend the related payment handoff.');
    $fixture->gatewayOffline = false;
    $advance($firstId);
    $send($firstId,'payment_link.paid',$secret);
    $settled = $lookup($firstId);
    $family = $payments->familyFor($second);
    $assert($family['captured'][0]['id']===$firstId && $settled['receipt']['id']==='book_'.$firstId
        && $payments->start($slug,$nextRevision,$freshOwner,freshFrom:$nextId)['id']===$firstId,
        'Late capture did not stop replacement handoff and return the confirmed old receipt.');
    // Simulate a second already-open external link settling: retain both real
    // verified receipts rather than drop payment evidence or auto-refund.
    $fixture->attempts[$nextId] = $second;
    $fixture->markPaid($nextId);
    $advance($nextId);
    $send($nextId,'payment_link.paid',$secret);
    $family = $payments->familyFor($lookup($nextId));
    $assert(array_column($family['captured'],'id')===[$firstId,$nextId]
        && $lookup($firstId)['receipt']===$settled['receipt']
        && $lookup($nextId)['receipt']['id']==='book_'.$nextId && !$family['can_replace'],
        'Multiple verified receipts were lost or permitted a third payment.');

    // Stale reconciliation generations must never reinstate an old clean proof.
    $older = CampaignPaymentService::claimCheck(array_replace($first, ['reconcile_after'=>0]),time())['booking'];
    $newer = CampaignPaymentService::claimCheck(array_replace($older,['reconcile_after'=>0]),time())['booking'];
    $staleChange = ['status'=>'expired','creation_attempt'=>$newer['creation_attempt'],
        'reconcile_generation'=>$older['reconcile_generation'],'terminal_clear'=>true,
        'provider_link_id'=>$firstLink,'provider_url'=>$first['provider_url']];
    $assert(CampaignPaymentService::applyChange($newer,$staleChange,time())===$newer,
        'An outdated provider read restored replacement eligibility.');

    if ($mysql) {
        // Two workers open the same published page simultaneously. One may create a link.
        $racingId = bin2hex(random_bytes(16));
        $ids[] = $racingId;
        $race = static function (?string $freshFrom = null, ?string $expectedId = null) use ($racingId, $slug, $nextRevision, $other, $assert): array {
            $job = json_encode([$racingId, $slug, $nextRevision, $other, 'test', $freshFrom], JSON_THROW_ON_ERROR);
            $expectedId ??= $racingId;
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
                $assert(proc_close($worker) === 0 && $result['booking']['id'] === $expectedId, 'Worker did not return the frozen booking.');
                $outcomes[] = $result['outcome'];
            }
            sort($outcomes);
            return $outcomes;
        };
        $assert($race() === ['created','existing'], 'Concurrent starts reserved two provider creations.');
        $store->updateCampaignBooking($racingId, ['status'=>'retryable','creation_attempt'=>1]);
        $assert($race() === ['existing','retry'], 'Concurrent recovery did not admit exactly one creation retry.');
        $assert($lookup($racingId)['creation_attempt'] === 2, 'Concurrent retry changed the durable creation generation more than once.');
        $store->updateCampaignBooking($racingId, ['status'=>'expired','creation_attempt'=>2,'terminal_clear'=>true,
            'provider_link_id'=>'plink_racefixture','provider_url'=>'https://rzp.io/rzp/racefixture']);
        $replacementId = CampaignPaymentService::replacementId($racingId,$racingId,$other);
        $ids[] = $replacementId;
        $assert($race($racingId,$replacementId) === ['created','existing'], 'Concurrent fresh confirmations created two replacements.');
        $assert($lookup($racingId)['replacement_ids'] === [$replacementId]
            && $lookup($replacementId)['creation_attempt']===1, 'Fresh reservation did not atomically preserve one successor.');
        $family = $store->readCampaignBookingFamily($replacementId,$other);
        $assert($family['ids']===[$racingId,$replacementId] && $family['requested']['id']===$replacementId
            && $store->readCampaignBookingFamily($replacementId,$owner)===null, 'Coherent family read did not enforce ownership.');

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
