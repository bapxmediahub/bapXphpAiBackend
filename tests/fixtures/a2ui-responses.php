<?php
declare(strict_types=1);
// Synthetic canonical tool results; use the production presenters for browser QA.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
putenv('BAPX_TEST_MODE=1');
require dirname(__DIR__,2).'/app/bootstrap.php';
$public=new \App\Services\PublicA2uiService();
$chat=new \App\Services\AgentAnswerPresentationService();
$productRow=[
    'name'=>'Emerald Ring','path'=>'/product/emerald-ring','priceInr'=>1250.00,'available'=>true,
    'description'=>'A synthetic product for UI verification.',
    'imagePath'=>'/assets/images/fixture-ring.png',
    'imagePaths'=>['/assets/images/fixture-ring.png','/assets/images/fixture-ring-side.png','/assets/images/fixture-ring-detail.png'],
    'highlights'=>['Published feature one.','Published feature two.'],
    'specifications'=>[['label'=>'Material','value'=>'Silver'],['label'=>'Size','value'=>'Adjustable']],
];
$secondRow=['name'=>'Ruby Pendant','path'=>'/product/ruby-pendant','priceInr'=>900,'available'=>false,
    'description'=>'Another synthetic published product.','imagePath'=>'/assets/images/fixture-pendant.png',
    'specifications'=>[['label'=>'Material','value'=>'Brass']]];
$product=$public->fromResult('products',['products'=>[$productRow],'total'=>1,'nextOffset'=>null]);
$selected=['products'=>[$productRow,$secondRow],'total'=>2,'nextOffset'=>null];
$contact=$public->fromResult('contact',[
    'name'=>'Fixture Shop','path'=>'/contact','phones'=>[['label'=>'Phone','number'=>'+91 00000 00000']],
    'email'=>'help@example.test','hours'=>[['days'=>'Monday','hours'=>'9 AM–5 PM']],
    'servicePath'=>'/contact#contact-form',
]);
$services=$public->fromResult('services',['services'=>[[
    'name'=>'Product Inquiry','description'=>'Ask about the published products.',
    'path'=>'/contact?subject=product#contact-form',
]],'total'=>1,'nextOffset'=>null]);
$sales=\App\Services\AgentToolRegistry::salesFacts([
    ['created_at'=>'2026-11-01','status'=>'confirmed','payment_id'=>'fixture_paid_1','total'=>500],
    ['created_at'=>'2026-11-02','status'=>'confirmed','payment_id'=>'fixture_paid_2','total'=>500],
    ['created_at'=>'2026-11-03','status'=>'confirmed','payment_id'=>'fixture_paid_3','total'=>500,
        'verified_refunds'=>[['amount_paise'=>25000]]],
],['from'=>'2026-11-01','to'=>'2026-11-22']);
$recorded=\App\Services\AgentTraceService::toolEvidence('sales_summary',[],$sales);
$recorded['observed_at']='2026-11-22T09:30:00.000000Z';
$tools=[['name'=>'sales_summary','status'=>'complete','evidence'=>$recorded]];
$commerceResult=['report'=>'products','from'=>'2026-11-01','to'=>'2026-11-22','currency'=>'INR','limit'=>6,'total_rows'=>2,
    'summary'=>['products'=>2,'units'=>5,'gross_line_value'=>3000,'allocated_net'=>2750],
    'rows'=>[
        ['product'=>'Emerald Ring','order_lines'=>2,'units'=>3,'gross'=>2100,'net'=>1900],
        ['product'=>'Ruby Pendant','order_lines'=>1,'units'=>2,'gross'=>900,'net'=>850],
    ],'report_url'=>'/admin/tax-report?report=products&from=2026-11-01&to=2026-11-22'];
$commerceEvidence=\App\Services\AgentTraceService::toolEvidence('commerce_report',[],$commerceResult);
$commerceEvidence['observed_at']='2026-11-22T10:00:00.000000Z';
$commerceTools=[['name'=>'commerce_report','status'=>'complete','evidence'=>$commerceEvidence]];
echo json_encode([
    'product'=>$product,'contact'=>$contact,'services'=>$services,
    'productGrid'=>$public->fromResult('products',$selected,4,'grid'),
    'productList'=>$public->fromResult('products',$selected,4,'list'),
    'productCompare'=>$public->fromResult('products',$selected,4,'compare'),
    'partialComparison'=>$public->fromResult('products',$selected,4,'compare',3),
    'articles'=>$public->fromResult('articles',['articles'=>[['title'=>'Product care guide','category'=>'Help','path'=>'/blog/product-care']]]),
    'commerce'=>$chat->present('Published report rows are shown below.','admin',[],null,$commerceTools),
    'commerceHistory'=>$chat->present('Saved product report.','admin',[],null,\App\Services\AgentAnswerPresentationService::toolsForTurn(['tools'=>array_map([\App\Services\AgentTraceService::class,'safeTool'],$commerceTools)])),
    'support'=>$chat->present("Here is the matching Emerald Ring.\n\nIts published price is ₹1,250.00.",'support',[
        ['label'=>'View shop','path'=>'/shop'],
    ],$product),
    'enterprise'=>$chat->present("Sales for the selected period.\n\nThe completed read-only lookup returned ₹1,250.00.",'admin',[
        ['label'=>'Sales report','path'=>'/admin/tax-report?report=sales&from=2026-11-01&to=2026-11-22'],
    ],null,$tools),
    'history'=>$chat->present('Saved authorized answer.','admin',[],null,$tools),
    'parityProducts'=>$public->fromResult('products',['products'=>[
        ['name'=>'Tamil product','path'=>'/product/'.rawurlencode(str_repeat('தமிழ்',10)),'priceInr'=>1,'available'=>true,
            'imagePath'=>'/assets/images/'.rawurlencode('தமிழ்').'.avif'],
        ['name'=>'Dotted product','path'=>'/product/foo..bar','priceInr'=>1,'available'=>true],
        ['name'=>'Maximum encoded path','path'=>'/product/'.str_repeat('a',2039),'priceInr'=>1,'available'=>true],
        ['name'=>'Exceeds path cap','path'=>'/product/'.str_repeat('a',2040),'priceInr'=>1,'available'=>true],
    ]]),
    'unicodeProduct'=>$public->fromResult('products',['products'=>[[
        'name'=>str_repeat('𐐀',160),'path'=>'/product/unicode','priceInr'=>1,'available'=>true,
        'description'=>str_repeat('𐐀',240),'imagePath'=>'/assets/images/unicode.png',
    ]]]),
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
