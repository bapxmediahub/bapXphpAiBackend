<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\RateLimiter;
if (($argv[1]??'')==='worker') {
    echo RateLimiter::consumeContact('192.0.2.'.(int)$argv[3],'fixture'.$argv[3].'@example.invalid',$argv[2],1800000000) ? '1' : '0';
    exit;
}
function check(bool $ok,string $msg): void {if(!$ok)throw new RuntimeException($msg);}
$dir=sys_get_temp_dir().'/bapx-contact-fixture-'.bin2hex(random_bytes(8));mkdir($dir,0700);
$now=1800000000;
try{
    $path=$dir.'/ip.json';
    for($i=0;$i<3;$i++)check(RateLimiter::consumeContact('192.0.2.1',"a$i@example.invalid",$path,$now),'First three IP admissions allowed');
    check(!RateLimiter::consumeContact('192.0.2.1','a3@example.invalid',$path,$now),'IP budget denied');
    check(RateLimiter::consumeContact('192.0.2.1','a3@example.invalid',$path,$now+120),'IP window expires');
    $path=$dir.'/recipient.json';
    check(RateLimiter::consumeContact('192.0.2.1','Example@EXAMPLE.invalid',$path,$now),'First recipient allowed');
    check(RateLimiter::consumeContact('192.0.2.2','example@example.invalid',$path,$now),'Second recipient allowed');
    check(!RateLimiter::consumeContact('192.0.2.3','example@example.invalid',$path,$now),'Normalized recipient limit across IPs');
    $path=$dir.'/site.json';$jobs=[];
    for($i=1;$i<=32;$i++){
        $proc=proc_open([PHP_BINARY,__FILE__,'worker',$path,(string)$i],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        check(is_resource($proc),'Worker starts');fclose($pipes[0]);$jobs[]=[$proc,$pipes];
    }
    $admitted=0;
    foreach($jobs as [$proc,$pipes]){
        $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        check(proc_close($proc)===0&&in_array($out,['0','1'],true),'Worker result valid');$admitted+=(int)$out;
    }
    check($admitted===20,'Exactly 20 accepted across competing workers');
    for($h=1;$h<=2;$h++)for($i=1;$i<=20;$i++)check(RateLimiter::consumeContact("192.0.$h.$i","h$h-$i@example.invalid",$path,$now+$h*3600),'Next hourly budget available');
    check(!RateLimiter::consumeContact('192.0.3.1','later@example.invalid',$path,$now+3*3600),'Daily budget enforced');
    check(RateLimiter::consumeContact('192.0.3.1','later@example.invalid',$path,$now+86400),'Daily budget expires');
    check(!str_contains(file_get_contents($path),'@example.invalid'),'No raw recipient in state');
    file_put_contents($path,'bad JSON');$closed=false;
    try{RateLimiter::consumeContact('192.0.3.1','later@example.invalid',$path,$now);}catch(Throwable){$closed=true;}
    check($closed,'Corrupt storage fails closed');
    echo "Contact admission: concurrent site cap, IP, normalized recipient, daily cap, expiry, private state, failure-closed PASS.\n";
}finally{
    foreach(glob($dir.'/*')?:[] as $file)unlink($file);rmdir($dir);
}
