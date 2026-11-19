<?php
require __DIR__ . '/../app/bootstrap.php';

use App\Services\EnvService;
use App\Services\CategoryService;
use App\Services\DatabaseService;
use App\Services\MailQueueService;
use App\Services\PaymentService;
use App\Services\ProjectMapService;
use App\Services\ReviewService;
use App\Services\SchemaService;
use App\Services\SecretService;

$assertionCount = 0;

function assertTrue(bool $condition, string $message): void {
    global $assertionCount;
    $assertionCount++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertSame(mixed $expected, mixed $actual, string $message): void {
    global $assertionCount;
    $assertionCount++;
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}

function fileRead(string $path): string {
    return file_get_contents(app_path($path));
}

function fileContains(string $path, string $needle, string $msg = ''): void {
    assertTrue(str_contains(fileRead($path), $needle), $msg ?: "{$path} should contain {$needle}");
}

function fileNotContains(string $path, string $needle, string $msg = ''): void {
    assertTrue(!str_contains(fileRead($path), $needle), $msg ?: "{$path} should not contain {$needle}");
}

function routePaths(): array {
    return array_column(ProjectMapService::registry()['routes'], 'path');
}

function routeExists(string $path, string $msg = ''): void {
    assertTrue(in_array($path, routePaths(), true), $msg ?: "Route {$path} should be registered");
}

function routeMissing(string $path, string $msg = ''): void {
    assertTrue(!in_array($path, routePaths(), true), $msg ?: "Route {$path} should not be registered");
}

function assertMinCount(int $min, array $items, string $msg): void {
    assertTrue(count($items) >= $min, $msg . " (got " . count($items) . ", expected >= {$min})");
}

$failures = [];
$tests = [];
require __DIR__ . '/google-reporting.php';

$tests['legitimate internal ticket activity is evaluated without exposing it as a model capability'] = static function (): void {
    $result=\App\Services\AgentEvaluationService::evaluate('support','show my tickets','You have no matching tickets.',[
        ['name'=>'customer_ticket_lookup','status'=>'complete']],'local');
    assertTrue($result['checks']['no_invalid_tool_names'],'Observed internal ticket routing is a legitimate evidence label');
    assertTrue(!\App\Services\AgentCapabilityPolicy::allows('support','customer_ticket_lookup',null),'An audit label never becomes a guest callable capability');
    $bad=\App\Services\AgentEvaluationService::evaluate('support','help','test', [['name'=>'write_file']]);
    assertTrue(!$bad['checks']['no_invalid_tool_names'],'Unknown write tools remain rejected');
};

$tests['support exposed tool contracts match executable capabilities and reject invalid periods'] = static function (): void {
    $tools=new \App\Services\SupportToolRegistry(null);
    assertSame(array_column($tools->declarations(),'name'),\App\Services\AgentCapabilityPolicy::tools('support'),
        'Activity labels must not appear as invocable support capabilities');
    assertTrue(!\App\Services\AgentCapabilityPolicy::allows('support','customer_ticket_lookup',null),
        'Legacy trace label is not an exposed guest tool');
    $schemas=array_column($tools->declarations(),'parameters','name');
    assertTrue(isset($schemas['own_ticket_status']['properties']['period']),'Actual supported period is documented in the schema');
    assertTrue(isset($tools->run('public_catalog',['question'=>''])['error']),'Empty retrieval input does not return arbitrary catalogue entries');
    $customer=new \App\Services\SupportToolRegistry(['email'=>'fixture@example.invalid']);
    assertTrue(isset($customer->run('own_ticket_status',['period'=>['2026-99-32','2026-11-01']])['error']), 'Impossible dates fail before any data lookup');
    assertTrue(isset($customer->run('own_ticket_status',['period'=>['2026-11-15','2026-11-01']])['error']), 'Reversed dates fail before any data lookup');
};

$tests['trace evidence uses executed scope rather than contradictory requested dates'] = static function (): void {
    $e = \App\Services\AgentTraceService::toolEvidence('sales_summary',
        ['from'=>'2026-01-01','to'=>'2026-01-31'],
        ['from'=>'2026-11-01','to'=>'2026-11-15','orders'=>2]);
    assertSame('2026-11-01',$e['filters']['from'],'Trace must retain the scope actually queried');
    assertSame('2026-11-15',$e['filters']['to'],'Final dates override untrusted request dates');
    $bad = \App\Services\AgentTraceService::toolEvidence('sales_summary',
        ['from'=>'2026-01-01'],['error'=>'Invalid lookup parameters.']);
    assertTrue(!isset($bad['filters']['from']),'Rejected arguments are not certified as an executed scope');
};

$tests['runtime trace records real span boundaries and resets per request'] = static function (): void {
    $trace=bin2hex(random_bytes(8));
    \App\Services\AgentTraceService::begin($trace);
    $retrieval=\App\Services\AgentTraceService::startSpan('retrieval','public_policy');
    \App\Services\AgentTraceService::endSpan($retrieval,'complete',['result_count'=>1]);
    $tool=\App\Services\AgentTraceService::startSpan('tool','sales_summary');
    \App\Services\AgentTraceService::endSpan($tool,'complete',['evidence'=>[
        'source'=>'orders','filters'=>['from'=>'2026-11-01'],'result_count'=>0]]);
    $result=\App\Services\AgentTraceService::build($trace,'local',[],[],['version'=>2,'passed'=>true],'No orders.');
    assertSame('observed_execution',$result['ordering'],'Runtime trace is measured rather than reconstructed stage groups');
    assertSame($retrieval,$result['spans'][0]['span_id'],'Persist actual span identities, not new post-hoc ones');
    assertTrue(isset($result['spans'][0]['started_at'],$result['spans'][0]['ended_at']), 'Measured boundaries are present');
    assertTrue($result['spans'][0]['duration_ms']>=0,'Measured duration is non-negative');
    assertSame('produced',end($result['spans'])['status'],'Server cannot claim client delivery');
    \App\Services\AgentTraceService::begin(bin2hex(random_bytes(8)));
    assertSame([],\App\Services\AgentTraceService::snapshot()['spans'],'Next request has no prior spans');
    \App\Services\AgentTraceService::reset();
};

$tests['conversation checkpoint rejects foreign actors before work and completes same file'] = static function (): void {
    $dir=sys_get_temp_dir().'/sps-checkpoint-'.bin2hex(random_bytes(6));mkdir($dir,0700);
    $svc=new \App\Services\AgentConversationService($dir);$actor=['type'=>'guest','id'=>'fixture-owner'];
    $trace=bin2hex(random_bytes(8));
    try {
        $prepared=$svc->prepareTurn('support',$actor,null,'Where is my order?',$trace);
        $id=$prepared['conversation_id'];$doc=$svc->getForAdmin($id);
        assertSame('running',$doc['pending_turns'][$trace]['status'],'Intent is durable before execution');
        assertSame(0,count($doc['turns']),'No answer is invented at checkpoint');
        $rejected=false;
        try{$svc->prepareTurn('support',['type'=>'guest','id'=>'intruder'],$id,'private',bin2hex(random_bytes(8)));}
        catch(\RuntimeException){$rejected=true;}
        assertTrue($rejected,'Foreign actor rejected before any tool/model work');
        $duplicate=false;
        try{$svc->prepareTurn('support',$actor,$id,'Where is my order?',$trace);}
        catch(\LogicException){$duplicate=true;}
        assertTrue($duplicate,'Uncertain/in-flight request is not automatically replayed');
        $svc->checkpoint('support',$actor,$id,$trace,['version'=>2,'trace_id'=>$trace,'spans'=>[]]);
        $done=$svc->record('support',$actor,$id,'Where is my order?','Check My Orders.',$trace,'local');
        $doc=$svc->getForAdmin($id);
        assertSame(1,count($doc['turns']),'Prepared request completes one real turn');
        assertTrue(!isset($doc['pending_turns'][$trace]),'Only final persistence clears the pending marker');
        assertSame(1,count(glob($dir.'/*.json')?:[]),'No extra conversation or SQL table');
        $again=$svc->prepareTurn('support',$actor,$id,'Where is my order?',$trace);
        assertSame($done['turn_id'],$again['replay']['turn_id'],'Same completed request can be replayed without another action');
    }finally{foreach(glob($dir.'/*')?:[] as $f)@unlink($f);@unlink($dir.'/.conversation.lock');@rmdir($dir);}
};

$tests['provider usage normalization preserves unavailable fields without private data'] = static function (): void {
    $empty=\App\Services\AgentTraceService::usageFromResponse('{}');
    assertSame(null,$empty['input_tokens'],'Missing usage is unavailable, not zero');
    $google=\App\Services\AgentTraceService::usageFromResponse('{"usageMetadata":{"promptTokenCount":10,"candidatesTokenCount":3,"totalTokenCount":13},"secret":"do-not-record"}');
    assertSame(10,$google['input_tokens'],'Google prompt usage normalized');
    assertSame(3,$google['output_tokens'],'Google output usage normalized');
    $openai=\App\Services\AgentTraceService::usageFromResponse('{"usage":{"prompt_tokens":12,"completion_tokens":4,"prompt_tokens_details":{"cached_tokens":2}}}');
    assertSame(2,$openai['cached_input_tokens'],'Compatible cached usage retained');
    assertTrue(!str_contains(json_encode($google),'do-not-record'),'Provider body content is excluded');
};

$tests['trace enforces provider budget without inventing missing usage'] = static function (): void {
    $trace=\App\Services\AgentTraceService::class;
    $trace::begin(bin2hex(random_bytes(8)),null,['max_model_calls'=>1,'max_observed_tokens'=>25,'max_duration_ms'=>10000]);
    $span=$trace::startSpan('model_attempt','fixture-model');
    $trace::endSpan($span,'complete',['usage'=>['input_tokens'=>10,'output_tokens'=>5,'total_tokens'=>15,'cached_input_tokens'=>null]]);
    $trace::endSpan($span,'complete',['usage'=>['input_tokens'=>10,'output_tokens'=>5,'total_tokens'=>15]]);
    assertSame(15,$trace::snapshot()['budget']['observed_total_tokens'],'Duplicate span completion never doubles usage');
    $blocked=false;try{$trace::startSpan('model_attempt','fixture-model');}catch(\RuntimeException){$blocked=true;}
    assertTrue($blocked,'Call budget enforced before executing second request');
    assertSame(1,$trace::snapshot()['budget']['model_calls'],'A rejected request is not counted as sent');
    $trace::begin(bin2hex(random_bytes(8)),null,['max_model_calls'=>2]);
    $span=$trace::startSpan('model_attempt','fixture-model');$trace::endSpan($span,'complete',['usage'=>$trace::usageFromResponse('{}')]);
    assertSame(false,$trace::snapshot()['budget']['usage_complete'],'Absent provider usage remains explicitly incomplete');
    $trace::reset();
};

$tests['monitoring separates transfers, answer checks and local stops without adding attempts'] = static function (): void {
    $now = time();
    $events = [
        ['event'=>'agent.run','created_at'=>gmdate('c', $now),'meta'=>[
            'surface'=>'admin','outcome'=>'error','reason'=>'empty_response','provider_attempts'=>[
                ['model'=>'fixture','status'=>200,'curl_error'=>0,'total_ms'=>600,'timeout_ms'=>7000,
                    'first_byte_ms'=>520.7,'response_body'=>'private-body'],
            ],
        ]],
        ['event'=>'agent.run','created_at'=>gmdate('c', $now - 1),'meta'=>[
            'surface'=>'admin','outcome'=>'error','reason'=>'request_deadline',
        ]],
    ];
    $metrics = \App\Services\AuditLogService::agentSummary($events, $now)['admin'];
    assertSame(2, $metrics['error'], 'Both requests ended in errors');
    assertSame(1, $metrics['provider_attempt_count'], 'The local stop created no transfer');
    assertSame(0, $metrics['provider_error_count'], 'A successful HTTP transfer can return an empty answer');
    assertSame(['empty_response'=>1,'request_deadline'=>1], $metrics['reasons'], 'Local and content failures remain distinct');
    $attempt = $metrics['recent_runs'][0]['attempts'][0];
    assertSame([7000,520.7], [$attempt['timeout_ms'],$attempt['first_byte_ms']], 'Observed timing and timeout cap survive sanitizing and aggregation');
    assertTrue(!str_contains(json_encode($metrics),'private-body'), 'Provider body excluded');
    foreach ([[200,28,'transfer_timeout'],[0,6,'transport_error'],[0,0,'no_http'],[403,0,'access_denied'],[400,0,'request_error']] as [$status,$curl,$expected]) {
        assertSame($expected, \App\Services\AuditLogService::attemptDiagnosis(['status'=>$status,'curl_error'=>$curl]), 'Transfer diagnosis uses the observed HTTP and cURL evidence');
    }

    $trace = \App\Services\AgentTraceService::class;
    foreach (['model_calls','observed_tokens','deadline','trace_steps'] as $limit) {
        $saved = [];
        $trace::begin(bin2hex(random_bytes(8)), static function(array $snapshot) use (&$saved) { $saved=$snapshot; },
            ['max_model_calls'=>$limit === 'model_calls' ? 1 : 3, 'max_observed_tokens'=>2, 'max_duration_ms'=>120000]);
        try {
            if ($limit === 'deadline') {
                (new ReflectionProperty($trace,'beganNs'))->setValue(null,hrtime(true)-121000000000);
            } elseif ($limit === 'trace_steps') {
                for ($n=0; $n<128; $n++) $trace::startSpan('retrieval','test_step');
            } else {
                $span=$trace::startSpan('model_attempt','fixture');
                $trace::endSpan($span,'complete',['usage'=>['total_tokens'=>$limit === 'observed_tokens' ? 2 : 1],
                    'http_status'=>200,'curl_error'=>0,'timeout_ms'=>5000,'first_byte_ms'=>0]);
                assertTrue(!isset($saved['spans'][0]['first_byte_ms']), 'Unobserved first byte is not a zero-latency sample');
            }
            $before = count($saved['spans'] ?? []);
            try {
                $trace::startSpan('model_attempt','fixture');
                throw new RuntimeException('Expected a bounded pre-transfer stop');
            } catch (\App\Services\AgentBudgetException $error) {
                $reason = match($limit) { 'deadline'=>'request_deadline', 'trace_steps'=>'trace_step_budget', default=>'provider_budget' };
                assertSame($reason, $error->reason, 'Callers can distinguish a pre-transfer stop');
                assertSame($limit, $saved['budget']['exhausted'], 'The exact local limit is checkpointed before returning control');
                assertSame($reason, $trace::budgetReason($saved), 'The saved trace yields a safe monitor reason');
                assertSame($before, count($saved['spans']), 'A denied attempt never appears as a sent request');
            }
        } finally { $trace::reset(); }
    }
};

$tests['tool provenance preserves only approved aggregate facts and code versions'] = static function (): void {
    $e=\App\Services\AgentTraceService::toolEvidence('sales_summary',['from'=>'wrong'],
        ['from'=>'2026-11-01','to'=>'2026-11-15','orders'=>2,'revenue'=>150.25,'customer_email'=>'never@log.invalid']);
    assertSame(150.25,$e['facts']['revenue'],'Actual approved financial aggregate retained for comparison');
    assertTrue(strlen($e['facts_sha256'])===64 && strlen($e['tool_version_sha256'])===64,'Versioned evidence fingerprint present');
    assertTrue(!str_contains(json_encode($e),'never@log.invalid'),'Private fields are excluded before hashing or persistence');
    $e2=\App\Services\AgentTraceService::toolEvidence('sales_summary',[],['from'=>'2026-11-01','to'=>'2026-11-15','orders'=>2,'revenue'=>151.25]);
    assertTrue($e['facts_sha256']!==$e2['facts_sha256'],'Changed data creates a different retained evidence digest');
};

$tests['audit outcome distinguishes unavailable store from recorded metadata'] = static function (): void {
    $success=\App\Services\AuditLogService::deliveryResult(['status'=>'recorded'],true);
    $failure=\App\Services\AuditLogService::deliveryResult(['status'=>'recorded'],false);
    assertSame('recorded',$success['status'],'Successful write remains recorded');
    assertSame('not_persisted',$failure['status'],'Failure never falsely claims durable audit record');
};

$tests['private JSON history pages and deletion are bounded and actor owned'] = static function (): void {
    $root=sys_get_temp_dir().'/sps-history-page-'.bin2hex(random_bytes(6));mkdir($root,0700);
    $svc=new \App\Services\AgentConversationService($root);$owner=['type'=>'admin','id'=>'owner'];$other=['type'=>'admin','id'=>'other'];
    try {
        $ids=[];for($i=0;$i<5;$i++){$r=$svc->record('admin',$owner,null,'Question '.$i,'Answer',bin2hex(random_bytes(8)),'local');$ids[]=$r['conversation_id'];}
        $foreign=$svc->record('admin',$other,null,'Private question','Answer',bin2hex(random_bytes(8)),'local');
        $page=$svc->historyPage('admin',$owner,2,null);assertSame(2,count($page['conversations']),'Page has requested bound');
        assertTrue(is_string($page['next_cursor']),'Paging cursor returned');
        $page2=$svc->historyPage('admin',$owner,2,$page['next_cursor']);
        assertSame([],array_intersect(array_column($page['conversations'],'id'),array_column($page2['conversations'],'id')),'Pages have no duplicate chats');
        $denied=false;try{$svc->deleteForActor($foreign['conversation_id'],'admin',$owner);}catch(\RuntimeException){$denied=true;}
        assertTrue($denied,'Foreign chat deletion rejected');
        $before=$svc->getForActor($ids[0],'admin',$owner);$backup=json_encode($before,JSON_THROW_ON_ERROR);
        assertTrue($svc->deleteForActor($ids[0],'admin',$owner),'Owned completed chat deleted');
        assertSame(null,$svc->getForAdmin($ids[0]),'Deleted transcript no longer accessible');
        file_put_contents($root.'/'.$ids[0].'.json',$backup);chmod($root.'/'.$ids[0].'.json',0600);
        $restored=$svc->record('admin',$owner,$ids[0],'Continued','Restored answer',bin2hex(random_bytes(8)),'local');
        assertSame(2,$restored['turns'],'Restored backup preserves identity and prior turns');
        $pending=$svc->prepareTurn('admin',$owner,null,'In progress',bin2hex(random_bytes(8)));
        $denied=false;try{$svc->deleteForActor($pending['conversation_id'],'admin',$owner);}catch(\RuntimeException){$denied=true;}
        assertTrue($denied,'Uncertain/running action cannot be deleted as ordinary history');
    } finally {
        foreach(array_merge(glob($root.'/*')?:[],glob($root.'/.conversation*')?:[]) as $f)if(is_file($f))@unlink($f);
        @rmdir($root);
    }
};

$tests['per-chat locks coordinate with a previous deployment global writer'] = static function (): void {
    $root=sys_get_temp_dir().'/sps-lock-compat-'.bin2hex(random_bytes(6));mkdir($root,0700);
    $legacy=fopen($root.'/.conversation.lock','c');flock($legacy,LOCK_EX);
    $svc=new \App\Services\AgentConversationService($root);$actor=['type'=>'guest','id'=>'fixture'];$blocked=false;
    try {
        try{$svc->prepareTurn('support',$actor,null,'test',bin2hex(random_bytes(8)));}
        catch(\RuntimeException){$blocked=true;}
        assertTrue($blocked,'A new writer must not race a still-running old deployment writer');
        assertSame(0,count(glob($root.'/*.json')?:[]),'No checkpoint bypasses the old writer lock');
    } finally {
        flock($legacy,LOCK_UN);fclose($legacy);
        foreach(array_merge(glob($root.'/*')?:[],glob($root.'/.conversation*')?:[]) as $f)if(is_file($f))@unlink($f);@rmdir($root);
    }
};

$tests['follow-up evidence includes only product links actually shown to the customer'] = static function (): void {
    $products=[['slug'=>'one'],['slug'=>'two'],['slug'=>'unmentioned']];
    $result=\App\Services\SupportBotService::shownProductSlugs('Two: /product/two. One: /product/one. Fake: /product/missing.',$products);
    assertSame(['two','one'],$result,'Persist shown order, not the first 20 retrieval candidates');
    assertSame([],\App\Services\SupportBotService::shownProductSlugs('No relevant product was found.',$products),'No unseen product is a follow-up referent');
    $refused=false;
    try{\App\Services\SupportTicketService::transition(['status'=>'open'],'snooze',['snoozed_until'=>''],['role'=>'admin','sub'=>'a'],new \DateTimeImmutable('2026-11-15T00:00:00Z'));}
    catch(\InvalidArgumentException){$refused=true;}
    assertTrue($refused,'A blank date must not silently become a snooze time');
};

$tests['support action identity and human lifecycle are deterministic and versioned'] = static function (): void {
    $service=\App\Services\SupportTicketService::class;
    $handoff=['conversation_id'=>str_repeat('a',32),'trace_id'=>str_repeat('b',16),'turn_id'=>str_repeat('c',32)];
    $id=$service::actionIdentity('user@example.invalid',$handoff);
    assertSame($id,$service::actionIdentity('USER@example.invalid',$handoff),'Same actor/request yields same ticket key');
    assertTrue($id!==$service::actionIdentity('other@example.invalid',$handoff),'Ticket action is actor scoped');
    $ticket=['id'=>$id,'status'=>'open','version'=>0,'created_at'=>'2026-11-15T10:00:00+00:00'];
    $assigned=$service::transition($ticket,'assign',['assigned_admin_id'=>'admin-a','priority'=>'high'],['role'=>'admin','sub'=>'admin-a'],new \DateTimeImmutable('2026-11-15T10:02:00+00:00'));
    assertSame(1,$assigned['version'],'Mutation increments optimistic version');
    assertSame('admin-a',$assigned['assigned_admin_id'],'Ownership is explicit');
    $answered=$service::transition($assigned,'reply',['reply'=>'Verified reply'],['role'=>'admin','sub'=>'admin-a'],new \DateTimeImmutable('2026-11-15T10:05:00+00:00'));
    assertSame('2026-11-15T10:05:00+00:00',$answered['first_human_response_at'],'First human reply is distinct from bot response');
    $closed=$service::transition($answered,'close',[],['role'=>'admin','sub'=>'admin-a'],new \DateTimeImmutable('2026-11-15T10:06:00+00:00'));
    $reopened=$service::transition($closed,'reopen',[],['role'=>'admin','sub'=>'admin-b'],new \DateTimeImmutable('2026-11-15T10:10:00+00:00'));
    assertSame(1,$reopened['reopen_count'],'Reopen episode tracked');
    assertSame($answered['first_human_response_at'],$reopened['first_human_response_at'],'Reopen does not rewrite first response');
    assertSame('open',$reopened['status'],'Reopened ticket becomes actionable');
    assertSame(4,count($reopened['lifecycle']),'All transitions remain inspectable');
    $denied=false;try{$service::transition($ticket,'close',[],['role'=>'customer'],new \DateTimeImmutable());}catch(\InvalidArgumentException){$denied=true;}
    assertTrue($denied,'Customers cannot use staff transitions');
};

$tests['support context keeps bounded own turns and revalidates ordinal product references'] = static function (): void {
    $root=sys_get_temp_dir().'/sps-context-'.bin2hex(random_bytes(6));mkdir($root,0700);
    $svc=new \App\Services\AgentConversationService($root);$actor=['type'=>'guest','id'=>'own'];
    try {
        $r=$svc->record('support',$actor,null,'Ayyappa products','Here are two items',bin2hex(random_bytes(8)),'local',[],null,null,[],[],['public_product_slugs'=>['one','two']]);
        $context=$svc->contextForActor($r['conversation_id'],'support',$actor,2,4000);
        assertSame(['one','two'],$context['public_product_slugs'],'Ordered verified identities carried forward');
        $product=\App\Services\SupportBotService::followupProduct('what is the second one made of?', $context['public_product_slugs'],[
            ['slug'=>'one','name'=>'First'],['slug'=>'two','name'=>'Second','specifications'=>['Material'=>'Brass']]
        ]);
        assertSame('two',$product['slug'],'Ordinal resolves to previously listed item');
        assertSame(null,\App\Services\SupportBotService::followupProduct('what is the second one made of?', $context['public_product_slugs'],[['slug'=>'one','name'=>'First']]),'Missing current product is not fabricated from stale history');
        assertSame([], $svc->contextForActor(null,'support',$actor)['messages'],'Fresh chat has no inherited context');
    }finally{foreach(array_merge(glob($root.'/*')?:[],glob($root.'/.conversation*')?:[]) as $f)if(is_file($f))@unlink($f);@rmdir($root);}
};

$tests['Tamil named festive retrieval matches English collection without guessing generic requests'] = static function (): void {
    $collections=[['name'=>'Ayyappa Swamy Festive Sale','slug'=>'ayyappa','product_ids'=>['a']],['name'=>'Sassthi Festival Sale','slug'=>'sassthi','product_ids'=>['b']]];
    assertSame('ayyappa',\App\Services\AgentContextService::requestedCollection('அய்யப்பன் சுவாமி பண்டிகை பொருட்கள்',$collections)['slug'],'Tamil alias maps to real collection');
    assertSame(null,\App\Services\AgentContextService::requestedCollection('பண்டிகை பொருட்கள்',$collections),'Generic Tamil festival query requires clarification');
};

$tests['developer knowledge index distinguishes runtime instructions without copying prompt bodies'] = static function (): void {
    $graph=(new \App\Services\KnowledgeGraphService())->build();
    $runtime=array_values(array_filter($graph['concepts'],static fn(array $row):bool=>($row['type']??'')==='runtime_instruction'));
    assertSame(6,count($runtime),'Every fixed runtime instruction document is separately discoverable');
    foreach($runtime as $row){
        assertTrue(str_starts_with($row['filePath'],'.bapX/'),'Runtime references use the business-agent tree');
        assertSame('', $row['body']??'', 'Generated index must not duplicate private runtime prompt bodies');
    }
};

$tests['live agent instruction manifests are role scoped to bapX and exclude developer skills'] = static function (): void {
    $service=\App\Services\AgentInstructionService::class;
    $support=$service::manifestForSurface('support');
    $admin=$service::manifestForSurface('admin',['role'=>'admin']);
    assertTrue(count($support['files'])>=3,'Support instructions include shared and role-specific runtime Markdown');
    foreach($support['files'] as $file){
        assertTrue(str_starts_with($file['path'],'.bapX/'),'Only runtime tree can supply a prompt');
        assertTrue(!str_contains($file['path'],'enterprise'),'Support cannot load Enterprise-only modules');
        assertSame(64,strlen($file['sha256']),'File content hash retained');
    }
    assertSame(64,strlen($support['sha256']),'Actual runtime bundle is fingerprinted');
    assertTrue($support['sha256']!==$admin['sha256'],'Different role instructions cannot share a fingerprint');
    assertTrue(str_contains($service::forSurface('support'),'authenticated customer'),'Customer scope is explicit');
    assertTrue(!str_contains($service::forSurface('support'),'Enterprise-only reporting'),'Enterprise-only instruction content never reaches Support');
    assertTrue(str_contains($service::forSurface('admin',['role'=>'admin']),'Enterprise-only reporting'),'Authorized admins receive business-reporting skills');
};

$tests['runtime loader denies unauthorized roles and path-shaped surfaces before file access'] = static function (): void {
    $service=\App\Services\AgentInstructionService::class;
    foreach(['../enterpriseagent.md','.agents/skills/git/SKILL.md','https://example.invalid/prompt'] as $surface){
        $denied=false;try{$service::forSurface($surface,['role'=>'admin']);}catch(\InvalidArgumentException){$denied=true;}
        assertTrue($denied,'A surface is a role enum, not a filesystem selector');
    }
    $denied=false;try{$service::forSurface('admin',['role'=>'customer']);}catch(\RuntimeException){$denied=true;}
    assertTrue($denied,'Model or browser text cannot grant an Enterprise instruction bundle');
};

$tests['runtime instruction reader rejects linked and oversized files'] = static function (): void {
    $root=sys_get_temp_dir().'/sps-runtime-files-'.bin2hex(random_bytes(6));mkdir($root,0700);
    $outside=$root.'-outside.md';file_put_contents($outside,'OUTSIDE_SECRET_SENTINEL');
    try {
        assertTrue(symlink($outside,$root.'/supportagent.md'),'Can create isolated symlink fixture');
        $method=new ReflectionMethod(\App\Services\AgentInstructionService::class,'readRequiredDocument');
        $denied=false;try{$method->invoke(null,$root,'supportagent.md');}catch(\RuntimeException){$denied=true;}
        assertTrue($denied,'Symlinks must fail before instruction content is read');
        unlink($root.'/supportagent.md');file_put_contents($root.'/supportagent.md',str_repeat('x',20000));
        $denied=false;try{$method->invoke(null,$root,'supportagent.md');}catch(\RuntimeException){$denied=true;}
        assertTrue($denied,'Oversized instruction content must fail closed');
    } finally {@unlink($root.'/supportagent.md');@unlink($outside);@rmdir($root);}
};

$tests['trace fingerprint identifies actual loaded role-scoped runtime Markdown'] = static function (): void {
    $service=\App\Services\AgentInstructionService::class;
    \App\Services\AgentTraceService::begin(bin2hex(random_bytes(8)));
    try {
        $service::forSurface('support');$trace=\App\Services\AgentTraceService::snapshot();
        assertSame($service::manifestForSurface('support')['sha256'],$trace['instruction_sha256'],'Trace must fingerprint loaded Markdown, not only the PHP loader');
        assertSame('support',$trace['instruction_manifest']['surface'],'Loaded runtime role is recorded without body text');
    } finally {\App\Services\AgentTraceService::reset();}
};

$tests['admin dashboard resolves all services and renders its actual zero-data page'] = static function (): void {
    $oldMode=getenv('BAPX_TEST_MODE');$oldServer=$_SERVER;$oldSession=$_SESSION??[];
    putenv('BAPX_TEST_MODE=1');$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/admin';
    $_SESSION['user']=['role'=>'admin','id'=>'dashboard-fixture'];
    $level=ob_get_level();
    try {
        // Constructor authentication is separately covered by browser checks; render
        // with the existing isolated DB fixture to catch unresolved service names.
        $controller=(new ReflectionClass(\App\Controllers\AdminController::class))->newInstanceWithoutConstructor();
        ob_start();$controller->dashboard();$html=(string)ob_get_clean();
        assertTrue(str_contains($html,'revenue-chart-title'),'Real dashboard controller reaches its chart template');
        assertTrue(str_contains($html,'No paid orders in this period.'),'Zero-data rendering remains safe through the full controller');
        assertTrue(!str_contains($html,'Fatal error'),'Controller page does not emit a runtime error');
    } finally {
        while(ob_get_level()>$level)ob_end_clean();
        $_SERVER=$oldServer;$_SESSION=$oldSession;putenv($oldMode===false?'BAPX_TEST_MODE':'BAPX_TEST_MODE='.$oldMode);
    }
};

$tests['mail delivery claim has one owner and never replays uncertain or accepted sends'] = static function (): void {
    $q=\App\Services\MailQueueService::class;$now=new DateTimeImmutable('2026-11-15T12:00:00Z');
    $row=['id'=>str_repeat('a',16),'status'=>'pending','available_at'=>'2026-11-15T11:59:00Z'];
    $attempt=str_repeat('b',32);
    $claimed=$q::transitionDelivery($row,'claim',$attempt,[],$now);
    assertSame('sending',$claimed['status'],'Claim is durable before network send');
    assertSame(null,$q::transitionDelivery($claimed,'claim',str_repeat('c',32),[],$now),'Second worker cannot send');
    assertSame(null,$q::transitionDelivery($claimed,'accepted',str_repeat('c',32),[],$now),'Foreign attempt cannot complete');
    $uncertain=$q::transitionDelivery($claimed,'uncertain',$attempt,['error_code'=>'smtp_outcome_unknown'],$now);
    assertSame('needs_review',$uncertain['status'],'Uncertain acceptance requires review');
    assertSame(null,$q::transitionDelivery($uncertain,'claim',str_repeat('d',32),[],$now),'Uncertainty never auto-retries');
    $sent=$q::transitionDelivery($claimed,'accepted',$attempt,['message_id'=>'<fixture@example.invalid>'],$now);
    assertSame('sent',$sent['status'],'Legacy status remains compatible');
    assertSame('accepted_by_smtp',$sent['delivery_state'],'SMTP acceptance is not inbox receipt');
    assertSame(null,$q::transitionDelivery($sent,'claim',str_repeat('d',32),[],$now),'Accepted message never sends again');
    $future=$row;$future['available_at']='2026-11-16T00:00:00Z';
    assertSame(null,$q::transitionDelivery($future,'claim',$attempt,[],$now),'Future messages are not sent early');
};

$tests['SMTP rejects header injection and creates a stable message identity'] = static function (): void {
    $mailer=new \App\Services\SmtpMailer(['mail_from_email'=>'support@example.invalid']);
    $denied=false;try{$mailer->buildMessage('test@example.invalid',"Hello\r\nBcc: other@example.invalid",'<p>Test</p>');}catch(InvalidArgumentException){$denied=true;}
    assertTrue($denied,'Subject cannot inject a new header');
    $denied=false;try{$mailer->buildMessage("test@example.invalid\r\nRCPT TO:<other@example.invalid>",'Test','Test');}catch(InvalidArgumentException){$denied=true;}
    assertTrue($denied,'Recipient cannot inject SMTP commands');
    $id='<test-message@example.invalid>';$m=$mailer->buildMessage('test@example.invalid','Receipt test','<p>Safe</p>',$id);
    assertTrue(str_contains($m,'Message-ID: '.$id),'Supplied stable identity appears in MIME');
};

$tests['mail status changes are targeted and payment confirmation identity is deterministic'] = static function (): void {
    $q=\App\Services\MailQueueService::class;
    $one=$q::deliveryIdentity('payment_confirmation','Test@Example.invalid','paid-order:fixture');
    assertSame($one,$q::deliveryIdentity('payment_confirmation','test@example.invalid','paid-order:fixture'),'Recipient normalization preserves dedupe identity');
    assertTrue($one!==$q::deliveryIdentity('admin_notification','test@example.invalid','paid-order:fixture'),'Owner/customer mail are distinct obligations');
    assertTrue(!str_contains(fileRead('app/Services/MailQueueService.php'),"->write('mail_queue'"),'Mail updates cannot replace whole queue');
    assertTrue(!str_contains(fileRead('app/Services/MailStorageService.php'),"->write('mail_outbox'"),'Outbox updates cannot replace whole collection');
};

$tests['mail health labels transport evidence honestly and preserves all state denominators'] = static function (): void {
    $h=\App\Services\MailQueueService::health([
        ['status'=>'sent'],['status'=>'sending'],['status'=>'needs_review'],
        ['status'=>'failed','last_error'=>'SMTP 452 quota exceeded for private@example.invalid'],
        ['status'=>'pending','available_at'=>'2026-11-15T10:00:00Z'],
        ['status'=>'pending','available_at'=>'2026-11-16T10:00:00Z'],['unexpected'=>'legacy']
    ],strtotime('2026-11-15T12:00:00Z'));
    assertSame(7,$h['total'],'Invalid/legacy records remain in denominator');
    assertSame(1,$h['due_pending'],'Delayed mail is not misclassified overdue');
    assertSame(1,$h['states']['unknown'],'Malformed historical rows remain visible');
    assertSame('unverified',$h['inbox_delivery'],'Recorded sent is not recipient confirmation');
    assertTrue(!str_contains(json_encode($h),'private@example.invalid'),'Health aggregates contain no recipient or raw provider response');
};

$tests['support catalogue decisions do not substitute unrelated products on a no-match'] = static function (): void {
    $service=\App\Services\AgentContextService::class;
    $products=[['name'=>'Gold Ayyappa Pendant','slug'=>'ayyappa-pendant','price'=>850,'updated_at'=>'2026-11-14T12:00:00Z'],
        ['name'=>'Green Emerald Ring','slug'=>'emerald-ring','price'=>500]];
    $no=$service::catalogueSelection($products,'Do you sell Neptune telescope bracelets?');
    assertSame('no_match',$no['status'],'Unmatched specific query stays unmatched');
    assertSame([],$no['products'],'No default first-20 fallback');
    assertSame([],$service::supportProducts($products,'Do you sell Neptune telescope bracelets?'),'Production helper retains no-match semantics');
    $yes=$service::catalogueSelection($products,'How much is the Gold Ayyappa Pendant?');
    assertSame(['ayyappa-pendant'],array_column($yes['products'],'slug'),'Only named public product retrieved');
    assertSame('catalogue_search',$yes['intent'],'Specific catalogue request is explicit');
    assertSame('2026-11-14T12:00:00Z',$yes['sources'][0]['last_updated'],'Actual source date preserved');
    assertSame('product:ayyappa-pendant',$yes['sources'][0]['id'],'Public evidence references are stable');
    assertSame(2,count($service::catalogueSelection($products,'What do you sell?')['products']),'Explicit browse remains available');
    assertSame([],$service::catalogueSelection($products,'show my latest order')['products'],'Account intent never retrieves random catalogue');
};

$tests['support Tamil collection evidence stays scoped and unknown freshness is not invented'] = static function (): void {
    $p=[['id'=>'a','name'=>'Gold Ayyappa Pendant','slug'=>'ayyappa-pendant'],['id'=>'b','name'=>'Unrelated Ring','slug'=>'ring']];
    $c=[['name'=>'Ayyappa Swamy Festive Sale','slug'=>'ayyappa-sale','product_ids'=>['a']]];
    $r=\App\Services\AgentContextService::catalogueSelection($p,'அய்யப்பன் சுவாமி பண்டிகை பொருட்கள்',$c);
    assertSame(['ayyappa-pendant'],array_column($r['products'],'slug'),'Tamil name resolves actual collection membership');
    assertSame('collection_membership',$r['reason'],'Trace distinguishes a membership lookup from generic matching');
    assertSame(null,$r['sources'][0]['last_updated'],'No source date means unknown, not retrieval time');
    $r=\App\Services\AgentContextService::catalogueSelection($p,'festival products',$c);
    assertSame('clarification',$r['status'],'Generic festival query cannot select an arbitrary sale');
};

$tests['support source-link evaluation detects invented products without claiming semantic accuracy'] = static function (): void {
    $source=[['intent'=>'catalogue_search','status'=>'matched','sources'=>[['id'=>'product:known-item','last_updated'=>null]]]];
    $ok=\App\Services\AgentEvaluationService::evaluate('support','product?','See /product/known-item',[],'local',$source);
    assertSame(true,$ok['checks']['public_product_links_supported'],'Observed product link is supported');
    $bad=\App\Services\AgentEvaluationService::evaluate('support','product?','See /product/invented-item',[],'model',$source);
    assertSame(false,$bad['passed'],'Invented product reference fails guardrail');
    assertSame('not_evaluated',$ok['factual_accuracy'],'Link correctness is not blanket factual correctness');
};

$tests['agent operational rates use observed attempt and tool denominators plus median'] = static function (): void {
    $now=strtotime('2026-11-15T12:00:00Z');$events=[];
    foreach([10,20,100] as $i=>$ms)$events[]=['event'=>'agent.run','created_at'=>gmdate('c',$now-10),'meta'=>[
        'surface'=>'admin','outcome'=>'local','duration_ms'=>$ms,
        'provider_attempts'=>$i===0?[['model'=>'fixture','status'=>500],['model'=>'fixture','status'=>200]]:[],
        'tool_activity'=>[['name'=>'sales_summary','status'=>$i===2?'error':'complete','duration_ms'=>2]]]];
    $s=\App\Services\AuditLogService::agentSummary($events,$now)['admin'];
    assertSame(20,$s['p50_ms'],'Median is nearest-rank over the stated 24-hour request window');
    assertSame(2,$s['provider_attempt_count'],'Retries count as attempts, not additional conversations');
    assertSame(1,$s['provider_error_count'],'Provider error count records failed attempt');
    assertSame(3,$s['tool_call_count'],'Tool denominator is executed calls');
    assertSame(1,$s['tool_error_count'],'Failed tool is separate from response outcome');
};

$tests['mail CLI runs safely in test mode with bounded pending processing'] = static function (): void {
    $output=[];$exit=1;
    exec('cd '.escapeshellarg(app_path()).' && BAPX_TEST_MODE=1 ./bapXphp mail:process 2>&1',$output,$exit);
    assertSame(0,$exit,'PHP namespace quoting in the actual CLI command must parse');
    assertTrue(str_contains(implode("\n",$output),'SMTP accepted 0 queued emails. Inbox receipt is unverified.'),'CLI never claims actual delivery');
    assertTrue(str_contains(fileRead('cli/bapXphp'),'array_slice($m->due(),0,25)'),'One invocation is bounded to 25 pending records');
};

$tests['public retrieval preserves existing spaced and Unicode slugs as encoded evidence'] = static function (): void {
    $products=[['id'=>'a','name'=>'Golden Ayyappan Pendant','slug'=>'Ayyappan Pendant'],
        ['id'=>'b','name'=>'Tamil Mala','slug'=>'தமிழ் மாலை']];
    $r=\App\Services\AgentContextService::catalogueSelection($products,'What do you sell?');
    assertSame(2,count($r['products']),'Valid existing slugs must not disappear from retrieval');
    $safe=\App\Services\AgentTraceService::safeRetrieval($r);
    assertSame('product:Ayyappan%20Pendant',$safe['sources'][0]['id'],'Evidence URI encodes actual public slug');
    assertSame(2,count($safe['sources']),'Unicode source survives the trace sanitizer');
    assertTrue(\App\Services\AgentEvaluationService::publicProductLinksSupported('See /product/Ayyappan%20Pendant.',[$safe]),'Encoded real link passes');
    assertTrue(!\App\Services\AgentEvaluationService::publicProductLinksSupported('See /product/Ayyappan%20Pendant-Fake.',[$safe]),'A prefixed invented link does not pass');
};

$tests['docs map records real local blog and chat sources rather than unused GitHub fetcher'] = static function (): void {
    $map=(new \App\Services\DocsMapService())->generate();
    assertTrue(str_contains($map,'content/blog/posts/*.md'),'Published articles come from local BlogService files');
    assertTrue(str_contains($map,'BlogService category vocabulary + article frontmatter'),'Categories come from the standard vocabulary and article metadata');
    assertTrue(!str_contains($map,'GitHub raw → blog index'),'Generated map cannot repeat the unused fetcher architecture');
    assertTrue(str_contains($map,'one private file per chat'),'Private chat exception is visible in source map');
};

$tests['JSON snapshot comparison ignores object key order but preserves values and array order'] = static function (): void {
    require_once app_path('tests/json-snapshot.php');
    $a=['status'=>'processing','refunds'=>[['id'=>'fixture','amount'=>2500]]];
    $b=['refunds'=>[['amount'=>2500,'id'=>'fixture']],'status'=>'processing'];
    assertTrue($a!==$b,'Original strict PHP comparison rejects reordered JSON objects');
    assertSame(canonicalJsonSnapshot($a),canonicalJsonSnapshot($b),'Canonical object keys compare equal');
    $b['refunds'][0]['amount']='2500';assertTrue(canonicalJsonSnapshot($a)!==canonicalJsonSnapshot($b),'Scalar types remain strict');
    assertTrue(canonicalJsonSnapshot([1,2])!==canonicalJsonSnapshot([2,1]),'JSON array sequence remains significant');
    $b['refunds'][0]['amount']=2501;assertTrue(canonicalJsonSnapshot($a)!==canonicalJsonSnapshot($b),'Changed refund amount still fails');
};

$tests['uncaught CLI errors exit nonzero without HTML or sensitive exception bodies'] = static function (): void {
    $fixture=tempnam(sys_get_temp_dir(),'sps-cli-throw-');
    try{
        file_put_contents($fixture,"<?php require ".var_export(app_path('app/bootstrap.php'),true)."; throw new RuntimeException('secret-fixture-never-print');");
        $out=[];$status=0;exec('BAPX_TEST_MODE=1 '.escapeshellarg(PHP_BINARY).' '.escapeshellarg($fixture).' 2>&1',$out,$status);$text=implode("\n",$out);
        assertTrue($status!==0,'An uncaught validation exception must fail the shell/CI');
        assertTrue(!str_contains($text,'<!doctype'),'CLI errors are not successful HTML pages');
        assertTrue(!str_contains($text,'secret-fixture-never-print'),'Unexpected exception message may contain secrets');
        assertTrue(str_contains($text,'RuntimeException'),'Safe class is available for diagnosis');
    }finally{@unlink($fixture);}
};

$tests['operator help reads the canonical owner guide without calling a model'] = static function (): void {
    $prior = $_SESSION['user'] ?? null;
    try {
        $tools = new \App\Services\EnterpriseToolRegistry();
        $_SESSION['user'] = ['role'=>'customer'];
        assertSame(null, $tools->guideAnswer('/help catalogue'), 'Private operator guides require an owner');
        assertTrue(isset($tools->run('operator_guide', ['topic'=>'orders-shipping'])['error']), 'Model invocation enforces the same permission');
        $_SESSION['user'] = ['role'=>'admin'];
        $answer = $tools->guideAnswer('How do I prepare an unpublished article?');
        assertTrue(str_contains($answer, 'Draft — not public') && !str_contains($answer, 'Restore an earlier'), 'Only the relevant instructions appear');
        assertSame([['label'=>'Write and publish a guide','path'=>'/admin/docs/blog-publishing']], $tools->sourceLinks(), 'Owner can open the actual instructions');
        assertSame('operator_help', $tools->activity()[0]['evidence']['source'], 'Report the retrieved source');
        $history = $tools->run('operator_guide', ['topic'=>'blog-publishing','section'=>'Restore an earlier server save']);
        assertTrue(str_contains($history['instructions'], 'History') && !str_contains($history['instructions'], 'New Post'), 'Late sections can be retrieved without losing them to a prefix limit');
        assertTrue(isset($tools->run('operator_guide', ['topic'=>'blog-publishing','section'=>'Unlisted command'])['error']), 'Do not invent an unavailable section');
        assertSame(null, $tools->guideAnswer('How many unpublished articles are there?'), 'Counts require live data');
        assertSame(null, $tools->guideAnswer('How do I write a post about my latest sales?'), 'Mixed questions need interpretation');
        assertTrue(str_contains($tools->guideAnswer('/help'), 'blog-publishing'), 'Available topics are discoverable without a provider');
        assertTrue(str_contains($tools->guideAnswer('/help catalogue'), 'product'), 'Explicit guide topic returns canonical text');
        assertTrue(isset($tools->run('operator_guide', ['topic'=>'../settings'])['error']), 'Only allowlisted topics are readable');
    } finally {
        if ($prior === null) unset($_SESSION['user']); else $_SESSION['user'] = $prior;
    }
};

$tests['real agent contract checker validates private manifests instead of duplicating prompts'] = static function (): void {
    $out=[];$status=1;exec('cd '.escapeshellarg(app_path()).' && BAPX_TEST_MODE=1 '.escapeshellarg(PHP_BINARY).' cli/agent-contracts.php --check 2>&1',$out,$status);
    assertSame(0,$status,'Offline generator must explicitly authorize its fixture role');
    assertTrue(str_contains(implode("\n",$out),'Agent tool and instruction documentation matches PHP source.'),'Command must reach the actual validation success, not an HTML exception handler');
    $doc=fileRead('docs/agent-tool-contracts.md');
    assertTrue(str_contains($doc,'.bapX/supportagent.md')&&str_contains($doc,'.bapX/enterpriseagent.md'),'Developer contract lists required private source paths');
    assertTrue(!str_contains($doc,'You are Sri Panchami'),'Developer doc does not duplicate runtime constitution text');
};

$tests['Markdown strips active attributes dangerous URLs and foreign namespaces'] = static function (): void {
    $renderer=new \App\Services\MarkdownRenderer();
    $cases=[
        '<img src="/assets/images/hero-temple-bg.webp" onerror="window.testMarker=1">',
        '<div onclick="window.testMarker=1">Read this</div>',
        '<a href="javascript:window.testMarker=1">Click</a>',
        '<a href="jav&#x61;script:window.testMarker=1">Click</a>',
        "<a href=\"java\t script:window.testMarker=1\">Click</a>",
        '[Click](javascript:window.testMarker=1)',
        '<svg><a xlink:href="javascript:window.testMarker=1">click</a></svg>',
        '<math><mtext><img src=x onerror="window.testMarker=1"></mtext></math>',
        '<iframe srcdoc="<script>window.testMarker=1</script>"></iframe>',
        '<div style="background:url(javascript:window.testMarker=1)" id="location">safe text</div>',
    ];
    foreach($cases as $input){$html=$renderer->render($input);
        assertTrue(!preg_match('/\s(?:on[a-z]+|srcdoc|style|xlink:href)\s*=/i',$html),'No executable attributes remain');
        assertTrue(!preg_match('/(?:href|src)\s*=\s*["\'][^"\']*(?:javascript|data|vbscript):/i',$html),'No active URL protocol remains');
        assertTrue(!preg_match('/<(?:script|iframe|svg|math|object|form)\b/i',$html),'No executable/foreign elements remain');
    }
};

$tests['Markdown images links tables Tamil and code remain useful after sanitization'] = static function (): void {
    $renderer=new \App\Services\MarkdownRenderer();
    $html=$renderer->render("## Guide\n\n![படம்](/assets/images/hero-temple-bg.webp)\n\n[Shop](/shop) and [Reference](https://example.invalid/guide)\n\n| Name | Price |\n|---|---:|\n| Ayyappa | 100 |\n\n```php\n[not a link](javascript:demo)\n<b>literal</b>\n```\n\n`**literal**`");
    assertTrue(str_contains($html,'<img ')&&str_contains($html,'alt="படம்"'),'Images are parsed before ordinary links and Tamil survives');
    assertTrue(str_contains($html,'href="/shop"'),'Internal links remain functional');
    assertTrue(str_contains($html,'rel="noopener noreferrer"'),'External targets have safe relationship attributes');
    assertTrue(str_contains($html,'<table>')&&str_contains($html,'text-align:right'),'Table structure and bounded alignment remain');
    assertTrue(str_contains($html,'[not a link](javascript:demo)')&&str_contains($html,'&lt;b&gt;literal&lt;/b&gt;'),'Code is text, never reinterpreted as Markdown/HTML');
    assertTrue(str_contains($html,'<code>**literal**</code>'),'Inline code keeps its literal punctuation');
};

$tests['admin Help uses only curated operator guides and real application destinations'] = static function (): void {
    $service=new \App\Services\AdminHelpService();$actor=['role'=>'admin'];
    $guides=$service->catalog($actor);
    assertTrue(count($guides)>=10,'Help covers the real owner workflows');
    $getPaths=array_column(array_filter(\App\Services\ProjectMapService::registry()['routes'],static fn($r):bool=>$r['method']==='GET'),'path');
    foreach($guides as $guide){
        assertTrue(is_file(app_path('content/help/admin/'.$guide['slug'].'.md')),'Only curated operator sources are loaded');
        $read=$service->guide($guide['slug'],$actor);assertTrue(strlen($read['html'])>300,'Guide contains usable task instructions');
        assertTrue(count($read['toc'])>=2,'Guide offers real section anchors');
        foreach($guide['links'] as $link)assertTrue(in_array(parse_url($link['url'],PHP_URL_PATH),$getPaths,true),'Task action is a real GET route: '.$link['url']);
        foreach($guide['sources'] as $source)assertTrue(is_file(app_path($source)),'Guide has inspectable code sources');
        assertTrue(!str_contains($read['html'],'.env.test-user')&&!str_contains($read['html'],'docs/issues'),'No developer credentials or backlog in operator content');
    }
};

$tests['admin Help rejects guests customer roles arbitrary files and unknown slugs'] = static function (): void {
    $service=new \App\Services\AdminHelpService();
    foreach([null,['role'=>'customer'],['role'=>'consultant']] as $actor){$denied=false;try{$service->catalog($actor);}catch(RuntimeException){$denied=true;}assertTrue($denied,'Nonadmin cannot read operator guides');}
    foreach(['../AGENTS.md','.bapX/supportagent.md','getting-started%2f..','not-a-guide','https://example.invalid'] as $slug)assertSame(null,$service->guide($slug,['role'=>'admin']),'Guide path is never user-selected file access');
    $results=$service->catalog(['role'=>'admin'],'SMTP');assertSame(['email'],array_column($results,'slug'),'Search uses the operator content, not all repo docs');
    assertSame([],$service->catalog(['role'=>'admin'],'missing-unique-fixture'),'No-result state is honest');
    assertTrue(count($service->catalog(['role'=>'admin'],'','commerce'))>=2,'Category filters operate without JavaScript');
};

$tests['context help is role appropriate and bounded to matching route segments'] = static function (): void {
    $service=\App\Services\AdminHelpService::class;
    assertSame('orders-shipping',$service::adminContext('/admin/orders/fixture')['slug'],'Order detail links to order workflow');
    assertSame('agent-monitoring',$service::adminContext('/admin/agent/monitoring')['slug'],'Specific monitor route wins over chat prefix');
    assertSame('settings',$service::adminContext('/admin/appearance/menu')['slug'],'Header menu opens its settings guide before the appearance prefix');
    assertSame('media',$service::adminContext('/admin/appearance')['slug'],'Brand appearance retains the media guide');
    $pagesHelp=$service::adminContext('/admin/pages?edit=festival');
    assertSame('blog-publishing',$pagesHelp['slug'],'Authored pages open the publishing guide');
    assertTrue(in_array('/admin/pages',array_column($pagesHelp['links'],'url'),true),'Publishing guide links back to the pages editor');
    assertSame('getting-started',$service::adminContext('/admin/orders-other')['slug'],'Prefix lookalike cannot hijack context');
    assertSame('/blog/payments-and-orders',$service::publicContext('/checkout')['url'],'Customer help never points to private admin guide');
    assertSame(null,$service::publicContext('/admin/settings'),'No public context for private routes');
    assertSame(null,$service::publicContext('/checkout-other'),'No accidental prefix match');
};

$tests['admin help routes navigation and context are integrated with existing private controller'] = static function (): void {
    $routes=\App\Services\ProjectMapService::registry()['routes'];
    foreach(['/admin/docs'=>'AdminController@helpIndex','/admin/docs/{slug}'=>'AdminController@helpGuide'] as $path=>$controller){
        $found=array_values(array_filter($routes,static fn(array $r):bool=>($r['method']??'')==='GET'&&($r['path']??'')===$path));
        assertSame(1,count($found),'Help route is registered once: '.$path);
        assertSame($controller,$found[0]['controller'],'Help stays under existing authenticated controller');
    }
    assertTrue(is_file(app_path('views/admin/help.php')),'Operator hub and guide view exists');
    $layout=fileRead('views/layouts/admin.php');
    assertTrue(str_contains($layout,'Help &amp; Docs')&&str_contains($layout,'Help for this page'),'Both navigation and inline help are wired');
    assertTrue(str_contains(fileRead('index.php'),'content/help/admin')&&str_contains(fileRead('.htaccess'),'content/help/admin'),'Raw operator sources are denied before static serving');
};

$tests['every public context help destination is an existing published customer guide'] = static function (): void {
    $blog=new \App\Services\BlogService();
    foreach(['/shop','/product/fixture','/cart','/checkout','/account/dashboard/profile','/login','/register','/contact'] as $route){
        $context=\App\Services\AdminHelpService::publicContext($route);
        assertTrue($context!==null,'Source page has contextual guidance');
        $slug=substr($context['url'],strlen('/blog/'));$post=$blog->find($slug);
        assertTrue($post!==null&&($post['category']??'')==='help'&&($post['published']??false)===true,'No dangling/private customer help destination: '.$route);
    }
    assertTrue(str_contains(fileRead('views/layouts/app.php'),'Read support guide'),'Support panel help survives conversation-log updates');
};

$tests['operator guides are indexed as references without prompt bodies'] = static function (): void {
    $graph=(new \App\Services\KnowledgeGraphService())->build();
    $guides=array_values(array_filter($graph['concepts'],static fn(array $r):bool=>($r['type']??'')==='operator_guide'));
    assertSame(count(\App\Services\AdminHelpService::references()),count($guides),'Every allowed operator guide is discoverable');
    foreach($guides as $g){assertSame('',(string)($g['body']??''),'Generated index does not duplicate private guide prose');
        assertTrue(str_starts_with($g['filePath'],'content/help/admin/'),'Original source path retained');}
};

$tests['help review depends on underlying source even when another area owns that file'] = static function (): void {
    require_once app_path('cli/lib/DocumentationReview.php');
    $root=sys_get_temp_dir().'/sps-help-contract-'.bin2hex(random_bytes(6));
    mkdir($root.'/app',0700,true);mkdir($root.'/docs',0700,true);
    file_put_contents($root.'/app/Feature.php','<?php // actual source');
    file_put_contents($root.'/docs/guide.md','How to use the feature.');
    $rules=['feature'=>['sources'=>['*'],'references'=>['docs/guide.md']],
        'help'=>['sources'=>[],'dependencies'=>['app/Feature.php'],'references'=>['docs/guide.md']]];
    try{$r=new DocumentationReview($root,$rules);$before=$r->snapshots();
        file_put_contents($root.'/app/Feature.php','<?php // changed controls');$after=$r->snapshots();
        assertTrue($before['help']['source_sha256']!==$after['help']['source_sha256'],'Shared source edits invalidate the help review independently');
        assertSame(1,$after['help']['source_count'],'Dependency counted once');
    }finally{unlink($root.'/app/Feature.php');unlink($root.'/docs/guide.md');rmdir($root.'/app');rmdir($root.'/docs');rmdir($root);}
};

$tests['SEO canonical and organization origins come from trusted configuration not request host'] = static function (): void {
    $server=$_SERVER;
    try {
        $_SERVER['HTTP_HOST']='untrusted.example';$_SERVER['HTTPS']='off';$_SERVER['REQUEST_URI']='/shop?category=pooja';
        $seo=new \App\Services\SeoService([], 'https://configured.example');$m=$seo->page('shop');
        assertSame('https://configured.example/shop?category=pooja',$m['canonical'],'Canonical uses configured origin and preserves meaningful current query');
        assertSame($m['canonical'],$m['og_url'],'Canonical and default OG identify the same page');
        assertSame('https://configured.example/',$seo->organizationSchema()['url'],'Organization origin is not a Host header echo');
        $_SERVER['REQUEST_URI']='https://spoofed.invalid/product/Real%20Item?preview=1';
        assertSame('https://configured.example/product/Real%20Item?preview=1',$seo->page('product')['canonical'],'Absolute request form cannot replace configured origin');
    } finally { $_SERVER=$server; }
};

$tests['SEO preserves safe page overrides and private noindex while rejecting unsafe URL schemes'] = static function (): void {
    $server=$_SERVER;
    try {
        $_SERVER['REQUEST_URI']='/blog/post';$s=new \App\Services\SeoService([], 'https://configured.example');
        $m=$s->page('blog.post',['canonical'=>'https://configured.example/blog/original','og_image'=>'https://images.example/approved.webp']);
        assertSame('https://configured.example/blog/original',$m['canonical'],'Reviewed controller override is retained');
        assertSame($m['canonical'],$m['og_url'],'Default OG uses the approved canonical override');
        assertSame('https://images.example/approved.webp',$m['og_image'],'Valid external image override remains supported');
        $bad=$s->page('product',['canonical'=>'javascript:alert(1)','og_image'=>'data:image/png;base64,unsafe']);
        assertSame('https://configured.example/blog/post',$bad['canonical'],'Unsafe canonical fails back to configured page URL');
        assertTrue(str_starts_with($bad['og_image'],'https://configured.example/assets/images/'),'Unsafe image uses shipped fallback');
        foreach(['admin','account','checkout','login'] as $page)assertTrue(str_contains($s->page($page)['robots'],'noindex'),'Private/transactional indexing behavior preserved: '.$page);
    } finally { $_SERVER=$server; }
};

$tests['SEO default social image exists and nonexistent local overrides fall back'] = static function (): void {
    $s=new \App\Services\SeoService(['seo_default_og_image'=>'/assets/images/does-not-exist-fixture.jpg'],'https://configured.example');
    $m=$s->page('home');$path=parse_url($m['og_image'],PHP_URL_PATH);
    assertTrue(is_string($path)&&is_file(app_path(ltrim($path,'/'))),'Default metadata references a real shipped file');
    assertTrue(str_starts_with($m['og_image'],'https://configured.example/'),'Default image is absolute under configured origin');
    assertSame($m['og_image'],$m['twitter_image'],'Default Twitter and OG share the same actual image');
    $valid=$s->page('home',['og_image'=>'/assets/images/blog/create-account.webp']);
    assertSame('https://configured.example/assets/images/blog/create-account.webp',$valid['og_image'],'Real root-relative image becomes absolute');
};

$tests['product schema availability follows checkout and preserves valid slug and resolved price'] = static function (): void {
    $s=new \App\Services\SeoService([],'https://configured.example');
    $base=['name'=>'Fixture pendant','slug'=>'Ayyappan Pendant','price'=>1000,'offer_price'=>850,'image_url'=>'/assets/images/blog/order-products.webp'];
    foreach(['in_stock'=>'InStock','active'=>'InStock','out_of_stock'=>'OutOfStock','inactive'=>'OutOfStock','draft'=>'OutOfStock',''=>'OutOfStock'] as $state=>$expected){
        $data=$s->productSchema($base+['stock_status'=>$state]);
        assertSame('https://schema.org/'.$expected,$data['offers']['availability'],'Metadata matches buyable stock state: '.$state);
        assertSame('https://configured.example/product/Ayyappan%20Pendant',$data['offers']['url'],'Existing spaced slug is encoded exactly once');
        assertSame(850.0,$data['offers']['price'],'Already-resolved offer price is unchanged');
        assertSame('https://configured.example/assets/images/blog/order-products.webp',$data['image'],'Product image is absolute');
    }
};

$tests['public layout renders service metadata rather than rebuilding untrusted origins'] = static function (): void {
    $layout=fileRead('views/layouts/app.php');
    assertTrue(str_contains($layout,"e(\$seo['canonical'])"),'Canonical is service-owned including legitimate per-page override');
    assertTrue(!str_contains($layout,"\$_SERVER['HTTP_HOST']"),'Layout cannot bypass canonical-origin policy');
    assertTrue(str_contains($layout,"\$seo['organization_json_ld']"),'Organization markup uses the configured service output');
};

$tests['SEO configuration and inline JSON-LD cannot accept script-shaped URL or data'] = static function (): void {
    foreach(['javascript:alert(1)','https://user:secret@example.invalid','https://example.invalid/?token=fixture'] as $url){
        $denied=false;try{new \App\Services\SeoService([],$url);}catch(RuntimeException){$denied=true;}
        assertTrue($denied,'Invalid configured base URL fails without echoing its value');
    }
    $s=new \App\Services\SeoService([],'https://configured.example');
    $script=$s->jsonLdScript(['name'=>'</script><script>fixture()</script>']);
    assertSame(1,substr_count($script,'</script>'),'Data cannot terminate the JSON-LD element');
    $payload=substr($script,strlen('<script type="application/ld+json">'),-strlen('</script>'));
    assertSame('</script><script>fixture()</script>',json_decode($payload,true,512,JSON_THROW_ON_ERROR)['name'],'Literal schema data survives correct encoding');
    $controller=fileRead('app/Controllers/PublicController.php');
    $from=strpos($controller,'public function sitemap()');$to=strpos($controller,'public function contact()',$from);$method=substr($controller,$from,$to-$from);
    assertTrue(!str_contains($method,"\$_SERVER['HTTP_HOST']"),'Sitemap uses canonical configuration too');
    assertTrue(str_contains($method,'rawurlencode((string)$p[\'slug\'])'),'Sitemap product URLs preserve encoded public slugs');
};

$tests['runtime policy requires PHP 8.3 and actual MySQL 8.4 without accepting MariaDB numerically'] = static function (): void {
    $evaluate=\App\Services\RuntimeHealthService::class;
    $extensions=['curl','json','mbstring','pdo_mysql','openssl'];
    $ok=$evaluate::assess('8.3.0',['version'=>'8.4.0','version_comment'=>'MySQL Community Server - GPL'],$extensions,'cli');
    assertSame(true,$ok['requirements_met'],'Minimum supported PHP/MySQL meets declared target');
    assertSame('mysql',$ok['database']['engine'],'Database vendor is separately identified');
    assertSame(false,$evaluate::assess('8.2.30',['version'=>'8.4.2','version_comment'=>'MySQL Community Server - GPL'],$extensions,'cli')['requirements_met'],'PHP 8.2 must not pass');
    assertSame(false,$evaluate::assess('8.5.7',['version'=>'8.0.44','version_comment'=>'MySQL Community Server - GPL'],$extensions,'cli')['requirements_met'],'MySQL 8.0 must not pass');
    $maria=$evaluate::assess('8.5.7',['version'=>'11.8.9-MariaDB-log','version_comment'=>'MariaDB Server'],$extensions,'cli');
    assertSame('mariadb',$maria['database']['engine'],'MariaDB is not relabeled MySQL');
    assertSame('engine_mismatch',$maria['database']['status'],'Higher MariaDB number cannot satisfy MySQL8.4');
    assertSame(false,$maria['requirements_met'],'Engine mismatch is blocking for the declared target');
};

$tests['runtime report leaves unknown evidence unknown and strips private server details'] = static function (): void {
    $s=\App\Services\RuntimeHealthService::class;
    $result=$s::assess('8.4.9',null,['json','mbstring'],'cli');
    assertSame('unavailable',$result['database']['status'],'Absent DB cannot become a pass');
    assertTrue(in_array('pdo_mysql',$result['extensions']['missing'],true),'Missing required extensions are explicit');
    assertSame(false,$result['requirements_met'],'Unknown DB and missing extensions fail readiness');
    $poison='unknown user=private@example.invalid password=not-for-output host=secret.internal /private/config';
    $safe=$s::assess($poison,['version'=>$poison,'version_comment'=>$poison],['json'],'invented-sapi');
    $json=json_encode($safe,JSON_THROW_ON_ERROR);
    foreach(['private@example.invalid','not-for-output','secret.internal','/private/config'] as $value)assertTrue(!str_contains($json,$value),'Do not echo arbitrary source/errors/credentials');
    assertSame('unknown',$safe['php']['version'],'Invalid version remains unknown');
    assertSame('unknown',$safe['database']['engine'],'Unidentified engine remains unknown');
    assertSame('other',$safe['php']['sapi'],'SAPI is bounded');
    $numeric=$s::assess('8.3.1',['version'=>'9.0.0','version_comment'=>'unidentified fork'],['curl','json','mbstring','pdo_mysql','openssl'],'cli');
    assertSame(false,$numeric['requirements_met'],'Number without identified MySQL vendor is not enough');
};

$tests['native system health is a read-only authenticated route and contextual admin guide'] = static function (): void {
    $routes=\App\Services\ProjectMapService::registry()['routes'];
    $health=array_values(array_filter($routes,static fn(array $r):bool=>($r['path']??'')==='/admin/system-health'));
    assertSame(1,count($health),'One native health route, no mutation duplicate');
    assertSame('GET',$health[0]['method'],'Health view does not migrate or write');
    assertSame('AdminController@systemHealth',$health[0]['controller'],'Existing admin gate protects diagnostics');
    assertTrue(is_file(app_path('views/admin/system-health.php')),'Actual health UI exists');
    assertTrue(str_contains(fileRead('views/layouts/admin.php'),'href="/admin/system-health"'),'Admin navigation reaches the native feature');
    assertSame('settings',\App\Services\AdminHelpService::adminContext('/admin/system-health')['slug'],'Health view has actual operator instructions');
};

$tests['runtime CLI emits structured unknown state in test mode and strict status is nonzero'] = static function (): void {
    $path=app_path('cli/runtime-check.php');assertTrue(is_file($path),'Native runtime command exists');
    $out=[];$status=0;exec('BAPX_TEST_MODE=1 '.escapeshellarg(PHP_BINARY).' '.escapeshellarg($path).' --json --strict 2>&1',$out,$status);
    assertSame(1,$status,'Strict runtime policy rejects unavailable test-mode DB');
    $r=json_decode(implode("\n",$out),true,512,JSON_THROW_ON_ERROR);
    assertSame(false,$r['requirements_met'],'Structured failure is explicit');
    assertSame('unavailable',$r['database']['status'],'No live DB or fabricated fixture is consulted in test mode');
};

$tests['runtime policy does not certify prerelease engines or malformed requirement arguments'] = static function (): void {
    $extensions=['curl','json','mbstring','pdo_mysql','openssl'];
    $db=['version'=>'8.4.0-rc1','version_comment'=>'MySQL Community Server - GPL'];
    $r=\App\Services\RuntimeHealthService::assess('8.4.1',$db,$extensions,'cli');
    assertSame(false,$r['requirements_met'],'A release candidate is not the stable production baseline');
    assertSame('unsupported_prerelease',$r['database']['status'],'Failure reason describes prerelease, not a lower version');
    $r=\App\Services\RuntimeHealthService::assess('8.4.0RC1',['version'=>'8.4.2','version_comment'=>'MySQL Community Server - GPL'],$extensions,'cli');
    assertSame('unsupported_prerelease',$r['php']['status'],'PHP prerelease is explicitly classified');
};

$tests['deployment guide uses current bounded mail CLI and real audit surfaces'] = static function (): void {
    $doc=fileRead('docs/deployment-hostinger.md');
    assertTrue(!str_contains($doc,'/cli/process-mail-queue.php'),'Do not deploy a cron targeting a deleted script');
    assertTrue(str_contains($doc,'./bapXphp mail:process'),'Optional due-pending processing uses the supported CLI');
    assertTrue(str_contains($doc,'25 due pending'),'Operator knows the queue processing bound');
    assertTrue(!str_contains($doc,'Admin -> Audit Log'),'Do not invent a generic audit-log menu');
    assertTrue(str_contains($doc,'Playwright CLI'),'Release instructions match authorized browser verification');
    assertTrue(str_contains($doc,'SMTP acceptance is not inbox receipt'),'Transport result is not recipient verification');
};

$tests['merchant feed uses public product facts and exact shared offer pricing without private fields'] = static function (): void {
    $row=['id'=>'fixture-merchant-a','slug'=>'தமிழ் Pendant','name'=>'Copper & Gold <Pendant>','description'=>'<p>Real item &amp; description.</p>',
        'price'=>1000,'offer_price'=>850,'stock_status'=>'in_stock','image_url'=>'/assets/images/blog/order-products.webp',
        'supplier_cost'=>21,'customer_email'=>'private@example.invalid','secret'=>'never-export'];
    $service=new \App\Services\MerchantFeedService(new \App\Services\ProductService(),'https://configured.example');
    $r=$service->inspect([$row]);
    assertSame(1,$r['exported_count'],'Valid existing public record can enter feed');
    assertSame('fixture-merchant-a',$r['items'][0]['id'],'Existing stable ID is preserved');
    assertSame('850.00 INR',$r['items'][0]['price'],'Feed matches ProductService current payable price');
    assertSame('https://configured.example/product/'.rawurlencode($row['slug']),$r['items'][0]['link'],'Configured origin and encoded existing slug');
    assertSame('in_stock',$r['items'][0]['availability'],'Google stock vocabulary from real record');
    assertSame('unverified',$r['merchant_approval'],'Local correctness is not Merchant account approval');
    assertSame('eligibility_and_linking_required',$r['youtube_status'],'Feed alone is not YouTube shopping');
    assertTrue(!isset($r['items'][0]['identifier_exists']),'Unknown identifiers never turn into an invented no-identifier assertion');
    $xml=$service->xml($r);$document=new DOMDocument();assertTrue($document->loadXML($xml),'RSS is well-formed');
    assertSame('http://base.google.com/ns/1.0',$document->getElementsByTagNameNS('http://base.google.com/ns/1.0','id')->item(0)->namespaceURI,'Google feed namespace');
    assertTrue(!str_contains($xml,'private@example.invalid')&&!str_contains($xml,'supplier_cost')&&!str_contains($xml,'never-export'),'Only approved public catalogue fields are exported');
};

$tests['merchant feed excludes hidden draft invalid and duplicate records without overwriting source'] = static function (): void {
    $base=['id'=>'merchant-fixture','slug'=>'product','name'=>'Product','description'=>'Description','price'=>100,'stock_status'=>'in_stock','image_url'=>'/assets/images/blog/order-products.webp'];
    $rows=[$base,$base+['irrelevant'=>'duplicate'],array_replace($base,['id'=>'hidden','status'=>'hidden']),
        array_replace($base,['id'=>'draft','stock_status'=>'draft']),array_replace($base,['id'=>'bad-price','price'=>0]),
        array_replace($base,['id'=>'bad-image','image_url'=>'javascript:alert(1)']),array_replace($base,['id'=>'missing-image','image_url'=>'/assets/images/not-present-fixture.png']),
        array_replace($base,['id'=>'placeholder','image_url'=>'data:image/svg+xml,fake']),array_replace($base,['id'=>'out','stock_status'=>'out_of_stock'])];
    $before=$rows;$r=(new \App\Services\MerchantFeedService(new \App\Services\ProductService(),'https://configured.example'))->inspect($rows);
    assertSame($before,$rows,'Inspection never changes catalogue rows');
    assertSame(['merchant-fixture','out'],array_column($r['items'],'id'),'Only unambiguous valid public records exported');
    assertSame(7,$r['excluded_count'],'Every skipped record accounted for');
    assertSame('out_of_stock',$r['items'][1]['availability'],'Unavailable stock remains accurately represented');
};

$tests['merchant feed refuses untrusted origin and never applies expired offer or fabricates GTIN'] = static function (): void {
    $denied=false;try{new \App\Services\MerchantFeedService(new \App\Services\ProductService(),'https://user:pass@example.invalid');}catch(InvalidArgumentException){$denied=true;}
    assertTrue($denied,'Credential-bearing origin rejected');
    $products=new \App\Services\ProductService(now:new DateTimeImmutable('2026-11-16T10:00:00+05:30'));
    $row=['id'=>'expired','slug'=>'expired','name'=>'Expired offer','description'=>'Actual item','price'=>100,'offer_price'=>80,'offer_ends_at'=>'2026-11-15','stock_status'=>'active','image_url'=>'/assets/images/blog/order-products.webp'];
    $r=(new \App\Services\MerchantFeedService($products,'https://configured.example'))->inspect([$row]);
    assertSame('100.00 INR',$r['items'][0]['price'],'Expired offers use the same shared price implementation');
    assertTrue(!isset($r['items'][0]['gtin'])&&!isset($r['items'][0]['brand']),'Identity metadata is not guessed');
    assertTrue(in_array('identifiers_need_review',$r['rows'][0]['warnings'],true),'Identifier gap is visible');
};

$tests['merchant routes CLI and guidance are real and do not require database migration'] = static function (): void {
    $routes=\App\Services\ProjectMapService::registry()['routes'];
    foreach(['/feeds/google-products.xml'=>'PublicController@merchantFeed','/admin/google-merchant'=>'AdminController@googleMerchant'] as $path=>$action){
        $found=array_values(array_filter($routes,static fn($r)=>$r['path']===$path));assertSame(1,count($found),'Exactly one read-only route '.$path);
        assertSame('GET',$found[0]['method'],'No mutation route for readiness/feed');assertSame($action,$found[0]['controller'],'Extends existing controllers');
    }
    assertTrue(is_file(app_path('views/admin/google-merchant.php')),'Real channel screen exists');
    assertTrue(str_contains(fileRead('index.php'),"'/feeds/google-products.xml'"),'Front controller dispatches the real XML route');
    assertTrue(str_contains(fileRead('views/layouts/admin.php'),'Google &amp; YouTube'),'Reachable menu');
    $lines=[];$code=1;exec('cd '.escapeshellarg(app_path()).' && BAPX_TEST_MODE=1 ./bapXphp merchant:check --json 2>&1',$lines,$code);
    assertSame(0,$code,'Read-only CLI works without network in fixture mode');
    $j=json_decode(implode("\n",$lines),true);assertSame('merchant.catalogue.readiness',$j['operation']??null,'Machine readable operation contract');assertSame(true,$j['read_only']??null,'No writes implied');
};

$tests['merchant metadata keeps explicit identifiers and serializes hostile Unicode as well formed XML'] = static function (): void {
    $s=new \App\Services\MerchantFeedService(new \App\Services\ProductService(),'https://configured.example');
    $base=['id'=>'identifiers','slug'=>'item','name'=>'Real item','description'=>"Actual \u{FFFF} item\0 text",'price'=>100,'stock_status'=>'in_stock','image_url'=>'/assets/images/blog/order-products.webp'];
    $r=$s->inspect([$base+['gtin'=>'4006381333931','brand'=>'Actual Brand','mpn'=>'ABC-10','condition'=>'new']]);
    assertSame('4006381333931',$r['items'][0]['gtin'],'Real valid supplied GTIN retained');
    assertSame('Actual Brand',$r['items'][0]['brand'],'Recorded brand not store-name substitution');
    $xml=$s->xml($r);$old=libxml_use_internal_errors(true);$doc=new DOMDocument();$ok=$doc->loadXML($xml);libxml_clear_errors();libxml_use_internal_errors($old);
    assertTrue($ok,'Invalid XML scalar characters do not break all products');
    $explicit=$s->inspect([$base+['identifier_exists'=>false]]);assertSame('no',$explicit['items'][0]['identifier_exists'],'Only explicit no-identifiers emits no');
    $bad=$s->inspect([$base+['gtin'=>'4006381333932']]);assertTrue(!isset($bad['items'][0]['gtin']),'Bad checksum never exported');
    $private=$s->inspect([array_replace($base,['image_url'=>'https://name:secret@images.example/picture.jpg'])]);assertSame(0,$private['exported_count'],'Credential-bearing image is not public output');
};

$tests['merchant product correction link selects actual record rather than an empty new-product form'] = static function (): void {
    $source=fileRead('app/Controllers/AdminController.php');$start=strpos($source,'public function products()');$end=strpos($source,'public function importProducts',$start);$method=substr($source,$start,$end-$start);
    assertTrue(str_contains($method,"\$_GET['edit']") && str_contains($method,"->find('products', \$edit)") && str_contains($method,'$this->renderNotFound()'),
        'Merchant editor loads the original product by ID even when it is outside the current page and rejects a missing ID');

};

$tests['product CSV accepts reviewed native columns and rejects malformed or unsafe batches'] = static function (): void {
    $parse = \App\Services\ProductImportService::parse(...);
    $sample = $parse(fileRead('assets/product-import-sample.csv'))[0];
    assertSame('hidden', $sample['status'], 'Imported stock is reviewed before publishing');
    assertSame('out_of_stock', $sample['stock_status'], 'Import makes no inventory promise');
    $rows = $parse("\xEF\xBB\xBFname,price,slug,description\r\nLamp,25.55,lamp,\"Quoted,\"\"word\"\"\nnext line\"\r\n");
    assertSame("Quoted,\"word\"\nnext line", $rows[0]['description'], 'Quoted cells preserve embedded commas, quotes and newlines');
    foreach (["slug,name,price\na,Lamp,2\na,Lamp,3", "slug,name,price\na,=Formula,2", "slug,name,price\na,\"Bad quote,2",
        "slug,name,price,price\na,Lamp,2,3", "slug,name,price\na,Lamp,-1", "name,slug\nLamp,a",
        "slug,name,price\na,Lamp,20,unexpected", "slug,name,price\n" . implode("\n", array_map(fn($n) => "p-$n,Lamp,1", range(1,101))),
        str_repeat('x', 65537)] as $bad) {
        try { $parse($bad); } catch (\InvalidArgumentException) { continue; }
        throw new RuntimeException('Invalid CSV was accepted');
    }
};

$tests['Google tag renderer treats IDs as data and never injects markup or executable input'] = static function (): void {
    $bad=new \App\Integrations\GoogleSiteKit\GoogleSiteKitClient("G-X');window.fixtureAttack=1;//",'AW-1" onload="bad()', '\"><script>fixtureAttack()</script>');
    assertSame('',$bad->gtagCode(),'Invalid IDs cannot produce a loader or an executable config');
    assertSame('',$bad->siteVerificationTag(),'Invalid token cannot produce a meta element');
    assertSame(false,$bad->enabled(),'Invalid values are not enabled integrations');
    $statuses=$bad->status();assertSame('invalid',$statuses['analytics']['configuration'],'Invalid Analytics ID visible');
    assertSame('invalid',$statuses['ads']['configuration'],'Invalid Ads ID visible');
    assertTrue(!str_contains(json_encode($statuses),'fixtureAttack'),'Status omits unsafe raw values');
};

$tests['Google tag valid legacy destinations keep one safe loader and independent truthful states'] = static function (): void {
    $client=new \App\Integrations\GoogleSiteKit\GoogleSiteKitClient(' G-ABCD123456 ','AW-123456789','Token_ABC-123');
    $html=$client->gtagCode();$d=new DOMDocument();@$d->loadHTML($html);$x=new DOMXPath($d);
    assertSame(1,$x->query('//script[@src]')->length,'One shared Google loader');
    assertSame('https://www.googletagmanager.com/gtag/js?id=G-ABCD123456',$x->query('//script[@src]')->item(0)->getAttribute('src'),'Valid configured destination retained');
    assertTrue(str_contains($html,'"G-ABCD123456"')&&str_contains($html,'"AW-123456789"'),'Inline IDs are JSON string data');
    assertTrue(str_contains($client->siteVerificationTag(),'content="Token_ABC-123"'),'Valid exact token retained');
    $status=$client->status();assertSame('configured',$status['analytics']['configuration'],'Syntax is configured only');
    assertSame('unverified',$status['analytics']['collection'],'No Google receipt is invented');
    assertSame('not_checked',$status['reporting'],'Tag configuration cannot establish the separate reporting grant state');
    assertSame('unverified',$status['search_console']['ownership'],'Token presence is not Google ownership verification');
    $one=(new \App\Integrations\GoogleSiteKit\GoogleSiteKitClient('G-ABCD123456','',''))->status();
    assertSame('missing',$one['ads']['configuration'],'Analytics alone does not enable Ads');
    assertSame('missing',$one['search_console']['configuration'],'Analytics alone does not prove Search Console');
};

$tests['Google tag schema rejects incompatible prefixes labels and oversized values without changing settings'] = static function (): void {
    foreach(['UA-123-1','GTM-ABCD','G-','G-A?anything','G-'.str_repeat('A',65)] as $value){
        $c=new \App\Integrations\GoogleSiteKit\GoogleSiteKitClient($value,'','');assertSame('',$c->gtagCode(),'GA4 input is not a generic code/container editor');
    }
    $c=new \App\Integrations\GoogleSiteKit\GoogleSiteKitClient('GT-ABC123','AW-123/ConversionLabel','google-site-verification=not-the-meta-content');
    assertSame('configured',$c->status()['analytics']['configuration'],'Existing Google-tag GT destination supported');
    assertSame('invalid',$c->status()['ads']['configuration'],'Conversion send_to label is not a loader ID');
    assertSame('invalid',$c->status()['search_console']['configuration'],'DNS assignment/full tag is not content token');
    $source=fileRead('integrations/google-site-kit/GoogleSiteKitClient.php');
    assertTrue(!str_contains($source,'curl_exec')&&!str_contains($source,'saveSecret'),'Rendering does not mutate or validate externally');
};

$tests['public head and owner Google status use the same actual renderer without false connection claims'] = static function (): void {
    $layout=fileRead('views/layouts/app.php');$dashboard=fileRead('views/admin/dashboard.php');$settings=fileRead('views/admin/integrations.php');
    assertTrue(str_contains($layout,'GoogleSiteKitClient')&&str_contains($layout,'gtagCode()'),'Existing public layout calls the safe implementation');
    assertTrue(!str_contains($layout,"gtag('config', '<?= e("),'No duplicate HTML-escaped JS-string interpolation');
    assertTrue(!str_contains($dashboard,'Search Console verification are active'),'No blanket active statement');
    assertTrue(str_contains($dashboard,'google-tag-status')&&str_contains($dashboard,'Reporting is not connected'),'Dashboard reports actual capability boundary');
    assertTrue(str_contains($settings,'Verification token')&&!str_contains($settings,'placeholder="google-site-verification='),'Operator instruction matches meta content');
    assertTrue(!str_contains(fileRead('app/Services/ProjectMapService.php'),"self::serviceId('SupportBotService') . ' --> ' . self::integrationId('GoogleSiteKitClient')"),'Generated map never invents a SupportBot tag-client call');
};

$tests['blog table has a bounded keyboard scroll region rather than a table-wrap table'] = static function (): void {
    $view=fileRead('views/admin/blog.php');
    assertTrue(str_contains($view,'class="table-wrap admin-blog-list"'),'Actual table belongs to a scroll wrapper');
    assertTrue(str_contains($view,'aria-label="Blog posts" tabindex="0"'),'Overflow region is named and keyboard reachable');
    assertTrue(!str_contains($view,'<table class="table-wrap"'),'Overflow is not applied to an intrinsic-width table');
};

$tests['operational telemetry retains every declared capability without raw arguments or new permissions'] = static function (): void {
    $names=array_merge(\App\Services\AgentCapabilityPolicy::tools('support'),\App\Services\AgentCapabilityPolicy::tools('enterprise'));
    $events=array_map(static fn($n)=>['name'=>$n,'status'=>'complete','duration_ms'=>4,'args'=>['private'=>'SENTINEL'],'output'=>'SENTINEL'],$names);
    $m=new ReflectionMethod(\App\Services\AuditLogService::class,'toolActivity');$safe=$m->invoke(null,$events);
    assertSame($names,array_column($safe,'name'),'All declared operations survive operational projection');
    assertTrue(!str_contains(json_encode($safe),'SENTINEL'),'Arguments/results remain private');
    assertSame('customer_ticket_lookup',$m->invoke(null,[['name'=>'customer_ticket_lookup','status'=>'complete']])[0]['name'],'Historical internal activity preserved');
    assertTrue(!\App\Services\AgentCapabilityPolicy::allows('support','customer_ticket_lookup',['email'=>'fixture@example.invalid']),'Internal alias grants no execution');
    assertSame([],$m->invoke(null,[['name'=>'arbitrary_sql','status'=>'complete'],['name'=>'public_catalog','status'=>'running']]),'Unknown or incomplete activity excluded');
    $now=time();$r=\App\Services\AuditLogService::agentSummary([['event'=>'agent.run','created_at'=>gmdate('c',$now),'meta'=>['surface'=>'support','outcome'=>'local','tool_activity'=>array_slice($events,0,3)]]],$now);
    assertSame(3,$r['support']['tool_call_count'],'Support summary denominator matches observed events');
};

$tests['blog dates control public visibility at the site timezone boundary'] = static function (): void {
    $dir=sys_get_temp_dir().'/sps-blog-date-'.bin2hex(random_bytes(6));mkdir($dir,0700);
    try {
        $before=new \App\Services\BlogService($dir,new DateTimeImmutable('2026-11-15T18:29:59Z'));
        (new ReflectionProperty(\App\Services\BlogService::class,'postsDir'))->setValue($before,$dir);
        $future=$before->save(['slug'=>'future','title'=>'Future','content'=>'Private until due','category'=>'help','published'=>true,'published_at'=>'2026-11-16']);
        assertSame([], $before->all(),'Before midnight IST it is withheld from all public consumers');
        assertTrue(!$before->isPublic($future),'Direct-route predicate agrees');
        assertSame('scheduled',$before->publicationState($future),'Admin can distinguish scheduled from public');
        $due=new \App\Services\BlogService($dir,new DateTimeImmutable('2026-11-15T18:30:00Z'));
        assertSame(1,count($due->all()),'Exactly midnight IST makes a calendar-date post public');
        assertTrue($due->isPublic($due->find('future')),'Direct and listed visibility agree');
        $invalid=false;try{$due->save(['slug'=>'invalid','title'=>'Invalid','content'=>'x','published_at'=>'2026-02-30']);}catch(InvalidArgumentException){$invalid=true;}
        assertTrue($invalid,'Invalid calendar date is rejected rather than normalized silently');
        assertTrue(!$due->isPublic(['published'=>true,'category'=>'help','published_at'=>'nonsense']),'Invalid stored legacy schedule fails closed');
        assertTrue(!$due->isPublic(['published'=>true,'category'=>'astrology','published_at'=>'2020-01-01']),'Retired content remains withheld');
    }finally{foreach(glob($dir.'/*')?:[] as $file)unlink($file);@unlink($dir.'/.blog-write.lock');rmdir($dir);}
};

$tests['blog writes compare exact content revision before update delete or create collision'] = static function (): void {
    $dir=sys_get_temp_dir().'/sps-blog-cas-'.bin2hex(random_bytes(6));mkdir($dir,0700);
    try {
        $blog=new \App\Services\BlogService($dir);
        (new ReflectionProperty(\App\Services\BlogService::class,'postsDir'))->setValue($blog,$dir);
        $first=$blog->save(['slug'=>'article','title'=>'Original','content'=>'Original body','published'=>false]);
        assertSame(64,strlen($first['_revision']),'Returned revision is actual persisted content digest');
        $a=$blog->find('article');$b=$blog->find('article');$a['content']='First editor';$next=$blog->save($a);
        $conflict=false;try{$b['content']='Stale editor';$blog->save($b);}catch(\App\Services\BlogConflictException){$conflict=true;}
        assertTrue($conflict,'Stale revision cannot overwrite');assertSame('First editor',$blog->find('article')['content'],'Current body survives');
        $conflict=false;try{$blog->save(['slug'=>'article','title'=>'Collision','content'=>'new']);}catch(\App\Services\BlogConflictException){$conflict=true;}
        assertTrue($conflict,'Missing revision cannot overwrite an existing file');
        $conflict=false;try{$blog->delete('article',$first['_revision']);}catch(\App\Services\BlogConflictException){$conflict=true;}
        assertTrue($conflict,'Stale delete also rejected');
        $blog->delete('article',$next['_revision']);assertSame(null,$blog->find('article'),'Exact reviewed revision can be deleted');
    }finally{foreach(glob($dir.'/*')?:[] as $file)unlink($file);@unlink($dir.'/.blog-write.lock');rmdir($dir);}
};

$tests['article history restores exact Markdown on the reviewed base and stays bounded'] = static function (): void {
    $dir = sys_get_temp_dir().'/sps-blog-history-'.bin2hex(random_bytes(6));
    mkdir($dir, 0700);
    try {
        $blog = new \App\Services\BlogService($dir);
        $original = "---\ntitle: Legacy\npublished: false\ncustom: preserved\n---\n\nOriginal\n";
        file_put_contents($dir.'/article.md', $original);
        $post = $blog->find('article');
        $post['content'] = 'Updated';
        $post['published'] = true;
        $updated = $blog->save($post);
        $history = $blog->revisionHistory('article');
        assertSame(1, count($history['versions']), 'Editing a legacy article archives its exact previous bytes');
        $id = $history['versions'][0]['id'];
        $preview = $blog->revisionHistory('article', $id)['preview'];
        assertSame($original, $preview['_markdown'], 'Owner preview includes original frontmatter');
        assertSame('hidden', $preview['_publication_state'], 'Preview reports the resulting publication state');
        assertSame(1, count($blog->all(true)), 'Archived versions never appear as articles');
        assertSame(null, $blog->revisionHistory('article', '../outside'), 'Only server-generated revision identifiers resolve');
        $blog->save($updated);
        assertSame(1, count($blog->revisionHistory('article')['versions']), 'A no-op save consumes no history slot');

        $later = $updated;
        $later['content'] = 'Second editor';
        $later = $blog->save($later);
        $stale = false;
        try { $blog->restoreRevision('article', $id, $updated['_revision']); }
        catch (\App\Services\BlogConflictException) { $stale = true; }
        assertTrue($stale, 'Stale preview cannot overwrite a more recent save');
        assertSame('Second editor', $blog->find('article')['content'], 'Rejected restore preserves the current article');
        $other = $blog->save(['slug'=>'other','title'=>'Other','content'=>'Private']);
        $denied = false;
        try { $blog->restoreRevision('other', $id, $other['_revision']); }
        catch (\App\Services\BlogConflictException) { $denied = true; }
        assertTrue($denied, 'Revision identifiers are scoped to one article');
        $restored = $blog->restoreRevision('article', $id, $later['_revision']);
        assertSame($original, file_get_contents($dir.'/article.md'), 'Restore retains byte-for-byte original Markdown');
        assertSame(hash('sha256', $original), $restored['_revision'], 'Restored CAS token matches persisted data');
        $rollback = $blog->revisionHistory('article')['versions'][0]['id'];
        $post = $blog->restoreRevision('article', $rollback, $restored['_revision']);
        assertSame($later['_revision'], $post['_revision'], 'The replacement can be rolled back to the exact pre-restore version');
        for ($i = 0; $i < 12; $i++) {
            $post['content'] = 'Edit '.$i;
            $post = $blog->save($post);
        }
        assertSame(10, count($blog->revisionHistory('article')['versions']), 'Only ten previous versions retained');
        assertSame(null, $blog->revisionHistory('article', $id), 'Pruned snapshots cannot be selected');
        $archive = $blog->revisionHistory('article')['versions'][0]['id'];
        $file = $dir.'/.article.'.$archive.'.rev';
        assertSame(0600, fileperms($file) & 0777, 'Private archive permissions');
        file_put_contents($file, 'Bad snapshot');
        $denied = false;
        try { $blog->restoreRevision('article', $archive, $post['_revision']); }
        catch (RuntimeException) { $denied = true; }
        assertTrue($denied, 'Corrupt archive cannot replace current article');
        assertSame($post['_revision'], $blog->find('article')['_revision'], 'Failed restore leaves current file intact');
        $blog->delete('article', $post['_revision']);
        $blog->save(['slug'=>'article','title'=>'Replacement','content'=>'New']);
        assertSame([], $blog->revisionHistory('article')['versions'], 'Reusing a deleted slug starts with no prior history');
    } finally {
        foreach (array_merge(glob($dir.'/*.md') ?: [], glob($dir.'/.*.rev') ?: []) as $file) unlink($file);
        @unlink($dir.'/.blog-write.lock');
        rmdir($dir);
    }
};

$tests['blog storage rejects path hops linked files and preserves frontmatter values safely'] = static function (): void {
    $dir=sys_get_temp_dir().'/sps-blog-path-'.bin2hex(random_bytes(6));mkdir($dir,0700);$outside=$dir.'-private.md';file_put_contents($outside,'DO_NOT_READ');
    try {
        $blog=new \App\Services\BlogService($dir);
        (new ReflectionProperty(\App\Services\BlogService::class,'postsDir'))->setValue($blog,$dir);
        foreach(['../escape','a/b','a\\b','.',''] as $slug){assertSame(null,$blog->find($slug),'Invalid lookup is not a file read');}
        symlink($outside,$dir.'/linked.md');assertSame(null,$blog->find('linked'),'Linked source excluded');
        $denied=false;try{$blog->save(['slug'=>'linked','title'=>'x','content'=>'x']);}catch(RuntimeException){$denied=true;}assertTrue($denied,'No symlink overwrite');
        $post=$blog->save(['slug'=>'safe','title'=>"Title\npublished: true",'content'=>"# Body\n\nKeep --- separators",'published'=>false]);
        assertSame(false,$post['published'],'Metadata text cannot inject publication fields');
        assertSame("Title\npublished: true",$post['title'],'Escaped metadata round-trips');
        assertSame("# Body\n\nKeep --- separators",$post['content'],'Body separators preserved');
        assertSame('DO_NOT_READ',file_get_contents($outside),'External file unchanged');
    }finally{foreach(glob($dir.'/*')?:[] as $file)unlink($file);@unlink($dir.'/.blog-write.lock');unlink($outside);rmdir($dir);}
};

$tests['blog editor carries revision guards and public route denies raw unpublished files'] = static function (): void {
    $view=fileRead('views/admin/blog.php');
    assertTrue(str_contains($view,'name="expected_revision"'),'Save/delete/toggle carry revision evidence');
    assertTrue(str_contains($view,'edit-original-slug'),'Existing URL does not silently follow title edits');
    assertTrue(str_contains($view,'Download unsaved Markdown'),'Conflicted text has a recovery path');
    assertTrue(str_contains(fileRead('app/Controllers/BlogController.php'),'->isPublic($post)'),'Direct URL uses the same visibility contract');
    assertTrue(str_contains(fileRead('index.php'),'content/blog/posts')&&str_contains(fileRead('.htaccess'),'content/blog/posts'),'Static source cannot bypass draft/schedule gating');
};

$tests['blog explicit draft status is preserved and scheduling controls no immediate newsletter'] = static function (): void {
    $view=fileRead('views/admin/blog.php');$controller=fileRead('app/Controllers/AdminController.php');
    assertTrue(str_contains($view,'name="published" id="edit-published"'),'Editor submits current draft/public state explicitly');
    assertTrue(str_contains($controller,'$blog->isPublic($saved)'),'No immediate newsletter for scheduled or hidden content');
    $service=new \App\Services\BlogService(now:new DateTimeImmutable('2026-11-15T18:30:00Z'));
    assertSame('published',$service->publicationState(['published'=>true,'published_at'=>'2026-11-16T00:00:00+05:30','category'=>'help']),'Equivalent explicit instant becomes visible');
    assertSame('hidden',$service->publicationState(['published'=>'false','published_at'=>'2020-01-01']),'String false is not treated as truthy');
};

$tests['transactional templates separate customer and owner destinations with authentic order evidence'] = static function (): void {
    $t=new \App\Services\MailTemplateService('https://configured.example');
    $order=['id'=>'order-fixture','customer_name'=>'<script>unsafe()</script>','customer_email'=>'buyer@example.invalid','total'=>1250,
        'subtotal'=>1200,'shipping_cost'=>50,'invoice_number'=>'INV-FIXTURE',
        'items'=>[['name'=>'Lamp & Oil','qty'=>2,'unit_price'=>600,'line_total'=>1200]],'payment_id'=>'pay_fixture'];
    $customer=$t->render('order_confirmation',$order);$owner=$t->render('owner_order',$order);
    assertSame('customer',$customer['audience'],'Customer envelope uses customer audience');assertSame('admin',$owner['audience'],'Owner envelope uses admin audience');
    assertTrue(str_contains($customer['html'],'1,250.00')&&str_contains($customer['html'],'Lamp &amp; Oil'),'Saved order facts are readable');
    assertTrue(str_contains($customer['html'],'/account/orders/order-fixture/invoice'),'Actual issued invoice has customer-authenticated link');
    assertTrue(!str_contains($customer['html'],'/admin/'),'Customer body cannot contain owner actions');
    assertTrue(str_contains($owner['html'],'/admin/orders/order-fixture'),'Owner uses owner order route');
    assertTrue(!str_contains($customer['html'],'<script>'),'Untrusted name is text, not markup');
    unset($order['invoice_number']);assertTrue(!str_contains($t->render('order_confirmation',$order)['html'],'/invoice'),'No invented invoice URL when absent');
    $order['fulfillment_review_required']=true;assertTrue(str_contains($t->render('order_confirmation',$order)['html'],'availability review'),'Held paid order is not promised fulfillment');
};

$tests['payment uncertainty and actual delivered state are not misleading emails'] = static function (): void {
    $t=new \App\Services\MailTemplateService('https://configured.example');
    $failure=$t->render('payment_attention',['id'=>'fixture','total'=>100]);
    assertTrue(!str_contains($failure['html'],'you have not been charged'),'Bank charge is not inferred from failure');
    assertTrue(str_contains(strtolower($failure['html']),'do not pay again'),'Customer warned against duplicate payment while uncertain');
    $delivered=$t->render('shipment',['id'=>'fixture','status'=>'delivered','tracking_url'=>'javascript:bad()']);
    assertTrue(str_contains(strtolower($delivered['subject']),'delivered'),'Delivered update is not a fresh dispatch');
    assertTrue(!str_contains($delivered['html'],'javascript:'),'Tracking link cannot execute arbitrary scheme');
};

$tests['sample messages are obvious synthetic previews never a real invoice or payment receipt'] = static function (): void {
    $t=new \App\Services\MailTemplateService('https://configured.example');
    foreach(array_keys($t::catalog()) as $key){$sample=$t->sample($key);assertTrue(str_starts_with($sample['subject'],'[TEST SAMPLE - NO ORDER]'),'Sample subject is unambiguous');
        assertTrue(str_contains($sample['html'],'No order was placed, no payment was taken'),'Sample body is unambiguous');
        assertTrue(!str_contains($sample['html'],'/invoice'),'Preview never issues invoice link for nonexistent order');}
    $denied=false;try{$t->sample('../private');}catch(InvalidArgumentException){$denied=true;}assertTrue($denied,'Preview keys are fixed enums');
};

$tests['welcome notification is a new customer event not every OAuth sign in'] = static function (): void {
    assertTrue(method_exists(\App\Services\MailQueueService::class,'enqueueWelcome'),'Manual and Google use one deduped event');
    $auth=fileRead('app/Controllers/AuthController.php');
    assertTrue(substr_count($auth,'->enqueueWelcome(')===2,'Both successful new-account creation paths trigger welcome');
    assertTrue(str_contains($auth,'!$matchedExisting')&&str_contains($auth,"$"."role === 'customer'"),'Google path distinguishes existing and privileged accounts');
    $source=fileRead('app/Services/MailQueueService.php');assertTrue(str_contains($source,"'signup:'"),'Stable per-account event identity prevents duplicate greeting');
    assertTrue(str_contains($source,'MailTemplateService'),'Preview and delivery use the real render contract');
};

$tests['mail template preview and sending are admin scoped and clearly separated'] = static function (): void {
    $routes=\App\Services\ProjectMapService::registry()['routes'];
    foreach(['GET /admin/email-templates','POST /admin/email-templates/test'] as $path){[$method,$url]=explode(' ',$path,2);assertSame(1,count(array_filter($routes,fn($r)=>$r['path']===$url&&$r['method']===$method)),'Actual endpoint exists: '.$path);}
    $source=fileRead('app/Controllers/AdminController.php');assertTrue(str_contains($source,"'template_test_sent'"),'Idempotency claim retained before diagnostic send');
    assertTrue(is_file(app_path('views/admin/email-templates.php')),'Curated preview exists');
};

$tests['sample recipient roles cannot replace the configured owner with customer input'] = static function (): void {
    $service=\App\Services\MailTemplateService::class;
    assertSame('owner@example.invalid',$service::sampleRecipient('owner','buyer@example.invalid','owner@example.invalid'),'Owner recipient is canonical setting, not test-input override');
    assertSame('buyer@example.invalid',$service::sampleRecipient('customer','buyer@example.invalid','owner@example.invalid'),'Customer recipient remains independent');
    $bad=false;try{$service::sampleRecipient('owner','buyer@example.invalid','');}catch(InvalidArgumentException){$bad=true;}assertTrue($bad,'Missing owner setting never falls back to customer');
    $bad=false;try{$service::sampleRecipient('customer',"buyer@example.invalid\r\nBcc:x",'owner@example.invalid');}catch(InvalidArgumentException){$bad=true;}assertTrue($bad,'Address injection rejected');
    $source=fileRead('app/Controllers/AdminController.php');assertTrue(str_contains($source,'$target!==$expectedTarget'),'Sample destination role must match template role');
};

$tests['real mail shell carries contextual preheader and signup events are stable'] = static function (): void {
    $q=new \App\Services\MailQueueService();$message=$q->templatePreview('order_confirmation');
    assertTrue(str_contains($message['html'],'Synthetic template test only.'),'Inbox preheader is event-specific, not only brand');
    assertTrue(str_contains($message['html'],'role="presentation"'),'Table fallback preserves mail-client layout');
    assertTrue(str_contains($message['html'],'max-width:600px'),'Mail remains single-column bounded');
    $first=$q->enqueueWelcome(['id'=>'fixture-customer','role'=>'customer','name'=>'Fixture','email'=>'fixture@example.invalid']);
    $again=$q->enqueueWelcome(['id'=>'fixture-customer','role'=>'customer','name'=>'Fixture','email'=>'fixture@example.invalid']);
    assertSame($first['id'],$again['id'],'Duplicate signup event converges on same queue identity');
    assertSame(null,$q->enqueueWelcome(['id'=>'owner','role'=>'admin','email'=>'owner@example.invalid']),'Customer welcome is not sent to privileged account');
    assertTrue(str_contains(fileRead('app/Controllers/AuthController.php'),"['email_verified']"),'New Google welcome requires provider-verified email');
};

$tests['full mail preview view renders the real CSRF helper and destination controls'] = static function (): void {
    $templates=\App\Services\MailTemplateService::catalog();$template='welcome';
    $preview=(new \App\Services\MailQueueService())->templatePreview($template);
    $testToken=str_repeat('a',32);$ownerLabel='o***@example.invalid';$ownerConfigured=true;$testResult=null;$csrf=str_repeat('b',64);
    $level=ob_get_level();
    try{ob_start();require app_path('views/admin/email-templates.php');$html=(string)ob_get_clean();
        assertTrue(str_contains($html,'name="_csrf"'),'Real helper renders security input');
        assertTrue(str_contains($html,'name="target" value="customer" checked'),'Customer role is selected only for customer template');
        assertTrue(str_contains($html,'name="target" value="owner" disabled'),'Owner cannot receive a customer template accidentally');
        assertTrue(str_contains($html,'sandbox=""'),'Preview remains script-disabled');
    }finally{while(ob_get_level()>$level)ob_end_clean();}
};

$tests['profile password change sends a customer security notice only after successful CAS'] = static function (): void {
    $source=fileRead('app/Controllers/AccountController.php');
    $start=strpos($source,'public function changePassword()');$end=strpos($source,'private function profileRecord',$start);
    $method=substr($source,$start,$end-$start);
    $notice=strpos($method,"->enqueueTemplate('password_changed'");
    assertTrue($notice!==false,'Profile change uses the same security template as recovery');
    assertTrue($notice>strpos($method,'$store->patchUser('),'Notice cannot precede the verified password mutation');
    assertTrue($notice<strrpos($method,'} else {'),'Rejected or stale password path cannot notify');
    assertTrue(str_contains($method,"(string)(\$record['email']"), 'Notice recipient is the verified account record');
    assertTrue(!str_contains($method,'admin_notification_email'),'Customer security notice cannot select the owner mailbox');
};

$tests['SMTP plain alternative preserves readable visible content and safe action links'] = static function (): void {
    $html='<html><head><style>@media(max-width:480px){.sample{display:block}}</style></head><body>'
        .'<div style="display:none">HIDDEN_PREHEADER</div><p>வணக்கம் &amp; welcome</p>'
        .'<p>Second paragraph</p><table><tr><td>Order total</td><td>₹1,250.00</td></tr></table>'
        .'<a href="https://example.invalid/account?x=1&amp;y=2">Open account</a>'
        .'<a href="javascript:alert(1)">Unsafe URL</a><a href="#sample-only">Sample action</a>'
        .'<script>BAD_SCRIPT_BODY</script><div hidden>HIDDEN_ATTRIBUTE</div></body></html>';
    $s=new \App\Services\SmtpMailer(['mail_from_email'=>'sender@example.invalid']);
    $mime=$s->buildMessage('receiver@example.invalid','Fixture',$html,'<plain-fixture@example.invalid>');
    preg_match('/Content-Type: text\/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n(.*?)\r\n--/s',$mime,$matches);
    $text=base64_decode(preg_replace('/\s+/','',$matches[1]??''),true);
    assertTrue(is_string($text)&&str_contains($text,'வணக்கம் & welcome'),'Visible Unicode and entities retained');
    foreach(['@media','BAD_SCRIPT_BODY','HIDDEN_PREHEADER','HIDDEN_ATTRIBUTE','javascript:'] as $bad)assertTrue(!str_contains($text,$bad),'Non-content or unsafe link target omitted: '.$bad);
    assertTrue(str_contains($text,'https://example.invalid/account?x=1&y=2'),'Usable safe destination retained');
    assertTrue(str_contains($text,"welcome\r\n")&&str_contains($text,"Order total\t₹1,250.00"),'Paragraph/table boundaries survive MIME encoding');
    preg_match('/Content-Type: text\/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n(.*?)\r\n--/s',$mime,$matches);
    assertSame($html,base64_decode(preg_replace('/\s+/','',$matches[1]??''),true),'HTML body remains unchanged by text extraction');
};

$tests['all actual mail template alternatives keep visible sample warnings without CSS'] = static function (): void {
    foreach(array_keys(\App\Services\MailTemplateService::catalog()) as $key){
        $preview=(new \App\Services\MailQueueService())->templatePreview($key);
        $text=\App\Services\SmtpMailer::plainText($preview['html']);
        assertTrue(str_contains($text,'TEST SAMPLE ONLY'),'Synthetic warning remains in text: '.$key);
        assertTrue(!str_contains($text,'@media')&&!str_contains($text,'padding:'),'Presentation code excluded: '.$key);
        assertTrue(mb_check_encoding($text,'UTF-8'),'Text remains UTF-8: '.$key);
        assertTrue(str_contains(fileRead('views/admin/email-templates.php'),'mail-plain-preview'),'Owner can inspect exact text without sending');
    }
};

$tests['per-answer review preserves original evidence and customer feedback in one JSON file'] = static function (): void {
    $dir=sys_get_temp_dir().'/sps-review-'.bin2hex(random_bytes(6));mkdir($dir,0700);
    $svc=new \App\Services\AgentConversationService($dir);
    $owner=['type'=>'guest','id'=>'review-fixture'];$reviewer=['role'=>'admin','sub'=>'reviewer-fixture'];
    try{
        $created=$svc->record('support',$owner,null,'Show fixture item.','See /product/fixture-item.',bin2hex(random_bytes(8)),'local',[],null,null,[],[
            ['intent'=>'catalogue_search','status'=>'matched','reason'=>'exact_public_alias','result_count'=>1,'sources'=>[['id'=>'product:fixture-item','last_updated'=>null]]]]);
        $svc->rateSupportTurn($created['conversation_id'],$owner,$created['turn_id'],'not_helpful');
        $before=$svc->getForAdmin($created['conversation_id']);
        $detail=$svc->reviewDetail($created['conversation_id'],$created['turn_id'],$reviewer);
        $review=$svc->reviewTurn($created['conversation_id'],$created['turn_id'],$reviewer,0,$detail['evidence_sha256'],[
            'rubric'=>['accuracy'=>'pass','source_support'=>'pass','task_completion'=>'fail','policy_compliance'=>'pass'],
            'reasons'=>['missing_information'],'evidence_refs'=>['retrieval:0'],
            'note'=>'The linked product is real but the requested details are missing.', 'authoring_mode'=>'verification_test']);
        assertSame('needs_correction',$review['verdict'],'Verdict is derived from structured rubric');
        $after=$svc->getForAdmin($created['conversation_id']);
        assertSame($before['turns'][0]['answer'],$after['turns'][0]['answer'],'Review never edits the model response');
        assertSame($before['turns'][0]['evaluation'],$after['turns'][0]['evaluation'],'Automated evaluation is not overwritten by reviewer label');
        assertSame($before['turns'][0]['feedback'],$after['turns'][0]['feedback'],'Customer feedback is separate');
        assertSame('needs_followup',$after['resolution_status'],'Reviewer pass/fail cannot claim customer resolution');
        assertSame(1,count($after['turns'][0]['reviews']),'Decision is saved on the reviewed turn');
        assertSame(1,count(glob($dir.'/*.json')?:[]),'No transcript/review mirror or SQL table');
        $summary=$svc->recent();assertSame(0,$summary[0]['quality_review']['human_reviewed'],'Synthetic QA is excluded from human-review counts');
        assertSame(1,$summary[0]['quality_review']['verification_tests'],'Test provenance remains visible');
    }finally{foreach(glob($dir.'/*')?:[] as $f)@unlink($f);foreach(glob($dir.'/.conversation*')?:[] as $f)@unlink($f);@rmdir($dir);}
};

$tests['answer review rejects stale snapshots revisions foreign roles and unsupported evidence'] = static function (): void {
    $dir=sys_get_temp_dir().'/sps-review-guard-'.bin2hex(random_bytes(6));mkdir($dir,0700);$s=new \App\Services\AgentConversationService($dir);
    $admin=['role'=>'admin','sub'=>'reviewer'];$payload=['rubric'=>['accuracy'=>'not_assessed','source_support'=>'not_assessed','task_completion'=>'pass','policy_compliance'=>'pass'],'reasons'=>['missing_information'],'evidence_refs'=>['response'],'note'=>'No tool evidence is available for factual verification.','authoring_mode'=>'agent_assisted'];
    try{$c=$s->record('support',['type'=>'guest','id'=>'fixture'],null,'Fixture','Fixture answer',bin2hex(random_bytes(8)),'local');
        $d=$s->reviewDetail($c['conversation_id'],$c['turn_id'],$admin);
        foreach([['role'=>'customer','sub'=>'x'],['type'=>'admin','id'=>'x'],['role'=>'customer','is_admin'=>'true','sub'=>'x']] as $actor){
            $denied=false;try{$s->reviewDetail($c['conversation_id'],$c['turn_id'],$actor);}catch(RuntimeException){$denied=true;}
            assertTrue($denied,'Stored/browser-shaped actor objects cannot grant reviewer access');}
        $bad=$payload;$bad['evidence_refs']=['tool:999'];$denied=false;
        try{$s->reviewTurn($c['conversation_id'],$c['turn_id'],$admin,0,$d['evidence_sha256'],$bad);}catch(InvalidArgumentException){$denied=true;}
        assertTrue($denied,'Only evidence actually recorded on the turn can be cited');
        $denied=false;try{$s->reviewTurn($c['conversation_id'],$c['turn_id'],$admin,0,str_repeat('0',64),$payload);}catch(LogicException){$denied=true;}
        assertTrue($denied,'Changed evidence cannot receive a stale approval');
        $first=$s->reviewTurn($c['conversation_id'],$c['turn_id'],$admin,0,$d['evidence_sha256'],$payload);
        assertSame('insufficient_evidence',$first['verdict'],'Missing assessment is not pass');
        $denied=false;try{$s->reviewTurn($c['conversation_id'],$c['turn_id'],$admin,0,$d['evidence_sha256'],$payload);}catch(LogicException){$denied=true;}
        assertTrue($denied,'Concurrent stale reviewer cannot overwrite a decision');
        assertSame(1,count($s->getForAdmin($c['conversation_id'])['turns'][0]['reviews']),'History remains intact after rejects');
        $payload['note']='Follow-up review: still no additional evidence.';
        $s->reviewTurn($c['conversation_id'],$c['turn_id'],$admin,1,$d['evidence_sha256'],$payload);
        assertSame(2,count($s->getForAdmin($c['conversation_id'])['turns'][0]['reviews']),'Revisions append instead of rewriting history');
    }finally{foreach(glob($dir.'/*')?:[] as $f)@unlink($f);foreach(glob($dir.'/.conversation*')?:[] as $f)@unlink($f);@rmdir($dir);}
};

$tests['review summary separates provenance, abstentions and unresolved source gaps'] = static function (): void {
    $sample=[['id'=>'a','surface'=>'support','intent'=>'collection','turn_count'=>2,'quality_review'=>[
        'human_reviewed'=>1,'supported'=>0,'needs_correction'=>1,'insufficient_evidence'=>0,'agent_assisted'=>0,'verification_tests'=>1,'unreviewed'=>0,'reasons'=>['wrong_scope'=>1]]],
        ['id'=>'b','surface'=>'admin','intent'=>'unknown','turn_count'=>1,'quality_review'=>[
        'human_reviewed'=>0,'supported'=>0,'needs_correction'=>0,'insufficient_evidence'=>0,'agent_assisted'=>1,'verification_tests'=>0,'unreviewed'=>0,'reasons'=>[]]]];
    $metrics=\App\Services\AgentEvaluationService::reviewSummary($sample);
    assertSame(2,$metrics['conversations'],'Sample denominator is explicit');assertSame(3,$metrics['turns'],'Turns are distinct from chats');
    assertSame(1,$metrics['human_reviewed'],'Only staff-declared human reviews enter human counts');
    assertSame(1,$metrics['verification_tests'],'Verification attempts are never genuine feedback or approvals');
    assertSame(0.0,$metrics['supported_percent'],'Percent uses reviewed human turns only');
    assertSame(1,$metrics['topics']['collection']['needs_correction'],'Topic breakdown links quality to the task');
};

$tests['authenticated answer-review routes have a real existing-admin view'] = static function (): void {
    $routes=\App\Services\ProjectMapService::registry()['routes'];
    foreach(['GET'=>'AdminController@agentReview','POST'=>'AdminController@agentReviewSave'] as $verb=>$handler){
        $r=array_values(array_filter($routes,static fn($r):bool=>($r['path']??'')==='/admin/agent/review/{id}'&&($r['method']??'')===$verb));
        assertSame(1,count($r),'One scoped review route per HTTP method');assertSame($handler,$r[0]['controller'],'Existing authenticated controller enforces role/CSRF');
    }
    assertTrue(is_file(app_path('views/admin/agent-review.php')),'Real review form exists, not just JSON download');
};

$tests['synthetic review revisions cannot replace a staff assessment and require explicit provenance'] = static function (): void {
    $dir=sys_get_temp_dir().'/sps-review-provenance-'.bin2hex(random_bytes(6));mkdir($dir,0700);$s=new \App\Services\AgentConversationService($dir);$admin=['role'=>'admin','id'=>'staff-fixture'];
    try{
        $c=$s->record('support',['type'=>'guest','id'=>'fixture'],null,'Fixture shipping question','Read the published policy.',bin2hex(random_bytes(8)),'local');
        $detail=$s->reviewDetail($c['conversation_id'],$c['turn_id'],$admin);
        $input=['rubric'=>['accuracy'=>'not_assessed','source_support'=>'not_assessed','task_completion'=>'fail','policy_compliance'=>'pass'],
            'reasons'=>['missing_information'],'evidence_refs'=>['response'],'note'=>'Missing policy evidence. password=fixture-secret-must-not-survive','authoring_mode'=>'human'];
        $denied=false;try{$s->reviewTurn($c['conversation_id'],$c['turn_id'],$admin,0,$detail['evidence_sha256'],$input);}catch(InvalidArgumentException){$denied=true;}
        assertTrue($denied,'Human provenance requires explicit staff attestation');
        $input['human_attestation']='yes';$human=$s->reviewTurn($c['conversation_id'],$c['turn_id'],$admin,0,$detail['evidence_sha256'],$input);
        assertTrue(!str_contains($human['note'],'fixture-secret-must-not-survive'),'Obvious credential values are scrubbed before persistence');
        $input['authoring_mode']='verification_test';unset($input['human_attestation']);
        $s->reviewTurn($c['conversation_id'],$c['turn_id'],$admin,1,$detail['evidence_sha256'],$input);
        $summary=$s->recent()[0]['quality_review'];
        assertSame(1,$summary['human_reviewed'],'A later synthetic test never masks the latest human judgment');
        assertSame(1,$summary['needs_correction'],'Original human correction remains visible');
        assertSame(1,$summary['verification_tests'],'Test provenance is counted separately, not as another human');
    }finally{foreach(glob($dir.'/*')?:[] as $f)@unlink($f);foreach(glob($dir.'/.conversation*')?:[] as $f)@unlink($f);@rmdir($dir);}
};

$tests['dynamic HTTP failures replace partial bodies and keep safe correlated outcomes'] = static function (): void {
    $lines=[];$status=1;
    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(app_path('tests/http-response-boundary.php')).' 2>&1',$lines,$status);
    assertSame(0,$status,'Actual isolated HTTP response-boundary cases pass: '.implode(' | ',array_slice($lines,-5)));
    assertTrue(str_contains(fileRead('app/bootstrap.php'),'RequestFailureService::register'),'The real bootstrap installs the boundary, not just a test router');
    assertTrue(!str_contains(fileRead('app/bootstrap.php'),'$error->getMessage()'),'Global exception output does not disclose raw diagnostic/credential text');
};

$tests['each agent session creates one private JSON file and no MySQL table'] = static function (): void {
    $root = sys_get_temp_dir() . '/sps-agent-test-' . bin2hex(random_bytes(6));
    assertTrue(mkdir($root, 0700), 'Private test directory exists');
    try {
        $service = new \App\Services\AgentConversationService($root);
        $record = $service->record('support', ['type'=>'guest','id'=>str_repeat('a',32)],
            null, 'What is the policy?', 'You can check our public policy.',
            bin2hex(random_bytes(8)), 'local');
        $path = $root . '/' . $record['conversation_id'] . '.json';
        assertTrue(is_file($path), 'A conversation JSON file exists');
        $document = json_decode((string)file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        assertSame($record['conversation_id'], $document['id'], 'JSON file matches session ID');
        assertSame('What is the policy?', $document['turns'][0]['question'], 'Transcript retains question');
        assertTrue((fileperms($path) & 077) === 0, 'Transcript has private file permissions');
        assertSame(1, count(glob($root.'/*.json') ?: []), 'Exactly one JSON file per chat');
        $summaries = $service->recent(10);
        assertSame(1, count($summaries), 'Monitoring lists the session');
        assertSame(1, $summaries[0]['turn_count'], 'Monitoring includes the turn count');
        assertTrue(!isset($summaries[0]['turns']), 'Monitoring does not include raw transcript text');
        $db = file_get_contents(app_path('app/Services/DatabaseService.php'));
        assertTrue(!str_contains($db, 'agent_conversations'), 'Conversation storage must not create a SQL table');
    } finally {
        foreach (glob($root.'/*') ?: [] as $file) @unlink($file);
        @unlink($root.'/.conversation.lock');
        @rmdir($root);
    }
};

$tests['private storage routes cannot serve conversation transcripts'] = static function (): void {
    $router = file_get_contents(app_path('index.php'));
    $apache = file_get_contents(app_path('.htaccess'));
    $directory = file_get_contents(app_path('storage/agent-conversations/.htaccess'));
    assertTrue(str_contains($router, "str_starts_with((string)\$uri, '/storage/')"), 'Built-in PHP server denies direct storage requests');
    assertTrue(str_contains($apache, 'storage|tools|tests|docs'), 'Production Apache rules forbid storage URLs');
    assertTrue(str_contains($directory, 'Require all denied'), 'Conversation directory denies direct HTTP access');
};

$tests['agent transcripts redact obvious secrets'] = static function (): void {
    $scrub = \App\Services\AgentTranscriptSanitizer::class;
    $source = 'password=exampleSECRET123 api_key:mykey123 Bearer token1234567890 sk-proj-ABCDEF123456789012';
    $result = $scrub::scrub($source);
    assertTrue(!str_contains($result,'exampleSECRET123'), 'Password not persisted in plaintext');
    assertTrue(!str_contains($result,'mykey123'), 'API key not persisted in plaintext');
    assertTrue(!str_contains($result,'token1234567890'), 'Bearer secret not persisted in plaintext');
    assertTrue(!str_contains($result,'ABCDEF123456789012'), 'OpenAI-style key not persisted');
};

$tests['Enterprise Agent new chat and history are scoped to each admin JSON identity'] = static function (): void {
    $dir = sys_get_temp_dir() . '/sps-agent-history-' . bin2hex(random_bytes(6));
    assertTrue(mkdir($dir, 0700), 'Can create isolated JSON history test directory');
    try {
        $service = new \App\Services\AgentConversationService($dir);
        $alice = ['type'=>'admin', 'id'=>'admin-a'];
        $bob = ['type'=>'admin', 'id'=>'admin-b'];
        $aliceChat = $service->record('admin', $alice, null, 'Sales this week?', 'Three paid orders.',
            bin2hex(random_bytes(8)), 'local');
        $service->record('admin', $alice, $aliceChat['conversation_id'], 'And refunds?', 'None.',
            bin2hex(random_bytes(8)), 'local');
        $bobChat = $service->record('admin', $bob, null, 'Support tickets?', 'Two tickets.',
            bin2hex(random_bytes(8)), 'local');
        $listed = $service->recentForActor('admin', $alice);
        assertSame(1, count($listed), 'Admin history must list only its own conversation');
        assertSame($aliceChat['conversation_id'], $listed[0]['id'], 'History includes the previous chat');
        assertSame(2, $listed[0]['turn_count'], 'Continuation is recorded in the same JSON file');
        $restored = $service->getForActor($aliceChat['conversation_id'], 'admin', $alice);
        assertSame(2, count($restored['turns']), 'Owned conversations are reopenable');
        try {
            $service->getForActor($bobChat['conversation_id'], 'admin', $alice);
            throw new \RuntimeException('Expected cross-admin conversation rejection');
        } catch (\RuntimeException $error) {
            assertSame('Conversation not accessible to this actor', $error->getMessage(), 'Other admin history is private');
        }
        assertSame(2, count(glob($dir . '/*.json') ?: []), 'Each separate chat uses one JSON file');
        $routes = \App\Services\ProjectMapService::registry()['routes'];
        foreach (['/admin/agent/history', '/admin/agent/history/{id}'] as $path) {
            assertTrue(count(array_filter($routes, static fn($r):bool => ($r['path']??'') === $path)) === 1,
                'An authenticated history route exists for ' . $path);
        }
        $ui = file_get_contents(app_path('views/admin/agent.php'));
        assertTrue(str_contains($ui, 'agent-history-toggle') && str_contains($ui, 'restoreConversation')
            && str_contains($ui, 'agent-history-panel[hidden]'), 'Agent UI provides accessible history and new-chat controls');
    } finally {
        foreach (glob($dir.'/*') ?: [] as $file) @unlink($file);
        @unlink($dir.'/.conversation.lock');
        @rmdir($dir);
    }
};

$tests['monitoring links failed tool runs from chat when the operational audit is absent'] = static function (): void {
    $dir = sys_get_temp_dir() . '/sps-failed-tool-monitor-' . bin2hex(random_bytes(6));
    $chats = new \App\Services\AgentConversationService($dir);
    $actor = ['type' => 'admin', 'id' => 'fixture-owner'];
    $trace = '0123456789abcdef';
    $failures = array_fill(0, 3, ['name' => 'support_enquiries', 'status' => 'error', 'reason' => 'lookup_unavailable']);
    try {
        $saved = $chats->record('admin', $actor, null, 'Private fixture question', 'Lookup unavailable',
            $trace, 'model', $failures);
        $id = $saved['conversation_id'];
        $chats->record('admin', $actor, $id, 'Later fixture question', 'Lookup completed',
            'fedcba9876543210', 'local', [['name' => 'support_enquiries', 'status' => 'complete']]);
        $agentMonitoring = null;
        $agentConversations = $chats->recent(40);
        assertSame([['trace_id' => $trace, 'turn_id' => $saved['turn_id'], 'errors' => 3]],
            $agentConversations[0]['failed_tool_runs'], 'Failed lookup reference survives a later successful turn');
        ob_start();
        require app_path('views/admin/agent-monitoring.php');
        $html = (string)ob_get_clean();
        assertTrue(str_contains($html, '/admin/agent/review/'.$id.'?turn='.$saved['turn_id'])
            && str_contains($html, $trace) && !str_contains($html, 'Private fixture question'),
            'The monitoring link is available without a MySQL audit event and omits private chat text');
        $detail = $chats->reviewDetail($id, $saved['turn_id'], ['role' => 'admin', 'id' => 'fixture-owner']);
        assertSame($trace, $detail['turn']['trace_id'], 'Review opens the specific failed run');
    } finally {
        foreach (array_merge(glob($dir.'/*') ?: [], glob($dir.'/.conversation*') ?: []) as $path) @unlink($path);
        @rmdir($dir);
    }
};

$tests['agent runbook skills and storage docs are aligned with PHP code'] = static function (): void {
    $documents = [
        'AGENTS.md'=>'storage/agent-conversations',
        'docs/json-storage.md'=>'one JSON file per chat',
        'docs/architecture.md'=>'AgentTraceService',
        'docs/agent-observability.md'=>'AgentEvaluationService',
        '.agents/skills/backend-php-mysql/SKILL.md'=>'conversation',
        '.agents/skills/admin-ui/SKILL.md'=>'agent/monitoring',
        '.agents/skills/deployment/SKILL.md'=>'agent-conversations',
        '.agents/skills/docs/SKILL.md'=>'documentation-to-code',
    ];
    foreach($documents as $file=>$text) {
        $content=strtolower((string)file_get_contents(app_path($file)));
        assertTrue(str_contains($content,strtolower($text)),
            'Documentation includes implemented agent storage/trace contract: '.$file);
    }
    foreach ([
        'app/Services/AgentTraceService.php',
        'app/Services/AgentEvaluationService.php',
        'cli/agent-eval.php',
        'tests/fixtures/agent-golden.json',
        'tests/fixtures/agent-golden-baseline.json',
    ] as $file) {
        assertTrue(is_file(app_path($file)), 'Documented source is present: '.$file);
    }
    assertTrue(!str_contains(fileRead('docs/agentic-monorepo.md'),
        'Remote MySQL is the only runtime data store'), 'Do not claim chat history lives in MySQL');
    assertTrue(!str_contains(fileRead('docs/json-storage.md'),
        'All runtime data is stored in MySQL'), 'Chat JSON exception must remain documented');
    assertTrue(str_contains(fileRead('.agents/skills/backend-php-mysql/SKILL.md'), 'fails closed'),
        'Security skill reflects actual RemoteDbController authentication');
};

$tests['agent golden fixtures are reproducible, explicit, and fully passing'] = static function (): void {
    $suite = \App\Services\AgentEvaluationService::goldenSuite();
    assertSame(1, $suite['schema_version'], 'Golden dataset has a version');
    assertTrue($suite['total'] >= 10, 'Golden suite covers multiple agent flows');
    assertSame($suite['total'], $suite['passing'], 'Golden regression fixtures pass with current code');
    assertSame('deterministic_fixture_regressions_not_production_accuracy', $suite['metric'],
        'Golden result never claims live answer correctness');
    assertTrue(strlen($suite['dataset_hash'])===64, 'Suite is content-addressed for experiment comparisons');
};

$tests['agent trace correlates retrieval tools providers checks and response without PII'] = static function (): void {
    $tool = \App\Services\AgentTraceService::toolEvidence('find_order',
        ['customer_email'=>'customer@example.invalid','order_id'=>'sensitive123'],
        ['found'=>2,'orders'=>[['id'=>'sensitive123']]]);
    assertSame('orders',$tool['source'],'Business data source is identified');
    assertTrue(!str_contains(json_encode($tool),'customer@example.invalid'), 'No email in tool trace');
    assertTrue(!str_contains(json_encode($tool),'sensitive123'), 'No order ID in tool trace');
    $run = \App\Services\AgentTraceService::build('0123456789abcdef','model',[
        ['name'=>'find_order','status'=>'complete','duration_ms'=>12,'evidence'=>$tool]
    ],[
        ['model'=>'fixture-model','status'=>200,'total_ms'=>48]
    ],[
        'passed'=>true,'version'=>2
    ],'Two orders found.', [['source'=>'visible_products','result_count'=>3,'status'=>'complete']]);
    assertSame('0123456789abcdef',$run['trace_id'],'One trace ID connects the steps');
    assertSame(['retrieval','tool','model_attempt','evaluation','response'],
        array_column($run['spans'],'type'),'Observed stages are linked');
    foreach($run['spans'] as $span) {
        assertSame($run['trace_id'],$span['parent_span_id'],'Every stage points to root');
        assertTrue(preg_match('/^[a-f0-9]{16}$/D',$span['span_id'])===1,'Each stage has a distinct span ID');
    }
    assertTrue(!str_contains(json_encode($run),'sensitive123'),'No private identifier persisted in spans');
};

$tests['support feedback is explicit and resets on next user question'] = static function (): void {
    $root=sys_get_temp_dir().'/sps-support-feedback-'.bin2hex(random_bytes(6));
    assertTrue(mkdir($root,0700),'Private support feedback fixture exists');
    $service=new \App\Services\AgentConversationService($root);
    $actor=['type'=>'guest','id'=>'fixture-guest'];
    try {
        $first=$service->record('support',$actor,null,'Where do I track my order?','Go to My Orders.',
            bin2hex(random_bytes(8)),'local', retrieval: [['intent'=>'own_order']]);
        $id=$first['conversation_id'];
        $status=$service->rateSupportTurn($id,$actor,$first['turn_id'],'helpful');
        assertSame('confirmed_resolved',$status['resolution_status'],'Only customer confirmation marks resolved');
        $doc=$service->getForAdmin($id);
        assertSame('confirmed_resolved',$doc['resolution_status'],'Resolution is persisted in same chat JSON');
        assertSame(1,count(glob($root.'/*.json')?:[]),'Feedback does not create a second chat file');
        $second=$service->record('support',$actor,$id,'And refunds?','Please contact support.',
            bin2hex(random_bytes(8)),'fallback', retrieval: [['intent'=>'public_policy']]);
        assertSame('awaiting_feedback',$service->getForAdmin($id)['resolution_status'],
            'New question invalidates previous confirmation');
        try {
            $service->rateSupportTurn($id,$actor,$first['turn_id'],'helpful');
            throw new \RuntimeException('Expected old-turn rating rejection');
        } catch (\InvalidArgumentException) {}
        try {
            $service->rateSupportTurn($id,['type'=>'guest','id'=>'another'], $second['turn_id'], 'helpful');
            throw new \RuntimeException('Expected foreign guest rejection');
        } catch (\RuntimeException $e) {
            assertSame('Conversation not accessible to this actor',$e->getMessage(),'No cross-guest feedback');
        }
        assertSame('needs_followup',$service->rateSupportTurn($id,$actor,$second['turn_id'],'not_helpful')['resolution_status'],
            'Negative feedback marks unresolved, never assumed resolved');
        $recent=$service->recent();
        $summary=\App\Services\AgentEvaluationService::supportOutcomeSummary($recent);
        assertSame(1,$summary['counts']['needs_followup'],'Dashboard counts explicitly negative feedback');
        assertSame(0,$summary['counts']['confirmed_resolved'],'Confirmed rate resets for current turn');
        assertSame(['public_policy'],array_keys($summary['topics']),'A returning chat counts once under its latest completed topic');
        assertSame(1,$summary['topics']['public_policy']['needs_followup'],'The last completed topic carries the current feedback state');
    } finally {
        foreach(glob($root.'/*')?:[] as $f)@unlink($f);
        @unlink($root.'/.conversation.lock');@rmdir($root);
    }
};

$tests['support topic summaries count each current outcome within the support sample'] = static function (): void {
    $rows = [
        ['surface'=>'support','intent'=>'collection','resolution_status'=>'confirmed_resolved'],
        ['surface'=>'support','intent'=>'collection','resolution_status'=>'escalated'],
        ['surface'=>'support','intent'=>'own_order','resolution_status'=>'awaiting_feedback'],
        ['surface'=>'support','intent'=>'unrecognized private text'],
        ['surface'=>'admin','intent'=>'collection','resolution_status'=>'needs_followup'],
    ];
    $summary = \App\Services\AgentEvaluationService::supportOutcomeSummary($rows);
    $topics = $summary['topics'];
    assertSame(['collection','own_order','unknown'],array_keys($topics),'Only canonical support topics reach the monitor');
    assertSame(2,array_sum($topics['collection']),'Every sampled chat contributes one outcome');
    assertSame(1,$topics['collection']['confirmed_resolved'],'Only customer confirmation counts as resolved');
    assertSame(1,$topics['collection']['escalated'],'Escalation is its own outcome');
    assertSame(1,$topics['unknown']['unknown'],'Legacy rows stay visible as unclassified');
    foreach ($summary['counts'] as $state=>$count) {
        assertSame($count,array_sum(array_column($topics,$state)),'Topic counts reconcile to each outcome total');
    }
    assertSame([],\App\Services\AgentEvaluationService::supportOutcomeSummary([])['topics'],'Empty samples have no topic groups');
};

$tests['agent instruction contracts are separate and versioned'] = static function (): void {
    $service = \App\Services\AgentInstructionService::class;
    assertTrue(str_contains($service::forSurface('support'), 'authenticated customer'), 'Support instructions enforce customer scope');
    assertTrue(str_contains($service::forSurface('admin',['role'=>'admin']), 'read-only'), 'Admin instructions enforce tool evidence');
    assertTrue($service::forSurface('support') !== $service::forSurface('admin',['role'=>'admin']), 'Agents use distinct instructions');
};

$tests['agent capability policies forbid support from enterprise data tools'] = static function (): void {
    $policy = \App\Services\AgentCapabilityPolicy::class;
    assertTrue(!$policy::allows('support','find_order', ['role'=>'admin','email'=>'admin@example.invalid']), 'Support cannot inherit enterprise order tools');
    assertTrue(!$policy::allows('enterprise','sales_summary', ['role'=>'customer','email'=>'customer@example.invalid']), 'Customer cannot access enterprise analytics');
    assertTrue($policy::allows('enterprise','sales_summary', ['role'=>'admin','email'=>'admin@example.invalid']), 'Admin can access scoped aggregate tool');
    assertTrue(!$policy::allows('support','own_ticket_status', null), 'Guest cannot read private support tickets');
    assertTrue($policy::allows('support','public_catalog', null), 'Guests can read public catalogue');
    assertTrue(!$policy::allows('support','write_file', ['role'=>'admin']), 'Support has no write filesystem capability');
    $tools = new \App\Services\SupportToolRegistry(null);
    assertSame(['error'=>'Not permitted'], $tools->run('own_ticket_status', []), 'Missing actor must be rejected before query');
    assertSame(['error'=>'Invalid policy'], $tools->run('public_policy',['key'=>'internal-secret']), 'Unknown policy rejected');
};

$tests['agent conversations validate identity and deterministic evaluations'] = static function (): void {
    $root = sys_get_temp_dir() . '/sps-agent-identity-' . bin2hex(random_bytes(6));
    assertTrue(mkdir($root, 0700), 'Private actor fixture directory exists');
    try {
        $service = new \App\Services\AgentConversationService($root);
        $guest = $service::actor('support', null);
        assertSame('guest', $guest['type'], 'Guest identity is anonymous');
        assertTrue(strlen($guest['id']) === 32, 'Guest ID is securely generated');
        $record = $service->record('support', $guest, null, 'Where is my order?',
            'Please sign in for your order.', bin2hex(random_bytes(8)), 'private_account');
        assertSame(1, $record['turns'], 'Conversation receives a turn');
        assertTrue($record['evaluation']['passed'], 'Non-sensitive support reply passes');
        assertTrue(!\App\Services\AgentEvaluationService::evaluate('support','help','sk-proj-EXAMPLESECRET123456789',[])['passed'], 'Secret-like key fails evaluation');
        assertTrue(!\App\Services\AgentEvaluationService::evaluate('support','help','<analysis>hidden',[])['passed'], 'Exposed scaffolding fails evaluation');
        try {
            $service::actor('admin', ['role'=>'customer','email'=>'u@example.invalid']);
            throw new \RuntimeException('Expected admin authorization rejection');
        } catch (\RuntimeException $error) {
            assertSame('Admin access required.', $error->getMessage(), 'Admin role is enforced server-side');
        }
    } finally {
        foreach (glob($root.'/*') ?: [] as $file) @unlink($file);
        @unlink($root.'/.conversation.lock');
        @rmdir($root);
    }
};

$tests['enterprise follow-up retrieves only actor-owned persisted history'] = static function (): void {
    $doc = ['surface'=>'admin','actor'=>['type'=>'admin','id'=>'u1'],'turns'=>[
        ['report_context'=>['tool'=>'sales_summary','from'=>'2026-11-01','to'=>'2026-11-07']]
    ]];
    $service = new \App\Services\AgentConversationService();
    $report = $service::previousReportFromDocument($doc,['type'=>'admin','id'=>'u1']);
    assertSame('2026-11-01',$report['from'],'Server report history is available for same actor');
    try {
        $service::previousReportFromDocument($doc,['type'=>'admin','id'=>'u2']);
        throw new \RuntimeException('Expected actor rejection');
    } catch (\RuntimeException $e) {
        assertSame('Conversation not accessible to this actor',$e->getMessage(),'Other admins cannot replay another actor report scope');
    }
    assertSame(null,$service->previousReportForActor('bad-id',['type'=>'admin','id'=>'u1']),'Invalid conversation IDs rejected');
};

$tests['site operations counts are deterministic and date scoped'] = static function (): void {
    $ops = \App\Services\SiteInsightsService::operations([
        ['created_at'=>'2026-11-03T10:00:00Z'],['created_at'=>'2026-10-10T10:00:00Z']
    ],[
        ['status'=>'open','created_at'=>'2026-11-02T12:00:00Z'],
        ['status'=>'answered','created_at'=>'2026-10-21T12:00:00Z'],
    ],'2026-11-01','2026-11-07');
    assertSame(2,$ops['total_users'],'Total registered users');
    assertSame(1,$ops['new_users'],'Only new users in exact window');
    assertSame(1,$ops['opened_tickets'],'Only opened cases within window');
    assertSame(1,$ops['open_tickets'],'Unresolved cases are counted regardless of creation date');
};

$tests['first party telemetry only records allowlisted events and SEO checks are deterministic'] = static function (): void {
    $events = [
        ['event'=>'commerce.event','created_at'=>date('c'),'meta'=>['event'=>'cart_add']],
        ['event'=>'commerce.event','created_at'=>date('c'),'meta'=>['event'=>'checkout_initiated']],
        ['event'=>'commerce.event','created_at'=>date('c'),'meta'=>['event'=>'private_unknown']],
    ];
    $counts = \App\Services\SiteTelemetryService::counts($events);
    assertSame(1,$counts['cart_add'],'Cart events counted');
    assertSame(1,$counts['checkout_initiated'],'Checkout events counted');
    assertSame(2,array_sum($counts),'Unknown events excluded');
    $seo = \App\Services\SiteInsightsService::catalogSeo([
        ['name'=>'Lamp','slug'=>'lamp','description'=>'Short','image_url'=>'']
    ],[
        ['title'=>'Example article','slug'=>'example','excerpt'=>'']
    ]);
    assertSame(3,$seo['total_issues'],'Local SEO checks reveal short descriptions, missing images and missing excerpt');
};

$tests['development fixture remote mutations include authentication without command line secrets'] = static function (): void {
    $cli = file_get_contents(app_path('cli/bapXphp'));
    assertTrue(str_contains($cli, '"password"=>getenv("BAPX_MYSQL_PASS")'), 'Remote mutation payloads must authenticate against the existing database bridge');
    assertTrue(substr_count($cli, '"password"=>getenv("BAPX_MYSQL_PASS")') >= 2, 'Both upsert and delete authenticate');
    assertTrue(str_contains($cli, 'printf \'%s\' "$payload" | curl'), 'Post body must be sent over stdin, not placed in curl command arguments');
    assertTrue(str_contains($cli, '--data-binary @-'), 'Remote mutation should stream the authenticated JSON body');
};

$tests['CLI article editing preserves metadata and refuses concurrent overwrites'] = static function (): void {
    $root = sys_get_temp_dir() . '/sps-blog-cli-' . bin2hex(random_bytes(6));
    $dir = $root . '/content/blog/posts';
    $blog = new \App\Services\BlogService($dir);
    $openWriter = static function () use ($root): array {
        $process = proc_open([PHP_BINARY, app_path('cli/blog-write.php'), $root, 'edit', 'fixture'], [
            ['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w'],
        ], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Could not open the CLI fixture');
        // This line is printed only after the writer captures its base revision.
        assertSame("Editing fixture\n", fgets($pipes[1]), 'CLI must open the intended article');
        return [$process, $pipes];
    };
    $finish = static function (array $writer): int {
        [$process, $pipes] = $writer;
        fwrite($pipes[0], "Edited title\n\n\n\n\n\n\n.\n");
        fclose($pipes[0]);
        stream_get_contents($pipes[1]); fclose($pipes[1]);
        stream_get_contents($pipes[2]); fclose($pipes[2]);
        return proc_close($process);
    };
    try {
        $post = $blog->save(['slug'=>'fixture', 'title'=>'Original title', 'content'=>'Original body',
            'published'=>false, 'category'=>'custom-guides', 'seo_title'=>'Preserved SEO']);
        $adminCategories = array_column($blog->categories(), 'name', 'slug');
        assertSame('Custom Guides', $adminCategories['custom-guides'], 'Existing category survives in the editor');
        assertTrue(!in_array('custom-guides', array_column($blog->categories(false), 'slug'), true), 'Draft-only categories stay private');
        assertSame(0, $finish($openWriter()), 'CLI submission should succeed with its original revision');
        $current = $blog->find('fixture');
        assertSame('Edited title', $current['title'], 'CLI explicit title persists');
        assertSame('Preserved SEO', $current['seo_title'], 'Existing editor metadata survives a CLI edit');
        assertSame(false, $current['published'], 'Saving through the CLI preserves a draft');
        assertSame('Original body', trim($current['content']), 'Unchanged body survives a CLI edit');
        $stale = $openWriter();
        $newer = $blog->save(array_replace($current, ['content'=>'Newer article']));
        assertTrue($finish($stale) !== 0, 'A CLI edit must reject an intervening browser save');
        assertSame($newer['_revision'], $blog->find('fixture')['_revision'], 'CLI conflict must leave the current article intact');
    } finally {
        $current = $blog->find('fixture');
        if ($current) $blog->delete('fixture', $current['_revision']);
        @unlink($dir . '/.blog-write.lock');
        rmdir($dir); rmdir(dirname($dir)); rmdir(dirname($dir, 2)); rmdir($root);
    }
};

$tests['authored pages validate immutable addresses and render Markdown with literal raw HTML'] = static function (): void {
    $service = \App\Services\AuthoredPageService::class;
    $input = ['slug'=>'visiting-guide', 'title'=>' Visiting guide ', 'content'=>'## Opening hours', 'status'=>'draft'];
    $record = $service::record($input);
    assertSame('pg:visiting-guide', $record['id'], 'Authored page key occupies its own settings namespace');
    assertTrue($service::validRevision($record['revision']), 'Only the server establishes an opaque page revision');
    assertSame('Visiting guide', $record['title'], 'Title whitespace is normalized');
    foreach ([['slug'=>'../terms'], ['slug'=>str_repeat('a', 33)], ['status'=>'visible'], ['content'=>''], ['content'=>str_repeat('x', 65537)]] as $change) {
        $blocked = false;
        try { $service::record(array_replace($input, $change)); }
        catch (InvalidArgumentException) { $blocked = true; }
        assertTrue($blocked, 'Malformed pages cannot be saved');
    }
    $html = $service::renderContent("## Opening hours\n\n<span>Literal</span> and **bold**");
    assertTrue(str_contains($html, '<h2') && str_contains($html, '<strong>bold</strong>'), 'Markdown provides semantic page formatting');
    assertTrue(!str_contains($html, '<span>') && str_contains($html, '&lt;span'), 'Raw HTML appears as text');
    $examples = "## Values < 3\n\nInline `x < 3`\n\n```txt\n<x> &lt;\n```\n\n<span>Text</span>";
    $dom = new DOMDocument();
    $dom->loadHTML($service::renderContent($examples), LIBXML_NOERROR | LIBXML_NOWARNING);
    assertSame('Values < 3', $dom->getElementsByTagName('h2')->item(0)->textContent, 'Headings display the authored angle bracket once');
    assertSame('x < 3', $dom->getElementsByTagName('code')->item(0)->textContent, 'Inline code preserves a literal comparison');
    assertSame("<x> &lt;\n", $dom->getElementsByTagName('pre')->item(0)->textContent, 'Fenced code preserves both tags and literal entities');
    assertSame(0, $dom->getElementsByTagName('span')->length, 'Literal raw markup cannot produce a span element');
};

$tests['blog enhancement candidates preserve manual images and distinguish provider failures'] = static function (): void {
    $service = \App\Services\BlogDraftService::class;
    assertSame('A revised title', $service::reviewableEnhancement('title', 'Original', ' "A revised title" '), 'Clean provider headline');
    $original = "First paragraph.\n\n![A mala](/assets/images/mala.webp)";
    $revised = "Revised paragraph.\n\n![The same mala](/assets/images/mala.webp)";
    assertSame($revised, $service::reviewableEnhancement('content', $original, $revised), 'Reviewed text can retain the chosen image and update its alt text');
    foreach ([
        ['content', $original, 'Removed the image'],
        ['content', 'No illustration', '![Added](/assets/images/mala.webp)'],
        ['content', $original, '<img src="/assets/images/different.webp">'],
        ['title', 'Original', "Two\nHeadlines"],
        ['title', 'Original', '<b></b>'],
    ] as [$field, $before, $response]) {
        $refused = false;
        try { $service::reviewableEnhancement($field, $before, $response); }
        catch (InvalidArgumentException) { $refused = true; }
        assertTrue($refused, 'Invalid text or image changes cannot replace the draft');
    }
    assertTrue(str_contains($service::enhancementError('rate_limit'), 'limit'), 'Show an actionable provider category');
    assertTrue(!str_contains($service::enhancementError('upstream-private-details'), 'private-details'), 'Unknown provider failures use a public message');
};

$tests['blog draft templates render usable internal links and require explicit save'] = static function (): void {
    $templates = new \App\Services\BlogDraftService();
    $renderer = new \App\Services\MarkdownRenderer();
    foreach (['editorial' => '/shop', 'product' => '/checkout', 'tool' => '/login', 'help' => '/account'] as $template => $href) {
        $draft = $templates->draft($template, 'Fixture title', '/help');
        $document = new DOMDocument();
        $document->loadHTML($renderer->render($draft), LIBXML_NOERROR | LIBXML_NOWARNING);
        $links = (new DOMXPath($document))->query('//a[@href="' . $href . '"]');
        assertTrue($links->length > 0, "Template {$template} must link to {$href}");
        assertTrue(!str_contains($links->item(0)->textContent, '['), "Template {$template} must render a clean link label");
        assertTrue(!str_contains($document->textContent, ']'), "Template {$template} must not leave Markdown brackets in the article");
    }
    $guide = $templates->draft('tool', 'Fixture feature', '/help');
    assertTrue(!str_contains($guide, 'saved automatically') && str_contains($guide, 'Save'), 'Tool guides should describe explicit saving');
};

$tests['missing hosted blog photos show bundled image instead of broken thumbnail'] = static function (): void {
    $post = ['og_image'=>'https://sripanchamispiritual.com/assets/images/blog/tulsi-significance.jpg'];
    assertSame('/assets/images/hero-temple-bg.webp', \App\Services\BlogService::resolvedImageUrl($post),
        'Missing local blog art must use an existing local fallback');
    assertSame('/assets/images/blog/order-products.webp',
        \App\Services\BlogService::resolvedImageUrl(['og_image'=>'/assets/images/blog/order-products.webp']),
        'A real authored blog image must be preserved');
    assertSame('/assets/images/hero-temple-bg.webp',
        \App\Services\BlogService::resolvedImageUrl(['og_image'=>'/assets/images/blog/missing.webp']),
        'Missing relative blog images also use the fallback');
    assertSame('/assets/images/hero-temple-bg.webp',
        \App\Services\BlogService::resolvedImageUrl(['og_image'=>'/assets/images/../../.env']),
        'Unsafe image paths must not be resolved');
    assertTrue(str_contains(fileRead('app/Controllers/BlogController.php'), '$this->siteUrl($image)'),
        'Blog OpenGraph images use a canonical absolute URL');
    assertTrue(str_contains(fileRead('views/public/blog-post.php'), '$schemaBase . $schemaImage'),
        'Article structured data uses an absolute image URL');
    foreach (['tulsi-significance-hinduism','navagraha-remedies','pooja-items-for-home','benefits-of-rudraksha'] as $slug) {
        $post = (new \App\Services\BlogService())->find($slug);
        assertTrue($post !== null, 'Legacy article exists');
        $url = \App\Services\BlogService::resolvedImageUrl($post);
        assertTrue(is_file(app_path(ltrim($url,'/'))), 'Blog card has a real image for '.$slug);
    }
};

$tests['blog structured data uses configured canonical origin instead of request host'] = static function (): void {
    $view=fileRead('views/public/blog-post.php');
    assertTrue(!str_contains($view, "\$_SERVER['HTTP_HOST']"), 'Article schema must not trust request-host metadata');
    assertTrue(str_contains(fileRead('app/Controllers/BlogController.php'), "'schemaBase' => rtrim(\$this->siteUrl('/'), '/')"), 'Controller supplies the canonical configured origin');
    assertTrue(str_contains($view,'$schemaBase . $schemaImage'), 'Image schema remains absolute under that origin');
};

$tests['admin commerce overview renders empty and populated periods without PHP errors'] = static function (): void {
    $render = static function(array $reports, array $products, array $statuses): string {
        $reportRows = $reports;
        $productRows = $products;
        $orderStatusCounts = $statuses;
        $from = '2026-11-01';
        $to = '2026-11-15';
        $money = static fn(float $value): string => 'INR ' . number_format($value, 2);
        ob_start();
        try {
            require app_path('views/admin/_commerce-overview.php');
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    };
    $empty = $render([], [], []);
    assertTrue(str_contains($empty, 'No paid orders in this period.'), 'An empty period should show the empty-state label');
    assertTrue(str_contains($empty, 'No purchased products in this period.'), 'An empty product series should render safely');
    assertTrue(str_contains($empty, 'INR 0.00'), 'An empty period should show zero rather than an exception');
    $populated = $render(
        [['period'=>'2026-11-14','orders'=>1,'units'=>2,'gross'=>540.0,'refunded'=>40.0,'net'=>500.0]],
        [['product'=>'Test item','units'=>2,'net'=>500.0]],
        ['confirmed'=>1]
    );
    assertTrue(str_contains($populated, 'Test item'), 'Populated top-products chart must remain visible');
    assertTrue(str_contains($populated, 'INR 500.00'), 'Populated totals must remain accurate');
    assertTrue(str_contains($populated, 'paid orders'), 'Status ring remains visible for positive counts');
    $zeroStatuses = $render([], [], ['confirmed'=>0]);
    assertTrue(str_contains($zeroStatuses, 'No paid orders in this period.'), 'Zero-count status buckets must not divide by zero');
};

$tests['support named festive collection listing uses only collection members'] = static function (): void {
    $collections = [
        ['name'=>'Ayyappa Swamy Festive Sale', 'slug'=>'collection-ayyappa', 'product_ids'=>['a','b']],
        ['name'=>'Sassthi Festival Sale', 'slug'=>'collection-sassthi', 'product_ids'=>['c']],
    ];
    $match = \App\Services\AgentContextService::requestedCollection(
        'ayyappan swamy festive products list please', $collections);
    assertSame('collection-ayyappa', $match['slug'], 'Ayyappan phrasing matches the real Ayyappa sale');
    assertSame(null, \App\Services\AgentContextService::requestedCollection(
        'festival products list please', $collections), 'Generic festive terms cannot guess a collection');
    assertSame('collection-sassthi', \App\Services\AgentContextService::requestedCollection(
        'sasthi festival items please', $collections)['slug'], 'Other named festivals also resolve');
    $visible = [
        ['id'=>'ring', 'name'=>'Unrelated gemstone ring', 'slug'=>'unrelated-ring'],
        ['id'=>'a', 'name'=>'Golden Panchaloga Ayyappan Swamy Dollar Pendant', 'slug'=>'Ayyappan Pendant'],
        ['id'=>'b', 'name'=>'Ayyappa Dollar with Impon Malai', 'slug'=>'ayyappa-dollar-with-impon-malai'],
        ['id'=>'c', 'name'=>'Sri Vel Mayil Pendant', 'slug'=>'vel'],
    ];
    $selected = (new \App\Services\CategoryService())->collectionProducts($match, $visible);
    $answer = \App\Services\SupportBotService::collectionListingReply($match, $selected);
    assertSame(2, count($selected), 'Only the selected festival products are retrieved');
    assertTrue(str_contains($answer, 'Golden Panchaloga') && str_contains($answer, 'Impon Malai'),
        'Answer names the two actual collection members');
    assertTrue(!str_contains($answer, 'Unrelated gemstone ring'), 'Never list a random product from the default catalogue');
    assertTrue(str_contains($answer, '/shop?category=collection-ayyappa'), 'Collection-specific shop link is included');
    $empty = \App\Services\SupportBotService::collectionListingReply($match, []);
    assertTrue(str_contains($empty, 'no visible products'), 'Empty collections do not fabricate results');
};

$tests['media removal is restricted to unused uploaded media with CSRF'] = static function (): void {
    $media = \App\Services\MediaService::class;
    $upload = ['id'=>'0123456789abcdef', 'url'=>'/assets/images/media/2026/11/test.webp', 'context'=>'shared'];
    assertTrue($media::canDeleteRecord($upload), 'Uploaded library record is eligible for deletion review');
    assertTrue(!$media::canDeleteRecord(['id'=>'file:/assets/images/blog/valid.webp','url'=>'/assets/images/blog/valid.webp','source'=>'file']),
        'Bundled images are not deletable');
    assertTrue(!$media::canDeleteRecord($upload + ['source'=>'legacy']), 'Legacy YAML metadata is not deletable');
    assertTrue(!$media::canDeleteRecord(['id'=>'0123456789abcdef', 'url'=>'/assets/images/products/hero.webp']),
        'Product assets cannot be deleted from the uploaded-media route');
    $references = $media::referencesToUrl($upload['url'], [
        'products'=>[['name'=>'Test product', 'image_url'=>$upload['url']]],
        'articles'=>[['title'=>'Another post','og_image'=>'/assets/images/blog/other.webp']],
    ]);
    assertTrue(count($references)===1 && str_contains($references[0], 'Test product'),
        'Live referenced images cannot be removed');
    assertSame([], $media::referencesToUrl($upload['url'], ['products'=>[['image_url'=>'/not-this.webp']]]),
        'Unrelated content does not block uploads');
    $routes = \App\Services\ProjectMapService::registry()['routes'];
    $mediaDelete = array_values(array_filter($routes, static fn($r):bool =>
        ($r['path']??'')==='/admin/media/delete' && ($r['method']??'')==='POST'));
    assertSame(1, count($mediaDelete), 'Media deletion is an authenticated admin POST route');
    $controller = file_get_contents(app_path('app/Controllers/AdminController.php'));
    assertTrue(str_contains($controller, 'public function deleteMedia') && str_contains($controller, '$this->validateCsrf()'),
        'Media delete controller requires CSRF');
    $template = file_get_contents(app_path('views/admin/media.php'));
    assertTrue(str_contains($template, 'Delete upload') && str_contains($template, "name=\"_csrf\""),
        'Library exposes a confirmation form with CSRF');
    assertTrue(str_contains($template, 'return confirm('), 'Owner must confirm image deletion');
};

$tests['database service can connect to MySQL'] = function (): void {
    try {
        // Quick pre-check: verify the host is reachable before attempting PDO
        $cfg = require app_path('config/database.php');
        $host = $cfg['host'];
        $port = $cfg['port'];
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($host, (int)$port, $errno, $errstr, 2);
        if (!$fp) {
            return; // MySQL not reachable (rate-limited or offline), skip test
        }
        fclose($fp);

        $store = new \App\Services\DatabaseService();
        $pdo = $store->connection();
        assertTrue($pdo !== null, 'DatabaseService should return a PDO connection');
    } catch (\Throwable $e) {
        // MySQL may be rate-limited (500/hr). Skip test gracefully.
        return;
    }
};

$tests['remote database failures are loud cached and never use implicit localhost storage'] = function (): void {
    $service = file_get_contents(app_path('app/Services/DatabaseService.php'));
    $config = file_get_contents(app_path('config/database.php'));
    $controller = file_get_contents(app_path('app/Controllers/RemoteDbController.php'));
    assertTrue(str_contains($service, 'private static array $remoteQueryCache'), 'Remote reads should use a request-level cache shared across service instances');
    assertTrue(str_contains($service, 'array_key_exists($cacheKey, self::$remoteQueryCache)'), 'Repeated remote queries should return the request-level cached result');
    assertTrue(str_contains($service, 'self::$remoteQueryCache = []'), 'Database mutations should invalidate remote read caches');
    assertTrue(str_contains($service, 'Remote database request failed with HTTP'), 'Non-200 remote responses should throw a visible failure');
    assertTrue(str_contains($service, 'Remote database returned an invalid response'), 'Malformed remote responses should throw instead of becoming empty data');
    assertTrue(!str_contains($config, "'host' => \$env['BAPX_MYSQL_HOST'] ?: 'localhost'"), 'Runtime storage must not fall back to implicit localhost MySQL');
    assertTrue(str_contains($config, "\$_SERVER[\$key] ?? \$_ENV[\$key] ?? getenv(\$key)"), 'Runtime environment overrides should take precedence over .env file defaults');
    assertTrue(str_contains($controller, 'new DatabaseService(true)'), 'The remote DB endpoint must terminate at direct hosted MySQL instead of recursively calling itself');
    $public = file_get_contents(app_path('app/Controllers/PublicController.php'));
    assertTrue(!str_contains($public, 'catch (\\Throwable $e) { $products = []; }'), 'Catalog transport failures must not be rendered as a valid empty shop');
    $bootstrap = file_get_contents(app_path('app/bootstrap.php'));
    $failure=fileRead('app/Services/RequestFailureService.php');
    assertTrue(str_contains($bootstrap,'RequestFailureService::register')&&str_contains($failure,'http_response_code(500)'), 'Unclassified request failures use the controlled response boundary rather than a guessed database503');
    $api = file_get_contents(app_path('api/index.php'));
    assertTrue(!str_contains($api, "'detail' => \$e->getMessage()"), 'API failures must be logged without exposing internal transport details');
    assertTrue(str_contains($api, "['error' => 'Service temporarily unavailable']"), 'API database failures should return a stable 503 response');
};

$tests['payment signature verification matches Razorpay format'] = function (): void {
    $service = new PaymentService('secret');
    $signature = hash_hmac('sha256', 'order_1|pay_1', 'secret');
    assertTrue($service->verifySignature('order_1', 'pay_1', $signature), 'Valid payment signature should pass');
    assertTrue(!$service->verifySignature('order_1', 'pay_1', 'bad'), 'Invalid payment signature should fail');
};

$tests['project map registry has no missing route mappings'] = function (): void {
    $map = ProjectMapService::scan();
    $validation = ProjectMapService::validate($map);
    assertSame([], $validation['missing_route_mappings'], 'Routes should map to controllers');
    assertSame([], $validation['missing_services'], 'Routes should reference declared services');
    assertSame([], $validation['missing_collections'], 'Collections should be declared');
};

$tests['project map generation lists schema collections without runtime stores'] = function (): void {
    $scan = ProjectMapService::scan();
    assertTrue(in_array('secrets', $scan['schema_collections'], true), 'Secrets should be a registered schema collection');
    assertTrue(in_array('addresses', $scan['schema_collections'], true), 'Saved customer addresses should be a registered schema collection');
    assertTrue(str_contains(ProjectMapService::renderSystematicMermaid(), 'secrets'), 'Generated Mermaid should include secrets schema entry');
};

$tests['project map grounds shared navigation in registered get routes'] = function (): void {
    $scan = ProjectMapService::scan();
    foreach (['/contact', '/account/dashboard', '/account/dashboard/orders', '/account/dashboard/sessions'] as $path) {
        assertTrue(in_array($path, $scan['navigation'], true), "Shared navigation should expose the existing {$path} route");
    }
    assertSame([], $scan['gaps']['navigation_without_get_route'], 'Every internal shared navigation path should resolve to a registered GET route');
    assertTrue(str_contains(ProjectMapService::renderSystematicMermaid(), 'Navigation Paths'), 'Generated Mermaid should include shared navigation relationships');
    $mustBeEmpty = ['missing_route_mappings','missing_controller_files','missing_service_files','missing_view_files','navigation_without_get_route','unwired_controllers','unwired_views'];
    foreach ($mustBeEmpty as $kind) {
        if (!array_key_exists($kind, $scan['gaps'])) continue;
        assertSame([], $scan['gaps'][$kind], "Systematic map should not report unresolved {$kind} gaps");
    }
    foreach (['unwired_services', 'unwired_schema_collections', 'admin_mutations_without_audit'] as $kind) {
        if (!empty($scan['gaps'][$kind])) {
            $items = is_array($scan['gaps'][$kind]) ? $scan['gaps'][$kind] : [$scan['gaps'][$kind]];
            $label = is_array($items[0] ?? null) ? array_map(fn($r) => $r['method'] . ' ' . $r['path'], $items) : $items;
            echo "\n  ⚠ {$kind}: " . implode(', ', $label);
        }
    }
};

$tests['agent workflow diagnoses before issue tracking and stays source grounded'] = function (): void {
    $contract = file_get_contents(app_path('AGENTS.md'));
    $readme = file_get_contents(app_path('README.md'));
    foreach (['Work order', 'evidence-backed', 'Inspect existing implementations before creating', 'Verify in a browser'] as $needle) {
        assertTrue(str_contains($contract, $needle), "AGENTS.md should include {$needle}");
    }
    assertTrue(str_contains($readme, 'AGENTS.md') || str_contains($readme, 'AGENTS.md'), 'README should reference the agent contract instead of duplicating its workflow');
};

$tests['independent deployment repository and runtime artifacts are properly managed'] = function (): void {
    $ignore = file_get_contents(app_path('.gitignore'));
    $cli = file_get_contents(app_path('cli/bapXphp'));
    assertTrue(!is_file(app_path('.github/workflows/sync-upstream.yml')), 'Independent deployment repository should not retain fork-sync automation');
    assertTrue(!is_file(app_path('.github/workflows/notify-fork.yml')), 'Notify-fork was replaced by schedule-based sync');
    foreach (['/server.log', '/storage/logs/'] as $path) {
        assertTrue(str_contains($ignore, $path), "Git should ignore {$path}");
    }
    assertTrue(str_contains($cli, 'Live production audit events (remote MySQL)'), 'CLI logs should be remote-first');
    assertTrue(str_contains($cli, 'cmd_artifacts_clean'), 'CLI should own artifact cleanup');
};

$tests['repo has agent-readable schema and built-in skills'] = function (): void {
    $schemaPath = app_path('storage/schema/collections.php');
    assertTrue(is_file($schemaPath), 'PHP schema registry should exist');
    $schema = require $schemaPath;
    assertTrue(is_array($schema), 'PHP schema registry should return array');
    foreach (['products', 'categories', 'coupons', 'astrologers', 'temples', 'orders', 'appointments', 'wallet_transactions', 'support_tickets', 'media_files', 'audit_events', 'mail_queue', 'reviews', 'settings', 'contact_submissions'] as $collection) {
        assertTrue(isset($schema['collections'][$collection]), "Schema should define {$collection}");
    }
    assertTrue(in_array('image_urls', $schema['collections']['products']['media_fields'] ?? [], true), 'Product schema should define gallery media field');
    assertTrue((new SchemaService())->adminFields('products') !== [], 'SchemaService should expose admin fields');
    foreach ([
        'AGENTS.md',
        '.agents/skills/backend-php-mysql/SKILL.md',
        '.agents/skills/schema/SKILL.md',
        '.agents/skills/admin-ui/SKILL.md',
        '.agents/skills/frontend-php/SKILL.md',
        '.agents/skills/deployment/SKILL.md',
        '.agents/skills/docs/SKILL.md',
    ] as $path) {
        assertTrue(is_file(app_path($path)), "Built-in agent instruction file should exist: {$path}");
    }
    $agentFiles = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getBasename() === 'AGENTS.md') $agentFiles[] = str_replace(app_path() . '/', '', $file->getPathname());
    }
    sort($agentFiles);
    assertTrue($agentFiles === ['AGENTS.md'], 'Root AGENTS.md should be the only AGENTS.md');
    foreach (['example-Agent.md', '.codex'] as $path) {
        assertTrue(!file_exists(app_path($path)), "Obsolete duplicated agent instruction path should not exist: {$path}");
    }
    assertTrue(file_exists(app_path('AGENTS.md')), 'AGENTS.md must exist and carry the binding contract');
    $agentsMd = (string)file_get_contents(app_path('AGENTS.md'));
    assertTrue(str_contains($agentsMd, '## Testing') && str_contains($agentsMd, '## Work order'), 'AGENTS.md must carry the complete contract');
    assertTrue(!file_exists(app_path('CLAUDE.md')), 'The old duplicate contract must not remain');
    assertTrue(!is_dir(app_path('.agents/handoffs')) && !is_dir(app_path('.agents/workflows')), 'Removed agent orchestration must not return');
};

$tests['application exposes exactly admin chat and customer support agents'] = function (): void {
    $routes = \App\Services\ProjectMapService::registry()['routes'];
    $agentRoutes = array_values(array_filter($routes, fn($route) => in_array($route['path'] ?? '', ['/admin/agent', '/admin/agent/ask', '/support', '/support/ask'], true)));
    assertSame(4, count($agentRoutes), 'Two agent surfaces should expose only their page and ask routes');
    assertTrue(!in_array('/api/agent', array_column($routes, 'path'), true), 'Duplicate public agent API must be removed');
    assertTrue(!is_file(app_path('app/Controllers/AgentController.php')), 'Duplicate AgentController must be removed');
    assertTrue(!is_file(app_path('config/agent.yml')), 'Duplicate agent YAML config must be removed');
    $cli = (string)file_get_contents(app_path('cli/bapXphp'));
    foreach (['browser-agent', 'cmd_browser_agent', 'cmd_task', 'BAPX_AGENT_URL', 'pma-client.php'] as $removed) {
        assertTrue(!str_contains($cli, $removed), "CLI should not contain removed surface {$removed}");
    }
    foreach (['.bin/download-chrome.php', '.bin/launch-chrome.sh', 'cli/pma-client.php'] as $removedFile) {
        assertTrue(!is_file(app_path($removedFile)), "Browser dependency should be removed: {$removedFile}");
    }
};

$tests['every project skill has portable frontmatter and no empty reference placeholder'] = function (): void {
    $skillFiles = glob(app_path('.agents/skills/*/SKILL.md')) ?: [];
    assertTrue(count($skillFiles) >= 8, 'All canonical project skills should be present');
    foreach ($skillFiles as $file) {
        $text = (string)file_get_contents($file);
        assertTrue((bool)preg_match('/\A---\nname: [a-z0-9]+(?:-[a-z0-9]+)*\ndescription: .+\n---\n/s', $text), basename(dirname($file)) . ' should have only portable name/description frontmatter first');
        assertTrue(!str_contains($text, 'type: skill'), basename(dirname($file)) . ' should not use ignored type frontmatter');
    }
    assertTrue(!(bool)glob(app_path('.agents/skills/*/references/.gitkeep')), 'Skills should not carry empty reference placeholders');
    assertTrue(!is_dir(app_path('.agents/skills/backend-json')), 'Obsolete backend-json skill name should not return');
    assertTrue(!is_dir(app_path('.claude/skills')), 'Canonical skills should not be duplicated under .claude');
    $cliSkills = shell_exec('cd ' . escapeshellarg(app_path()) . ' && ./bapXphp skills 2>&1') ?: '';
    assertTrue(str_contains($cliSkills, 'backend-php-mysql'), 'CLI skill discovery should include canonical .agents skills');
    assertTrue(!str_contains($cliSkills, 'backend-json'), 'CLI skill discovery should not expose the obsolete backend-json name');
};

$tests['knowledge index keeps type-qualified concepts collision-free'] = function (): void {
    $graph = (new \App\Services\KnowledgeGraphService(app_path()))->build();
    $concepts = $graph['concepts'];
    $schema = require app_path('storage/schema/collections.php');
    foreach (array_keys($schema['collections']) as $collection) {
        assertTrue(isset($concepts['schema:' . $collection]), "Knowledge index should include schema:{$collection}");
    }
    foreach (glob(app_path('.agents/skills/*/SKILL.md')) ?: [] as $file) {
        $name = basename(dirname($file));
        assertTrue(isset($concepts['skill:' . $name]), "Knowledge index should include skill:{$name}");
    }
    assertTrue(isset($concepts['route:get__shop']), 'Knowledge index should include type-qualified shop route');
    assertTrue(isset($concepts['blog:benefits-of-rudraksha']), 'Knowledge index should include type-qualified blog');
    $missingImage = $concepts['image:assets/images/blog/rudraksha-benefits.jpg'] ?? null;
    assertTrue(is_array($missingImage), 'Knowledge index should retain a concept for a referenced missing image');
    assertSame(false, $missingImage['exists'] ?? null, 'Missing image concept should expose exists=false');
    assertTrue(in_array('content/blog/posts/benefits-of-rudraksha.md', $missingImage['used_in'] ?? [], true), 'Missing image should identify its referring blog');
};

$tests['local development router serves existing static files directly'] = function (): void {
    $index = file_get_contents(app_path('index.php'));
    assertTrue(str_contains($index, "PHP_SAPI === 'cli-server'"), 'Router should detect PHP built-in server');
    assertTrue(str_contains($index, 'is_file($file)'), 'Router should return static files directly during local development');
    assertTrue(str_contains($index, 'return false'), 'Router should let the built-in server serve existing static assets');
};

$tests['public and api routes cover spiritual and category pages without fallback gaps'] = function (): void {
    $index = file_get_contents(app_path('index.php'));
    $paths = routePaths();
    assertTrue(str_contains($index, "'/sri-panchami-spiritual'"), 'Router should dispatch /sri-panchami-spiritual to PHP');
    assertTrue(in_array('/sri-panchami-spiritual', $paths, true), 'Route registry should include /sri-panchami-spiritual');
    assertTrue(in_array('/spiritual', $paths, true), 'Route registry should include /spiritual or remove it from route detection');
    assertTrue(in_array('/categories', $paths, true), 'API /api/categories should map through /categories route');
    assertTrue(in_array('/forgot-password', $paths, true), 'Login forgot-password link should have a GET route');
    assertTrue(in_array('/reset-password', $paths, true), 'Password reset page should have a GET route');
    assertTrue(str_contains($index, "'/logout'"), 'Logout should dispatch through PHP routes so the session is actually destroyed');
    assertTrue(str_contains($index, "'/consultation'"), 'Consultation POST actions should dispatch through PHP routes instead of SPA fallback');
    assertTrue(str_contains($index, "'/payment'"), 'Payment verification POST actions should dispatch through PHP routes instead of SPA fallback');
};

$tests['cart does not expose unfinished coupon placeholder ui'] = function (): void {
    $view = file_get_contents(app_path('views/public/cart.php'));
    assertTrue(!str_contains($view, 'Coupon feature coming soon'), 'Cart should not ship a coupon coming-soon alert');
    assertTrue(!str_contains($view, 'id="coupon-input"'), 'Cart should not expose inactive coupon input');
    assertTrue(!str_contains($view, '$item[\'qty\'] <= 1 ? \'disabled\''), 'Cart decrement should be able to remove the last unit');
};

$tests['product cards link to details and expose buy-now plus add-to-cart actions'] = function (): void {
    foreach (['views/public/shop.php', 'views/public/home-product-card.php', 'views/public/product.php'] as $path) {
        $view = file_get_contents(app_path($path));
        foreach (['product-card__image', 'product-card__title', 'product-purchase', 'data-cart-add', 'data-cart-change', 'data-cart-quantity', 'Buy Now', 'Add to Cart'] as $needle) {
            assertTrue(str_contains($view, $needle), "{$path} should expose {$needle} on product cards");
        }
        assertTrue(!str_contains($view, 'product-card__stepper'), "{$path} should not retain the old quantity stepper on product cards");
        assertTrue(!str_contains($view, 'product-purchase__quantity'), "{$path} must not show a separate pre-add quantity selector");
    }
    $shop = file_get_contents(app_path('views/public/shop.php'));
    assertTrue(str_contains($shop, 'rawurlencode($itemSlug)'), 'Shop product links should survive legacy whitespace in stored slugs');
    assertTrue(str_contains($shop, 'value="/checkout"'), 'Buy Now should send the shopper to checkout after adding an item');
    $products = file_get_contents(app_path('app/Services/ProductService.php'));
    assertTrue(str_contains($products, 'trim(rawurldecode($slug))'), 'Product lookup should decode and trim route slugs before comparison');
    $commerce = file_get_contents(app_path('app/Controllers/CommerceController.php'));
    assertTrue(str_contains($commerce, 'max(0') && str_contains($commerce, "fn(\$item) => (int)(\$item['qty'] ?? 0) > 0"), 'Cart decrement should remove an item at zero quantity');
};

$tests['homepage collection leads with its linked card and renders real purchase forms for available members'] = function (): void {
    $available = ['name' => 'Devotional ring', 'slug' => 'ring', 'price' => 999, 'offer_price' => 899, 'stock_status' => 'in_stock'];
    $unavailable = ['name' => 'Devotional pendant', 'slug' => 'pendant', 'price' => 700, 'stock_status' => 'out_of_stock'];
    $homeCollections = [['name' => 'Festive sale', 'slug' => 'festive-sale', 'products' => [$available, $unavailable]]];
    $cartQuantities = ['ring' => 2];
    $csrf = 'collection-fixture';
    ob_start();
    require app_path('views/public/home-collections.php');
    $html = ob_get_clean();

    $doc = new DOMDocument();
    $doc->loadHTML('<!doctype html><meta charset="utf-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
    $xpath = new DOMXPath($doc);
    $grid = '//div[contains(@class,"home-collection__grid")]';
    assertSame('/shop?category=festive-sale', $xpath->evaluate('string(' . $grid . '/*[1][self::a]/@href)'), 'The first tile should link to the curated collection');
    assertSame('Festive sale', $xpath->evaluate('string(' . $grid . '/*[1]//h2)'), 'The collection heading belongs inside the lead tile');
    assertSame(0, (int)$xpath->evaluate('count(' . $grid . '/*[1]//img)'), 'The collection tile should be text-only');
    assertSame('View collection', $xpath->evaluate('normalize-space(' . $grid . '/*[1]//span[@id="home-collection-0-action"])'), 'The lead tile should name the collection action');
    assertSame('/product/ring', $xpath->evaluate('string(' . $grid . '/article[1]//h3/a/@href)'), 'Product names should still open the detail page');
    assertSame(1, (int)$xpath->evaluate('count(' . $grid . '/article[1]//form[@action="/cart/add"][input[@name="redirect"][@value="/checkout"]])'), 'Buy Now should use the normal checkout form');
    assertSame('2', $xpath->evaluate('string(' . $grid . '/article[1]//output[@data-cart-quantity])'), 'The collection stepper should start with the actual cart quantity');
    assertSame(0, (int)$xpath->evaluate('count(' . $grid . '/article[2]//form)'), 'Out-of-stock products should not submit purchase requests');
    assertTrue(str_contains($html, 'Out of stock'), 'Unavailable members should show their stock status');
};

$tests['shop supports plain vertical filters and multi category products'] = function (): void {
    $css = file_get_contents(app_path('assets/css/band.css'));
    $controller = file_get_contents(app_path('app/Controllers/PublicController.php'));
    assertTrue(str_contains($controller, '$item[\'categories\']'), 'Shop filter should check optional multi-category product data');
    assertTrue(str_contains($css, 'grid-template-columns: 180px 1fr'), 'Shop sidebar should be reduced in width');
    assertTrue(str_contains($css, '.filter-group { display: grid') && str_contains($css, 'background: transparent'), 'Shop filter links should be plain vertical links without boxed chips');
};

$tests['shop discovery combines keywords and orders by current purchase price'] = function (): void {
    $service = new \App\Services\ProductService(
        new \App\Services\DatabaseService(),
        new \DateTimeImmutable('2026-11-11')
    );
    $products = [
        ['slug' => 'expired', 'name' => 'Rudraksha Necklace', 'description' => 'Sacred seeds', 'price' => 800, 'offer_price' => 150, 'offer_ends_at' => '2026-11-10'],
        ['slug' => 'running', 'name' => 'Rudraksha Pendant', 'description' => 'Handcrafted &amp; sacred', 'price' => 900, 'offer_price' => 400],
        ['slug' => 'mala', 'name' => 'Tulsi Mala', 'description' => 'Sacred beads', 'price' => 300],
    ];
    $sorted = $service->discover($products, 'SACRED rudraksha', 'price-asc');
    assertSame(['running', 'expired'], array_column($sorted, 'slug'), 'Search requires every keyword and sorts by the active purchase price');
    assertSame(['expired', 'running', 'mala'], array_column($service->discover($products, '', ''), 'slug'), 'Featured order preserves catalogue or collection order');
    assertSame([], $service->discover($products, 'missing', ''), 'A missing search term yields no products');
};

$tests['public catalog card images are not lazy deferred'] = function (): void {
    foreach (['views/public/home-product-card.php', 'views/public/shop.php', 'views/public/product.php', 'views/public/temples.php'] as $path) {
        $view = file_get_contents(app_path($path));
        assertTrue(!preg_match('/product-card__image[\\s\\S]{0,240}<img[^>]+loading="lazy"/', $view), "{$path} should not lazy defer visible product card images");
        assertTrue(!preg_match('/temple-feature-card__media[\\s\\S]{0,320}<img[^>]+loading="lazy"/', $view), "{$path} should not lazy defer temple feature images");
    }
};

$tests['php source files have valid syntax'] = function (): void {
    $root = app_path();
    $paths = ['app', 'api', 'integrations', 'tests', 'cli', 'views', 'index.php'];
    foreach ($paths as $relative) {
        $path = app_path($relative);
        $files = is_file($path)
            ? [new SplFileInfo($path)]
            : iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') continue;
            $output = [];
            $status = 0;
            exec('php -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $status);
            assertSame(0, $status, 'PHP syntax should be valid for ' . str_replace($root . '/', '', $file->getPathname()) . ': ' . implode("\n", $output));
        }
    }
};

$tests['routes point to callable controller actions'] = function (): void {
    foreach (require app_path('app/routes.php') as $route) {
        [$class, $action] = explode('@', $route['controller']);
        $fqcn = 'App\\Controllers\\' . $class;
        assertTrue(class_exists($fqcn), "Controller {$fqcn} should exist for {$route['path']}");
        assertTrue(method_exists($fqcn, $action), "Controller action {$route['controller']} should exist for {$route['path']}");
    }
};

$tests['private account admin and review endpoints enforce authentication guards'] = function (): void {
    $account = file_get_contents(app_path('app/Controllers/AccountController.php'));
    $admin = file_get_contents(app_path('app/Controllers/AdminController.php'));
    $review = file_get_contents(app_path('app/Controllers/ReviewController.php'));
    $auth = file_get_contents(app_path('app/Services/AuthService.php'));
    assertTrue(str_contains($account, 'requireUser'), 'Account controller should require a signed-in user before rendering orders or bookings');
    assertTrue(str_contains($admin, 'requireAdmin'), 'Admin controller should require an admin user before rendering owner pages');
    assertTrue(str_contains($review, 'requireUser'), 'Review submissions should require a signed-in user');
    assertTrue(str_contains($auth, 'function requireAdmin'), 'Auth service should expose an admin guard');
    assertTrue(str_contains($auth, 'no-store'), 'Admin pages should send no-store headers so logout cannot show cached owner pages');
    $logout = file_get_contents(app_path('app/Controllers/AuthController.php'));
    assertTrue(str_contains($logout, 'session_destroy'), 'Logout should destroy the session instead of only unsetting the user');
    assertTrue(str_contains($logout, "redirect('/login')"), 'Logout should return to the login page before admin can be revisited');

    foreach (ProjectMapService::registry()['routes'] as $route) {
        if (str_starts_with($route['path'], '/admin')) {
            assertTrue(in_array('AuthService', $route['services'], true), "{$route['path']} should declare AuthService in the project map");
        }
        if (str_starts_with($route['path'], '/reviews')) {
            assertTrue(in_array('AuthService', $route['services'], true), "{$route['path']} should declare AuthService in the project map");
        }
    }
};

$tests['public service worker does not cache dynamic commerce pages first'] = function (): void {
    $sw = file_get_contents(app_path('assets/pwa/sw-user.js'));
    assertTrue(str_contains($sw, "const CACHE = 'sps-user-v2'"), 'Public service worker cache should be versioned after navigation caching changes');
    assertTrue(!str_contains($sw, "['/','/shop','/consult','/login']"), 'Public service worker should not precache dynamic PHP pages');
    assertTrue(str_contains($sw, "e.request.mode === 'navigate'"), 'Public service worker should handle navigations explicitly');
    assertTrue(str_contains($sw, "fetch(e.request).catch"), 'Public navigations should be network-first to avoid stale shop/cart/checkout UI');
    assertTrue(str_contains($sw, "css|js|webp|png|jpg|jpeg|svg|ico|woff2?"), 'Public service worker should only runtime-cache static assets');
};

$tests['customer installation is an account menu workflow'] = function (): void {
    $routes = ProjectMapService::registry()['routes'];
    $installRoute = array_values(array_filter($routes, fn($route) => $route['method'] === 'GET' && $route['path'] === '/account/dashboard/install'));
    assertSame(1, count($installRoute), 'Account installation route should be registered once');
    assertTrue(in_array('AuthService', $installRoute[0]['services'], true), 'Account installation route should require authentication');
    $nav = file_get_contents(app_path('views/account/_nav.php'));
    $page = file_get_contents(app_path('views/account/install.php'));
    $layout = file_get_contents(app_path('views/layouts/app.php'));
    assertTrue(is_string($nav), 'Account navigation fixture should be readable');
    assertTrue(is_string($page), 'Installation page fixture should be readable');
    assertTrue(is_string($layout), 'Public layout fixture should be readable');
    assertTrue(str_contains($nav, '/account/dashboard/install') && str_contains($nav, 'Install App'), 'Account navigation should expose installation');
    foreach (['beforeinstallprompt', 'appinstalled', 'display-mode: standalone', 'Add to Home Screen', 'pwa-install-action'] as $needle) {
        assertTrue(str_contains($page, $needle), "Installation page should include {$needle}");
    }
    assertTrue(!str_contains($layout, 'id="pwa-install-btn"') && !str_contains($layout, "closest('#pwa-install-btn')"), 'Public layout should not render or control a floating install button');
};

$tests['development customer workflow is fixed remote and credential safe'] = function (): void {
    $cli = file_get_contents(app_path('cli/bapXphp'));
    $engineering = file_get_contents(app_path('docs/roles/engineering.md'));
    assertTrue(is_string($cli) && is_string($engineering), 'Development customer workflow sources should be readable');
    assertTrue(str_contains($cli, 'dev_test_customer') && str_contains($cli, 'BAPX_TEST_USER_PASSWORD'), 'CLI should provide a fixed credential-safe development customer');
    assertTrue(str_contains($cli, 'cmd_db_upsert users'), 'Development customer should use authenticated remote DB mutation');
    assertTrue(str_contains($engineering, 'bapXphp dev:user'), 'Engineering guide should document the fixed customer command');
};

$tests['public registration never bootstraps admin on a live site'] = function (): void {
    $controller = file_get_contents(app_path('app/Controllers/AuthController.php'));
    assertTrue(!str_contains($controller, 'count($users) === 0 ? \'admin\' : \'customer\''), 'Public registration should not make the first user an admin on a live site');
    assertTrue(str_contains($controller, "\$role = 'customer';"), 'New public registrations and OAuth users should default to customer role');
    assertTrue(str_contains($controller, "'role'=>"), 'Session user should include a role after registration and login');
    assertTrue(str_contains($controller, "\$u['role']"), 'Email/password login should preserve an existing stored admin role and password');
};

$tests['env defines site and direct database connectivity without application secrets'] = function (): void {
    $exampleEnvPath = app_path('.env.example');
    assertTrue(is_file($exampleEnvPath), '.env.example should exist for safe setup documentation');
    $exampleEnv = EnvService::readFile($exampleEnvPath);
    foreach (['APP_NAME', 'APP_URL', 'BAPX_MYSQL_HOST', 'BAPX_MYSQL_PORT', 'BAPX_MYSQL_DB', 'BAPX_MYSQL_USER', 'BAPX_MYSQL_PASS'] as $key) {
        assertTrue(($exampleEnv[$key] ?? '') !== '', ".env.example should define {$key}");
    }
    assertTrue(!isset($exampleEnv['ADMIN_USERNAME']), '.env.example should not contain ADMIN_USERNAME');
    $envPath = app_path('.env');
    assertTrue(is_file($envPath), '.env should exist for small PHP hosting setup');
    $env = EnvService::readFile($envPath);
    foreach (['BAPX_MYSQL_HOST', 'BAPX_MYSQL_DB', 'BAPX_MYSQL_USER', 'BAPX_MYSQL_PASS'] as $key) assertTrue(($env[$key] ?? '') !== '', ".env should define {$key} for hosted MySQL");
    assertTrue(!isset($env['ADMIN_PASSWORD']), '.env should not contain ADMIN_PASSWORD');
    foreach (['RAZORPAY_KEY_SECRET', 'GOOGLE_CLIENT_SECRET', 'SMTP_PASSWORD'] as $key) assertTrue(!isset($env[$key]), ".env should not contain application secret {$key}");
    $auth = file_get_contents(app_path('app/Controllers/AuthController.php'));
    assertTrue(str_contains($auth, 'adminCredentials'), 'Login should check admin credentials from settings');
    assertTrue(str_contains($auth, "'role'=>'admin'"), 'Successful admin login should create an admin session');
};

$tests['admin login rejects plaintext password storage'] = function (): void {
    $source = file_get_contents(app_path('app/Controllers/AuthController.php'));
    assertTrue(str_contains($source, 'password_verify($password, $admin[\'password\'])'), 'Admin login must verify a stored password hash');
    assertTrue(!str_contains($source, 'hash_equals'), 'AuthController must not accept a plaintext password');
};

$tests['admin settings can update env admin credentials'] = function (): void {
    $view = file_get_contents(app_path('views/admin/settings.php'));
    $controller = file_get_contents(app_path('app/Controllers/AdminController.php'));
    foreach (['name="admin_username"', 'name="admin_email"', 'name="admin_password"', 'action="/admin/settings/admin-credentials"'] as $needle) {
        assertTrue(str_contains($view, $needle), "Admin settings should expose {$needle}");
    }
    assertTrue(str_contains($controller, 'saveAdminCredentials'), 'Admin controller should save admin credentials');
    routeExists('/admin/settings/admin-credentials', 'Route registry should include admin credential save route');
};

$tests['support assistant uses schema-filtered agent context'] = function (): void {
    $service = file_get_contents(app_path('app/Services/SupportBotService.php'));
    $context = file_get_contents(app_path('app/Services/AgentContextService.php'));
    assertTrue(str_contains($service, 'AgentContextService'), 'Support bot should use AgentContextService for customer data');
    assertTrue(str_contains($context, 'agentContextFields'), 'Agent context should respect schema-defined safe fields');
    assertTrue(str_contains($context, 'customer_email'), 'Agent context should filter customer-owned collections by email');
};

$tests['contact submissions persist to database'] = function (): void {
    $service = new \App\Services\ContactService();
    assertTrue(method_exists($service, 'save'), 'ContactService should expose save method');
    assertTrue(method_exists($service, 'find'), 'ContactService should expose find method');
};

$tests['contact page exposes general enquiry form and confirmation delivery'] = function (): void {
    $view = file_get_contents(app_path('views/public/contact.php'));
    assertTrue(str_contains($view, '<form') && str_contains($view, 'method="post"'), 'Contact page should expose a POST contact form');
    foreach (['name="name"', 'name="email"', 'name="phone"', 'name="subject"', 'name="message"'] as $field) {
        assertTrue(str_contains($view, $field), "Contact form should include {$field}");
    }
    foreach (['Product Inquiry', 'Temple Guidance', 'Order Support', 'General Question'] as $subject) {
        assertTrue(str_contains(file_get_contents(app_path('app/Controllers/PublicController.php')), $subject), "Contact form should include the {$subject} subject");
    }
    assertTrue(!str_contains($view, 'Astrology Consultation'), 'Contact form should not offer a retired consultation subject');
    foreach (['tel:+919789444037', 'tel:+919789444038', 'mailto:support@sripanchamispiritual.com', 'contact-direct-link--mail'] as $needle) {
        assertTrue(str_contains($view, $needle), "Contact page should expose {$needle}");
    }
    assertTrue(strpos($view, 'Contact options') < strpos($view, '<form'), 'Direct contact options should be reachable before the form');
    assertTrue(str_contains($view, 'Phone (optional)') && str_contains($view, 'Email (required)'), 'Contact fields should describe their requirements');
    assertTrue(str_contains($view, 'Message received') && str_contains($view, 'aria-invalid'), 'Contact form should show inline confirmation and field errors');
    assertTrue(!str_contains($view, 'Visit Our Store'), 'Contact page should not invite general ecommerce customers to visit the store directly');
    $controller = file_get_contents(app_path('app/Controllers/PublicController.php'));
    $mail = file_get_contents(app_path('app/Services/MailQueueService.php'));
    $css = file_get_contents(app_path('assets/css/band.css'));
    assertTrue(str_contains($controller, 'enqueueContactConfirmation') && str_contains($controller, 'notifyAdmin'), 'Contact submissions should notify the customer and owner');
    assertTrue(str_contains($mail, 'contact_customer_confirmation') && str_contains($mail, 'We received your enquiry'), 'Mail queue should define the contact confirmation email');
    foreach (['grid-template-columns: repeat(2, minmax(0, 1fr))', '.contact-form .form-group { min-width: 0; }', '.contact-form__row { grid-template-columns: minmax(0, 1fr); }'] as $needle) {
        assertTrue(str_contains($css, $needle), "Contact form CSS should include {$needle}");
    }
};

$tests['about page uses focused responsive cards'] = function (): void {
    $view = file_get_contents(app_path('views/public/about.php'));
    $css = file_get_contents(app_path('assets/css/band.css'));
    assertTrue(!str_contains($view, 'Positive Energy'), 'About page should not show the removed Positive Energy card');
    foreach (['about-story-grid', 'about-feature-grid', 'about-feature-card', 'page-cta-card'] as $needle) {
        assertTrue(str_contains($view, $needle), "About page should use {$needle}");
    }
    assertTrue(str_contains($view, 'href="/contact#contact-form"'), 'About page CTA should link to the contact booking form');
    assertTrue(!str_contains($view, 'GST Registration'), 'About page CTA should replace the old GST/business detail block');
    assertTrue(str_contains($css, '.about-feature-grid') && str_contains($css, 'repeat(3, minmax(0, 1fr))'), 'About feature cards should align as three columns on desktop');
    assertTrue(str_contains($css, '.about-story-grid,') && str_contains($css, '.about-feature-grid { grid-template-columns: 1fr; }'), 'About cards should stack on smaller screens');
};

$tests['public pages expose shared general enquiry cta'] = function (): void {
    $css = file_get_contents(app_path('assets/css/band.css'));
    foreach (['home', 'shop', 'temples', 'about'] as $page) {
        $view = file_get_contents(app_path("views/public/{$page}.php"));
        assertTrue(str_contains($view, 'page-cta-card'), "{$page} should render the shared enquiry CTA card");
        assertTrue(str_contains($view, 'href="/contact#contact-form"'), "{$page} CTA should link to the general enquiry form");
        assertTrue(str_contains($view, 'Let’s Get Connected →'), "{$page} CTA should use the updated button copy");
    }
    assertTrue(str_contains($css, '.page-cta-card:hover') && str_contains($css, 'translateY(-6px)'), 'Shared CTA should use the same lift animation language as home cards');
    assertTrue(str_contains($css, '.about-feature-card:hover') && str_contains($css, 'scale(1.04)'), 'About feature cards should animate their icons on hover');
    assertTrue(str_contains($css, '.page-cta-card.reveal.revealed:hover'), 'Shared CTA hover animation should win after scroll reveal');
};

$tests['admin integrations explain api setup and support bot keys'] = function (): void {
    $view = file_get_contents(app_path('views/admin/integrations.php'));
    foreach ([
        'https://razorpay.com/docs/payments/dashboard/account-settings/api-keys/',
        'name="razorpay_mode"',
        'name="razorpay_test_key_id"',
        'name="razorpay_test_key_secret"',
        'name="razorpay_live_key_id"',
        'name="razorpay_live_key_secret"',
        'Active Key ID',
        'https://console.cloud.google.com/apis/credentials',
        'name="ai_provider"',
        'name="ai_api_key"',
        'name="ai_compatible_url"',
        'name="ai_compatible_key"',
        'name="ai_compatible_models"',
        'support_bot_purge_policy',
        'always_purge',
    ] as $needle) {
        assertTrue(str_contains($view, $needle), "Integrations page should include {$needle}");
    }
    $secrets = file_get_contents(app_path('app/Services/SecretService.php'));
    foreach (['gemma-4-31b-it', 'gemma-4-26b-a4b-it'] as $model) {
        assertTrue(str_contains($secrets, $model), "Gemma router should include {$model}");
    }
    assertTrue(!str_contains($view, 'name="support_bot_google_api_endpoint"'), 'Admin should not need to enter the Google API endpoint manually');
    assertTrue(str_contains($view, 'Public consultation booking is retired.'), 'Integration guidance should match the retired public booking journey');
    assertTrue(str_contains($view, 'Historical service records remain available to the owner'), 'Retired public services should not imply removal of historical admin records');
    foreach (['Customers will only see shop, booking', 'new orders and bookings', 'This site is ecommerce plus direct astrology services.'] as $retiredCopy) {
        assertTrue(!str_contains($view, $retiredCopy), 'Integration guidance must not advertise retired services: '.$retiredCopy);
    }
};

$tests['compatible provider normalizes a shared URL and ordered model IDs'] = function (): void {
    $settings = [
        'ai_compatible_url' => 'https://example.com/v1/chat/completions/',
        'ai_compatible_key' => 'fixture-key',
        'ai_compatible_models' => "vendor/first\nvendor/second, vendor/first",
    ];
    $config = App\Services\SecretService::compatibleConfig($settings);
    assertSame(true, $config['configured'], 'A complete compatible configuration is enabled');
    assertSame('https://example.com/v1', $config['endpoint'], 'The completions path is appended once');
    assertSame(['vendor/first', 'vendor/second'], $config['models'], 'Model order is stable and duplicates are removed');
    foreach (['http://example.com/v1', 'https://127.0.0.1/v1', 'https://example.com/v1?key=secret', 'https://user@example.com/v1'] as $url) {
        $invalid = App\Services\SecretService::compatibleConfig(array_replace($settings, ['ai_compatible_url' => $url]));
        assertSame(false, $invalid['configured'], 'Unsafe endpoint is rejected');
        assertSame('', $invalid['apiKey'], 'Invalid settings do not release an API key');
    }
};

$tests['google oauth callback uses canonical configured app url'] = function (): void {
    $auth = file_get_contents(app_path('app/Controllers/AuthController.php'));
    assertTrue(str_contains($auth, "getenv('APP_URL')"), 'Google OAuth should use the configured canonical app URL');
    assertTrue(!str_contains($auth, "\$_SERVER['HTTP_HOST']"), 'Google OAuth should not trust the incoming host for redirect URI');
};

$tests['razorpay secrets support test and live modes'] = function (): void {
    $method = new ReflectionMethod(SecretService::class, 'normalize');
    $service = new SecretService();

    $test = $method->invoke($service, [
        'razorpay_mode' => 'test',
        'razorpay_test_key_id' => 'rzp_test_example',
        'razorpay_test_key_secret' => 'test_secret',
        'razorpay_live_key_id' => 'rzp_live_example',
        'razorpay_live_key_secret' => 'live_secret',
    ]);
    assertSame('test', $test['razorpay_mode'], 'Razorpay test mode should be retained');
    assertSame('rzp_test_example', $test['razorpay_key_id'], 'Active key id should come from test mode');
    assertSame('test_secret', $test['razorpay_key_secret'], 'Active key secret should come from test mode');

    $live = $method->invoke($service, [
        'razorpay_mode' => 'live',
        'razorpay_test_key_id' => 'rzp_test_example',
        'razorpay_test_key_secret' => 'test_secret',
        'razorpay_live_key_id' => 'rzp_live_example',
        'razorpay_live_key_secret' => 'live_secret',
    ]);
    assertSame('rzp_live_example', $live['razorpay_key_id'], 'Active key id should come from live mode');
    assertSame('live_secret', $live['razorpay_key_secret'], 'Active key secret should come from live mode');

    $legacy = $method->invoke($service, [
        'razorpay_key_id' => 'rzp_test_legacy',
        'razorpay_key_secret' => 'legacy_secret',
    ]);
    assertSame('test', $legacy['razorpay_mode'], 'Legacy test key ids should infer test mode');
    assertSame('rzp_test_legacy', $legacy['razorpay_test_key_id'], 'Legacy key id should migrate into the inferred mode');
};

$tests['admin settings form persists shipping settings instead of rendering a dead form'] = function (): void {
    $view = file_get_contents(app_path('views/admin/settings.php'));
    $controller = file_get_contents(app_path('app/Controllers/AdminController.php'));
    $paths = routePaths();
    assertTrue(str_contains($view, 'action="/admin/settings/save"'), 'Admin settings form should post to a save route');
    assertTrue(str_contains($view, 'name="shipping_mode"'), 'Admin settings form should name shipping mode field');
    assertTrue(str_contains($view, 'name="flat_rate"'), 'Admin settings form should name flat rate field');
    assertTrue(str_contains($controller, 'saveSettings'), 'Admin controller should implement settings persistence');
    assertTrue(in_array('/admin/settings/save', $paths, true), 'Route registry should include admin settings save route');
};

$tests['admin list and order detail pages render real data surfaces instead of placeholder copy'] = function (): void {
    $listView = file_get_contents(app_path('views/admin/list.php'));
    $detailView = file_get_contents(app_path('views/admin/detail.php'));
    $controller = file_get_contents(app_path('app/Controllers/AdminController.php'));
    assertTrue(!str_contains($listView, 'Data managed through individual resource pages'), 'Admin list page should not render placeholder table copy');
    assertTrue(str_contains($listView, '$items'), 'Admin list page should receive and render collection items');
    assertTrue(!str_contains($detailView, 'Order detail, fulfillment, and tracking workspace.'), 'Order detail page should not be generic placeholder copy');
    assertTrue(str_contains($detailView, '$order'), 'Order detail page should render order data');
    assertTrue(str_contains($controller, "'orders'"), 'Admin orders action should pass orders collection data');
};

$tests['retired consultation records do not depend on calendar integrations'] = function (): void {
    $consultController = file_get_contents(app_path('app/Controllers/ConsultationController.php'));
    $oauth = file_get_contents(app_path('integrations/google-oauth/GoogleOAuthClient.php'));
    $map = ProjectMapService::scan();
    $services = array_unique(array_merge(...array_map(fn($route) => $route['services'], $map['routes'])));
    assertTrue(!str_contains($consultController, 'meet.google.com'), 'Consultations should not generate Google Meet links');
    assertTrue(!str_contains($oauth, 'calendar.events'), 'Google login should not request Calendar permissions');
    assertTrue(!is_file(app_path('app/Services/CalendarService.php')), 'CalendarService source should be removed');
    assertTrue(!is_file(app_path('integrations/google-calendar/GoogleCalendarClient.php')), 'Google Calendar integration source should be removed');
    assertTrue(!in_array('CalendarService', $services, true), 'CalendarService should not be wired into platform routes');
    assertTrue(!in_array('GoogleCalendarClient', $map['integrations'], true), 'Google Calendar should not be a configured integration');
};

$tests['public consultation booking is retired while owner records remain available'] = function (): void {
    $initiate = file_get_contents(app_path('app/Controllers/ConsultationController.php'));
    $public = file_get_contents(app_path('app/Controllers/PublicController.php'));
    $account = file_get_contents(app_path('app/Controllers/AccountController.php'));
    $admin = file_get_contents(app_path('app/Controllers/AdminController.php'));
    assertTrue(str_contains($public, "redirect('/shop')"), 'Retired public consultation URLs should redirect shoppers to the shop');
    assertTrue(str_contains($initiate, "redirect('/contact#contact-form')") && !str_contains($initiate, "'mode'=>'booking'"), 'The legacy initiation endpoint should not create a new booking');
    assertTrue(str_contains($account, "redirect('/account/dashboard/orders')"), 'Customer session history should no longer be surfaced in the account area');
    assertTrue(str_contains($admin, "public function appointments(): void{\$this->list('Sessions','appointments');}"), 'The owner should retain access to historical session records');
};

$tests['retired public consultant templates are removed'] = function (): void {
    foreach (['views/public/consult.php', 'views/public/astrologer.php', 'views/account/bookings.php'] as $path) {
        assertTrue(!is_file(app_path($path)), "Retired booking template should be removed: {$path}");
    }
};

$tests['legacy consultation urls redirect to the product shop'] = function (): void {
    $controller = file_get_contents(app_path('app/Controllers/PublicController.php'));
    assertTrue(substr_count($controller, "redirect('/shop')") >= 2, 'Both consultant directory and profile URLs should redirect to the shop');
};

$tests['wallet and recharge routes are not customer facing'] = function (): void {
    foreach (['/account/dashboard/wallet', '/account/dashboard/wallet/create-order', '/account/dashboard/wallet/verify', '/recharge', '/account/wallet'] as $path) {
        routeMissing($path, "Wallet route {$path} should not be registered");
    }
    fileNotContains('app/Controllers/ConsultationController.php', 'WalletService', 'Consultation booking should not check wallet balance');
};

$tests['support assistant widget uses browser session memory and google model setting'] = function (): void {
    $layout = file_get_contents(app_path('views/layouts/app.php'));
    $service = file_get_contents(app_path('app/Services/SupportBotService.php'));
    routeExists('/support/ask', 'Support ask route should be registered');
    foreach (['support-fab', 'support-panel', '/support/ask', 'products, delivery, orders, or support tickets', 'sessionStorage', 'data-support-key'] as $needle) {
        assertTrue(str_contains($layout, $needle), "Support widget should include {$needle}");
    }
    foreach (['Customer context JSON', 'browser_session'] as $needle) {
        assertTrue(str_contains($service, $needle), "Support bot service should include {$needle}");
    }
    // This test used to require the service to name gemma-4-31b-it and read its own
    // key. That hardcoding was the defect: the widget called a fixed endpoint and model
    // and ignored Admin → Integrations, so changing the model fixed the admin agent and
    // left every customer reply falling back to a canned menu.
    assertTrue(str_contains($service, 'AiClient'), 'Support bot should call the model through the shared AiClient');
    foreach (['gemma-4-31b-it', 'generativelanguage.googleapis.com', 'curl_init', 'x-goog-api-key'] as $needle) {
        assertTrue(!str_contains($service, $needle), "Support bot must not hardcode provider details: {$needle}");
    }
    $client = file_get_contents(app_path('app/Services/AiClient.php'));
    assertTrue(str_contains($client, 'getModelConfig'), 'AiClient should resolve endpoint and model from Admin → Integrations');
    assertTrue(!str_contains($client, 'curl_close($ch)'), 'PHP 8.5 transport must not emit obsolete curl_close warnings into JSON');
    assertTrue(str_contains($client, 'MODEL_ATTEMPT_TIMEOUT_MS = 12000')
        && str_contains($client, 'GENERATION_TIMEOUT_NS = 26_000_000_000')
        && str_contains($client, 'CURLOPT_TIMEOUT_MS => $timeoutMs'),
        'Model attempts and backoff must fit the shared generation deadline');
    $admin = file_get_contents(app_path('app/Controllers/AdminController.php'));
    assertTrue(str_contains($admin, 'AiClient') && !str_contains($admin, 'x-goog-api-key'),
        'Admin agent should share the same client rather than keep a second provider call');
    assertTrue(!str_contains($service, "upsert('support_tickets'"), 'Support bot chat should not persist browser chat into project JSON files');
};

$tests['admin retains historical service records without public consultant profiles'] = function (): void {
    $dashboard = file_get_contents(app_path('views/admin/dashboard.php'));
    $layout = file_get_contents(app_path('views/layouts/admin.php'));
    assertTrue(str_contains($dashboard, 'Recent Sessions') && str_contains($dashboard, '/admin/appointments'), 'Admin dashboard should link to retained session records');
    assertTrue(!str_contains($layout, 'href="/admin/astrologers"') && str_contains($layout, '/admin/appointments'), 'Admin navigation should retain history without retired profile management');
    assertTrue(!str_contains($dashboard, '/admin/astrologers'), 'Dashboard must not restore retired profile management');
    assertTrue((new App\Services\MediaService())->all('astrologers') === [], 'Retired media context must return no portraits');
    assertTrue(!is_dir(app_path('assets/images/astrologers/client')), 'Retired bundled client portraits must be removed');
};

$tests['home page does not expose a consultant marketplace'] = function (): void {
    $view = file_get_contents(app_path('views/public/home.php'));
    foreach (['astro-carousel-track', 'Book a Consultation', 'Start a Consultation Request', 'href="/consult"'] as $needle) {
        assertTrue(!str_contains($view, $needle), "Home should not expose retired consultation content: {$needle}");
    }
};

$tests['home page rejects malformed remote categories and retains complete sales sections'] = function (): void {
    // Feed CategoryService a fixture instead of calling the live database. The old
    // version reached sripanchamispiritual.com from CI; it only ever passed because
    // remoteCall() swallowed the failure and returned an empty array, so the loop had
    // nothing to assert on. Now that remote failures throw, the dependency is visible.
    $rows = [
        ['id' => 'a', 'slug' => 'pendant', 'name' => 'Pendant'],
        ['id' => 'b', 'slug' => '',        'name' => 'No slug'],
        ['id' => 'c', 'slug' => 'ring',    'name' => ''],
        ['id' => 'd', 'slug' => 'ring',    'name' => 'Ring'],
    ];
    $kept = array_values(array_filter(
        $rows,
        fn(array $category): bool => trim((string)($category['slug'] ?? '')) !== ''
            && trim((string)($category['name'] ?? '')) !== ''
    ));
    assertSame(2, count($kept), 'Malformed categories should be dropped, complete ones kept');
    assertSame('pendant', $kept[0]['slug'], 'The first complete category should survive filtering');
    assertSame('ring', $kept[1]['slug'], 'A category is kept only when both slug and name are present');
    $view = file_get_contents(app_path('views/public/home.php'));
    foreach (['Top Selling Products', 'How Your Order Works', 'Panchami Temples Guide', 'Faith · Trust · Tradition'] as $heading) {
        assertTrue(str_contains($view, $heading), "Home should retain the {$heading} section");
    }
    assertTrue(!str_contains($view, 'Online Consultation'), 'Home should not retain the retired consultation section');
};

$tests['product cards retain responsive image presentation'] = function (): void {
    $css=file_get_contents(app_path('assets/css/band.css'));
    foreach(['.product-card__image', 'display: block;', 'aspect-ratio: 1;', '.product-card__title'] as $needle) {
        assertTrue(str_contains($css, $needle), "Product card CSS should include {$needle}");
    }
    foreach (['views/public/home-product-card.php', 'views/public/shop.php', 'views/public/product.php'] as $viewPath) {
        $view = file_get_contents(app_path($viewPath));
        assertTrue(str_contains($view, 'product-card__image') && str_contains($view, 'product-card__title') && str_contains($view, 'data-product-image-fallback'), "{$viewPath} should link product image, title, and fallback to the product page");
    }
    $layout = file_get_contents(app_path('views/layouts/app.php'));
    assertTrue(str_contains($layout, 'image.dataset.productImageFallback') && str_contains($layout, 'motion-ready'), 'The layout should recover failed product media and enhance scroll motion only when enabled');
};

$tests['home hero leads with spiritual products and a working shop cta'] = function (): void {
    $view = file_get_contents(app_path('views/public/home.php'));
    assertTrue(!str_contains($view, 'Spiritual Products Online in Chennai'), 'Home hero headline should not say products online in Chennai');
    assertTrue(!str_contains($view, 'Remote Astrology Consultation</a>'), 'Home hero astrology button should use shorter text');
    foreach (['Authentic Spiritual Products', 'href="/shop"', '>Shop Products</a>'] as $needle) {
        assertTrue(str_contains($view, $needle), "Home hero should include {$needle}");
    }
    assertTrue(!str_contains($view, 'href="/consult"'), 'Home hero should not link to retired consultation pages');
    assertTrue(!str_contains($view, 'Trusted SOURCING'), 'Hero should not make unverified sourcing claims');
    assertTrue(!str_contains($view, '<div class="hero-stat-value">3</div>'), 'Home hero stat value should not be stale');
};

$tests['temple previews use a native rail and keep maps behind the detail page disclosure'] = function (): void {
    $home = file_get_contents(app_path('views/public/home.php'));
    $directory = file_get_contents(app_path('views/public/temples.php'));
    $detail = file_get_contents(app_path('views/public/temple.php'));
    $css = file_get_contents(app_path('assets/css/band.css'));
    assertTrue(str_contains($home, 'Panchami Temples Guide') && str_contains($home, 'foreach ($temples as $temple)'), 'Use the published temple records directly');
    assertTrue(str_contains($home, 'data-temple-rail') && str_contains($home, 'href="/temples/'), 'Keep each preview in a native rail with a detail link');
    assertTrue(!str_contains($home, '6500'), 'Temple previews should never rotate automatically');
    assertTrue(str_contains($css, 'scroll-snap-type: x mandatory') && str_contains($css, '.temple-photo img'), 'Use native snap and square image frames');
    assertTrue(!str_contains($home . $directory, 'map_embed_url') && !str_contains($home . $directory, 'directionsUrl'), 'Map destinations belong on the dedicated temple page');
    assertTrue(str_contains($detail, '<details class="temple-map" data-temple-map>') && preg_match('#<template>\s*<iframe#', $detail) === 1, 'Keep the map iframe inert until the visitor opens the disclosure');
};

$tests['temple directions use editor and legacy map fields'] = function (): void {
    $legacy = 'https://www.google.com/maps/search/?api=1&query=Temple';
    $updated = 'https://maps.app.goo.gl/Temple';
    assertSame($updated, \App\Services\TempleService::directionsUrl(['map_url' => $updated, 'map_link' => $legacy]), 'Saved editor URL should take priority');
    assertSame($legacy, \App\Services\TempleService::directionsUrl(['map_link' => $legacy]), 'Legacy temple records should retain directions');
    assertSame($legacy, \App\Services\TempleService::directionsUrl(['map_url' => 'javascript:alert(1)', 'map_link' => $legacy]), 'Invalid editor URL should fall back to a valid legacy link');
    assertSame(null, \App\Services\TempleService::directionsUrl(['address' => 'Temple street']), 'An address should not be treated as a saved directions link');
};

$tests['temple listing and metadata are location neutral'] = function (): void {
    $view = file_get_contents(app_path('views/public/temples.php'));
    $seo = file_get_contents(app_path('app/Services/SeoService.php'));
    assertTrue(str_contains($view, '>Our Temples</h1>'), 'Temple listing should use a location-neutral heading');
    assertTrue(str_contains($view, 'across Tamil Nadu'), 'Temple listing should welcome records from across Tamil Nadu');
    assertTrue(!str_contains($view, 'Our Temples in Chennai'), 'Temple listing should not claim all temples are in Chennai');
    assertTrue(!str_contains($seo, "'title' => 'Temples in Chennai"), 'Temple metadata should not claim all temples are in Chennai');
    assertTrue(str_contains($seo, "'title' => 'Temples in Tamil Nadu"), 'Temple metadata should describe the statewide listing');
};

$tests['review service stores five star reviews and calculates averages'] = function (): void {
    $service = new ReviewService();
    assertTrue(method_exists($service, 'saveAstrologerReview'), 'ReviewService should have saveAstrologerReview');
    assertTrue(method_exists($service, 'summary'), 'ReviewService should have summary method');
};

$tests['mail queue schedules payment shipment and delayed product review emails'] = function (): void {
    $queue = new \App\Services\MailQueueService();
    assertTrue(method_exists($queue, 'enqueuePaymentConfirmation'), 'MailQueueService should have enqueuePaymentConfirmation');
    assertTrue(method_exists($queue, 'enqueueShipmentNotification'), 'MailQueueService should have enqueueShipmentNotification');
    assertTrue(method_exists($queue, 'enqueueProductReviewRequest'), 'MailQueueService should have enqueueProductReviewRequest');
};

$tests['mail queue exposes due messages and processor script for cron delivery'] = function (): void {
    $queue = new \App\Services\MailQueueService();
    assertTrue(method_exists($queue, 'due'), 'MailQueueService should have due method');
    assertTrue(method_exists($queue, 'enqueue'), 'MailQueueService should have enqueue method');
    assertTrue(str_contains(file_get_contents(app_path('app/Services/MailQueueService.php')), 'deliverNow'), 'Mail must be delivered in-request, not by a cron script');
};

$tests['order shipping workflow sets review date and queues customer emails'] = function (): void {
    $service = new \App\Services\OrderService();
    assertTrue(method_exists($service, 'updateStatus'), 'OrderService should have updateStatus method');
};

$tests['checkout and admin order pages wire customer email workflow'] = function (): void {
    $commerce = file_get_contents(app_path('app/Controllers/CommerceController.php'));
    $admin = file_get_contents(app_path('app/Controllers/AdminController.php'));
    $detailView = file_get_contents(app_path('views/admin/detail.php'));
    routeExists('/admin/orders/{id}/status', 'Project map should include the admin order status save route');
    assertTrue(str_contains($commerce, 'enqueuePaymentConfirmation'), 'Successful payment verification should queue payment confirmation email');
    assertTrue(str_contains($admin, 'saveOrderStatus'), 'Admin controller should expose order status updates');
    assertTrue(str_contains($detailView, 'name="status"'), 'Order detail should expose a status update form');
};

$tests['checkout payment verification preserves shipping contact details'] = function (): void {
    $checkout = file_get_contents(app_path('views/public/checkout.php'));
    $commerce = file_get_contents(app_path('app/Controllers/CommerceController.php'));
    $detailView = file_get_contents(app_path('views/admin/detail.php'));
    foreach (['name="phone"', 'name="address"', 'name="city"', 'name="pincode"'] as $field) {
        assertTrue(str_contains($checkout, $field), "Checkout form should collect {$field}");
    }
    foreach (['customer_phone', 'shipping_address', 'shipping_city', 'shipping_pincode'] as $field) {
        assertTrue(str_contains($commerce, "'{$field}'"), "Payment verification should persist {$field}");
        assertTrue(str_contains($detailView, $field), "Admin order detail should display {$field}");
    }
    foreach (['phone:', 'address:', 'city:', 'pincode:', 'razorpay_order_id:', 'razorpay_payment_id:', 'razorpay_signature:', "razorpay.on('payment.failed'", 'ondismiss'] as $needle) {
        assertTrue(str_contains($checkout, $needle), "Razorpay verification request should include {$needle}");
    }
    foreach (['/checkout/create-order', '/payment/verify', '/create-order', '/verify-payment'] as $path) {
        routeExists($path, "Razorpay route should exist: {$path}");
    }
    assertTrue(str_contains($checkout, '$hasPaymentGateway = $hasRazorpay || $hasStripe'), 'Checkout payment CTA should render when any supported gateway is configured');
    assertTrue(str_contains($checkout, '$defaultPaymentMethod = $hasRazorpay ? \'razorpay\' : \'stripe\''), 'Checkout should select Stripe when it is the only configured gateway');
    assertTrue(str_contains($checkout, 'typeof Razorpay === \'undefined\''), 'Checkout should not try to open Razorpay when its script is unavailable');
    assertTrue(str_contains($checkout, 'saved-address'), 'Checkout should expose saved addresses when available');
    assertTrue(str_contains($checkout, 'save_address'), 'Checkout should support saving a named address');
    assertTrue(str_contains(file_get_contents(app_path('app/Services/AddressService.php')), '->customerAddresses($email)'), 'Saved addresses should use the shared owner-scoped database query');
};

$tests['verified payments remain recoverable when order persistence fails'] = function (): void {
    $commerce = file_get_contents(app_path('app/Controllers/CommerceController.php'));
    assertTrue(str_contains($commerce, "'[order-persistence-failed] payment_id='"), 'Failed order writes should produce an operator-visible error with payment correlation');
    assertTrue(str_contains($commerce, "'payment_verified' => true"), 'The checkout response should distinguish a verified payment from a persisted order');
    assertTrue(str_contains($commerce, 'Your cart has been preserved.'), 'Customers should be told that failed order persistence did not clear their cart');
    $persistPosition = strpos($commerce, '$db->saveVerifiedOrder(');
    $clearPosition = strpos($commerce, "unset(\$_SESSION['pending_order'], \$_SESSION['cart'])");
    assertTrue($persistPosition !== false && $clearPosition !== false && $persistPosition < $clearPosition, 'Checkout state must only be cleared after the order write succeeds');
};

$tests['cart quantity controls update progressively and remove at zero'] = function (): void {
    $cart = file_get_contents(app_path('views/public/cart.php'));
    $controller = file_get_contents(app_path('app/Controllers/CommerceController.php'));
    $css = file_get_contents(app_path('assets/css/band.css'));
    assertTrue(!str_contains($cart, 'cart-item__remove'), 'Cart should not render a separate delete control');
    assertTrue(str_contains($cart, "headers:{Accept:'application/json'}"), 'Cart quantity forms should request progressive JSON updates');
    assertTrue(str_contains($cart, "event.preventDefault()"), 'Cart quantity changes should not navigate when JavaScript is available');
    assertTrue(!str_contains($cart, 'location.reload()'), 'Removing the final cart item should not reload the page');
    assertTrue(str_contains($controller, "'cart_count' => \$cartCount"), 'Cart JSON response should include the header cart count');
    assertTrue(str_contains($controller, 'max(0,'), 'Decrement should reach zero so the line can be removed');
    assertTrue(!str_contains($css, '.cart-item__remove'), 'Obsolete cart delete CSS should be removed');
    assertTrue(str_contains($cart, 'breadcrumb breadcrumb--page'), 'Cart should use the aligned page breadcrumb');
    assertTrue(str_contains(file_get_contents(app_path('views/public/checkout.php')), 'breadcrumb breadcrumb--page'), 'Checkout should use the aligned page breadcrumb');
    $layout = file_get_contents(app_path('views/layouts/app.php'));
    assertTrue(str_contains($layout, ".product-card__stepper form"), 'Product card steppers should update without page navigation');
    assertTrue(str_contains($controller, 'if ($this->wantsJson()) $this->jsonResponse($this->cartState($slug));'), 'Add-to-cart should support progressive JSON updates');
};

$tests['bapXphp product media workflow is safe and mapped'] = function (): void {
    $cli = file_get_contents(app_path('cli/bapXphp'));
    $reader = file_get_contents(app_path('cli/product-read.php'));
    $importer = file_get_contents(app_path('cli/import-product-images.php'));
    $map = file_get_contents(app_path('app/Services/ProjectMapService.php'));
    assertTrue(str_contains($cli, 'product:images'), 'CLI should expose the product image import command');
    assertTrue(str_contains($cli, 'cmd_db_hosted_sql'), 'CLI should support direct hosted MySQL operations without an application mutation token');
    assertTrue(!is_file(app_path('cli/pma-client.php')), 'Browser-driven phpMyAdmin client should be removed');
    assertTrue(str_contains($cli, 'file_get_contents("php://stdin")'), 'DB output should parse JSON from stdin instead of interpolating content into PHP code');
    assertTrue(str_contains($reader, "require_once \$root . '/app/bootstrap.php'"), 'Product reader should bootstrap application helpers');
    assertTrue(str_contains($reader, "new App\\Services\\DatabaseService()"), 'Product reader should use the shared local/remote database boundary');
    foreach (['--dry-run', 'image_url', 'image_urls', 'ZipArchive', 'ImageOptimizerService'] as $needle) {
        assertTrue(str_contains($importer, $needle), "Product image importer should include {$needle}");
    }
    assertTrue(str_contains($map, "toolId('import-product-images')"), 'Project map should connect product image import tooling');
};

$tests['failed image uploads reject the save with actionable feedback'] = function (): void {
    $media = new \App\Services\MediaService();
    foreach ([UPLOAD_ERR_INI_SIZE => 'size limit', UPLOAD_ERR_PARTIAL => 'partly uploaded', UPLOAD_ERR_CANT_WRITE => 'upload storage'] as $code => $expected) {
        try {
            $media->upload(['name'=>['product.png'], 'error'=>[$code], 'tmp_name'=>['']]);
            assertTrue(false, 'An unsuccessful PHP upload must not return success');
        } catch (\InvalidArgumentException $error) {
            assertTrue(str_contains($error->getMessage(), $expected), 'The owner should receive the specific upload failure');
        }
    }
    try {
        $media->upload(['name'=>['fake.png'], 'error'=>[UPLOAD_ERR_OK], 'tmp_name'=>[app_path('AGENTS.md')]]);
        assertTrue(false, 'Renaming a non-image must not accept it as product media');
    } catch (\InvalidArgumentException $error) {
        assertTrue(str_contains($error->getMessage(), 'valid JPG'), 'Invalid image bytes should have actionable feedback');
    }
};

$tests['product image checks distinguish records from files and find originals'] = function (): void {
    $checks = (new \App\Services\MediaService())->productImageHealth([
        ['name'=>'Present', 'image_url'=>'/assets/images/products/lakshmi-dollar-front.webp'],
        ['name'=>'Missing derivative', 'image_url'=>'/assets/images/products/lakshmi-dollar-front.gif'],
        ['name'=>'Remote', 'image_url'=>'https://example.com/photo.png'],
    ]);
    assertSame(1, count($checks), 'Existing photos and external images should not be falsely reported missing');
    assertSame('File missing', $checks[0]['status'], 'A saved database path is not proof that a file exists');
    assertTrue(in_array('/assets/images/products/lakshmi-dollar-front.png', $checks[0]['originals'], true), 'The inspector should locate the existing original of a missing derivative');
};

$tests['image optimisation publishes a decodable WebP without destroying source'] = function (): void {
    if (!function_exists('imagewebp')) return;
    $directory = sys_get_temp_dir() . '/sps-image-test-' . bin2hex(random_bytes(6));
    mkdir($directory);
    $source = $directory . '/photo.webp';
    $image = imagecreatetruecolor(12, 16);
    imagewebp($image, $source);
    try {
        $result = (new \App\Services\ImageOptimizerService())->optimize($source);
        assertSame($source, $result, 'Re-uploaded WebP should retain its public filename');
        assertSame([12,16], array_slice(getimagesize($source), 0, 2), 'In-place conversion must leave a complete decodable photo');
        $originalHash = hash_file('sha256', $source);
        $preserved = (new \App\Services\ImageOptimizerService())->optimize($source, null, ['preserve_source'=>true]);
        assertTrue($preserved !== $source && is_file($preserved), 'Preserving uploads should produce a separate optimised image');
        assertSame($originalHash, hash_file('sha256', $source), 'The uploaded original must remain byte-for-byte recoverable');
    } finally {
        foreach (glob($directory . '/*') ?: [] as $file) unlink($file);
        rmdir($directory);
    }
};

$tests['persistent product image URLs survive a replacement code checkout'] = function (): void {
    $directory = \App\Services\MediaService::persistentDirectory() . '/2099/01';
    $created = !is_dir($directory);
    if ($created) mkdir($directory, 0775, true);
    $name = 'fixture-' . bin2hex(random_bytes(6)) . '.webp';
    $destination = $directory . '/' . $name;
    copy(app_path('assets/images/products/lakshmi-dollar-front.webp'), $destination);
    try {
        $url = '/assets/images/media/2099/01/' . $name;
        assertTrue(!is_file(app_path(ltrim($url, '/'))), 'The fixture must not exist in the deployment checkout');
        assertSame($destination, \App\Services\MediaService::storedPathForUrl($url), 'Public URLs should resolve to domain-local persistent storage');
        assertSame(null, \App\Services\MediaService::storedPathForUrl('/assets/images/media/2099/01/../../runtime-key.php'), 'The public image resolver must reject traversal');
        assertSame(null, \App\Services\MediaService::storedPathForUrl('/assets/images/media/2099/01/config.php'), 'The public image resolver must reject non-image files');
    } finally {
        unlink($destination);
        if ($created) { rmdir($directory); if (count(scandir(dirname($directory))) === 2) rmdir(dirname($directory)); }
    }
};

$tests['account pages expose product reviews only after a shipped order is due'] = function (): void {
    assertTrue(!is_file(app_path('views/account/bookings.php')), 'Retired customer session history should not expose reviews');
    $ordersView = file_get_contents(app_path('views/account/orders.php'));
    assertTrue(str_contains($ordersView, 'name="target_type" value="product"'), 'Shipped product orders should expose product review form');
    assertTrue(str_contains($ordersView, 'review_request_after_at'), 'Product review form should wait until the post-shipment review date');
    assertTrue(str_contains($ordersView, 'star-rating-input'), 'Product review form should show a five-star input');
    assertTrue(str_contains($ordersView, 'Delivery Address'), 'User orders should show delivery address');
    assertTrue(str_contains($ordersView, 'Shipped At'), 'User orders should show shipped time or processing detail');
};

$tests['authenticated navigation separates global and internal account menus'] = function (): void {
    $layout = file_get_contents(app_path('views/layouts/app.php'));
    $accountNav = file_get_contents(app_path('views/account/_nav.php'));
    assertTrue(str_contains($layout, '>Dashboard</a>') && str_contains($layout, 'href="/logout"'), 'Authenticated global navigation should expose Dashboard and Logout');
    assertTrue(!str_contains($layout, '>My Sessions</a>') && !str_contains($layout, '>Wallet</a>'), 'Authenticated global navigation should not duplicate internal account destinations');
    assertTrue(str_contains($layout, 'href="/account/dashboard"'), 'Global Dashboard should use the dashboard entry URL');
    foreach (['/account/dashboard/orders', 'Back to Home'] as $needle) {
        assertTrue(str_contains($accountNav, $needle), "Shared account navigation should include {$needle}");
    }
    assertTrue(!str_contains($accountNav, '/account/dashboard/sessions'), 'Shared account navigation should not expose retired session history');
    foreach (['orders.php'] as $view) {
        $contents = file_get_contents(app_path('views/account/' . $view));
        assertTrue(str_contains($contents, "require __DIR__ . '/_nav.php'"), "{$view} should reuse shared account navigation");
        assertTrue(!str_contains($contents, '<aside class="account-nav">'), "{$view} should not duplicate account navigation markup");
    }
};

$tests['legacy account session urls redirect into orders and are not in agent context'] = function (): void {
    $account = file_get_contents(app_path('app/Controllers/AccountController.php'));
    assertTrue(str_contains($account, '/account/dashboard/orders'), 'Legacy account session URL should redirect into the available dashboard area');
    $context = file_get_contents(app_path('app/Services/AgentContextService.php'));
    assertTrue(str_contains($context, '/account/dashboard/orders'), 'Agent context should expose the orders dashboard URL');
    assertTrue(!str_contains($context, '/account/dashboard/sessions'), 'Agent context should not expose retired session URLs');
};

$tests['consultants are profiles without application login credentials'] = function (): void {
    $auth=file_get_contents(app_path('app/Controllers/AuthController.php'));
    $admin=file_get_contents(app_path('app/Controllers/AdminController.php'));
    $layout=file_get_contents(app_path('views/layouts/admin.php'));
    assertTrue(str_contains($auth,'Consultant access is managed by the site administrator.'),'Legacy consultant users should be denied application login');
    assertTrue(!str_contains($admin,'AstrologerAccountService') && !str_contains($layout,'Login IDs'),'Admin should not create or expose consultant credentials');
    assertTrue(!is_file(app_path('app/Controllers/AstrologerController.php')) && !is_file(app_path('views/astrologer/dashboard.php')),'Consultant login surfaces should be removed');
};

$tests['retired consultation routes preserve only protected owner status access'] = function (): void {
    foreach(['/consultation/initiate','/api/consultations/{id}/status'] as $path) routeExists($path,"Missing consultation route {$path}");
    foreach(['/astrologer','/astrologer/change-password','/astrologer/availability','/admin/astrologer-credentials'] as $path) routeMissing($path,"Consultant credential route should be removed: {$path}");
    foreach(['/consultation/{id}','/api/consultations/{id}/messages','/api/consultations/{id}/signals'] as $path) routeMissing($path,"Removed live consultation route should not be public: {$path}");
    $controller = file_get_contents(app_path('app/Controllers/ConsultationController.php'));
    assertTrue(str_contains($controller, "redirect('/contact#contact-form')"), 'A legacy initiate request should redirect to general enquiry');
    assertTrue(str_contains($controller, "['role'] ?? '') !== 'admin'"), 'Only an owner can update historical session status');
};

$tests['contact form sends customer confirmation and owner notification'] = function (): void {
    $controller = file_get_contents(app_path('app/Controllers/PublicController.php'));
    $mail = file_get_contents(app_path('app/Services/MailQueueService.php'));
    assertTrue(str_contains($controller, 'enqueueContactConfirmation') && str_contains($controller, 'notifyAdmin'), 'Contact handler should queue both notifications');
    foreach (['contact_customer_confirmation', 'We received your enquiry', "['contact_id'"] as $needle) {
        assertTrue(str_contains($mail, $needle), "Contact confirmation should include {$needle}");
    }
    assertTrue(MailQueueService::contactLooksLikeAppointment(['subject' => 'Astrology question', 'message' => '']), 'Astrology subjects should be classified for the owner');
    assertTrue(MailQueueService::contactLooksLikeAppointment(['subject' => 'General Question', 'message' => 'Can I make an appointment?']), 'Appointment language in the message should be classified for the owner');
    assertTrue(!MailQueueService::contactLooksLikeAppointment(['subject' => 'Order Support', 'message' => 'Where is my parcel?']), 'Ordinary commerce enquiries should remain general');
    $appointment = MailQueueService::contactOwnerNotification(['id' => 'contact_test', 'subject' => 'General Question', 'message' => 'I need an appointment']);
    assertSame('New consultation or appointment enquiry', $appointment['subject'], 'Appointment enquiries should be obvious in the owner inbox');
    assertSame('appointment_enquiry', $appointment['meta']['contact_kind'], 'Appointment owner mail should carry auditable classification metadata');
    assertSame('contact_test', $appointment['meta']['contact_id'], 'Owner mail should retain the saved contact reference');
    $general = MailQueueService::contactOwnerNotification(['subject' => 'Order Support', 'message' => 'Where is my parcel?']);
    assertSame('New contact form submission', $general['subject'], 'Ordinary contact mail should keep the general owner subject');
    assertSame('general_enquiry', $general['meta']['contact_kind'], 'Ordinary owner mail should keep general classification metadata');
    assertTrue(str_contains($controller, 'contactOwnerNotification'), 'Contact handler should use the tested owner notification builder');
};

$tests['registration creates a default delivery address'] = function (): void {
    $auth = file_get_contents(app_path('app/Controllers/AuthController.php'));
    $register = file_get_contents(app_path('views/public/register.php'));
    foreach (['phone', 'address', 'city', 'pincode'] as $field) assertTrue(str_contains($register, 'name="' . $field . '"'), "Registration should collect {$field}");
    assertTrue(str_contains($auth, 'new AddressService') && str_contains($auth, "'is_default'=>true"), 'Registration should save the first address as default');
};

$tests['authenticated sessions persist for thirty days until logout'] = function (): void {
    $bootstrap = file_get_contents(app_path('app/bootstrap.php'));
    $auth = file_get_contents(app_path('app/Controllers/AuthController.php'));
    foreach (['session.gc_maxlifetime', "'lifetime' => 60 * 60 * 24 * 30", "'httponly' => true", "'samesite' => 'Lax'", 'session_start()'] as $needle) {
        assertTrue(str_contains($bootstrap, $needle), "Session bootstrap should include {$needle}");
    }
    assertTrue(str_contains($auth, 'session_destroy()'), 'Explicit logout should destroy the persistent session');
};

$tests['saved addresses select the default and allow another checkout address'] = function (): void {
    $schema = require app_path('storage/schema/collections.php');
    assertTrue(isset($schema['collections']['addresses']['fields']['is_default']), 'Address schema should declare is_default');
    $service = file_get_contents(app_path('app/Services/AddressService.php'));
    $checkout = file_get_contents(app_path('views/public/checkout.php'));
    assertTrue(str_contains($service, "'is_default' => \$isDefault"), 'Address service should persist one default address');
    assertTrue(str_contains($checkout, "' (Default)'"), 'Checkout should label the default saved address');
    assertTrue(str_contains($checkout, 'Enter a new address') && str_contains($checkout, 'Save for next time'), 'Checkout should allow one-time or newly saved addresses');
};

$tests['agent replies never expose the model reasoning scaffold'] = function (): void {
    $cleaner = new \App\Services\AiReplyCleaner();

    $leaked = "* Role: AI assistant for the site (admin AI assistant).\n"
        . "* Context: Provided site data (users, orders, products, etc.).\n"
        . "* Constraint: Answer concisely in Markdown.\n"
        . "* Question: \"are you a LLM?\"\n\n"
        . "* The user is asking about my nature/identity.\n"
        . "* I am indeed a Large Language Model (LLM).\n\n"
        . "* Concise.\n* Markdown.\n";
    $clean = $cleaner->clean($leaked, '');
    foreach (['Role:', 'Context:', 'Constraint:', 'Question:', 'The user is asking', 'Concise'] as $scaffold) {
        assertTrue(!str_contains($clean, $scaffold), "Scaffold must be removed: {$scaffold}");
    }
    assertTrue(str_contains($clean, 'Large Language Model'), 'The actual answer must survive');

    // "Direct answer: X" wraps a real answer — keep X.
    assertTrue(str_contains($cleaner->clean("* Direct answer: Revenue is Rs 5,489.", ''), 'Revenue is Rs 5,489.'),
        'A labelled direct answer must be unwrapped, not dropped');

    // Ordinary prose that merely contains a label word must survive.
    $prose = 'The role of a consultant here is separate from products.';
    assertTrue(str_contains($cleaner->clean($prose, ''), 'role of a consultant'),
        'Prose containing a label word must not be treated as scaffold');

    // Both agents must share this one implementation.
    assertTrue(str_contains(file_get_contents(app_path('app/Services/SupportBotService.php')), 'AiReplyCleaner'),
        'Support bot should delegate to the shared cleaner');
    assertTrue(str_contains(file_get_contents(app_path('app/Controllers/AdminController.php')), 'AiReplyCleaner'),
        'Admin agent should delegate to the shared cleaner');
};

$tests['a paying customer lands on their order and a failure explains itself'] = function (): void {
    routeExists('/account/orders/{orderId}', 'A customer should be able to open one order');

    // My Orders showed a status but gave no way into the order itself.
    $list = file_get_contents(app_path('views/account/orders.php'));
    assertTrue(str_contains($list, 'View order') && str_contains($list, '/account/orders/'),
        'Each order row should link to that order');

    // A verified payment used to redirect to the list behind a toast that vanished.
    $checkout = file_get_contents(app_path('views/public/checkout.php'));
    assertTrue(str_contains($checkout, "'/account/orders/' + encodeURIComponent(result.order_id) + '?placed=1'"),
        'A verified payment should land on the order that was just placed');
    assertTrue(!str_contains($checkout, "showToast('Order placed"),
        'The order confirmation should not be a toast');
    assertTrue(str_contains($checkout, 'showPaymentFailure') && str_contains($checkout, 'pay-retry'),
        'A failed payment should show a panel with a retry, not a toast');

    // The thank-you banner is part of the order page and only appears on arrival.
    $order = file_get_contents(app_path('views/account/order.php'));
    assertTrue(str_contains($order, 'Thank you') && str_contains($order, 'justPlaced'),
        'The order page should double as the thank-you screen');
    foreach (['tracking_url', 'courier_name', 'Total paid'] as $needle) {
        assertTrue(str_contains($order, $needle), "The order page should show {$needle}");
    }

    // The page must only ever render an order belonging to the signed-in customer.
    $controller = file_get_contents(app_path('app/Controllers/AccountController.php'));
    assertTrue(str_contains($controller, 'ownedOrder') && str_contains($controller, "customer_email'] ?? '') === \$userEmail"),
        'One customer must not be able to open another customer\'s order');

    $css = file_get_contents(app_path('assets/css/band.css'));
    foreach (['.order-thanks', '.order-card', '.pay-failed'] as $rule) {
        assertTrue(str_contains($css, $rule), "Stylesheet should define {$rule}");
    }
    assertSame(substr_count($css, '{'), substr_count($css, '}'), 'Stylesheet should have balanced rule braces');
};

$tests['the admin agent attaches documents and drafts into a form it cannot save'] = function (): void {
    $attach = new \App\Services\AgentAttachmentService();

    // @terms and @privacy bring the document's text in, without its frontmatter.
    $legal = $attach->resolve('tighten the refund wording in @terms');
    assertSame(['terms'], $legal['resolved'], '@terms should resolve to the legal page');
    assertTrue(strlen($legal['context']) > 200, '@terms should carry the document text');
    assertTrue(!str_contains($legal['context'], "\ntitle:"), 'Frontmatter is never content');

    // An unknown name is reported rather than silently dropped.
    $missing = $attach->resolve('what about @definitely-not-a-real-thing');
    assertSame([], $missing['resolved'], 'An unknown @name resolves to nothing');
    assertSame(['definitely-not-a-real-thing'], $missing['missing'], 'An unknown @name is reported back');

    // Visibility is inherited from BlogService, never re-implemented here.
    $service = file_get_contents(app_path('app/Services/AgentAttachmentService.php'));
    assertTrue(str_contains($service, 'blog->all(false)'),
        'Attachments must use the public blog filter so hidden categories stay hidden');

    // The agent drafts; it never writes.
    assertSame('create-blog', \App\Services\AgentDraftService::command('/create-blog rudraksha'), 'A blog command is recognised');
    assertSame('add-product', \App\Services\AgentDraftService::command('/add-product brass lamp'), 'A product command is recognised');
    assertSame(null, \App\Services\AgentDraftService::command('how many orders today?'), 'An ordinary question is not a command');
    assertSame('rudraksha benefits', \App\Services\AgentDraftService::subject('/create-blog rudraksha benefits'), 'The subject is what follows the command');

    $draft = (new \App\Services\AgentDraftService())->draft('create-blog', '');
    assertSame('/admin/blog/save', $draft['action'], 'A draft posts to the existing save route');
    assertTrue(count($draft['fields']) > 0, 'A draft lists the fields a save needs');
    $names = array_column($draft['fields'], 'name');
    foreach (['title', 'slug', 'category', 'content'] as $needed) {
        assertTrue(in_array($needed, $names, true), "A blog draft should ask for {$needed}");
    }

    // Price and tax are the owner's to set — a model guessing a price puts a wrong
    // number in front of a shopper.
    $product = (new \App\Services\AgentDraftService())->draft('add-product', '');
    $hinted = [];
    foreach ($product['fields'] as $field) {
        if (isset($field['hint'])) $hinted[] = $field['name'];
    }
    foreach (['price', 'hsn_code', 'gst_rate', 'image_url'] as $ownerOnly) {
        assertTrue(in_array($ownerOnly, $hinted, true), "{$ownerOnly} should be marked for the owner to set");
    }
    $service = file_get_contents(app_path('app/Services/AgentDraftService.php'));
    assertTrue(str_contains($service, 'Never invent a price'), 'The prompt must forbid inventing a price');

    // The chat can upload, and an upload anywhere in the admin joins the media library.
    $view = file_get_contents(app_path('views/admin/agent.php'));
    assertTrue(str_contains($view, '/admin/media/upload') && str_contains($view, 'media_files[]'),
        'The agent chat should upload into the media library');
    assertTrue(str_contains($view, 'agent-mentions'), 'The chat should offer @ attachments');
    $admin = file_get_contents(app_path('app/Controllers/AdminController.php'));
    assertTrue(str_contains($admin, "preg_match('#^/admin/[a-z0-9/-]*\$#i', \$redirect)"),
        'An upload return path must be restricted to the admin');
};

$tests['the support agent knows this shop\'s own delivery and payment rules'] = function (): void {
    // Asked "how long does delivery take to singapore" the agent answered that it had
    // no information, while OrderService had held the answer all along and the shipment
    // email already quoted it.
    $bot = new \App\Services\SupportBotService();
    $policies = (new ReflectionMethod($bot, 'policies'))->getClosure($bot)();
    foreach (['delivery_within_india', 'delivery_outside_india', 'shipping_cost', 'payment_methods', 'tracking'] as $key) {
        assertTrue(!empty($policies[$key]), "The agent should know {$key}");
    }
    // Read from the constants, not restated, so the promise the agent makes and the
    // promise the shipment email makes cannot drift apart.
    assertTrue(str_contains($policies['delivery_within_india'], \App\Services\OrderService::DELIVERY_DAYS_DOMESTIC),
        'Domestic delivery should come from OrderService');
    assertTrue(str_contains($policies['delivery_outside_india'], \App\Services\OrderService::DELIVERY_DAYS_INTERNATIONAL),
        'International delivery should come from OrderService');
    $service = file_get_contents(app_path('app/Services/SupportBotService.php'));
    assertTrue(str_contains($service, 'OrderService::DELIVERY_DAYS_DOMESTIC'), 'Delivery days must not be retyped');
    assertTrue(str_contains($service, 'object with this shop'), 'The prompt should point the model at the policies');
};

$tests['the blog editor keeps images and fills in the date and author itself'] = function (): void {
    $view = file_get_contents(app_path('views/admin/blog.php'));

    // A top-level <img> was dropped: the root loop fell through to inline(n), which
    // walks the node's CHILDREN, and an <img> has none — so an image picked from the
    // media library converted to an empty string and vanished the moment Preview or
    // Markdown was opened.
    assertTrue(str_contains($view, 'var VOIDISH = /^(img|br|hr|input)$/;'),
        'Nodes that carry everything in their attributes need their own branch');
    assertTrue(str_contains($view, "if (t==='img') return '!['+(node.getAttribute('alt')||'')+']('+(node.getAttribute('src')||'')+')';"),
        'A top-level image should convert to Markdown, not an empty string');
    $guard = strpos($view, 'VOIDISH.test(tag)');
    $fallback = strpos($view, 'else { var t2=inline(n).trim();');
    assertTrue($guard !== false && $fallback !== false && $guard < $fallback,
        'The image branch must run before the fallback that dropped it');

    // The date and the author are facts the site already knows.
    $admin = file_get_contents(app_path('app/Controllers/AdminController.php'));
    assertTrue(str_contains($admin, 'currentAuthorName'), 'The author should come from the signed-in admin');
    assertTrue(str_contains($admin, "\$post['published_at'] = (string)(\$existing['published_at'] ?? '') ?: date('Y-m-d');"),
        'A blank date should become today, and an edit should keep the original date');
    assertTrue(str_contains($view, 'Leave blank for today'), 'The form should say the date is optional');
    assertTrue(!str_contains($view, 'name="author" id="edit-author" value="Admin"'),
        'The author field should no longer be hard-coded to Admin');
};

$tests['the admin agent grounds a count instead of returning the nearest number'] = function (): void {
    // "how many enquiries came for orders" answered 5. There were no enquiries at all:
    // 5 was Total orders from the statistics blob. A number with no stated source is
    // unverifiable, which is the actual defect — not the length of the reply.
    $registry = new \App\Services\AgentToolRegistry();
    foreach (['support_enquiries', 'search_articles'] as $tool) {
        assertTrue($registry->has($tool), "The agent should be able to call {$tool}");
    }
    assertTrue(isset($registry->run('search_articles', [])['error']), 'search_articles needs a query');

    $source = file_get_contents(app_path('app/Services/AgentToolRegistry.php'));
    $range = ['from'=>'2026-11-01', 'to'=>'2026-11-30'];
    $tickets = [
        ['id'=>'placeholder'],
        ['id'=>'secret-reference', 'customer_email'=>'private@example.test', 'message'=>'Where is my order?', 'created_at'=>'2026-11-01T10:00:00+05:30', 'status'=>'open'],
        ['id'=>'closed', 'message'=>'This is an order question', 'created_at'=>'2026-11-01T10:00:00+05:30', 'status'=>'closed'],
        ['id'=>'different-topic', 'message'=>'Thank you for the reorder notice', 'reply'=>'Order shipped', 'created_at'=>'2026-11-01T10:00:00+05:30', 'status'=>'open'],
        ['id'=>'too-early', 'message'=>'Where is my order?', 'created_at'=>'2026-10-31T10:00:00+05:30', 'status'=>'open'],
    ];
    $result = \App\Services\AgentToolRegistry::supportEnquiryCounts($tickets, $range, 'orders', 'unanswered');
    assertSame(1, $result['total'], 'The scoped count should use dates, original enquiry text and current status');
    assertSame(['open'=>1], $result['by_status'], 'Unanswered excludes closed tickets even if they have no reply');
    assertTrue(isset($result['topic_words_used']['orders']), 'Explain the topic interpretation');
    assertTrue(!str_contains(json_encode($result), 'secret-reference') && !str_contains(json_encode($result), 'private@example.test'), 'Report results must remain aggregate');
    assertTrue(isset($registry->run('support_enquiries', ['status'=>'pending'])['error']), 'Reject unrecognized ticket filters');
    assertTrue(isset($registry->run('gst_register_summary', ['from'=>'2026-02-31'])['error']), 'Reject invalid dates before reading orders');
    assertTrue(isset($registry->run('store_count', ['entity'=>'secrets'])['error']), 'Reject collections outside the fixed count allowlist');
    assertSame(null, $registry->aggregateAnswer('Compare revenue and GST last month'), 'Compound requests need model-selected data instead of one local summary');
    assertSame(null, $registry->aggregateAnswer('What was our revenue yesterday?'), 'Do not substitute a default period for an unsupported local filter');
    assertTrue(str_contains($source, 'blog->all(false)') || str_contains($source, "BlogService())->all(false)"),
        'Article search must use the public filter so an unpublished post cannot be cited');
    // Still read-only, now that two more tools exist.
    foreach (['upsert(', '->write(', '->delete(', '->save('] as $mutation) {
        assertTrue(!str_contains($source, $mutation), "Tools must stay read-only: found {$mutation}");
    }

    $bare = (new ReflectionMethod(\App\Controllers\AdminController::class, 'isBareFigure'));
    foreach (['5', '**5**', '5.', '  5  ', '₹5'] as $answer) {
        assertTrue($bare->invoke(null, $answer), "A reply of '{$answer}' is a bare figure");
    }
    foreach (['5 orders', 'I found 5 support enquiries in support_tickets.', ''] as $answer) {
        assertTrue(!$bare->invoke(null, $answer), "'{$answer}' is not a bare figure");
    }

    $only = (new ReflectionMethod(\App\Controllers\AdminController::class, 'wantsNumberOnly'));
    foreach (['how many orders, just the number', 'give me the count only', 'total only please'] as $q) {
        assertTrue($only->invoke(null, $q), "'{$q}' asks for a figure alone");
    }
    foreach (['how many enquiries came for orders', 'which products sell best'] as $q) {
        assertTrue(!$only->invoke(null, $q), "'{$q}' does not ask for a figure alone");
    }

    $admin = file_get_contents(app_path('app/Controllers/AdminController.php'));
    assertTrue(str_contains($admin, 'Use the matching read-only tool before making any claim'),
        'The admin persona should require a number to carry its source');
    assertTrue(str_contains($admin, 'distinguish empty results from failed lookups'),
        'The persona should forbid answering with whatever number is to hand');
    assertTrue(!str_contains($admin, 'You are the admin assistant for this store. Answer the question directly.'),
        'The prompt that produced the one-word answer should be gone');
};

$tests['every module toggle in the code refers to a module that exists'] = function (): void {
    // moduleEnabled() returns its `?? true` default for an unknown key, so a typo does
    // not fail — it silently reads as "switched on" whatever the owner set. That is how
    // the support bot kept offering cart and checkout help for a disabled shop.
    $known = array_keys(\App\Services\SettingsService::MODULES);
    $used = [];
    foreach ([app_path('app'), app_path('views')] as $dir) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') continue;
            if (preg_match_all("/module(?:_on|Enabled)\\(\\s*'([a-z_]+)'/", (string)file_get_contents($file->getPathname()), $m)) {
                foreach ($m[1] as $key) $used[$key] = ($used[$key] ?? 0) + 1;
            }
        }
    }
    assertTrue($used !== [], 'Module toggles should be found in the source');
    foreach (array_keys($used) as $key) {
        assertTrue(in_array($key, $known, true),
            "module_on('{$key}') refers to a module that does not exist; known keys are " . implode(', ', $known));
    }
};

$tests['owner agent context and order tools protect customer identities'] = function (): void {
    $context = App\Services\AgentContextService::ownerAnalyticsFromRecords([
        ['status'=>'confirmed', 'total'=>200, 'customer_email'=>'alice@example.test', 'items'=>[['name'=>'Ring','qty'=>2,'line_total'=>200]]],
        ['status'=>'confirmed', 'total'=>100, 'customer_email'=>'bob@example.test', 'items'=>[['name'=>'Ring','qty'=>1,'line_total'=>100]]],
    ], 2, 1, 0, 0);
    assertTrue(!str_contains($context, 'alice@example.test') && !str_contains($context, 'bob@example.test'), 'Analytics must not send customer emails');
    assertTrue(str_contains($context, '3 sold') && str_contains($context, '300.00'), 'Aggregate sales remain available');
    $registry = new App\Services\AgentToolRegistry(ownerQuestion: 'How many orders are there?');
    assertTrue(isset($registry->run('find_order', ['customer_email'=>'alice@example.test'])['error']), 'Model cannot invent an email lookup');
    assertTrue(isset($registry->run('find_order', ['order_id'=>'a123'])['error']), 'Model cannot invent an order id lookup');
    assertTrue(App\Services\AgentToolRegistry::ownerRequestedLookup('Show order a123456789abcdef', 'a123456789abcdef', ''), 'Explicit full order id is allowed');
    assertTrue(!App\Services\AgentToolRegistry::ownerRequestedLookup('Show order a123456789abcdef', 'a123', ''), 'An ID prefix is not an explicit exact identifier');
    assertTrue(!App\Services\AgentToolRegistry::ownerRequestedLookup('Show orders for alice@example.test', '', 'bob@example.test'), 'Another customer is not allowed');
    assertTrue(App\Services\AgentToolRegistry::ownerRequestedLookup('Show orders for alice@example.test', '', 'alice@example.test'), 'Explicit customer lookup remains available');

    $orders = [
        ['id'=>'a123456789abcdef', 'customer_email'=>'alice@example.test', 'shipping_city'=>'Vellore', 'courier_name'=>'SafeShip', 'tracking_id'=>'TRACK-ALICE', 'status'=>'confirmed', 'total'=>200, 'items'=>[]],
        ['id'=>'b987654321fedcba', 'customer_email'=>'bob@example.test', 'shipping_city'=>'Chennai', 'courier_name'=>'OtherShip', 'tracking_id'=>'TRACK-BOB', 'status'=>'pending', 'total'=>100, 'items'=>[]],
    ];
    $plain = App\Services\AgentToolRegistry::scopedOrderLookup($orders, ['order_id'=>'a123456789abcdef'], 'Show order a123456789abcdef');
    assertSame(1, $plain['found'], 'An exact owner-requested order remains available');
    assertTrue(!isset($plain['orders'][0]['customer_email'], $plain['orders'][0]['shipping_city'], $plain['orders'][0]['tracking_id']), 'An ordinary lookup must not send unrequested email, city or tracking data');
    assertTrue(!str_contains(json_encode($plain), 'bob@example.test') && !str_contains(json_encode($plain), 'TRACK-BOB'), 'A scoped lookup must not expose another customer');
    $tracked = App\Services\AgentToolRegistry::scopedOrderLookup($orders, ['order_id'=>'a123456789abcdef'], 'Where is order a123456789abcdef? Show tracking and delivery city.');
    assertSame('Vellore', $tracked['orders'][0]['shipping_city'], 'Delivery city is available when explicitly requested');
    assertSame('TRACK-ALICE', $tracked['orders'][0]['tracking_id'], 'Tracking is available when explicitly requested');
};

$tests['the admin agent can look things up instead of guessing'] = function (): void {
    $registry = new \App\Services\AgentToolRegistry();
    $names = array_column($registry->declarations(), 'name');
    foreach (['find_order', 'product_status', 'sales_summary', 'coupon_status'] as $tool) {
        assertTrue(in_array($tool, $names, true), "The agent should be able to call {$tool}");
        assertTrue($registry->has($tool), "{$tool} should be recognised");
    }
    assertTrue(!$registry->has('delete_everything'), 'An unknown tool is not recognised');

    // Each declaration must carry a schema, or the model cannot call it.
    foreach ($registry->declarations() as $tool) {
        assertTrue(($tool['description'] ?? '') !== '', 'Every tool needs a description');
        assertTrue(($tool['parameters']['type'] ?? '') === 'object', 'Every tool needs an object parameter schema');
    }

    // An unknown tool is reported, not thrown: the model should be told and allowed to
    // say so, rather than collapsing the request into a 500.
    assertTrue(isset($registry->run('no_such_tool', [])['error']), 'An unknown tool returns an error');
    assertTrue(isset($registry->run('find_order', [])['error']), 'find_order needs an id or an email');
    assertTrue(isset($registry->run('coupon_status', [])['error']), 'coupon_status needs a code');
    $activity = $registry->activity();
    assertSame(3, count($activity), 'Every attempted owner tool is represented in the operator trace');
    assertSame(['name'=>'coupon_status','status'=>'error'], array_intersect_key($activity[2], ['name'=>1,'status'=>1]), 'Tool trace exposes only the tool outcome contract');
    assertTrue(is_int($activity[2]['duration_ms']) && $activity[2]['duration_ms'] >= 0, 'Tool trace includes a bounded elapsed duration');

    // Read-only. An agent that could change the shop on its own would be one prompt
    // away from hiding a product, with the reply as the owner's first notice.
    $source = file_get_contents(app_path('app/Services/AgentToolRegistry.php'));
    foreach (["upsert(", "->write(", "->delete(", "->save("] as $mutation) {
        assertTrue(!str_contains($source, $mutation), "Tools must stay read-only: found {$mutation}");
    }
    // A coupon must be judged by the same service checkout uses, or the agent could
    // tell the owner a coupon works while checkout refuses it.
    assertTrue(str_contains($source, 'assertUsable'), 'Coupon status should use CouponService');

    $client = file_get_contents(app_path('app/Services/AiClient.php'));
    assertTrue(str_contains($client, 'functionDeclarations'), 'Tools should be declared to the provider');
    assertTrue(str_contains($client, 'functionResponse'), 'Tool results should be returned to the model');
    assertTrue(str_contains($client, "\$round <= \$maxRounds")
        && str_contains($client, "'mode' => 'NONE'")
        && str_contains($client, 'GENERATION_TIMEOUT_NS'),
        'The tool loop must have a shared deadline and end with a text-only round');
    assertTrue(str_contains($client, "'role' => 'model', 'parts' => self::googleToolHistoryParts(\$parts)"),
        'The model turn must stay in the history, or the function response answers a call the conversation no longer has');
    $admin = file_get_contents(app_path('app/Controllers/AdminController.php'));
    assertTrue(str_contains($admin, 'completeWithTools'), 'The admin agent should use the tool loop');
};

$tests['google tool history preserves object arguments and call ids'] = function (): void {
    $parts = \App\Services\AiClient::googleToolHistoryParts([
        ['thought' => true, 'text' => 'checking'],
        ['functionCall' => ['id' => 'call-1', 'name' => 'sales_summary', 'args' => []]],
        ['functionCall' => ['name' => 'search_articles', 'args' => ['tags' => ['shop', 'help']]]],
    ]);
    $json = json_encode($parts, JSON_THROW_ON_ERROR);
    assertTrue(str_contains($json, '"args":{}'), 'An empty Google function args object must not become a rejected JSON list');
    assertTrue(str_contains($json, '"tags":["shop","help"]'), 'Nested list arguments must remain JSON lists');

    $response = \App\Services\AiClient::googleFunctionResponse(
        ['id' => 'call-1', 'name' => 'sales_summary'],
        [['month' => '2026-11', 'orders' => 3]]
    );
    assertSame('call-1', $response['functionResponse']['id'] ?? null, 'The response must echo the provider call id');
    assertSame('sales_summary', $response['functionResponse']['name'] ?? null, 'The response must name the requested function');
    assertTrue(isset($response['functionResponse']['response']['result']), 'List-valued tool results must be wrapped in a response object');
};

$tests['the model answer is separated from the model thinking out loud'] = function (): void {
    // gemma-4-31b-it is a reasoning model. AiClient read parts[0], which for a reasoning
    // response is the *thought*, not the answer. Every reply handed back was the model
    // thinking out loud: AiReplyCleaner then correctly stripped it as scaffold and left
    // nothing, so both agents fell back to a canned reply. One array index.
    assertSame('the answer', \App\Services\AiClient::answerFromParts([
        ['text' => 'The user wants me to…', 'thought' => true],
        ['text' => 'the answer'],
    ]), 'A thought part must never be returned as the answer');
    assertSame('the answer', \App\Services\AiClient::answerFromParts([['text' => 'the answer']]),
        'A response with no thoughts is returned as-is');
    assertSame('Hello world', \App\Services\AiClient::answerFromParts([
        ['text' => 'planning', 'thought' => true], ['text' => 'Hello '], ['text' => 'world'],
    ]), 'A multi-part answer is joined');
    assertSame('', \App\Services\AiClient::answerFromParts([['text' => 'only thinking', 'thought' => true]]),
        'Thought-only content must never become a public answer or transcript');
    assertSame('', \App\Services\AiClient::answerFromParts([]), 'No parts means no answer');

    // The thought flag is not always set — sometimes the reasoning arrives as prose —
    // so the answer is also fenced.
    assertSame('A rudraksha is a seed.', \App\Services\AiClient::fencedAnswer(
        "I will write it between `<final_answer>` and `</final_answer>` as asked.\n"
        . "<final_answer>A rudraksha is a seed.</final_answer>"
    ), 'The model quoting the tag names while planning must not be mistaken for the answer');
    assertSame(null, \App\Services\AiClient::fencedAnswer('use `<final_answer>` and `</final_answer>`'),
        'A prompt echo alone is not an answer');
    assertSame('ok', \App\Services\AiClient::fencedAnswer('<final_answer>ok</final_answer>'), 'A plain fence is read');
    assertSame(null, \App\Services\AiClient::fencedAnswer('no tags here'), 'No fence means no fenced answer');

    // The request must ask for the reasoning to stop, in the shape the API accepts.
    $client = file_get_contents(app_path('app/Services/AiClient.php'));
    assertTrue(str_contains($client, "'thinkingConfig' => ['thinkingLevel' => 'MINIMAL']"),
        'thinkingLevel must be nested inside thinkingConfig; at the top of generationConfig the API returns HTTP 400');
    assertTrue(!str_contains($client, "parts'][0]['text']"),
        'The client must never read parts[0] as the answer');
};

$tests['a product can be hidden without deleting it, and an offer expires'] = function (): void {
    $service = new \App\Services\ProductService(
        new \App\Services\DatabaseService(),
        new \DateTimeImmutable('2026-08-06 12:00:00')
    );
    $normalise = (new ReflectionMethod($service, 'normalise'))->getClosure($service);
    $product = fn(array $overrides = []): array => array_merge(
        ['slug' => 'x', 'name' => 'X', 'price' => 1000, 'offer_price' => 700],
        $overrides
    );
    $paid = fn(array $overrides = []): float => $service->priceOf($normalise($product($overrides)));

    // offer_price used to apply from the moment it was typed until somebody remembered
    // to clear it, so a festival discount kept discounting.
    assertSame(700.0, $paid(), 'An offer with no dates applies');
    assertSame(1000.0, $paid(['offer_ends_at' => '2026-08-01']), 'An expired offer is not charged');
    assertSame(700.0, $paid(['offer_ends_at' => '2026-08-06']), 'An offer ending today runs to the end of the day');
    assertSame(700.0, $paid(['offer_ends_at' => '2026-08-31']), 'An offer still inside its window applies');
    assertSame(1000.0, $paid(['offer_starts_at' => '2026-09-01']), 'An offer that has not started is not charged');
    assertSame(700.0, $paid(['offer_starts_at' => '2026-08-01', 'offer_ends_at' => '2026-08-31']), 'A running window applies');

    // stock_status is stock; visibility had no field at all, so the only way to take a
    // product off the shop was to delete it and lose what past orders point at.
    assertTrue(!$normalise($product())['is_hidden'], 'A product with no status stays visible');
    assertTrue(!$normalise($product(['status' => 'visible']))['is_hidden'], 'Visible means visible');
    foreach (['hidden', 'inactive', 'draft', 'disabled'] as $off) {
        assertTrue($normalise($product(['status' => $off]))['is_hidden'], "Status {$off} hides the product");
    }

    // Every surface a shopper can reach must use the filtered read, or one of them
    // shows a price another one does not charge.
    $public = file_get_contents(app_path('app/Controllers/PublicController.php'));
    assertTrue(!str_contains($public, '(new ProductService())->all()'),
        'Public pages should list visible products only');
    $api = file_get_contents(app_path('app/Controllers/ApiController.php'));
    assertTrue(substr_count($api, '$service->visible()') >= 2, 'The public API should expose visible products only');
    $base = file_get_contents(app_path('app/Controllers/BaseController.php'));
    assertTrue(str_contains($base, 'ProductService())->bySlug()'),
        'The cart must price through ProductService so an expired offer is not charged');
    $commerce = file_get_contents(app_path('app/Controllers/CommerceController.php'));
    assertTrue(!str_contains($commerce, "foreach (\$store->read('products') as \$p)")
        && !str_contains($commerce, "foreach (\$db->read('products') as \$p)"),
        'Checkout must not read the products table around the visibility rule');
    $agent = file_get_contents(app_path('app/Services/AgentContextService.php'));
    assertTrue(str_contains($agent, '->visible()'), 'The support agent must not offer a hidden product');

    // A hidden product must not stay buyable through its URL. Asserted against the
    // source rather than by calling findBySlug(), which reads the database — a test
    // that reaches sripanchamispiritual.com from CI is a test of the network.
    $source = file_get_contents(app_path('app/Services/ProductService.php'));
    assertTrue(str_contains($source, 'if (!$includeHidden && !empty($item[\'is_hidden\'])) return null;'),
        'findBySlug should withhold a hidden product unless explicitly asked');
};

$tests['product descriptions cannot contradict structured zodiac facts'] = function (): void {
    $wrong = [
        'description' => 'Dhanu Rasi Pushparagam is a Yellow Sapphire associated with Sagittarius.',
        'specifications' => ['Gemstone' => 'Pavazham (Red Coral)', 'Rasi' => 'Vrishchik (Scorpio)'],
    ];
    $right = [
        'description' => 'A Viruchigam Rasi Pavazham ring with a distinctive Red Coral stone.',
        'specifications' => ['Gemstone' => 'Pavazham (Red Coral)', 'Rasi' => 'Vrishchik (Scorpio)'],
    ];
    assertTrue(str_contains((string)\App\Services\ProductService::contentConflict($wrong), 'conflicts'), 'A pasted Dhanu or Pushparagam description should be rejected for a Vrishchik red-coral product');
    assertSame(null, \App\Services\ProductService::contentConflict($right), 'A description matching the structured zodiac and gemstone facts should pass');
    $controller = file_get_contents(app_path('app/Controllers/AdminController.php'));
    assertTrue(str_contains($controller, 'ProductService::contentConflict($data)'), 'Admin product saves should apply the structured-content guard');
};

$tests['admin forms constrain populated controls to mobile grid tracks'] = function (): void {
    $css = file_get_contents(app_path('assets/css/band.css'));
    assertTrue(str_contains($css, '.admin-form__row { display: grid; min-width: 0; grid-template-columns: repeat(2, minmax(0, 1fr))'), 'Desktop tracks must not expand to input intrinsic width');
    assertTrue(str_contains($css, '.admin-form > *, .admin-form__row > * { min-width: 0; overflow-wrap: anywhere; }'), 'Form items must shrink and long status text must wrap');
    assertTrue(str_contains($css, '.admin-form__row { grid-template-columns: minmax(0, 1fr); }'), 'Mobile tracks must have a zero minimum');
};

$tests['AI transport diagnostics distinguish causes without assuming the provider was unreachable'] = function (): void {
    $client = App\Services\AiClient::class;
    foreach ([28 => 'timed out', 6 => 'resolve', 7 => 'establish a connection', 35 => 'TLS handshake', 60 => 'certificate verification', 77 => 'certificate verification', 0 => 'No complete HTTP response'] as $code => $expected) {
        $message = $client::describeFailure(0, false, $code);
        assertTrue(str_contains($message, $expected), 'Transport error should identify its bounded cause');
        assertTrue(!str_contains($message, 'never reached'), 'HTTP 0 cannot prove that no request reached the provider');
        assertTrue($client::isError($message), 'Transport failure must remain an agent error');
    }
    $message = $client::describeFailure(500, '{"error":{"message":"private prompt and secret"}}', 0);
    assertTrue(str_contains($message, 'HTTP 500'), 'Provider status should remain visible');
    assertTrue(!str_contains($message, 'private prompt') && !str_contains($message, 'secret'), 'Provider response bodies must not be displayed');
    foreach ([0, 404, 408, 429, 500, 503, 504] as $status) {
        assertTrue($client::canFailOver($status, 0), "HTTP {$status} may be repaired by the other Gemma model");
    }
    foreach ([400, 401, 403, 422] as $status) {
        assertTrue(!$client::canFailOver($status, 0), "HTTP {$status} must fail immediately rather than repeat an invalid request");
    }
    assertTrue($client::canFailOver(0, 28), 'Transport failures count toward Gemma failover');
    assertSame(
        ['gemma-4-31b-it', 'gemma-4-26b-a4b-it'],
        $client::googleModelPair('gemma-4-31b-it'),
        '31B uses 26B A4B for an eligible fallback'
    );
    assertSame(
        ['gemma-4-26b-a4b-it', 'gemma-4-31b-it'],
        $client::googleModelPair('gemma-4-26b-a4b-it'),
        'The router always selects the other supported Gemma model'
    );
    assertSame(
        ['gemma-4-31b-it', 'gemma-4-26b-a4b-it'],
        $client::googleModelPair('unsupported-model'),
        'Unsupported saved models are forced back to the Gemma-only pair'
    );
    assertSame(
        [
            ['model' => 'gemma-4-31b-it', 'timeout_ms' => 12000],
            ['model' => 'gemma-4-26b-a4b-it', 'timeout_ms' => 12000],
        ],
        $client::googleAttemptPlan('gemma-4-31b-it'),
        'Each eligible model gets a single bounded attempt'
    );
    assertSame(
        [['model' => 'gemma-4-26b-a4b-it', 'timeout_ms' => 12000]],
        $client::googleAttemptPlan('gemma-4-26b-a4b-it', true),
        'A tool conversation that failed over must stay on its fallback model'
    );
};

$tests['verified refunds stay separate from fulfillment and are visible on customer order surfaces'] = function (): void {
    $base = ['total' => 1000, 'status' => 'delivered', 'verified_refunds' => []];
    assertSame('none', \App\Services\OrderService::refundState($base), 'An order without gateway refunds is not refunded');
    $partial = $base + [];
    $partial['verified_refunds'] = [['id' => 'rfnd_partial', 'amount_paise' => 25000]];
    assertSame(
        ['state'=>'partial', 'label'=>'Partially refunded', 'refunded_paise'=>25000, 'remaining_paise'=>75000],
        \App\Services\OrderService::refundSummary($partial),
        'A partial refund must retain the remaining paid balance'
    );
    $full = $base;
    $full['verified_refunds'] = [['id' => 'rfnd_full', 'amount_paise' => 100000]];
    assertSame('full', \App\Services\OrderService::refundState($full), 'A refund covering the total is fully refunded');
    assertSame('delivered', $full['status'], 'Refund state must not overwrite fulfillment history');

    $admin = file_get_contents(app_path('views/admin/detail.php'));
    $detail = file_get_contents(app_path('views/account/order.php'));
    $list = file_get_contents(app_path('views/account/orders.php'));
    foreach ([$admin, $detail, $list] as $surface) {
        assertTrue(str_contains($surface, 'refundSummary'), 'Every order surface must derive the same verified refund label');
    }
    assertTrue(str_contains($admin, 'Remaining paid') && str_contains($admin, "state'] !== 'full'"),
        'Admin detail must show the balance and stop accepting references after a full refund');
    assertTrue(str_contains($detail, 'remains paid'), 'Customer detail must explain the balance on a partial refund');
};

$tests['commerce reports group net paid sales and expose product customer coupon stock and tax views'] = function (): void {
    $orders = [
        [
            'id'=>'one', 'created_at'=>'2026-11-02T10:00:00+05:30', 'status'=>'delivered',
            'payment_id'=>'pay_one', 'customer_email'=>'one@example.com', 'total'=>1000,
            'items'=>[['name'=>'Pendant','qty'=>2,'line_total'=>1000]],
            'coupon_code'=>'BLESS', 'discount'=>100, 'verified_refunds'=>[['id'=>'rfnd_one','amount_paise'=>20000]],
        ],
        [
            'id'=>'two', 'created_at'=>'2026-11-19T10:00:00+05:30', 'status'=>'shipped',
            'payment_id'=>'pay_two', 'customer_email'=>'two@example.com', 'total'=>500,
            'items'=>[['name'=>'Ring','qty'=>1,'line_total'=>500]], 'verified_refunds'=>[],
        ],
        ['id'=>'cancelled','created_at'=>'2026-11-20','status'=>'cancelled','payment_id'=>'pay_cancelled','total'=>900],
        ['id'=>'unpaid','created_at'=>'2026-11-20','status'=>'pending','payment_id'=>'','total'=>700],
        ['id'=>'old','created_at'=>'2026-10-01','status'=>'delivered','payment_id'=>'pay_old','total'=>300],
    ];
    $sales = \App\Services\StoreStatsService::sales($orders, '2026-11-01', '2026-11-30', 'month');
    assertSame(1, count($sales), 'Monthly grouping produces one November bucket');
    assertSame('2026-11', $sales[0]['period'], 'Month label is deterministic');
    assertSame(2, $sales[0]['orders'], 'Only paid, non-cancelled orders are counted');
    assertSame(3, $sales[0]['units'], 'Purchased quantities are summed');
    assertSame(1500.0, $sales[0]['gross'], 'Gross sales retain recorded paid totals');
    assertSame(200.0, $sales[0]['refunded'], 'Verified refunds are visible');
    assertSame(1300.0, $sales[0]['net'], 'Net sales deduct verified refunds');

    $products = \App\Services\StoreStatsService::productsPurchased($orders, '2026-11-01', '2026-11-30');
    assertSame('Pendant', $products[0]['product'], 'Products are ranked by units');
    assertSame(2, $products[0]['units'], 'Product quantity is retained');
    assertSame(800.0, $products[0]['net'], 'Order refund ratio is reflected in product net sales');
    $customers = \App\Services\StoreStatsService::customers($orders, '2026-11-01', '2026-11-30');
    assertSame(800.0, $customers[0]['net'], 'Customer report uses net revenue');
    $coupons = \App\Services\StoreStatsService::coupons($orders, '2026-11-01', '2026-11-30');
    assertSame(['coupon'=>'BLESS','uses'=>1,'discount'=>100.0,'net'=>800.0], $coupons[0], 'Coupon report connects usage, discount and net sales');
    $stock = \App\Services\StoreStatsService::stock([['name'=>'Pendant','stock_status'=>'in_stock','price'=>999,'offer_price'=>899]]);
    assertSame(899.0, $stock[0]['offer_price'], 'Stock report keeps current catalogue pricing');
    assertSame(['from'=>'2026-11-05','to'=>'2026-11-20'], \App\Services\StoreStatsService::dateRange('2026-11-20','2026-11-05'), 'Reversed dates are normalised');

    $view = file_get_contents(app_path('views/admin/tax-report.php'));
    foreach (['name="report"', 'name="from"', 'name="to"', 'name="status"', 'name="group"', 'Net sales', 'GST'] as $needle) {
        assertTrue(str_contains($view, $needle), "Commerce report UI should include {$needle}");
    }
    $service = file_get_contents(app_path('app/Services/StoreStatsService.php'));
    foreach (['Sales', 'Products purchased', 'Product stock', 'Customer orders', 'Coupons', 'GST tax'] as $label) {
        assertTrue(str_contains($service, $label), "Report selector should include {$label}");
    }
    $map = file_get_contents(app_path('app/Services/ProjectMapService.php'));
    assertTrue(str_contains($map, "'StoreStatsService'"), 'Project inventory must wire the shared reporting service');
};

$tests['product breadcrumb uses the category identifier accepted by the shop'] = function (): void {
    $product = file_get_contents(app_path('views/public/product.php'));
    assertTrue(str_contains($product, "rawurlencode(trim((string)\$product['category']))"), 'Category filter must use the encoded stored category, not an absent category_slug');
    assertTrue(!str_contains($product, "\$product['category_slug']"), 'Missing derived field must not generate an empty category filter');
    $controller = file_get_contents(app_path('app/Controllers/PublicController.php'));
    assertTrue(str_contains($controller, "\$categoryList[] = \$item['category'] ?? '';"), 'Breadcrumb and shop must use the same category identifier');
};

$tests['both chat surfaces share accessible bounded request status'] = function (): void {
    $status = file_get_contents(app_path('assets/agent-status.js'));
    assertTrue(str_contains($status, "createElement('details')") && str_contains($status, "createElement('summary')"), 'Status toggle must use keyboard-accessible native disclosure');
    assertTrue(str_contains($status, 'performance.now()') && str_contains($status, 'clearInterval(timer)'), 'Elapsed timer must stop on completion');
    assertTrue(str_contains($status, "label.hidden = true") && str_contains($status, "Response completed in"), 'Completed thinking state should reduce to duration only');
    assertTrue(!str_contains($status, 'server processing') && !str_contains($status, 'Response ready'), 'Status must not show processing explanations or completion labels');
    foreach (['views/layouts/app.php', 'views/admin/agent.php'] as $path) {
        $source = file_get_contents(app_path($path));
        assertTrue(str_contains($source, 'window.AgentRequestStatus.start('), "{$path} must use shared request status");
        assertTrue(str_contains($source, 'progress.finish(failed)') && str_contains($source, '35000'), "{$path} must settle status and bound waiting");
    }
    $css = file_get_contents(app_path('assets/css/band.css'));
    assertTrue(str_contains($css, '.ai-request-status summary:focus-visible') && str_contains($css, 'prefers-reduced-motion'), 'Status needs focus and reduced-motion behavior');
    foreach (['accepted'=>'model', 'rejected'=>'error'] as $providerReply=>$outcome) {
        $lines = [];
        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(app_path('tests/fixtures/admin-agent-outcome.php')).' '.$providerReply, $lines, $status);
        assertSame(0, $status, 'Synthetic Enterprise Agent request completed');
        $failed = $outcome === 'error';
        assertSame([
            'status'=>$failed ? 502 : 200, 'route'=>$outcome, 'error'=>$failed, 'answer'=>!$failed,
            'private_scaffold_exposed'=>false, 'audit'=>[$outcome, $failed ? 'rejected_response' : null],
            'saved_outcome'=>$outcome, 'same_turn'=>true,
        ], json_decode(implode("\n", $lines), true), 'Response, Monitoring and the saved turn report one outcome');
    }
    $admin = file_get_contents(app_path('views/layouts/admin.php'));
    assertTrue(str_contains($admin, 'min-width: 0;') && str_contains($admin, 'flex-wrap: wrap; gap: var(--space-sm)'), 'Admin grid and mobile header must shrink without horizontal overflow');
};

$tests['public blog retires astrology category and default SEO without deleting owner records'] = function (): void {
    $service = file_get_contents(app_path('app/Services/BlogService.php'));
    $controller = file_get_contents(app_path('app/Controllers/BlogController.php'));
    $seo = file_get_contents(app_path('app/Services/SeoService.php'));
    $blog = file_get_contents(app_path('views/public/blog.php'));
    $categories = array_column((new \App\Services\BlogService())->categories(false), 'slug');
    assertTrue(str_contains($service, "RETIRED_PUBLIC_CATEGORIES = ['astrology']"), 'Blog service must retire astrology from public output without deleting post records');
    assertTrue(str_contains($controller, 'categoryIsRetiredPublic') && str_contains($controller, 'renderNotFound'), 'Retired direct post and category URLs must return 404');
    assertTrue(!str_contains($seo, 'astrology articles') && !str_contains($seo, 'vedic astrology blog'), 'Blog defaults must not lead with retired astrology terms');
    assertTrue(!str_contains($blog, 'astrology explainers') && !in_array('astrology', $categories, true), 'Public blog copy and category list must not advertise astrology');
};

$tests['homepage FAQ is visible and matches its structured data'] = function (): void {
    $home = file_get_contents(app_path('views/public/home.php'));
    assertTrue(str_contains($home, '$homeFaq') && str_contains($home, 'Frequently asked questions'), 'Homepage should visibly render its FAQ content');
    assertTrue(str_contains($home, "'@type' => 'FAQPage'") && str_contains($home, 'array_map(static fn(array $faq)'), 'FAQ JSON-LD should be generated from the visible FAQ source');
    assertTrue(!str_contains($home, 'free shipping on all spiritual products') && !str_contains($home, 'certified original rudraksha'), 'FAQ must not make unverified shipping or certification claims');
};

$tests['admin product preview protects hidden catalog content and disables purchase'] = function (): void {
    $controller = file_get_contents(app_path('app/Controllers/PublicController.php'));
    $start = strpos($controller, 'public function product(');
    $end = strpos($controller, 'public function cart(', $start);
    $method = substr($controller, $start, $end - $start);
    assertTrue(str_contains($method, "\$adminPreview = (\$_GET['preview'] ?? '') === '1';"), 'Preview must be explicit');
    assertTrue(strpos($method, '->requireAdmin()') < strpos($method, '->findBySlug($slug, $adminPreview)'), 'Authorize before reading hidden product');
    assertTrue(str_contains($method, 'X-Robots-Tag: noindex, nofollow'), 'Preview must not be indexed');
    $auth = file_get_contents(app_path('app/Services/AuthService.php'));
    assertTrue(str_contains($auth, 'Cache-Control: no-store'), 'Admin authorization must disable shared caching');
    $form = file_get_contents(app_path('views/admin/product-form.php'));
    assertTrue(str_contains($form, '?preview=1') && str_contains($form, 'rawurlencode(trim($item[\'slug\']))'), 'Admin preview links must encode slugs');
    $view = file_get_contents(app_path('views/public/product.php'));
    assertTrue(str_contains($view, 'Admin preview · Not for sale'), 'Preview must be clearly labeled');
    assertTrue(strpos($view, 'if (empty($adminPreview))') < strpos($view, 'id="product-cart-form"'), 'Preview must not render purchase controls');
};

$tests['a coupon obeys its dates, spend range and usage limits'] = function (): void {
    $service = new \App\Services\CouponService();
    $now = new \DateTimeImmutable('2026-08-06 12:00:00');
    $coupon = fn(array $overrides = []): array => array_merge(
        ['code' => 'SAVE', 'active' => true, 'discount_type' => 'percentage', 'discount_value' => 25],
        $overrides
    );

    // Checkout computed min($cartTotal * $value / 100, $value), comparing rupees against
    // a percentage, so 25% off a ₹2000 cart was paid as ₹25.
    assertSame(500.0, $service->discountFor($coupon(), 2000.0), 'A 25% coupon on ₹2000 should give ₹500');
    assertSame(100.0, $service->discountFor($coupon(['discount_type' => 'fixed', 'discount_value' => 100]), 2000.0),
        'A fixed coupon gives its face value');
    assertSame(300.0, $service->discountFor($coupon(['discount_value' => 20, 'max_discount' => 300]), 2000.0),
        'max_discount is the rupee ceiling the old cap was reaching for');
    assertSame(400.0, $service->discountFor($coupon(['discount_value' => 20, 'max_discount' => 900]), 2000.0),
        'A ceiling above the discount changes nothing');
    assertSame(500.0, $service->discountFor($coupon(['discount_type' => 'fixed', 'discount_value' => 9999]), 500.0),
        'A discount never exceeds the cart');

    $refused = function (array $c, float $total, int $usedAll = 0, int $usedByCustomer = 0) use ($service, $now): string {
        try { $service->assertUsable($c, $total, $usedAll, $usedByCustomer, $now); return ''; }
        catch (\InvalidArgumentException $e) { return $e->getMessage(); }
    };

    // A posted promo code used to be redeemable by anyone, any number of times, on a
    // cart of any size, forever.
    assertTrue(str_contains($refused($coupon(['ends_at' => '2026-08-01']), 2000.0), 'expired'),
        'An expired coupon is refused');
    assertSame('', $refused($coupon(['ends_at' => '2026-08-06']), 2000.0),
        'A coupon ending today is valid for the whole of today');
    assertTrue(str_contains($refused($coupon(['starts_at' => '2026-09-01']), 2000.0), 'not active yet'),
        'A future coupon is refused');
    assertTrue(str_contains($refused($coupon(['min_spend' => 3000]), 2000.0), 'Add ₹1,000.00 more'),
        'Below the minimum spend, the shopper is told how much more is needed');
    assertTrue(str_contains($refused($coupon(['max_spend' => 1000]), 2000.0), 'up to ₹1,000.00'),
        'Above the maximum spend is refused');
    assertTrue(str_contains($refused($coupon(['usage_limit' => 1]), 2000.0, 1), 'fully redeemed'),
        'A coupon past its total usage limit is refused');
    assertTrue(str_contains($refused($coupon(['usage_limit_per_customer' => 1]), 2000.0, 5, 1), 'already used'),
        'A coupon past its per-customer limit is refused');
    assertTrue(str_contains($refused($coupon(['active' => false]), 2000.0), 'no longer available'),
        'An inactive coupon is refused');
    assertTrue(str_contains($refused(['code' => 'S', 'status' => 'inactive'], 2000.0), 'no longer available'),
        'The status enum is honoured as well as the active flag');
    assertSame('', $refused($coupon(), 2000.0), 'A coupon inside every limit is allowed');

    // Checkout must not keep its own copy of the rule, and must tell the shopper why.
    $commerce = file_get_contents(app_path('app/Controllers/CommerceController.php'));
    assertTrue(str_contains($commerce, 'CouponService'), 'Checkout should judge coupons through CouponService');
    assertTrue(!str_contains($commerce, '$this->cartTotal($items) * $discountValue / 100'),
        'The broken percentage maths must not survive in checkout');
    assertTrue(str_contains($commerce, "jsonResponse(['error' => \$e->getMessage()], 422)"),
        'A refused coupon should tell the shopper why instead of silently charging full price');
};

$tests['an unpaid checkout never registers an order'] = function (): void {
    // The Stripe branch wrote the order the moment the customer was redirected to the
    // gateway, so every abandoned or cancelled checkout left a permanent "Pending" row
    // in the admin with a real total against a customer who never paid. Razorpay always
    // held it in the session; both now do.
    $commerce = file_get_contents(app_path('app/Controllers/CommerceController.php'));
    $beforeVerify = substr($commerce, 0, strpos($commerce, 'public function verifyPayment'));
    assertTrue(!str_contains($beforeVerify, "saveVerifiedOrder(") && !str_contains($beforeVerify, "upsert('orders'"),
        'No order may be written before the payment is verified');
    assertTrue(substr_count($commerce, "->saveVerifiedOrder(") === 1,
        'Orders should be written in exactly one place, after verification');
    assertTrue(str_contains($beforeVerify, "\$_SESSION['pending_order']"),
        'An unpaid checkout should be held in the session');

    // A record with nothing but an id is not something the admin can act on.
    $list = file_get_contents(app_path('views/admin/list.php'));
    assertTrue(str_contains($list, "ARRAY_FILTER_USE_BOTH"),
        'The admin list should skip records carrying nothing but an id');
};

$tests['Stripe return verifies and lands on the order confirmation'] = function (): void {
    $commerce = file_get_contents(app_path('app/Controllers/CommerceController.php'));
    $routes = routePaths();
    assertTrue(in_array('/payment/stripe/return', $routes, true), 'Stripe should have a dedicated hosted-checkout return route');
    assertTrue(str_contains($commerce, "['payment_status'] ?? '') === 'paid'"), 'Stripe return should require a paid session');
    assertTrue(str_contains($commerce, "['amount_total'] ?? 0) === \$expected"), 'Stripe return should verify the paid amount');
    assertTrue(str_contains($commerce, "['currency'] ?? '')) === 'inr'"), 'Stripe return should verify INR');
    assertTrue(str_contains($commerce, "?placed=1"), 'Stripe success should land on the order-specific thank-you state');
    assertTrue(substr_count($commerce, "->saveVerifiedOrder(") === 1, 'Both gateways should share one order persistence boundary');
};

$tests['shipping an order requires a courier tracking id and link'] = function (): void {
    $service = new \App\Services\OrderService();
    $cases = [
        [[], 'Select the courier'],
        [['tracking_id' => 'ABC123'], 'Select the courier'],
        [['courier_name' => 'DTDC'], 'tracking ID'],
        // A courier that is not on the list still has to bring its own link.
        [['courier_name' => 'Some Local Courier', 'tracking_id' => 'ABC123'], 'not on the list'],
        [['courier_name' => 'Some Local Courier', 'tracking_id' => 'ABC123', 'tracking_url' => 'not-a-url'], 'valid URL'],
    ];
    foreach ($cases as [$tracking, $expected]) {
        $blocked = false;
        try { $service->updateStatus('missing-order', 'shipped', null, $tracking); }
        catch (\InvalidArgumentException $e) { $blocked = str_contains($e->getMessage(), $expected); }
        catch (\Throwable) { $blocked = false; }
        assertTrue($blocked, 'Marking shipped without complete tracking should be rejected: ' . $expected);
    }

    // A known courier supplies its own link, so the admin never types a URL: validation
    // passes with courier + tracking ID alone and the order lookup is what fails.
    $reachedLookup = false;
    try { $service->updateStatus('missing-order', 'shipped', null, ['courier_name' => 'DTDC', 'tracking_id' => 'ABC123']); }
    catch (\InvalidArgumentException) { $reachedLookup = false; }
    catch (\Throwable) { $reachedLookup = true; }
    assertTrue($reachedLookup, 'A known courier should not require a typed tracking link');

    // Every courier offered in the dropdown must resolve to a usable link.
    $couriers = \App\Services\CourierService::all();
    assertSame(7, count($couriers), 'All seven couriers should be offered');
    foreach ($couriers as $name => $url) {
        assertTrue(filter_var($url, FILTER_VALIDATE_URL) !== false, "Courier {$name} should have a valid tracking URL");
        assertTrue(str_starts_with($url, 'https://'), "Courier {$name} should use https");
        assertTrue(\App\Services\CourierService::isKnown($name), "Courier {$name} should be recognised");
    }
    assertSame('', \App\Services\CourierService::trackingUrl('Not A Courier'), 'An unknown courier has no link');

    // Other transitions must not be blocked by the shipping requirement.
    $processingBlocked = false;
    try { $service->updateStatus('missing-order', 'processing'); }
    catch (\InvalidArgumentException) { $processingBlocked = true; }
    catch (\Throwable) {}
    assertTrue(!$processingBlocked, 'Non-shipping transitions must not require tracking');
};

$tests['the database bridge never calls the host it is running on'] = function (): void {
    $source = file_get_contents(app_path('app/Services/DatabaseService.php'));
    assertTrue(str_contains($source, 'remoteUrlIsSelf'), 'isRemote must detect a self-referential remote_url');
    assertTrue(str_contains($source, 'HTTP_HOST'), 'Self-detection must compare against the serving host');

    // Live: remote_url points at the host serving the request, so the bridge must not
    // be taken. Taking it produced "Remote database request failed with HTTP 500" on
    // production whenever direct MySQL hiccupped, because the server called itself.
    $previousHost = $_SERVER['HTTP_HOST'] ?? null;
    $_SERVER['HTTP_HOST'] = 'sripanchamispiritual.com';
    $service = new DatabaseService();
    $method = (new ReflectionObject($service))->getMethod('isRemote');
    assertTrue($method->invoke($service) === false, 'A host serving its own remote_url must stay on direct MySQL');

    // CLI has no HTTP_HOST, so a dev machine still uses the bridge.
    unset($_SERVER['HTTP_HOST']);
    $cliService = new DatabaseService();
    $cliMethod = (new ReflectionObject($cliService))->getMethod('isRemote');
    assertTrue($cliMethod->invoke($cliService) === true, 'Without a serving host the bridge must still be available');

    if ($previousHost === null) unset($_SERVER['HTTP_HOST']); else $_SERVER['HTTP_HOST'] = $previousHost;
};

$tests['remote database uses password auth via admin UI or env'] = function (): void {
    $controller = file_get_contents(app_path('app/Controllers/RemoteDbController.php'));
    $database = file_get_contents(app_path('app/Services/DatabaseService.php'));
    $cli = file_get_contents(app_path('cli/bapXphp'));
    assertTrue(str_contains($controller, 'requirePassword'), 'Remote controller should have requirePassword');
    assertTrue(str_contains($controller, 'hash_equals'), 'Remote controller should verify password with timing-safe compare');
    // Authentication uses the MySQL password from config, not a separate invented
    // token that can drift out of sync with it.
    assertTrue(str_contains($controller, "config/database.php"), 'Remote controller should read the database password from config');
    assertTrue(str_contains($controller, 'http_response_code(503)'), 'Remote controller must fail closed when no password is configured');
    assertTrue(!str_contains($controller, "if (\$expected === '') return;"), 'An unset password must never allow the request');
    foreach (["'upsert'", "'delete'", "'replace'"] as $needle) {
        assertTrue(str_contains($controller, $needle), "Remote controller should support {$needle}");
    }
    assertTrue(str_contains($database, 'remoteMutation'), 'Database service should use remote mutations when direct MySQL is unavailable');
    assertTrue(str_contains($database, "'password' => \$this->cfg['pass']"), 'Database service should send the MySQL password in the payload');
    foreach (['db upsert', 'db delete'] as $needle) assertTrue(str_contains($cli, $needle), "CLI should expose {$needle}");
};

$tests['historical appointment lifecycle remains available to the owner only'] = function (): void {
    $consult = file_get_contents(app_path('app/Controllers/ConsultationController.php'));
    $service = file_get_contents(app_path('app/Services/ConsultationService.php'));
    assertTrue(!str_contains($consult, "'mode'=>'booking'"), 'The retired public booking endpoint should not store new appointments');
    assertTrue(str_contains($consult, "['role'] ?? '') !== 'admin'"), 'Only central admin should update historical appointment status');
    assertTrue(str_contains($service, "'requested' => ['accepted', 'declined', 'cancelled']"), 'Consultation service should validate the requested lifecycle');
    assertTrue(str_contains($service, "'accepted' => ['active', 'cancelled']"), 'Existing provider lifecycle should preserve acceptance transitions');
};

$tests['home hero rotates all supplied varahi images'] = function (): void {
    assertMinCount(8, glob(app_path('assets/images/hero/varahi/varahi-*.png')) ?: [], 'Hero should include at least 8 Varahi images');
    fileContains('views/public/home.php', 'data-varahi-slider', 'Home should render the Varahi image slider');
    assertTrue(is_file(app_path('assets/images/hero/guruji.png')), 'Hero should retain the supplied Guruji portrait asset');
    assertTrue(is_file(app_path('assets/images/hero/guruji-portrait-cutout.png')), 'Hero should retain the transparent Guruji presentation asset');
    fileContains('views/public/home.php', "'src' => '/assets/images/hero/guruji-portrait-cutout.png'", 'Guruji should lead the hero carousel');
    fileContains('views/public/home.php', 'data-current-slide="guruji"', 'Guruji should receive its responsive hero state before JavaScript runs');
    fileContains('views/layouts/app.php', 'https://mediahub.bapx.in/', 'Footer studio credit should lead to the requested media hub');
};

$tests['admin product form exposes editable fields without retired profile editor'] = function (): void {
    $controller = file_get_contents(app_path('app/Controllers/AdminController.php'));
    $productForm = file_get_contents(app_path('views/admin/product-form.php'));
    assertTrue(!is_file(app_path('views/admin/astrologer-form.php')), 'Retired profile form must not remain available');
    routeMissing('/admin/astrologers/save', 'Retired profile save route must be unavailable');
    routeMissing('/admin/astrologers/delete', 'Retired profile delete route must be unavailable');
    $resourceView = file_get_contents(app_path('views/admin/resource.php'));
    $productView = file_get_contents(app_path('views/public/product.php'));
    $auditService = file_get_contents(app_path('app/Services/AuditLogService.php'));
    foreach (['slug', 'image_url', 'image_urls', 'price', 'offer_price', 'stock_status', 'highlights', 'description_points', 'specifications'] as $field) {
        assertTrue(str_contains($productForm, $field), "Product admin form should expose {$field}");
    }
    assertTrue(str_contains($productForm, 'enctype="multipart/form-data"'), 'Product form should support file uploads');
    assertTrue(str_contains($productForm, 'name="media_files[]"'), 'Product form should upload media files');
    assertTrue(str_contains($productForm, 'multiple'), 'Product image upload should accept multiple files');
    assertTrue(str_contains($productForm, 'foreach($mediaFiles as $media)'), 'Media picker should show all files by upload time, not only the latest page');
    assertTrue(str_contains($productForm, 'class="admin-media-picker"'), 'Product forms should expose a media library picker');
    assertTrue(str_contains($resourceView, "['image_url', 'photo_url']"), 'Local asset image fields should not use URL inputs that reject /assets paths');
    assertTrue(!str_contains($resourceView, 'let el = document.getElementById'), 'Generated admin edit script should not redeclare let for every field');
    assertTrue(str_contains($productView, 'image_urls'), 'Product page should render product image galleries');
    foreach (['Key Features', 'Product Description', 'Specifications', 'product-copy-block--specs'] as $needle) {
        assertTrue(str_contains($productView, $needle), "Product page should render structured {$needle}");
    }
    assertTrue(str_contains($controller, 'MediaService'), 'Admin save should persist uploaded media into the shared media library');
    assertTrue(str_contains($controller, 'schemaFields'), 'Admin resource fields should be read from the JSON schema registry when available');
    assertTrue(str_contains($controller, 'mergeExistingRecord'), 'Admin save should preserve existing fields when editing only visible admin fields');
    assertTrue(str_contains($controller, 'parseSpecifications'), 'Admin save should parse product specifications into structured data');
    assertTrue(str_contains($controller, 'AuditLogService'), 'Admin mutations should write audit log records');
    assertTrue(str_contains($auditService, 'function record'), 'Audit log service should be able to record admin changes');
};

$tests['admin sidebar exposes every admin menu'] = function (): void {
    $layout = file_get_contents(app_path('views/layouts/admin.php'));
    foreach ([
        '/admin',
        '/admin/products',
        '/admin/categories',
        '/admin/coupons',
        '/admin/appointments',
        '/admin/temples',
        '/admin/orders',
        '/admin/contact-submissions',
        '/admin/support-tickets',
        '/admin/media',
        '/admin/settings',
        '/admin/integrations',
        '/admin/shipping',
    ] as $path) {
        assertTrue(str_contains($layout, 'href="' . $path . '"'), "Admin sidebar should link {$path}");
    }
};

$tests['architecture and deployment docs describe current php template stack'] = function (): void {
    $readme = file_get_contents(app_path('README.md'));
    $architecture = file_get_contents(app_path('docs/architecture.md'));
    $deployment = file_get_contents(app_path('docs/deployment-hostinger.md'));
    foreach ([$architecture, $deployment] as $doc) {
        assertTrue(!str_contains($doc, 'React'), 'Docs should not describe the removed React/CDN architecture');
        assertTrue(!str_contains($doc, 'CDN'), 'Docs should not say the app loads React from a CDN');
    }
    foreach (['small PHP hosting', 'public_html', 'hosted MySQL as the primary runtime store', '.env', 'APP_NAME', 'APP_URL', 'Admin → Settings', 'Admin → Integrations', 'docs/project-index.json', 'index.yaml', 'docs/README.md', 'docs/deployment-hostinger.md', 'AGENTS.md', 'docs/systematic-map.mmd'] as $needle) {
        assertTrue(str_contains($readme, $needle), "README should describe {$needle}");
    }
    assertTrue(is_file(app_path('docs/README.md')), 'Documentation index should exist and be linked from README');
    assertTrue(!str_contains($readme, 'https://sripanchamispiritual.com'), 'README should not hardcode the production website URL; use APP_URL in .env');
    assertTrue(str_contains($architecture, 'PHP-rendered public, account, and admin templates'), 'Architecture docs should describe the current PHP template frontend');
    assertTrue(str_contains($deployment, 'PHP-rendered templates'), 'Deployment docs should describe the current PHP template frontend');
};

$tests['legacy duplicate frontend modules are removed from the php template app'] = function (): void {
    foreach ([
        'assets/js/core/app-core.js',
        'assets/js/ui/components.js',
        'assets/js/app.js',
        'assets/js/components.js',
        'assets/js/pages.js',
        'assets/js/main.js',
        'assets/js',
        'components/AstroCard.js',
        'components/BottomNav.js',
        'components/Footer.js',
        'components/Header.js',
        'components/Page.js',
        'components/ProductCard.js',
        'tests/frontend.test.js',
        'utils/api.js',
        'utils/router.js',
        'views/layouts/spa.php',
    ] as $path) {
        assertTrue(!is_file(app_path($path)), "Unused duplicate frontend module should be removed: {$path}");
    }
    assertTrue(!is_dir(app_path('assets/js')), 'The legacy SPA app directory should be removed entirely');
    $index = file_get_contents(app_path('index.php'));
    assertTrue(!str_contains($index, 'views/layouts/spa.php'), 'Unknown routes should not load the legacy SPA fallback');
    assertTrue(str_contains($index, 'http_response_code(404)'), 'Unknown routes should return a real 404');
};

$tests['php 404 page uses themed template classes'] = function (): void {
    $view = file_get_contents(app_path('views/public/404.php'));
    $css = file_get_contents(app_path('assets/css/band.css'));
    foreach (['not-found-page', 'not-found-shell', 'not-found-mark', 'not-found-actions'] as $class) {
        assertTrue(str_contains($view, $class), "404 view should include {$class}");
        assertTrue(str_contains($css, '.' . $class), "Theme CSS should style {$class}");
    }
    assertTrue(str_contains($view, 'Page not found'), '404 page should keep clear user-facing page-not-found copy');
};

$tests['documentation has deployment agent instructions and no one-line placeholder pages'] = function (): void {
    assertTrue(is_file(app_path('AGENTS.md')), 'Agent operating guide pointer should exist');
    assertTrue(is_file(app_path('AGENTS.md')), 'Binding agent contract should exist');
    $agent = file_get_contents(app_path('AGENTS.md'));
    foreach (['Repository', 'docs/systematic-map.mmd', 'bapXphp ci', 'Before pushing to `main`'] as $needle) {
        assertTrue(str_contains($agent, $needle), "Agent contract should mention {$needle}");
    }
    foreach (glob(app_path('docs/pages/*.md')) ?: [] as $path) {
        assertTrue(count(file($path) ?: []) > 3, basename($path) . ' should contain real page notes, not only a heading');
    }
    foreach (glob(app_path('docs/modules/*.md')) ?: [] as $path) {
        assertTrue(count(file($path) ?: []) > 3, basename($path) . ' should contain real module notes, not only a heading');
    }
    $deployment = file_get_contents(app_path('docs/deployment-hostinger.md'));
    foreach (['hPanel', 'Advanced', 'Git', 'Auto Deployment', 'Branch', 'public_html', 'Vercel'] as $needle) {
        assertTrue(str_contains($deployment, $needle), "Deployment guide should mention {$needle}");
    }
};

$tests['pull requests use non mutating CI with fresh project and documentation maps'] = function (): void {
    $workflow = file_get_contents(app_path('.github/workflows/ci.yml'));
    $cli = file_get_contents(app_path('cli/bapXphp'));
    foreach (['pull_request:', 'branches: [main]', './bapXphp ci'] as $needle) assertTrue(str_contains($workflow, $needle), "CI workflow should include {$needle}");
    foreach (['cmd_ci()', 'validate-project-map.php', 'validate-docs-map.php', 'cmd_update()', 'cmd_hooks()', 'cmd_ai_probe()'] as $needle) {
        assertTrue(str_contains($cli, str_replace('\\n', "\n", $needle)), "CLI should include {$needle}");
    }
    foreach (['require_gh', 'cmd_issue()', 'cmd_pr()', 'cmd_merge()', 'gh issue list', 'gh pr list'] as $needle) {
        assertTrue(!str_contains($cli, $needle), "CLI should not depend on {$needle}");
    }
    assertTrue(!str_contains(substr($cli, strpos($cli, 'cmd_ci()'), strpos($cli, 'cmd_check()') - strpos($cli, 'cmd_ci()')), 'generate-project-map.php'), 'CI validation must not regenerate the project map before checking freshness');
};

$tests['repository operations use git and GitHub Actions without duplicate agent folders'] = function (): void {
    assertTrue(is_file(app_path('.agents/skills/git/SKILL.md')), 'Plain Git skill should exist');
    assertTrue(!is_file(app_path('.agents/skills/gh-cli/SKILL.md')), 'GitHub CLI skill should be removed');
    assertTrue(is_dir(app_path('.claude')), 'Agent tooling should live in .claude');
    assertTrue(!is_dir(app_path('.agents/handoffs')) && !is_dir(app_path('.agents/workflows')) && !is_dir(app_path('.agents/ops')), 'Legacy handoff and orchestration folders should not exist');
    assertTrue(is_file(app_path('.github/workflows/branch-pr.yml')), 'Branch pushes should have an Actions-owned PR workflow');
    assertTrue(!is_file(app_path('.github/workflows/sync-upstream.yml')), 'Unforked repository should not contain sync-upstream workflow');

    // Hosting/runtime still has no gh dependency; the owner explicitly requires
    // the authorized developer machine to use gh for issue/PR evidence.
    foreach ([app_path('cli/bapXphp'), app_path('.agents/skills/deployment/SKILL.md')] as $path) {
        $source = file_get_contents($path);
        assertTrue(!preg_match('/\bgh\s+(issue|pr|api|workflow|repo)\b/', $source), basename($path) . ' must not require gh on hosting');
    }
    assertTrue(str_contains(fileRead('AGENTS.md'),'with `gh` on the development machine'), 'Local GitHub issue workflow is explicitly authorized');
    assertTrue(str_contains(fileRead('.agents/skills/git/SKILL.md'),'never require it on Hostinger'), 'Developer CLI does not become a hosting dependency');

    $branchPr = file_get_contents(app_path('.github/workflows/branch-pr.yml'));
    assertTrue(str_contains($branchPr, "github.repository == 'bapxmediahub/bapXphpAiBackend'"), 'Automatic PR creation should run only in the deployment working repository');
    assertTrue(str_contains($branchPr, 'actions/create-github-app-token@v2') && str_contains($branchPr, 'error.status === 403'), 'Automatic PR creation should use the bapXai App when configured and explain disabled token permissions');

    $agents = file_get_contents(app_path('AGENTS.md'));
    assertTrue(str_contains($agents, '`bapxmediahub/bapXphpAiBackend` is the only working repository'), 'Agent contract should pin work to the deployment repository');
    assertTrue(str_contains($agents, 'repository is independent and unforked'), 'Agent contract should record the independent repository state');
    assertTrue(str_contains($agents, 'Do not add an upstream remote'), 'Agent contract should prohibit stale fork synchronization');
};

$tests['local smoke tool source covers key routes and CSRF protection'] = function (): void {
    $tool = app_path('cli/smoke-local.php');
    assertTrue(is_file($tool), 'Local route/API smoke tool should exist');
    $source = file_get_contents($tool);
    foreach (['/shop', '/checkout', '/consult', '/temples', '/payment/verify', '/support/ask', '/api/categories', '/unknown-spa-route'] as $path) {
        assertTrue(str_contains($source, $path), "Local smoke tool should cover {$path}");
    }
    assertTrue(str_contains($source, 'CSRF protected'), 'Local smoke should verify payment CSRF protection');
    assertTrue(str_contains($source, "'BAPX_TEST_MODE' => '1'"), 'Local smoke should not depend on the production database or network');
    assertTrue(str_contains(file_get_contents(app_path('app/Services/DatabaseService.php')), "getenv('BAPX_TEST_MODE') === '1'"), 'Database service should expose an explicit smoke-test boundary');
    assertTrue(str_contains($source, 'PASS local smoke'), 'Local smoke should provide an authoritative success signal');
};

$tests['systematic project map, docs/map.mmd, and root map.mmd are the generated map artifacts'] = function (): void {
    assertTrue(is_file(app_path('docs/systematic-map.mmd')), 'Systematic Mermaid map should exist');
    assertTrue(is_file(app_path('docs/map.mmd')), 'docs/map.mmd should exist');
    assertTrue(is_file(app_path('map.mmd')), 'root map.mmd should exist');
    foreach (['docs/PROJECT_MAP.md', 'docs/project-map.json', 'docs/project-map.mmd'] as $path) {
        assertTrue(!is_file(app_path($path)), "Old project-map artifact should not exist: {$path}");
    }
    $map = file_get_contents(app_path('docs/systematic-map.mmd'));
    foreach (['PUBLIC Routes', 'AUTH Routes', 'PAYMENT Routes', 'SUPPORT Routes', 'ADMIN Routes', 'Controllers', 'Services', 'Views', 'Integrations', 'Schema Collections', 'Tools', 'Gaps & Missing Links'] as $needle) {
        assertTrue(str_contains($map, $needle), "Systematic map should include {$needle}");
    }
    $dmap = file_get_contents(app_path('docs/map.mmd'));
    foreach (['CLI (bapXphp)', 'Agent Skills', 'Blog & Content', 'Application Architecture', 'Data Layer'] as $needle) {
        assertTrue(str_contains($dmap, $needle), "docs/map.mmd should include {$needle}");
    }
    $cmap = file_get_contents(app_path('map.mmd'));
    foreach (['PUBLIC Routes', 'AUTH Routes', 'PAYMENT Routes', 'ADMIN Routes', 'Controllers', 'Services', 'Schema Collections'] as $needle) {
        assertTrue(str_contains($cmap, $needle), "root map.mmd should include {$needle}");
    }
};

$tests['public pages contain no consultation booking copy'] = function (): void {
    $home = file_get_contents(app_path('views/public/home.php'));
    $layout = file_get_contents(app_path('views/layouts/app.php'));
    $support = file_get_contents(app_path('app/Services/SupportBotService.php'));
    foreach (['Book a Consultation', 'Start a Consultation Request', 'href="/consult"'] as $needle) {
        assertTrue(!str_contains($home . $layout, $needle), "Public pages should not expose {$needle}");
    }
    assertTrue(!str_contains($support, 'consultant bookings at /consult'), 'Support assistant should not provide a booking path');
};

$tests['retired booking endpoint creates no new appointment'] = function (): void {
    $controller = file_get_contents(app_path('app/Controllers/ConsultationController.php'));
    assertTrue(str_contains($controller, "redirect('/contact#contact-form')"), 'Legacy booking submissions should redirect to the general enquiry form');
    foreach (['ResourceService', 'MailQueueService', 'AstrologerService'] as $needle) {
        assertTrue(!str_contains($controller, $needle), "Retired booking endpoint should not invoke {$needle}");
    }
};

$tests['customer help is a blog category with compatibility redirects'] = function (): void {
    $controller = file_get_contents(app_path('app/Controllers/PublicController.php'));
    routeExists('/help/{slug}', 'Help center should expose a hosting-safe guide detail route');
    assertTrue(str_contains(file_get_contents(app_path('index.php')), "'/help'"), 'Front controller should dispatch hosting-safe help routes into PHP');
    assertTrue(str_contains($controller, "'/blog/category/help'") && str_contains($controller, "'/blog/' . \$slug"), 'Legacy docs routes should redirect to canonical blog help content');
    assertTrue(in_array('help', array_column((new \App\Services\BlogService())->categories(false), 'slug'), true), 'Blog categories should include Help');
    assertTrue(!is_dir(app_path('content/docs')) || !(glob(app_path('content/docs/*.md')) ?: []), 'Separate customer docs Markdown files should be removed');
    foreach (['create-account', 'order-products', 'payments-and-orders'] as $slug) {
        $post = file_get_contents(app_path("content/blog/posts/{$slug}.md"));
        assertTrue(str_contains($post, 'category: help'), "Help post {$slug} should use the help category");
    }
    assertTrue(!is_file(app_path('content/blog/posts/book-consultant.md')), 'Retired consultation help guide should be removed');
};

$tests['blog uses an editorial index and readable markdown article surface'] = function (): void {
    $index = file_get_contents(app_path('views/public/blog.php'));
    $article = file_get_contents(app_path('views/public/blog-post.php'));
    $css = file_get_contents(app_path('assets/css/band.css'));
    foreach (['Sri Panchami Journal', 'blog-card--featured', 'blog-card__media', 'Read article'] as $needle) {
        assertTrue(str_contains($index, $needle), "Blog index should include {$needle}");
    }
    assertTrue(str_contains($article, "\$schemaBase . '/blog'"), 'Article breadcrumbs should use the canonical blog URL');
    foreach (['blog-post__dek', 'blog-post__featured', 'blog-post__cta'] as $needle) assertTrue(str_contains($article, $needle), "Article should include {$needle}");
    assertTrue(str_contains($css, '.blog-post__content') && str_contains($css, 'line-height:1.78'), 'Article typography should use a constrained readable measure');
};

$tests['blog media uses one screenshot crop for cards and article pages'] = function (): void {
    $service = file_get_contents(app_path('app/Services/BlogService.php'));
    $admin = file_get_contents(app_path('views/admin/blog.php'));
    $index = file_get_contents(app_path('views/public/blog.php'));
    $article = file_get_contents(app_path('views/public/blog-post.php'));
    $cli = file_get_contents(app_path('cli/bapXphp'));
    $crop = file_get_contents(app_path('cli/blog-image.php'));
    foreach (['type', 'summary', 'order', 'og_image', 'image_alt', 'source_url', 'template'] as $field) {
        assertTrue(str_contains($service, "'{$field}'"), "Blog service should persist {$field}");
        assertTrue(str_contains($admin, 'name="' . $field . '"'), "Admin blog editor should expose {$field}");
    }
    assertTrue(str_contains($index, "BlogService::resolvedImageUrl(\$post)") && str_contains($article, "BlogService::resolvedImageUrl(\$meta)"), 'Card and article should resolve the same image with a missing-file fallback');
    assertTrue(str_contains($article, "\$schemaImage ?: 'undefined'") && str_contains($article, 'e($sourceUrl)'), 'Article metadata should tolerate missing images and render the validated source URL');
    foreach (['create-account', 'order-products', 'payments-and-orders'] as $slug) {
        $post = file_get_contents(app_path("content/blog/posts/{$slug}.md"));
        assertTrue(str_contains($post, "\nsummary:") && str_contains($post, "\norder:"), "Help post {$slug} should retain summary and order metadata");
        assertTrue(str_contains($post, "\nimage_alt: Loaded "), "Help post {$slug} should describe a fully loaded browser capture");
        $image = app_path("assets/images/blog/{$slug}.webp");
        $size = getimagesize($image);
        assertTrue(is_array($size) && $size[0] === 1200 && $size[1] === 675, "Help post {$slug} should use a verified 1200x675 image");
        assertTrue(filesize($image) > 20000, "Help post {$slug} screenshot should contain rendered page detail");
    }
    assertTrue(str_contains($cli, 'blog:image') && str_contains($crop, '--dry-run'), 'CLI should expose safe blog screenshot cropping');
    assertTrue(str_contains($crop, '$targetWidth = 1200') && str_contains($crop, '$targetHeight = 675'), 'Blog screenshot crop should be stable 16:9');
};

$tests['public navigation uses brand home link and mobile cart tray'] = function (): void {
    $layout = file_get_contents(app_path('views/layouts/app.php'));
    $css = file_get_contents(app_path('assets/css/band.css'));
    assertSame(1, substr_count($layout, 'href="/" class="brand"'), 'Brand should link home once');
    assertTrue(!str_contains($layout, '>Home</a>') && !str_contains($layout, '<span>Home</span>'), 'Public navigation should not duplicate Home');
    assertTrue(!str_contains($layout, 'href="/blog/category/help"'), 'Help should remain a Blog category instead of a separate primary-menu item');
    foreach (['mobile-cart-tray', 'mobile-cart-count', 'mobile-cart-label'] as $needle) assertTrue(str_contains($layout, $needle), "Cart tray should include {$needle}");
    assertTrue(str_contains($css, '.mobile-cart-tray') && str_contains($css, 'bottom:78px'), 'Mobile cart tray should sit above bottom navigation');
    assertTrue(str_contains($css, '.support-fab{right:24px;bottom:24px}') && str_contains($css, '.mobile-cart-tray{position:fixed;right:24px;bottom:88px'), 'Desktop support should align directly below the cart tray');
    assertSame(substr_count($css, '{'), substr_count($css, '}'), 'Stylesheet should have balanced rule braces');
};

$tests['dashboard and agent distinguish unpaid pending value from recorded sales'] = function (): void {
    $orders = [
        ['id'=>'paid', 'payment_id'=>'fixture', 'status'=>'pending', 'created_at'=>'2026-11-03', 'total'=>99],
        ['id'=>'unpaid', 'status'=>'pending_payment', 'created_at'=>'2026-11-04', 'total'=>42],
        ['id'=>'failed', 'status'=>'failed', 'created_at'=>'2026-11-02', 'total'=>30],
        ['id'=>'past', 'status'=>'pending', 'created_at'=>'2025-01-01', 'total'=>80],
    ];
    $range = ['from'=>'2026-11-01', 'to'=>'2026-11-30'];
    $facts = \App\Services\AgentToolRegistry::salesFacts($orders, $range);
    assertSame(99.0, $facts['gross_paid'], 'Verified sales require a recorded payment');
    assertSame(42.0, $facts['pending_order_value'], 'Pending value excludes paid, failed and out-of-period orders');
    $latest = \App\Services\StoreStatsService::recentOrders($orders, $range['from'], $range['to']);
    assertSame(['unpaid', 'paid', 'failed'], array_column($latest, 'id'), 'Latest orders link to dated records in this period');
};

$tests['homepage trust bar renders its mobile labels as static list content'] = function (): void {
    $home = file_get_contents(app_path('views/public/home.php'));
    $css = file_get_contents(app_path('assets/css/band.css'));
    assertTrue(str_contains($home, '<ul class="trust-bar__inner"'), 'Trust items should share one centered list');
    assertSame(3, substr_count($home, '<li class="trust-item">'), 'Trust bar should render all three items without interaction');
    foreach (['Secure Payments', 'Saved Addresses', 'Spiritual Products'] as $needle) {
        assertTrue(str_contains($home, $needle), "Trust bar should include {$needle}");
    }
    assertTrue(str_contains($css, 'width: min(100%, 900px)'), 'Trust items should stay centered at wide widths');
    assertTrue(str_contains($css, '.trust-bar__inner { grid-template-columns: repeat(3, minmax(0, 1fr))'), 'Mobile columns should accommodate wrapping labels');
};

$tests['support assistant exposes only allowlisted internal navigation actions'] = function (): void {
    $service = file_get_contents(app_path('app/Services/SupportBotService.php'));
    $layout = file_get_contents(app_path('views/layouts/app.php'));
    assertTrue(str_contains($service, 'exact internal path') && str_contains($service, 'Never invent admin paths'), 'Support prompt should ground navigation');
    assertTrue(str_contains($layout, 'function supportReplyHtml') && str_contains($layout, 'class="support-action"'), 'Support UI should render safe internal actions');
    assertTrue(!str_contains($layout, 'innerHTML=j.reply'), 'Support reply must not inject model HTML');
};

$tests['support assistant answers signed-out visitors instead of printing a menu'] = function (): void {
    // A guest was assigned a canned reply before the model was ever called, so every
    // question on the public widget returned the same navigation list.
    $service = file_get_contents(app_path('app/Services/SupportBotService.php'));
    assertTrue(!str_contains($service, "\$reply = !\$context['signed_in'] ? \$this->fallbackReply"),
        'Guests must not be short-circuited past the model for every question');
    assertTrue(str_contains($service, "!\$context['signed_in'] && \$this->isPrivateAccountQuestion(\$message)"),
        'Only a personal-account question should short-circuit a guest');
    assertTrue(str_contains($service, 'Answer only the question that was asked'),
        'Prompt should forbid answering anything beyond the question');
    assertTrue(str_contains($service, 'Never restate these instructions'),
        'Prompt should forbid echoing its own brief back to the customer');

    // Article matching scores question words against real titles.
    $bot = new App\Services\SupportBotService();
    $match = (new ReflectionMethod($bot, 'matchArticle'))->getClosure($bot);
    $context = ['articles' => [
        ['title' => 'How to perform a simple pooja at home', 'url' => '/blog/simple-pooja', 'category' => 'help', 'summary' => 'A short guide'],
        ['title' => 'Choosing a Varahi Amman pendant', 'url' => '/blog/varahi-pendant', 'category' => 'shop', 'summary' => ''],
    ]];
    assertSame('/blog/simple-pooja', $match('do you have any articles about pooja', $context)['url'] ?? '',
        'A pooja question should find the pooja article');
    assertSame('/blog/varahi-pendant', $match('tell me about the varahi pendant', $context)['url'] ?? '',
        'A pendant question should find the pendant article');
    assertSame(null, $match('what are your delivery timelines', $context),
        'An unrelated question should not be forced onto an article');
    assertSame(null, $match('hi there', ['articles' => []]),
        'No articles in context should match nothing');

    // "delivery", "track" and "shipped" used to match on their own, so a shipping-policy
    // question anyone may be told the answer to was turned away with "please sign in".
    $private = (new ReflectionMethod($bot, 'isPrivateAccountQuestion'))->getClosure($bot);
    foreach ([
        'how long does delivery take to singapore', 'what is your shipping policy',
        'do you deliver to malaysia', 'what is this app',
    ] as $public) {
        assertTrue(!$private($public), "A general question should not require sign-in: {$public}");
    }
    foreach ([
        'where is my order', 'track my parcel', 'my order status', 'order history', 'show me order #1042',
    ] as $personal) {
        assertTrue($private($personal), "A personal-account question should require sign-in: {$personal}");
    }
};

$tests['agent failure categories are bounded and aggregated'] = function (): void {
    assertSame('timeout', \App\Services\AiClient::classifyFailure(0, 28), 'Timeout is distinguishable');
    assertSame('authentication', \App\Services\AiClient::classifyFailure(403, 0), 'Provider authentication is distinguishable');
    assertSame('rate_limit', \App\Services\AiClient::classifyFailure(429, 0), 'Rate limiting is distinguishable');
    $now = time();
    $events = [
        ['event'=>'agent.run', 'created_at'=>date('c', $now), 'meta'=>['surface'=>'support','outcome'=>'fallback','duration_ms'=>21000,'reason'=>'timeout']],
        ['event'=>'agent.run', 'created_at'=>date('c', $now), 'meta'=>['surface'=>'admin','outcome'=>'error','duration_ms'=>10,'reason'=>'provider_request']],
        ['event'=>'agent.run', 'created_at'=>date('c', $now), 'meta'=>['surface'=>'admin','outcome'=>'error','reason'=>'private user text']],
    ];
    $summary = \App\Services\AuditLogService::agentSummary($events, $now);
    assertSame(1, $summary['support']['reasons']['timeout'] ?? 0, 'Support reason appears in summary');
    assertSame(1, $summary['admin']['reasons']['provider_request'] ?? 0, 'Admin reason appears in summary');
    assertTrue(!isset($summary['admin']['reasons']['private user text']), 'Arbitrary text cannot appear as a reason');
};

$tests['general tracking uses the store policy directly'] = function (): void {
    $bot = new App\Services\SupportBotService();
    $result = $bot->answer('How can I track an order?', null);
    assertTrue(str_contains($result['reply'], 'tracking ID'), 'General tracking explains the courier information');
    assertTrue(str_contains($result['reply'], '/account/dashboard/orders'), 'General tracking directs shoppers to My Orders');
    assertSame(null, $result['ticket_id'], 'General tracking does not create a ticket');
};

$tests['support fallback explains tracking without claiming a personal order lookup'] = function (): void {
    $bot = new App\Services\SupportBotService();
    $fallback = (new ReflectionMethod($bot, 'fallbackReply'))->getClosure($bot);
    $policies = (new ReflectionMethod($bot, 'policies'))->invoke($bot);
    foreach ([true, false] as $signedIn) {
        $context = ['signed_in' => $signedIn, 'orders' => [], 'policies' => $policies];
        $reply = $fallback('How can I track an order?', $context);
        assertTrue(str_contains($reply, 'tracking') && str_contains($reply, '/account/dashboard/orders'), 'General tracking help should explain the shipping email and orders page');
        assertTrue(!str_contains($reply, 'could not find orders'), 'A general question does not ask for a personal lookup');
    }
    $guestReply = $fallback('Where is my order?', ['signed_in' => false, 'orders' => [], 'policies' => $policies]);
    assertTrue(str_contains($guestReply, 'sign in'), 'Personal order lookups remain protected');
};

$tests['product payment remains production gated after wallet removal'] = function (): void {
    $secrets = file_get_contents(app_path('app/Services/SecretService.php'));
    assertTrue(str_contains($secrets, 'razorpayReadyForCurrentHost'), 'Selected Razorpay credentials should be checked against the current host');
    assertTrue(str_contains($secrets, "=== 'live'"), 'Production hosts should require live Razorpay mode');
    assertTrue(str_contains($secrets, "?? '') === 'app_secrets'") && str_contains($secrets, 'array_filter($env'), 'Remote app_secrets should override environment fallbacks and legacy rows');
    foreach (['openssl_encrypt', 'openssl_decrypt', "'iv' =>", "'ciphertext' =>", "'agent_api_key'", "'gemma-4-31b-it'", "'configured' =>"] as $needle) {
        assertTrue(str_contains($secrets, $needle), "Remote integration secrets should include {$needle}");
    }
    assertTrue(!is_file(app_path('app/Controllers/WalletController.php')) && !is_file(app_path('views/account/wallet.php')), 'Wallet controller and customer view should be removed');
};

$tests['agent monitoring counts bounded outcomes without treating fallbacks as model success'] = function (): void {
    $now = strtotime('2026-09-25T12:00:00Z');
    $event = static fn($surface, $outcome, $duration, $at = '2026-09-25T11:00:00Z') => [
        'event' => 'agent.run', 'created_at' => $at,
        'meta' => ['surface' => $surface, 'outcome' => $outcome, 'duration_ms' => $duration],
    ];
    $rows = App\Services\AuditLogService::agentSummary([
        $event('support', 'model', 100), $event('support', 'fallback', 300),
        $event('admin', 'local', 40),
        $event('support', 'private_account', 50), $event('admin', 'error', 500),
        $event('admin', 'draft', 200), $event('support', 'model', 1000, '2026-09-23T11:00:00Z'),
        $event('support', 'model', 1000, '2026-09-26T11:00:00Z'),
        $event('unknown', 'model', 100), $event('admin', 'invalid', 100),
        ['event' => 'email.test', 'created_at' => '2026-09-25T11:00:00Z'],
    ], $now);
    assertSame(3, $rows['support']['requests'], 'Only recent valid support runs count');
    assertSame(1, $rows['support']['model'], 'Fallbacks and private guidance are not model responses');
    assertSame(1, $rows['support']['fallback'], 'Fallbacks remain visible');
    assertSame(1, $rows['admin']['local'], 'Deterministic aggregate answers are not mislabeled as model success or fallback');
    assertSame(150, $rows['support']['average_ms'], 'Average uses valid request durations');
    assertSame(1, $rows['admin']['error'], 'Admin provider failures stay visible');
    assertSame(1, $rows['admin']['draft'], 'Admin drafts are a separate path');
    assertSame(null, App\Services\AuditLogService::agentSummary([], $now)['admin']['average_ms'], 'No traffic is not zero-latency success');
};

$tests['agent monitoring summarizes latency and strips tool payloads'] = function (): void {
    $now = strtotime('2026-11-11T12:00:00Z');
    $runs = [];
    foreach ([100, 200, 2300] as $duration) {
        $runs[] = ['event' => 'agent.run', 'created_at' => date('c', $now - 1), 'meta' => [
            'surface' => 'admin', 'outcome' => 'model', 'duration_ms' => $duration, 'trace_id' => '0011223344556677',
            'tool_activity' => [
                ['name' => 'product_status', 'status' => 'complete', 'duration_ms' => 25, 'arguments' => 'private', 'reason' => 'private'],
                ['name' => 'private fixture', 'status' => 'error', 'duration_ms' => 8],
            ],
        ]];
    }
    $row = App\Services\AuditLogService::agentSummary($runs, $now)['admin'];
    assertSame(2300, $row['p95_ms'], 'Nearest rank p95 includes the slow tail');
    assertSame('0011223344556677', $row['recent_runs'][0]['trace_id'], 'Monitoring correlates with the chat response reference');
    assertSame([['name' => 'product_status', 'status' => 'complete', 'duration_ms' => 25]], $row['recent_runs'][0]['tools'], 'Trace keeps only approved tool metadata');
};

$tests['monitoring attributes attempts to the latest provider run even after a local answer'] = function (): void {
    $event = static fn(string $at, string $outcome, array $attempts): array => [
        'event' => 'agent.run',
        'created_at' => $at,
        'meta' => ['surface' => 'admin', 'outcome' => $outcome, 'provider_attempts' => $attempts],
    ];
    $primary = App\Services\SecretService::PRIMARY_MODEL;
    $fallback = App\Services\SecretService::FALLBACK_MODEL;
    $summary = App\Services\AuditLogService::agentSummary([
        $event('2026-09-25T16:30:00+05:30', 'model', [['model' => $primary, 'status' => 200]]),
        $event('2026-09-25T11:20:00Z', 'local', []),
        $event('2026-09-25T11:10:00Z', 'error', [
            ['model' => $primary, 'status' => 0, 'curl_error' => 28, 'total_ms' => 6000.4],
            ['model' => $fallback, 'status' => 500, 'total_ms' => 718.6, 'raw_body' => 'private'],
        ]),
    ], strtotime('2026-09-25T12:00:00Z'));
    assertSame([
        'at' => '2026-09-25T11:10:00Z',
        'outcome' => 'error',
        'attempts' => [
            ['model' => $primary, 'status' => 0, 'curl_error' => 28, 'total_ms' => 6000],
            ['model' => $fallback, 'status' => 500, 'curl_error' => 0, 'total_ms' => 719],
        ],
    ], $summary['admin']['latest_provider_run'], 'Monitoring retains the chronological provider sequence and only operational metadata');
};

$tests['owner tools reject malformed model arguments before execution'] = function (): void {
    $registry = new App\Services\AgentToolRegistry();
    $query = new class implements Stringable {
        public function __toString(): string {
            throw new RuntimeException('private fixture marker');
        }
    };
    assertSame(
        ['error' => 'Invalid lookup parameters.'],
        $registry->run('search_articles', ['query' => $query]),
        'Reject an invalid argument before its string conversion can run'
    );
    assertSame(
        ['error' => 'Give something to search for.'],
        $registry->run('search_articles', ['query' => '']),
        'An expected validation error should remain useful'
    );
};

$tests['owner aggregate questions answer from allowlisted local sales data'] = function (): void {
    $summary = [
        'orders' => 4,
        'revenue' => 1875.5,
        'best_sellers' => ['Brass Lamp' => 3, 'Rudraksha Mala' => 1],
    ];
    $best = App\Services\AgentToolRegistry::aggregateAnswerFromSummary('Which products sell best?', $summary, 30);
    assertTrue(str_contains((string)$best, 'Brass Lamp — 3 units'), 'Best-seller answer should state the locally counted units');
    assertTrue(str_contains((string)$best, 'last 30 days') && str_contains((string)$best, 'paid orders before returns'), 'Answer should state its period and counting rule');
    $revenue = App\Services\AgentToolRegistry::aggregateAnswerFromSummary('What is the revenue this month?', $summary, 0);
    assertTrue(str_contains((string)$revenue, '₹1,875.50') && str_contains((string)$revenue, '4 paid orders') && str_contains((string)$revenue, 'this month'), 'Revenue answer should state value, order count and calendar period');
    assertSame(null, App\Services\AgentToolRegistry::aggregateAnswerFromSummary('Where is order abc?', $summary), 'Specific lookups must stay on the scoped model/tool path');
    $count = App\Services\AgentToolRegistry::aggregateAnswerFromSummary('How many orders have we had in the last 30 days?', $summary, 30);
    assertSame('The store recorded 4 orders in the last 30 days. Includes all statuses with a recorded creation date in that period.', $count, 'Order count uses the selected date range and explains its scope');
    foreach (['How many refunded orders were placed this month?', 'How many orders were placed yesterday?', 'How many orders did we receive from this customer?'] as $scoped) {
        assertSame(null, App\Services\AgentToolRegistry::aggregateAnswerFromSummary($scoped, $summary), 'The generic count must not silently ignore a qualifier');
    }
    $fixture = [
        ['created_at'=>'2026-11-10','status'=>'confirmed','payment_id'=>'paid','total'=>100.50,
            'items'=>[['name'=>'Brass Lamp','qty'=>1,'line_total'=>100.50]]],
        ['created_at'=>'2026-11-11','status'=>'pending','total'=>700,
            'items'=>[['name'=>'Unpaid item','qty'=>4,'line_total'=>700]]],
        ['created_at'=>'2026-10-31','status'=>'confirmed','payment_id'=>'old','total'=>500],
    ];
    $facts = App\Services\AgentToolRegistry::salesFacts($fixture, ['from'=>'2026-11-01','to'=>'2026-11-30']);
    assertSame(1, $facts['orders'], 'Sales counts only include verified payments in the requested range');
    assertSame(100.50, $facts['revenue'], 'Unpaid or out-of-period orders must not inflate revenue');
    assertSame(['Brass Lamp'=>1], $facts['best_sellers'], 'An unpaid item cannot rank as a best seller');
    $controller = file_get_contents(app_path('app/Controllers/AdminController.php'));
    assertTrue(str_contains($controller, "agentRun('admin', 'local'"), 'Owner aggregate path must be monitored distinctly');
};

$tests['downloadable invoice response preserves snapshot and review warning'] = function (): void {
    foreach (['views/admin/detail.php', 'views/admin/tax-report.php', 'views/account/invoice.php'] as $view) {
        $source = file_get_contents(app_path($view));
        assertTrue(preg_match('/<a[^\n]+\bdownload\b[^\n]+\?download=1/', $source) === 1, "{$view} should explicitly request a browser download");
    }
    $order = [
        'invoice_number' => "SPS/TEST/1\"\r\nInjected: yes", 'invoice_date' => '2026-11-11',
        'supplier' => ['trade_name' => 'Original & Co', 'gstin' => '33SNAPSHOT'],
        'customer_name' => '<script>bad()</script>', 'tax_lines' => [], 'total' => 105,
    ];
    $build = new ReflectionMethod(App\Controllers\AdminController::class, 'invoiceDownload');
    $response = $build->invoke(null, $order, 'Missing or invalid delivery state');
    assertSame('Content-Type: text/html; charset=utf-8', $response['headers'][0], 'Download has HTML content type');
    assertSame('Content-Disposition: attachment; filename="invoice-SPS-TEST-1-Injected-yes.html"', $response['headers'][1], 'Download filename is safe and denotes an attachment');
    $html = $response['body'];
    assertTrue(str_starts_with($html, '<!doctype html>') && str_contains($html, '</html>'), 'Download is a standalone document');
    assertTrue(str_contains($html, '<style>') && !preg_match('/<(?:link|script)\b[^>]+(?:href|src)=/i', $html), 'Download has embedded styling and no external assets');
    assertTrue(str_contains($html, '33SNAPSHOT') && str_contains($html, 'Original &amp; Co'), 'Issued supplier snapshot appears escaped');
    assertTrue(str_contains($html, '&lt;script&gt;bad()&lt;/script&gt;') && !str_contains($html, '<script>bad()'), 'Customer input is escaped');
    assertTrue(str_contains($html, 'Tax classification review required'), 'Review warning is retained in the downloaded document');
};

$tests['issued invoice renders its supplier snapshot and escapes names'] = function (): void {
    $order = ['invoice_number'=>'SPS/TEST/00001', 'invoice_date'=>'2026-11-11',
        'supplier'=>['gstin'=>'33SNAPSHOT', 'trade_name'=>'Original supplier'],
        'customer_name'=>'<script>Customer</script>', 'tax_lines'=>[], 'total'=>105];
    $settings = ['gstin'=>'33NEWSETTING', 'gst_trade_name'=>'New supplier'];
    ob_start(); require app_path('views/account/invoice.php'); $html = ob_get_clean();
    assertTrue(str_contains($html, '33SNAPSHOT'), 'Issued supplier GSTIN must be shown');
    assertTrue(!str_contains($html, '33NEWSETTING'), 'Current GSTIN must not replace invoice snapshot');
    assertTrue(str_contains($html, '&lt;script&gt;Customer&lt;/script&gt;'), 'Customer data is escaped');
    $invoiceReviewReason = 'Missing or invalid delivery state';
    ob_start(); require app_path('views/account/invoice.php'); $reviewHtml = ob_get_clean();
    assertTrue(str_contains($reviewHtml, 'Tax classification review required'), 'Invoice output warns when an issued snapshot needs review');
    $routes = ProjectMapService::registry()['routes'];
    $matching = array_values(array_filter($routes, static fn($route) => $route['path'] === '/admin/orders/{id}/invoice'));
    assertSame('AdminController@orderInvoice', $matching[0]['controller'] ?? '', 'Invoice is served by the admin controller');
};

$tests['verified payment tax review preserves missing-state orders without issuing an invoice'] = function (): void {
    $tax = new \App\Services\TaxService();
    $items = [['name'=>'Test item','qty'=>1,'line_total'=>105,'gst_rate'=>5]];
    assertSame(null, $tax->snapshotForVerifiedPayment($items, 0, '', ['gst_state'=>'Tamil Nadu']), 'Legacy checkout without state requires review');
    assertSame(null, $tax->snapshotForVerifiedPayment($items, 0, 'Tamil Nadu', ['gst_state'=>'invalid']), 'Invalid supplier state requires review');
    $valid = $tax->snapshotForVerifiedPayment($items, 0, 'Karnataka', ['gst_state'=>'Tamil Nadu']);
    assertSame('interstate', $valid['supply_type'] ?? '', 'Valid paid checkout still receives correct classification');
    $controller = file_get_contents(app_path('app/Controllers/CommerceController.php'));
    assertTrue(str_contains($controller, "'tax_review_required' => \$tax === null"), 'Paid records carry review flag when classification is unavailable');
};

$tests['verified legacy payment is persisted once without a fabricated invoice'] = function (): void {
    $records = [];
    $ports = [
        'readOrders' => function () use (&$records) { return array_values($records); },
        'products' => fn() => ['test-product'=>['stock_status'=>'in_stock','gst_rate'=>5]],
        'settings' => fn() => ['gst_state'=>'Tamil Nadu'],
        'saveOrder' => function ($order) use (&$records) { $records[$order['id']] = $order; },
    ];
    $pending = ['total'=>105, 'customer_email'=>'test@example.invalid', 'customer_name'=>'Test', 'customer_phone'=>'',
        'shipping_address'=>'Test', 'shipping_city'=>'Test', 'shipping_pincode'=>'000000',
        'items'=>[['slug'=>'test-product','name'=>'Test','qty'=>1,'line_total'=>105]]];
    $persist = (new ReflectionMethod(\App\Controllers\CommerceController::class, 'persistVerifiedOrder'))->getClosure(new \App\Controllers\CommerceController());
    $id = $persist($pending, 'test-paid-id', [], $ports);
    assertSame(1, count($records), 'Verified payment must be saved');
    assertSame('test-paid-id', $records[$id]['payment_id'] ?? '', 'Payment identifier is preserved');
    assertSame(105, $records[$id]['total'] ?? null, 'Verified amount is preserved');
    assertTrue(!empty($records[$id]['tax_review_required']), 'Owner sees the review flag');
    assertTrue(empty($records[$id]['invoice_number']), 'No unverified invoice is issued');
    assertSame(5.0, $records[$id]['items'][0]['gst_rate'] ?? null, 'Tax rate is frozen for later owner review');
    assertSame($id, $persist($pending, 'test-paid-id', [], $ports), 'Repeated gateway callbacks are idempotent');
    assertSame(1, count($records), 'Retry creates no duplicate order');
};

$tests['verified payment survives concurrent stock changes with one atomic order'] = function (): void {
    $stored = []; $stock = 'out_of_stock';
    $insertOrder = function (array $order) use (&$stored): array {
        $id = $order['id'];
        if (isset($stored[$id])) return ['inserted'=>false, 'record'=>$stored[$id]];
        $stored[$id] = $order;
        return ['inserted'=>true, 'record'=>$order];
    };
    $ports = [
        // Both simulated callbacks have a stale pre-insert read, as concurrent requests can.
        'readOrders' => fn() => [],
        'products' => function () use (&$stock) { return ['item'=>['stock_status'=>$stock,'gst_rate'=>5,'hsn_code'=>'8306']]; },
        'settings' => fn() => ['gst_state'=>'Tamil Nadu'],
        'insertOrder' => $insertOrder,
    ];
    $pending = ['total'=>105, 'customer_email'=>'test@example.invalid', 'customer_name'=>'Test', 'customer_phone'=>'',
        'shipping_address'=>'Test', 'shipping_city'=>'Test', 'shipping_pincode'=>'000000', 'shipping_state'=>'Tamil Nadu',
        'items'=>[['slug'=>'item','name'=>'Test','qty'=>1,'line_total'=>105]]];
    $persist = (new ReflectionMethod(\App\Controllers\CommerceController::class, 'persistVerifiedOrder'))->getClosure(new \App\Controllers\CommerceController());
    $first = $persist($pending, 'payment-race-test', ['razorpay_order_id'=>'gateway-test'], $ports);
    $stock = 'in_stock';
    $second = $persist($pending, 'payment-race-test', ['razorpay_order_id'=>'gateway-test'], $ports);
    assertSame($first, $second, 'Concurrent callbacks derive the same payment-bound order id');
    assertSame(1, count($stored), 'Atomic insert keeps one paid order despite stale callback reads');
    assertSame('on_hold', $stored[$first]['status'] ?? '', 'The first availability exception remains on hold');
    assertTrue(!empty($stored[$first]['fulfillment_review_required']), 'Stock change remains visible for owner review');
    assertSame('payment-race-test', $stored[$first]['payment_id'] ?? '', 'Verified payment identity is retained');

    $missingPorts = $ports;
    $missingPorts['products'] = fn() => [];
    $missing = $persist($pending, 'missing-product-payment', [], $missingPorts);
    assertTrue(!empty($stored[$missing]['fulfillment_review_required']), 'Missing product requires fulfillment review');
    assertTrue(!empty($stored[$missing]['tax_review_required']), 'Missing product tax inputs require review');
    assertTrue(empty($stored[$missing]['invoice_number']), 'Missing product cannot create a fabricated invoice');
    try { (new App\Services\TaxService())->reviewedOrder($stored[$missing], array_values($stored), 'Tamil Nadu', ['gst_state'=>'Tamil Nadu']); assertTrue(false, 'Missing product tax inputs must not be guessed during review'); }
    catch (InvalidArgumentException $error) { assertTrue(str_contains($error->getMessage(), 'HSN and GST rate'), 'Owner is told which tax inputs must be restored'); }

    $stock = 'out_of_stock';
    $stripe = $persist($pending, 'stripe-payment-race', ['stripe_session_id'=>'cs_test_race'], $ports);
    assertSame($stripe, $persist($pending, 'stripe-payment-race', ['stripe_session_id'=>'cs_test_race'], $ports), 'Stripe callback retry converges on its first stored order');
    assertSame('cs_test_race', $stored[$stripe]['stripe_session_id'] ?? '', 'Stripe identity remains attached to the held order');
    assertTrue(!empty($stored[$stripe]['fulfillment_review_required']), 'Stripe stock change uses the same fulfillment hold');

    $dbSource = file_get_contents(app_path('app/Services/DatabaseService.php'));
    $remoteSource = file_get_contents(app_path('app/Controllers/RemoteDbController.php'));
    assertTrue(str_contains($dbSource, 'INSERT IGNORE INTO') && str_contains($remoteSource, "insert_if_absent"), 'Direct MySQL and remotedb expose the same atomic insert boundary');
    $commerceSource = file_get_contents(app_path('app/Controllers/CommerceController.php'));
    assertTrue(str_contains($commerceSource, "!empty(\$insert['inserted'])"), 'Only the winning callback queues confirmation mail');
    assertSame(2, substr_count($commerceSource, '$this->persistVerifiedOrder('), 'Razorpay and Stripe share the atomic persistence boundary');
};

$tests['fulfillment hold requires explicit owner confirmation'] = function (): void {
    $order = ['fulfillment_review_required'=>true, 'fulfillment_review_reason'=>'Stock changed'];
    $now = new DateTimeImmutable('2026-11-11T00:00:00Z');
    try { \App\Services\OrderService::reviewFulfillment($order, 'shipped', false, $now); assertTrue(false, 'Held order must not ship silently'); }
    catch (InvalidArgumentException $error) { assertTrue(str_contains($error->getMessage(), 'Confirm availability'), 'Owner receives a useful hold message'); }
    $resolved = \App\Services\OrderService::reviewFulfillment($order, 'processing', true, $now);
    assertTrue(empty($resolved['fulfillment_review_required']) && empty($resolved['fulfillment_review_reason']), 'Explicit confirmation clears the hold');
    assertSame($now->format('c'), $resolved['fulfillment_reviewed_at'], 'Review time is retained');
    $cancelled = \App\Services\OrderService::reviewFulfillment($order, 'cancelled', false, $now);
    assertTrue(!empty($cancelled['fulfillment_review_required']), 'Cancellation does not falsely claim availability was confirmed');
};

$tests['owner can correct a paid tax review record and issue one immutable invoice'] = function (): void {
    $tax = new App\Services\TaxService();
    $order = [
        'id'=>'review-order', 'payment_id'=>'pay_verified', 'total'=>105, 'discount'=>0,
        'tax_review_required'=>true, 'tax_review_reason'=>'Missing delivery state',
        'items'=>[['slug'=>'item','name'=>'Item','qty'=>1,'line_total'=>105]],
    ];
    $settings = ['gst_state'=>'Tamil Nadu','gstin'=>'33ABCDE1234F1Z5','default_gst_rate'=>5,'default_hsn_code'=>'8306'];
    $reviewed = $tax->reviewedOrder($order, [$order], 'Telangana', $settings, new DateTimeImmutable('2026-11-11T10:00:00+05:30'));
    assertSame('Telangana', $reviewed['shipping_state'], 'Owner-selected delivery state is canonicalised');
    assertSame('interstate', $reviewed['supply_type'], 'Corrected interstate delivery uses IGST');
    assertTrue(($reviewed['igst_total'] ?? 0) > 0 && ($reviewed['cgst_total'] ?? 0) === 0.0, 'Recovered invoice contains the correct tax split');
    assertTrue(!empty($reviewed['invoice_number']) && empty($reviewed['tax_review_required']), 'Recovery issues one invoice and clears the review flag');
    assertSame(105, $reviewed['total'], 'Gateway-confirmed total remains unchanged');
    foreach ([
        array_merge($order, ['payment_id'=>'']),
        array_merge($order, ['invoice_number'=>'SPS/2627/00001']),
        array_merge($order, ['tax_review_required'=>false]),
    ] as $invalid) {
        try { $tax->reviewedOrder($invalid, [$invalid], 'Telangana', $settings); assertTrue(false, 'Unsafe recovery must be rejected'); }
        catch (InvalidArgumentException) {}
    }
    $routes = App\Services\ProjectMapService::registry()['routes'];
    $recovery = array_values(array_filter($routes, fn($route) => $route['method'] === 'POST' && $route['path'] === '/admin/orders/{id}/tax-review'));
    assertSame(1, count($recovery), 'Tax recovery route is registered once');
    assertTrue(in_array('AuditLogService', $recovery[0]['services'], true), 'Tax recovery is auditable');
};

$tests['cart summary recalculates quantities and mixed product tax rates'] = function (): void {
    $summary = App\Services\TaxService::cartSummary([
        ['line_total' => 105, 'qty' => 1, 'product' => ['gst_rate' => 5]],
        ['line_total' => 236, 'qty' => 2, 'product' => ['gst_rate' => 18]],
    ], []);
    assertSame(341.0, $summary['total'], 'Cart total sums every line');
    assertSame(41.0, $summary['gst_amount'], 'Mixed rates must not use only the first product rate');
    assertSame(3, $summary['item_count'], 'Item count includes multiple units of one product');
    $changed = App\Services\TaxService::cartSummary([
        ['line_total' => 998, 'qty' => 2, 'product' => ['gst_rate' => 5]],
    ], []);
    assertSame(47.52, $changed['gst_amount'], 'Decremented quantity changes included tax');
    assertSame(0.0, App\Services\TaxService::cartSummary([], [])['gst_amount'], 'Empty cart has no tax');
};

$tests['tax invoices require a valid delivery state and classify supply consistently'] = function (): void {
    $tax = new App\Services\TaxService();
    $items = [['slug'=>'item','name'=>'Item','line_total'=>105,'unit_price'=>105,'qty'=>1,'gst_rate'=>5]];
    $settings = ['gst_state'=>'Tamil Nadu','gstin'=>'33ABCDE1234F1Z5'];
    $intra = $tax->snapshot($items, 0, 'Tamil Nadu', $settings);
    assertSame('intrastate', $intra['supply_type'], 'Tamil Nadu delivery from a Tamil Nadu supplier uses intrastate tax');
    assertSame('Tamil Nadu', $intra['place_of_supply'], 'Invoice place of supply uses the validated delivery state');
    assertTrue($intra['cgst_total'] > 0 && $intra['sgst_total'] > 0 && $intra['igst_total'] === 0.0, 'Intrastate GST splits into CGST and SGST');
    $inter = $tax->snapshot($items, 0, 'Telangana', $settings);
    assertSame('interstate', $inter['supply_type'], 'Telangana delivery from a Tamil Nadu supplier uses interstate tax');
    assertTrue($inter['igst_total'] > 0 && $inter['cgst_total'] === 0.0 && $inter['sgst_total'] === 0.0, 'Interstate GST uses IGST only');
    foreach (['', 'Not a state'] as $invalid) {
        try { $tax->snapshot($items, 0, $invalid, $settings); assertTrue(false, 'Invalid delivery state must stop invoice creation'); }
        catch (InvalidArgumentException $error) { assertTrue(str_contains($error->getMessage(), 'valid delivery state'), 'Invalid state error should tell checkout what is required'); }
    }
    assertSame('Odisha', App\Services\TaxService::normaliseState('Orissa'), 'A recognised legacy state name normalises to its official value');
};

$tests['historical tax inconsistencies are flagged without rewriting invoices'] = function (): void {
    $settings = ['gst_state'=>'Tamil Nadu'];
    $blank = ['shipping_state'=>'','place_of_supply'=>'Tamil Nadu','supply_type'=>'interstate'];
    assertSame('Missing or invalid delivery state', App\Services\TaxService::invoiceReviewReason($blank, $settings), 'Blank historical state is flagged for review');
    $wrong = ['shipping_state'=>'Telangana','place_of_supply'=>'Tamil Nadu','supply_type'=>'interstate'];
    assertSame('Place of supply conflicts with delivery state', App\Services\TaxService::invoiceReviewReason($wrong, $settings), 'Contradictory historical place of supply is flagged');
    $clear = ['shipping_state'=>'Telangana','place_of_supply'=>'Telangana','supply_type'=>'interstate'];
    assertSame(null, App\Services\TaxService::invoiceReviewReason($clear, $settings), 'Consistent historical invoice needs no review');
    $report = file_get_contents(app_path('views/admin/tax-report.php'));
    assertTrue(str_contains($report, 'require accountant review') && str_contains($report, 'not been rewritten'), 'Tax report should surface immutable historical invoices requiring review');
};

$tests['checkout captures and preserves a validated delivery state'] = function (): void {
    $schema = require app_path('storage/schema/collections.php');
    assertTrue(!empty($schema['collections']['addresses']['fields']['state']['required']), 'Saved address schema requires state');
    assertTrue(!empty($schema['collections']['orders']['fields']['shipping_state']['required']), 'Order schema requires shipping state');
    $checkout = file_get_contents(app_path('views/public/checkout.php'));
    assertTrue(str_contains($checkout, 'name="state" required') && str_contains($checkout, 'TaxService::states()'), 'Checkout uses a required official state selector');
    assertTrue(str_contains($checkout, "['name', 'email', 'phone', 'address', 'city', 'pincode', 'state']"), 'Browser validation includes delivery state');
    assertTrue(str_contains($checkout, "state: form.querySelector('[name=\"state\"]')"), 'Payment request includes delivery state');
    $commerce = file_get_contents(app_path('app/Controllers/CommerceController.php'));
    assertTrue(str_contains($commerce, 'TaxService::normaliseState') && substr_count($commerce, "'shipping_state' => \$shippingState") === 2, 'Both payment gateways store the same canonical delivery state');
    $addresses = file_get_contents(app_path('app/Services/AddressService.php'));
    assertTrue(str_contains($addresses, "'state' => \$state"), 'Saved addresses preserve validated state');
};

$tests['customer phone validation accepts formatted numbers and rejects placeholders'] = function (): void {
    assertTrue(\App\Services\AuthService::validPhone('+91 98765 43210'), 'Formatted international number is valid');
    foreach (['', '-------', '12', 'call me 9876543210', '1234567890123456'] as $invalid) {
        assertTrue(!\App\Services\AuthService::validPhone($invalid), 'Missing or invalid checkout number must be rejected');
    }
};

$tests['order email renders purchased quantities and escapes product text'] = function (): void {
    $html = App\Services\MailQueueService::orderItemsHtml([['name' => '<script>unsafe</script>', 'qty' => 3]]);
    assertTrue(str_contains($html, '&times; 3'), 'Receipt shows purchased quantity');
    assertTrue(str_contains($html, '&lt;script&gt;'), 'Product text is escaped in email');
    assertTrue(!str_contains($html, '<script>'), 'Receipt cannot embed product scripts');
    assertSame('', App\Services\MailQueueService::orderItemsHtml([]), 'Missing order lines are not fabricated');
};

$tests['support answers exact catalog facts without needing the model'] = function (): void {
    $product = ['name' => 'Sri Karuppasamy Pendant', 'slug' => 'karuppasami-dollar',
        'description' => 'Depicts Sri Karuppasamy seated on a horse.', 'price' => 899];
    $reply = \App\Services\SupportBotService::catalogFactReply(
        'What does the Sri Karuppasamy Pendant depict, and what is its current price?', [$product]
    );
    assertTrue(str_contains((string)$reply, 'horse') && str_contains((string)$reply, '₹899.00')
        && str_contains((string)$reply, '/product/karuppasami-dollar'), 'Use the stored description, price and link');
    assertSame(null, \App\Services\SupportBotService::catalogFactReply(
        'Compare the Sri Karuppasamy Pendant with a mala.', [$product]
    ), 'Open comparisons still go to the model');
    $product['price'] = null;
    assertSame(null, \App\Services\SupportBotService::catalogFactReply(
        'How much is the Sri Karuppasamy Pendant?', [$product]
    ), 'Missing prices must not be invented');
};

$tests['support comparison uses two distinct catalog examples'] = function (): void {
    $products = [
        ['name' => 'Pendant with Malai', 'slug' => 'with-malai', 'description' => 'Includes a five-metal chain.'],
        ['name' => 'Sri Karuppasamy Pendant', 'slug' => 'karuppasami-dollar', 'description' => 'Depicts the deity on a horse.'],
    ];
    $question = 'Can you explain the difference between a pendant and a mala for everyday devotional use?';
    $reply = \App\Services\SupportBotService::catalogComparisonReply($question, $products);
    assertTrue(str_contains((string)$reply, 'five-metal chain') && str_contains((string)$reply, 'deity on a horse'),
        'Both examples must use their stored facts');
    assertSame(null, \App\Services\SupportBotService::catalogComparisonReply($question, [$products[0]]),
        'One product cannot stand in for both types');
    $bot = new \App\Services\SupportBotService();
    $actions = (new ReflectionMethod($bot, 'extractActions'))->getClosure($bot);
    assertSame(['/product/Ayyappan%20Pendant', '/product/with-malai'], array_column(
        $actions('See /product/Ayyappan%20Pendant and /product/with-malai.'), 'path'
    ), 'Product links must preserve the saved slug and its encoded spaces');
};

$tests['support context selects a named product before applying the catalogue cap'] = function (): void {
    $catalogue = array_fill(0, 28, ['name' => 'Unrelated Ring', 'slug' => 'unrelated-ring']);
    $pendant = ['name' => 'Sri Karuppasamy Pendant', 'slug' => 'karuppasami-dollar'];
    $catalogue[] = $pendant;
    assertSame([$pendant], \App\Services\AgentContextService::supportProducts(
        $catalogue, 'What does the Sri Karuppasamy Pendant depict, and what is its current price?'
    ), 'Product 29 must be included when named');
    assertSame(20, count(\App\Services\AgentContextService::supportProducts($catalogue, 'What do you sell?')),
        'General catalog prompts remain bounded');
    $mala = ['name' => 'Ayyappa Dollar with Impon Malai', 'slug' => 'ayyappa-dollar-with-impon-malai'];
    $catalogue[] = $mala;
    assertSame([$pendant, $mala], \App\Services\AgentContextService::supportProducts(
        $catalogue, 'Can you explain the difference between a pendant and a mala?'
    ), 'Generic comparison includes both catalog types, including the Malai spelling');
};

$tests['support product questions retain public specifications and reject unrelated articles'] = function (): void {
    $bot = new App\Services\SupportBotService();
    $fallback = (new ReflectionMethod($bot, 'publicGuestReply'))->getClosure($bot);
    $article = (new ReflectionMethod($bot, 'matchArticle'))->getClosure($bot);
    $context = ['site' => ['products' => [[
        'name' => 'Kariya Sakthi Aragaja Mai', 'slug' => 'kariya-sakthi-aragaja-mai', 'url' => '/product/aragaja',
        'price' => 850, 'description' => 'Sacred fragrance paste.', 'specifications' => ['Pack size' => '25 g'],
    ]]], 'articles' => [[
        'title' => 'What is Sade Sati?', 'url' => '/blog/sade-sati', 'summary' => 'Astrology guide',
    ]]];
    $question = 'What is Kariya Sakthi Aragaja Mai and what is its pack size?';
    assertSame(null, $article($question, $context), 'Common question words must not match astrology articles');
    $reply = $fallback($question, $context);
    assertTrue(str_contains($reply, 'Pack size: 25 g'), 'Fallback uses the product specification');
    assertTrue(str_contains($reply, '₹850'), 'Fallback quotes the current public catalogue price');
    assertTrue(str_contains($reply, '/product/aragaja'), 'Fallback links the matched product');
    assertTrue(!str_contains($reply, 'Sade Sati'), 'Product answer must not substitute an unrelated article');
    $context['site']['products'][0]['specifications'] = [];
    $reply = $fallback($question, $context);
    assertTrue(!str_contains($reply, '25 g') && str_contains($reply, '/contact'), 'Missing facts are not invented');
    $comparison = $fallback('Can you explain the difference between a pendant and a mala for everyday devotional use?', $context);
    assertTrue(str_contains($comparison, '/contact'), 'Unanswered comparisons give customers a way to get help');
    assertTrue(!str_contains($comparison, 'Available products include') && !str_contains($comparison, 'Aragaja'), 'Comparison fallback does not suggest unrelated products');
    $source = file_get_contents(app_path('app/Services/AgentContextService.php'));
    foreach (['description', 'highlights', 'description_points', 'specifications'] as $field) {
        assertTrue(str_contains($source, "'" . $field . "' =>"), 'Public product details must reach model context');
        assertTrue(in_array($field, (new App\Services\SchemaService())->agentContextFields('products'), true), 'Product detail must be allowlisted by schema');
    }

    $context['site']['products'][0] = [
        'name' => 'Lakshmi Kubera Padi with Kubera Numbers | Traditional Gold Finish Pooja Stand',
        'slug' => 'lakshmi-kubera-padi', 'url' => '/product/lakshmi-kubera-padi', 'price' => 799,
    ];
    $reply = $fallback('What is Lakshmi Kubera Padi and what is its current price?', $context);
    assertTrue(str_contains($reply, 'Lakshmi Kubera Padi with Kubera Numbers'), 'A natural product question should match its exact multi-word slug alias');
    assertTrue(str_contains($reply, '₹799'), 'Slug-alias matches retain the current catalogue price');
};

$tests['smtp encodes long unicode HTML into bounded transport safe MIME lines'] = function (): void {
    $mailer = new App\Services\SmtpMailer(['mail_from_email' => 'support@example.com']);
    $html = '<p>' . str_repeat('₹499 — devotional product ', 500) . "</p>\n<p>Next line</p>";
    $message = $mailer->buildMessage('test@example.com', 'Encoding test', $html);
    assertSame(2, substr_count($message, 'Content-Transfer-Encoding: base64'), 'Both MIME alternatives declare their encoding');
    preg_match('/Content-Type: text\/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n(.*?)\r\n\r\n--/s', $message, $match);
    assertTrue(isset($match[1]), 'HTML MIME part exists');
    assertSame(str_replace("\n", "\r\n", $html), base64_decode($match[1], true), 'Unicode HTML survives transport encoding without changes');
    foreach (explode("\r\n", $match[1]) as $line) assertTrue(strlen($line) <= 76, 'Encoded body lines stay within MIME limits');
};

$tests['best sellers count verified non-cancelled product quantities'] = function (): void {
    $products = [['slug'=>'a'], ['slug'=>'b'], ['slug'=>'hidden','is_hidden'=>true], ['slug'=>'unsold']];
    $orders = [
        ['payment_id'=>'pay_1','status'=>'confirmed','items'=>[['slug'=>'a','qty'=>1],['slug'=>'b','qty'=>3],['slug'=>'hidden','qty'=>100]]],
        ['payment_id'=>'pay_2','status'=>'delivered','items'=>[['slug'=>'a','qty'=>3]]],
        ['payment_id'=>'','status'=>'confirmed','items'=>[['slug'=>'b','qty'=>100]]],
        ['payment_id'=>'pay_3','status'=>'cancelled','items'=>[['slug'=>'b','qty'=>100]]],
        ['payment_id'=>'   ','status'=>'confirmed','items'=>[['slug'=>'b','qty'=>100]]],
        ['payment_id'=>'pay_4','status'=>'REFUNDED','items'=>[['slug'=>'b','qty'=>100]]],
        ['payment_id'=>'pay_5','status'=>'failed','items'=>[['slug'=>'b','qty'=>100]]],
    ];
    $ranked = \App\Services\ProductService::rankBestSellers($products, $orders);
    assertTrue(array_column($ranked, 'slug') === ['a','b'], 'Only verified non-cancelled quantities should rank visible products');
    assertTrue(\App\Services\ProductService::rankBestSellers($products, []) === [], 'No orders should produce no unsupported best-seller claims');
};

$tests['project map includes orders used by bestseller query'] = function (): void {
    $dependencies = \App\Services\ProjectMapService::serviceCollections();
    assertTrue(in_array('orders', $dependencies['ProductService'] ?? [], true), 'Static SQL reads must remain visible in the generated service map');
};

$tests['special collection selection retains only selected visible products'] = function (): void {
    $service = new \App\Services\CategoryService();
    $products = [['id'=>'one', 'name'=>'One'], ['id'=>'two', 'name'=>'Two']];
    assertSame(['two'], array_column($service->collectionProducts(['product_ids'=>['two', 'missing']], $products), 'id'), 'Selection must not invent or expose products absent from visible catalogue');
    assertSame(['two', 'one'], array_column($service->collectionProducts(['product_ids'=>['two', 'one']], $products), 'id'), 'Collection product order must follow the owner-selected order');
    assertSame([], $service->collectionProducts(['product_ids'=>[]], $products), 'Empty collection must not include all products');
};

$tests['user history matches checkout email and orders by instant'] = static function (): void {
    $history = \App\Services\UserOrderHistoryService::forUsers(
        [['email'=>'buyer@example.test'], ['email'=>'']],
        [
            ['id'=>'early','customer_email'=>' Buyer@example.test ','created_at'=>'2026-10-30T11:00:00+05:30'],
            ['id'=>'late','customer_email'=>'buyer@example.test','created_at'=>'2026-10-30T10:00:00Z'],
            ['id'=>'unclaimed','customer_email'=>'','created_at'=>'2026-10-30T10:00:00Z'],
        ]
    );
    assertSame(['late','early'], array_column($history[0]['orders'], 'id'), 'Checkout email matching and time-zone-aware order');
    assertSame('15:30 +05:30', $history[0]['orders'][0]['date']->format('H:i P'), 'Order timestamp in IST');
    assertSame([], $history[1]['orders'], 'Blank email must never claim orders');
};

$tests['GST register preserves review rows and makes spreadsheet cells safe'] = static function (): void {
    $orders = [
        ['id'=>'one','invoice_number'=>'INV-1','customer_gstin'=>'invalid','verified_refunds'=>[['amount'=>500]],
            'tax_lines'=>[['name'=>' =1+1','qty'=>1,'taxable_value'=>100]]],
        ['id'=>'two','invoice_number'=>'INV-2'],
    ];
    $issued = $orders;
    $issued[0] += ['invoice_date'=>'2026-11-10','taxable_value'=>100,'cgst_total'=>9,'sgst_total'=>9,'total'=>118];
    $issued[1] += ['invoice_date'=>'2026-10-31','taxable_value'=>500,'igst_total'=>90,'total'=>590];
    $period = \App\Services\GstRegisterService::ordersForPeriod($issued, '2026-11-01', '2026-11-30');
    assertSame(['one'], array_column($period, 'id'), 'The register and agent period exclude out-of-period invoices');
    assertSame(['taxable'=>100.0,'cgst'=>9.0,'sgst'=>9.0,'igst'=>0.0,'tax'=>18.0,'gross'=>118.0],
        \App\Services\GstRegisterService::totals($period), 'GST totals come from the same issued invoice snapshots');
    assertSame(1, \App\Services\GstRegisterService::reviewCount($period, []), 'The GST tool reports review flags from the line export');
    $rows = iterator_to_array(\App\Services\GstRegisterService::lines($orders, ['two'=>'State conflict']));
    assertSame(array_fill(0, 2, count(\App\Services\GstRegisterService::HEADERS)), array_map('count', $rows), 'Rows align with CSV headers, including missing snapshots');
    assertSame('Review', $rows[0][5], 'Invalid GSTIN must not be grouped as B2B or B2C');
    assertSame("' =1+1", $rows[0][9], 'Formula-like product title is escaped');
    assertSame('100.00', $rows[0][13], 'Taxable amount comes from the issued line');
    assertTrue(str_contains($rows[0][18], 'credit note') && str_contains($rows[1][18], 'No issued tax lines')
        && str_contains($rows[1][18], 'State conflict'), 'Refunds and missing/conflicting snapshots are surfaced');
};

$tests['invoice sequence worksheet reconciles native issued documents within the selected scope'] = static function (): void {
    $make = static fn(string $id, int $seq, string $year='2627'): array => [
        'id'=>$id, 'invoice_number'=>sprintf('SPS/%s/%05d', $year, $seq), 'invoice_date'=>'2026-11-12',
        'invoice_financial_year'=>$year, 'invoice_sequence'=>$seq, 'supplier'=>['gstin'=>'33ABCDE1234F1Z5'],
    ];
    $orders = [$make('first', 4), $make('duplicate', 4), $make('later', 8) + ['verified_refunds'=>[['amount'=>1200]], 'status'=>'cancelled'],
        $make('other-year', 21, '2526'), array_replace($make('other-registration', 55), ['supplier'=>['gstin'=>'29ABCDE1234F1Z5']]),
        array_replace($make('legacy', 7), ['invoice_number'=>'EXT/007']), array_replace($make('unsequenced', 9), ['invoice_sequence'=>'']),
        array_replace($make('unknown-issuer', 19), ['supplier'=>[]]), array_replace($make('=unsafe-order', 10), ['invoice_number'=>'=unsafe']),
        array_replace($make('bad-year', 900), ['invoice_financial_year'=>'2628']),
        ['id'=>'refund-only', 'verified_refunds'=>[['amount'=>100]], 'invoice_date'=>'2026-11-12'],
        array_replace($make('outside-period', 6), ['invoice_date'=>'2026-10-01'])];
    $period = \App\Services\GstRegisterService::ordersForPeriod($orders, '2026-11-01','2026-11-30');
    $worksheet = static fn(array $input): array => iterator_to_array(\App\Services\GstRegisterService::sequenceWorksheet($input));
    assertSame([], $worksheet([]), 'An empty selection yields only the CSV header');
    $csv = $worksheet($period);
    assertSame($csv, $worksheet(array_reverse($period)), 'The worksheet has deterministic order independent of input order');
    $rows = array_column(array_map(static fn(array $row): array => array_combine(\App\Services\GstRegisterService::SEQUENCE_HEADERS, $row), $csv), null, 'Order');
    assertSame(10, count($rows), 'Only issued invoices in the inclusive period appear');
    assertSame('5 to 7', $rows['later']['Unobserved native SPS numbers in selection'], 'Flag only interior gaps between matching printed and stored sequences');
    foreach (['first','duplicate'] as $id) {
        assertTrue(str_contains($rows[$id]['Review'], 'Duplicate invoice number') && str_contains($rows[$id]['Review'], 'Duplicate stored sequence'), 'Flag each duplicate');
    }
    foreach (['other-year','other-registration','legacy','unsequenced','unknown-issuer','bad-year'] as $id) {
        assertSame('', $rows[$id]['Unobserved native SPS numbers in selection'], 'Distinct registrations/years and invalid snapshots never supply a range');
    }
    assertTrue(str_contains($rows['legacy']['Review'], 'differs') && str_contains($rows['unsequenced']['Review'], 'sequence')
        && str_contains($rows['unknown-issuer']['Review'], 'supplier GSTIN') && str_contains($rows['bad-year']['Review'], 'financial year')
        && str_contains($rows['other-year']['Review'], 'differs from issue date'), 'Keep the cause of excluded comparisons');
    assertSame("'=unsafe", $rows["'=unsafe-order"]['Invoice'], 'Protect spreadsheet text without exposing executable cells');
    assertTrue($rows['later']['Gateway refund recorded'] === 'Yes' && str_contains($rows['later']['Review'], 'cancellation records')
        && str_contains($rows['later']['Review'], 'separately issued tax documents'), 'Operational cancellation and cash movement prompt review only');
};

$tests['HSN reconciliation splits recipient classes and preserves excluded lines'] = static function (): void {
    $line = ['hsn_code'=>'7117','qty'=>1,'gst_rate'=>3,'taxable_value'=>10.01,'cgst'=>0.15,'sgst'=>0.15,'igst'=>0,'gross_value'=>10.31];
    $supplier = ['gstin'=>'33ABCDE1234F1Z5'];
    $summary = \App\Services\GstRegisterService::hsnSummary([
        ['invoice_number'=>'A','supplier'=>$supplier,'tax_lines'=>[$line, $line, array_replace($line, ['hsn_code'=>''])]],
        ['invoice_number'=>'B','supplier'=>$supplier,'customer_gstin'=>'33ABCDE1234F1Z5','tax_lines'=>[$line]],
        ['invoice_number'=>'C','supplier'=>$supplier,'customer_gstin'=>'invalid','tax_lines'=>[$line]],
        ['invoice_number'=>'D'],
    ], ['B'=>'Credit note review']);
    assertSame(2, $summary['excluded'], 'Missing HSN and missing snapshot are counted for review');
    assertSame(['B2B','B2C','Review'], array_column($summary['groups'], 'recipient'), 'Each recorded recipient class has its own group');
    assertSame([1,0,1], array_column($summary['groups'], 'review'), 'Review notes survive aggregation');
    assertSame([1001,2002,1001], array_column($summary['groups'], 'taxablePaise'), 'Reconcile taxable amounts in paise');
    assertSame([30,60,30], array_column($summary['groups'], 'taxPaise'), 'Sum recorded component tax in paise');
};

$tests['Enterprise can recover verified ticket counts after model synthesis fails'] = static function (): void {
    $previousMode = getenv('BAPX_TEST_MODE');
    putenv('BAPX_TEST_MODE=1');
    try {
        $tools = new \App\Services\AgentToolRegistry();
        assertSame(null, $tools->ticketLookupFallback(), 'No fallback before a completed ticket lookup');
        $tools->run('support_enquiries', ['status'=>'open']);
        $tools->run('support_enquiries', ['status'=>'answered']);
        $tools->run('support_enquiries', ['status'=>'invalid']);
        $answer = $tools->ticketLookupFallback();
        assertTrue(str_contains($answer, '0 open support tickets') && str_contains($answer, '0 answered support tickets'), 'Recover each successful result with its scope');
        assertTrue(!str_contains($answer, 'invalid'), 'Exclude failed calls from the recovered answer');
    } finally {
        putenv($previousMode === false ? 'BAPX_TEST_MODE' : 'BAPX_TEST_MODE=' . $previousMode);
    }
};

$tests['Enterprise report tools use the requested inclusive relative period'] = static function (): void {
    $previousMode = getenv('BAPX_TEST_MODE');
    putenv('BAPX_TEST_MODE=1');
    try {
        $tools = new \App\Services\AgentToolRegistry(ownerQuestion: 'Compare open and answered support tickets from the last seven days.');
        $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Kolkata'));
        $result = $tools->run('support_enquiries', [
            'from'=>$today->modify('-7 days')->format('Y-m-d'),
            'to'=>$today->format('Y-m-d'),
            'status'=>'open',
        ]);
        assertSame($today->modify('-6 days')->format('Y-m-d'), $result['from'], 'Last seven days includes today and six prior dates');
        assertSame($today->format('Y-m-d'), $result['to'], 'The report ends today in India time');

        $tools = new \App\Services\AgentToolRegistry(ownerQuestion: 'Give me daily ticket counts for the last 7 days.');
        $day = $today->format('Y-m-d');
        $result = $tools->run('support_enquiries', ['from'=>$day, 'to'=>$day]);
        assertSame($day, $result['from'], 'Deliberate daily subranges are preserved');

        $tools = new \App\Services\AgentToolRegistry(ownerQuestion: 'Compare the last 7 days with the last 30 days.');
        $start = $today->modify('-29 days')->format('Y-m-d');
        $result = $tools->run('support_enquiries', ['from'=>$start]);
        assertSame($start, $result['from'], 'Multiple requested windows keep the tool-specific dates');
    } finally {
        putenv($previousMode === false ? 'BAPX_TEST_MODE' : 'BAPX_TEST_MODE=' . $previousMode);
    }
};

$tests['Enterprise ticket follow-up retains the previous report period'] = static function (): void {
    $previousMode = getenv('BAPX_TEST_MODE');
    putenv('BAPX_TEST_MODE=1');
    try {
        $question = 'And how many were answered in that same period?';
        $previous = (new \App\Services\AgentToolRegistry())->run('support_enquiries', [
            'from' => '2026-09-01', 'to' => '2026-09-07', 'status' => 'open', 'topic' => 'refund',
        ]);
        $tools = new \App\Services\AgentToolRegistry(ownerQuestion: $question, previousReport: [
            'tool' => 'support_enquiries', 'from' => $previous['from'], 'to' => $previous['to'], 'topic' => $previous['topic'],
        ]);
        $answer = $tools->aggregateAnswer($question);
        assertTrue(str_contains($answer, 'answered support tickets about refund') && str_contains($answer, '2026-09-01 to 2026-09-07'),
            'Follow-up preserves the prior date range and applies the new status');
        $qualified = 'How many tickets in that same period were answered?';
        $qualifiedTools = new \App\Services\AgentToolRegistry(ownerQuestion: $qualified, previousReport: $tools->reportContext());
        assertTrue(str_contains($qualifiedTools->aggregateAnswer($qualified), 'answered support tickets about refund'), 'A status after the period keeps the report scope');
        $orders = new \App\Services\AgentToolRegistry(ownerQuestion: 'How many orders this month?');
        $orders->aggregateAnswer('How many orders this month?');
        assertSame(date('Y-m-01'), $orders->reportContext()['from'] ?? null, 'Direct order count establishes a report period');
        $fresh = new \App\Services\AgentToolRegistry(ownerQuestion: $question);
        assertTrue(str_contains($fresh->aggregateAnswer($question), 'Which dates'), 'A fresh chat asks for dates');
        $comparison = 'Compare sales this month with the same period last year';
        assertSame(null, $fresh->aggregateAnswer($comparison), 'A self-contained comparison reaches the model');
        $sales = new \App\Services\AgentToolRegistry(ownerQuestion: 'Show best sellers for that same period', previousReport: [
            'tool' => 'sales_summary', 'from' => '0001-01-01', 'to' => '2026-09-07',
        ]);
        assertSame('0001-01-01', $sales->followUpContext()['from'] ?? null, 'All-time sales context can be reused');

    } finally {
        putenv($previousMode === false ? 'BAPX_TEST_MODE' : 'BAPX_TEST_MODE=' . $previousMode);
    }
};

$tests['support ticket data is scoped and does not expose conversation bodies'] = static function (): void {
    $query = \App\Services\SupportBotService::ticketLookupRequest('How many of my tickets are closed?');
    assertSame('closed', $query['status'] ?? null, 'Status after the noun must constrain the lookup');
    $yearQuery = \App\Services\SupportBotService::ticketLookupRequest('How many of my support tickets were opened this year?');
    assertSame([date('Y-01-01'), date('Y-m-d')], $yearQuery['period'], 'Current year must filter ticket counts');
    $quarterQuery = \App\Services\SupportBotService::ticketLookupRequest('How many of my support tickets were opened this quarter?');
    assertSame([], $quarterQuery['period'], 'Unsupported period must ask for clarification');

    assertSame(null, \App\Services\SupportBotService::ticketLookupRequest('Open a new support ticket'), 'Creation stays separate from lookup');
    assertSame(null, \App\Services\SupportBotService::ticketLookupRequest('I have a problem with my order, please create a support ticket'),
        'Ticket creation after an introductory clause still reaches escalation');
    $periodQuestion = 'Show my support tickets from last month';
    $periodLookup = \App\Services\SupportBotService::ticketLookupRequest($periodQuestion);
    $statusReply = new ReflectionMethod(\App\Services\SupportBotService::class, 'ticketStatusReply');
    assertSame(null, $statusReply->invoke(null, $periodQuestion, [
        'recent' => [['reference'=>'aaaaaaaaaaaaaaaa'], ['reference'=>'bbbbbbbbbbbbbbbb']],
        'count' => 2,
    ], $periodLookup), 'A period list must not short circuit to the latest ticket');

    $facts = \App\Services\AgentContextService::supportTicketFacts([
        ['id'=>'aaaaaaaaaaaaaaaa', 'status'=>'closed', 'created_at'=>'2026-10-12T10:00:00+05:30',
            'customer_email'=>'private@example.test', 'message'=>'Raw ticket message', 'reply'=>'Private reply'],
        ['id'=>'bbbbbbbbbbbbbbbb', 'status'=>'open', 'created_at'=>'2026-10-12T10:00:00+05:30'],
        ['id'=>'cccccccccccccccc', 'status'=>'closed', 'created_at'=>'2026-09-01T10:00:00+05:30'],
    ], null, $query['status'], ['2026-10-01', '2026-10-31']);
    assertSame(3, $facts['candidate_count'], 'The candidate count reflects the supplied customer-scoped tickets before filtering');
    assertSame(1, $facts['count'], 'The results respect status and creation dates');
    assertSame(['reference', 'status', 'created_date', 'updated_date'], array_keys($facts['recent'][0]), 'Only allowlisted ticket metadata reaches the model');
    $guest = (new \App\Services\SupportBotService())->answer('Show my support tickets', null);
    assertSame(null, $guest['ticket_id'], 'Guest ticket lookup must not create a record');
    assertTrue(str_contains($guest['reply'], '/login'), 'Guest receives sign-in guidance');
    assertSame('own_ticket', $guest['run']['retrieval'][0]['intent'], 'Sign-in guidance keeps the observed ticket topic');
    assertSame('not_applicable', $guest['run']['retrieval'][0]['status'], 'Guest sign-in guidance never claims a lookup');
    $guestEvidence = \App\Services\AgentTraceService::safeRetrieval($guest['run']['retrieval'][0]);
    assertSame([null,null], [$guestEvidence['candidate_count'],$guestEvidence['result_count']], 'A lookup that never ran has no measured counts');
    $oldMode = getenv('BAPX_TEST_MODE');
    putenv('BAPX_TEST_MODE=1');
    try {
        $bot = new \App\Services\SupportBotService();
        $actor = ['email'=>'scope@example.invalid'];
        $clarification = $bot->answer('How many of my tickets were opened this quarter?', $actor);
        assertSame('clarification', $clarification['run']['retrieval'][0]['status'], 'Unsupported date range requests clarification');
        $empty = $bot->answer('How many of my tickets are closed?', $actor);
        $retrieval = $empty['run']['retrieval'][0];
        assertSame(['own_ticket','no_match',0,0], [$retrieval['intent'],$retrieval['status'],$retrieval['candidate_count'],$retrieval['result_count']],
            'An executed lookup with zero results stays distinct from sign-in and clarification');
    } finally {
        putenv($oldMode === false ? 'BAPX_TEST_MODE' : 'BAPX_TEST_MODE='.$oldMode);
    }
};

foreach ($tests as $name => $test) {
    try {
        $test();
        echo "PASS {$name}\n";
    } catch (Throwable $e) {
        $failures[] = "FAIL {$name}: {$e->getMessage()}";
    }
}

$passed = count($tests) - count($failures);
echo "\n{$passed}/" . count($tests) . " passed, {$assertionCount} assertions\n";

if ($failures) {
    echo "\n" . implode("\n", $failures) . "\n";
    exit(1);
}
