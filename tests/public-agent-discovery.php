<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
putenv('BAPX_TEST_MODE=1');putenv('APP_URL=https://public.example');
require dirname(__DIR__).'/app/bootstrap.php';
function discoveryCheck(bool $condition,string $message):void {
    if(!$condition)throw new RuntimeException($message);
}
$mcp=new \App\Services\PublicMcpService();
$discovery=new \App\Services\PublicAgentDiscoveryService($mcp);
$metadata=$discovery->document();
discoveryCheck($metadata['format']==='bapx.agent-tools'&&$metadata['version']===1
    &&str_contains($metadata['standardsStatus'],'Site-specific'),'Custom discovery claimed an unsupported standard.');
discoveryCheck(json_encode($metadata['tools'],JSON_THROW_ON_ERROR)===json_encode($mcp->toolDefinitions(),JSON_THROW_ON_ERROR)
    &&$metadata['transport']['url']==='https://public.example/api/mcp'
    &&$metadata['transport']['supportedProtocols']===\App\Services\PublicMcpService::SUPPORTED_PROTOCOLS,
    'Discovery diverged from the current transport registry or protocol contract.');
$reads=['search_public_products','search_public_articles','get_public_contact','list_public_services'];
$actions=['create_cart','get_cart','set_cart_quantity','review_checkout','prepare_checkout_payment','get_checkout_status'];
$scopes=[];
foreach($metadata['tools'] as $tool) {
    $access=$tool['_meta']['in.bapx/access']??null;
    discoveryCheck(is_array($access)&&is_string($access['scope']??null)&&is_string($access['authorization']??null),
        'Explicit scope was missing from the authoritative registry.');
    if(in_array($tool['name'],$reads,true))discoveryCheck($access===['scope'=>'public_information','authorization'=>'none'],'Public read scope drifted.');
    else discoveryCheck($access['scope']!=='public_information'&&!array_key_exists('operation',(array)$tool['inputSchema']['properties']),'Scoped commerce is disguised as public information or a generic executor.');
    $key=$access['scope'].'|'.$access['authorization'];
    $scopes[$key]??=$access+['tools'=>[]];$scopes[$key]['tools'][]=$tool['name'];
}
discoveryCheck(array_column($metadata['httpReads'],'tool')===$reads&&$metadata['capabilities']===array_values($scopes)
    &&$metadata['commerce']['availableTools']===$actions,'Public HTTP reads or explicit commerce grouping diverged.');
$byName=array_column($metadata['tools'],null,'name');
discoveryCheck($byName['get_cart']['annotations']['readOnlyHint']===true
    &&$byName['get_cart']['_meta']['in.bapx/access']['authorization']==='cart_capability'
    &&!in_array('get_cart',array_column($metadata['httpReads'],'tool'),true),'Read-only hint incorrectly granted unscoped HTTP cart access.');
discoveryCheck($metadata['commerce']['cartCapability']['lifetimeSeconds']===86400
    &&$metadata['commerce']['checkout']['quoteLifetimeSeconds']===600
    &&$metadata['commerce']['checkout']['statusLifetimeSeconds']===604800,'Documented capability lifetimes drifted.');
foreach($metadata['examples'] as $example) {
    discoveryCheck(str_contains($example['curl'],"'".$example['url']."'")
        &&!str_contains($example['curl'],'--insecure'),'Copyable requests lost their configured URL or TLS verification.');
    if($example['method']!=='POST')continue;
    $headers=array_change_key_case($example['headers'],CASE_LOWER);
    $response=$mcp->handle(json_encode($example['body'],JSON_THROW_ON_ERROR),$headers);
    discoveryCheck($response['status']===200&&!isset($response['body']['error'])
        &&!($response['body']['result']['isError']??false),'A published MCP curl example cannot execute against the real dispatcher.');
    if($example['id']==='list_tools')discoveryCheck(json_encode($response['body']['result']['tools'],JSON_THROW_ON_ERROR)
        ===json_encode($metadata['tools'],JSON_THROW_ON_ERROR),
        'HTTP discovery no longer matches MCP tools/list.');
}
$settings=new ReflectionProperty(\App\Services\SettingsService::class,'cache');
$priorSettings=$settings->getValue();
try {
    $settings->setValue(null,['module_shop'=>'0','module_blog'=>'0']);
    $disabled=$discovery->document();
    discoveryCheck(array_column($disabled['tools'],'name')===['get_public_contact','list_public_services','get_checkout_status']
        &&array_column($disabled['httpReads'],'tool')===['get_public_contact','list_public_services']
        &&$disabled['commerce']['availableTools']===['get_checkout_status']
        &&!in_array('search_products_http',array_column($disabled['examples'],'id'),true),
        'Discovery advertised a disabled module or an unavailable product example.');
} finally { $settings->setValue(null,$priorSettings); }
$previousHost=$_SERVER['HTTP_HOST']??null;
$_SERVER['HTTP_HOST']='untrusted.invalid';
try {
    discoveryCheck(\App\Services\PublicSiteUrlService::origin()==='https://public.example'
        &&\App\Services\PublicSiteUrlService::origin('https://Public.Example:443/')==='https://public.example'
        &&(new \App\Services\PublicMcpService(publicUrl:'https://official.example:8443/'))->origin()==='https://official.example:8443',
        'Canonical public links trusted Host or lost explicit origin injection.');
    foreach(['','http://public.example','https://user:secret@public.example',
        'https://public.example/subpath','https://public.example?query=1','https://public.example#fragment'] as $invalid) {
        $denied=false;
        try { \App\Services\PublicSiteUrlService::origin($invalid); } catch(RuntimeException) { $denied=true; }
        discoveryCheck($denied,'An invalid configured public origin was accepted.');
    }
} finally {
    if($previousHost===null)unset($_SERVER['HTTP_HOST']);else $_SERVER['HTTP_HOST']=$previousHost;
}
$controller=new \App\Controllers\PublicController();
$guide=(new ReflectionMethod($controller,'publicAssistantGuide'))->invoke($controller);
discoveryCheck(str_contains($guide['text'],'https://public.example/api/mcp')
    &&str_contains($guide['html'],'https://public.example/api/mcp')
    &&!str_contains($guide['text'],'{{SITE_URL}}')&&!str_contains($guide['text'],'{{MCP_URL}}'),'Guide did not use the configured public origin.');
foreach(['New consultation booking is retired','advisory discovery','search_public_products','slugs','readable text',
    '/api/agent-tools','curl --fail-with-body','Mcp-Method: tools/list','Mcp-Name: get_public_contact',
    'productUrl','imageUrl','does not install a connector'] as $fact) {
    discoveryCheck(str_contains($guide['text'],$fact),'Public guide omitted its actual capability or limitation.');
}
preg_match_all("/--data-raw '([^']+)'/",$guide['text'],$guideRequests);
discoveryCheck(count($guideRequests[1])===2,'The guide lost its copyable MCP list/read requests.');
foreach($guideRequests[1] as $request) {
    $decoded=json_decode($request,true,32,JSON_THROW_ON_ERROR);
    $headers=['mcp-protocol-version'=>\App\Services\PublicMcpService::PROTOCOL,'mcp-method'=>$decoded['method']];
    if(isset($decoded['params']['name']))$headers['mcp-name']=$decoded['params']['name'];
    $response=$mcp->handle($request,$headers);
    discoveryCheck($response['status']===200&&!isset($response['body']['error'])
        &&!($response['body']['result']['isError']??false),'The canonical guide advertises a broken MCP request.');
}
ob_start();$controller->assistantInstructions();$plain=ob_get_clean();
discoveryCheck($plain===$guide['text']&&!str_contains($plain,'<html'),'Plain text endpoint diverged from the public guide.');
$document=$guide;
ob_start();require app_path('views/public/ai-assistants.php');$html=ob_get_clean();
discoveryCheck(substr_count($html,'<h1')===1&&str_contains($html,'document-page__content')
    &&str_contains($html,'href="https://public.example/contact#contact-form"'),'Human guide lost semantic document markup or real contact navigation.');
$routes=\App\Services\ProjectMapService::registry()['routes'];
foreach(['/ai-assistants'=>'aiAssistants','/llms.txt'=>'assistantInstructions'] as $path=>$method) {
    $matches=array_values(array_filter($routes,static fn(array $route):bool=>$route['path']===$path&&$route['method']==='GET'));
    discoveryCheck(count($matches)===1&&$matches[0]['controller']==='PublicController@'.$method,'Public discovery route missing.');
}
$jsonRoute=array_values(array_filter($routes,static fn(array $route):bool=>$route['path']==='/api/agent-tools'&&$route['method']==='GET'));
discoveryCheck(count($jsonRoute)===1&&$jsonRoute[0]['controller']==='PublicAgentDiscoveryController@index',
    'Site-specific HTTP discovery route is missing.');
$layout=file_get_contents(app_path('views/layouts/app.php'));
discoveryCheck(str_contains($layout,'rel="help" href="/ai-assistants"')
    &&str_contains($layout,'type="text/plain" href="/llms.txt"')
    &&str_contains($layout,'<a href="/ai-assistants">AI assistants</a>'),'Public discovery links missing from the shared layout.');
discoveryCheck(!str_contains($plain,'.bapX/')&&!str_contains($plain,'AGENTS.md')
    &&!str_contains($plain,'BAPX_MCP_CLIENT_TOKEN'),'Private developer/runtime instructions entered public discovery.');
echo "Actual-registry HTTP discovery, executable curl requests, module scopes, official origins, canonical guide and route contracts PASS.\n";
