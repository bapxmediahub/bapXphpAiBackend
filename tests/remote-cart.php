<?php
declare(strict_types=1);
use App\Services\{CartService,RemoteCartException,RemoteCartService,TaxService};
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
putenv('BAPX_TEST_MODE=1');
require dirname(__DIR__).'/app/bootstrap.php';
require __DIR__.'/fixtures/remote-cart.php';
$checks=0;
function cartCheck(bool $condition,string $message):void {
    global $checks;$checks++;if(!$condition)throw new RuntimeException($message);
}
function cartFailure(callable $call,string $code,int $status=400):void {
    try{$call();}catch(RemoteCartException $error){
        cartCheck($error->errorCode===$code&&$error->httpStatus===$status,'Wrong safe cart error: '.$code);return;
    }
    throw new RuntimeException('Missing safe cart error: '.$code);
}
$fixture=new RemoteCartFixture();$service=$fixture->service();
$ring='sri-varahi-amman-ring';$pendant='varahi-amman-pendant';
$create=static fn():array=>$service->shoppingCart(['operation'=>'create']);
$get=static fn(string $token):array=>$service->shoppingCart(['operation'=>'get','cartToken'=>$token]);
$set=static fn(string $token,int $revision,string $slug,int $quantity):array=>$service->shoppingCart([
    'operation'=>'set_quantity','cartToken'=>$token,'expectedRevision'=>$revision,'slug'=>$slug,'quantity'=>$quantity]);
$prepare=static fn(string $token,int $revision):array=>$service->prepareCheckout(['cartToken'=>$token,'expectedRevision'=>$revision]);
$ticket=static function(array $checkout):string {parse_str(parse_url($checkout['checkoutUrl'],PHP_URL_QUERY),$query);return $query['ticket'];};
$binding=hash('sha256','fixture-browser-a');$otherBinding=hash('sha256','fixture-browser-b');
try {
    $originalSession=$_SESSION;$_SESSION=['cart'=>[['slug'=>'browser-only','qty'=>7]],'user'=>['role'=>'admin','email'=>'private@example.test']];
    $a=$create();$b=$create();$token=$a['cartToken'];
    cartCheck($a['items']===[]&&$a['revision']===0&&$a['status']==='open'&&$a['estimatedTotalInr']===0.0,'New cart is not empty.');
    cartCheck($token!==$b['cartToken']&&preg_match('/^cart_[a-f0-9]{32}\.[a-f0-9]{64}$/D',$token)===1,'Capabilities are not distinct opaque random values.');
    cartCheck(strtotime($a['expiresAt'])-$fixture->now===RemoteCartService::CART_TTL,'Cart lifetime is not fixed24hours.');
    cartCheck($_SESSION['cart']===[['slug'=>'browser-only','qty'=>7]],'Remote creation changed the browser cart.');
    $stored=reset($fixture->carts);$parts=explode('.',$token);$id=substr($parts[0],5);
    cartCheck(!str_contains(json_encode($stored),$parts[1])&&hash_equals($stored['capability_hash'],hash('sha256',$parts[1])),'Persistent record contains a raw capability.');
    cartCheck(!isset($get($token)['cartToken']),'Get needlessly re-exposed the cart token.');
    $otherSecret=explode('.',$b['cartToken'])[1];
    cartFailure(fn()=>$get($parts[0].'.'.$otherSecret),'cart_not_found',404);
    cartFailure(fn()=>$get('cart_'.str_repeat('0',32).'.'.$parts[1]),'cart_not_found',404);
    cartFailure(fn()=>$get('cart_'.$id),'invalid_capability');
    cartFailure(fn()=>$service->shoppingCart(['operation'=>'create','role'=>'admin']),'invalid_arguments');
    cartFailure(fn()=>$service->shoppingCart(['operation'=>'get','cartToken'=>$token,'user_id'=>'any']),'invalid_arguments');
    cartFailure(fn()=>$prepare($token,0),'checkout_unavailable',409);
    $a=$set($token,0,$ring,2);
    cartCheck($a['revision']===1&&$a['items'][0]['quantity']===2&&$a['itemCount']===2,'Absolute set did not create exactly two units.');
    cartCheck($a['items'][0]['unitPriceInr']===500.75&&$a['estimatedTotalInr']===1001.50,'Fractional INR was truncated.');
    cartCheck($a['items'][0]['productUrl']==='https://cart.example.test/product/'.$ring
        &&$a['items'][0]['imageUrl']==='https://cart.example.test/assets/images/ring.webp','Cart product/image links are not canonical official URLs.');
    cartCheck(!str_contains(json_encode($a),'PRIVATE_')&&!str_contains(json_encode($a),'private@example.test'),'Private product/account fields leaked.');
    $retry=$set($token,0,$ring,2);
    cartCheck($retry['revision']===1&&$retry['items'][0]['quantity']===2&&$retry['replayed'],'Lost-response replay incremented quantity.');
    cartFailure(fn()=>$set($token,0,$ring,3),'cart_conflict',409);
    $same=$set($token,1,$ring,2);cartCheck($same['revision']===1&&$same['replayed'],'Same absolute quantity changed revision.');
    $a=$set($token,1,$pendant,1);
    cartCheck($a['items'][1]['unitPriceInr']===299.95&&!isset($a['items'][1]['imageUrl']),'Current offer or foreign-media exclusion failed.');
    cartFailure(fn()=>$set($token,0,$ring,2),'cart_conflict',409);
    $browserItems=(new CartService($fixture->products()))->resolve([['slug'=>$ring,'qty'=>2],['slug'=>$pendant,'qty'=>1]]);
    $summary=TaxService::cartSummary($browserItems,[]);
    cartCheck(CartService::total($browserItems)===$a['estimatedTotalInr']&&$summary['total']===$a['estimatedTotalInr'],'Browser checkout and remote estimate disagree in paise.');
    $savedProducts=$fixture->records();$fractional=$savedProducts;$fractional[0]['price']=1.15;$fixture->setProducts($fractional);
    $fractionalItems=(new CartService($fixture->products()))->resolve([['slug'=>$ring,'qty'=>1]]);
    $renderMoney=static function(string$view,array$items):string{
        $total=CartService::total($items);$itemCount=1;$gstAmount=0;$settings=[];$addresses=[];
        $razorpayReady=true;$secrets=['razorpay_key_id'=>'fixture-public-test-key'];
        ob_start();require app_path('views/public/'.$view.'.php');return ob_get_clean();
    };
    $cartHtml=$renderMoney('cart',$fractionalItems);$checkoutHtml=$renderMoney('checkout',$fractionalItems);
    cartCheck(str_contains($cartHtml,'data-unit-price="1.15"')&&str_contains($cartHtml,'data-line-total>₹1.15'),'Cart markup recomputed or truncated resolved unit/line prices.');
    cartCheck(str_contains($checkoutHtml,"amount: '115'")&&str_contains($checkoutHtml,'checkout-item__price">₹1.15'),'Checkout markup truncated a fractional rupee gateway amount.');
    $fixture->setProducts($savedProducts);
    $expiredOffer=$fixture->knowledge()->products('',0,1,['expired-offer-ring'])['products'][0];
    cartCheck($expiredOffer['priceInr']===199.99,'Expired offer is still applied.');
    foreach(['private-ring','sold-out-ring','missing-product']as$slug)cartFailure(fn()=>$set($token,2,$slug,1),'product_unavailable',409);
    foreach([-1,100]as$quantity)cartFailure(fn()=>$set($token,2,$ring,$quantity),'invalid_quantity');
    foreach(['../admin','%2e%2e','ring/private']as$slug)cartFailure(fn()=>$set($token,2,$slug,1),'invalid_quantity');
    foreach([1.5,'1',true]as$quantity)cartFailure(fn()=>$service->shoppingCart(['operation'=>'set_quantity','cartToken'=>$token,'slug'=>$ring,'quantity'=>$quantity,'expectedRevision'=>2]),'invalid_quantity');
    cartFailure(fn()=>$service->shoppingCart(['operation'=>'set_quantity','cartToken'=>$token,'slug'=>$ring,'quantity'=>1,'expectedRevision'=>'2']),'invalid_revision');
    $a=$set($token,2,'தமிழ் pendant',1);
    cartCheck($a['items'][2]['path']==='/product/'.rawurlencode('தமிழ் pendant'),'Valid Unicode selection failed.');
    $a=$set($token,3,'தமிழ் pendant',0);cartCheck(count($a['items'])===2&&$a['revision']===4,'Zero quantity did not remove.');
    $originalProducts=$fixture->records();$changed=$originalProducts;
    foreach($changed as&$product)if($product['slug']===$ring)$product['status']='hidden';unset($product);
    $fixture->setProducts($changed);$unavailable=$get($token);
    cartCheck($unavailable['unavailableSlugs']===[$ring]&&$unavailable['estimatedTotalInr']===null&&count($unavailable['items'])===1,'Hidden products became stale cart metadata or a partial checkout quote.');
    cartFailure(fn()=>$prepare($token,4),'checkout_unavailable',409);
    $fixture->setProducts($originalProducts);
    $checkout=$prepare($token,4);$reviewTicket=$ticket($checkout);
    $fixture->carts[$id]['lines']=array_map(static fn(array$line):array=>['qty'=>$line['qty'],'slug'=>$line['slug']],$fixture->carts[$id]['lines']);
    $receiptBefore=$fixture->carts[$id]['handoff'];$noOp=$set($token,4,$ring,2);
    cartCheck($noOp['revision']===4&&$noOp['replayed']&&$fixture->carts[$id]['handoff']===$receiptBefore,
        'MySQL-style JSON key ordering turned a no-op into a change or removed its review ticket.');
    cartCheck(!str_contains($checkout['checkoutUrl'],$parts[1])&&!str_contains(json_encode($checkout),'cartToken'),'Checkout URL/result exposed the persistent cart capability.');
    cartCheck(strtotime($checkout['expiresAt'])-$fixture->now===900,'Checkout review lifetime is not15minutes.');
    $again=$prepare($token,4);cartCheck($again['checkoutUrl']===$checkout['checkoutUrl']&&$again['replayed'],'Pending checkout retry minted a different ticket.');
    $before=$fixture->carts;$preview=$service->previewCheckout($reviewTicket,$binding);
    cartCheck($fixture->carts===$before&&$_SESSION['cart']===[['slug'=>'browser-only','qty'=>7]],'Review GET mutated a stored or browser cart.');
    cartFailure(fn()=>$service->claimCheckout($reviewTicket,$binding,str_repeat('0',64)),'quote_changed',409);
    $changed=$originalProducts;$changed[0]['price']=501.25;$fixture->setProducts($changed);
    cartFailure(fn()=>$service->claimCheckout($reviewTicket,$binding,$preview['quote']),'quote_changed',409);
    $fresh=$service->previewCheckout($reviewTicket,$binding);
    $claimed=$service->claimCheckout($reviewTicket,$binding,$fresh['quote']);
    $claimedLines=array_map(static fn(array$line):array=>['slug'=>$line['slug'],'qty'=>$line['qty']],$claimed['lines']);
    cartCheck($claimedLines===[['slug'=>$ring,'qty'=>2],['slug'=>$pendant,'qty'=>1]]&&!$claimed['replayed'],'Claim did not yield the reviewed exact cart.');
    cartCheck($_SESSION['cart']===[['slug'=>'browser-only','qty'=>7]],'Service claim bypassed the explicit browser controller import.');
    cartCheck($service->claimCheckout($reviewTicket,$binding,$fresh['quote'])['replayed'],'Same-browser interrupted claim cannot recover.');
    cartFailure(fn()=>$service->previewCheckout($reviewTicket,$otherBinding),'checkout_already_used',409);
    cartFailure(fn()=>$service->claimCheckout($reviewTicket,$otherBinding,$fresh['quote']),'checkout_already_used',409);
    cartCheck($get($token)['status']==='handed_off','Handed-off cart did not retain its read-only receipt.');
    cartFailure(fn()=>$set($token,4,$ring,3),'cart_handed_off',409);
    cartFailure(fn()=>$prepare($token,4),'cart_handed_off',409);
    $fixture->setProducts($originalProducts);
    $c=$create();$ct=$c['cartToken'];$set($ct,0,$ring,1);$link=$prepare($ct,1);$oldTicket=$ticket($link);
    $fixture->now+=901;
    cartFailure(fn()=>$service->previewCheckout($oldTicket,$binding),'checkout_expired',410);
    $renewed=$prepare($ct,1);cartCheck($renewed['checkoutUrl']!==$link['checkoutUrl']&&!$renewed['replayed'],'Expired review ticket did not renew separately.');
    cartFailure(fn()=>$service->previewCheckout($oldTicket,$binding),'cart_not_found',404);
    $set($ct,1,$ring,2);
    cartFailure(fn()=>$service->previewCheckout($ticket($renewed),$binding),'cart_not_found',404);
    $many=$create();$manyToken=$many['cartToken'];
    for($i=1;$i<=20;$i++)$many=$set($manyToken,$many['revision'],'item-'.$i,1);
    cartCheck(count($many['items'])===20&&$many['itemCount']===20,'Twenty-line cart bound is not reachable.');
    cartFailure(fn()=>$set($manyToken,20,'item-21',1),'cart_limit',409);
    $removed=$set($manyToken,20,'item-1',0);$many=$set($manyToken,$removed['revision'],'item-21',99);
    cartCheck(count($many['items'])===20&&$many['itemCount']===118,'Removing an item did not release capacity.');
    $fixture->shopEnabled(false);
    cartFailure($create,'shop_unavailable',503);cartFailure(fn()=>$get($ct),'shop_unavailable',503);
    $fixture->shopEnabled(true);
    $fixture->now=strtotime($a['expiresAt']);
    cartFailure(fn()=>$get($token),'cart_expired',410);
    cartFailure(fn()=>$service->previewCheckout($reviewTicket,$binding),'cart_expired',410);
    $schema=RemoteCartService::cartOutputSchema();
    cartCheck($schema['additionalProperties']===false&&$schema['properties']['items']['maxItems']===20
        &&$schema['properties']['items']['items']['additionalProperties']===false,'Output schema lost bounded explicit projection.');
    cartCheck(array_diff($schema['required'],array_keys($many))===[]
        &&array_diff(array_keys($many),array_keys($schema['properties']))===[],'Cart output violates its declared key contract.');
    cartCheck(array_diff(array_keys($fixture->carts[$id]),['id','capability_hash','creation_key','status','revision','lines','created_at','updated_at','expires_at','last_change','handoff'])===[],'Cart record acquired account/payment data.');
    $_SESSION=$originalSession;
    echo "Remote cart capability, paise, availability, CAS replay, expiry and checkout claim: $checks checks passed.\n";
}catch(Throwable $error){fwrite(STDERR,$error->getMessage()."\n");exit(1);}
finally{$fixture->close();}
