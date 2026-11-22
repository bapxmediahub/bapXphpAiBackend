<?php
declare(strict_types=1);
// Disposable loopback MySQL only. Neither workers nor the HTTP fixture inherit production configuration.
$job=($argv[1]??'')==='worker'?json_decode(base64_decode($argv[2]??'',true)?:'',true,512,JSON_THROW_ON_ERROR):null;
foreach(['BAPX_MYSQL_HOST'=>'127.0.0.1','BAPX_MYSQL_PORT'=>isset($job['remoteUrl'])?'1':'33068',
    'BAPX_MYSQL_DB'=>'sps_order_test','BAPX_MYSQL_USER'=>'sps_test','BAPX_MYSQL_PASS'=>'sps_ci_fixture',
    'BAPX_REMOTE_DB_URL'=>$job['remoteUrl']??'http://127.0.0.1:1/remotedb','BAPX_TEST_MODE'=>'0',
    'APP_URL'=>'https://cart.example.test']as$key=>$value){putenv($key.'='.$value);$_SERVER[$key]=$value;}
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{DatabaseService,RemoteCartService,RemoteCartException,SettingsService};
$store=new DatabaseService(!isset($job['remoteUrl']));
if($job!==null){
    echo "ready\n";fflush(STDOUT);
    if(trim((string)fgets(STDIN))!=='go')throw new RuntimeException('Missing cart worker start barrier.');
    try{$result=$store->remoteCartOperation($job['operation'],$job['payload']);echo json_encode([
        'ok'=>true,'revision'=>$result['record']['revision'],'replayed'=>$result['replayed']],JSON_THROW_ON_ERROR),"\n";}
    catch(RemoteCartException$error){echo json_encode(['ok'=>false,'code'=>$error->errorCode,'status'=>$error->httpStatus,'retryAfter'=>$error->retryAfter],JSON_THROW_ON_ERROR),"\n";}
    exit;
}
$pdo=$store->connection();
if($pdo->query('SELECT DATABASE()')->fetchColumn()!=='sps_order_test')throw new RuntimeException('Refuse a nonfixture database.');
$checks=0;
function mysqlCartCheck(bool $value,string $message):void {global$checks;$checks++;if(!$value)throw new RuntimeException($message);}
function mysqlCartError(callable$call,string$code,int$status):void{
    try{$call();}catch(RemoteCartException$error){mysqlCartCheck($error->errorCode===$code&&$error->httpStatus===$status,'Wrong database cart error: '.$code);return;}
    throw new RuntimeException('Expected database cart error: '.$code);
}
$run=static function(array$jobs):array {
    $workers=[];$results=[];
    foreach($jobs as$task){
        $process=proc_open([PHP_BINARY,__FILE__,'worker',base64_encode(json_encode($task,JSON_THROW_ON_ERROR))],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process))throw new RuntimeException('Cart fixture worker unavailable.');
        $workers[]=[$process,$pipes];
    }
    foreach($workers as[, $pipes])if(trim((string)fgets($pipes[1]))!=='ready')throw new RuntimeException('Cart worker did not start.');
    foreach($workers as[, $pipes]){fwrite($pipes[0],"go\n");fflush($pipes[0]);fclose($pipes[0]);}
    foreach($workers as[$process,$pipes]){
        $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($process)!==0)throw new RuntimeException('Cart fixture worker failed: '.$error);
        $results[]=json_decode(trim($output),true,512,JSON_THROW_ON_ERROR);
    }
    return $results;
};
$owner=hash('sha256','cart-mysql-'.bin2hex(random_bytes(16)));$owners=[$owner];
$newIdentity=static function(?string$key=null)use(&$owners,$owner):array {
    $key??=$owner;if(!in_array($key,$owners,true))$owners[]=$key;
    return ['id'=>bin2hex(random_bytes(16)),'capabilityHash'=>hash('sha256',bin2hex(random_bytes(32))),'creationKey'=>$key];
};
$countOwner=static function(string$key)use($pdo):int{$query=$pdo->prepare('SELECT COUNT(*) FROM agent_carts WHERE _owner=?');$query->execute([$key]);return(int)$query->fetchColumn();};
$bridge=null;$bridgeLog=null;$sessionDir=null;$temporary=[];$productId='remote-cart-'.bin2hex(random_bytes(6));
$settingsBefore=null;$settingsTouched=false;$productSeeded=false;
try{
    $identity=$newIdentity();$store->remoteCartOperation('create',$identity);
    mysqlCartCheck($countOwner($owner)===1,'Creation did not persist one isolated cart.');
    $jobs=[];foreach([2,3]as$qty)$jobs[]=['operation'=>'set_quantity','payload'=>$identity+['slug'=>'fixture-product','quantity'=>$qty,'expectedRevision'=>0]];
    $race=$run($jobs);
    mysqlCartCheck(count(array_filter($race,fn($r)=>$r['ok']))===1
        &&count(array_filter($race,fn($r)=>($r['code']??'')==='cart_conflict'))===1,'Competing absolute updates were not serialized.');
    $same=['operation'=>'set_quantity','payload'=>$identity+['slug'=>'fixture-product','quantity'=>4,'expectedRevision'=>1]];
    $race=$run([$same,$same]);
    mysqlCartCheck(array_column($race,'revision')===[2,2]&&array_sum(array_column($race,'replayed'))===1,'Exact concurrent replay applied twice.');
    $ticketHash=hash('sha256','fixture-ticket-'.bin2hex(random_bytes(16)));
    $store->remoteCartOperation('prepare_checkout',$identity+['expectedRevision'=>2,'ticketHash'=>$ticketHash,'ticketExpiresAt'=>time()+900]);
    $noOp=$store->remoteCartOperation('set_quantity',$identity+['slug'=>'fixture-product','quantity'=>4,'expectedRevision'=>2]);
    mysqlCartCheck($noOp['record']['revision']===2&&$noOp['replayed']
        &&($noOp['record']['handoff']['token_hash']??null)===$ticketHash,'MySQL JSON key order made a no-op invalidate its checkout receipt.');
    $claims=[];foreach(['a','b']as$browser)$claims[]=['operation'=>'claim_checkout','payload'=>[
        'id'=>$identity['id'],'ticketHash'=>$ticketHash,'browserBinding'=>hash('sha256',$browser),'expectedRevision'=>2]];
    $race=$run($claims);
    mysqlCartCheck(count(array_filter($race,fn($r)=>$r['ok']))===1
        &&count(array_filter($race,fn($r)=>($r['code']??'')==='checkout_already_used'))===1,'Competing browsers both claimed a checkout ticket.');
    $winner=$race[0]['ok']?0:1;
    mysqlCartCheck($store->remoteCartOperation('claim_checkout',$claims[$winner]['payload'])['replayed'],'Winner cannot safely replay its claim.');

    // Expired records are cleaned in a bounded batch, rather than scanning/deleting all rows per request.
    $expiredOwner=hash('sha256',$owner.':expired');$owners[]=$expiredOwner;
    $insert=$pdo->prepare('INSERT INTO agent_carts (id,_data,_owner,_status,_created_at,_updated_at) VALUES (?,?,?,?,?,?)');
    for($i=0;$i<101;$i++){
        $expired=$newIdentity($expiredOwner);$record=RemoteCartService::transition(null,'create',$expired,time()-86410)['record'];
        $insert->execute([$record['id'],json_encode($record,JSON_THROW_ON_ERROR),$expiredOwner,'open',$record['created_at'],$record['updated_at']]);
    }
    mysqlCartError(fn()=>$store->remoteCartOperation('get',$expired),'cart_expired',410);
    $store->remoteCartOperation('create',$newIdentity());
    mysqlCartCheck($countOwner($expiredOwner)===1,'Expired cleanup did not retain the configured100-row bound.');

    // Real HTTP bridge and browser review route; capability output never enters test logs.
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);if(!$socket)throw new RuntimeException('Loopback reservation unavailable.');
    $address=stream_socket_get_name($socket,false);fclose($socket);$origin='http://'.$address;
    $sessionDir=sys_get_temp_dir().'/sps-cart-session-'.bin2hex(random_bytes(8));mkdir($sessionDir,0700);
    $bridgeLog=tempnam(sys_get_temp_dir(),'sps-cart-http-');
    putenv('PHP_CLI_SERVER_WORKERS=3');
    $bridge=proc_open([PHP_BINARY,'-d','session.save_path='.$sessionDir,'-S',$address,'index.php'],
        [0=>['pipe','r'],1=>['file',$bridgeLog,'a'],2=>['file',$bridgeLog,'a']],$pipes,dirname(__DIR__));
    if(!is_resource($bridge))throw new RuntimeException('Cart HTTP fixture unavailable.');fclose($pipes[0]);
    for($i=0;$i<60;$i++){$socket=@stream_socket_client('tcp://'.$address,$errno,$error,.1);if($socket)break;usleep(50000);}
    if(!$socket)throw new RuntimeException('Cart HTTP fixture did not start.');fclose($socket);
    $remote=new DatabaseService();(new ReflectionProperty(DatabaseService::class,'remoteOnly'))->setValue($remote,true);
    $cfg=new ReflectionProperty(DatabaseService::class,'cfg');$configuration=$cfg->getValue($remote);$configuration['remote_url']=$origin.'/remotedb';$cfg->setValue($remote,$configuration);
    $remoteIdentity=$newIdentity();$remote->remoteCartOperation('create',$remoteIdentity);
    $remoteJobs=[];foreach([2,3]as$quantity)$remoteJobs[]=['operation'=>'set_quantity','payload'=>$remoteIdentity+[
        'slug'=>'fixture-product','quantity'=>$quantity,'expectedRevision'=>0],'remoteUrl'=>$origin.'/remotedb'];
    $race=$run($remoteJobs);
    mysqlCartCheck(count(array_filter($race,fn($r)=>$r['ok']))===1&&count(array_filter($race,fn($r)=>($r['code']??'')==='cart_conflict'))===1,'Authenticated remote bridge lost row-lock/CAS semantics.');
    mysqlCartError(fn()=>$remote->remoteCartOperation('get',['id'=>$remoteIdentity['id'],'capabilityHash'=>str_repeat('0',64)]),'cart_not_found',404);

    $rateOwner=hash('sha256',$owner.':rate');$owners[]=$rateOwner;$jobs=[];
    for($i=0;$i<12;$i++)$jobs[]=['operation'=>'create','payload'=>$newIdentity($rateOwner),'remoteUrl'=>$origin.'/remotedb'];
    $rate=$run($jobs);
    mysqlCartCheck(count(array_filter($rate,fn($r)=>$r['ok']))===10&&count(array_filter($rate,fn($r)=>($r['status']??0)===429))===2,'Concurrent remote creation bypassed the10/minute limit.');
    mysqlCartError(fn()=>$remote->remoteCartOperation('create',$newIdentity($rateOwner)),'cart_rate_limited',429);

    // Seed only a synthetic product and a temporary module setting in this disposable database.
    foreach(['products','settings']as$table)$pdo->exec("CREATE TABLE IF NOT EXISTS $table (id VARCHAR(64) PRIMARY KEY,_data JSON NOT NULL,_owner VARCHAR(255),_status VARCHAR(64),_created_at VARCHAR(40),_updated_at VARCHAR(40)) ENGINE=InnoDB");
    $settingsBefore=$store->find('settings',SettingsService::SETTINGS_ID);$settingsTouched=true;
    $store->upsert('settings',['id'=>SettingsService::SETTINGS_ID,'module_shop'=>'1']);SettingsService::flushCache();
    $product=['id'=>$productId,'slug'=>$productId,'name'=>'Disposable Remote Cart Product','price'=>123.45,'stock_status'=>'in_stock'];$store->upsert('products',$product);$productSeeded=true;
    $orderCount=static function()use($pdo):?int{return $pdo->query("SHOW TABLES LIKE 'orders'")->fetchColumn()===false?null:(int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();};
    $ordersBefore=$orderCount();
    $service=new RemoteCartService($store,publicUrl:'https://cart.example.test');
    $browserIdentity=$newIdentity();$secret=bin2hex(random_bytes(32));$browserIdentity['capabilityHash']=hash('sha256',$secret);
    $store->remoteCartOperation('create',$browserIdentity);$cartToken='cart_'.$browserIdentity['id'].'.'.$secret;
    $service->shoppingCart(['operation'=>'set_quantity','cartToken'=>$cartToken,'expectedRevision'=>0,'slug'=>$productId,'quantity'=>2]);
    $checkout=$service->prepareCheckout(['cartToken'=>$cartToken,'expectedRevision'=>1]);$path=parse_url($checkout['checkoutUrl'],PHP_URL_PATH).'?'.parse_url($checkout['checkoutUrl'],PHP_URL_QUERY);
    $jar=tempnam(sys_get_temp_dir(),'sps-cart-cookie-');$temporary[]=$jar;
    $http=static function(string$path,?array$post=null,?string$cookie=null)use($origin):array{
        $headers=[];$curl=curl_init($origin.$path);curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_TIMEOUT=>10,CURLOPT_HEADERFUNCTION=>static function($curl,string$line)use(&$headers):int{
                if(str_contains($line,':')){[$key,$value]=explode(':',$line,2);$headers[strtolower(trim($key))]=trim($value);}return strlen($line);}]);
        if($cookie!==null)curl_setopt_array($curl,[CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie]);
        if($post!==null)curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);
        $body=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
        if(!is_string($body))throw new RuntimeException('Fixture HTTP request failed.');return compact('status','headers','body');
    };
    $session=static function(string$jar,?array$replace=null)use($sessionDir):array{
        $id='';foreach(file($jar)?:[]as$line){$parts=explode("\t",trim($line));if(count($parts)===7&&$parts[5]==='PHPSESSID')$id=$parts[6];}
        if(!preg_match('/^[a-zA-Z0-9,-]+$/D',$id))throw new RuntimeException('Fixture session cookie missing.');
        $file=$sessionDir.'/sess_'.$id;$handle=fopen($file,'r+');if(!$handle)throw new RuntimeException('Fixture session missing.');flock($handle,LOCK_EX);
        $old=$_SESSION;$_SESSION=[];session_decode(stream_get_contents($handle));$data=$_SESSION;
        if($replace!==null){$_SESSION=$replace;rewind($handle);ftruncate($handle,0);fwrite($handle,session_encode());$data=$replace;}
        $_SESSION=$old;flock($handle,LOCK_UN);fclose($handle);return$data;
    };
    $form=static function(string$html):array{
        $values=[];preg_match_all('/<input type="hidden" name="([^"]+)" value="([^"]*)">/',$html,$matches,PREG_SET_ORDER);
        foreach($matches as$match)$values[$match[1]]=html_entity_decode($match[2],ENT_QUOTES,'UTF-8');return$values;
    };
    $cartLines=static fn(array$data):array=>array_map(static fn(array$line):array=>['slug'=>$line['slug'],'qty'=>$line['qty']],$data['cart']??[]);
    $unauth=$http('/remotedb',['action'=>'remote_cart_operation','collection'=>'agent_carts','operation'=>'create']);
    mysqlCartCheck($unauth['status']===401,'Remote database mutation became public.');
    $page=$http($path,null,$jar);mysqlCartCheck($page['status']===200,'Public review route failed.');
    $browser=$session($jar);$browser['cart']=[['slug'=>'fixture-existing-cart','qty'=>7]];$session($jar,$browser);
    $page=$http($path,null,$jar);$fields=$form($page['body']);
    mysqlCartCheck($session($jar)['cart']===$browser['cart']&&$store->remoteCartOperation('get',$browserIdentity)['record']['status']==='open','Review GET mutated a browser or stored cart.');
    mysqlCartCheck(($page['headers']['referrer-policy']??'')==='no-referrer'
        &&str_contains($page['headers']['cache-control']??'','no-store')
        &&!str_contains($page['body'],'<script')&&!str_contains($page['body'],$secret),'Review leaked a persistent cart capability or loaded analytics/scripts.');
    $bad=$http('/cart/agent-checkout',['_csrf'=>'invalid','confirm'=>'replace']+$fields,$jar);
    mysqlCartCheck($bad['status']===419&&$session($jar)['cart']===$browser['cart'],'Invalid CSRF replaced a browser cart.');
    mysqlCartCheck($http('/cart/agent-checkout',$fields,$jar)['status']===419,'Unconfirmed replacement was accepted.');
    $import=$http('/cart/agent-checkout',$fields+['confirm'=>'replace'],$jar);
    mysqlCartCheck($import['status']===302&&($import['headers']['location']??'')==='/checkout'
        &&$cartLines($session($jar))===[['slug'=>$productId,'qty'=>2]],'Explicit review did not import the real cart then hand off to checkout.');
    $edited=$session($jar);$edited['cart']=[['slug'=>$productId,'qty'=>3]];$session($jar,$edited);
    mysqlCartCheck($http('/cart/agent-checkout',$fields+['confirm'=>'replace'],$jar)['status']===302
        &&$session($jar)['cart']===$edited['cart'],'Repeated import overwrote later browser cart changes.');
    $otherJar=tempnam(sys_get_temp_dir(),'sps-cart-cookie-');$temporary[]=$otherJar;
    mysqlCartCheck($http($path,null,$otherJar)['status']===409,'Another browser reused a claimed review link.');
    // Recover the gap after a successful DB claim but before the PHP session was saved.
    unset($edited['agent_checkout_receipts']);$edited['cart']=[['slug'=>'fixture-existing-cart','qty'=>7]];$session($jar,$edited);
    $recovery=$http($path,null,$jar);
    mysqlCartCheck(str_contains($recovery['body'],'name="confirm"'),'Interrupted import did not offer same-browser recovery.');
    mysqlCartCheck($http('/cart/agent-checkout',$form($recovery['body'])+['confirm'=>'replace'],$jar)['status']===302
        &&$cartLines($session($jar))===[['slug'=>$productId,'qty'=>2]],'Same-browser recovery did not finish its original import.');
    mysqlCartCheck($orderCount()===$ordersBefore,'Anonymous cart handoff created an order.');

    // Prove the hard active-record ceiling without creating ten thousand service requests.
    $capacityOwner=hash('sha256',$owner.':capacity');$owners[]=$capacityOwner;$pdo->beginTransaction();
    try{for($i=0;$i<10000;$i++){
        $record=RemoteCartService::transition(null,'create',$newIdentity($capacityOwner),time())['record'];
        $insert->execute([$record['id'],json_encode($record,JSON_THROW_ON_ERROR),$capacityOwner,'open',$record['created_at'],$record['updated_at']]);
    }$pdo->commit();}catch(Throwable$error){$pdo->rollBack();throw$error;}
    mysqlCartError(fn()=>$store->remoteCartOperation('create',$newIdentity()),'cart_capacity',429);
    echo "Remote cart MySQL/bridge concurrency, limits and real CSRF review route: $checks checks passed.\n";
}finally{
    if(is_resource($bridge)){proc_terminate($bridge);proc_close($bridge);}
    foreach($temporary as$file)if(is_file($file))unlink($file);
    if($sessionDir!==null){foreach(glob($sessionDir.'/sess_*')?:[]as$file)unlink($file);rmdir($sessionDir);}
    if($bridgeLog!==null&&is_file($bridgeLog))unlink($bridgeLog);
    foreach($owners as$key){$delete=$pdo->prepare('DELETE FROM agent_carts WHERE _owner=?');$delete->execute([$key]);}
    if($productSeeded){$delete=$pdo->prepare('DELETE FROM products WHERE id=?');$delete->execute([$productId]);}
    if($settingsTouched&&$settingsBefore!==null)$store->upsert('settings',$settingsBefore);
    elseif($settingsTouched){$delete=$pdo->prepare('DELETE FROM settings WHERE id=?');$delete->execute([SettingsService::SETTINGS_ID]);}
}
