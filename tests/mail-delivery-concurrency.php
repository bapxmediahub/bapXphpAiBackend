<?php
declare(strict_types=1);
foreach(['BAPX_MYSQL_HOST'=>'127.0.0.1','BAPX_MYSQL_PORT'=>'33068','BAPX_MYSQL_DB'=>'sps_order_test',
    'BAPX_MYSQL_USER'=>'sps_test','BAPX_MYSQL_PASS'=>'sps_ci_fixture','BAPX_REMOTE_DB_URL'=>'http://127.0.0.1:1/remotedb','BAPX_TEST_MODE'=>'0'] as $k=>$v){putenv($k.'='.$v);$_SERVER[$k]=$v;}
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{ContactService,DatabaseService,MailQueueService,SmtpMailer};
$store=new DatabaseService(true);$id=$argv[2]??bin2hex(random_bytes(8));
if(($argv[1]??'')==='claim'){$a=bin2hex(random_bytes(16));$r=$store->transitionMailDelivery($id,'claim',$a,[]);echo json_encode(['claimed'=>$r!==null,'attempt'=>$r['attempt_id']??null]),"\n";exit;}
$pdo=$store->connection();if($pdo->query('SELECT DATABASE()')->fetchColumn()!=='sps_order_test')throw new RuntimeException('Refuse a nonfixture database');
foreach(['mail_queue','mail_outbox','contact_submissions','mail_inbox'] as $table)$pdo->exec("CREATE TABLE IF NOT EXISTS $table (id VARCHAR(36) PRIMARY KEY,_data JSON NOT NULL,_owner VARCHAR(255),_status VARCHAR(50),_created_at VARCHAR(40),_updated_at VARCHAR(40)) ENGINE=InnoDB");
$other=bin2hex(random_bytes(8));$heldId=bin2hex(random_bytes(8));$contactId='contact_fixture_'.bin2hex(random_bytes(5));
$sampleEvent = 'fixture:' . bin2hex(random_bytes(8));
$recipient = 'recipient@example.invalid';
$smtpId = MailQueueService::deliveryIdentity(MailQueueService::SCHEDULED_SAMPLE_TYPE, $recipient, $sampleEvent);
$row=static fn($id)=>['id'=>$id,'status'=>'pending','available_at'=>gmdate('c',time()-5),'to'=>'recipient@example.invalid','subject'=>'Fixture','html'=>'<p>Local test only</p>','created_at'=>gmdate('c')];
try{
    $store->upsert('mail_queue',$row($id));$store->upsert('mail_queue',$row($other));
    $heldRow=$row($heldId)+['type'=>'admin_notification','meta'=>['contact_id'=>$contactId]];
    $store->upsert('mail_queue',$heldRow);
    if($store->transitionMailDelivery($heldId,'claim',bin2hex(random_bytes(16)),[])!==null)
        throw new RuntimeException('Held contact acquired a database claim');
    if(in_array($heldId,array_column((new MailQueueService($store))->due(),'id'),true))
        throw new RuntimeException('Held contact was selected by worker');
    if($store->find('mail_queue',$heldId)['status']!=='pending')
        throw new RuntimeException('Historical held row was rewritten');
    $before=(int)$pdo->query('SELECT COUNT(*) FROM mail_queue')->fetchColumn();
    (new ContactService($store))->save(['id'=>$contactId,'name'=>'Fixture','email'=>'fixture@example.invalid','subject'=>'general','message'=>'A valid fixture enquiry']);
    if(!$store->find('contact_submissions',$contactId)||!$store->find('mail_inbox','in_'.$contactId))
        throw new RuntimeException('Contact or inbox persistence missing');
    if((int)$pdo->query('SELECT COUNT(*) FROM mail_queue')->fetchColumn()!==$before)
        throw new RuntimeException('Saving contact unexpectedly queued mail');
    $jobs=[];$results=[];
    for($i=0;$i<6;$i++){$p=proc_open([PHP_BINARY,__FILE__,'claim',$id],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$jobs[]=[$p,$pipes];}
    foreach($jobs as [$p,$pipes]){$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0)throw new RuntimeException('Fixture claim worker failed');$results[]=json_decode($out,true,512,JSON_THROW_ON_ERROR);}
    $claimed=array_values(array_filter($results,fn($r)=>$r['claimed']));
    if(count($claimed)!==1)throw new RuntimeException('Expected one sender');
    $store->transitionMailDelivery($id,'uncertain',$claimed[0]['attempt'],['error_code'=>'smtp_outcome_unknown']);
    if($store->transitionMailDelivery($id,'claim',bin2hex(random_bytes(16)),[])!==null)throw new RuntimeException('Uncertain mail retried');
    if($store->find('mail_queue',$other)['status']!=='pending')throw new RuntimeException('Unrelated mail was rewritten');
    $queue = new MailQueueService($store);
    $sample = $queue->enqueueTemplateSample('welcome', $recipient, $sampleEvent, true);
    $delay = strtotime($sample['available_at']) - strtotime($sample['created_at']);
    if ($sample['id'] !== $smtpId || $sample['status'] !== 'pending' || $delay < 119 || $delay > 120)
        throw new RuntimeException('Scheduled sample was not persisted with its expected delay');
    if ($queue->processDue(scheduledSamplesOnly: true) !== 0 || $store->find('mail_queue', $smtpId)['status'] !== 'pending')
        throw new RuntimeException('Sample attempted SMTP before becoming eligible');
    if ($store->transitionMailDelivery($other, 'claim', bin2hex(random_bytes(16)), ['scheduled_samples_only' => true]) !== null)
        throw new RuntimeException('Ordinary due mail acquired a sample-only claim');
    $sample['available_at'] = gmdate('c', time() - 1);
    $store->upsert('mail_queue', $sample);
    if (in_array($smtpId, array_column($queue->due(), 'id'), true))
        throw new RuntimeException('Scheduled diagnostic leaked into ordinary worker selection');
    $p=proc_open([PHP_BINARY,__DIR__.'/smtp-protocol.php','server','accept_close'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);
    $address=trim((string)fgets($pipes[1]));if(!preg_match('/^127\.0\.0\.1:(\d+)$/D',$address,$m))throw new RuntimeException('Invalid fixture SMTP host');
    try{
        $mailer=new SmtpMailer(['smtp_host'=>'127.0.0.1','smtp_port'=>(int)$m[1],'smtp_encryption'=>'none','smtp_username'=>'fixture','smtp_password'=>'fixture','mail_from_email'=>'sender@example.invalid']);
        if ($queue->processDue($mailer, scheduledSamplesOnly: true) !== 1)
            throw new RuntimeException('Sample-only worker did not record exactly one SMTP acceptance');
        $saved=$store->find('mail_queue',$smtpId);
        if($saved['status']!=='sent'||$saved['delivery_state']!=='accepted_by_smtp'||$saved['inbox_receipt']!=='unverified')throw new RuntimeException('Receipt evidence incorrect');
        $outbox = $store->find('mail_outbox', 'out_' . $smtpId);
        if (($outbox['status'] ?? null) !== 'sent' || ($outbox['message_id'] ?? null) !== $saved['message_id'])
            throw new RuntimeException('Outbox did not project canonical sample acceptance');
        if ($queue->processDue($mailer, scheduledSamplesOnly: true) !== 0)
            throw new RuntimeException('Already accepted sample was retried');
        foreach ([$other, $heldId] as $untouched) {
            if ($store->find('mail_queue', $untouched)['status'] !== 'pending')
                throw new RuntimeException('Sample worker rewrote unrelated historical mail');
        }
    }finally{foreach([1,2] as $i){stream_get_contents($pipes[$i]);fclose($pipes[$i]);}proc_terminate($p);proc_close($p);}
    echo "Mail MySQL: atomic single claim, uncertain replay blocked, scheduled SMTP accepted once and projected to Outbox, unrelated due and held rows preserved.\n";
}finally{
    foreach([$id,$other,$smtpId,$heldId] as $clean){$q=$pdo->prepare('DELETE FROM mail_queue WHERE id=?');$q->execute([$clean]);}
    $q=$pdo->prepare('DELETE FROM mail_outbox WHERE id=?');$q->execute(['out_'.$smtpId]);
    $q=$pdo->prepare('DELETE FROM contact_submissions WHERE id=?');$q->execute([$contactId]);
    $q=$pdo->prepare('DELETE FROM mail_inbox WHERE id=?');$q->execute(['in_'.$contactId]);
}
