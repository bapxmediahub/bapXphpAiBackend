<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
putenv('BAPX_TEST_MODE=1');
require dirname(__DIR__,2).'/app/bootstrap.php';
$_SESSION=['csrf_token'=>'fixture-csrf'];
$shopEnabled=!in_array('--shop-disabled',$argv??[],true);
$__modules=['shop'=>$shopEnabled,'blog'=>true];
$currentPath='/';
$source=file_get_contents(app_path('views/layouts/app.php'));
$start=strpos($source,"<?php if (!in_array(rtrim(\$currentPath, '/'), ['/login', '/register', '/contact'], true)): ?>");
$end=strpos($source,"<?php if (!in_array(\$currentPath, ['/login', '/register'])): ?>",$start);
$scriptStart=strpos($source,'var supportFab=');
$scriptEnd=strpos($source,'function showToast(',$scriptStart);
if($start===false||$end===false||$scriptStart===false||$scriptEnd===false)throw new RuntimeException('Actual Support widget was not found.');
// Execute only the existing repository-owned Support fragment, with synthetic data.
// This fixture has no HTTP entrypoint and does not accept template source from a caller.
ob_start();eval('?>'.substr($source,$start,$end-$start));$widget=ob_get_clean();
$config=['publicOrigin'=>'https://cart.example.test','protocolVersion'=>\App\Services\PublicMcpService::PROTOCOL,
    'deliveryStates'=>\App\Services\TaxService::states(),'canShop'=>$shopEnabled];
$head='<meta name="viewport" content="width=device-width,initial-scale=1">';
foreach(['band.css','a2ui-public.css','chat-commerce.css','support-checkout.css']as$style)$head.='<link rel="stylesheet" href="/assets/css/'.$style.'">';
$head.='<script type="application/json" id="bapx-support-commerce-config">'.json_encode($config,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR).'</script>';
foreach(['a2ui-public-renderer.js','agent-status.js','checkout-form.js','public-mcp-shopping.js','support-checkout.js']as$script)$head.='<script defer src="/assets/'.$script.'"></script>';
$html='<!doctype html><html lang="en"><head>'.$head.'</head><body><main><h1>Fixture shop</h1></main>'.$widget
    .'<script>'.substr($source,$scriptStart,$scriptEnd-$scriptStart).'</script></body></html>';
$products=(new \App\Services\PublicA2uiService())->fromResult('products',['products'=>[[
    'name'=>'Sri Varahi Amman Ring','path'=>'/product/sri-varahi-amman-ring','priceInr'=>500.75,'available'=>true,
    'description'=>'Published fixture ring.','imagePath'=>'/assets/images/products/varahi-amman-ring-front.webp',
]],'total'=>1,'nextOffset'=>null]);
$reply='Here is the Sri Varahi Amman Ring. Use Add to cart to choose it; no cart change has been made by this answer.';
$answer=['reply'=>$reply]+(new \App\Services\AgentAnswerPresentationService())->responseData($reply,'support',[],$products);
echo json_encode(['html'=>$html,'answer'=>$answer],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
