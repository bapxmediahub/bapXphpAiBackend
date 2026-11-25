<?php
declare(strict_types=1);
putenv('BAPX_TEST_MODE=1');
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{AiClient,SecretService};
function expect(bool $ok,string $message):void { if(!$ok)throw new RuntimeException($message); }
if(!function_exists('curl_init')) {
    $client=new AiClient();
    $route=new ReflectionMethod(AiClient::class,'route');
    $plan=[['provider'=>'google','endpoint'=>SecretService::GOOGLE_ENDPOINT,'apiKey'=>'fixture','model'=>'gemma-4-31b-it','models'=>['gemma-4-31b-it'],'configured'=>true]];
    $answer=$route->invoke($client,'Fixture',32,0.0,null,null,0,$plan);
    expect(AiClient::isError($answer)&&$client->failureCategory()==='transport'&&$client->transferTimings()===[],'Missing cURL must return a recognized transport failure before sending');
    echo "PASS missing-cURL text guard\n";
    exit;
}
$guardOutput=[];$guardStatus=0;
exec(escapeshellarg(PHP_BINARY).' -d disable_functions=curl_init '.escapeshellarg(__FILE__).' 2>&1',$guardOutput,$guardStatus);
expect($guardStatus===0&&in_array('PASS missing-cURL text guard',$guardOutput,true),'Missing-cURL subprocess must succeed');

$settings=['ai_api_key'=>'fixture-google','ai_google_enabled'=>'1','ai_google_models'=>"gemma-4-31b-it\nother-google-model",'ai_google_disabled_models'=>['gemma-4-31b-it'],'ai_openrouter_key'=>'fixture-router-key-12345','ai_openrouter_enabled'=>'1','ai_openrouter_models'=>"openrouter/free\nvendor/model:free",'ai_provider'=>'google','ai_compatible_enabled'=>'1','ai_compatible_url'=>'https://example.com/v1','ai_compatible_key'=>'fixture-compatible','ai_compatible_models'=>'vendor/model'];
$s=new SecretService();
$plan=$s->providerPlan($settings);
expect(array_column($plan,'provider')===['google','openai_compatible','openrouter'],'Independent enabled providers use the configured fallback order');
expect($plan[0]['models']===['other-google-model'],'Disabled Google model is excluded');
expect($plan[0]['endpoint']==='https://generativelanguage.googleapis.com/v1beta/openai'
    && $plan[0]['apiKey']==='fixture-google','Google uses its saved key and the Chat Completions base');
expect($plan[2]['endpoint']==='https://openrouter.ai/api/v1','OpenRouter keeps its compatible base');

expect($plan[2]['models']===['openrouter/free','vendor/model:free'],'OpenRouter model IDs are editable');
$settings['ai_google_enabled']='0';
expect(array_column($s->providerPlan($settings),'provider')===['openai_compatible','openrouter'],'Disabled Google does not run as a hidden fallback');
$settings['ai_openrouter_enabled']='0';
$settings['ai_provider']='openai_compatible';
$settings['ai_compatible_url']='https://api.openai.com/v1/chat/completions';
$settings['ai_compatible_models']="gpt-4.1-mini\ngpt-4o-mini";
$plan=$s->providerPlan($settings);
expect(count($plan)===1 && $plan[0]['provider']==='openai_compatible' && $plan[0]['endpoint']==='https://api.openai.com/v1'
    && $plan[0]['models']===['gpt-4.1-mini','gpt-4o-mini'],'Both agents can use only the saved Chat Completions connection and its ordered model IDs');
expect($plan[0]['tokenField'] === null, 'Existing settings use automatic token field selection');
$settings['ai_compatible_token_field'] = 'max_completion_tokens';
expect($s->providerPlan($settings)[0]['tokenField'] === 'max_completion_tokens', 'Custom endpoints can request the current token limit field');
$settings['ai_compatible_token_field'] = 'max_tokens';
expect($s->providerPlan($settings)[0]['tokenField'] === 'max_tokens', 'Legacy endpoints can explicitly retain their token limit field');
$settings['ai_compatible_enabled']='0';
expect($s->providerPlan($settings)===[],'All disabled providers produce no route');
$duplicatePlan=[
    ['provider'=>'openrouter','models'=>['shared/model'],'model'=>'shared/model','endpoint'=>'https://openrouter.ai/api/v1','configured'=>true],
    ['provider'=>'openai_compatible','models'=>['shared/model','other'],'model'=>'shared/model','endpoint'=>'https://example.com/v1','configured'=>true],
];
expect(array_column(AiClient::probeTargets($duplicatePlan),'target')===['openrouter|shared/model','openai_compatible|shared/model','openai_compatible|other'],'Probe identity includes the provider, not just model ID');
$exact=AiClient::probePlan($duplicatePlan,'openai_compatible|shared/model');
expect(count($exact)===1&&$exact[0]['provider']==='openai_compatible'&&$exact[0]['models']===['shared/model'],'Exact target cannot silently fall back');
expect(AiClient::probePlan($duplicatePlan,'google|unknown')===[],'Unknown probe target must not invoke a different model');
expect(!AiClient::probeSucceeded('unrelated answer','connection ok',0,false),'Nonempty text is not a passed connection test');
expect(AiClient::probeSucceeded('connection ok','connection ok',0,false),'Exact text response succeeds');
expect(!AiClient::probeSucceeded('challenge','challenge',0,true),'Guessing the response without tool execution is not a passed tool test');
expect(AiClient::probeSucceeded('challenge','challenge',1,true),'Executed tool and exact returned challenge required');
echo "PASS provider schema, model selection and independent fallback toggles\n";
