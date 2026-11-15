<?php
declare(strict_types=1);
// Real MySQL fixture only. Never inherit production host, password or remote URL.
foreach(['BAPX_MYSQL_HOST'=>'127.0.0.1','BAPX_MYSQL_PORT'=>'33068','BAPX_MYSQL_DB'=>'sps_order_test',
    'BAPX_MYSQL_USER'=>'sps_test','BAPX_MYSQL_PASS'=>'sps_ci_fixture','BAPX_REMOTE_DB_URL'=>'http://127.0.0.1:1/remotedb','BAPX_TEST_MODE'=>'0'] as $key=>$value){putenv($key.'='.$value);$_SERVER[$key]=$value;}
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{DatabaseService,SupportTicketService};
$store=new DatabaseService(true);$mode=$argv[1]??'';
$conversation=$argv[2]??bin2hex(random_bytes(16));$trace=$argv[3]??bin2hex(random_bytes(8));
$handoff=['conversation_id'=>$conversation,'trace_id'=>$trace,'turn_id'=>str_repeat('f',32)];
$email='support-concurrency@example.invalid';$id=SupportTicketService::actionIdentity($email,$handoff);
if($mode==='create') {
    $ticket=(new SupportTicketService($store))->create($email,'Disposable support request','isolated CI fixture',$handoff);
    echo json_encode(['id'=>$ticket['id']],JSON_THROW_ON_ERROR),"\n";exit;
}
if($mode==='claim') {
    try{$r=$store->updateSupportTicket($id,'assign',[],['role'=>'admin','sub'=>(string)$argv[4]],0);echo json_encode(['result'=>'updated','version'=>$r['version']],JSON_THROW_ON_ERROR),"\n";}
    catch(InvalidArgumentException $e){if($e->getMessage()!=='Ticket changed; reload before saving.')throw $e;echo "{\"result\":\"stale\"}\n";}
    exit;
}
$pdo=$store->connection();if($pdo->query('SELECT DATABASE()')->fetchColumn()!=='sps_order_test')throw new RuntimeException('Refuse a nonfixture database');
$pdo->exec('CREATE TABLE IF NOT EXISTS support_tickets (id VARCHAR(36) PRIMARY KEY,_data JSON NOT NULL,_owner VARCHAR(255),_status VARCHAR(50),_created_at VARCHAR(40),_updated_at VARCHAR(40)) ENGINE=InnoDB');
$run=static function(string $mode,int $n)use($conversation,$trace):array{
    $jobs=[];$result=[];
    for($i=0;$i<$n;$i++){$p=proc_open([PHP_BINARY,__FILE__,$mode,$conversation,$trace,'fixture-admin-'.$i],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($p))throw new RuntimeException('Worker unavailable');fclose($pipes[0]);$jobs[]=[$p,$pipes];}
    foreach($jobs as [$p,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0)throw new RuntimeException('Fixture worker failed: '.$err);$result[]=json_decode(trim($out),true,512,JSON_THROW_ON_ERROR);}
    return $result;
};
try {
    $rows=$run('create',6);
    if(count(array_unique(array_column($rows,'id')))!==1)throw new RuntimeException('Repeated action created multiple identities');
    $count=$pdo->prepare('SELECT COUNT(*) FROM support_tickets WHERE id=?');$count->execute([$id]);
    if((int)$count->fetchColumn()!==1)throw new RuntimeException('Exactly one support ticket must exist');
    $claims=$run('claim',2);$results=array_count_values(array_column($claims,'result'));
    if(($results['updated']??0)!==1||($results['stale']??0)!==1)throw new RuntimeException('Concurrent staff ownership was not protected');
    $ticket=$store->find('support_tickets',$id);
    if(($ticket['version']??0)!==1||($ticket['conversation_id']??'')!==$conversation)throw new RuntimeException('Handoff/version lost');
    $actor=['role'=>'admin','sub'=>$ticket['assigned_admin_id']];
    $reply=$store->updateSupportTicket($id,'reply',['reply'=>'Fixture reply'],$actor,1);
    $closed=$store->updateSupportTicket($id,'close',[],$actor,2);
    $opened=$store->updateSupportTicket($id,'reopen',[],$actor,3);
    if(empty($reply['first_human_response_at'])||($opened['first_human_response_at']??null)!==$reply['first_human_response_at']||($opened['reopen_count']??0)!==1||count($opened['lifecycle'])!==4)throw new RuntimeException('Human lifecycle evidence changed incorrectly');
    echo "Support MySQL: six repeated actions create one ticket; competing claims reject a stale writer; reply/close/reopen preserve evidence.\n";
}finally{$q=$pdo->prepare('DELETE FROM support_tickets WHERE id=? AND _owner=?');$q->execute([$id,$email]);}
