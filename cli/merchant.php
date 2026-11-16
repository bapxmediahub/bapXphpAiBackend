<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{MerchantFeedService,SettingsService};
$args=$argv;array_shift($args);$operation=array_shift($args)??'check';
if(in_array('--help',$args,true)||$operation==='help'){
    echo "Usage: ./bapXphp merchant:check [--json|--schema]\n       ./bapXphp merchant:feed\nRead-only current catalogue projection. No Google upload, account approval or YouTube connection.\n";exit;
}
if(!in_array($operation,['check','feed'],true)||array_diff($args,['--json','--schema'])!==[]||($operation==='feed'&&$args!==[])){
    fwrite(STDERR,"Unsupported merchant command or option. Use merchant:check --help.\n");exit(2);
}
if(in_array('--schema',$args,true)){echo json_encode(MerchantFeedService::contract(),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";exit;}
try{
    if(!(new SettingsService())->moduleEnabled('shop'))throw new RuntimeException('Shop unavailable');
    $service=new MerchantFeedService();$report=$service->inspect();
    if($operation==='feed'){echo $service->xml($report);exit;}
    $output=MerchantFeedService::contract()+['result'=>array_diff_key($report,['items'=>true])];
    if(in_array('--json',$args,true))echo json_encode($output,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    else echo 'Catalogue feed: '.$report['exported_count'].' exported, '.$report['excluded_count'].' excluded, '.$report['warning_count']." with warnings.\n".$report['feed_url']."\nGoogle approval and YouTube eligibility/linking are unverified.\n";
}catch(Throwable $error){
    $out=['schema_version'=>1,'operation'=>'merchant.catalogue.'.$operation,'read_only'=>true,'error'=>'catalogue_unavailable'];
    if(in_array('--json',$args,true))echo json_encode($out,JSON_THROW_ON_ERROR)."\n";
    else fwrite(STDERR,"Catalogue feed unavailable; no upload or database change was performed.\n");exit(1);
}
