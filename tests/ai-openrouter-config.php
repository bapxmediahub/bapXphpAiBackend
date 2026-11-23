<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
putenv('BAPX_TEST_MODE=1');
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{AiClient,SecretService};
$check=static function(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);};
$fixture=new class extends SecretService {
    public array $settings=[];
    public function all():array{return $this->settings;}
};
$fixture->settings=[
    'ai_openrouter_enabled'=>'1','ai_openrouter_key'=>'fixture-secret-not-a-real-key',
    'ai_api_key'=>'fixture-google-key','agent_model'=>SecretService::PRIMARY_MODEL,
];
$selected=$fixture->getModelConfig();
$check($selected['provider']==='openai_compatible','OpenRouter must reuse the compatible transport');
$check($selected['endpoint']===SecretService::OPENROUTER_ENDPOINT,'OpenRouter endpoint must be pinned');
$check($selected['models']===[SecretService::OPENROUTER_MODEL],'Only the free router is allowed');
$check($selected['google_fallback']===true,'Google fallback must be enabled');
$google=$fixture->googleModelConfig();
$check($google['provider']==='google' && $google['model']===SecretService::PRIMARY_MODEL,'Google fallback must remain independent');
$fixture->settings['ai_openrouter_enabled']='0';
$check($fixture->getModelConfig()['provider']==='google','Disabled OpenRouter must use Google');
$fixture->settings['ai_openrouter_enabled']='1';
$fixture->settings['ai_openrouter_key']='';
$check($fixture->getModelConfig()['provider']==='google','Missing OpenRouter key must not break existing Google');
$fixture->settings['ai_openrouter_key']='fixture-secret-not-a-real-key';
$fixture->settings['ai_api_key']='';
$check($fixture->getModelConfig()['provider']==='openai_compatible','OpenRouter can work without Google key');
$check($fixture->googleModelConfig()['configured']===false,'Unavailable Google must not claim a fallback');
$check(AiClient::googleModelPair(SecretService::PRIMARY_MODEL)===[SecretService::PRIMARY_MODEL,SecretService::FALLBACK_MODEL],'Google fallback order must stay intact');
echo "PASS OpenRouter selection, disabled state, key isolation and Google fallback config.\n";
