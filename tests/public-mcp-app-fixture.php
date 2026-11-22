<?php
declare(strict_types=1);
use App\Services\{PublicMcpService,PublicA2uiService};
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
putenv('BAPX_TEST_MODE=1');
putenv('APP_URL=https://example.test');
require dirname(__DIR__).'/app/bootstrap.php';
$meta=['io.modelcontextprotocol/protocolVersion'=>PublicMcpService::PROTOCOL,
    'io.modelcontextprotocol/clientCapabilities'=>(object)[]];
$request=['jsonrpc'=>'2.0','id'=>1,'method'=>'resources/read',
    'params'=>['_meta'=>$meta,'uri'=>PublicMcpService::UI_URI]];
$response=(new PublicMcpService())->handle(json_encode($request,JSON_THROW_ON_ERROR),[
    'mcp-protocol-version'=>PublicMcpService::PROTOCOL,'mcp-method'=>'resources/read','mcp-name'=>PublicMcpService::UI_URI]);
if ($response['status']!==200) throw new RuntimeException('Public UI resource unavailable.');
$data=['products'=>[['name'=>'Fixture public ring','path'=>'/product/fixture-ring','priceInr'=>125,
    'description'=>'Published fixture description.','available'=>true]],'total'=>1,'nextOffset'=>null];
$result=['content'=>[['type'=>'text','text'=>'Fixture public ring — INR 125 — https://example.test/product/fixture-ring']],
    'structuredContent'=>$data,'_meta'=>[PublicMcpService::SURFACE_META=>(new PublicA2uiService())->fromResult('products',$data)]];
echo json_encode(['resource'=>$response['body']['result']['contents'][0],'result'=>$result],JSON_THROW_ON_ERROR);
