<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
/** Full HTTP requests on loopback only; no production route, database, credentials or payment. */
$root=dirname(__DIR__);$fixture=sys_get_temp_dir().'/sps-response-'.bin2hex(random_bytes(7));
if(!mkdir($fixture,0700,true))throw new RuntimeException('Fixture directory unavailable');
$listener=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
if(!$listener)throw new RuntimeException('Fixture port unavailable');
$address=stream_socket_get_name($listener,false);fclose($listener);
$router=<<<'ROUTER'
<?php
require BOOTSTRAP;
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
switch($path){
 case '/ok': header('Content-Type: text/plain');echo 'NORMAL_BODY';break;
 case '/json-ok':header('Content-Type: application/json');echo '{"ok":true}';break;
 case '/redirect':header('Location: /ok',true,303);exit;
 case '/created':http_response_code(201);echo 'CREATED_BODY';break;
 case '/no-content':http_response_code(204);exit;
 case '/unavailable':http_response_code(503);header('Content-Type: application/json');echo '{"error":"Service temporarily unavailable"}';break;
 case '/xml-ok':header('Content-Type: application/xml');echo '<catalog><item>ok</item></catalog>';break;
 case '/binary-ok':header('Content-Type: application/octet-stream');echo "\x00\xFFfixture";break;
 case '/view-error':require VIEW;break;
 case '/admin/error':$_SESSION['user']=['role'=>'admin','id'=>'synthetic-fixture-admin'];require VIEW;break;
 case '/api/error':echo 'PRIVATE_PARTIAL_SENTINEL';throw new RuntimeException('PRIVATE_MESSAGE_SENTINEL password=fixture-only');
 case '/remotedb':echo 'PRIVATE_PARTIAL_SENTINEL';throw new RuntimeException('PRIVATE_MESSAGE_SENTINEL');
 case '/support/ask':echo 'PRIVATE_PARTIAL_SENTINEL';throw new RuntimeException('PRIVATE_MESSAGE_SENTINEL');
 case '/json-error':header('Content-Type: application/json');echo '{"private":"PRIVATE_PARTIAL_SENTINEL"';throw new RuntimeException('PRIVATE_MESSAGE_SENTINEL');
 case '/nested-error':ob_start();echo 'PRIVATE_PARTIAL_SENTINEL';ob_start();echo str_repeat('PRIVATE_NESTED_SENTINEL',4000);throw new TypeError('PRIVATE_MESSAGE_SENTINEL');
 case '/header-error':header('Location: /wrong');header('Content-Disposition: attachment; filename="private.txt"');header('Content-Length: 987654');header('ETag: fixture');echo 'PRIVATE_PARTIAL_SENTINEL';throw new RuntimeException('PRIVATE_MESSAGE_SENTINEL');
 case '/fatal-error':echo str_repeat('PRIVATE_PARTIAL_SENTINEL',4000);trigger_error('PRIVATE_MESSAGE_SENTINEL',E_USER_ERROR);break;
 case '/parse-error':require INVALID;break;
 case '/head-error':echo 'PRIVATE_PARTIAL_SENTINEL';throw new RuntimeException('PRIVATE_MESSAGE_SENTINEL');
 default:http_response_code(404);echo 'NOT_FOUND';
}
ROUTER;
$router=str_replace(['BOOTSTRAP','VIEW','INVALID'],[var_export($root.'/app/bootstrap.php',true),var_export($fixture.'/view.php',true),var_export($fixture.'/invalid.php',true)],$router);
file_put_contents($fixture.'/router.php',$router);
file_put_contents($fixture.'/view.php','<?php echo "PRIVATE_PARTIAL_SENTINEL".str_repeat("private-view-output",5000); sps_missing_fixture_view_helper();');
file_put_contents($fixture.'/invalid.php','<?php function fixture_broken( {');
$environment=getenv();$environment['BAPX_TEST_MODE']='1';$environment['BAPX_REMOTE_DB_URL']='http://127.0.0.1:1/remotedb';
$process=proc_open([PHP_BINARY,'-d','output_buffering=0','-d','display_errors=1','-S',$address,$fixture.'/router.php'],
    [0=>['pipe','r'],1=>['file',$fixture.'/server.log','a'],2=>['file',$fixture.'/server.log','a']],$pipes,$fixture,$environment);
if(!is_resource($process))throw new RuntimeException('Fixture PHP server could not start');fclose($pipes[0]);
$failures=[];$assertions=0;
$check=static function(bool $ok,string $label)use(&$failures,&$assertions):void{$assertions++;if(!$ok){$failures[]=$label;echo "FAIL $label\n";}};
$request=static function(string $path,array $headers=[],bool $head=false)use($address):array{
    $received=[];$ch=curl_init('http://'.$address.$path);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>6,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_NOBODY=>$head,
        CURLOPT_HTTPHEADER=>$headers,CURLOPT_HEADERFUNCTION=>static function($c,string $line)use(&$received):int{
            if(str_contains($line,':')){[$name,$value]=explode(':',$line,2);$received[strtolower(trim($name))]=trim($value);}return strlen($line);
        }]);
    $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);unset($ch);
    return ['status'=>$status,'headers'=>$received,'body'=>is_string($body)?$body:'','transport_error'=>$err];
};
try{
    $ready=false;for($i=0;$i<60;$i++){$socket=@stream_socket_client('tcp://'.$address,$e,$s,.1);if(is_resource($socket)){fclose($socket);$ready=true;break;}usleep(50000);}
    if(!$ready)throw new RuntimeException('Fixture server is not listening');
    foreach([['/ok',200,'NORMAL_BODY'],['/json-ok',200,'{"ok":true}'],['/created',201,'CREATED_BODY'],['/no-content',204,''],['/unavailable',503,'{"error":"Service temporarily unavailable"}'],['/xml-ok',200,'<catalog><item>ok</item></catalog>'],['/binary-ok',200,"\x00\xFFfixture"],['/unknown',404,'NOT_FOUND']] as [$path,$status,$body]){
        $r=$request($path);$check($r['status']===$status&&$r['body']===$body,'Normal response preserved '.$path);
    }
    $r=$request('/redirect');$check($r['status']===303&&($r['headers']['location']??'')==='/ok'&&$r['body']==='','Normal redirect preserved');
    $ids=[];
    foreach(['/view-error','/admin/error','/api/error','/remotedb','/support/ask','/json-error','/nested-error','/header-error','/fatal-error','/parse-error','/head-error'] as $path){
        $r=$request($path,['X-Request-Id: attacker-controlled-reference'], $path==='/head-error');
        $check($r['status']===500,'Unhandled failure is HTTP500 '.$path);
        $check($r['transport_error']==='','No stale-length/transport error '.$path);
        $check(!preg_match('/PRIVATE_|sps_missing_fixture|password=|Stack trace|Fatal error|Warning:|secure database|Your request was not recorded/',$r['body']),'No partial/private/misleading output '.$path);
        $id=$r['headers']['x-request-id']??'';$check((bool)preg_match('/^[a-f0-9]{32}$/D',$id),'Server-generated request reference '.$path);$ids[]=$id;
        $check(str_contains($r['headers']['cache-control']??'','no-store'),'Failure never cached '.$path);
        $check(!isset($r['headers']['location'])&&!isset($r['headers']['content-disposition'])&&!isset($r['headers']['etag']),'Stale response headers discarded '.$path);
        if(in_array($path,['/api/error','/remotedb','/support/ask','/json-error'],true)){
            $j=json_decode($r['body'],true);$check(str_contains($r['headers']['content-type']??'','application/json')&&is_array($j)&&($j['request_id']??'')===$id&&!isset($j['detail']),'Machine failure is safe correlated JSON '.$path);
        }elseif($path==='/head-error')$check($r['body']==='','HEAD failure contains no body');
        else $check(str_contains($r['body'],'<!doctype html>')&&str_contains($r['body'],$id)&&str_contains($r['body'],'check'),'Standalone correlated HTML '.$path);
    }
    $r=$request('/view-error',['Accept: application/problem+json']);$j=json_decode($r['body'],true);
    $check($r['status']===500&&is_array($j)&&isset($j['request_id']),'JSON media types respected');
    $check(count(array_unique($ids))===count($ids),'References differ between requests');
    echo 'HTTP boundary assertions: '.($assertions-count($failures)).'/'.$assertions." passed\n";
}finally{
    proc_terminate($process);proc_close($process);
    foreach(glob($fixture.'/*')?:[] as $file)unlink($file);rmdir($fixture);
}
exit($failures===[]?0:1);
