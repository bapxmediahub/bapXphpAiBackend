<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
// Fixed disposable database only. Gateway operations are deterministic constructor ports, never real payment APIs.
$job=($argv[1]??'')==='worker'?json_decode(base64_decode($argv[2]??'',true)?:'',true,128,JSON_THROW_ON_ERROR):null;
foreach(['BAPX_MYSQL_HOST'=>'127.0.0.1','BAPX_MYSQL_PORT'=>isset($job['remoteUrl'])?'1':'33068',
    'BAPX_MYSQL_DB'=>'sps_order_test','BAPX_MYSQL_USER'=>'sps_test','BAPX_MYSQL_PASS'=>'sps_ci_fixture',
    'BAPX_REMOTE_DB_URL'=>$job['remoteUrl']??'http://127.0.0.1:1/remotedb','BAPX_TEST_MODE'=>'0',
    'APP_URL'=>'https://cart.example.test'] as $key=>$value){putenv($key.'='.$value);$_SERVER[$key]=$value;}
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{DatabaseService,RemoteCartService,RemoteCartException,RemoteCheckoutService,SettingsService,VerifiedOrderService};
$store=new DatabaseService(!isset($job['remoteUrl']));
if($job!==null) {
    echo "ready\n";fflush(STDOUT);
    if(trim((string)fgets(STDIN))!=='go')throw new RuntimeException('Missing checkout worker start barrier.');
    try {
        $r=$store->remoteCheckoutOperation($job['operation'],$job['payload']);
        echo json_encode(['ok'=>true,'created'=>$r['created']??null,'claimed'=>$r['claimed']??null,
            'attemptId'=>$r['attempt']['id']??null,'revision'=>$r['record']['revision']??null],JSON_THROW_ON_ERROR),"\n";
    }catch(RemoteCartException $e){echo json_encode(['ok'=>false,'code'=>$e->errorCode,'status'=>$e->httpStatus,'retryAfter'=>$e->retryAfter],JSON_THROW_ON_ERROR),"\n";}
    exit;
}
require __DIR__.'/fixtures/remote-checkout.php';
$pdo=$store->connection();if($pdo->query('SELECT DATABASE()')->fetchColumn()!=='sps_order_test')throw new RuntimeException('Refuse a nonfixture database.');
$checks=0;
function mysqlCheckoutCheck(bool $value,string $message):void {global $checks;$checks++;if(!$value)throw new RuntimeException($message);}
function mysqlCheckoutError(callable $call,string $code,int $status):void {
    try{$call();}catch(RemoteCartException $e){mysqlCheckoutCheck($e->errorCode===$code&&$e->httpStatus===$status,'Wrong native checkout error: '.$code);return;}
    throw new RuntimeException('Expected native checkout error: '.$code);
}
$workers=static function(array $jobs):array {
    $running=[];$results=[];
    foreach($jobs as $task) {
        $process=proc_open([PHP_BINARY,__FILE__,'worker',base64_encode(json_encode($task,JSON_THROW_ON_ERROR))],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process))throw new RuntimeException('Native checkout worker unavailable.');
        $running[]=[$process,$pipes];
    }
    foreach($running as[, $pipes])if(trim((string)fgets($pipes[1]))!=='ready')throw new RuntimeException('Checkout worker did not start.');
    foreach($running as[, $pipes]){fwrite($pipes[0],"go\n");fflush($pipes[0]);fclose($pipes[0]);}
    foreach($running as[$process,$pipes]) {
        $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($process)!==0)throw new RuntimeException('Checkout worker failed: '.$error);
        $results[]=json_decode(trim($output),true,128,JSON_THROW_ON_ERROR);
    }
    return $results;
};
$fixture=new RemoteCheckoutFixture();$cartIds=[];$orderIds=[];$productId='checkout-native-'.bin2hex(random_bytes(6));
$owner=hash('sha256',$productId);$bulkOwner=$owner.':capacity';$oldOwner=$owner.':old';$bridge=null;$bridgeLog=null;
$settingsBefore=null;$settingsTouched=false;$productSeeded=false;
try {
    foreach(['products','settings','coupons','orders'] as $table)$pdo->exec("CREATE TABLE IF NOT EXISTS $table (id VARCHAR(64) PRIMARY KEY,_data JSON NOT NULL,_owner VARCHAR(255),_status VARCHAR(64),_created_at VARCHAR(40),_updated_at VARCHAR(40)) ENGINE=InnoDB");
    $settingsBefore=$store->find('settings',SettingsService::SETTINGS_ID);$settingsTouched=true;
    $store->upsert('settings',['id'=>SettingsService::SETTINGS_ID,'module_shop'=>'1']+$fixture->settings);SettingsService::flushCache();
    $store->upsert('products',['id'=>$productId,'slug'=>$productId,'name'=>'Synthetic checkout item','price'=>1.15,'stock_status'=>'in_stock']);$productSeeded=true;
    $newCart=static function()use($store,$owner,&$cartIds,$productId):array {
        $id=bin2hex(random_bytes(16));$secret=bin2hex(random_bytes(32));$identity=['id'=>$id,'capabilityHash'=>hash('sha256',$secret),'creationKey'=>$owner];
        $store->remoteCartOperation('create',$identity);$store->remoteCartOperation('set_quantity',$identity+['slug'=>$productId,'quantity'=>2,'expectedRevision'=>0]);
        $cartIds[]=$id;return ['token'=>'cart_'.$id.'.'.$secret,'identity'=>$identity];
    };
    $persist=static function(array $pending,string $paymentId,array $fields)use($store,&$orderIds):string {
        $id=(new VerifiedOrderService())->persist($pending,$paymentId,$fields,[
            'readOrders'=>fn():array=>$store->read('orders'),
            'products'=>fn():array=>(new \App\Services\ProductService($store))->bySlug(),
            'settings'=>fn():array=>(new SettingsService($store))->public(),
            'insertOrder'=>static function(array $order)use($store):array {$r=$store->saveVerifiedOrder($order,true);return ['inserted'=>$r['created'],'record'=>$r['record']];},
        ]);$orderIds[$id]=true;return $id;
    };
    $make=static fn(?Closure $operation=null):RemoteCheckoutService=>new RemoteCheckoutService(store:$store,operation:$operation,
        gateway:$fixture->gatewayPort(),settings:fn():array=>$fixture->settings,secrets:fn():array=>$fixture->secrets,persist:$persist,publicUrl:'https://cart.example.test');
    $service=$make();$cart=$newCart();
    $review=$service->reviewCheckout(['cartToken'=>$cart['token'],'expectedRevision'=>1,'delivery'=>RemoteCheckoutFixture::delivery()]);
    mysqlCheckoutCheck($review['cartRevision']===2&&$review['totals']['totalPaise']===230,'Native quote lost fractional paise or single delivery revision.');
    $same=$service->reviewCheckout(['cartToken'=>$cart['token'],'expectedRevision'=>2,'delivery'=>RemoteCheckoutFixture::delivery()]);
    mysqlCheckoutCheck($same['quote']===$review['quote']&&$same['cartRevision']===2,'MySQL JSON key/number normalization changed the same quote.');
    $noOp=$store->remoteCartOperation('set_quantity',$cart['identity']+['slug'=>$productId,'quantity'=>2,'expectedRevision'=>2]);
    mysqlCheckoutCheck($noOp['record']['revision']===2&&$noOp['record']['checkout']['quote']['id']===$review['quote']['id'],'No-op quantity invalidated a MySQL checkout quote.');
    $capture=static function(array $cart,array $review)use($make,$store):array {
        $captured=null;$service=$make(static function(string $operation,array $payload)use(&$captured,$store):array {
            if($operation==='begin_payment'){$captured=$payload;throw new LogicException('fixture-capture');}
            return $store->remoteCheckoutOperation($operation,$payload);
        });
        try{$service->preparePayment(['cartToken'=>$cart['token'],'expectedRevision'=>$review['cartRevision'],'quoteId'=>$review['quote']['id'],'confirm'=>true]);}
        catch(LogicException $e){if($e->getMessage()!=='fixture-capture')throw $e;}
        if(!is_array($captured))throw new RuntimeException('Native attempt fixture missing.');return $captured;
    };
    $first=$capture($cart,$review);$second=$capture($cart,$review);
    $raced=$workers([['operation'=>'begin_payment','payload'=>$first],['operation'=>'begin_payment','payload'=>$second]]);
    mysqlCheckoutCheck(count(array_filter($raced,fn(array $r):bool=>$r['ok']&&$r['created']))===1
        &&count(array_unique(array_column($raced,'attemptId')))===1,'Concurrent confirmations inserted more than one durable attempt.');
    $winner=$raced[0]['attemptId'];$record=$store->remoteCheckoutOperation('read_cart',$cart['identity']);
    mysqlCheckoutCheck($record['record']['status']==='checkout_locked'&&$record['record']['checkout_receipt']['attempt_id']===$winner
        &&$record['attempt']['id']===$winner,'Cart lock/receipt and attempt were not atomically saved.');
    $raced=$workers([['operation'=>'claim_reconcile','payload'=>['id'=>$winner]],['operation'=>'claim_reconcile','payload'=>['id'=>$winner]]]);
    mysqlCheckoutCheck(array_sum(array_column($raced,'claimed'))===1,'Concurrent status checks both acquired provider reconciliation.');
    mysqlCheckoutError(fn()=>$store->remoteCheckoutOperation('read_attempt',['id'=>$winner,'statusHash'=>str_repeat('0',64)]),'checkout_not_found',404);

    // Real authenticated remote bridge must retain the same transaction and safe error envelope.
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);if(!$socket)throw new RuntimeException('Native checkout loopback unavailable.');
    $address=stream_socket_get_name($socket,false);fclose($socket);$origin='http://'.$address;$bridgeLog=tempnam(sys_get_temp_dir(),'sps-checkout-http-');
    putenv('PHP_CLI_SERVER_WORKERS=3');$bridge=proc_open([PHP_BINARY,'-S',$address,'index.php'],
        [0=>['pipe','r'],1=>['file',$bridgeLog,'a'],2=>['file',$bridgeLog,'a']],$pipes,dirname(__DIR__));
    if(!is_resource($bridge))throw new RuntimeException('Native checkout HTTP server unavailable.');fclose($pipes[0]);
    for($i=0;$i<60;$i++){$socket=@stream_socket_client('tcp://'.$address,$errno,$error,.1);if($socket)break;usleep(50000);}
    if(!$socket)throw new RuntimeException('Native checkout HTTP server did not start.');fclose($socket);
    $remote=new DatabaseService();(new ReflectionProperty(DatabaseService::class,'remoteOnly'))->setValue($remote,true);
    $cfg=new ReflectionProperty(DatabaseService::class,'cfg');$config=$cfg->getValue($remote);$config['remote_url']=$origin.'/remotedb';$cfg->setValue($remote,$config);
    $cart2=$newCart();$review2=$service->reviewCheckout(['cartToken'=>$cart2['token'],'expectedRevision'=>1,'delivery'=>RemoteCheckoutFixture::delivery()]);
    $remotePayload=$capture($cart2,$review2);
    $raced=$workers(array_fill(0,2,['operation'=>'begin_payment','payload'=>$remotePayload,'remoteUrl'=>$origin.'/remotedb']));
    mysqlCheckoutCheck(array_sum(array_column($raced,'created'))===1&&count(array_unique(array_column($raced,'attemptId')))===1,'Remote bridge lost atomic confirmation replay.');
    mysqlCheckoutError(fn()=>$remote->remoteCheckoutOperation('read_cart',['id'=>$cart2['identity']['id'],'capabilityHash'=>str_repeat('0',64)]),'cart_not_found',404);
    $http=static function(string $path,?string $body=null,array $extra=[])use($origin):array {
        $headers=[];$curl=curl_init($origin.$path);curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,
            CURLOPT_HTTPHEADER=>$extra,CURLOPT_HEADERFUNCTION=>static function($curl,string $line)use(&$headers):int {
                if(str_contains($line,':')){[$key,$value]=explode(':',$line,2);$headers[strtolower(trim($key))]=trim($value);}return strlen($line);}]);
        if($body!==null)curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body]);
        $body=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
        if(!is_string($body))throw new RuntimeException('Native checkout HTTP request failed.');return compact('body','headers','status');
    };
    $unauth=$http('/remotedb','{}',['Content-Type: application/json']);
    mysqlCheckoutCheck($unauth['status']===401,'The database checkout bridge became public.');
    $invalid=$http('/api/payments/razorpay-webhook','{}',['Content-Type: application/json','X-Razorpay-Signature: '.str_repeat('0',64)]);
    mysqlCheckoutCheck($invalid['status']===401&&!isset($invalid['headers']['set-cookie']),'Unsigned callback used a browser session or bypassed HMAC.');

    // Confirmed captured payment uses the actual SQL-backed shared order boundary.
    $paidCart=$newCart();$quote=$service->reviewCheckout(['cartToken'=>$paidCart['token'],'expectedRevision'=>1,'delivery'=>RemoteCheckoutFixture::delivery()]);
    $prepared=$service->preparePayment(['cartToken'=>$paidCart['token'],'expectedRevision'=>$quote['cartRevision'],'quoteId'=>$quote['quote']['id'],'confirm'=>true]);
    mysqlCheckoutCheck($prepared['stage']==='payment_ready','Native provider fixture did not prepare a link.');
    $nativeAttempt=$store->remoteCheckoutOperation('read_internal',['id'=>$prepared['attemptId']])['attempt'];$fixture->attempts[$nativeAttempt['id']]=$nativeAttempt;
    parse_str(parse_url($prepared['paymentUrl'],PHP_URL_QUERY),$ticket);
    $page=$http('/checkout/payment?ticket='.rawurlencode($ticket['ticket']));
    mysqlCheckoutCheck($page['status']===200&&str_contains($page['body'],'Continue to Razorpay')
        &&str_contains($page['headers']['content-security-policy']??'',"frame-ancestors 'none'")
        &&!str_contains($page['body'],'synthetic-shopper@example.test'),'Merchant payment page leaked delivery or failed its fixed CSP.');
    mysqlCheckoutCheck($store->find('orders',substr(hash('sha256','verified-payment:pay_'.substr($nativeAttempt['id'],0,20)),0,16))===null,'Payment page GET created an order.');
    $fixture->markPaid($nativeAttempt['id']);
    // Simulate the cart cleanup independently of the durable payment receipt.
    $delete=$pdo->prepare('DELETE FROM agent_carts WHERE id=?');$delete->execute([$paidCart['identity']['id']]);
    $nativeAttempt['reconcile_after']=0;$nativeAttempt['updated_at']=gmdate('Y-m-d H:i:s',time()-60);
    $update=$pdo->prepare('UPDATE checkout_attempts SET _data=?,_updated_at=? WHERE id=?');
    $update->execute([json_encode($nativeAttempt,JSON_THROW_ON_ERROR),$nativeAttempt['updated_at'],$nativeAttempt['id']]);
    $status=$service->checkoutStatus(['paymentToken'=>$prepared['paymentToken']]);
    mysqlCheckoutCheck($status['paymentStatus']==='captured'&&$status['orderStatus']==='saved','Captured native payment did not survive cart cleanup.');
    $order=$store->find('orders',$status['orderReference']);
    mysqlCheckoutCheck($order['total']==2.30&&isset($order['invoice_number'])&&!$order['tax_review_required'],'Native verified order lost fractional amount or frozen GST snapshot.');
    mysqlCheckoutCheck($order['supplier']['state_code']==='33'&&$order['place_of_supply_code']==='33','Native MySQL checkout persists the official state codes.');
    $again=$service->checkoutStatus(['paymentToken'=>$prepared['paymentToken']]);
    mysqlCheckoutCheck($again['orderReference']===$status['orderReference'],'Native status replay saved a different order.');

    // Global capacity cannot leave a locked cart without an attempt when the transaction rejects.
    $capacityCart=$newCart();$capacityQuote=$service->reviewCheckout(['cartToken'=>$capacityCart['token'],'expectedRevision'=>1,'delivery'=>RemoteCheckoutFixture::delivery()]);
    $capacityPayload=$capture($capacityCart,$capacityQuote);$needed=max(0,20000-(int)$pdo->query('SELECT COUNT(*) FROM checkout_attempts')->fetchColumn());
    $rows=[];
    for($i=0;$i<$needed;$i++) {
        $rows[]=[bin2hex(random_bytes(16)),'{}',$bulkOwner,'uncertain',gmdate('Y-m-d H:i:s'),gmdate('Y-m-d H:i:s')];
        if(count($rows)===200||$i===$needed-1){$insert=$pdo->prepare('INSERT INTO checkout_attempts (id,_data,_owner,_status,_created_at,_updated_at) VALUES '.implode(',',array_fill(0,count($rows),'(?,?,?,?,?,?)')));$insert->execute(array_merge(...$rows));$rows=[];}
    }
    mysqlCheckoutError(fn()=>$remote->remoteCheckoutOperation('begin_payment',$capacityPayload),'checkout_capacity',429);
    $unchanged=$store->remoteCheckoutOperation('read_cart',$capacityCart['identity']);
    mysqlCheckoutCheck($unchanged['record']['status']==='open'&&!isset($unchanged['record']['checkout_receipt'])&&$unchanged['attempt']===null,'Capacity rejection committed a stranded cart lock.');
    $cleanup=$pdo->prepare('DELETE FROM checkout_attempts WHERE _owner=?');$cleanup->execute([$bulkOwner]);
    $insert=$pdo->prepare('INSERT INTO checkout_attempts (id,_data,_owner,_status,_created_at,_updated_at) VALUES (?,?,?,?,?,?)');
    $old=gmdate('Y-m-d H:i:s',time()-RemoteCheckoutService::STATUS_TTL-60);
    for($i=0;$i<101;$i++)$insert->execute([bin2hex(random_bytes(16)),'{}',$oldOwner,'completed',$old,$old]);
    $unresolvedId=bin2hex(random_bytes(16));$insert->execute([$unresolvedId,'{}',$oldOwner,'uncertain',$old,$old]);
    $store->remoteCheckoutOperation('begin_payment',$capacityPayload);
    $count=$pdo->prepare("SELECT COUNT(*) FROM checkout_attempts WHERE _owner=? AND _status='completed'");$count->execute([$oldOwner]);
    mysqlCheckoutCheck((int)$count->fetchColumn()===1,'Terminal checkout cleanup exceeded its100-row bound.');
    $count=$pdo->prepare('SELECT COUNT(*) FROM checkout_attempts WHERE id=?');$count->execute([$unresolvedId]);
    mysqlCheckoutCheck((int)$count->fetchColumn()===1,'Unresolved payment was removed by terminal/24hour cleanup.');
    echo 'Native MySQL checkout atomicity, HTTP bridge, provider recovery and retention: '.$checks." checks passed.\n";
}finally {
    if(is_resource($bridge)){proc_terminate($bridge);proc_close($bridge);}if(is_string($bridgeLog)&&is_file($bridgeLog))unlink($bridgeLog);
    foreach($cartIds as $id){foreach(['agent_carts','checkout_attempts'] as $table){$query=$pdo->prepare('DELETE FROM '.$table.' WHERE '.($table==='agent_carts'?'id':'_owner').'=?');$query->execute([$id]);}}
    foreach([$bulkOwner,$oldOwner] as $key){if($pdo->query("SHOW TABLES LIKE 'checkout_attempts'")->fetchColumn()!==false){$query=$pdo->prepare('DELETE FROM checkout_attempts WHERE _owner=?');$query->execute([$key]);}}
    foreach(array_keys($orderIds) as $id){$query=$pdo->prepare('DELETE FROM orders WHERE id=?');$query->execute([$id]);}
    if($productSeeded){$query=$pdo->prepare('DELETE FROM products WHERE id=?');$query->execute([$productId]);}
    if($settingsTouched){if($settingsBefore!==null)$store->upsert('settings',$settingsBefore);else{$query=$pdo->prepare('DELETE FROM settings WHERE id=?');$query->execute([SettingsService::SETTINGS_ID]);}SettingsService::flushCache();}
    $fixture->close();
}
