<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
putenv('BAPX_TEST_MODE=1');
require dirname(__DIR__).'/app/bootstrap.php';

use App\Services\{PaymentService,VerifiedOrderService};
$checks=0;
$expect=static function(bool $ok,string $message)use(&$checks):void {
    $checks++;
    if(!$ok)throw new RuntimeException($message);
};
$payment=['id'=>'pay_fixture','order_id'=>'order_fixture','amount'=>115,'currency'=>'INR','status'=>'captured','captured'=>true];
$expect(PaymentService::isCapturedRazorpayPayment($payment,'pay_fixture','order_fixture',115),'Captured exact INR payment must verify.');
foreach([
    ['id'=>'pay_other'],['order_id'=>'order_other'],['amount'=>114],['amount'=>'115'],
    ['amount'=>115.0],['currency'=>'USD'],['currency'=>'inr'],['status'=>'authorized'],
    ['status'=>'created'],['status'=>'refunded'],['captured'=>false],['captured'=>1],
] as $change) {
    $expect(!PaymentService::isCapturedRazorpayPayment(array_replace($payment,$change),'pay_fixture','order_fixture',115),
        'Wrong identity, amount, currency or uncaptured state cannot become a paid order.');
}
$expect(!PaymentService::isCapturedRazorpayPayment($payment,'pay_fixture','order_fixture',0),'Zero expected payment cannot verify.');
$expect(!PaymentService::isCapturedRazorpayPayment([],'pay_fixture','order_fixture',115),'Missing gateway facts cannot verify.');

$sessionBefore=$_SESSION;
$records=[];$catalogue=['item'=>['stock_status'=>'in_stock','gst_rate'=>5,'hsn_code'=>'8306']];
$settings=['gst_state'=>'Tamil Nadu','default_gst_rate'=>5.0,'default_hsn_code'=>'8306',
    'gst_legal_name'=>'Fixture Shop','gst_trade_name'=>'Fixture','gstin'=>'',
    'gst_address'=>'Fixture address','gst_state_code'=>'33'];
$ports=[
    'readOrders'=>static function()use(&$records):array{return array_values($records);},
    'products'=>static function()use(&$catalogue):array{return $catalogue;},
    'settings'=>static function()use(&$settings):array{return $settings;},
    'insertOrder'=>static function(array $order)use(&$records):array{
        if(isset($records[$order['id']]))return ['inserted'=>false,'record'=>$records[$order['id']]];
        $records[$order['id']]=$order;return ['inserted'=>true,'record'=>$order];
    },
];
$pending=['total'=>94.5,'discount'=>10.5,'coupon_code'=>'FIXTURE',
    'customer_email'=>'test@example.invalid','customer_name'=>'Fixture','customer_phone'=>'',
    'shipping_address'=>'Fixture','shipping_city'=>'Fixture','shipping_pincode'=>'000000','shipping_state'=>'Tamil Nadu',
    'items'=>[['slug'=>'item','name'=>'Fixture item','qty'=>1,'line_total'=>105.0]]];
$service=new VerifiedOrderService();
try {
    $_SESSION=['cart'=>[['slug'=>'later-item','qty'=>2]],'pending_order'=>['marker'=>'other checkout']];
    $untouched=$_SESSION;
    $id=$service->persist($pending,'pay_discount',['razorpay_order_id'=>'order_discount','total'=>999],$ports);
    $expect($records[$id]['total']===94.5 && $records[$id]['tax_total']===4.5,
        'Verified payable amount and proportional coupon discount must reach the tax snapshot.');
    $expect($records[$id]['discount']===10.5 && $records[$id]['coupon_code']==='FIXTURE','Coupon evidence must be retained.');
    $expect($records[$id]['tax_lines'][0]['gross_value']===94.5 && $records[$id]['tax_lines'][0]['taxable_value']===90.0,
        'Discounted invoice lines must agree with the charged total.');
    $expect($records[$id]['razorpay_order_id']==='order_discount' && isset($records[$id]['invoice_number']),
        'Verified gateway reference and one invoice must survive shared persistence.');
    $expect($_SESSION===$untouched,'API/shared persistence must not clear any browser session.');
    $expect($service->persist($pending,'pay_discount',[],$ports)===$id && count($records)===1,
        'Repeated verified payment must converge on the first saved order.');

    $frozen=$pending;
    $frozen['tax_settings']=$settings;
    $frozen['items'][0]+=['unit_price'=>105.0,'gst_rate'=>5.0,'hsn_code'=>'8306'];
    $catalogue['item']['gst_rate']=18;$settings['gst_state']='Karnataka';
    $frozenId=$service->persist($frozen,'pay_frozen',[],$ports);
    $expect($records[$frozenId]['tax_total']===4.5 && $records[$frozenId]['supply_type']==='intrastate',
        'Later catalogue/supplier edits cannot rewrite confirmed quote tax evidence.');
    $expect($records[$frozenId]['items'][0]['gst_rate']===5.0 && $records[$frozenId]['supplier']['state']==='Tamil Nadu',
        'Frozen rate and supplier state must be preserved.');
    $catalogue=[];
    $missingId=$service->persist($frozen,'pay_missing_frozen',[],$ports);
    $expect($records[$missingId]['status']==='on_hold' && $records[$missingId]['fulfillment_review_required']===true,
        'A product removed after payment must require fulfillment review.');
    $expect($records[$missingId]['tax_review_required']===false && isset($records[$missingId]['invoice_number']),
        'Complete frozen tax evidence can preserve an invoice after catalogue removal.');
    $legacyId=$service->persist($pending,'pay_missing_legacy',[],$ports);
    $expect($records[$legacyId]['tax_review_required']===true && !isset($records[$legacyId]['invoice_number']),
        'Legacy missing product tax data cannot produce an invented invoice.');
    $broken=$frozen;$broken['items'][0]['gst_rate']=-1;
    $brokenId=$service->persist($broken,'pay_invalid_frozen',[],$ports);
    $expect($records[$brokenId]['tax_review_required']===true && !isset($records[$brokenId]['invoice_number']),
        'Invalid frozen tax evidence preserves payment but requires review.');
    $zero=$frozen;$zero['items'][0]['gst_rate']=0.0;
    $zeroId=$service->persist($zero,'pay_zero_rate',[],$ports);
    $expect($records[$zeroId]['tax_total']===0.0 && $records[$zeroId]['items'][0]['gst_rate']===0.0,
        'An explicit valid zero GST rate must not be replaced by a default.');
    $badState=$frozen;$badState['tax_settings']['gst_state']='Unknown';
    $badStateId=$service->persist($badState,'pay_bad_state',[],$ports);
    $expect($records[$badStateId]['tax_review_required']===true && $records[$badStateId]['total']===94.5,
        'Invalid supplier state must not discard the verified payment or fabricate tax.');

    $catalogue=['item'=>['stock_status'=>'in_stock','gst_rate'=>5,'hsn_code'=>'8306']];
    $persist=(new ReflectionMethod(App\Controllers\CommerceController::class,'persistVerifiedOrder'))
        ->getClosure(new App\Controllers\CommerceController());
    $_SESSION=['pending_order'=>$pending,'cart'=>[['qty'=>1,'slug'=>'item']]];
    $persist($pending,'pay_browser',[],$ports);
    $expect(!isset($_SESSION['pending_order']) && !isset($_SESSION['cart']),'Matching browser checkout clears its own cart after persistence.');
    $_SESSION=['pending_order'=>$pending,'cart'=>[['slug'=>'item','qty'=>2]]];
    $persist($pending,'pay_browser',[],$ports);
    $expect(!isset($_SESSION['pending_order']) && $_SESSION['cart'][0]['qty']===2,
        'A repeated callback must preserve later cart edits.');
    $_SESSION=$untouched;
    $persist($pending,'pay_browser',[],$ports);
    $expect($_SESSION===$untouched,'A callback must preserve a different active checkout.');
    $failedPorts=$ports;$failedPorts['insertOrder']=static function(array $order):array{throw new RuntimeException('Fixture write failure');};
    try {$service->persist($pending,'pay_storage_failure',[],$failedPorts);$expect(false,'A failed write must not claim completion.');}
    catch(RuntimeException $error){$expect($error->getMessage()==='Fixture write failure','Storage failure must propagate for payment recovery.');}
    $expect($_SESSION===$untouched,'Persistence failure cannot clear browser recovery state.');
} finally {$_SESSION=$sessionBefore;}
echo "Verified captured payment, discounted/frozen tax, idempotent persistence and session isolation: {$checks} checks passed.\n";
