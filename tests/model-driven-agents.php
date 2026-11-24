<?php
declare(strict_types=1);
// Scripted model decisions over real HTTP. Never uses real keys or live records.
if(PHP_SAPI==='cli-server'){
    if(getenv('BAPX_MODEL_DRIVEN_FIXTURE')!=='1'){http_response_code(404);return;}
    header('Content-Type: application/json');
    $scenario=explode('/',trim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/'))[0];
    if($scenario==='outage'){http_response_code(503);echo '{"error":{"status":"UNAVAILABLE"}}';return;}
    $r=json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
    $compatible=isset($r['messages']);
    $names=$compatible?array_column(array_column($r['tools']??[],'function'),'name'):array_column($r['tools'][0]['functionDeclarations']??[],'name');
    $contents=$compatible?$r['messages']:$r['contents'];
    $n=$compatible?1+2*count(array_filter($contents,static fn(array $m):bool=>($m['role']??null)==='assistant')):count($contents);
    $responses=[];
    if($n>1){
        if($compatible){
            for($i=count($contents)-1;$i>0&&$contents[$i]['role']==='tool';$i--){
                $responses[$contents[$i]['tool_call_id']]=json_decode($contents[$i]['content'],true,64,JSON_THROW_ON_ERROR);
            }
            $previous=array_column($contents[$i]['tool_calls']??[],'id');
            if(($contents[$i]['reasoning_content']??null)!=='fixture-continuation'
                ||($contents[$i]['reasoning_details']??null)!==[['type'=>'fixture','id'=>'opaque-state']]){
                http_response_code(400);echo '{"error":{"message":"Continuation metadata missing"}}';return;
            }

        }else{
            foreach(end($contents)['parts']??[] as $part){
                $response=$part['functionResponse']??[];
                $responses[$response['id']??'']=$response['response']??[];
            }
            $previous=array_column(array_column($contents[count($contents)-2]['parts'],'functionCall'),'id');
        }
        if(count($previous)!==count($responses)||array_diff($previous,array_keys($responses))){
            http_response_code(400);echo '{"error":{"message":"Uncorrelated tool batch"}}';return;
        }
    }
    $last=$responses===[]?[]:reset($responses);
    $owner=in_array($scenario,['owner','capability'],true);
    $guest=!in_array($scenario,['owner','capability','customer','ticket'],true);
    $fail=static function(string $message):void{http_response_code(400);echo json_encode(['error'=>['message'=>$message]]);};
    if((!$owner&&in_array('order_count',$names,true))||($guest&&array_intersect(['own_order_status','own_ticket_status'],$names))){$fail('Role leak');return;}
    $call=static fn(string $name,array $args,string $id):array=>['functionCall'=>['name'=>$name,'args'=>(object)$args,'id'=>$id]];
    $choice=match($scenario){
        'aggregate'=>['public_catalogue_summary',[], 'total','catalogue'],
        'owner'=>['order_count',['period'=>'all_time'],'count','reporting'],
        'customer'=>['own_order_status',[],'count','customer-policy'],
        'ticket'=>['own_ticket_status',[],'count','customer-policy'],
        'article'=>['read_published_article',['slug'=>'get-support'],'found','customer-policy'],
        'contact'=>['present_contact_enquiry',[],'submitted','customer-policy'],
        'policy'=>['public_policy',['key'=>'shipping_cost'],'value','customer-policy'],
        default=>null
    };
    $finish='STOP';
    if($scenario==='mixed'){
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
    }elseif($scenario==='truncated'){
        $parts=[['text'=>'<final_answer>Unfinished text</final_answer>']];$finish='MAX_TOKENS';
    }elseif($scenario==='unfinished'){
        $parts=[$call('tool_search',['query'=>'public_policy'],'r'.$n)];
        if($n===9)$parts[]=['text'=>'<final_answer>Not a completed answer.</final_answer>'];
    }elseif($scenario==='guest'||$scenario==='capability'){
        if($n!==1){$fail('Unexpected tool round');return;}
        $parts=[['text'=>'<final_answer>'.($scenario==='guest'?'Please sign in to view your own orders at /login.':'I can use your authorized business tools. Ask me what you would like to investigate.').'</final_answer>']];
    }elseif($choice!==null){
        [$tool,$args,$field,$skill]=$choice;
        if($n===1)$parts=[$call('tool_search',['query'=>$tool],'discover')];
        elseif($n===3&&in_array($tool,$names,true)&&($last['matches'][0]['name']??null)===$tool)$parts=[$call('read_runtime_skill',['skill'=>$skill],'skill')];
        elseif($n===5&&isset($last['instructions'])&&in_array($tool,$names,true))$parts=[$call($tool,$args,'business')];
        elseif($n===7&&array_key_exists($field,$last)&&!isset($last['error'])){
            $answer=match($scenario){
                'aggregate'=>'The catalogue contains '.$last['total'].' published products.',
                'owner'=>'There are '.$last['count'].' dated order records in the requested period.',
                'customer'=>'Your matching order count is '.$last['count'].'.',
                'ticket'=>'Your matching ticket count is '.$last['count'].'.',
                'policy'=>$last['value'],
                'contact'=>'I can offer a contact form for your review; nothing has been submitted.',
                'article'=>'See the published support guide at '.$last['articles'][0]['path'].'.',
            };
            if($scenario==='article'&&empty($last['articles'][0]['text'])){$fail('Missing retrieved body');return;}
            $parts=[['text'=>'<final_answer>'.$answer.'</final_answer>']];
        }else{$fail('Unexpected tool exchange');return;}
    }else{$fail('Unknown fixture');return;}
    if($compatible){
        $message=['role'=>'assistant','content'=>''];$calls=[];
        foreach($parts as $part){
            if(isset($part['functionCall'])){
                $fc=$part['functionCall'];
                $calls[]=['id'=>$fc['id'],'type'=>'function','function'=>['name'=>$fc['name'],'arguments'=>json_encode($fc['args'],JSON_THROW_ON_ERROR)]];
            }else $message['content'].=$part['text']??'';
        }
        if($calls!==[]){
            $message['tool_calls']=$calls;
            $message['reasoning_content']='fixture-continuation';
            $message['reasoning_details']=[['type'=>'fixture','id'=>'opaque-state']];
        }
        echo json_encode(['choices'=>[['message'=>$message,'finish_reason'=>$finish==='MAX_TOKENS'?'length':($calls!==[]?'tool_calls':'stop')]]],JSON_THROW_ON_ERROR);
    }else echo json_encode(['candidates'=>[['content'=>['parts'=>$parts],'finishReason'=>$finish]]],JSON_THROW_ON_ERROR);
    return;
}
if(PHP_SAPI!=='cli')exit;
$socket=stream_socket_server('tcp://127.0.0.1:0');if(!$socket)throw new RuntimeException('Loopback bind failed');
$address=stream_socket_get_name($socket,false);fclose($socket);
$process=proc_open([PHP_BINARY,'-S',$address,__FILE__],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes,null,['BAPX_MODEL_DRIVEN_FIXTURE'=>'1']+getenv());
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
        ['capability','admin','admin','What kinds of store tasks can you help me with?',null],
        ['outage','support','guest','How many published products do you have?',null],
        ['outage','admin','admin','How many orders in total?',null],
    ];
    $cases=array_merge($cases,[
        ['mixed','support','guest','வணக்கம், உதவுங்கள்',['public_catalogue_summary','public_policy']],
        ['recover','support','guest','வணக்கம், உதவுங்கள்','public_policy'],
        ['denied','support','guest','Where is my order?',null],
        ['duplicate','support','guest','வணக்கம்',null],
        ['truncated','support','guest','Explain',null],
        ['unfinished','support','guest','Explain',null],
    ]);
    foreach(['google','compatible'] as $protocol)foreach($cases as [$scenario,$surface,$role,$question,$tool]){
        $env=['BAPX_MODEL_DRIVEN_FIXTURE'=>'1','BAPX_FIXTURE_ENDPOINT'=>'http://'.$address.'/'.$scenario,'BAPX_TEST_MODE'=>'1','BAPX_FIXTURE_PROTOCOL'=>$protocol]+getenv();
        $child=proc_open([PHP_BINARY,__DIR__.'/fixtures/model-driven-agent-turn.php',$scenario,$surface,$role,$question],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$io,null,$env);
        if(!is_resource($child))throw new RuntimeException('Child startup failed');
        $raw=stream_get_contents($io[1]);$err=stream_get_contents($io[2]);fclose($io[1]);fclose($io[2]);$exit=proc_close($child);
        $result=json_decode($raw,true);
        $expected=in_array($scenario,['outage','duplicate','truncated','unfinished'],true)?'error':'model';
        $rounds=match($scenario){'mixed'=>3,'denied'=>2,'unfinished'=>5,default=>$tool!==null?4:1};
        $turn=$result['turn']??[];$response=$result['response']??[];
        if($exit!==0||($turn['outcome']??null)!==$expected||count($turn['model_attempts']??[])!==$rounds||($turn['turn_id']??null)!==($response['turn_id']??null)){
            throw new RuntimeException("$scenario/$surface: unexpected model lifecycle: ".substr($raw,0,2500)." STDERR: ".substr($err,0,1000));
        }
        foreach((array)$tool as $selected)if(!in_array($selected,array_column($turn['tools']??[],'name'),true))throw new RuntimeException("Missing selected tool: $selected");
        if(!empty($response['ticket_id']))throw new RuntimeException('Unapproved ticket creation');
        if(str_contains($raw,'fixture-continuation')||str_contains($raw,'opaque-state'))throw new RuntimeException('Provider-only continuation leaked');
        if($protocol==='google'&&$scenario==='contact'&&($response['ui']['kind']??null)!=='contact_enquiry')throw new RuntimeException('Missing model-selected form proposal');
        if($scenario==='outage'&&($turn['tools']??[])!==[])throw new RuntimeException('Outage must not trigger canned lookup');
        if(in_array($scenario,['denied','duplicate','truncated'],true)&&($turn['tools']??[])!==[])throw new RuntimeException('Denied call executed');
        if($scenario==='recover'&&count(array_filter($turn['tools']??[],static fn(array $t):bool=>$t['name']==='public_policy'))!==2)throw new RuntimeException('Unadvertised tool executed');
        if($scenario==='article'&&empty($turn['tools'][0]['evidence']['sources'][0]['content_sha256']))throw new RuntimeException('Retrieved document digest missing');
        echo "PASS $protocol/$surface/$scenario: $expected, ".count($turn['model_attempts'])." HTTP rounds\n";
    }
}finally{proc_terminate($process);proc_close($process);}
