<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
putenv('BAPX_TEST_MODE=1');putenv('APP_URL=https://public.example');
require dirname(__DIR__).'/app/bootstrap.php';
function discoveryCheck(bool $condition,string $message):void {
    if(!$condition)throw new RuntimeException($message);
}
$controller=new \App\Controllers\PublicController();
$guide=(new ReflectionMethod($controller,'publicAssistantGuide'))->invoke($controller);
discoveryCheck(str_contains($guide['text'],'https://public.example/api/mcp')
    &&str_contains($guide['html'],'https://public.example/api/mcp')
    &&!str_contains($guide['text'],'{{SITE_URL}}')&&!str_contains($guide['text'],'{{MCP_URL}}'),'Guide did not use the configured public origin.');
foreach(['New consultation booking is retired','advisory discovery','search_public_products','slugs','readable text'] as $fact) {
    discoveryCheck(str_contains($guide['text'],$fact),'Public guide omitted its actual capability or limitation.');
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
$layout=file_get_contents(app_path('views/layouts/app.php'));
discoveryCheck(str_contains($layout,'rel="help" href="/ai-assistants"')
    &&str_contains($layout,'type="text/plain" href="/llms.txt"')
    &&str_contains($layout,'<a href="/ai-assistants">AI assistants</a>'),'Public discovery links missing from the shared layout.');
discoveryCheck(!str_contains($plain,'.bapX/')&&!str_contains($plain,'AGENTS.md')
    &&!str_contains($plain,'BAPX_MCP_CLIENT_TOKEN'),'Private developer/runtime instructions entered public discovery.');
echo "Public guide, configured origin, advisory plain text, real routes and shared discovery links PASS.\n";
