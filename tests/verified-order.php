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

// Run the actual browser controller against native cart/coupon calculation and
// an isolated recording gateway. Never submit a real payment.
$fixtureCoupon=static fn(string $type,float $value):array=>['code'=>'FIXTURE','active'=>true,'discount_type'=>$type,'discount_value'=>$value];
$checkoutCases=[
    [['price'=>105.0,'coupon'=>$fixtureCoupon('percentage',100),'posted'=>'99999'],400,0],
    [['price'=>105.0,'coupon'=>$fixtureCoupon('fixed',200),'posted'=>'100'],400,0],
    [['price'=>0.0,'posted'=>'100'],400,0],
    [['price'=>105.0,'coupon'=>$fixtureCoupon('fixed',105)],400,0],
    [['price'=>1.0,'coupon'=>$fixtureCoupon('fixed',0.01),'posted'=>'10000'],400,0],
    [['price'=>105.0,'coupon'=>$fixtureCoupon('percentage',10),'posted'=>'1'],200,9450],
    [['price'=>1.15,'posted'=>'999999'],200,115],
    [['price'=>1.0],200,100],
];
foreach($checkoutCases as [$case,$status,$amount]){
    $process=proc_open([PHP_BINARY,__DIR__.'/fixtures/browser-checkout-amount.php',json_encode($case,JSON_THROW_ON_ERROR)],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,['BAPX_CHECKOUT_AMOUNT_FIXTURE'=>'1']);
    if(!is_resource($process))throw new RuntimeException('Cannot start checkout fixture');
    fclose($pipes[0]);$raw=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    $expect($exit===0 && $stderr==='','Checkout fixture must execute without runtime errors');
    $result=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    $expect($result['status']===$status,'Browser checkout must enforce server-derived payable amount');
    $expect($result['gateway_calls']===($amount>0?[$amount]:[]),'Only the authoritative amount may reach the recording gateway');
    $expect($result['session']['cart']===[['slug'=>'fixture-product','qty'=>1]],'Checkout creation must preserve cart');
    if($amount>0){
        $expect($result['body']['amount']===$amount && (int)round($result['session']['pending_order']['total']*100)===$amount,'Response and pending order must agree with gateway amount');
    }else{
        $expect(isset($result['body']['error'])&&$result['session']['pending_order']===['marker'=>'existing recovery'],'Rejected amount must preserve prior payment recovery');
    }
}

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
    $expect($records[$id]['place_of_supply_code']==='33' && $records[$id]['supplier']['state_code']==='33','Both official codes persist with the issued invoice.');
    $expect($_SESSION===$untouched,'API/shared persistence must not clear any browser session.');
    $expect($service->persist($pending,'pay_discount',[],$ports)===$id && count($records)===1,
        'Repeated verified payment must converge on the first saved order.');

    $frozen=$pending;
    $frozen['tax_settings']=$settings;
    $frozen['items'][0]+=['unit_price'=>105.0,'gst_rate'=>5.0,'hsn_code'=>'8306','gst_uqc'=>'KGS','gst_qty_per_unit'=>'0.125'];
    $catalogue['item']['gst_rate']=18;$settings['gst_state']='Karnataka';
    $catalogue['item'] += ['gst_uqc'=>'PCS','gst_qty_per_unit'=>'1.000'];
    $frozenId=$service->persist($frozen,'pay_frozen',[],$ports);
    $expect($records[$frozenId]['tax_total']===4.5 && $records[$frozenId]['supply_type']==='intrastate',
        'Later catalogue/supplier edits cannot rewrite confirmed quote tax evidence.');
    $expect($records[$frozenId]['items'][0]['gst_rate']===5.0 && $records[$frozenId]['supplier']['state']==='Tamil Nadu',
        'Frozen rate and supplier state must be preserved.');
    $expect($records[$frozenId]['supplier']['state_code']==='33' && $records[$frozenId]['place_of_supply_code']==='33','Later supplier edits cannot alter frozen state-code evidence.');
    $line = $records[$frozenId]['tax_lines'][0];
    $expect($line['uqc']==='KGS' && $line['uqc_quantity']==='0.125', 'Changing the catalogue cannot reclassify a frozen unit.');
    $browserPending = $pending;
    $browserPending['items'][0] += ['gst_uqc'=>'KGS','gst_qty_per_unit'=>'0.125'];
    $browserUnits = $service->persist($browserPending, 'pay_browser_units', [], $ports);
    $expect($records[$browserUnits]['tax_lines'][0]['uqc']==='KGS', 'Native pending order preserves its configured unit independently of older rate calculation.');
    $oldPending = $service->persist($pending, 'pay_browser_legacy_units', [], $ports);
    $expect(!isset($records[$oldPending]['tax_lines'][0]['uqc']), 'New catalogue units cannot backfill an in-flight legacy order.');
    $catalogue=[];
    $missingId=$service->persist($frozen,'pay_missing_frozen',[],$ports);
    $expect($records[$missingId]['status']==='on_hold' && $records[$missingId]['fulfillment_review_required']===true,
        'A product removed after payment must require fulfillment review.');
    $expect($records[$missingId]['tax_review_required']===false && isset($records[$missingId]['invoice_number']),
        'Complete frozen tax evidence can preserve an invoice after catalogue removal.');
    $expect($records[$missingId]['tax_lines'][0]['uqc_quantity']==='0.125', 'Frozen quantity survives product removal.');
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
