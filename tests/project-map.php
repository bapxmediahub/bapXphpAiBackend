<?php
declare(strict_types=1);

use App\Services\{KnowledgeGraphService,ProjectMapService};

// The same pure regressions run alone and inside the required application suite.
$projectMapStandalone = !isset($tests);
if ($projectMapStandalone) {
    require __DIR__.'/../app/bootstrap.php';
    $tests=[];
    $assertionCount=0;
    function assertTrue(bool $condition,string $message):void {
        global $assertionCount;
        $assertionCount++;
        if(!$condition)throw new RuntimeException($message);
    }
    function assertSame(mixed $expected,mixed $actual,string $message):void {
        assertTrue($expected===$actual,$message.' Expected '.var_export($expected,true).'; got '.var_export($actual,true));
    }
}

$tests['project map shows source inheritance and gateway references without route-name inference'] = static function():void {
    $map=ProjectMapService::renderSystematicMermaid();
    foreach(['PublicMcpController','PublicAgentDiscoveryController','RemoteCheckoutController'] as $controller) {
        assertTrue(!preg_match('/controller_'.strtolower($controller).'[^\n]*controller_basecontroller/',$map),
            $controller.' has no declared parent and must not acquire browser-controller inheritance in the map');
    }
    assertTrue(str_contains($map,'controller_commercecontroller -- extends --> controller_basecontroller'),'Real controller inheritance remains visible');
    foreach(['razorpayclient','stripeclient'] as $client) {
        assertTrue(!preg_match('/service_paymentservice[^\n]*integration_'.$client.'/',$map),'Pure payment verification does not construct '.$client);
        assertTrue(str_contains($map,'controller_commercecontroller -. source new .-> integration_'.$client),'CommerceController has a literal '.$client.' construction');
    }
    foreach (['remotecheckoutservice','campaignpaymentservice'] as $gatewayConsumer) {
        assertTrue(str_contains($map,'service_'.$gatewayConsumer.' -. static reference .-> service_razorpaypaymentlinkservice'),
            'Checkout references the shared provider operations');
    }
    assertTrue(str_contains($map,'service_razorpaypaymentlinkservice -. source new .-> integration_razorpayclient'),
        'Shared provider operations construct the actual Razorpay gateway client');
    assertTrue(!str_contains($map,' -. renders .-> '),'A declared related view must not be presented as proof of rendering');
    assertTrue(str_contains($map,'controller_publicmcpcontroller -. declared related view .-> view_public_mcp_app'),'Related resource templates remain navigable as declared associations');
    assertTrue(str_contains($map,'controller_publiccontroller -. declared related view .-> view_public_shop'),'Redirect-related page associations are labelled without claiming execution');
};

$tests['project map token references resolve imports without executing code or counting decoys'] = static function():void {
    $source=<<<'PHP'
<?php
namespace Fixture\One {
use App\Controllers\BaseController as ParentController;
use App\Services\{PaymentService as Payment, PublicKnowledgeService, UnusedService};
use App\Integrations\Razorpay as Gateway;
use function Fake\PaymentService;
use const Fake\RazorpayClient;
throw new \RuntimeException('This fixture must never execute.');
final class Example extends ParentController {
    public function run($class) {
        // new UnusedService(); UnusedService::call(); class Nope extends Other {}
        $text='new UnusedService(); UnusedService::call();';
        $more="new UnusedService(); UnusedService::call();";
        $braces="}{$class}}";
        new Gateway\RazorpayClient();
        new \App\Services\PublicKnowledgeService();
        Payment::isCapturedRazorpayPayment([],'','',0);
        PublicKnowledgeService::class;
        namespace\LocalService::inspect();
        new $class();
        $class::run();
        $object->UnusedService::run();
        self::run($class);
        $anon=new class { public function work(){new UnusedService();} };
    }
}
}
namespace Fixture\Two {
    use App\Services\PaymentService as Other;
    final class Second { public function run(){new Other('');} }
}
PHP;
    token_get_all($source,TOKEN_PARSE);
    $autoloads=[];
    $loader=static function(string $name)use(&$autoloads):void{$autoloads[]=$name;};
    spl_autoload_register($loader);
    try{$parsed=ProjectMapService::sourceReferences($source);}
    finally{spl_autoload_unregister($loader);}
    assertSame([],$autoloads,'Source inspection must not load or execute referenced classes');
    assertSame(['Fixture\\One\\Example','Fixture\\Two\\Second'],$parsed['classes'],'Named declarations retain their namespaces');
    $actual=array_map(static fn(array $edge):array=>[$edge['from'],$edge['relation'],$edge['to']],$parsed['references']);
    assertSame([
        ['Fixture\\One\\Example','extends','App\\Controllers\\BaseController'],
        ['Fixture\\One\\Example','constructs','App\\Integrations\\Razorpay\\RazorpayClient'],
        ['Fixture\\One\\Example','constructs','App\\Services\\PublicKnowledgeService'],
        ['Fixture\\One\\Example','static_reference','App\\Services\\PaymentService'],
        ['Fixture\\One\\Example','static_reference','App\\Services\\PublicKnowledgeService'],
        ['Fixture\\One\\Example','static_reference','Fixture\\One\\LocalService'],
        ['Fixture\\Two\\Second','constructs','App\\Services\\PaymentService'],
    ],$actual,'Aliases, grouped imports, fully qualified and namespace-relative names resolve; dynamic and anonymous class work is outside scope');
    foreach($parsed['references'] as $edge)assertTrue(is_int($edge['line'])&&$edge['line']>0,'Each observed reference has a source line');
    assertSame(['Plain\\Example'],ProjectMapService::sourceReferences('<?php namespace Plain; class Example {}')['classes'],'Unbracketed namespace declarations are supported');
    try {
        ProjectMapService::sourceReferences(str_repeat(' ',2*1024*1024+1));
        throw new RuntimeException('Oversized source must fail generation instead of silently dropping evidence');
    } catch(LengthException $expected) {
        assertTrue(str_contains($expected->getMessage(),'scan budget'),'Per-file budget failure remains explicit');
    }
};

$tests['project map source evidence includes the actual native answer presenter'] = static function():void {
    $scan=ProjectMapService::scan();
    assertTrue(!isset($scan['gaps']['unwired_services']),'A limited scan must not label missing references as proven unwired code');
    assertTrue(!in_array('AgentAnswerPresentationService',$scan['gaps']['unreferenced_services'],true),'The real presenter is referenced by both native controllers');
    foreach(['SupportController','AdminController'] as $controller) {
        $matches=array_values(array_filter($scan['source_relationships'],static fn(array $edge):bool=>
            $edge['from']==='controller:'.$controller&&$edge['to']==='service:AgentAnswerPresentationService'&&$edge['relation']==='constructs'));
        assertSame(1,count($matches),'Repeated presenter constructions collapse to one source relationship per controller');
        assertSame('app/Controllers/'.$controller.'.php',$matches[0]['source'],'Evidence points to the original caller');
        assertSame('source_token',$matches[0]['provenance'],'Source evidence is distinguishable from route declarations');
    }
    assertSame($scan,ProjectMapService::scan(),'The same source tree produces deterministic inventory and evidence');
    assertSame(ProjectMapService::renderSystematicMermaid(),ProjectMapService::renderSystematicMermaid(),'Repeated map generation is deterministic');
};

$tests['project knowledge preserves declared JSON response and relationship provenance'] = static function():void {
    $index=json_decode((string)file_get_contents(app_path('docs/project-index.json')),true,512,JSON_THROW_ON_ERROR);
    $webhook=array_values(array_filter($index['routes'],static fn(array $route):bool=>$route['path']==='/api/payments/razorpay-webhook'))[0];
    assertSame('json',$webhook['response'],'The queryable index preserves an explicit JSON response');
    assertSame('',$webhook['view'],'A JSON-only handler has no invented HTML view');
    assertSame('route_registry',$webhook['provenance'],'Route metadata identifies its declarative source');
    assertTrue(isset($index['relationship_provenance']['source_token'],$index['source_relationships']),'The index explains and exposes bounded source references');
    $graph=(new KnowledgeGraphService())->build();
    $route=$graph['concepts']['route:post__api_payments_razorpay_webhook'];
    assertSame('json',$route['response'],'The knowledge router retains JSON response metadata too');
    assertSame('route_registry',$route['provenance'],'The knowledge router distinguishes declaration from execution');
    $edges=array_values($graph['edges']);
    assertTrue((bool)array_filter($edges,static fn(array $edge):bool=>$edge['relation']==='declared_service'),'Registry service associations are explicitly declared');
    assertTrue((bool)array_filter($edges,static fn(array $edge):bool=>$edge['relation']==='collection_reference'),'Collection associations do not invent read/write direction');
    assertTrue(!(bool)array_filter($edges,static fn(array $edge):bool=>in_array($edge['relation'],['uses','stores'],true)),'The knowledge router must not convert association metadata into runtime calls or writes');
};

if($projectMapStandalone) {
    $failures=[];
    foreach($tests as $name=>$test){try{$test();echo "PASS $name\n";}catch(Throwable $error){$failures[]="FAIL $name: ".$error->getMessage();}}
    echo (count($tests)-count($failures)).'/'.count($tests)." project map tests passed, $assertionCount assertions\n";
    foreach($failures as $failure)echo $failure."\n";
    exit($failures?1:0);
}
