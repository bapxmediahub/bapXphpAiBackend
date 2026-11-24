<?php
declare(strict_types=1);
putenv('BAPX_TEST_MODE=1');
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{AiClient,SecretService};
function expect(bool $ok,string $message):void { if(!$ok)throw new RuntimeException($message); }
$decl=['name'=>'lookup','description'=>'Fixture','parameters'=>['type'=>'object','properties'=>['query'=>['type'=>'string']],'required'=>['query'],'additionalProperties'=>false]];
$wire=AiClient::googleDeclarations([$decl])[0];
expect(!isset($wire['parameters'])&&$wire['parametersJsonSchema']===$decl['parameters'],'Google must use the JSON Schema field without weakening validation');
$settings=['ai_api_key'=>'fixture-google','ai_google_enabled'=>'1','ai_google_models'=>"gemma-4-31b-it\nother-google-model",'ai_google_disabled_models'=>['gemma-4-31b-it'],'ai_openrouter_key'=>'fixture-router-key-12345','ai_openrouter_enabled'=>'1','ai_openrouter_models'=>"openrouter/free\nvendor/model:free",'ai_provider'=>'google','ai_compatible_enabled'=>'1','ai_compatible_url'=>'https://example.com/v1','ai_compatible_key'=>'fixture-compatible','ai_compatible_models'=>'vendor/model'];
$s=new SecretService();
$plan=$s->providerPlan($settings);
expect(array_column($plan,'provider')===['openrouter','google','openai_compatible'],'Independent enabled providers use the configured fallback order');
expect($plan[1]['models']===['other-google-model'],'Disabled Google model is excluded');
expect($plan[0]['models']===['openrouter/free','vendor/model:free'],'OpenRouter model IDs are editable');
$settings['ai_google_enabled']='0';
expect(array_column($s->providerPlan($settings),'provider')===['openrouter','openai_compatible'],'Disabled Google does not run as a hidden fallback');
$settings['ai_openrouter_enabled']='0';$settings['ai_compatible_enabled']='0';
expect($s->providerPlan($settings)===[],'All disabled providers produce no route');
expect(AiClient::googleAttemptPlan('other-google-model',false,['other-google-model','gemma-4-31b-it'])[0]['model']==='other-google-model','Custom Google model ID reaches route');
expect(AiClient::googleAttemptPlan('other-google-model',true,['other-google-model','gemma-4-31b-it'])===[['model'=>'other-google-model','timeout_ms'=>12000]],'Tool exchange stays locked');
echo "PASS provider schema, model selection and independent fallback toggles\n";
