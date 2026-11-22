<?php
declare(strict_types=1);

use App\Services\{DatabaseService,ProductService,PublicKnowledgeService,PublicMcpService,SettingsService};
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
putenv('BAPX_TEST_MODE=1');
require dirname(__DIR__).'/app/bootstrap.php';
function selectedCheck(bool $condition,string $message):void {
    if(!$condition)throw new RuntimeException($message);
}
// Seed only the real database adapter's in-request cache with synthetic records.
// Product visibility, offer normalization and public projection remain production code; no sockets or persistent store.
$store=new DatabaseService();
$config=new ReflectionProperty(DatabaseService::class,'cfg');
$remote=new ReflectionProperty(DatabaseService::class,'remoteOnly');
$cache=new ReflectionProperty(DatabaseService::class,'remoteQueryCache');
$settings=new ReflectionProperty(SettingsService::class,'cache');
$oldCache=$cache->getValue();$oldSettings=$settings->getValue();
$oldUrl=$_SERVER['APP_URL']??null;
$fixtureUrl='http://127.0.0.1:1/public-selection-fixture';
$config->setValue($store,['remote_url'=>$fixtureUrl]);
$remote->setValue($store,true);
$records=[
    ['id'=>'ring','slug'=>'sri-varahi-amman-ring','name'=>'Sri Varahi Amman Ring','price'=>500,
        'description'=>'<p>Published ring.</p><script>notPublic()</script>',
        'image_url'=>'/assets/images/ring.webp',
        'image_urls'=>['/assets/images/ring.webp','https://untrusted.invalid/tracker.png','/assets/images/ring-side.png',
            '/assets/images/ring-back.avif','/assets/images/fourth.png'],
        'highlights'=>['<b>Real feature</b>',str_repeat('𐐀',180),'Third','Fourth','Fifth'],
        'specifications'=>['Material'=>'Silver','Size'=>'Adjustable','Weight'=>12,'Care'=>'Keep dry',
            'Long label '.str_repeat('a',90)=>str_repeat('அ',300),'Sixth'=>'Recorded','Seventh'=>'Omitted'],
        'owner_note'=>'PRIVATE_FIELD','customer_email'=>'private@example.test'],
    ['id'=>'pendant','slug'=>'varahi-amman-pendant','name'=>'Varahi Amman Pendant','price'=>350,
        'stock_status'=>'out_of_stock','description'=>'Published pendant.',
        'image_url'=>'https://public.example/assets/images/pendant.png'],
    ['id'=>'hidden','slug'=>'private-ring','name'=>'Private Ring','price'=>999,'status'=>'hidden',
        'image_url'=>'/assets/images/private.png','highlights'=>['PRIVATE_HIGHLIGHT']],
    ['id'=>'tamil','slug'=>'தமிழ் pendant','name'=>'தமிழ் Pendant','price'=>200,
        'image_urls'=>['/assets/images/'.rawurlencode('தமிழ்').'.webp']],
    ['id'=>'unsafe','slug'=>'..','name'=>'Unsafe destination','price'=>1],
];
$key=hash('sha256',$fixtureUrl."\0SELECT * FROM products\0".serialize([]));
$cache->setValue(null,[$key=>array_map(static fn(array $row):array=>['id'=>$row['id'],'_data'=>json_encode($row,JSON_THROW_ON_ERROR)],$records)]);
$settings->setValue(null,[]);
$_SERVER['APP_URL']='https://public.example';
putenv('BAPX_TEST_MODE=0');
try {
    $knowledge=new PublicKnowledgeService(new ProductService($store));
    $compact=$knowledge->products();
    selectedCheck($compact['total']===3,'Hidden or unsafe product entered public search.');
    foreach($compact['products'] as $row) {
        selectedCheck(array_intersect(['imagePaths','highlights','specifications'],array_keys($row))===[],'Default search did not stay compact.');
    }
    $slugs=['varahi-amman-pendant','sri-varahi-amman-ring'];
    $selected=$knowledge->products('',0,2,$slugs);
    selectedCheck(array_column($selected['products'],'path')===array_map(static fn(string $slug):string=>'/product/'.$slug,$slugs)
        &&$selected['total']===2&&$selected['nextOffset']===null,'Selection order, count or paging changed.');
    $ring=$selected['products'][1];
    selectedCheck($ring['imagePaths']===['/assets/images/ring.webp','/assets/images/ring-side.png','/assets/images/ring-back.avif'],'Selected gallery lost primary ordering, bounds or local image filtering.');
    selectedCheck(count($ring['highlights'])===4&&$ring['highlights'][0]==='Real feature'
        &&mb_strlen($ring['highlights'][1])===160,'Highlights were not bounded public plain text.');
    selectedCheck(count($ring['specifications'])===6&&mb_strlen($ring['specifications'][4]['label'])===80
        &&mb_strlen($ring['specifications'][4]['value'])===240,'Specifications lost canonical bounds.');
    selectedCheck($selected['products'][0]['available']===false,'Selection changed stock availability.');
    selectedCheck(!str_contains(json_encode($selected),'PRIVATE_')&&!str_contains(json_encode($selected),'private@example.test'),'Raw private fields entered the public projection.');
    $missing=$knowledge->products('',0,3,['private-ring','does-not-exist','varahi-amman-pendant']);
    selectedCheck(array_column($missing['products'],'path')===['/product/varahi-amman-pendant']
        &&$missing['total']===1,'Missing/hidden selections were disclosed or substituted.');
    $tamil=$knowledge->products('',0,1,['தமிழ் pendant'])['products'][0];
    selectedCheck($tamil['path']==='/product/'.rawurlencode('தமிழ் pendant')
        &&$tamil['imagePaths']===['/assets/images/'.rawurlencode('தமிழ்').'.webp'],'Raw Unicode selectors or selected gallery were encoded incorrectly.');
    foreach([
        ['ring',0,3,$slugs],['',1,3,$slugs],['',0,1,$slugs],
        ['',0,3,['sri-varahi-amman-ring','sri-varahi-amman-ring']],
        ['',0,3,['a','b','c','d']],['',0,3,['../admin']],['',0,3,['%2e%2e']],
        ['',0,3,['தமிழ்%20pendant']],['',0,3,[null]],['',0,3,['id'=>'ring']],
    ] as $arguments) {
        $rejected=false;
        try{$knowledge->products(...$arguments);}catch(InvalidArgumentException){$rejected=true;}
        selectedCheck($rejected,'Invalid or ambiguous exact selection was accepted.');
    }
    selectedCheck($knowledge->comparisonSlugs('Compare the Sri Varahi Amman Ring and the Varahi Amman Pendant.')
        ===['sri-varahi-amman-ring','varahi-amman-pendant'],'Injected public comparison resolution missed the actual named products.');
    $mcp=new PublicMcpService(knowledge:$knowledge,publicUrl:'https://public.example');
    foreach([PublicMcpService::PROTOCOL,PublicMcpService::LEGACY_PROTOCOL] as $version) {
        $params=['name'=>'search_public_products','arguments'=>['slugs'=>$slugs,'limit'=>2]];
        $headers=['mcp-protocol-version'=>$version];
        if($version===PublicMcpService::PROTOCOL) {
            $headers+=['mcp-method'=>'tools/call','mcp-name'=>'search_public_products'];
            $params['_meta']=['io.modelcontextprotocol/protocolVersion'=>$version,
                'io.modelcontextprotocol/clientCapabilities'=>(object)[]];
        }
        $call=static fn(array $parameters):array=>$mcp->handle(json_encode(
            ['jsonrpc'=>'2.0','id'=>1,'method'=>'tools/call','params'=>$parameters],JSON_THROW_ON_ERROR),$headers);
        $reply=$call($params);
        selectedCheck($reply['status']===200&&($reply['body']['result']['structuredContent']??null)===$selected,'MCP profile changed selected public product data.');
        $params['arguments']['query']='ring';
        selectedCheck(($call($params)['body']['result']['isError']??false)===true,'MCP did not report ambiguous selection arguments as a tool error.');
    }
    $settings->setValue(null,['module_shop'=>'0']);
    selectedCheck($knowledge->products('',0,2,$slugs)['total']===0
        &&$knowledge->comparisonSlugs('Compare Sri Varahi Amman Ring and Varahi Amman Pendant')===[],'Disabled Shop leaked exact selection or comparison.');
    echo "Exact published selection/order, compact search, bounded real details, Unicode, both public MCP profiles and module isolation PASS.\n";
} finally {
    $cache->setValue(null,$oldCache);$settings->setValue(null,$oldSettings);
    if($oldUrl===null)unset($_SERVER['APP_URL']);else $_SERVER['APP_URL']=$oldUrl;
    putenv('BAPX_TEST_MODE=1');
}
