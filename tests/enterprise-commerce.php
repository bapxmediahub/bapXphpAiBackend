<?php
declare(strict_types=1);

use App\Services\{AgentAnswerPresentationService, AgentCapabilityPolicy, AgentConversationService, AgentToolRegistry, AgentTraceService, EnterpriseToolRegistry, StoreStatsService, SupportToolRegistry};

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
putenv('BAPX_TEST_MODE=1');
require dirname(__DIR__) . '/app/bootstrap.php';

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) { fwrite(STDERR, $message . "\n"); throw new RuntimeException($message); }
};
$orders = [
    ['id'=>'fixture-one','customer_email'=>'first@example.invalid','created_at'=>'2026-11-01T10:00:00+05:30',
        'payment_id'=>'fixture-payment-one','status'=>'delivered','total'=>1000,'coupon_code'=>'SAVE','discount'=>100,
        'verified_refunds'=>[['amount_paise'=>10000]],'items'=>[
            ['name'=>'Lamp','qty'=>1,'line_total'=>300],['name'=>'Lamp','qty'=>1,'line_total'=>300],
            ['name'=>'Mala','qty'=>1,'line_total'=>400]]],
    ['id'=>'fixture-two','customer_email'=>'first@example.invalid','created_at'=>'2026-11-02',
        'payment_id'=>'fixture-payment-two','status'=>'confirmed','total'=>500,'coupon_code'=>'SAVE','discount'=>50,
        'items'=>[['name'=>'Lamp','qty'=>1,'line_total'=>500]]],
    ['id'=>'fixture-three','customer_email'=>'second@example.invalid','created_at'=>'2026-11-03',
        'payment_id'=>'fixture-payment-three','status'=>'processing','total'=>200,
        'items'=>[['name'=>'Mala','qty'=>1,'line_total'=>200]]],
    ['id'=>'fixture-unidentified','created_at'=>'2026-11-04','payment_id'=>'fixture-payment-four','status'=>'confirmed','total'=>100,
        'items'=>[['name'=>'Bell','qty'=>1,'line_total'=>100]]],
    ['id'=>'fixture-pending','customer_email'=>'private-pending@example.invalid','created_at'=>'2026-11-04','status'=>'pending','total'=>999,
        'items'=>[['name'=>'Unpaid product','qty'=>1,'line_total'=>999]]],
    ['id'=>'fixture-cancelled','created_at'=>'2026-11-04','payment_id'=>'fixture-cancelled-payment','status'=>'cancelled','total'=>777,
        'items'=>[['name'=>'Cancelled product','qty'=>1,'line_total'=>777]]],
    ['id'=>'fixture-old','created_at'=>'2026-10-01','payment_id'=>'fixture-old-payment','status'=>'confirmed','total'=>888,
        'items'=>[['name'=>'Old product','qty'=>1,'line_total'=>888]]],
];
$dates = ['from'=>'2026-11-01','to'=>'2026-11-15'];
$report = static fn(string $type, array $extra=[]): array =>
    AgentToolRegistry::commerceReportFromRecords($orders, [], ['report'=>$type] + $extra + $dates);

$products = $report('products', ['limit'=>2]);
$assert(!isset($products['error']) && $products['total_rows'] === 3 && count($products['rows']) === 2, 'Products must return a bounded ranking with the full result count.');
$assert($products['summary']['units'] === 6 && $products['summary']['allocated_net'] === 1700.0, 'Summary covers all matching paid lines, including rows beyond the limit.');
$assert($products['rows'][0] === ['product'=>'Lamp','order_lines'=>3,'units'=>3,'gross'=>1100.0,'net'=>1040.0], 'Repeated product lines must not be mislabeled as distinct paid orders.');
$canonical = StoreStatsService::productsPurchased($orders, $dates['from'], $dates['to']);
$assert($products['rows'][1]['net'] === $canonical[1]['net'], 'Product allocation must agree with the canonical admin report.');
$assert(!str_contains(json_encode($products), 'fixture-payment') && !str_contains(json_encode($products), '@'), 'Product reports do not include payment or customer identity fields.');

$customers = $report('customers');
$assert($customers['summary'] === [
    'customers'=>2,'repeat_customers'=>1,'paid_orders'=>4,'unidentified_orders'=>1,
    'gross_paid'=>1800.0,'recorded_refunds'=>100.0,'net_paid'=>1700.0,
], 'Customer counts must exclude unknown identities while totals account for their paid orders.');
$assert(array_column($customers['rows'],'band') === ['one_order','two_to_four'], 'Customer rows are fixed anonymous frequency bands.');
$assert(!preg_match('/example|customer_email|fixture-payment|fixture-one/', json_encode($customers)), 'Customer report must not return identities or private order references.');
$assert(str_contains($customers['basis'], 'not lifetime retention'), 'Within-period frequency must retain its interpretation limit.');

$coupons = $report('coupons');
$assert($coupons['rows'] === [['coupon'=>'SAVE','uses'=>2,'discount'=>150.0,'net'=>1400.0]], 'Coupon figures must use the same paid orders and refund amounts as the canonical report.');
$assert(str_contains($coupons['basis'], 'not sales caused'), 'Coupon association must not be labeled causal attribution.');

$stock = AgentToolRegistry::commerceReportFromRecords([], [
    ['name'=>'Active product','stock_status'=>'active','price'=>50,'offer_price'=>40],
    ['name'=>'Out of stock','stock_status'=>'out_of_stock','price'=>100,'offer_price'=>null],
    ['name'=>'Draft product','stock_status'=>'draft','price'=>200,'offer_price'=>null],
    ['name'=>'Unrecorded status','stock_status'=>'legacy','price'=>300,'offer_price'=>null],
], ['report'=>'stock','limit'=>2]);
$assert($stock['summary'] === ['products'=>4,'in_stock'=>1,'out_of_stock'=>1,'inactive'=>0,'draft'=>1,'unknown_status'=>1], 'Current stock status counts must remain distinct from quantities.');
$assert($stock['report_url'] === '/admin/tax-report?report=stock' && !isset($stock['from'],$stock['to']), 'A stock snapshot must not claim historical dates.');
$assert($stock['rows'][0]['offer_price'] === 40.0 && !isset($stock['rows'][1]['quantity']), 'Stock report preserves nullable effective prices without inventing quantities.');

$presenter = new AgentAnswerPresentationService();
foreach ([$products,$stock,$customers,$coupons] as $result) {
    $action = ['label'=>'Commerce report','path'=>$result['report_url']];
    $surface = $presenter->present('Recorded owner report.','admin',[$action]);
    $assert($surface['messages'][0]['createSurface']['dataModel']['actions'] === [$action], 'Every canonical commerce source must remain clickable in the primary owner surface.');
    $assert(!AgentAnswerPresentationService::safeActionPath($result['report_url'],'support')
        && !AgentAnswerPresentationService::safeActionPath($result['report_url'],'public'), 'Commerce source paths must never be accepted for Support or public hosts.');
}
foreach ([
    '/admin/settings', '/admin/tax-report?report=products', '/admin/tax-report?report=users',
    '/admin/tax-report?report=stock&from=2026-11-01&to=2026-11-15',
    '/admin/tax-report?report=coupons&from=2026-11-01&to=2026-11-15&format=csv',
    'https://evil.invalid/admin/tax-report?report=stock',
] as $path) {
    $surface = $presenter->present('Recorded owner report.','admin',[['label'=>'Unapproved navigation','path'=>$path]]);
    $assert($surface['messages'][0]['createSurface']['dataModel']['actions'] === [], 'Unapproved or noncanonical source routes must not become owner actions.');
}

foreach ([
    ['report'=>'users'], ['report'=>'sales'], ['report'=>[]], ['report'=>'products','limit'=>0],
    ['report'=>'products','limit'=>11], ['report'=>'products','limit'=>'2'], ['report'=>'products','limit'=>2.5],
    ['report'=>'products','limit'=>null], ['report'=>'products','limit'=>true],
    ['report'=>'products','from'=>null], ['report'=>'products','to'=>[]],
    ['report'=>'products','from'=>'2026-02-30','to'=>'2026-03-01'],
    ['report'=>'products','from'=>'2026-11-15','to'=>'2026-11-01'],
    ['report'=>'products','from'=>'2024-01-01','to'=>'2026-11-01'],
    ['report'=>'products','from'=>'2999-01-01','to'=>'2999-01-02'],
    ['report'=>'products','sql'=>'SELECT * FROM users'], ['report'=>'products','customer_email'=>'first@example.invalid'],
    ['report'=>'stock','from'=>'2026-11-01'], ['report'=>'stock','to'=>'2026-11-15'],
] as $invalid) {
    $assert(isset(AgentToolRegistry::commerceReportFromRecords($orders, [], $invalid)['error']), 'Invalid report scope must be rejected.');
}
$empty = AgentToolRegistry::commerceReportFromRecords([], [], ['report'=>'products'] + $dates);
$assert(!isset($empty['error']) && $empty['total_rows'] === 0 && $empty['rows'] === [] && $empty['summary']['units'] === 0, 'A successful empty report is different from an invalid request.');

$evidence = AgentTraceService::toolEvidence('commerce_report', ['from'=>'wrong','customer_email'=>'private@example.invalid'], $customers);
$tool = ['name'=>'commerce_report','status'=>'complete','evidence'=>$evidence];
$assert($evidence['source'] === 'commerce_reports' && $evidence['filters']['from'] === $dates['from'], 'Evidence uses the actual source and executed range.');
$saved = AgentTraceService::safeTool(json_decode(json_encode($tool, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR));
$assert($saved['evidence']['report'] === AgentTraceService::safeCommerceReport($customers), 'The typed report must survive JSON replay without a fresh query.');
$assert(!str_contains(json_encode($saved), 'private@example.invalid'), 'Requested customer identifiers must not enter report evidence.');
$tampered = $customers;
$tampered['customer_email'] = 'private@example.invalid';
$tampered['rows'][0]['email'] = 'private@example.invalid';
$tampered['summary']['raw_sql'] = 'PRIVATE QUERY';
$tampered['basis'] = '<script>PRIVATE INSTRUCTION</script>';
$tampered['report_url'] = '/admin/secrets';
$projection = AgentTraceService::safeCommerceReport($tampered);
$assert(!str_contains(json_encode($projection), 'PRIVATE') && !str_contains(json_encode($projection), '@')
    && $projection['report_url'] === $customers['report_url'], 'Saved report projection must discard unapproved fields and derive meaning and navigation itself.');
$missing = $customers; unset($missing['rows'][0]['net']);
$assert(AgentTraceService::safeCommerceReport($missing) === null, 'Missing recorded amounts must not become zero.');
$invalidNumber = $customers; $invalidNumber['summary']['net_paid'] = INF;
$assert(AgentTraceService::safeCommerceReport($invalidNumber) === null, 'Non-finite money must fail closed.');
$invalidRows = $customers; $invalidRows['limit'] = 1;
$assert(AgentTraceService::safeCommerceReport($invalidRows) === null, 'Oversized saved rows must fail closed.');
$wrongBand = $customers; $wrongBand['rows'][0]['band'] = 'private@example.invalid';
$assert(AgentTraceService::safeCommerceReport($wrongBand) === null, 'Arbitrary customer labels cannot enter anonymous report cards.');
$failed = AgentTraceService::safeTool(['name'=>'commerce_report','status'=>'error','evidence'=>$evidence]);
$assert(!isset($failed['evidence']['report']), 'Failed tools do not produce report cards.');
$wrongSource = $tool; $wrongSource['evidence']['source'] = 'orders';
$assert(!isset(AgentTraceService::safeTool($wrongSource)['evidence']['report']), 'A report must have its own allowlisted source.');
$changed = $customers; $changed['summary']['net_paid'] = 1699.0;
$assert(AgentTraceService::toolEvidence('commerce_report', [], $changed)['facts_sha256'] !== $evidence['facts_sha256'], 'Recorded report values participate in the evidence fingerprint.');

// Persist a real synthetic owner conversation; reopening keeps its observed period
// and report values, without querying MySQL or trusting a new browser actor.
$directory = sys_get_temp_dir() . '/bapx-enterprise-report-' . bin2hex(random_bytes(6));
$conversations = new AgentConversationService($directory);
$actor = ['type'=>'admin','id'=>'fixture-report-owner'];
$context = ['tool'=>'commerce_report','report'=>'customers'] + $dates;
try {
    $record = $conversations->record('admin', $actor, null, 'Synthetic customer report for the stated dates.',
        'Synthetic recorded customer groups.', bin2hex(random_bytes(8)), 'local', [$tool], reportContext:$context);
    $reopened = $conversations->getForActor($record['conversation_id'], 'admin', $actor);
    $assert(AgentTraceService::safeTool($reopened['turns'][0]['tools'][0])['evidence']['report'] === $saved['evidence']['report'],
        'Actor-owned conversation replay must retain the exact bounded recorded report.');
    $assert($conversations->previousReportForActor($record['conversation_id'], $actor) === $context,
        'The effective report period and type must survive actor-owned replay.');
    $denied = false;
    try { $conversations->getForActor($record['conversation_id'], 'admin', ['type'=>'admin','id'=>'another-owner']); }
    catch (RuntimeException) { $denied = true; }
    $assert($denied, 'A different owner cannot reopen report evidence.');
} finally {
    foreach (glob($directory . '/{*,.*}', GLOB_BRACE) ?: [] as $file) if (is_file($file)) unlink($file);
    if (is_dir($directory)) rmdir($directory);
}

$previous = $_SESSION['user'] ?? null;
try {
    foreach ([null, ['role'=>'customer','email'=>'customer@example.invalid'], ['role'=>'guest']] as $actor) {
        $_SESSION['user'] = $actor;
        $assert(!AgentCapabilityPolicy::allows('enterprise','commerce_report',$actor), 'Only the server-authenticated owner can run the report.');
        $assert(isset((new EnterpriseToolRegistry())->run('commerce_report',['report'=>'customers'])['error']), 'Execution must enforce the owner boundary.');
        $assert(EnterpriseToolRegistry::capabilities($actor) === [], 'Capability guidance must not advertise unauthorized tools.');
    }
    $_SESSION['user'] = ['role'=>'admin','id'=>'fixture-owner'];
    $enterprise = new EnterpriseToolRegistry();
    $assert(array_column($enterprise->declarations(),'name') === AgentCapabilityPolicy::tools('enterprise'), 'Tool declarations and authorization policy must remain in parity.');
    $schema = array_column($enterprise->declarations(), 'parameters', 'name')['commerce_report'];
    $assert($schema['properties']['report']['enum'] === ['products','stock','customers','coupons']
        && $schema['properties']['limit']['maximum'] === 10 && $schema['additionalProperties'] === false, 'The provider sees the same strict report contract.');
    $assert(!in_array('commerce_report', array_column((new SupportToolRegistry(null))->declarations(),'name'), true), 'The private report must not become a Support tool.');
    $assert(isset($enterprise->run('commerce_report',['report'=>'stock','from'=>'2026-11-01'])['error']), 'Runtime rejects dates on stock before returning data.');
    $success = $enterprise->run('commerce_report',['report'=>'products'] + $dates);
    $assert(!isset($success['error']) && $success['total_rows'] === 0, 'Authorized runtime dispatch works with the isolated empty test store.');
    $activities = $enterprise->activity();
    $assert(isset($activities[1]['evidence']['report']), 'Runtime dispatch records the completed report projection.');
    $guide = $enterprise->guideAnswer('What can you do?');
    $assert(is_string($guide) && str_contains($guide,'Customer patterns') && str_contains($guide,'live availability and booking are not available'), 'Task guidance states usable capabilities and appointment limits.');
    $assert($enterprise->guideAnswer('What can you do with sales from 2026-11-01?') === null, 'A business question must not be swallowed by generic capability help.');
    $followup = new EnterpriseToolRegistry(ownerQuestion:'What about coupons for that same period?',
        previousReport:['tool'=>'commerce_report','report'=>'products'] + $dates);
    $assert($followup->followUpContext() === $dates + ['tool'=>'commerce_report','report'=>'products'], 'Follow-up dates come from the validated previous report.');
} finally {
    if ($previous === null) unset($_SESSION['user']); else $_SESSION['user'] = $previous;
}
echo "Enterprise commerce: $checks checks passed.\n";
