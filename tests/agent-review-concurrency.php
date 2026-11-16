<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\AgentConversationService;
$root=$argv[2]??sys_get_temp_dir().'/sps-answer-review-'.bin2hex(random_bytes(8));
$id=$argv[3]??bin2hex(random_bytes(16));$turnId=$argv[4]??null;
$actor=['role'=>'admin','id'=>'fixture-reviewer'];$owner=['type'=>'guest','id'=>'fixture-owner'];
$payload=['rubric'=>['accuracy'=>'not_assessed','source_support'=>'not_assessed','task_completion'=>'pass','policy_compliance'=>'pass'],
    'reasons'=>['missing_information'],'evidence_refs'=>['response'],'note'=>'Isolated concurrency fixture; no factual claim is certified.','authoring_mode'=>'verification_test'];
$service=new AgentConversationService($root);
if(($argv[1]??'')==='worker'){
    try{$result=$service->reviewTurn($id,(string)$turnId,$actor,0,(string)$argv[5],$payload);echo json_encode(['result'=>'saved','revision'=>$result['revision']]),"\n";}
    catch(LogicException){echo "{\"result\":\"stale\"}\n";}
    exit;
}
if(!mkdir($root,0700))throw new RuntimeException('Private fixture directory unavailable');
try{
    $initial=$service->record('support',$owner,$id,'Fixture question','Fixture answer',bin2hex(random_bytes(8)),'local');
    $detail=$service->reviewDetail($id,$initial['turn_id'],$actor);$jobs=[];$outcomes=[];
    for($i=0;$i<6;$i++){
        $p=proc_open([PHP_BINARY,__FILE__,'worker',$root,$id,$initial['turn_id'],$detail['evidence_sha256']],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($p))throw new RuntimeException('Fixture worker unavailable');fclose($pipes[0]);$jobs[]=[$p,$pipes];
    }
    foreach($jobs as [$p,$pipes]){
        $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($p)!==0)throw new RuntimeException('Review fixture process failed');$outcomes[]=json_decode($output,true,512,JSON_THROW_ON_ERROR)['result'];
    }
    $counts=array_count_values($outcomes);if(($counts['saved']??0)!==1||($counts['stale']??0)!==5)throw new RuntimeException('Exactly one matching-version review should save');
    $service->record('support',$owner,$id,'Later fixture turn','Original answer remains.',bin2hex(random_bytes(8)),'local');
    $doc=$service->getForAdmin($id);
    if(count($doc['turns'])!==2||count($doc['turns'][0]['reviews'])!==1||$doc['turns'][0]['answer']!=='Fixture answer')throw new RuntimeException('Review/append lost source evidence');
    if(count(glob($root.'/*.json')?:[])!==1)throw new RuntimeException('Review created a transcript mirror');
    echo "Answer review: six competing saves yield one decision/five stale rejections; new turns preserve history in the same private JSON.\n";
}finally{foreach(glob($root.'/*')?:[] as $file)@unlink($file);foreach(glob($root.'/.conversation*')?:[] as $file)@unlink($file);@rmdir($root);}
