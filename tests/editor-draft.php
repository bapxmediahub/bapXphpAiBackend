<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
$mysql = in_array($argv[1]??'', ['--mysql','--worker'],true);
foreach (['BAPX_TEST_MODE'=>$mysql?'0':'1','BAPX_MYSQL_HOST'=>'127.0.0.1','BAPX_MYSQL_PORT'=>'33068',
    'BAPX_MYSQL_DB'=>'sps_order_test','BAPX_MYSQL_USER'=>'sps_test','BAPX_MYSQL_PASS'=>'sps_ci_fixture',
    'BAPX_REMOTE_DB_URL'=>'http://127.0.0.1:1/remotedb','APP_URL'=>'https://editor-fixture.invalid'] as $k=>$v) {
    putenv("$k=$v"); $_SERVER[$k]=$_ENV[$k]=$v;
}
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{DatabaseService,EditorDraftService,SettingsService};
function check(bool $ok,string $message):void {
    if(!$ok) {
        // All check labels in this fixture are fixed developer assertions.
        fwrite(STDERR,'Editor recovery fixture assertion: '.$message."\n");
        throw new RuntimeException($message);
    }
}
/** JSON objects have no key ordering; preserve array order and scalar types. */
function canonicalFixtureJson(mixed $value): mixed {
    if(!is_array($value))return $value;
    if(!array_is_list($value))ksort($value,SORT_STRING);
    foreach($value as &$child)$child=canonicalFixtureJson($child);
    unset($child);
    return $value;
}
if (($argv[1]??'')==='--worker') {
    $args=json_decode($argv[2],true,flags:JSON_THROW_ON_ERROR);
    $db=new DatabaseService(true);
    if ($db->connection()->query('SELECT DATABASE()')->fetchColumn()!=='sps_order_test') throw new RuntimeException('Refusing nonfixture database.');
    echo "ready\n";flush();check(trim((string)fgets(STDIN))==='go','Worker barrier');
    try {
        echo json_encode($db->editorDraftOperation(...$args),JSON_THROW_ON_ERROR);
        exit(0);
    } catch (Throwable $error) {
        // This process only accesses a disposable fixture database. Preserve
        // useful bounded diagnostics without printing SQL, data or credentials.
        $diagnostic = ['class'=>$error::class,'file'=>basename($error->getFile()),'line'=>$error->getLine()];
        if ($error instanceof PDOException) {
            $state=$error->errorInfo[0]??null;$vendor=$error->errorInfo[1]??null;
            if (is_string($state)&&preg_match('/^[A-Z0-9]{5}$/D',$state)) $diagnostic['sqlstate']=$state;
            if (is_int($vendor)) $diagnostic['vendor_code']=$vendor;
        }
        fwrite(STDERR,'[editor-worker] '.json_encode($diagnostic,JSON_THROW_ON_ERROR)."\n");
        exit(1);
    }
}
$a=EditorDraftService::owner(['role'=>'admin','sub'=>'owner-fixture-a']);
$b=EditorDraftService::owner(['role'=>'admin','sub'=>'owner-fixture-b']);
$rev=str_repeat('a',32);$slug='fixture-editor-'.bin2hex(random_bytes(4));
$v=['slug'=>$slug,'title'=>'Unsaved title','content'=>'Unsaved body','campaign_fields[detail_1][label]'=>'Question'];
check(EditorDraftService::identity($a,'page',$slug)!==EditorDraftService::identity($b,'page',$slug),'Different owner identity');
check(EditorDraftService::identity($a,'page',$slug)!==EditorDraftService::identity($a,'blog',$slug),'Different document type');
$old=null;
for($i=1;$i<=4;$i++) $old=EditorDraftService::next($a,'page',$slug,$rev,array_replace($v,['title'=>"Version $i"]),$old,$i);
check(count($old['versions'])===3 && array_column($old['versions'],'at')===[4,3,2],'Bounded newest-first recovery history');
check(EditorDraftService::next($a,'page',$slug,$rev,$old['versions'][0]['values'],$old,5)===$old,'Identical input retains token');
foreach ([['published'=>'1'],['status'=>'published'],['notify_subscribers'=>'1'],['unknown'=>'x'],['content'=>str_repeat('x',65537)],['title'=>['bad']]] as $bad) {
    try { EditorDraftService::values('page',array_replace($v,$bad)); throw new RuntimeException('Unapproved draft accepted'); }
    catch (InvalidArgumentException) {}
}
try { EditorDraftService::owner(['role'=>'customer','sub'=>'owner-fixture-a']); throw new RuntimeException('Customer impersonated owner'); }
catch (DomainException) {}
try { EditorDraftService::identity($a,'page','../private'); throw new RuntimeException('Traversal accepted'); }
catch (InvalidArgumentException) {}
$blog=['slug'=>$slug,'title'=>'Future editorial draft','content'=>'Not published','published_at'=>'2099-01-01'];
check(EditorDraftService::values('blog',$blog)===$blog,'Future date recovery does not publish');
$large= ['slug'=>$slug,'title'=>'Tamil recovery','content'=>str_repeat('தமிழ் ',50000)];
$largeA=EditorDraftService::next($a,'blog',$slug,str_repeat('a',64),$large,null,1);
$large['title']='Second version';
$largeB=EditorDraftService::next($a,'blog',$slug,str_repeat('a',64),$large,$largeA,2);
check(strlen(json_encode($largeB,JSON_THROW_ON_ERROR))<=2500000
    && $largeB['versions'][0]['values']===$large,'Bound actual encoded bytes; latest Unicode copy intact');

// Deterministically test the policy even when the real race does not deadlock.
$failure=static function(string $state,int $code):PDOException {
    $error=new PDOException('Synthetic fixture failure');
    $error->errorInfo=[$state,$code,'Synthetic fixture failure'];
    return $error;
};
check(canonicalFixtureJson(['owner'=>'fixture','versions'=>[['at'=>1,'values'=>['title'=>'x','slug'=>'y']]]])
    ===canonicalFixtureJson(['versions'=>[['values'=>['slug'=>'y','title'=>'x'],'at'=>1]],'owner'=>'fixture']),
    'JSON object key normalization does not change stored evidence');
check(canonicalFixtureJson(['versions'=>[1,2]])!==canonicalFixtureJson(['versions'=>[2,1]])
    &&canonicalFixtureJson(['at'=>1])!==canonicalFixtureJson(['at'=>'1']),
    'JSON normalization preserves array order and exact scalar types');
$attempts=0;$conflict=['outcome'=>'conflict','draft'=>['token'=>'winning-token']];
$result=EditorDraftService::retryDeadlock(static function() use(&$attempts,$failure,$conflict):array {
    if (++$attempts===1)throw $failure('40001',1213);
    return $conflict;
});
check($attempts===2&&$result===$conflict,'Confirmed rollback re-observes the competing winner');
foreach([['40001',1213,3],['HY000',1205,1],['HY000',2006,1],['23000',1062,1],['40001',9999,1]] as [$state,$code,$limit]){
    $attempts=0;$original=$failure($state,$code);$caught=null;
    try {EditorDraftService::retryDeadlock(static function() use(&$attempts,$original):array {
        ++$attempts;throw $original;
    });}catch(PDOException $error){$caught=$error;}
    check($caught===$original&&$attempts===$limit,'Only confirmed rollback is retried with a strict bound');
}
if (!$mysql) {echo "Editor recovery validation, identity, history and bounded deadlock policy PASS\n";exit;}
$db=new DatabaseService(true);$pdo=$db->connection();
check($pdo->query('SELECT DATABASE()')->fetchColumn()==='sps_order_test','Only fixture DB');
$pdo->exec("CREATE TABLE IF NOT EXISTS settings (id VARCHAR(64) PRIMARY KEY,_data JSON NOT NULL,_owner VARCHAR(255),_status VARCHAR(64),_created_at VARCHAR(40),_updated_at VARCHAR(40)) ENGINE=InnoDB");
$keys=[EditorDraftService::identity($a,'page',$slug),EditorDraftService::identity($b,'page',$slug),EditorDraftService::identity($a,'blog',$slug)];
try {
    $initial=$db->editorDraftOperation($a,'page',$slug,'write','',$rev,$v);
    check($initial['outcome']==='saved','Initial recovery stored');
    $token=$initial['draft']['token'];
    check($db->editorDraftOperation($b,'page',$slug,'read')['draft']===null,'Other owner cannot read recovery');
    $race=static function(string $expected) use($a,$slug,$rev,$v):array {
        $children=[];
        foreach(['A','B'] as $label) {
            $args=[$a,'page',$slug,'write',$expected,$rev,array_replace($v,['title'=>'Concurrent '.$label])];
            $child=proc_open([PHP_BINARY,__FILE__,'--worker',json_encode($args,JSON_THROW_ON_ERROR)],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            check(is_resource($child),'Worker creation');$children[]=[$child,$pipes];
        }
        foreach($children as [, $pipes]) check(trim((string)fgets($pipes[1]))==='ready','Worker ready');
        foreach($children as [, $pipes]) {fwrite($pipes[0],"go\n");fclose($pipes[0]);}
        $out=[];$failed=false;
        foreach($children as $index=>[$child,$pipes]) {
            $raw=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);
            fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($child);
            if($exit!==0||$stderr!==''||!is_string($raw)){
                $failed=true;
                $diagnostic=['worker'=>$index,'exit'=>$exit,'stdout_bytes'=>is_string($raw)?strlen($raw):0,'stderr_bytes'=>is_string($stderr)?strlen($stderr):0];
                foreach(explode("\n",(string)$stderr) as $line){
                    if(preg_match('/^\[editor-worker\] (\{.*\})$/D',$line,$matches)){
                        $record=json_decode($matches[1],true);
                        if(is_array($record)){
                            $diagnostic['exception']=array_intersect_key($record,array_flip(['class','file','line','sqlstate','vendor_code']));
                        }
                    }elseif(preg_match('/^\[cli failure\] [A-Za-z0-9_\\\\]+ at [A-Za-z0-9_.-]+:[0-9]+$/D',$line)){
                        $diagnostic['bootstrap_failure']=$line;
                    }
                }
                fwrite(STDERR,'Editor recovery fixture worker failed: '.json_encode($diagnostic,JSON_THROW_ON_ERROR)."\n");
                continue;
            }
            try{$result=json_decode($raw,true,flags:JSON_THROW_ON_ERROR);}
            catch(JsonException){
                $failed=true;
                fwrite(STDERR,'Editor recovery fixture worker '.$index.' returned invalid JSON ('.strlen($raw)." bytes).\n");
                continue;
            }
            if(!is_array($result)||!in_array($result['outcome']??null,['saved','conflict'],true)){
                $failed=true;
                fwrite(STDERR,'Editor recovery fixture worker '.$index." returned an unexpected outcome.\n");
                continue;
            }
            $out[]=$result;
        }
        check(!$failed&&count($out)===2,'Both workers must complete with valid observed outcomes');
        return $out;
    };
    $out=$race($token);$states=array_column($out,'outcome');sort($states);
    check($states===['conflict','saved'],'Exactly one concurrent CAS wins');
    $latest=$db->editorDraftOperation($a,'page',$slug,'read')['draft'];
    check(count($latest['versions'])===2 && $latest['versions'][1]['values']==$v,'Losing edit did not erase earlier version');
    check($db->editorDraftOperation($a,'page',$slug,'discard',$token)['outcome']==='conflict','Stale discard refused');
    check($db->editorDraftOperation($b,'page',$slug,'write','',$rev,$v)['outcome']==='saved','Other editor has separate draft');
    check($db->editorDraftOperation($a,'blog',$slug,'write','',str_repeat('a',64),$blog)['outcome']==='saved','Blog recovery independent');
    SettingsService::flushCache();
    check(!isset((new SettingsService($db))->public()['editor_recovery']),'Private recovery cannot become public settings');
    check($db->editorDraftOperation($a,'page',$slug,'discard',$latest['token'])['outcome']==='discarded','Matching discard clears only own copy');
    check($db->editorDraftOperation($a,'page',$slug,'read')['draft']===null && $db->editorDraftOperation($b,'page',$slug,'read')['draft']!==null,'Discard isolation');
    for ($round=0;$round<12;$round++) {
        $out=$race('');$states=array_column($out,'outcome');sort($states);
        check($states===['conflict','saved'],'Concurrent initial insert has one winner');
        $latest=$db->editorDraftOperation($a,'page',$slug,'read')['draft'];
        $winner=array_values(array_filter($out,static fn($result)=>$result['outcome']==='saved'))[0]['draft'];
        check(canonicalFixtureJson($latest)===canonicalFixtureJson($winner)&&count($latest['versions'])===1,'Only the winning initial copy persisted');
        check($db->editorDraftOperation($a,'page',$slug,'discard',$latest['token'])['outcome']==='discarded','Clear only the exact fixture winner before next race');
    }
    $pdo->beginTransaction();
    try {
        $denied=false;
        try {$db->editorDraftOperation($a,'page',$slug,'write','',$rev,$v);}
        catch (LogicException) {$denied=true;}
        check($denied&&$pdo->inTransaction(),'Refuse ambient transaction without rollback or write');
    }finally {$pdo->rollBack();}
    check($db->editorDraftOperation($a,'page',$slug,'read')['draft']===null,'Ambient write did not create recovery');
    echo "Editor recovery MySQL CAS, 12 simultaneous initial insert races, role and namespace isolation PASS\n";
} finally {
    $delete=$pdo->prepare('DELETE FROM settings WHERE id=?');
    foreach($keys as $key) $delete->execute([$key]);
}
