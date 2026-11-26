<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
putenv('BAPX_TEST_MODE=1');require dirname(__DIR__).'/app/bootstrap.php';require __DIR__.'/fixtures/remote-checkout.php';
use App\Services\{RemoteCartException,RemoteCheckoutService,TaxService};
set_exception_handler(static function(Throwable $error):never {fwrite(STDERR,'Checkout test failed: '.$error->getMessage().' at '.basename($error->getFile()).':'.$error->getLine()."\n");exit(1);});
$checks=0;
function checkoutCheck(bool $condition,string $message):void {global $checks;$checks++;if(!$condition)throw new RuntimeException($message);}
function checkoutError(callable $call,string $code,int $status=400):void {
    try{$call();}catch(RemoteCartException $error){checkoutCheck($error->errorCode===$code&&$error->httpStatus===$status,'Wrong safe error for '.$code);return;}
    throw new RuntimeException('Missing safe error for '.$code);
}
function checkoutCart(RemoteCheckoutFixture $f):array {
    $cart=$f->cartFixture->service()->shoppingCart(['operation'=>'create']);
    $f->cartFixture->service()->shoppingCart(['operation'=>'set_quantity','cartToken'=>$cart['cartToken'],
        'expectedRevision'=>0,'slug'=>'sri-varahi-amman-ring','quantity'=>1]);return $cart;
}
function checkoutQuote(RemoteCheckoutFixture $f,?array $delivery=null,string $coupon=''):array {
    $cart=checkoutCart($f);$review=$f->service()->reviewCheckout(['cartToken'=>$cart['cartToken'],'expectedRevision'=>1,
        'delivery'=>$delivery??RemoteCheckoutFixture::delivery(),'couponCode'=>$coupon]);
    return [$cart,$review,['cartToken'=>$cart['cartToken'],'expectedRevision'=>$review['cartRevision'],'quoteId'=>$review['quote']['id'],'confirm'=>true]];
}
function checkoutPrivateAbsent(array $result):void {
    $text=json_encode($result,JSON_THROW_ON_ERROR);
    foreach(['synthetic-shopper@example.test','12 Synthetic Test Street','9876543210','synthetic-provider-private',
        'synthetic-private@provider','fixture-key-never','fixture-webhook-never','PRIVATE_CART_FIXTURE','owner_note'] as $private) {
        checkoutCheck(!str_contains($text,$private),'Checkout output exposed a private fixture field.');
    }
}
$f=new RemoteCheckoutFixture();
try {
    $service=$f->service();$cart=checkoutCart($f);$token=$cart['cartToken'];$id=substr(explode('.',$token)[0],5);
    $initial=$service->reviewCheckout(['cartToken'=>$token,'expectedRevision'=>1]);
    checkoutCheck($initial['stage']==='details_required'&&$initial['quote']===null&&count($initial['missingFields'])===7,'Incomplete details created a quote.');
    checkoutCheck($initial['totals']['itemsPaise']===50075&&$initial['totals']['shippingPaise']===0&&$initial['totals']['tax']===null,'Initial authoritative paise/shipping estimate changed.');
    checkoutCheck($f->attempts===[]&&$f->gatewayCalls===[]&&$f->orders===[],'Review started provider/order work.');
    checkoutCheck(array_keys(RemoteCheckoutService::reviewInputSchema()['properties'])===['cartToken','expectedRevision','delivery','couponCode'],'Review schema misses atomic delivery/coupon input.');
    checkoutError(fn()=>$service->reviewCheckout(['cartToken'=>$token,'expectedRevision'=>1,'role'=>'admin']),'invalid_arguments');
    foreach(['pincode'=>'12','email'=>'not-an-email','phone'=>'abc','state'=>'Unsupported State','gstin'=>'wrong'] as $key=>$value) {
        $delivery=RemoteCheckoutFixture::delivery();$delivery[$key]=$value;
        checkoutError(fn()=>$service->reviewCheckout(['cartToken'=>$token,'expectedRevision'=>1,'delivery'=>$delivery]),'invalid_delivery');
    }
    $delivery=RemoteCheckoutFixture::delivery();$delivery['accountId']='owner';
    checkoutError(fn()=>$service->reviewCheckout(['cartToken'=>$token,'expectedRevision'=>1,'delivery'=>$delivery]),'invalid_arguments');
    checkoutError(fn()=>$service->reviewCheckout(['cartToken'=>$token,'expectedRevision'=>1,'couponCode'=>str_repeat('X',41)]),'invalid_coupon');
    checkoutError(fn()=>$service->reviewCheckout(['cartToken'=>$token,'expectedRevision'=>1,'couponCode'=>'MISSING']),'coupon_unavailable',409);
    $review=$service->reviewCheckout(['cartToken'=>$token,'expectedRevision'=>1,'delivery'=>RemoteCheckoutFixture::delivery()]);
    checkoutCheck($review['stage']==='review_ready'&&$review['cartRevision']===2&&$review['deliveryRegion']==='Tamil Nadu','Atomic delivery review did not advance exactly one revision.');
    checkoutCheck($review['deliveryComplete']&&$review['missingFields']===[]&&!$review['couponApplied'],'Delivery completion or coupon projection is incorrect.');
    checkoutCheck(strtotime($review['quote']['expiresAt'])-$f->cartFixture->now===600,'Quote is not bounded to ten minutes.');
    checkoutCheck($review['totals']['totalPaise']===50075&&$review['totals']['taxIncluded']&&$review['totals']['tax']['igstPaise']===0,'Reviewed total or intrastate tax is wrong.');
    checkoutPrivateAbsent($review);
    $same=$service->reviewCheckout(['cartToken'=>$token,'expectedRevision'=>2,'delivery'=>RemoteCheckoutFixture::delivery()]);
    checkoutCheck($same['quote']===$review['quote']&&$same['cartRevision']===2,'Same review replaced its quote after MySQL-like JSON normalization.');
    $f->coupons=[['id'=>'coupon-fixture','code'=>'SAVE10','active'=>true,'discount_type'=>'fixed','discount_value'=>10]];
    $coupon=$service->reviewCheckout(['cartToken'=>$token,'expectedRevision'=>2,'couponCode'=>'save10']);
    checkoutCheck($coupon['cartRevision']===3&&$coupon['couponApplied']&&$coupon['totals']['discountPaise']===1000&&$coupon['totals']['totalPaise']===49075,'Canonical coupon discount was not applied atomically.');
    checkoutCheck(!str_contains(json_encode($coupon),'SAVE10'),'Coupon code leaked into the model response.');
    $private=$f->cartFixture->carts[$id]['checkout'];
    $tax=(new TaxService())->snapshot($private['pending_order']['items'],10,'Tamil Nadu',$private['pending_order']['tax_settings']);
    checkoutCheck($coupon['totals']['tax']['gstPaise']===(int)round($tax['tax_total']*100),'Quote GST ignored the coupon discount.');
    checkoutCheck(count($private['pending_order']['tax_settings'])===8&&is_float($private['pending_order']['tax_settings']['default_gst_rate'])===false,'Stored fixture did not exercise whole-number JSON normalization.');
    $clear=$service->reviewCheckout(['cartToken'=>$token,'expectedRevision'=>3,'couponCode'=>'']);
    checkoutCheck($clear['cartRevision']===4&&!$clear['couponApplied']&&$clear['totals']['totalPaise']===50075,'Explicit empty coupon did not clear it.');
    checkoutError(fn()=>$service->preparePayment(['cartToken'=>$token,'expectedRevision'=>4,'quoteId'=>$clear['quote']['id'],'confirm'=>false]),'confirmation_required');
    checkoutError(fn()=>$service->preparePayment(['cartToken'=>$token,'expectedRevision'=>3,'quoteId'=>$clear['quote']['id'],'confirm'=>true]),'quote_expired',409);
    checkoutError(fn()=>$service->preparePayment(['cartToken'=>$token,'expectedRevision'=>4,'quoteId'=>$review['quote']['id'],'confirm'=>true]),'quote_expired',409);
    $f->advance(601);
    checkoutError(fn()=>$service->preparePayment(['cartToken'=>$token,'expectedRevision'=>4,'quoteId'=>$clear['quote']['id'],'confirm'=>true]),'quote_expired',409);
    checkoutCheck($f->attempts===[]&&$f->gatewayCalls===[],'Invalid confirmation/expired quote created a durable attempt.');
}finally{$f->close();}

foreach([0.99,1.00] as $price) {
    $f=new RemoteCheckoutFixture();
    try {
        $products=$f->cartFixture->records();$products[0]['price']=$price;$f->cartFixture->setProducts($products);
        if($price<1) {
            checkoutError(fn()=>checkoutQuote($f),'checkout_amount',409);
            checkoutCheck($f->attempts===[]&&$f->gatewayCalls===[],'Sub-INR1 total reached durable/provider creation.');
        }else {
            [$cart,$review,$args]=checkoutQuote($f);$prepared=$f->service()->preparePayment($args);
            checkoutCheck($review['totals']['totalPaise']===100&&$prepared['amountPaise']===100&&$prepared['stage']==='payment_ready','Exactly100paise was rejected or rounded.');
        }
    }finally{$f->close();}
}

// Changed price, visibility, tax, coupon eligibility and missing configuration all fail before payment creation.
foreach(['price','visibility','tax','coupon','configuration','legacy_credentials'] as $change) {
    $f=new RemoteCheckoutFixture();
    try {
        if($change==='coupon')$f->coupons=[['id'=>'limited','code'=>'ONCE','active'=>true,'discount_type'=>'fixed','discount_value'=>1,'usage_limit'=>1]];
        [$cart,$review,$args]=checkoutQuote($f,null,$change==='coupon'?'ONCE':'');
        if(in_array($change,['price','visibility'],true)) {
            $products=$f->cartFixture->records();$products[0][$change==='price'?'price':'status']=$change==='price'?501.15:'hidden';$f->cartFixture->setProducts($products);
        }elseif($change==='tax')$f->settings['default_gst_rate']=12;
        elseif($change==='coupon')$f->coupons[0]['active']=false;
        elseif($change==='legacy_credentials') {
            $f->secrets['razorpay_key_id']=$f->secrets['razorpay_test_key_id'];$f->secrets['razorpay_key_secret']=$f->secrets['razorpay_test_key_secret'];
            unset($f->secrets['razorpay_test_key_id'],$f->secrets['razorpay_test_key_secret']);
            $unavailable=$f->service()->reviewCheckout(['cartToken'=>$cart['cartToken'],'expectedRevision'=>$review['cartRevision']]);
            checkoutCheck(!$unavailable['payment']['available']&&$unavailable['payment']['mode']==='unavailable','Raw legacy-only credential input falsely advertised payment availability.');
        }else unset($f->secrets['razorpay_test_webhook_secret']);
        $code=match($change){'visibility'=>'checkout_unavailable','coupon'=>'coupon_unavailable','configuration','legacy_credentials'=>'payment_unavailable',default=>'quote_changed'};
        checkoutError(fn()=>$f->service()->preparePayment($args),$code,in_array($change,['configuration','legacy_credentials'],true)?503:409);
        checkoutCheck($f->attempts===[]&&$f->gatewayCalls===[]&&$f->orders===[],'Changed quote/configuration caused an external effect.');
    }finally{$f->close();}
}

$f=new RemoteCheckoutFixture();
try {
    $delivery=RemoteCheckoutFixture::delivery();$delivery['state']='Karnataka';
    [$cart,$review,$args]=checkoutQuote($f,$delivery);$originalSession=$_SESSION;
    $_SESSION=['cart'=>[['slug'=>'unrelated-browser-cart','qty'=>4]],'user'=>['role'=>'admin','email'=>'owner-private@example.test']];
    $prepared=$f->service()->preparePayment($args);$attemptId=$prepared['attemptId'];$attempt=$f->attempts[$attemptId];
    checkoutCheck($prepared['stage']==='payment_ready'&&count($f->attempts)===1&&$f->orders===[],'Preparing created an order or duplicate attempt.');
    checkoutCheck(count(array_filter($f->gatewayCalls,fn(array $call):bool=>$call['operation']==='create_link'))===1,'Provider creation did not happen exactly once.');
    checkoutCheck($f->gatewayCalls[0]['payload']['accept_partial']===false&&$f->gatewayCalls[0]['payload']['notify']===['sms'=>false,'email'=>false]
        &&$f->gatewayCalls[0]['payload']['reminder_enable']===false&&!isset($f->gatewayCalls[0]['payload']['customer']),'Provider setup sends notifications or personal delivery data.');
    checkoutCheck(strtotime($prepared['statusExpiresAt'])-$f->cartFixture->now===604800&&strtotime($prepared['paymentExpiresAt'])-$f->cartFixture->now===1800,'Status/payment deadlines are conflated.');
    checkoutCheck(str_starts_with($prepared['paymentUrl'],'https://cart.example.test/checkout/payment?ticket=pay_')
        &&!str_contains($prepared['paymentUrl'],$cart['cartToken'])&&!str_contains($prepared['paymentUrl'],$prepared['paymentToken']),'Payment URL is not a separate merchant capability.');
    checkoutCheck(!str_contains(json_encode($attempt),explode('.',$prepared['paymentToken'])[1]),'Raw status capability was persisted.');
    checkoutPrivateAbsent($prepared);
    $get=$f->cartFixture->service()->shoppingCart(['operation'=>'get','cartToken'=>$cart['cartToken']]);
    checkoutCheck($get['status']==='checkout_locked'&&$get['checkout']['paymentToken']===$prepared['paymentToken']&&$get['checkout']['attemptId']===$attemptId,'Lost-response recovery from cart capability failed.');
    checkoutCheck(str_contains($get['notice'],'status receipt')&&!str_contains($get['notice'],'Nothing is'),'Locked cart fabricated an unpaid/no-order status.');
    checkoutError(fn()=>$f->cartFixture->service()->shoppingCart(['operation'=>'set_quantity','cartToken'=>$cart['cartToken'],
        'expectedRevision'=>$get['revision'],'slug'=>'sri-varahi-amman-ring','quantity'=>0]),'checkout_locked',409);
    checkoutError(fn()=>$f->service()->reviewCheckout(['cartToken'=>$cart['cartToken'],'expectedRevision'=>$get['revision'],'delivery'=>RemoteCheckoutFixture::delivery()]),'checkout_locked',409);
    $resume=$f->service()->reviewCheckout(['cartToken'=>$cart['cartToken'],'expectedRevision'=>$get['revision']]);
    checkoutCheck($resume['stage']==='payment_ready'&&$resume['cart']['checkout']['paymentToken']===$prepared['paymentToken'],'Review did not recover an existing attempt.');
    $replay=$f->service()->preparePayment($args);
    checkoutCheck($replay['attemptId']===$attemptId&&$replay['paymentToken']===$prepared['paymentToken']&&count($f->links)===1,'Exact preparation replay made a second payment.');
    parse_str(parse_url($prepared['paymentUrl'],PHP_URL_QUERY),$query);$ops=count($f->gatewayCalls);
    $handoff=$f->service()->paymentHandoff($query['ticket']);
    checkoutCheck(count($f->gatewayCalls)===$ops&&$f->orders===[]&&str_starts_with($handoff['providerUrl'],'https://rzp.io/'),'Payment page GET mutated provider/order state.');
    $payment=$handoff;$error=null;ob_start();require app_path('views/public/agent-payment.php');$html=ob_get_clean();
    checkoutCheck(str_contains($html,'Continue to Razorpay')&&!str_contains($html,'<script')&&!str_contains($html,'<iframe')&&!str_contains($html,'9876543210'),'Merchant payment page embedded payment scripts or delivery data.');
    checkoutCheck($_SESSION['cart']===[['slug'=>'unrelated-browser-cart','qty'=>4]],'Checkout affected a signed-in browser cart.');$_SESSION=$originalSession;
    checkoutError(fn()=>$f->service()->checkoutStatus(['paymentToken'=>$cart['cartToken']]),'invalid_capability');
    checkoutError(fn()=>$f->service()->checkoutStatus(['paymentToken'=>'payment_'.$attemptId.'.'.str_repeat('0',64)]),'checkout_not_found',404);
    $other=checkoutQuote($f);$otherPrepared=$f->service()->preparePayment($other[2]);
    checkoutError(fn()=>$f->service()->checkoutStatus(['paymentToken'=>'payment_'.$attemptId.'.'.explode('.',$otherPrepared['paymentToken'])[1]]),'checkout_not_found',404);
    $f->markPaid($attemptId);$f->advance(6);$status=$f->service()->checkoutStatus(['paymentToken'=>$prepared['paymentToken']]);
    checkoutCheck($status['paymentStatus']==='captured'&&$status['orderStatus']==='saved'&&count($f->orders)===1,'Verified payment did not save exactly one paid order.');
    $order=$f->orders[$status['orderReference']];
    checkoutCheck($order['total']===500.75&&$order['supply_type']==='interstate'&&$order['igst_total']>0&&!$order['tax_review_required']&&isset($order['invoice_number']),'Shared verified save lost frozen GST/invoice facts.');
    checkoutCheck($order['supplier']['state_code']==='33'&&$order['place_of_supply_code']==='29','Shared checkout freezes official supplier and destination codes.');
    checkoutCheck($order['razorpay_payment_link_id']===$attempt['provider_link_id'],'Paid order lost its verified provider link identity.');
    checkoutCheck(!isset($f->attempts[$attemptId]['pending_order']),'Completed status receipt retained a second delivery snapshot.');
    checkoutPrivateAbsent($status);
    $again=$f->service()->checkoutStatus(['paymentToken'=>$prepared['paymentToken']]);
    checkoutCheck($again===$status&&count($f->orders)===1,'Status replay duplicated or changed the paid order.');
}finally{$f->close();}

// Uncertain POST is recovered through a unique provider reference, without a second create.
$f=new RemoteCheckoutFixture();
try {
    $f->timeoutAfterCreate=true;[$cart,$review,$args]=checkoutQuote($f);$prepared=$f->service()->preparePayment($args);
    checkoutCheck($prepared['stage']==='payment_uncertain'&&!isset($prepared['paymentUrl'])&&count($f->links)===1,'Lost provider response was assumed successful or repeated.');
    $f->advance(6);$recovered=$f->service()->preparePayment($args);
    checkoutCheck($recovered['stage']==='payment_ready'&&$recovered['attemptId']===$prepared['attemptId'],'Unique-reference lookup did not recover uncertain creation.');
    checkoutCheck(count(array_filter($f->gatewayCalls,fn(array $call):bool=>$call['operation']==='create_link'))===1,'Uncertain creation was blindly retried.');
    $export=$f->exportState();$f->close();$f=new RemoteCheckoutFixture($export);
    $cartNow=$f->cartFixture->service()->shoppingCart(['operation'=>'get','cartToken'=>$cart['cartToken']]);
    checkoutCheck($cartNow['checkout']['paymentToken']===$prepared['paymentToken'],'Process reload lost recovery capability.');
    $f->markPaid($prepared['attemptId'],['currency'=>'USD']);$f->advance(6);
    $bad=$f->service()->checkoutStatus(['paymentToken'=>$prepared['paymentToken']]);
    checkoutCheck($bad['paymentStatus']!=='captured'&&$f->orders===[],'A non-INR provider payment created an order.');
    $f->markPaid($prepared['attemptId'],['status'=>'authorized','captured'=>false]);$f->advance(6);
    $bad=$f->service()->checkoutStatus(['paymentToken'=>$prepared['paymentToken']]);
    checkoutCheck($bad['paymentStatus']!=='captured'&&$f->orders===[],'An authorization was treated as captured.');
    $f->markPaid($prepared['attemptId'],['amount'=>1]);$f->advance(6);
    $f->service()->checkoutStatus(['paymentToken'=>$prepared['paymentToken']]);
    checkoutCheck($f->orders===[],'A mismatched captured amount created an order.');
    $f->markPaid($prepared['attemptId']);$f->saveThenFailOnce=true;$f->advance(6);
    $pending=$f->service()->checkoutStatus(['paymentToken'=>$prepared['paymentToken']]);
    checkoutCheck($pending['paymentStatus']==='captured'&&$pending['orderStatus']==='save_pending'&&count($f->orders)===1,'Lost save response was misreported or lost payment evidence.');
    $f->advance(6);$saved=$f->service()->checkoutStatus(['paymentToken'=>$prepared['paymentToken']]);
    checkoutCheck($saved['orderStatus']==='saved'&&count($f->orders)===1,'Idempotent shared order save did not recover.');
}finally{$f->close();}

$f=new RemoteCheckoutFixture();
try {
    [$cart,$review,$args]=checkoutQuote($f);$f->gatewayOffline=true;$prepared=$f->service()->preparePayment($args);
    $f->gatewayOffline=false;$f->advance(1900);$status=$f->service()->checkoutStatus(['paymentToken'=>$prepared['paymentToken']]);
    checkoutCheck($status['paymentStatus']==='unknown'&&$status['orderStatus']==='not_created','An empty reference lookup fabricated provider expiry.');
    checkoutCheck($f->attempts[$prepared['attemptId']]['state']==='uncertain'&&$f->links===[],'Unresolved provider creation was removed or silently retried.');
}finally{$f->close();}

// Signed completion works after the browser closes and the 24-hour cart disappears.
$f=new RemoteCheckoutFixture();
try {
    $products=$f->cartFixture->records();
    $products[0] += ['gst_uqc'=>'KGS','gst_qty_per_unit'=>'0.125'];
    $f->cartFixture->setProducts($products);
    [$cart,$review,$args]=checkoutQuote($f);$prepared=$f->service()->preparePayment($args);$id=$prepared['attemptId'];
    $body=json_encode(['event'=>'payment_link.paid','payload'=>['payment_link'=>['entity'=>['reference_id'=>'ac_'.$id]]]],JSON_THROW_ON_ERROR);
    $signature=hash_hmac('sha256',$body,$f->secrets['razorpay_test_webhook_secret']);$before=count($f->operations);
    checkoutError(fn()=>$f->service()->receiveWebhook($body,str_repeat('0',64)),'invalid_signature',401);
    checkoutCheck(count($f->operations)===$before,'Invalid HMAC reached private persistence.');
    checkoutError(fn()=>$f->service()->receiveWebhook($body.' ',$signature),'invalid_signature',401);
    $f->advance(6);checkoutError(fn()=>$f->service()->receiveWebhook($body,$signature),'notification_pending',503);
    checkoutCheck($f->orders===[],'Signed paid payload bypassed authenticated provider verification.');
    $f->markPaid($id);$f->advance(86400);$f->cartFixture->carts=[];
    $products=$f->cartFixture->records();array_shift($products);$f->cartFixture->setProducts($products);
    $f->service()->receiveWebhook($body,$signature);
    checkoutCheck(count($f->orders)===1&&$f->attempts[$id]['order_status']==='saved','Signed callback could not finish after cart cleanup.');
    $order=reset($f->orders);
    checkoutCheck($order['fulfillment_review_required']&&!$order['tax_review_required']&&isset($order['invoice_number']),'Deleted product lost frozen invoice facts or bypassed fulfillment hold.');
    $line=$order['tax_lines'][0];
    checkoutCheck($line['uqc']==='KGS'&&$line['uqc_quantity']==='0.125','Remote quote must freeze the unit through signed payment after product removal.');
    $f->service()->receiveWebhook($body,$signature);
    checkoutCheck(count($f->orders)===1,'Duplicate webhook made a duplicate order.');
    $f->advance(604800);
    checkoutError(fn()=>$f->service()->checkoutStatus(['paymentToken'=>$prepared['paymentToken']]),'checkout_expired',410);
}finally{$f->close();}

$f=new RemoteCheckoutFixture();
try {
    [$cart,$review,$args]=checkoutQuote($f);$prepared=$f->service()->preparePayment($args);$f->markPaid($prepared['attemptId']);$f->advance(31);
    $summary=$f->service()->reconcilePending(20);
    checkoutCheck($summary===['checked'=>1,'ordersSaved'=>1]&&count($f->orders)===1,'Bounded reconciliation did not complete a missed callback.');
    checkoutCheck(array_keys($summary)===['checked','ordersSaved'],'Reconciler printed capabilities or personal data.');
    $f->advance(1800);parse_str(parse_url($prepared['paymentUrl'],PHP_URL_QUERY),$query);
    checkoutError(fn()=>$f->service()->paymentHandoff($query['ticket']),'checkout_expired',410);
    checkoutCheck($f->service()->checkoutStatus(['paymentToken'=>$prepared['paymentToken']])['orderStatus']==='saved','Payment link expiry incorrectly expired status access.');
}finally{$f->close();}

echo 'Remote checkout quote, coupon/GST, confirmation, durable recovery, signed completion and PII boundaries: '.$checks." checks passed.\n";
