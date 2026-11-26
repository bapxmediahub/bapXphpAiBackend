<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{MerchantApiService,MerchantFeedService,SettingsService,SiteProfileService};
$args=$argv;array_shift($args);$operation=array_shift($args)??'check';
if(in_array('--help',$args,true)||$operation==='help'){
    echo "Usage: ./bapXphp merchant:check [--json|--schema]\n"
        ."       ./bapXphp merchant:feed\n"
        ."       ./bapXphp merchant:inputs --language <code> --feed-label <label>\n"
        ."       ./bapXphp merchant:inputs --language <code> --feed-label <label> --youtube-group-id <assigned-group> --channel-id <UC...> [--channel-id <UC...>]\n"
        ."       ./bapXphp merchant:diagnose --account <id> --data-source <id> --language <code> --feed-label <label> --country <code>\n"
        ."Preview only: no Google upload or channel link. Diagnostics use an authorized ephemeral BAPX_MERCHANT_ACCESS_TOKEN in the operator environment, never a CLI argument.\n";exit;
}
if(in_array($operation,['inputs','diagnose'],true)){
    $allowed=$operation==='inputs'?['--language','--feed-label','--youtube-group-id','--channel-id']
        :['--account','--data-source','--language','--feed-label','--country'];
    $options=[];$channels=[];
    try {
        for($i=0;$i<count($args);$i++){
            $key=$args[$i];$value=$args[++$i]??null;
            if(!in_array($key,$allowed,true)||!is_string($value)||$value===''||str_starts_with($value,'--'))throw new InvalidArgumentException('Invalid option.');
            if($key==='--channel-id'){$channels[]=$value;continue;}
            if(isset($options[$key]))throw new InvalidArgumentException('Duplicate option.');
            $options[$key]=$value;
        }
        $language=$options['--language']??'';$label=$options['--feed-label']??'';
        MerchantApiService::validateLocale($language,$label);
        if($operation==='diagnose'){
            foreach(['--account','--data-source'] as $key)if(!preg_match('/^[1-9][0-9]{0,19}$/D',$options[$key]??''))throw new InvalidArgumentException('Account and source required.');
            if(!preg_match('/^[A-Z]{2}$/D',$options['--country']??''))throw new InvalidArgumentException('Country required.');
        }elseif(isset($options['--youtube-group-id'])!==($channels!==[])){
            throw new InvalidArgumentException('Partner preview requires an assigned group and actual channel IDs together.');
        }
    }catch(InvalidArgumentException){
        fwrite(STDERR,"Invalid Merchant API options. See merchant:$operation --help.\n");exit(2);
    }
    try {
        if(!(new SettingsService())->moduleEnabled('shop'))throw new RuntimeException('Shop unavailable');
        $service=new MerchantFeedService();$report=$service->inspect();$api=new MerchantApiService();
        if($operation==='inputs'){
            $custom=[];
            if($channels!==[]){
                $storeUrl=substr($report['feed_url'],0,-strlen(MerchantFeedService::FEED_PATH));
                $custom=MerchantApiService::partnerPreviewAttributes($storeUrl,SiteProfileService::current()->name(),$options['--youtube-group-id'],$channels);
            }
            $result=$api->preview($report,$language,$label,$custom);
        }else{
            // Never persist, print or accept the access token as a command argument.
            $token=getenv('BAPX_MERCHANT_ACCESS_TOKEN');
            if($token===false||$token==='')throw new DomainException('Authorized Merchant API access token is not available in the operator environment.');
            $result=$api->diagnose($report,$options['--account'],$options['--data-source'],$language,$label,$options['--country'],$token);
            unset($token);
        }
        echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";exit;
    }catch(DomainException|InvalidArgumentException $error){
        // Only fixed service messages; no external response body or token.
        fwrite(STDERR,"Merchant API operation unavailable: ".$error->getMessage()."\n");exit(1);
    }catch(Throwable){
        fwrite(STDERR,"Merchant API operation unavailable; no data was uploaded.\n");exit(1);
    }
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
