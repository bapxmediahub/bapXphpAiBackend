<?php
declare(strict_types=1);
use App\Services\PublicMcpService;
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
putenv('BAPX_TEST_MODE=1');putenv('APP_URL=https://cart.example.test');
require dirname(__DIR__).'/app/bootstrap.php';
require __DIR__.'/fixtures/remote-checkout.php';
$checks=0;
$check=static function(bool $value,string $message)use(&$checks):void{$checks++;if(!$value)throw new RuntimeException($message);};
foreach(PublicMcpService::SUPPORTED_PROTOCOLS as $protocol){
    $fixture=new RemoteCheckoutFixture();
    try{
        $fixture->coupons=[['id'=>'coupon-fixture','code'=>'PRIVATEFIXTURE10','active'=>true,'discount_type'=>'fixed','discount_value'=>10]];
        $service=new PublicMcpService(knowledge:$fixture->cartFixture->knowledge(),shopping:$fixture->cartFixture->service(),
            checkout:$fixture->service(),publicUrl:'https://cart.example.test');
        $call=static function(string $name,array $args=[])use($service,$protocol):array{
            $headers=['mcp-protocol-version'=>$protocol];$params=['name'=>$name,'arguments'=>(object)$args];
            if($protocol===PublicMcpService::PROTOCOL){
                $headers+=['mcp-method'=>'tools/call','mcp-name'=>$name];
                $params['_meta']=['io.modelcontextprotocol/protocolVersion'=>$protocol,'io.modelcontextprotocol/clientCapabilities'=>(object)[]];
            }
            $response=$service->handle(json_encode(['jsonrpc'=>'2.0','id'=>1,'method'=>'tools/call','params'=>$params],JSON_THROW_ON_ERROR),$headers);
            if($response['status']!==200||isset($response['body']['error']))throw new RuntimeException('Checkout wrapper framing failed.');
            return $response['body']['result'];
        };
        $cart=$call('create_cart')['structuredContent'];$token=$cart['cartToken'];
        $cart=$call('set_cart_quantity',['cartToken'=>$token,'slug'=>'sri-varahi-amman-ring','quantity'=>2,'expectedRevision'=>0])['structuredContent'];
        $empty=$call('review_checkout',['cartToken'=>$token,'expectedRevision'=>1]);
        $check($empty['structuredContent']['stage']==='details_required'&&$empty['structuredContent']['quote']===null,'Unentered delivery invented');
        $check($empty['_meta'][PublicMcpService::SHOPPING_META]==='review','Inline review resource metadata missing');
        $check($empty['structuredContent']['totals']['totalPaise']===100150&&$fixture->attempts===[],'Review created payment or wrong paise');
        $delivery=RemoteCheckoutFixture::delivery();
        $review=$call('review_checkout',['cartToken'=>$token,'expectedRevision'=>1,'delivery'=>$delivery,'couponCode'=>'PRIVATEFIXTURE10']);
        $data=$review['structuredContent'];
        $check($data['stage']==='review_ready'&&$data['deliveryComplete']&&$data['deliveryRegion']==='Tamil Nadu','Complete delivery review failed');
        $check($data['cartRevision']===2&&$data['cart']['revision']===2,'Delivery and coupon did not share one revision');
        $check($data['totals']['discountPaise']===1000&&$data['totals']['totalPaise']===99150&&$data['totals']['taxIncluded'],'Authoritative checkout money drifted');
        foreach([$delivery['name'],$delivery['email'],$delivery['phone'],$delivery['address'],$delivery['pincode'],'PRIVATEFIXTURE10',$token]as$private){
            $check(!str_contains(json_encode($review),$private),'Personal input/coupon/cart capability echoed in review');
        }
        $check($fixture->gatewayCalls===[]&&$fixture->orders===[],'Review contacted provider or saved order');
        $confirm=['cartToken'=>$token,'expectedRevision'=>2,'quoteId'=>$data['quote']['id'],'confirm'=>true];
        $unconfirmed=$call('prepare_checkout_payment',array_replace($confirm,['confirm'=>false]));
        $check(($unconfirmed['isError']??false)&&$fixture->attempts===[],'Payment accepted without exact explicit confirmation');
        $forbidden=$call('review_checkout',['cartToken'=>$token,'expectedRevision'=>2,'cardNumber'=>'fixture-not-accepted']);
        $check(($forbidden['isError']??false)&&$fixture->attempts===[],'Raw payment credential field accepted');
        $prepared=$call('prepare_checkout_payment',$confirm);$payment=$prepared['structuredContent'];
        $check($prepared['_meta'][PublicMcpService::SHOPPING_META]==='payment'&&$payment['stage']==='payment_ready','Prepared payment UI data missing');
        $check($payment['amountPaise']===99150&&$payment['mode']==='test','Wrong confirmed amount/mode');
        $check((bool)preg_match('/^payment_[a-f0-9]{32}\.[a-f0-9]{64}$/D',$payment['paymentToken']),'Status capability malformed');
        $check(str_starts_with($payment['paymentUrl'],'https://cart.example.test/checkout/payment?ticket=pay_'),'Payment URL leaves merchant origin');
        $check(!str_contains(json_encode($prepared['content']),$payment['paymentToken'])&&!str_contains(json_encode($prepared['_meta']),$payment['paymentToken']),'Status capability in human content or metadata');
        $check(!str_contains(json_encode($prepared),$token)&&$fixture->orders===[],'Prepare exposes cart access or claims order');
        $again=$call('prepare_checkout_payment',$confirm)['structuredContent'];
        $check($again['attemptId']===$payment['attemptId']&&$again['paymentToken']===$payment['paymentToken'],'Retry created another attempt or receipt');
        $creates=array_filter($fixture->gatewayCalls,static fn(array $call):bool=>$call['operation']==='create_link');
        $check(count($creates)===1,'Provider create repeated for exact quote');
        $provider=array_values($creates)[0]['payload'];
        $check(($provider['notify']??null)===['sms'=>false,'email'=>false]&&($provider['reminder_enable']??null)===false,'Unrequested provider notification enabled');
        $check(!str_contains(json_encode($provider),$delivery['email'])&&!isset($provider['customer']),'Delivery contact leaked into provider creation');
        $locked=$call('get_cart',['cartToken'=>$token]);
        $check($locked['structuredContent']['status']==='checkout_locked'&&$locked['structuredContent']['checkout']['paymentToken']===$payment['paymentToken'],'Lost-response recovery missing from locked cart');
        $check(!str_contains(json_encode($locked['content']),$payment['paymentToken']),'Recovery token appears in text');
        $edit=$call('set_cart_quantity',['cartToken'=>$token,'slug'=>'sri-varahi-amman-ring','quantity'=>3,'expectedRevision'=>2]);
        $check(($edit['isError']??false)===true,'Confirmed checkout changed quantity');
        $status=$call('get_checkout_status',['paymentToken'=>$payment['paymentToken']]);
        $check($status['structuredContent']['paymentStatus']==='pending'&&$status['structuredContent']['orderStatus']==='not_created','Prepared link became a paid claim');
        $fixture->advance(31);$fixture->markPaid($payment['attemptId']);$fixture->failPersist=true;
        $pending=$call('get_checkout_status',['paymentToken'=>$payment['paymentToken']])['structuredContent'];
        $check($pending['paymentStatus']==='captured'&&$pending['orderStatus']==='save_pending'&&!isset($pending['orderReference']),'Order persistence failure hidden');
        $fixture->advance(31);$fixture->failPersist=false;
        $paid=$call('get_checkout_status',['paymentToken'=>$payment['paymentToken']]);
        $check($paid['structuredContent']['paymentStatus']==='captured'&&$paid['structuredContent']['orderStatus']==='saved'&&count($fixture->orders)===1,'Verified order not saved exactly once');
        $check($paid['_meta'][PublicMcpService::SHOPPING_META]==='status'&&isset($paid['structuredContent']['orderReference']),'Verified status view/reference missing');
        foreach([$delivery['name'],$delivery['email'],$delivery['address'],'synthetic-provider-private@example.test','synthetic-private@provider',$payment['paymentToken']]as$private){
            $check(!str_contains(json_encode($paid),$private),'Status exposed personal/provider data or repeated capability');
        }
        $fixture->cartFixture->shopEnabled(false);
        $off=$call('get_checkout_status',['paymentToken'=>$payment['paymentToken']]);
        $check($off['structuredContent']['orderStatus']==='saved','Shop-off hid an existing status receipt');
        $bad=$call('get_checkout_status',['paymentToken'=>$payment['paymentToken'],'email'=>$delivery['email']]);
        $check(($bad['isError']??false)===true,'Status tool accepted identity discovery input');
        $fixture->advance(604800);
        $expired=$call('get_checkout_status',['paymentToken'=>$payment['paymentToken']]);
        $check(($expired['isError']??false)&&$expired['_meta'][PublicMcpService::SHOPPING_ERROR_META]['httpStatus']===410,'Expired payment receipt accepted');
    }finally{$fixture->close();}
}
echo "Public MCP checkout: {$checks} profile, quote, privacy, payment and status checks PASS.\n";
