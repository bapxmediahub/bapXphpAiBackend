<?php
declare(strict_types=1);
// Scripted model decisions over real HTTP. Never uses real keys or live records.
if(PHP_SAPI==='cli-server'){
    if(getenv('BAPX_MODEL_DRIVEN_FIXTURE')!=='1'){http_response_code(404);return;}
    header('Content-Type: application/json');
    $scenario=explode('/',trim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/'))[0];
    if($scenario==='outage'){http_response_code(503);echo '{"error":{"status":"UNAVAILABLE"}}';return;}
    $r=json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
    $names=array_column($r['tools'][0]['functionDeclarations']??[],'name');
    $contents=$r['contents'];$n=count($contents);
    $last=$n>1?(end($contents)['parts'][0]['functionResponse']['response']??[]):[];
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
    if($scenario==='guest'||$scenario==='capability'){
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
    echo json_encode(['candidates'=>[['content'=>['parts'=>$parts]]]],JSON_THROW_ON_ERROR);return;
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
    foreach($cases as [$scenario,$surface,$role,$question,$tool]){
        $env=['BAPX_MODEL_DRIVEN_FIXTURE'=>'1','BAPX_FIXTURE_ENDPOINT'=>'http://'.$address.'/'.$scenario,'BAPX_TEST_MODE'=>'1']+getenv();
        $child=proc_open([PHP_BINARY,__DIR__.'/fixtures/model-driven-agent-turn.php',$scenario,$surface,$role,$question],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$io,null,$env);
        if(!is_resource($child))throw new RuntimeException('Child startup failed');
        $raw=stream_get_contents($io[1]);$err=stream_get_contents($io[2]);fclose($io[1]);fclose($io[2]);$exit=proc_close($child);
        $result=json_decode($raw,true);
        $expected=$scenario==='outage'?'error':'model';
        $turn=$result['turn']??[];$response=$result['response']??[];
        if($exit!==0||($turn['outcome']??null)!==$expected||count($turn['model_attempts']??[])!==($tool!==null?4:1)||($turn['turn_id']??null)!==($response['turn_id']??null)){
            throw new RuntimeException("$scenario/$surface: unexpected model lifecycle: ".substr($raw,0,2500)." STDERR: ".substr($err,0,1000));
        }
        if($tool!==null&&!in_array($tool,array_column($turn['tools']??[],'name'),true))throw new RuntimeException("Missing selected tool: $tool");
        if(!empty($response['ticket_id']))throw new RuntimeException('Unapproved ticket creation');
        if($scenario==='contact'&&($response['ui']['kind']??null)!=='contact_enquiry')throw new RuntimeException('Missing model-selected form proposal');
        if($scenario==='outage'&&($turn['tools']??[])!==[])throw new RuntimeException('Outage must not trigger canned lookup');
        echo "PASS $surface/$scenario: $expected, ".count($turn['model_attempts'])." real HTTP model rounds".($tool?" / $tool":'')."\n";
    }
}finally{proc_terminate($process);proc_close($process);}
