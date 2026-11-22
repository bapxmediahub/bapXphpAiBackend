<?php
declare(strict_types=1);
use App\Services\{PublicA2uiService,PublicKnowledgeService,BlogService,AgentAnswerPresentationService,ProductService,SettingsService,AgentTraceService,AgentToolRegistry,GstRegisterService};
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
    checkA2ui(count($components)===8,'Unexpected number of rendered components');
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
    $services=$service->fromResult('services',['services'=>[['name'=>'Product Inquiry','description'=>'Published support option.',
        'path'=>'/contact?subject=product#contact-form']]]);
    checkA2ui($services['messages'][0]['createSurface']['dataModel']['items'][0]['path']==='/contact?subject=product#contact-form','Service navigation lost selected subject');
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
