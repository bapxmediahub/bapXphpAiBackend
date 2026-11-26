<?php
declare(strict_types=1);
// Scripted model decisions over real HTTP. Never uses real keys or live records.
const OPENROUTER_FREE_MODEL = 'google/gemma-4-26b-a4b-it:free';
const OPENROUTER_CHANGED_MODEL = 'google/gemma-4-31b-it:free';
if(PHP_SAPI==='cli-server'){
    if(getenv('BAPX_MODEL_DRIVEN_FIXTURE')!=='1'){http_response_code(404);return;}
    header('Content-Type: application/json');
    $route = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
    [$scenario, $provider] = array_pad($route, 2, '');
    $google = $provider === 'google';
    $router = $provider === 'openrouter';
    $fail=static function(string $message):void{http_response_code(400);echo json_encode(['error'=>['message'=>$message]]);};
    if (str_starts_with($scenario, 'foreign_')) {
        file_put_contents(getenv('BAPX_RECOVERY_FIXTURE_DIR') . '/' . $scenario, 'Unexpected provider transfer');
        $fail('A foreign conversation reached the provider');
        return;
    }
    $wire=file_get_contents('php://input');
    $r=json_decode($wire,true,512,JSON_THROW_ON_ERROR);
    $expectedModels = match ($provider) {
        'google' => ['gemma-4-31b-it','gemma-4-26b-a4b-it'],
        'openrouter' => ['openrouter/free', OPENROUTER_FREE_MODEL],
        'openai' => ['gpt-5','gpt-4o','gpt-6-luna'],
        'openai_compatible' => ['fixture-model'],
        default => [],
    };
    $credential = $provider === 'openai' ? 'openai_compatible' : $provider;
    $luna = ($r['model'] ?? '') === 'gpt-6-luna';
    $modernTokenField = $luna || $scenario === 'modern_policy' || in_array($r['model'] ?? '', ['gpt-5','gpt-4o'], true);
    $tokenField = $modernTokenField ? 'max_completion_tokens' : 'max_tokens';
    $allowedFields = ['model','messages','tools','tool_choice',$tokenField];
    if ($luna) $allowedFields[] = 'reasoning_effort';
    if ($google) $allowedFields = [...$allowedFields, 'temperature', 'extra_body'];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST'
        || $route !== [$scenario,$provider,'chat','completions']
        || ($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== 'Bearer fixture-'.$credential
        || isset($_SERVER['HTTP_X_GOOG_API_KEY'])
        || ($_SERVER['CONTENT_TYPE'] ?? '') !== 'application/json'
        || !in_array($r['model'] ?? null, $expectedModels, true)
        || !isset($r[$tokenField]) || !is_array($r['messages'] ?? null)
        || ($luna && ($r['reasoning_effort'] ?? null) !== 'none')
        || ($google && (!in_array($r['temperature'] ?? null, [0.2, 0.3], true)
            || ($r['extra_body'] ?? null) !== ['google'=>['thinking_config'=>['thinking_level'=>'minimal']]]))
        || array_diff(array_keys($r), $allowedFields)) {
        $fail('Unexpected Chat Completions request');
        return;
    }
    if($scenario==='outage'){http_response_code(503);echo '{"error":{"status":"UNAVAILABLE"}}';return;}
    if($scenario==='quota'){http_response_code(429);header('Retry-After: 60');echo '{}';return;}
    if($scenario==='long_retry'){http_response_code(503);header('Retry-After: 60');echo '{}';return;}
    if($scenario==='schema_error'){http_response_code(400);echo '{}';return;}
    $tools = $r['tools'] ?? [];
    if ($tools === [] || array_column($tools, 'type') !== array_fill(0, count($tools), 'function')) {
        $fail('Tool declarations must use the Chat Completions function envelope');
        return;
    }
    $names = array_column(array_column($tools, 'function'), 'name');
    $contents = $r['messages'];
    $owner=in_array($scenario,['owner','capability','owner_followup','owner_multi_scope','owner_ambiguous_followup','owner_article','slash_capabilities','slash_help','direct_order_total','missing_finish','router_changed_answer'],true);
    $guest=!$owner&&!in_array($scenario,['customer','ticket'],true);
    $hasHistory = str_starts_with($scenario, 'catalogue_') || $scenario === 'owner_followup';
    $instructionRole = $luna || ($r['model'] ?? '') === 'gpt-5' ? 'developer' : 'system';
    $scopeChain = in_array($scenario,['owner_multi_scope','owner_ambiguous_followup'],true);
    $expectedRoles = $hasHistory || $scenario === 'owner_ambiguous_followup'
        ? [$instructionRole,'user','assistant','user','assistant','user']
        : ($scenario === 'owner_multi_scope' ? [$instructionRole,'user','assistant','user'] : [$instructionRole,'user']);
    $initialMessages = array_slice($contents, 0, count($expectedRoles));
    $instruction = array_shift($initialMessages)['content'] ?? '';
    $text = static fn(array $message): string => $message['content'] ?? '';
    $current = json_decode($text(end($initialMessages)), true);
    if (array_column(array_slice($contents, 0, count($expectedRoles)), 'role') !== $expectedRoles
        || !str_contains($instruction, $owner ? 'Enterprise Agent runtime constitution' : 'Support Agent runtime constitution')
        || !str_contains($instruction, 'finished answer between <final_answer> and </final_answer>')
        || !is_string($current['question'] ?? null) || $current['question'] === ''
        || !is_array($current['reference_data'] ?? null) || !is_string($current['reference_data']['site_display_name'] ?? null)
        || (strlen($current['question']) > 16 && str_contains($instruction, $current['question']))) {
        $fail('Unexpected native instructions, roles or current question');
        return;
    }
    $context = $current['reference_data'];
    if (str_contains($instruction, $context['site_display_name']) || isset($context['conversation_history'])
        || (!$owner && (isset($context['previous_report']) || isset($context['attachments'])))) {
        $fail('Reference data crossed its provider-role boundary');
        return;
    }
    if ($scopeChain) {
        $history = array_map($text, array_slice($initialMessages, 0, -1));
        $first = ['Show the first order report.','The last delivered order report covered 3 to 7 January.'];
        if ($scenario === 'owner_multi_scope') {
            if ($history !== $first || ($context['previous_report']['from'] ?? null) !== '2024-01-03'
                || ($context['previous_report_ambiguous'] ?? null) !== false) {
                $fail('Initial report context missing'); return;
            }
        } elseif ($history !== [...$first,'Compare orders from 1 to 5 February and 1 to 5 March 2024.',
            'February: 0 orders. March: 0 orders.']
            || !array_key_exists('previous_report',$context) || $context['previous_report'] !== null
            || ($context['previous_report_ambiguous'] ?? null) !== true) {
            $fail('Ambiguous accepted report reused an older scope'); return;
        }
    }
    if ($hasHistory) {
        $expectedFirst = $owner
            ? ['Show the first order report.','The last delivered order report covered 3 to 7 January.']
            : ['How many products were published?','There are currently 43 products published in the shop. One example was /product/fixture-first.'];
        $history = array_map($text, array_slice($initialMessages, 0, -1));
        $hasGrounding = $owner
            ? ($context['previous_report']['tool'] ?? null) === 'order_count'
                && ($context['previous_report']['from'] ?? null) === '2024-01-03'
                && ($context['previous_report']['to'] ?? null) === '2024-01-07'
                && str_contains((string)($context['attachments'] ?? ''), '@privacy (legal page)')
                && !str_contains($instruction, '@privacy (legal page)')
                && ($current['question'] ?? '') === 'How many orders in that same period now? Include @privacy for reference.'
            : ($context['previous_public_product_slugs'] ?? null) === ['fixture-first']
                && ($current['question'] ?? '') === 'Is that published product count still correct?';
        if ($history !== [...$expectedFirst,'Can I ask a follow-up?','Yes, you can ask about the same subject.']
            || !$hasGrounding || str_contains($wire, 'Unsuccessful provider attempt.')
            || str_contains($instruction, $expectedFirst[1]) || str_contains($wire, '2024-01-08')) {
            $fail('Unsuccessful attempts displaced approved follow-up context, or native role order changed');
            return;
        }
    }
    if ($scenario === 'refusal') {
        echo json_encode(['choices'=>[['message'=>['role'=>'assistant','content'=>null,
            'refusal'=>'I can only help with store-related questions.'],'finish_reason'=>'stop']]], JSON_THROW_ON_ERROR);
        return;
    }
    $n = 1 + 2 * count(array_filter(array_slice($contents, count($expectedRoles)),
        static fn(array $message): bool => ($message['role'] ?? null) === 'assistant'));
    if ($router && $r['model'] !== ($n === 1 ? 'openrouter/free' : OPENROUTER_FREE_MODEL)) {
        $fail('OpenRouter continuation did not request its selected free model');
        return;
    }
    if (($r['tool_choice'] ?? null) !== ($n === 9 ? 'none' : 'auto')) {
        $fail('Unexpected tool choice for the current round');
        return;
    }
    if (($scenario === 'catalogue_fallback' && !$google && $n === 1)
        || ($scenario === 'catalogue_locked' && !$google && $n === 7)) {
        http_response_code(503);
        header('Retry-After: 60');
        echo '{"error":{"status":"UNAVAILABLE"}}';
        return;
    }
    if ($scenario === 'catalogue_locked' && $google) {
        $fail('A partially executed tool exchange crossed providers');
        return;
    }
    // Force a single failed HTTP transfer, then require a byte-identical retry.
    // State is isolated in this test runner's temporary directory.
    if(($scenario==='transient_start'&&$n===1)||($scenario==='transient_continuation'&&$n===7)){
        $file=getenv('BAPX_RECOVERY_FIXTURE_DIR').'/'.$provider.'-'.$scenario;
        if(!is_file($file)){
            file_put_contents($file,hash('sha256',$wire),LOCK_EX);
            http_response_code(503);echo '{"error":{"status":"UNAVAILABLE"}}';return;
        }
        if(file_get_contents($file)!==hash('sha256',$wire)){
            http_response_code(400);echo '{"error":{"message":"Retry changed the request"}}';return;
        }
    }
    $responses = [];
    if ($n > 1) {
        for ($i = count($contents) - 1; $i > 0 && $contents[$i]['role'] === 'tool'; $i--) {
            $responses[$contents[$i]['tool_call_id']] = json_decode($contents[$i]['content'], true, 64, JSON_THROW_ON_ERROR);
        }
        $previous = array_column($contents[$i]['tool_calls'] ?? [], 'id');
        if ($contents[$i]['role'] !== 'assistant' || !array_key_exists('content', $contents[$i])
            || $contents[$i]['content'] !== null || $previous !== array_reverse(array_keys($responses))) {
            $fail('Unexpected assistant continuation or tool result order');
            return;
        }
    }
    $signature = static fn(string $scope): array => ['google'=>['thought_signature'=>'fixture-google-signature-'.$scope]];
    foreach ($contents as $message) {
        $toolCalls = $message['tool_calls'] ?? [];
        $extensions = array_intersect_key($message, array_flip(['reasoning_content','reasoning_details','extra_content']));
        $expectedExtensions = match (true) {
            $toolCalls === [] => [],
            $google => ['extra_content'=>$signature('assistant')],
            $router => ['reasoning_content'=>'fixture-continuation','reasoning_details'=>[['type'=>'fixture','id'=>'opaque-state']]],
            default => [],
        };
        if ($extensions !== $expectedExtensions) {
            $fail('Unexpected provider-specific assistant continuation');
            return;
        }
        foreach ($toolCalls as $toolCall) {
            if (($toolCall['extra_content'] ?? null) !== ($google ? $signature($toolCall['id']) : null)) {
                $fail('Unexpected provider-specific tool-call continuation');
                return;
            }
        }
    }
    $last = $responses === [] ? [] : reset($responses);
    if ((!$owner && in_array('order_count', $names, true)) || ($guest && array_intersect(['own_order_status','own_ticket_status'], $names))) {
        $fail('Role leak');
        return;
    }
    $call = static fn(string $name, array $args, string $id): array => [
        'id'=>$id, 'type'=>'function',
        'function'=>['name'=>$name,'arguments'=>json_encode((object)$args, JSON_THROW_ON_ERROR)],
    ];
    $directChoice=match($scenario){
        'direct_shop_total'=>['public_catalogue_summary',[],'catalogue',['total'=>0]],
        'direct_delivery_charge','router_missing_model','router_paid_model','router_changed_tool'=>
            ['public_policy',['key'=>'shipping_cost'],'customer-policy',['value'=>'Shipping is calculated at checkout.']],
        'direct_order_total','router_changed_answer'=>['order_count',['period'=>'all_time'],'reporting',
            ['from'=>'0001-01-01','count'=>0,'undated_excluded'=>0]],
        default=>null,
    };
    $choice=match($scenario){
        'aggregate','catalogue_wrong','catalogue_current','catalogue_fallback','catalogue_locked'=>['public_catalogue_summary',[], 'total','catalogue'],
        'owner'=>['order_count',['period'=>'all_time'],'count','reporting'],
        'owner_followup'=>['order_count',['from'=>'2024-01-03','to'=>'2024-01-07'],'count','reporting'],
        'customer'=>['own_order_status',[],'count','customer-policy'],
        'ticket'=>['own_ticket_status',[],'count','customer-policy'],
        'article','owner_article'=>['read_published_article',['slug'=>'get-support','query'=>'ticket information'],'found','customer-policy'],
        'contact'=>['present_contact_enquiry',[],'submitted','customer-policy'],
        'policy','modern_policy','router_policy','transient_continuation'=>['public_policy',['key'=>'shipping_cost'],'value','customer-policy'],
        default=>null
    };
    $finish='stop';
    if ($scenario === 'owner_multi_scope') {
        if ($n===1) $parts=[$call('tool_search',['query'=>'order_count'],'discover')];
        elseif ($n===3 && in_array('order_count',$names,true)) $parts=[
            $call('order_count',['from'=>'2024-02-01','to'=>'2024-02-05'],'feb'),
            $call('order_count',['from'=>'2024-03-01','to'=>'2024-03-05'],'mar')];
        elseif ($n===5 && ($responses['feb']['from'] ?? null)==='2024-02-01'
            && ($responses['mar']['from'] ?? null)==='2024-03-01'
            && ($responses['feb']['count'] ?? null)===0 && ($responses['mar']['count'] ?? null)===0) {
            $parts=[['text'=>'<final_answer>February: 0 orders. March: 0 orders.</final_answer>']];
        } else { $fail('Missing distinct model-selected report scopes'); return; }
    } elseif ($scenario === 'owner_ambiguous_followup') {
        if ($n!==1) { $fail('Ambiguous report must not trigger an implicit lookup'); return; }
        $parts=[['text'=>'<final_answer>Which of the two report periods should I use?</final_answer>']];
    } elseif($scenario==='catalogue_stale') {
        $parts=[['text'=>'<final_answer>There are currently 43 products published in the shop.</final_answer>']];
    }elseif($scenario==='mixed'){
        if($n===1)$parts=[$call('tool_search',['query'=>'public_catalogue_summary'],'d1'),$call('tool_search',['query'=>'public_policy'],'d2')];
        elseif($n===3&&isset($responses['d1']['matches'][0],$responses['d2']['matches'][0]))$parts=[$call('public_catalogue_summary',[],'b1'),$call('public_policy',['key'=>'shipping_cost'],'b2')];
        elseif($n===5&&isset($responses['b1']['total'],$responses['b2']['value']))$parts=[['text'=>'<final_answer>Published products: '.$responses['b1']['total'].'. '.$responses['b2']['value'].'</final_answer>']];
        else{$fail('Mixed tool results lost');return;}
    }elseif($scenario==='recover'){
        if($n===1&&!in_array('public_policy',$names,true))$parts=[$call('tool_search',['query'=>'public_policy'],'d1'),$call('public_policy',['key'=>'shipping_cost'],'early')];
        elseif($n===3&&isset($responses['d1']['matches'][0],$responses['early']['error']))$parts=[$call('public_policy',['key'=>'invalid'],'bad')];
        elseif($n===5&&isset($responses['bad']['error']))$parts=[$call('public_policy',['key'=>'shipping_cost'],'fixed')];
        elseif($n===7&&isset($responses['fixed']['value']))$parts=[['text'=>'<final_answer>'.$responses['fixed']['value'].'</final_answer>']];
        else{$fail('Rejected-call recovery failed');return;}
    }elseif($scenario==='denied'){
        if($n===1)$parts=[$call('own_order_status',[],'forbidden')];
        elseif($n===3&&isset($responses['forbidden']['error']))$parts=[['text'=>'<final_answer>Please sign in at /login.</final_answer>']];
        else{$fail('Unauthorized tool was not denied');return;}
    }elseif($scenario==='duplicate'){
        $parts=[$call('tool_search',['query'=>'public_policy'],'dup'),$call('tool_search',['query'=>'public_catalogue_summary'],'dup')];
    }elseif(in_array($scenario,['stopped_tool','object_tool_batch'],true)){
        $parts=[$call('tool_search',['query'=>'public_policy'],'unexpected')];
    }elseif($scenario==='oversized_signature'){
        if (!in_array('public_policy', $names, true)) {
            $fail('Oversized signature test needs an already advertised business tool');
            return;
        }
        $parts=[
            $call('public_policy',['key'=>'shipping_cost'],'valid-first-call'),
            $call('tool_search',['query'=>'public_policy'],'oversized-call'),
        ];
    }elseif($scenario==='missing_finish'){
        $parts=[['text'=>'<final_answer>Unverified partial answer.</final_answer>']];
    }elseif($scenario==='truncated'){
        $parts=[['text'=>'<final_answer>Unfinished text</final_answer>']];$finish='length';
    }elseif($scenario==='unfinished'){
        $parts=[$call('tool_search',['query'=>'public_policy'],'r'.$n)];
        if($n===9)$parts[]=['text'=>'<final_answer>Not a completed answer.</final_answer>'];
    }elseif($scenario==='transient_start'){
        $parts=[['text'=>'<final_answer>Hello! How can I help?</final_answer>']];
    }elseif(in_array($scenario,['guest','capability','slash_capabilities'],true)){
        if($n!==1){$fail('Unexpected tool round');return;}
        $parts=[['text'=>'<final_answer>'.($scenario==='guest'?'Please sign in to view your own orders at /login.':'I can use your authorized business tools. Ask me what you would like to investigate.').'</final_answer>']];
    } elseif ($scenario === 'slash_help') {
        if ($n === 1 && in_array('operator_guide', $names, true)) {
            $parts = [$call('operator_guide', ['topic'=>'blog-publishing','section'=>'Prepare an unpublished article'], 'guide')];
        } elseif ($n === 3 && ($last['report_url'] ?? '') === '/admin/docs/blog-publishing'
            && is_string($last['instructions'] ?? null) && $last['instructions'] !== '') {
            $parts = [['text'=>'<final_answer>The owner guide describes how to save an unpublished draft. See /admin/docs/blog-publishing.</final_answer>']];
        } else {
            $fail('Explicit owner help must use its initially advertised guide and finish in two model rounds');
            return;
        }
    } elseif ($directChoice !== null) {
        [$tool,$args,$skill,$expectedFacts] = $directChoice;
        if ($n === 1 && in_array($tool, $names, true)) {
            $parts = [$call($tool, $args, 'business')];
        } elseif ($n === 3 && array_keys($responses) === ['business'] && !isset($last['error'])
            && array_intersect_key($last, $expectedFacts) === $expectedFacts
            && in_array($skill, array_column($last['runtime_guidance'] ?? [], 'skill'), true)) {
            $answer = match ($scenario) {
                'direct_shop_total' => 'The catalogue contains '.$last['total'].' published products.',
                'direct_delivery_charge','router_changed_tool' => $last['value'],
                'direct_order_total' => 'There are '.$last['count'].' dated order records across all statuses.',
                'router_changed_answer' => 'fixture-continued-on-wrong-model',
            };
            // Both forms of a mismatched 200 response must stop the turn.
            $parts = $scenario === 'router_changed_tool'
                ? [$call($tool, $args, 'unexpected-second-business')]
                : [['text'=>'<final_answer>'.$answer.'</final_answer>']];
        } else {
            $fail('Initially advertised business lookup did not return its scoped facts and guidance in one call');
            return;
        }
    }elseif($choice!==null){
        [$tool,$args,$field,$skill]=$choice;
        if($n===1)$parts=[$call('tool_search',['query'=>$tool],'discover')];
        elseif($n===3&&in_array($tool,$names,true)&&($last['matches'][0]['name']??null)===$tool)$parts=[$call('read_runtime_skill',['skill'=>$skill],'skill')];
        elseif($n===5&&isset($last['instructions'])&&in_array($tool,$names,true))$parts=[$call($tool,$args,'business')];
        elseif($n===7&&array_key_exists($field,$last)&&!isset($last['error'])){
            $answer=match($scenario){
                'aggregate','catalogue_current','catalogue_fallback','catalogue_locked'=>'The catalogue contains '.$last['total'].' published products.',
                'catalogue_wrong'=>'The catalogue contains '.($last['total']+1).' published products.',
                'owner'=>'There are '.$last['count'].' dated order records in the requested period.',
                'owner_followup'=>'There are '.$last['count'].' orders from '.$last['from'].' through '.$last['to'].'.',
                'customer'=>'Your matching order count is '.$last['count'].'.',
                'ticket'=>'Your matching ticket count is '.$last['count'].'.',
                'policy','modern_policy','router_policy','transient_continuation'=>$last['value'],
                'contact'=>'I can offer a contact form for your review; nothing has been submitted.',
                'article','owner_article'=>'See the published support guide at '.$last['articles'][0]['path'].'.',
            };
            if ($tool === 'read_published_article' && (($last['articles'][0]['heading'] ?? null) !== 'Check private order or ticket information'
                || empty($last['articles'][0]['revision_sha256']) || empty($last['articles'][0]['content_sha256']))) {
                $fail('Missing retrieved passage or published revision');
                return;
            }
            $parts=[['text'=>'<final_answer>'.$answer.'</final_answer>']];
        }else{$fail('Unexpected tool exchange');return;}
    }else{$fail('Unknown fixture');return;}
    $message = ['role'=>'assistant','content'=>''];
    $calls = [];
    foreach ($parts as $part) {
        if (isset($part['id'])) {
            // Only Google may return the signature in a subsequent request.
            $part['extra_content'] = $signature($part['id']) + ['ignored'=>'fixture-ignored-extension'];
            if ($scenario === 'oversized_signature' && $part['id'] === 'oversized-call') {
                $part['extra_content']['google']['thought_signature'] = 'fixture-oversized-signature'.str_repeat('x', 262145);
            }
            $calls[] = $part;
        } else {
            $message['content'] .= $part['text'] ?? '';
        }
    }
    if ($calls !== []) {
        $message['content'] = null;
        $message['tool_calls'] = $scenario === 'object_tool_batch' ? (object)$calls : $calls;
        $message['extra_content'] = $signature('assistant') + ['ignored'=>'fixture-ignored-extension'];
        $message['reasoning_content'] = 'fixture-continuation';
        $message['reasoning_details'] = [['type'=>'fixture','id'=>'opaque-state']];
    }
    $choice = ['message'=>$message];
    if ($scenario !== 'missing_finish') {
        $choice['finish_reason'] = $calls !== [] && $scenario !== 'stopped_tool' ? 'tool_calls' : $finish;
    }
    $reply = ['choices'=>[$choice]];
    if ($scenario !== 'router_missing_model') {
        $reply['model'] = match (true) {
            $scenario === 'router_paid_model' => 'google/gemma-4-26b-a4b-it',
            $n > 1 && in_array($scenario, ['router_changed_tool','router_changed_answer'], true) => OPENROUTER_CHANGED_MODEL,
            $router => OPENROUTER_FREE_MODEL,
            default => $r['model'],
        };
    }
    echo json_encode($reply, JSON_THROW_ON_ERROR);
    return;
}
if(PHP_SAPI!=='cli')exit;
$socket=stream_socket_server('tcp://127.0.0.1:0');if(!$socket)throw new RuntimeException('Loopback bind failed');
$address=stream_socket_get_name($socket,false);fclose($socket);
$recoveryDir=sys_get_temp_dir().'/bapx-recovery-fixture-'.bin2hex(random_bytes(8));
mkdir($recoveryDir,0700);
$process=proc_open([PHP_BINARY,'-S',$address,__FILE__],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes,null,['BAPX_MODEL_DRIVEN_FIXTURE'=>'1','BAPX_RECOVERY_FIXTURE_DIR'=>$recoveryDir]+getenv());
if(!is_resource($process))throw new RuntimeException('Loopback startup failed');
try{
    $ready=false;
    for($i=0;$i<100;$i++){if($connection=@fsockopen('127.0.0.1',(int)substr(strrchr($address,':'),1),$errno,$errstr,.1)){fclose($connection);$ready=true;break;}usleep(20000);}
    if(!$ready)throw new RuntimeException('Loopback unavailable');
    $cases=[
        ['aggregate','support','guest','How many published products do you have and which categories are listed?','public_catalogue_summary'],
        ['policy','support','guest','How is shipping calculated?','public_policy'],
        ['guest','support','guest','Where is my order?',null],
        ['customer','support','customer','Please check my own orders.','own_order_status'],
        ['ticket','support','customer','How many of my tickets are closed?','own_ticket_status'],
        ['contact','support','guest','Please book me an appointment.','present_contact_enquiry'],
        ['article','support','guest','Read your support article.','read_published_article'],
        ['owner','admin','admin','How many orders in total?','order_count'],
        ['direct_shop_total','support','guest','How many items are in the shop?','public_catalogue_summary'],
        ['direct_delivery_charge','support','guest','What is the delivery charge?','public_policy'],
        ['direct_order_total','admin','admin','How many orders in total?','order_count'],
        ['capability','admin','admin','What kinds of store tasks can you help me with?',null],
        ['slash_capabilities','admin','admin','/capabilities',null],
        ['slash_help','admin','admin','/help blog-publishing','operator_guide'],
        ['outage','support','guest','How many published products do you have?',null],
        ['outage','admin','admin','How many orders in total?',null],
        ['owner_article','admin','admin','What does our public support guide say about ticket information?','read_published_article'],
    ];
    $cases=array_merge($cases,[
        ['mixed','support','guest','வணக்கம், உதவுங்கள்',['public_catalogue_summary','public_policy']],
        ['recover','support','guest','வணக்கம், உதவுங்கள்','public_policy'],
        ['denied','support','guest','Where is my order?',null],
        ['duplicate','support','guest','வணக்கம்',null],
        ['truncated','support','guest','Explain',null],
        ['unfinished','support','guest','Explain',null],
        ['transient_start','support','guest','hi',null],
        ['transient_continuation','support','guest','How is shipping calculated?','public_policy'],
        ['quota','support','guest','hi',null],
        ['long_retry','support','guest','hi',null],
        ['schema_error','support','guest','hi',null],
    ]);
    // Real controllers resume private saved chats through Chat Completions.
    $followupCases=[
        ['catalogue_stale','support','guest','Is that published product count still correct?',null],
        ['catalogue_wrong','support','guest','Is that published product count still correct?','public_catalogue_summary'],
        ['catalogue_current','support','guest','Is that published product count still correct?','public_catalogue_summary'],
        ['owner_followup','admin','admin','How many orders in that same period now? Include @privacy for reference.','order_count'],
    ];
    $privacyCases = [
        ['foreign_support','support','customer','Open the other customer conversation.',null],
        ['foreign_owner','admin','admin','Open the other owner conversation.',null],
    ];
    $providerRecovery = [
        ['catalogue_fallback','support','guest','Is that published product count still correct?','public_catalogue_summary'],
        ['catalogue_locked','support','guest','Is that published product count still correct?','public_catalogue_summary'],
    ];
    $chatCompletionBoundaries = [
        ['stopped_tool','support','guest','Help with shipping.',null],
        ['object_tool_batch','support','guest','Help with shipping.',null],
        ['missing_finish','admin','admin','What can you help with?',null],
        ['refusal','support','guest','Answer this unrelated question.',null],
    ];
    $standard = array_merge($cases, $followupCases, $privacyCases);
    $textAndTools = array_values(array_filter($cases, static fn(array $case): bool =>
        in_array($case[0], ['guest','capability','direct_delivery_charge','direct_order_total'], true)));
    $profiles = [
        'google' => [...$standard, ...$chatCompletionBoundaries,
            ['oversized_signature','support','guest','What is the delivery charge?',null]],
        'google_fallback' => $textAndTools,
        'compatible' => array_merge($standard, $providerRecovery, $chatCompletionBoundaries, [
            ['owner_multi_scope','admin','admin','Compare orders from 1 to 5 February and 1 to 5 March 2024.','order_count'],
            ['owner_ambiguous_followup','admin','admin','What about that same period?',null],
        ]),
        'openai' => [$cases[9], $followupCases[3]],
        'openai_legacy' => [$followupCases[3]],
        'openai_luna' => [$cases[9], $followupCases[3]],
        'compatible_modern' => [['modern_policy','support','guest','How is shipping calculated?','public_policy']],
        'openrouter' => [
            ...$textAndTools,
            ['router_policy','support','guest','How is shipping calculated?','public_policy'],
            ['transient_continuation','support','guest','How is shipping calculated?','public_policy'],
            ['router_missing_model','support','guest','What is the delivery charge?',null],
            ['router_paid_model','support','guest','What is the delivery charge?',null],
            ['router_changed_tool','support','guest','What is the delivery charge?','public_policy'],
            ['router_changed_answer','admin','admin','How many orders in total?','order_count'],
        ],
    ];
    foreach ($profiles as $protocol=>$requests) foreach ($requests as [$scenario,$surface,$role,$question,$tool]) {
        $env=['BAPX_MODEL_DRIVEN_FIXTURE'=>'1','BAPX_FIXTURE_ENDPOINT'=>'http://'.$address.'/'.$scenario,'BAPX_TEST_MODE'=>'1','BAPX_FIXTURE_PROTOCOL'=>$protocol]+getenv();
        if (in_array($scenario,['owner_multi_scope','owner_ambiguous_followup'],true)) {
            $chainDir=$recoveryDir.'/scope-chain';
            if ($scenario==='owner_multi_scope') {mkdir($chainDir,0700);mkdir($chainDir.'/logs',0700);}
            $env['BAPX_FIXTURE_SCOPE_CHAIN_DIR']=$chainDir;
        } else unset($env['BAPX_FIXTURE_SCOPE_CHAIN_DIR']);
        $child=proc_open([PHP_BINARY,__DIR__.'/fixtures/model-driven-agent-turn.php',$scenario,$surface,$role,$question],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$io,null,$env);
        if(!is_resource($child))throw new RuntimeException('Child startup failed');
        $raw=stream_get_contents($io[1]);$err=stream_get_contents($io[2]);fclose($io[1]);fclose($io[2]);$exit=proc_close($child);
        $result=json_decode($raw,true);
        $turn=$result['turn']??[];$response=$result['response']??[];
        if (str_starts_with($scenario, 'foreign_')) {
            if ($exit !== 0 || ($result['status'] ?? 0) < 400 || ($turn['model_attempts'] ?? []) !== []
                || is_file($recoveryDir . '/' . $scenario) || str_contains(json_encode($response), 'private answer')) {
                throw new RuntimeException('Foreign conversation crossed the actor boundary');
            }
            echo "PASS $protocol/$surface/$scenario: denied before provider transfer\n";
            continue;
        }
        $invalidChatCompletion = in_array($scenario, [
            'stopped_tool','object_tool_batch','missing_finish','oversized_signature','router_missing_model','router_paid_model',
        ], true);
        $routerChangedModel = in_array($scenario, ['router_changed_tool','router_changed_answer'], true);
        $expected=$invalidChatCompletion||$routerChangedModel||in_array($scenario,['outage','duplicate','truncated','unfinished','quota','long_retry','schema_error','catalogue_stale','catalogue_wrong','catalogue_locked'],true)?'error':'model';
        $rounds=match($scenario){'mixed','owner_multi_scope'=>3,'denied'=>2,'unfinished','transient_continuation','catalogue_fallback'=>5,
            'transient_start','slash_help','direct_shop_total','direct_delivery_charge','direct_order_total','router_changed_tool','router_changed_answer'=>2,
            'outage'=>6,default=>$tool!==null?4:1};
        if($exit!==0||($turn['outcome']??null)!==$expected||count($turn['model_attempts']??[])!==$rounds||($turn['turn_id']??null)!==($response['turn_id']??null)){
            throw new RuntimeException("$scenario/$surface: unexpected model lifecycle: ".substr($raw,0,2500)." STDERR: ".substr($err,0,1000));
        }
        if ($expected === 'model') {
            $surfaceId = $response['a2ui']['messages'][0]['createSurface']['surfaceId'] ?? '';
            if (!str_starts_with($surfaceId, 'bapx_' . $surface . '_')
                || ($turn['answer'] ?? '') !== ($response[$surface === 'admin' ? 'answer' : 'reply'] ?? null)) {
                throw new RuntimeException('Saved answer or native A2UI scope differed from the controller response');
            }
        } elseif (isset($response['a2ui']) || isset($response['ui'])) {
            throw new RuntimeException('A failed model run must not return an answer surface or action proposal');
        }
        $attempts = $turn['model_attempts'];
        $measured = $attempts[0];
        [$provider, $model] = match ($protocol) {
            'google' => ['google','gemma-4-31b-it'],
            'google_fallback' => ['google','gemma-4-26b-a4b-it'],
            'openrouter' => ['openrouter','openrouter/free'],
            'openai' => ['openai_compatible','gpt-5'],
            'openai_legacy' => ['openai_compatible','gpt-4o'],
            'openai_luna' => ['openai_compatible','gpt-6-luna'],
            default => ['openai_compatible','fixture-model'],
        };
        $providers = array_fill(0, $rounds, $provider);
        $models = array_fill(0, $rounds, $model);
        if ($scenario === 'catalogue_fallback') {
            $providers = [$provider, ...array_fill(0, $rounds - 1, 'google')];
            $models = [$model, ...array_fill(0, $rounds - 1, 'gemma-4-31b-it')];
        } elseif ($protocol === 'openrouter') {
            $models = ['openrouter/free', ...array_fill(0, $rounds - 1, OPENROUTER_FREE_MODEL)];
        }
        if (array_column($attempts, 'provider') !== $providers || array_column($attempts, 'model') !== $models) {
            throw new RuntimeException('Recorded provider/model sequence does not match the configured connection');
        }
        if ($invalidChatCompletion && (($measured['http_status'] ?? null) !== 200 || ($turn['tools'] ?? []) !== [])) {
            throw new RuntimeException('Malformed successful Chat Completions response must fail before tool dispatch');
        }
        if ($routerChangedModel && (array_column($attempts, 'http_status') !== [200, 200]
            || ($attempts[1]['response_model'] ?? null) !== OPENROUTER_CHANGED_MODEL
            || count($turn['tools'] ?? []) !== 1
            || str_contains($raw, 'fixture-continued-on-wrong-model'))) {
            throw new RuntimeException('OpenRouter accepted a tool or answer from a changed continuation model');
        }
        if (($measured['connect_ms'] ?? 0) <= 0 || ($measured['ready_ms'] ?? 0) <= 0 || ($measured['first_byte_ms'] ?? 0) <= 0) {
            throw new RuntimeException('Loopback request milestones missing from private attempt evidence');
        }
        if ($protocol === 'google' && ($scenario === 'policy' || $scenario === 'owner')) {
            $spans = array_values(array_filter($turn['trace']['spans'] ?? [],
                static fn(array $span): bool => ($span['type'] ?? '') === 'model_attempt'));
            if (($spans[0]['ready_ms'] ?? null) !== $measured['ready_ms']) {
                throw new RuntimeException('Transfer start not retained in the observed model span');
            }
        }
        foreach((array)$tool as $selected)if(!in_array($selected,array_column($turn['tools']??[],'name'),true))throw new RuntimeException("Missing selected tool: $selected");
        if (str_starts_with($scenario, 'direct_') && (($result['status'] ?? 0) !== 200 || count($turn['tools'] ?? []) !== 1)) {
            throw new RuntimeException('Direct lookup must deliver its model answer after one recorded tool execution');
        }
        if(!empty($response['ticket_id']))throw new RuntimeException('Unapproved ticket creation');
        if (str_starts_with($scenario, 'catalogue_') || $scenario === 'owner_followup') {
            if (($result['history_preserved'] ?? false) !== true) throw new RuntimeException('Existing conversation history was rewritten or lost');
        }
        if ($scenario === 'owner_multi_scope'
            && (($turn['report_context_ambiguous'] ?? false) !== true || isset($turn['report_context'])
                || count(array_filter($turn['tools'] ?? [],static fn(array $t):bool=>$t['name']==='order_count' && $t['status']==='complete'))!==2)) {
            throw new RuntimeException('Successful multi-scope controller turn did not persist an ambiguity boundary');
        }
        if ($scenario === 'owner_ambiguous_followup'
            && (($turn['tools'] ?? []) !== [] || !str_contains($turn['answer'] ?? '', 'Which of the two report periods'))) {
            throw new RuntimeException('Follow-up did not request clarification without an implicit lookup');
        }
        if ($scenario === 'owner_followup'
            && (($result['status'] ?? 0) !== 200 || !str_contains($response['answer'] ?? '', '2024-01-03 through 2024-01-07'))) {
            throw new RuntimeException('Owner follow-up did not rerun its tool for the last delivered report period');
        }
        if ($scenario === 'refusal' && ($response['reply'] ?? '') !== 'I can only help with store-related questions.') {
            throw new RuntimeException('Documented model refusal was discarded');
        }
        if(str_starts_with($scenario,'catalogue_')) {
            if(in_array($scenario,['catalogue_current','catalogue_fallback'],true)){
                if(($result['status']??0)!==200||!str_contains($response['reply']??'','0 published products'))
                    throw new RuntimeException('Matching fresh zero-product count was not delivered');
            }elseif(in_array($scenario,['catalogue_stale','catalogue_wrong'],true)){
                if(($result['status']??0)!==503||($response['run']['reason']??'')!=='catalogue_total_unverified'
                    ||!str_contains($response['reply']??'','/shop')||str_contains($response['reply']??'','published products'))
                    throw new RuntimeException('Unsupported catalogue total was not withheld with an explicit outcome');
                if($scenario==='catalogue_stale'&&($turn['tools']??[])!==[])throw new RuntimeException('Guard chose a tool instead of the model');
            }
        }
        if ($scenario === 'catalogue_fallback' && array_column($attempts,'http_status') !== [503,200,200,200,200]) {
            throw new RuntimeException('Pre-tool fallback did not preserve Chat Completions roles across providers');
        }
        if ($scenario === 'catalogue_locked' && (array_column($attempts,'http_status') !== [200,200,200,503]
            || count($turn['tools'] ?? []) !== 1)) {
            throw new RuntimeException('Fallback restarted an already executed tool exchange');
        }
        foreach (['fixture-continuation','opaque-state','fixture-google-signature','fixture-ignored-extension','fixture-oversized-signature'] as $marker) {
            if (str_contains($raw, $marker)) throw new RuntimeException('Provider-only continuation leaked');
        }
        if($scenario==='contact'&&($response['ui']['kind']??null)!=='contact_enquiry')throw new RuntimeException('Missing model-selected form proposal');
        if($scenario==='outage'&&($turn['tools']??[])!==[])throw new RuntimeException('Outage must not trigger canned lookup');
        if(in_array($scenario,['denied','duplicate','truncated'],true)&&($turn['tools']??[])!==[])throw new RuntimeException('Denied call executed');
        if($scenario==='recover'&&count(array_filter($turn['tools']??[],static fn(array $t):bool=>$t['name']==='public_policy'))!==2)throw new RuntimeException('Unadvertised tool executed');
        if($scenario==='transient_start'||$scenario==='transient_continuation'){
            $attempts=$turn['model_attempts'];
            $statusKey=array_key_exists('http_status',$attempts[0])?'http_status':'status';
            if(array_column($attempts,$statusKey)!==($scenario==='transient_start'?[503,200]:[200,200,200,503,200]))throw new RuntimeException('Missing observed transient recovery');
            if(count($turn['tools']??[])!==($scenario==='transient_start'?0:1))throw new RuntimeException('Recovery repeated business execution');
        }
        if ($tool === 'read_published_article') {
            $source = $turn['tools'][0]['evidence']['sources'][0] ?? [];
            if (($source['id'] ?? null) !== 'article:get-support' || empty($source['content_sha256'])
                || empty($source['revision_sha256']) || !is_int($source['section_index'] ?? null)
                || isset($source['heading']) || isset($source['text']) || !str_contains($turn['answer'] ?? '', '/blog/get-support')) {
                throw new RuntimeException('Passage evidence did not retain safe digests and section identity.');
            }
        }
        echo "PASS $protocol/$surface/$scenario: $expected, ".count($turn['model_attempts'])." HTTP rounds\n";
    }
}finally{proc_terminate($process);proc_close($process);foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($recoveryDir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $entry){if($entry->isDir())rmdir($entry->getPathname());else unlink($entry->getPathname());}rmdir($recoveryDir);}
