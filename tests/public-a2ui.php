<?php
declare(strict_types=1);
use App\Services\{PublicA2uiService,PublicKnowledgeService,PublicMcpService,BlogService,AgentAnswerPresentationService,ProductService,SettingsService,AgentTraceService,AgentToolRegistry,GstRegisterService,DatabaseService};
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
putenv('BAPX_TEST_MODE=1');
require dirname(__DIR__).'/app/bootstrap.php';
function checkA2ui(bool $condition,string $reason): void {
    if (!$condition) throw new RuntimeException($reason);
}
$root=sys_get_temp_dir().'/a2ui-public-'.bin2hex(random_bytes(7));
mkdir($root,0700,true);
$fixture=function(string $slug,string $title,bool $published,string $date) use($root): void {
    file_put_contents($root.'/'.$slug.'.md',"---\n".
        "title: $title\npublished: ".($published?'true':'false')."\n".
        "published_at: $date\ncategory: help\nexcerpt: A public ritual guide\n".
        "---\nPublished guide text.\n");
};
try {
    $fixture('open-guide','Ritual Open Guide',true,'2026-01-01');
    $fixture('hidden-guide','Secret Ritual Guide',false,'2026-01-01');
    $fixture('future-guide','Future Ritual Guide',true,'2999-01-01');
    file_put_contents($root.'/injected.md',"---\ntitle: <script>alert(1)</script>\npublished: true\npublished_at: 2026-01-01\n---\nText.");
    $knowledge=new PublicKnowledgeService(
        blog:new BlogService($root,new DateTimeImmutable('2026-11-22 00:00:00',new DateTimeZone('Asia/Kolkata')))
    );
    $service=new PublicA2uiService($knowledge);
    $response=$service->present('articles','ritual',4);
    $stream=$response['messages']??[];
    checkA2ui(count($stream)===1,'Expected one A2UI message');
    checkA2ui($stream[0]['version']==='1.0','Not A2UI 1.0');
    $surface=$stream[0]['createSurface']??[];
    checkA2ui($surface['catalogId']===PublicA2uiService::CATALOG,'Catalog mismatch');
    checkA2ui(preg_match('/^bapx_articles_[a-f0-9]{16}$/D',$surface['surfaceId'])===1,'Unsafe surface ID');
    $items=$surface['dataModel']['items']??[];
    checkA2ui(count($items)===1,'Unpublished or future content must not render');
    checkA2ui($items[0]['path']==='/blog/open-guide','Wrong published link');
    checkA2ui(!str_contains(json_encode($response),'Secret Ritual Guide'),'Private title leaked');
    checkA2ui(!str_contains(json_encode($response),'Future Ritual Guide'),'Future title leaked');
    checkA2ui(!str_contains(json_encode($response),'<script>'),'Dangerous unrelated content included');
    $components=$surface['components']??[];
    checkA2ui($components[0]['id']==='root' && $components[0]['component']==='Column','Root missing');
    checkA2ui($surface['dataModel']['presentation']==='article_list'
        &&count(array_filter($components,static fn(array $node):bool=>$node['component']==='Card'))===1,'Article result lost its list presentation');
    $ids=array_column($components,'id');
    checkA2ui(count($ids)===count(array_unique($ids)),'Duplicate component ids');
    foreach($components as $component){
        checkA2ui(in_array($component['component'],['Card','Text','Button','Column'],true),'Unapproved component');
        if($component['component']==='Button'){
            checkA2ui(($component['action']['functionCall']['@call']??'')==='openUrl','Unexpected function');
        }
    }
    $product=$service->present('products','',2);
    checkA2ui($product['messages'][0]['createSurface']['surfaceId']!==$surface['surfaceId'],'Surface IDs reused');
    checkA2ui($service->forQuestion('Where are the ritual guides?')!==null,'Guide intent not detected');
    checkA2ui($service->forQuestion('What is my private order number?')===null,'Private order request added public UI');
    foreach([
        'What is my email address?', 'Change my phone number.', 'What service did I order?',
        'What is the phone number on my account?', 'What email did I use for my order?',
        'Show my registered contact details.', 'Email me.', 'Call me.', 'எனது தொலைபேசி எண் என்ன?',
    ] as $question) {
        checkA2ui($service->forQuestion($question)===null,'Personal request received business cards: '.$question);
        checkA2ui($service->forQuestion($question,['public_business_view'=>'contact'])===null,'Public-tool hint overrode personal request guard');
    }
    foreach([
        'How can I contact you?', 'What is your e-mail address?', 'What phone number do I call?',
        'How can I contact you about my order?', 'உங்கள் தொடர்பு எண் என்ன?',
    ] as $question) {
        $business=$service->forQuestion($question)['messages'][0]['createSurface']??[];
        checkA2ui(str_starts_with($business['surfaceId']??'','bapx_contact_')
            &&count($business['dataModel']['items']??[])===4,'Public business contact intent was lost: '.$question);
    }
    foreach([
        'What services do you provide?'=>['product','temple','order','general'],
        'Tell me about temple guidance services.'=>['temple'],
        'What product inquiry services are available?'=>['product'],
        'What order support services are available?'=>['order'],
        'What services are available for products?'=>['product'],
        'Show me nonexistentuniqueterm services.'=>[],
    ] as $question=>$subjects) {
        $rows=$service->forQuestion($question)['messages'][0]['createSurface']['dataModel']['items']??null;
        checkA2ui(is_array($rows)&&array_column($rows,'path')===array_map(
            static fn(string $subject):string=>'/contact?subject='.$subject.'#contact-form',$subjects),
            'Service question lost its specific subject or showed unrelated cards: '.$question);
    }
    foreach([['private','',2],['products','',0],['products',str_repeat('x',121),1],['articles','',5]] as $bad) {
        try { $service->present(...$bad); throw new RuntimeException('Invalid UI request accepted'); }
        catch(InvalidArgumentException) {}
    }
    // A named request cannot be silently replaced with the first catalogue page.
    $query=PublicA2uiService::questionQuery('What is the price of the Emerald ring?');
    $matched=(new ProductService())->discover([
        ['name'=>'Ruby ring','slug'=>'ruby-ring'],['name'=>'Emerald ring','slug'=>'emerald-ring'],
    ],$query,'');
    checkA2ui(array_column($matched,'slug')===['emerald-ring'],'Named-product query selected an unrelated product');
    $unmatched=$service->forQuestion('Show me articles about nonexistentuniqueterm');
    checkA2ui(($unmatched['messages'][0]['createSurface']['dataModel']['items']??null)===[],'No-match question showed unrelated articles');
    $page=$service->fromResult('products',['products'=>[
        ['name'=>'Emerald ring','path'=>'/product/emerald-ring','priceInr'=>1250,'available'=>true,
            'description'=>'A real tool result.','imagePath'=>'/assets/images/ring.webp'],
    ],'total'=>12,'nextOffset'=>8]);
    $row=$page['messages'][0]['createSurface']['dataModel']['items'][0];
    checkA2ui($row['path']==='/product/emerald-ring'&&$row['imagePath']==='/assets/images/ring.webp','Exact paginated tool result changed during presentation');
    checkA2ui($row['priceInr']===1250.0&&$row['available']===true&&$row['priceLabel']==='₹1,250.00','Product price/availability must remain typed facts');
    $richRows=[
        ['name'=>'Emerald ring','path'=>'/product/emerald-ring','priceInr'=>1250,'available'=>true,
            'description'=>'Published detail.','imagePaths'=>['/assets/images/ring.webp','/assets/images/ring-side.webp','https://bad.invalid/photo.jpg'],
            'highlights'=>['Real feature'],'specifications'=>[['label'=>'Material','value'=>'Silver'],['label'=>'Size','value'=>'Adjustable']]],
        ['name'=>'Ruby pendant','path'=>'/product/ruby-pendant','priceInr'=>900,'available'=>false,
            'specifications'=>[['label'=>'Material','value'=>'Brass']]],
    ];
    foreach(['grid'=>'product_grid','list'=>'product_list','compare'=>'product_compare'] as $layout=>$family) {
        $model=$service->fromResult('products',['products'=>$richRows,'total'=>10,'nextOffset'=>7],4,$layout)['messages'][0]['createSurface'];
        checkA2ui($model['dataModel']['presentation']===$family,'Product layout selection failed');
        checkA2ui(array_column($model['dataModel']['items'],'path')===array_column($richRows,'path'),'Layout changed the exact canonical selections');
        checkA2ui(count($model['components'])<=96,'Public layout exceeds bounded component budget');
        if($layout==='compare')checkA2ui($model['dataModel']['items'][1]['specifications'][1]===['label'=>'Size','value'=>'Not listed'],'Comparison invented a missing specification');
    }
    $detail=$service->fromResult('products',['products'=>[$richRows[0]]],1,'detail')['messages'][0]['createSurface'];
    checkA2ui($detail['dataModel']['presentation']==='product_detail'&&count($detail['dataModel']['items'][0]['imagePaths'])===2,'Detail gallery lost bounded safe images');
    checkA2ui(in_array('extra_images_0',array_column($detail['components'],'id'),true),'Additional photos need a native disclosure');
    $partial=$service->fromResult('products',['products'=>[$richRows[0]]],4,'compare')['messages'][0]['createSurface']['dataModel'];
    checkA2ui($partial['presentation']==='product_detail'&&str_contains($partial['notice'],'two or three matching'),'A missing selection must not claim a complete comparison');
    foreach([
        ['products','',4,'arbitrary',[]],['contact','',4,'grid',[]],
        ['products','ring',4,'compare',['emerald-ring','ruby-pendant']],
        ['products','',4,'compare',[]],['products','',4,'compare',['emerald-ring']],
        ['products','',1,'compare',['emerald-ring','ruby-pendant']],
        ['products','',4,'detail',['emerald-ring','ruby-pendant']],
        ['products','',4,'auto',['emerald-ring','emerald-ring']],
        ['products','',4,'auto',['../private']],
    ] as $arguments) {
        try {$service->present(...$arguments);throw new RuntimeException('Invalid selected-product request accepted');}
        catch(InvalidArgumentException){}
    }
    // Seed the real adapter's request cache: verify native question -> canonical
    // name resolution -> exact selected detail -> comparison, without sockets.
    $store=new DatabaseService();$fixtureUrl='http://127.0.0.1:1/a2ui-selection-fixture';
    (new ReflectionProperty(DatabaseService::class,'cfg'))->setValue($store,['remote_url'=>$fixtureUrl]);
    (new ReflectionProperty(DatabaseService::class,'remoteOnly'))->setValue($store,true);
    $cache=new ReflectionProperty(DatabaseService::class,'remoteQueryCache');$previousCache=$cache->getValue();
    $fixtureSettings=new ReflectionProperty(SettingsService::class,'cache');$previousSettings=$fixtureSettings->getValue();
    $records=[
        ['id'=>'ring','slug'=>'emerald-ring','name'=>'Emerald ring','price'=>1250,'stock_status'=>'in_stock','specifications'=>['Material'=>'Silver']],
        ['id'=>'pendant','slug'=>'ruby-pendant','name'=>'Ruby pendant','price'=>900,'stock_status'=>'out_of_stock'],
        ['id'=>'hidden','slug'=>'private-ring','name'=>'Private ring','price'=>5,'status'=>'hidden'],
    ];
    $cacheKey=hash('sha256',$fixtureUrl."\0SELECT * FROM products\0".serialize([]));
    $cache->setValue(null,[$cacheKey=>array_map(static fn(array $record):array=>['id'=>$record['id'],'_data'=>json_encode($record,JSON_THROW_ON_ERROR)],$records)]);
    $fixtureSettings->setValue(null,[]);putenv('BAPX_TEST_MODE=0');
    try {
        $selectedKnowledge=new PublicKnowledgeService(new ProductService($store));
        $selectedService=new PublicA2uiService($selectedKnowledge);
        $named=$selectedService->forQuestion('Compare the Emerald ring and the Ruby pendant.')['messages'][0]['createSurface']['dataModel'];
        checkA2ui($named['presentation']==='product_compare'&&array_column($named['items'],'path')===['/product/emerald-ring','/product/ruby-pendant'],'Native named comparison did not use the shared published selection');
        foreach(['What is the difference between the Emerald ring and the Ruby pendant?','How does the Emerald ring differ from the Ruby pendant?'] as $question) {
            $phrased=$selectedService->forQuestion($question)['messages'][0]['createSurface']['dataModel'];
            checkA2ui($phrased['presentation']==='product_compare'&&array_column($phrased['items'],'path')===['/product/emerald-ring','/product/ruby-pendant'],'Native comparison phrase lost shared resolver parity');
        }
        $explicit=$selectedService->present('products','',2,'compare',['ruby-pendant','emerald-ring'])['messages'][0]['createSurface']['dataModel'];
        checkA2ui(array_column($explicit['items'],'path')===['/product/ruby-pendant','/product/emerald-ring'],'Explicit comparison lost requested order');
        $missing=$selectedService->present('products','',2,'compare',['private-ring','ruby-pendant'])['messages'][0]['createSurface']['dataModel'];
        checkA2ui($missing['presentation']==='product_detail'&&array_column($missing['items'],'path')===['/product/ruby-pendant'],'Missing/hidden comparison selection was substituted');
        $partial=$selectedService->present('products','',3,'compare',['emerald-ring','missing','ruby-pendant'])['messages'][0]['createSurface']['dataModel'];
        checkA2ui($partial['presentation']==='product_compare'&&array_column($partial['items'],'path')===['/product/emerald-ring','/product/ruby-pendant']
            &&str_contains($partial['notice'],'Showing 2 of 3 selected products.'),'A three-product request silently became a complete two-product comparison');
        $fromTool=$selectedService->fromResult('products',['products'=>$richRows],4,'compare',3)['messages'][0]['createSurface']['dataModel'];
        checkA2ui(str_contains($fromTool['notice'],'Showing 2 of 3 selected products.'),'Exact public tool presentation lost requested selection count');
        $mcp=new PublicMcpService(knowledge:$selectedKnowledge,publicUrl:'https://a2ui.example.test');
        foreach([PublicMcpService::PROTOCOL,PublicMcpService::LEGACY_PROTOCOL] as $version) {
            $params=['name'=>'search_public_products','arguments'=>['slugs'=>['emerald-ring','missing','ruby-pendant'],'limit'=>3]];
            $headers=['mcp-protocol-version'=>$version];
            if($version===PublicMcpService::PROTOCOL) {
                $headers+=['mcp-method'=>'tools/call','mcp-name'=>'search_public_products'];
                $params['_meta']=['io.modelcontextprotocol/protocolVersion'=>$version,'io.modelcontextprotocol/clientCapabilities'=>(object)[]];
            }
            $mcpResponse=$mcp->handle(json_encode(['jsonrpc'=>'2.0','id'=>1,'method'=>'tools/call','params'=>$params],JSON_THROW_ON_ERROR),$headers);
            $mcpModel=$mcpResponse['body']['result']['_meta']['in.bapx/publicSurface']['messages'][0]['createSurface']['dataModel']??[];
            checkA2ui(($mcpModel['presentation']??null)==='product_compare'&&str_contains($mcpModel['notice']??'','Showing 2 of 3 selected products.')
                &&array_column($mcpModel['items'],'path')===['/product/emerald-ring','/product/ruby-pendant'],'MCP Apps lost the requested selection count or substituted data');
        }
    } finally {$cache->setValue(null,$previousCache);$fixtureSettings->setValue(null,$previousSettings);putenv('BAPX_TEST_MODE=1');}
    $unsafe=$service->fromResult('products',['products'=>[
        ['name'=>'Bad path','path'=>'/product/%2e%2e','priceInr'=>1,'available'=>true],
        ['name'=>'Good path','path'=>'/product/good','priceInr'=>1,'available'=>true,'imagePath'=>'https://evil.invalid/tracker.png'],
    ]]);
    $safeItems=$unsafe['messages'][0]['createSurface']['dataModel']['items'];
    checkA2ui(count($safeItems)===1&&!isset($safeItems[0]['imagePath']),'Unsafe navigation or remote image accepted');
    foreach(['/product/%2e%2e','/admin/support-tickets','//evil.invalid','/contact?subject=unknown','/assets/images/../../private.png'] as $path){
        checkA2ui(!PublicA2uiService::safePublicPath($path),'Unsafe public action accepted: '.$path);
    }
    $tamilPath='/product/'.rawurlencode(str_repeat('தமிழ்',10));
    checkA2ui(strlen($tamilPath)>200&&PublicA2uiService::safePublicPath($tamilPath),'Canonical Tamil product path was rejected');
    checkA2ui(PublicA2uiService::safePublicPath('/product/foo..bar'),'Harmless dots inside a slug were rejected');
    checkA2ui(PublicA2uiService::safePublicPath('/product/'.str_repeat('a',2039))&&!PublicA2uiService::safePublicPath('/product/'.str_repeat('a',2040)),'Canonical product path boundary changed');
    checkA2ui(PublicA2uiService::safeImagePath('/assets/images/'.rawurlencode('தமிழ்').'.avif'),'Canonical AVIF/Unicode media was rejected');
    foreach(['/product/%CC%81bad','/product/a%2fb','/product/a%5cb','/product/a%252fb','/product/.','/product/..'] as $path)checkA2ui(!PublicA2uiService::safePublicPath($path),'Invalid decoded product segment accepted');
    foreach(['/assets/images/../x.avif','/assets/images/a%2fb.png','/assets/images/a%252fb.png','/assets/images/.png'] as $path)checkA2ui(!PublicA2uiService::safeImagePath($path),'Invalid decoded image segment accepted');
    $contact=$service->fromResult('contact',['name'=>'Fixture','phones'=>[['label'=>'Phone','number'=>'0000']],
        'email'=>'help@example.test','hours'=>[['days'=>'Mon','hours'=>'9–5']]]);
    checkA2ui(array_column($contact['messages'][0]['createSurface']['dataModel']['items'],'detail')===['0000','help@example.test','Mon 9–5'],'Contact presentation invented or lost canonical fields');
    checkA2ui($contact['messages'][0]['createSurface']['dataModel']['presentation']==='contact_panel'
        &&count(array_filter($contact['messages'][0]['createSurface']['components'],static fn(array $node):bool=>$node['component']==='Button'))===1,'Contact panel repeats its enquiry action');
    checkA2ui(count($service->fromResult('contact',['phones'=>[['label'=>'Phone','number'=>'0000']],
        'email'=>'help@example.test','hours'=>[['days'=>'Mon','hours'=>'9–5']]],1)['messages'][0]['createSurface']['dataModel']['items'])===3,'One contact panel must retain email and hours');
    $services=$service->fromResult('services',['services'=>[['name'=>'Product Inquiry','description'=>'Published support option.',
        'path'=>'/contact?subject=product#contact-form']]]);
    checkA2ui($services['messages'][0]['createSurface']['dataModel']['items'][0]['path']==='/contact?subject=product#contact-form','Service navigation lost selected subject');
    checkA2ui($services['messages'][0]['createSurface']['dataModel']['presentation']==='service_tiles','Service enquiry must have its own presentation');
    $settings=new ReflectionProperty(SettingsService::class,'cache');
    $savedSettings=$settings->getValue();
    try {
        $settings->setValue(null,['module_shop'=>'0','module_blog'=>'0']);
        checkA2ui($service->present('products')['messages'][0]['createSurface']['dataModel']['items']===[],'Disabled shop exposed cards');
        checkA2ui($service->present('articles')['messages'][0]['createSurface']['dataModel']['items']===[],'Disabled blog exposed cards');
        checkA2ui(count($service->present('contact')['messages'][0]['createSurface']['dataModel']['items'])>0,'Module switches removed public contact cards');
        checkA2ui(count($service->present('services')['messages'][0]['createSurface']['dataModel']['items'])>0,'Module switches removed public service cards');
    } finally { $settings->setValue(null,$savedSettings); }
    $presenter=new AgentAnswerPresentationService();
    $original="Authorized summary.\n\nDetailed explanation.\n\n".str_repeat('தமிழ் ',100);
    $private=$presenter->present($original,'admin',[
        ['label'=>'Sales report','path'=>'/admin/tax-report?report=sales&from=2026-11-01&to=2026-11-22'],
        ['label'=>'Admin settings write','path'=>'/admin/settings'],
        ['label'=>'External','path'=>'https://evil.invalid'],
    ],$page);
    checkA2ui(count($private['messages'])===2,'Answer and public references must remain separately scoped');
    $answerModel=$private['messages'][0]['createSurface']['dataModel'];
    checkA2ui(implode("\n\n",array_column($answerModel['sections'],'body'))===trim($original),'Accepted answer content was truncated');
    checkA2ui(count($answerModel['actions'])===1,'Private actions were not bounded to readonly sources');
    $replay=$presenter->present($original,'support');
    checkA2ui(count($replay['messages'])===1&&!isset($replay['messages'][0]['createSurface']['dataModel']['items']),'History/replay fabricated fresh public references');
    $support=$presenter->present('Safe reply.','support',[['label'=>'Owner tickets','path'=>'/admin/support-tickets']]);
    checkA2ui($support['messages'][0]['createSurface']['dataModel']['actions']===[],'Support surface allowed owner navigation');
    $range=['from'=>'2026-11-01','to'=>'2026-11-22'];
    $sales=AgentToolRegistry::salesFacts([
        ['created_at'=>'2026-11-01','status'=>'confirmed','payment_id'=>'fixture_paid_1','total'=>500],
        ['created_at'=>'2026-11-02','status'=>'confirmed','payment_id'=>'fixture_paid_2','total'=>500],
        ['created_at'=>'2026-11-03','status'=>'confirmed','payment_id'=>'fixture_paid_3','total'=>500,
            'verified_refunds'=>[['amount_paise'=>25000]]],
        ['created_at'=>'2026-11-04','status'=>'pending','total'=>900],
    ],$range);
    checkA2ui($sales['orders']===3&&$sales['revenue']===1250.0&&$sales['gross_paid']===1500.0
        &&$sales['pending_order_value']===900.0,'Sales fixture must distinguish paid sales, verified refunds and pending value');
    $evidence=AgentTraceService::toolEvidence('sales_summary',[],$sales+[
        'customer_email'=>'private@example.test','secret'=>'SHOULD_NOT_APPEAR',
    ]);
    $evidence['observed_at']='2026-11-22T09:30:00.000000Z';
    $observed=[['name'=>'sales_summary','status'=>'complete','evidence'=>$evidence]];
    $facts=$presenter->present('Prose must not replace recorded values such as 9999.','admin',[],null,$observed);
    $factsModel=$facts['messages'][0]['createSurface']['dataModel'];
    checkA2ui($factsModel['facts'][0]['metrics']===[
        ['label'=>'Paid orders','value'=>'3'],['label'=>'Net revenue','value'=>'₹1,250.00'],
    ],'Sales labels or metrics diverged from the actual completed salesFacts result');
    checkA2ui($factsModel['presentation']==='report_summary'&&$factsModel['facts'][0]['primary']===1,'Sales report must emphasize its recorded net figure');
    $unsupported=$observed;
    $unsupported[0]['evidence']['facts']+=['units'=>999,'gross'=>888,'refunded'=>777];
    checkA2ui($presenter->present('Legacy evidence.','admin',[],null,$unsupported)['messages'][0]['createSurface']['dataModel']['facts']===$factsModel['facts'],
        'Unsupported historical fields became sales metrics');
    checkA2ui(str_contains($factsModel['facts'][0]['context'],'2026-11-01 to 2026-11-22')&&str_contains($factsModel['facts'][0]['context'],'2026-11-22 09:30:00.000000 UTC'),'Recorded metrics lost period/observation context');
    checkA2ui($factsModel['actions'][0]['path']==='/admin/tax-report?report=sales&from=2026-11-01&to=2026-11-22','Recorded fact source missing');
    checkA2ui(!str_contains(json_encode($facts),'private@example.test')&&!str_contains(json_encode($facts),'SHOULD_NOT_APPEAR'),'Raw tool data leaked into facts');
    $stored=array_map([AgentTraceService::class,'safeTool'],$observed);
    $restored=$presenter->present('Saved answer.','admin',[],null,AgentAnswerPresentationService::toolsForTurn(['tools'=>$stored]));
    checkA2ui($restored['messages'][0]['createSurface']['dataModel']['facts']===$factsModel['facts'],'History facts drifted from recorded evidence');
    $legacy=AgentAnswerPresentationService::toolsForTurn(['tools'=>[['name'=>'sales_summary']],
        'trace'=>['spans'=>[['type'=>'tool']+$stored[0]]]]);
    checkA2ui(($legacy[0]['evidence']['facts']['revenue']??null)===1250.0,'Trace fallback lost recorded metrics');
    $failed=$observed;$failed[0]['status']='error';
    checkA2ui($presenter->present('Lookup failed.','admin',[],null,$failed)['messages'][0]['createSurface']['dataModel']['facts']===[],'Failed lookup created metric cards');
    checkA2ui($presenter->present('Customer answer.','support',[],null,$observed)['messages'][0]['createSurface']['dataModel']['facts']===[],'Support accepted owner aggregate facts');
    $count=['name'=>'store_count','status'=>'complete','evidence'=>AgentTraceService::toolEvidence('store_count',[],['entity'=>'products','count'=>0])];
    $zero=$presenter->present('No records.','admin',[],null,[$count])['messages'][0]['createSurface']['dataModel']['facts'];
    checkA2ui($zero[0]['title']==='Product records'&&$zero[0]['metrics'][0]['value']==='0','Verified zero count must remain distinct from unavailable');
    $missing=$count;unset($missing['evidence']['facts']['count']);
    checkA2ui($presenter->present('Unavailable.','admin',[],null,[$missing])['messages'][0]['createSurface']['dataModel']['facts']===[],'Missing count became zero');
    foreach([
        'gst_register_summary'=>$range+['issued_invoices'=>0,'delivery_state_review_invoices'=>0,
            'review_invoices'=>GstRegisterService::reviewCount([],[]),'totals'=>GstRegisterService::totals([]),
            'currency'=>'INR','report_url'=>'/admin/tax-report?report=tax&from=2026-11-01&to=2026-11-22'],
        'support_enquiries'=>AgentToolRegistry::supportEnquiryCounts([],$range,'orders'),
    ] as $name=>$result) {
        $tool=['name'=>$name,'status'=>'complete','evidence'=>AgentTraceService::toolEvidence($name,[],$result)];
        $model=$presenter->present('Recorded result.','admin',[],null,[$tool])['messages'][0]['createSurface']['dataModel'];
        checkA2ui(count($model['facts'])===1&&count($model['facts'][0]['metrics'])===($name==='gst_register_summary'?7:1),'Approved tool facts missing');
    }
    $commerceCases=[
        'products'=>['summary'=>['products'=>8,'units'=>8,'gross_line_value'=>80,'allocated_net'=>72],
            'rows'=>array_map(static fn(int $i):array=>['product'=>'Published product '.$i,'order_lines'=>1,'units'=>1,'gross'=>10,'net'=>9],range(1,8)),
            'limit'=>8,'total_rows'=>8],
        'stock'=>['summary'=>['products'=>1,'in_stock'=>0,'out_of_stock'=>1,'inactive'=>0,'draft'=>0,'unknown_status'=>0],
            'rows'=>[['product'=>'Internal product','status'=>'out_of_stock','price'=>250,'offer_price'=>null]],'limit'=>6,'total_rows'=>1],
        'customers'=>['summary'=>['customers'=>2,'repeat_customers'=>1,'paid_orders'=>4,'unidentified_orders'=>1,'gross_paid'=>400,'recorded_refunds'=>50,'net_paid'=>350],
            'rows'=>[['band'=>'one_order','customers'=>1,'orders'=>1,'gross'=>100,'refunded'=>0,'net'=>100],
                ['band'=>'two_to_four','customers'=>1,'orders'=>2,'gross'=>200,'refunded'=>50,'net'=>150]],'limit'=>6,'total_rows'=>2],
        'coupons'=>['summary'=>['coupons'=>0,'uses'=>0,'discount'=>0,'net'=>0],'rows'=>[],'limit'=>6,'total_rows'=>0],
    ];
    foreach($commerceCases as $kind=>$case) {
        $result=['report'=>$kind,'currency'=>'INR']+($kind==='stock'?[]:$range)+$case;
        $result['customer_email']='private@example.test';$result['arbitrary_html']='<script>private</script>';
        $recordedReport=AgentTraceService::toolEvidence('commerce_report',[],$result);
        $recordedReport['observed_at']='2026-11-22T10:00:00.000000Z';
        $reportTools=[['name'=>'commerce_report','status'=>'complete','evidence'=>$recordedReport]];
        $surface=$presenter->present('Prose claims 999999; recorded values are authoritative.','admin',[],null,$reportTools)['messages'][0]['createSurface'];
        $model=$surface['dataModel'];
        checkA2ui($model['presentation']==='report_summary'&&$model['reports'][0]['kind']===$kind,'Recorded commerce report family missing');
        checkA2ui(count($model['reports'][0]['rows'])===min(6,$case['total_rows']),'Commerce report ignored the display bound');
        checkA2ui(count($surface['components'])<=224,'Private report exceeds its bounded component budget');
        checkA2ui(str_contains($model['reports'][0]['context'],'2026-11-22 10:00:00.000000 UTC'),'Report lost its recorded observation');
        checkA2ui(!str_contains(json_encode($model),'private@example.test')&&!str_contains(json_encode($model),'<script>'),'Private identities or arbitrary report fields leaked');
        if($kind==='products')checkA2ui(str_contains($model['reports'][0]['coverage'],'Showing 6 of 8'),'Report truncation must be explicit');
        if($kind==='stock')checkA2ui($model['actions'][0]['path']==='/admin/tax-report?report=stock'
            &&str_contains($model['reports'][0]['context'],'Current stock-status snapshot'),'Snapshot report fabricated a date range');
        if($kind==='customers')checkA2ui(array_column($model['reports'][0]['rows'],'title')===['One paid order','Two to four paid orders'],'Customer report must show aggregate bands only');
        if($kind==='coupons')checkA2ui($model['reports'][0]['coverage']==='No matching records.','Zero report lost its honest empty state');
        $savedTools=array_map([AgentTraceService::class,'safeTool'],$reportTools);
        $historyTools=AgentAnswerPresentationService::toolsForTurn(['tools'=>$savedTools,'trace'=>['spans'=>[['type'=>'tool','name'=>'store_count']]]]);
        $history=$presenter->present('Saved report.','admin',[],null,$historyTools)['messages'][0]['createSurface']['dataModel'];
        checkA2ui($history['reports']===$model['reports']&&$history['facts']===$model['facts'],'Report-only history lost or changed its recorded evidence');
        $supportReport=$presenter->present('Customer answer.','support',[],null,$reportTools)['messages'][0]['createSurface']['dataModel'];
        checkA2ui($supportReport['reports']===[]&&$supportReport['facts']===[],'Support accepted private commerce report data');
        $failedReport=$reportTools;$failedReport[0]['status']='error';
        checkA2ui($presenter->present('Unavailable.','admin',[],null,$failedReport)['messages'][0]['createSurface']['dataModel']['reports']===[],'Failed report became a success card');
    }
    $unidentified=AgentTraceService::toolEvidence('commerce_report',[],['report'=>'customers','currency'=>'INR']+$range+[
        'summary'=>['customers'=>0,'repeat_customers'=>0,'paid_orders'=>2,'unidentified_orders'=>2,'gross_paid'=>200,'recorded_refunds'=>0,'net_paid'=>200],
        'rows'=>[],'limit'=>6,'total_rows'=>0,
    ]);
    $unidentifiedModel=$presenter->present('No identified customer groups.','admin',[],null,[
        ['name'=>'commerce_report','status'=>'complete','evidence'=>$unidentified],
    ])['messages'][0]['createSurface']['dataModel'];
    checkA2ui($unidentifiedModel['reports'][0]['coverage']==='No identified customer groups for this period.'
        &&$unidentifiedModel['facts'][0]['metrics'][2]===['label'=>'Paid orders','value'=>'2'],
        'Unidentified paid orders must not be described as no matching records');
    $complete=str_repeat('x',12000);
    checkA2ui($presenter->present($complete)['messages'][0]['createSurface']['dataModel']['sections'][0]['body']===$complete,'Maximum accepted answer was truncated');
    checkA2ui($presenter->responseData($complete.'x')===[],'Oversize presentation must leave original text fallback');
    checkA2ui(PublicA2uiService::inputSchema()['required']===['view'],'Tool input declaration incomplete');
    checkA2ui(PublicA2uiService::outputSchema()['required']===['messages'],'Tool output declaration incomplete');
    echo "A2UI public relevance/pagination/contact/services, private answer preservation/actions/replay, safe components and schemas PASS.\n";
} finally {
    foreach(glob($root.'/*')?:[] as $file) @unlink($file);
    @rmdir($root);
}
