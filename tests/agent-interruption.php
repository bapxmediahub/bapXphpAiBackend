<?php
declare(strict_types=1);
putenv('BAPX_TEST_MODE=1');$_SERVER['BAPX_TEST_MODE']='1';
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{AgentConversationService,AgentTraceService};
$root=$argv[2]??sys_get_temp_dir().'/sps-interruption-'.bin2hex(random_bytes(8));
$id=$argv[3]??bin2hex(random_bytes(16));$trace=$argv[4]??bin2hex(random_bytes(8));
$actor=['type'=>'guest','id'=>'interruption-fixture'];
if(($argv[1]??'')==='worker') {
    $store=new AgentConversationService($root);
    $store->prepareTurn('support',$actor,$id,'Disposable crash fixture',$trace);
    AgentTraceService::begin($trace,static fn(array $snapshot)=>$store->checkpoint('support',$actor,$id,$trace,$snapshot));
    $r=AgentTraceService::startSpan('retrieval','public_policy');AgentTraceService::endSpan($r,'complete',['result_count'=>1]);
    AgentTraceService::startSpan('tool','public_catalog');
    echo "checkpoint_saved\n";fflush(STDOUT);sleep(30);exit;
}
if(!mkdir($root,0700))throw new RuntimeException('Cannot create isolated recovery fixture');
$worker=null;
try {
    $worker=proc_open([PHP_BINARY,__FILE__,'worker',$root,$id,$trace],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($worker))throw new RuntimeException('Cannot start disposable worker');
    fclose($pipes[0]);stream_set_timeout($pipes[1],8);
    if(trim((string)fgets($pipes[1]))!=='checkpoint_saved')throw new RuntimeException('Worker did not reach durable checkpoint');
    proc_terminate($worker);fclose($pipes[1]);fclose($pipes[2]);proc_close($worker);$worker=null;
    $store=new AgentConversationService($root);$doc=$store->getForAdmin($id);
    if(count($doc['turns'])!==0||count($doc['pending_turns'])!==1)throw new RuntimeException('Interrupted work must not appear completed');
    $spans=$doc['pending_turns'][$trace]['trace']['spans'];
    if(count($spans)!==2||$spans[0]['status']!=='complete'||$spans[1]['status']!=='running'||$spans[1]['ended_at']!==null)throw new RuntimeException('Observed pre-crash evidence was not preserved');
    try{$store->prepareTurn('support',$actor,$id,'Disposable crash fixture',$trace);throw new RuntimeException('Uncertain action was replayed');}
    catch(LogicException){}
    if(count(glob($root.'/*.json')?:[])!==1)throw new RuntimeException('A chat must remain exactly one JSON file');
    echo "Interrupted worker: checkpoint survives; no fabricated completion; duplicate execution blocked.\n";
} finally {
    if(is_resource($worker)){proc_terminate($worker);proc_close($worker);}
    foreach(array_merge(glob($root.'/*')?:[],glob($root.'/.conversation*')?:[]) as $p)if(is_file($p))@unlink($p);
    @rmdir($root);
}
