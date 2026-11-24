<?php
declare(strict_types=1);
// Exercise the owner's actual reference policy without constructing an HTTP session.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
putenv('BAPX_TEST_MODE=1');
require dirname(__DIR__).'/app/bootstrap.php';
$denyPublicLookup=static function(string $class):void {
    if(in_array($class,[\App\Services\PublicA2uiService::class,\App\Services\PublicKnowledgeService::class],true)) {
        throw new RuntimeException('Owner aggregate attempted a public lookup.');
    }
};
spl_autoload_register($denyPublicLookup,true,true);
$controller=(new ReflectionClass(\App\Controllers\AdminController::class))->newInstanceWithoutConstructor();
$policy=new ReflectionMethod($controller,'agentPublicReferences');
foreach([
    ['How many products were sold?','Three products were sold.'],
    ['Show products and articles.','See /product/emerald-ring or /blog/open-guide.'],
    ['How can I contact you?','See /contact.'],
] as [$question,$answer]) {
    if($policy->invoke($controller,$question,$answer,[])!==null) {
        throw new RuntimeException('An owner answer without completed tool evidence received public references.');
    }
}
foreach(['sales_summary','gst_register_summary','support_enquiries','store_count','find_order','coupon_status','operator_guide'] as $tool) {
    $result=$policy->invoke($controller,'How many products were sold?','Three products were sold.',[
        ['name'=>$tool,'status'=>'complete'],
    ]);
    if($result!==null)throw new RuntimeException('Owner aggregate received catalogue references.');
}
foreach(['product_status','search_articles'] as $tool) {
    $result=$policy->invoke($controller,'Show products and articles.','No public path was returned.',[
        ['name'=>$tool,'status'=>'complete'],
    ]);
    if($result!==null)throw new RuntimeException('Ungrounded owner tool answer received catalogue references.');
}
$contactTool=['name'=>'get_public_contact','status'=>'complete',
    'evidence'=>\App\Services\AgentTraceService::toolEvidence('get_public_contact',[],['path'=>'/contact'])];
$servicesTool=['name'=>'list_public_services','status'=>'complete',
    'evidence'=>\App\Services\AgentTraceService::toolEvidence('list_public_services',[],['total'=>4])];
foreach([
    [$contactTool,['name'=>'sales_summary','status'=>'complete']],
    [['name'=>'store_count','status'=>'complete'],$servicesTool],
    [['name'=>'get_public_contact','status'=>'complete']],
    [['name'=>'list_public_services','status'=>'complete']],
    [array_replace($contactTool,['status'=>'error'])],
    [array_replace($servicesTool,['status'=>'error'])],
    [array_replace($contactTool,['evidence'=>['source'=>'orders','result_count'=>1]])],
    [array_replace($contactTool,['evidence'=>['source'=>'public_contact','result_count'=>4]])],
    [array_replace($servicesTool,['evidence'=>['source'=>'public_services','result_count'=>0]])],
    [array_replace($servicesTool,['evidence'=>['source'=>'public_services','result_count'=>5]])],
] as $activity) {
    if($policy->invoke($controller,'Show public contact and services.','Recorded answer.',$activity)!==null) {
        throw new RuntimeException('Mixed private or incomplete public evidence received business cards.');
    }
}
spl_autoload_unregister($denyPublicLookup);
$knowledge=new \App\Services\PublicKnowledgeService();
$contactTool['evidence']=\App\Services\AgentTraceService::toolEvidence('get_public_contact',[],$knowledge->contact());
$servicesTool['evidence']=\App\Services\AgentTraceService::toolEvidence('list_public_services',['query'=>'temple'],$knowledge->services('temple'));
$contact=$policy->invoke($controller,'Show those details.','Recorded public contact answer.',[$contactTool]);
if(!str_starts_with($contact['messages'][0]['createSurface']['surfaceId']??'','bapx_contact_')) {
    throw new RuntimeException('Completed canonical public contact evidence did not select contact cards.');
}
$services=$policy->invoke($controller,'Tell me about temple guidance services.','Recorded public service answer.',[$servicesTool]);
if(array_column($services['messages'][0]['createSurface']['dataModel']['items']??[],'path')!==['/contact?subject=temple#contact-form']) {
    throw new RuntimeException('Completed public service evidence lost its relevant service subject.');
}
$another=$policy->invoke($controller,'Another phrasing.','Recorded public contact answer.',[$contactTool]);
if(($another['messages'][0]['createSurface']['dataModel']??null)!==($contact['messages'][0]['createSurface']['dataModel']??null)) {
    throw new RuntimeException('Presentation must follow actual completed tool evidence rather than question keywords.');
}
echo "Owner private/mixed/ungrounded tools perform no public lookup; completed public evidence selects relevant business cards PASS.\n";
