<?php
declare(strict_types=1);
use App\Services\{PublicMcpService,RemoteCartService,RemoteCheckoutService};
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
putenv('BAPX_TEST_MODE=1');putenv('APP_URL=https://cart.example.test');
require dirname(__DIR__).'/app/bootstrap.php';
require __DIR__.'/fixtures/remote-cart.php';
set_exception_handler(static function(Throwable $error):void { fwrite(STDERR,$error::class.': '.$error->getMessage()."\n");exit(1); });
$checks=0;
$check=static function(bool $ok,string $reason) use(&$checks):void { $checks++;if(!$ok)throw new RuntimeException($reason); };
foreach(PublicMcpService::SUPPORTED_PROTOCOLS as $protocol) {
    $fixture=new RemoteCartFixture();
    try {
        $service=new PublicMcpService(knowledge:$fixture->knowledge(),publicUrl:'https://cart.example.test',shopping:$fixture->service());
        $invoke=static function(string $method,array $params=[]) use($service,$protocol):array {
            $headers=['mcp-protocol-version'=>$protocol];
            if($protocol===PublicMcpService::PROTOCOL) {
                $headers['mcp-method']=$method;
                if(isset($params['name']))$headers['mcp-name']=$params['name'];
                if(isset($params['uri']))$headers['mcp-name']=$params['uri'];
                $params['_meta']=['io.modelcontextprotocol/protocolVersion'=>$protocol,'io.modelcontextprotocol/clientCapabilities'=>(object)[]];
            }
            return $service->handle(json_encode(['jsonrpc'=>'2.0','id'=>1,'method'=>$method,'params'=>(object)$params],JSON_THROW_ON_ERROR),$headers);
        };
        $call=static fn(string $name,array $args=[])=>$invoke('tools/call',['name'=>$name,'arguments'=>(object)$args])['body'];
        $tools=$invoke('tools/list')['body']['result']['tools'];$byName=array_column($tools,null,'name');
        $check(count($tools)===10 && !isset($byName['shopping_cart'],$byName['prepare_checkout']),'Operation-selector or website-only tools exposed');
        $fields=RemoteCartService::cartInputSchema()['properties'];
        $check($byName['get_cart']['inputSchema']['properties']===['cartToken'=>$fields['cartToken']],'Canonical cart field schema forked');
        $check($byName['set_cart_quantity']['outputSchema']===RemoteCartService::cartOutputSchema(),'Cart output schema forked');
        $check($byName['review_checkout']['inputSchema']===RemoteCheckoutService::reviewInputSchema(),'Review input schema forked');
        $check($byName['prepare_checkout_payment']['outputSchema']===RemoteCheckoutService::preparePaymentOutputSchema(),'Payment output schema forked');
        $check($byName['get_checkout_status']['outputSchema']===RemoteCheckoutService::statusOutputSchema(),'Status output schema forked');
        $check($byName['set_cart_quantity']['annotations']===['readOnlyHint'=>false,'destructiveHint'=>true,'idempotentHint'=>true,'openWorldHint'=>false],'Absolute quantity annotations wrong');
        $check($byName['get_cart']['annotations']['readOnlyHint']===true && $byName['create_cart']['annotations']['idempotentHint']===false,'Focused cart annotations wrong');
        $check($byName['get_checkout_status']['annotations']['readOnlyHint']===false && $byName['get_checkout_status']['annotations']['openWorldHint']===true,'Provider reconciliation advertised as read-only');
        $check($byName['create_cart']['_meta']['in.bapx/access']['authorization']==='none','Create scope omitted');
        $check($byName['get_checkout_status']['_meta']['in.bapx/access']['authorization']==='payment_status_capability','Status scope omitted');
        $created=$call('create_cart')['result'];$token=$created['structuredContent']['cartToken'];
        $check((bool)preg_match('/^cart_[a-f0-9]{32}\.[a-f0-9]{64}$/D',$token),'Creation capability not returned');
        $check(!str_contains(json_encode($created['content']),$token),'Capability exposed in human text');
        $check(!str_contains(json_encode($created['_meta']),$token),'Capability duplicated into view metadata');
        $check($created['_meta'][PublicMcpService::SHOPPING_META]==='cart','Cart view missing');
        $check($created['structuredContent']['revision']===0 && $created['structuredContent']['items']===[],'Unexpected new cart');
        $args=['cartToken'=>$token,'slug'=>'sri-varahi-amman-ring','quantity'=>2,'expectedRevision'=>0];
        $updated=$call('set_cart_quantity',$args)['result'];$cart=$updated['structuredContent'];
        $check($cart['revision']===1 && $cart['itemCount']===2 && $cart['estimatedTotalInr']===1001.50,'Canonical quantity/price drifted');
        $check(!isset($cart['cartToken']),'Capability re-echoed on update');
        $check($cart['items'][0]['productUrl']==='https://cart.example.test/product/sri-varahi-amman-ring','Product URL lost configured origin');
        $check(!str_contains(json_encode($updated),'PRIVATE_CART_FIXTURE') && !str_contains(json_encode($updated),'private@example.test'),'Private catalogue data escaped');
        $replay=$call('set_cart_quantity',$args)['result']['structuredContent'];
        $check($replay['revision']===1 && $replay['itemCount']===2 && $replay['replayed'],'Duplicate changed quantity');
        $conflict=$call('set_cart_quantity',array_replace($args,['quantity'=>3]))['result'];
        $check($conflict['isError'] && $conflict['_meta'][PublicMcpService::SHOPPING_ERROR_META]['code']==='cart_conflict','Conflict not a safe tool error');
        $check(!str_contains(json_encode($conflict),$token),'Error echoed capability');
        $check($conflict['_meta'][PublicMcpService::SHOPPING_ERROR_META]['httpStatus']===409,'Conflict HTTP semantic missing from tool error');
        foreach([['get_cart',[]],['create_cart',['actor'=>'admin']],['create_cart',['operation'=>'create']],
            ['set_cart_quantity',array_replace($args,['quantity'=>1.5])],
            ['set_cart_quantity',array_replace($args,['cartToken'=>'cart_'.str_repeat('0',32).'.'.str_repeat('0',64)])]] as [$name,$bad]) {
            $check(($call($name,$bad)['result']['isError']??false)===true,'Invalid capability/arguments accepted');
        }
        $second=$call('create_cart')['result']['structuredContent'];
        $check($second['itemCount']===0 && $second['cartToken']!==$token,'Anonymous carts merged');
        $hidden=$call('set_cart_quantity',array_replace($args,['expectedRevision'=>1,'slug'=>'private-ring']))['result'];
        $check($hidden['isError'] && $hidden['_meta'][PublicMcpService::SHOPPING_ERROR_META]['code']==='product_unavailable','Hidden product added');
        foreach(['paid_sales_summary','find_order','issue_refund','reserve_stock','checkout_submit','shopping_cart','prepare_checkout'] as $private) {
            $check(($call($private)['error']['code']??null)===-32602,'Unrelated private or purchase capability exposed');
        }
        $fixture->now+=RemoteCartService::CART_TTL;
        $expired=$call('get_cart',['cartToken'=>$token])['result'];
        $check($expired['isError'] && $expired['_meta'][PublicMcpService::SHOPPING_ERROR_META]['code']==='cart_expired','Expired capability accepted');
        $fixture->shopEnabled(false);
        $names=array_column($invoke('tools/list')['body']['result']['tools'],'name');
        $check(!array_intersect($names,['create_cart','get_cart','set_cart_quantity','review_checkout','prepare_checkout_payment']),'Disabled shop still advertises new shopping actions');
        $check(in_array('get_checkout_status',$names,true),'Existing payment status hidden when Shop is off');
        $check(($call('create_cart')['error']['code']??null)===-32602,'Disabled shop created cart');
    } finally { $fixture->close(); }
}
echo "Public shopping MCP: {$checks} modern/legacy named tools, capability, privacy and revision checks PASS.\n";
