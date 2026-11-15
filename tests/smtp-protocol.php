<?php
declare(strict_types=1);
// Loopback-only fake SMTP server. No production credentials, recipients or network host.
if (($argv[1]??'')==='server') {
    $mode=$argv[2]??'accept';
    $server=stream_socket_server('tcp://127.0.0.1:0',$error,$description);
    if(!$server)throw new RuntimeException('Fixture listener unavailable');
    echo stream_socket_get_name($server,false),"\n";fflush(STDOUT);
    $client=stream_socket_accept($server,8);if(!$client)exit(2);
    stream_set_timeout($client,5);fwrite($client,"220 fixture\r\n");$auth=0;$data=false;
    while(($line=fgets($client))!==false){
        if($data){if(rtrim($line,"\r\n")!=='.')continue;
            $data=false;
            if($mode==='unknown'){fclose($client);break;}
            if($mode==='reject'){fwrite($client,"550 rejected fixture\r\n");fclose($client);break;}
            fwrite($client,"250 accepted fixture\r\n");
            if($mode==='accept_close'){fclose($client);break;}
            continue;
        }
        if($auth===1){$auth=2;fwrite($client,"334 password\r\n");continue;}
        if($auth===2){$auth=0;fwrite($client,"235 authenticated fixture\r\n");continue;}
        if(str_starts_with($line,'EHLO ')){fwrite($client,"250 fixture\r\n");continue;}
        if(str_starts_with($line,'AUTH LOGIN')){$auth=1;fwrite($client,"334 username\r\n");continue;}
        if(str_starts_with($line,'MAIL FROM:')||str_starts_with($line,'RCPT TO:')){fwrite($client,"250 ok\r\n");continue;}
        if(str_starts_with($line,'DATA')){$data=true;fwrite($client,"354 body\r\n");continue;}
        if(str_starts_with($line,'QUIT')){fwrite($client,"221 bye\r\n");break;}
        fwrite($client,"500 unsupported fixture\r\n");
    }
    if(is_resource($client))fclose($client);fclose($server);exit;
}
require dirname(__DIR__).'/app/bootstrap.php';
$legacy=getenv('SPS_TEST_SMTP_SOURCE');if(is_string($legacy)&&$legacy!=='')require $legacy;
$failures=[];
foreach(['accept','accept_close','reject','unknown'] as $mode){
    $child=proc_open([PHP_BINARY,__FILE__,'server',$mode],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($child))throw new RuntimeException('SMTP fixture unavailable');fclose($pipes[0]);
    $address=trim((string)fgets($pipes[1]));
    if(!preg_match('/^127\.0\.0\.1:(\d+)$/D',$address,$m))throw new RuntimeException('Fixture did not bind loopback');
    try{
        $mailer=new App\Services\SmtpMailer(['smtp_host'=>'127.0.0.1','smtp_port'=>(int)$m[1],
            'smtp_encryption'=>'none','smtp_username'=>'fixture','smtp_password'=>'not-a-real-secret','mail_from_email'=>'fixture@example.invalid']);
        $receipt=$mailer->send('recipient@example.invalid','Protocol fixture','<p>Local test only</p>','<fixture@example.invalid>');
        if(!in_array($mode,['accept','accept_close'],true)||!($receipt['accepted']??false)||($receipt['message_id']??'')!=='<fixture@example.invalid>')throw new RuntimeException('Receipt classification incorrect');
        echo 'PASS SMTP '.$mode."\n";
    }catch(Throwable $e){
        $expected=$mode==='unknown'&&$e instanceof App\Services\SmtpDeliveryUncertainException
            ||$mode==='reject'&&$e->getCode()===550&&!($e instanceof App\Services\SmtpDeliveryUncertainException);
        if($expected)echo 'PASS SMTP '.$mode."\n";
        else{$failures[]=$mode;echo 'FAIL SMTP '.$mode.' ('.$e::class.")\n";}
    }finally{
        foreach([1,2] as $i){stream_get_contents($pipes[$i]);fclose($pipes[$i]);}
        proc_terminate($child);proc_close($child);
    }
}
exit($failures===[]?0:1);
