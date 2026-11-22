<?php
declare(strict_types=1);
use App\Services\{PublicMcpService,PublicKnowledgeService,PublicA2uiService};
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
putenv('BAPX_TEST_MODE=1');
putenv('APP_URL=https://example.test');
require dirname(__DIR__).'/app/bootstrap.php';
set_exception_handler(static function(Throwable $error):void {
    fwrite(STDERR,$error::class.': '.$error->getMessage()."\n");
    exit(1);
});

function mcpCheck(bool $condition,string $reason): void {
    if (!$condition) throw new RuntimeException($reason);
}
$service=new PublicMcpService();
$metadata=['io.modelcontextprotocol/protocolVersion'=>PublicMcpService::PROTOCOL,
    'io.modelcontextprotocol/clientCapabilities'=>(object)[]];
$ui=$metadata;
$ui['io.modelcontextprotocol/clientCapabilities']=['extensions'=>['io.modelcontextprotocol/ui'=>['mimeTypes'=>[PublicMcpService::UI_MIME]]]];
$invoke=static function(string $method,array $params=[],?array $meta=null,array $override=[]) use($service,$metadata): array {
    $headers=['mcp-protocol-version'=>PublicMcpService::PROTOCOL,'mcp-method'=>$method];
    if (isset($params['name'])) $headers['mcp-name']=$params['name'];
    if (isset($params['uri'])) $headers['mcp-name']=$params['uri'];
    return $service->handle(json_encode(['jsonrpc'=>'2.0','id'=>7,'method'=>$method,
        'params'=>['_meta'=>$meta??$metadata]+$params],JSON_THROW_ON_ERROR),array_replace($headers,$override));
};
$discovery=$invoke('server/discover');
mcpCheck($discovery['status']===200,'Discovery failed');
mcpCheck($discovery['body']['result']['supportedVersions']===PublicMcpService::SUPPORTED_PROTOCOLS,'Wrong supported versions');
foreach(['server/discover','tools/list','resources/list','resources/templates/list'] as $method) {
    $result=$invoke($method)['body']['result'];
    mcpCheck($result['resultType']==='complete' && $result['ttlMs']===0 && $result['cacheScope']==='public','Missing modern result/cache fields');
}
$tools=$invoke('tools/list',[], $ui)['body']['result']['tools'];
mcpCheck(array_column($tools,'name')===['search_public_products','search_public_articles','get_public_contact','list_public_services'],'Unexpected public tools');
foreach($tools as $tool) {
    mcpCheck($tool['_meta']['ui']['resourceUri']===PublicMcpService::UI_URI,'Missing tool UI linkage');
    mcpCheck($tool['annotations']['readOnlyHint']===true && $tool['annotations']['destructiveHint']===false,'Unsafe public annotation');
    mcpCheck($tool['inputSchema']['additionalProperties']===false,'Loose public arguments');
}
foreach($invoke('tools/list')['body']['result']['tools'] as $tool) mcpCheck(isset($tool['_meta']['ui']),'Default clients cannot discover optional public UI');
$contact=$invoke('tools/call',['name'=>'get_public_contact','arguments'=>(object)[]],$ui)['body']['result'];
mcpCheck($contact['structuredContent']===(new PublicKnowledgeService())->contact(),'Contact projection drifted');
mcpCheck(isset($contact['_meta'][PublicMcpService::SURFACE_META]['messages'][0]['createSurface']),'Contact card missing');
mcpCheck(count($contact['content'])===1 && $contact['content'][0]['type']==='text','Text fallback missing');
$plain=$invoke('tools/call',['name'=>'get_public_contact','arguments'=>(object)[]])['body']['result'];
mcpCheck($plain['structuredContent']===$contact['structuredContent'] && $plain['content']===$contact['content'],'Plain-client data or text changed');
mcpCheck(isset($plain['_meta'][PublicMcpService::SURFACE_META]),'Default host cannot render the exact public result');
$services=$invoke('tools/call',['name'=>'list_public_services','arguments'=>['offset'=>1,'limit'=>1]],$ui)['body']['result'];
$items=$services['_meta'][PublicMcpService::SURFACE_META]['messages'][0]['createSurface']['dataModel']['items'];
mcpCheck(count($items)===1 && $items[0]['path']===$services['structuredContent']['services'][0]['path'],'Cards ignored result offset');
foreach(['find_order','paid_sales_summary','own_ticket_status','admin_report'] as $private) {
    mcpCheck($invoke('tools/call',['name'=>$private])['body']['error']['code']===-32602,'Private capability escaped');
}
foreach([['role'=>'admin'],['limit'=>21],['query'=>null],['offset'=>-1]] as $bad) {
    mcpCheck($invoke('tools/call',['name'=>'search_public_products','arguments'=>$bad])['body']['result']['isError']===true,'Invalid search accepted');
}
mcpCheck($invoke('tools/call',['name'=>'get_public_contact','arguments'=>['email'=>'private@example.test']])['body']['result']['isError']===true,'Contact gained a mutation argument');
mcpCheck($invoke('tools/list',['cursor'=>'untrusted'])['body']['error']['code']===-32602,'Invalid cursor accepted');
foreach(['initialize','ping','subscriptions/listen','unknown'] as $method) {
    $answer=$invoke($method); mcpCheck($answer['status']===404 && $answer['body']['error']['code']===-32601,'Unsupported modern RPC accepted');
}
$version=$metadata;$version['io.modelcontextprotocol/protocolVersion']='2030-01-01';
$answer=$invoke('server/discover',[],$version,['mcp-protocol-version'=>'2030-01-01']);
mcpCheck($answer['status']===400 && $answer['body']['error']['code']===-32022 && $answer['body']['error']['data']['supported']===PublicMcpService::SUPPORTED_PROTOCOLS,'Version negotiation error malformed');
mcpCheck($invoke('server/discover',[],[])['body']['error']['code']===-32602,'Missing metadata accepted');
foreach([['mcp-method'=>'tools/list'],['mcp-protocol-version'=>'wrong'],['mcp-method'=>'']] as $headers) {
    mcpCheck($invoke('server/discover',[],null,$headers)['body']['error']['code']===-32020,'Header mismatch accepted');
}
mcpCheck($invoke('tools/call',['name'=>'get_public_contact'],null,['mcp-name'=>'wrong'])['body']['error']['code']===-32020,'Wrong named header accepted');
$encoded='=?base64?'.base64_encode('get_public_contact').'?=';
mcpCheck($invoke('tools/call',['name'=>'get_public_contact'],null,['mcp-name'=>$encoded])['status']===200,'Encoded Mcp-Name rejected');
$resource=$invoke('resources/read',['uri'=>PublicMcpService::UI_URI])['body']['result'];
mcpCheck($resource['ttlMs']===0 && $resource['cacheScope']==='public','Resource cache fields missing');
mcpCheck($resource['contents'][0]['mimeType']===PublicMcpService::UI_MIME,'Wrong Apps MIME');
mcpCheck(str_contains($resource['contents'][0]['text'],'ui/initialize') && !str_contains($resource['contents'][0]['text'],'<script src='),'Widget is not self contained');
mcpCheck($resource['contents'][0]['_meta']['ui']['csp']['connectDomains']===[],'Widget gained network permission');
mcpCheck($invoke('resources/read',['uri'=>'file:///storage/secrets'])['body']['error']['code']===-32602,'Arbitrary resource path accepted');
mcpCheck($service->handle('{',[])['body']['error']['code']===-32700,'Malformed JSON accepted');
mcpCheck($service->handle('[]',[])['body']['error']['code']===-32600,'Batch accepted');
mcpCheck($service->handle(str_repeat('x',65537),[])['status']===413,'Oversized request accepted');
$notification=$service->handle('{"jsonrpc":"2.0","method":"notifications/initialized"}',[]);
mcpCheck($notification['status']===400 && $notification['body']===null,'Unversioned notification accepted');

// Unmodified current SDK clients still use this exact legacy profile and need no host capability options.
$legacyInvoke=static function(string $method,array $params=[],array $override=[]) use($service):array {
    $headers=$method==='initialize' ? [] : ['mcp-protocol-version'=>PublicMcpService::LEGACY_PROTOCOL];
    return $service->handle(json_encode(['jsonrpc'=>'2.0','id'=>9,'method'=>$method,'params'=>(object)$params],JSON_THROW_ON_ERROR),
        array_replace($headers,$override));
};
$initialize=['protocolVersion'=>PublicMcpService::LEGACY_PROTOCOL,'capabilities'=>(object)[],
    'clientInfo'=>['name'=>'unmodified-default-client','version'=>'1']];
$legacyInit=$legacyInvoke('initialize',$initialize);
mcpCheck($legacyInit['status']===200 && $legacyInit['body']['result']['protocolVersion']===PublicMcpService::LEGACY_PROTOCOL,'Legacy initialization failed');
mcpCheck(isset($legacyInit['body']['result']['serverInfo'],$legacyInit['body']['result']['capabilities']['tools']),'Legacy discovery contract missing');
mcpCheck(!isset($legacyInit['body']['result']['resultType'],$legacyInit['body']['result']['ttlMs']),'Modern framing leaked into initialize');
$alternative=$initialize;$alternative['protocolVersion']='2024-11-05';
mcpCheck($legacyInvoke('initialize',$alternative)['body']['result']['protocolVersion']===PublicMcpService::LEGACY_PROTOCOL,'Legacy negotiation did not offer supported version');
foreach([['protocolVersion'=>null],['capabilities'=>[]],['clientInfo'=>['name'=>'missing-version']]] as $invalid) {
    mcpCheck($legacyInvoke('initialize',array_replace($initialize,$invalid))['body']['error']['code']===-32602,'Malformed initialize accepted');
}
$legacyHeaders=['mcp-protocol-version'=>PublicMcpService::LEGACY_PROTOCOL];
$notification=$service->handle('{"jsonrpc":"2.0","method":"notifications/initialized"}',$legacyHeaders);
mcpCheck($notification['status']===202 && $notification['body']===null,'Legacy readiness notification did not receive empty 202');
foreach(['{"jsonrpc":"2.0","method":"notifications/initialized","params":null}','{"jsonrpc":"2.0","method":"unknown"}'] as $bad) {
    $answer=$service->handle($bad,$legacyHeaders);
    mcpCheck($answer['status']===400 && $answer['body']===null,'Invalid notification accepted or replied to');
}
foreach(['tools/list','resources/list','resources/templates/list'] as $method) {
    $result=$legacyInvoke($method)['body']['result'];
    mcpCheck(!array_key_exists('resultType',$result) && !array_key_exists('ttlMs',$result) && !array_key_exists('cacheScope',$result),'Modern response fields leaked into legacy profile');
}
$ping=$legacyInvoke('ping')['body']['result'];
mcpCheck($ping instanceof stdClass && get_object_vars($ping)===[],'Legacy ping is not an empty result object');
mcpCheck(json_encode($legacyInvoke('tools/list')['body']['result']['tools'],JSON_THROW_ON_ERROR)===json_encode($tools,JSON_THROW_ON_ERROR),'Legacy public tool definitions diverged');
$legacyContact=$legacyInvoke('tools/call',['name'=>'get_public_contact','arguments'=>(object)[]])['body']['result'];
mcpCheck($legacyContact['structuredContent']===$contact['structuredContent'] && $legacyContact['content']===$contact['content'],'Legacy canonical data or text diverged');
mcpCheck(isset($legacyContact['_meta'][PublicMcpService::SURFACE_META]),'Default legacy host lost cards');
mcpCheck(!isset($legacyContact['_meta']['io.modelcontextprotocol/serverInfo']),'Modern server metadata leaked');
$legacyResource=$legacyInvoke('resources/read',['uri'=>PublicMcpService::UI_URI])['body']['result'];
mcpCheck($legacyResource['contents']===$resource['contents'],'Legacy UI resource diverged');
foreach(['paid_sales_summary','find_order','own_ticket_status'] as $private) {
    mcpCheck($legacyInvoke('tools/call',['name'=>$private])['body']['error']['code']===-32602,'Legacy private capability escaped');
}
mcpCheck($legacyInvoke('tools/call',['name'=>'get_public_contact','arguments'=>['email'=>'private@example.test']])['body']['result']['isError']===true,'Legacy contact acquired mutation arguments');
mcpCheck($legacyInvoke('resources/read',['uri'=>'file:///storage/secrets'])['body']['error']['code']===-32602,'Legacy arbitrary resource path accepted');
mcpCheck($legacyInvoke('server/discover')['body']['error']['code']===-32601,'Legacy profile accepted modern RPC');
mcpCheck($legacyInvoke('initialize',$initialize,['mcp-protocol-version'=>PublicMcpService::PROTOCOL])['body']['error']['code']===-32020,'Malformed modern initialize downgraded');
mcpCheck($legacyInvoke('tools/list',[],['mcp-method'=>'tools/list'])['body']['error']['code']===-32602,'Mixed modern framing downgraded');
mcpCheck($service->handle('{"jsonrpc":"2.0","id":1,"method":"tools/list","params":null}',$legacyHeaders)['body']['error']['code']===-32602,'Legacy null params accepted');
foreach(['javascript:alert(1)','http://example.test','https://user:pass@example.test','https://example.test/private'] as $origin) {
    try { new PublicMcpService(publicUrl:$origin); throw new RuntimeException('Unsafe origin accepted'); }
    catch(InvalidArgumentException) {} catch(RuntimeException $error) { mcpCheck($error->getMessage()!=='Unsafe origin accepted','Unsafe origin accepted'); }
}
echo "Public MCP modern/legacy framing, header checks, public projections, exact-result cards and text fallback PASS.\n";
