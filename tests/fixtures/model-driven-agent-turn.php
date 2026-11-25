<?php
declare(strict_types=1);
namespace {
    if(PHP_SAPI!=='cli'||getenv('BAPX_MODEL_DRIVEN_FIXTURE')!=='1')exit(1);
}
namespace App\Services {
    // Only the configured endpoint is substituted. The real AiClient, registries,
    // instruction manifests, controllers and conversation persistence execute.
    final class SecretService {
        public const PRIMARY_MODEL='fixture-model', FALLBACK_MODEL='fixture-model';
        public const GOOGLE_MODELS=['fixture-model'];
        public static function validModelId(string $model): bool {return $model==='fixture-model';}
        public function getProviderPlan(): array {
            $endpoint=(string)getenv('BAPX_FIXTURE_ENDPOINT');
            if(!preg_match('~^http://127\.0\.0\.1:[0-9]+/[a-z_]+$~D',$endpoint))throw new \RuntimeException('Only loopback fixtures permitted');
            return [['provider'=>'google','endpoint'=>$endpoint,'apiKey'=>'fixture-only','models'=>['fixture-model'],'model'=>'fixture-model']];
        }
        public function getModelConfig(): array {return $this->getProviderPlan()[0];}
        public function all(): array {return [];}
    }
}
namespace {
    putenv('BAPX_TEST_MODE=1');
    date_default_timezone_set('Asia/Kolkata');
    function app_path(string $path=''): string{return dirname(__DIR__,2).'/'.$path;}
    function storage_path(string $path=''): string{return $GLOBALS['directory'].'/'.$path;}
    function module_on(string $key): bool{return (new \App\Services\SettingsService())->moduleEnabled($key);}
    function e(string $value): string{return htmlspecialchars($value,ENT_QUOTES,'UTF-8');}
    spl_autoload_register(static function(string $class):void{
        if(str_starts_with($class,'App\\')){ $path=app_path('app/'.str_replace('\\','/',substr($class,4)).'.php');if(is_file($path))require $path; }
    });
    $directory=sys_get_temp_dir().'/bapx-model-turn-'.bin2hex(random_bytes(8));
    mkdir($directory,0700);mkdir($directory.'/logs',0700);
    $scenario=$argv[1];$surface=$argv[2];$role=$argv[3];
    $_SERVER['REQUEST_METHOD']='POST';$_SERVER['REMOTE_ADDR']='127.0.0.1';
    $_SERVER['REQUEST_URI']=$surface==='admin'?'/admin/agent/ask':'/support/ask';
    $_SESSION=['csrf_token'=>'fixture','support_guest_id'=>'fixture-guest'];
    if($role!=='guest')$_SESSION['user']=['role'=>$role,'id'=>'fixture-actor','email'=>'fixture@example.invalid'];
    $_POST=['message'=>$argv[4],'_csrf'=>'fixture','request_id'=>bin2hex(random_bytes(16))];
    $catalogueFollowup = str_starts_with($scenario, 'catalogue_');
    if ($catalogueFollowup) {
        $conversations = new \App\Services\AgentConversationService();
        $actor = $conversations::actor('support', null);
        $previous = $conversations->record('support', $actor, null, 'How many products were published?',
            'There are currently 43 products published in the shop.', str_repeat('b', 16), 'model');
        $_POST['conversation_id'] = $previous['conversation_id'];
    }
    ob_start();
    register_shutdown_function(static function()use($directory,$catalogueFollowup):void{
        $response=json_decode(ob_get_clean(),true);
        $files=glob($directory.'/agent-conversations/*.json')?:[];
        $turns=$files?(json_decode(file_get_contents($files[0]),true)['turns']??[]):[];
        $result=['status'=>http_response_code(),'response'=>$response,'turn'=>$turns[array_key_last($turns)]??[]];
        if($catalogueFollowup)$result['history_preserved']=count($turns)===2
            &&($turns[0]['answer']??null)==='There are currently 43 products published in the shop.';
        echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST) as $entry){
            if($entry->isDir())rmdir($entry->getPathname());else unlink($entry->getPathname());
        }
        rmdir($directory);
    });
    if(getenv('BAPX_FIXTURE_PROTOCOL')==='compatible'){
        $actor=$_SESSION['user']??null;
        $tools=new \App\Services\RuntimeAgentToolRegistry($surface,$actor,
            $surface==='admin'?new \App\Services\EnterpriseToolRegistry(ownerQuestion:$argv[4]):new \App\Services\SupportToolRegistry($actor),$argv[4]);
        $traceId=bin2hex(random_bytes(8));
        \App\Services\AgentTraceService::begin($traceId,null,\App\Services\AiClient::agentLimits());
        $client=new \App\Services\AiClient();
        $config=(new \App\Services\SecretService())->getProviderPlan()[0];
        $config['provider']='openai_compatible';$config['configured']=true;
        $method=new ReflectionMethod(\App\Services\AiClient::class,'completeCompatibleLoop');
        $answer=$method->invoke($client,$config,$tools->instructionPrompt()."\n".$argv[4],1024,null,$tools,4,hrtime(true)+110000000000,false,[CURLOPT_PROXY=>'',CURLOPT_FOLLOWLOCATION=>false]);
        $outcome=\App\Services\AiClient::isError($answer)||trim($answer)===''?'error':'model';
        $conversation=new \App\Services\AgentConversationService();
        $identity=$conversation::actor($surface,$actor);
        $prepared=$conversation->prepareTurn($surface,$identity,null,$argv[4],$traceId);
        $business=$tools->activity();
        $record=$conversation->record($surface,$identity,$prepared['conversation_id'],$argv[4],$answer,$traceId,$outcome,$business,null,null,$client->transferTimings(),$tools->retrieval());
        http_response_code($outcome==='model'?200:502);
        echo json_encode(['answer'=>$answer,'ticket_id'=>null]+$record,JSON_THROW_ON_ERROR);
        exit;
    }
    if($surface==='admin')(new \App\Controllers\AdminController())->agentAsk();
    else (new \App\Controllers\SupportController())->ask();
}
