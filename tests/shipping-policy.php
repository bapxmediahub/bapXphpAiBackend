<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit;
$mysql=($argv[1]??'')==='--mysql';
foreach (['BAPX_TEST_MODE'=>$mysql?'0':'1','BAPX_MYSQL_HOST'=>'127.0.0.1','BAPX_MYSQL_PORT'=>'33068',
    'BAPX_MYSQL_DB'=>'sps_order_test','BAPX_MYSQL_USER'=>'sps_test','BAPX_MYSQL_PASS'=>'sps_ci_fixture',
    'BAPX_REMOTE_DB_URL'=>'http://127.0.0.1:1/remotedb','APP_URL'=>'https://shipping-fixture.invalid'] as $k=>$v) {
    putenv("$k=$v"); $_SERVER[$k]=$_ENV[$k]=$v;
}
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{DatabaseService,SettingsService,ShippingService};
$checks=0;
function check(bool $ok,string $label):void {
    global $checks;$checks++;
    if(!$ok){fwrite(STDERR,'Shipping draft fixture: '.$label."\n");throw new RuntimeException($label);}
}
function rejects(callable $operation,string $type,string $label):void {
    try{$operation();}catch(Throwable $error){check($error instanceof $type,$label);return;}
    check(false,$label);
}
$blank=['state'=>'','pins'=>'','method'=>'flat','fee'=>'','minimum'=>''];
$flat=['state'=>' Tamil Nadu ','pins'=>'641*, 641*,600001','method'=>'flat','fee'=>'75.2','minimum'=>''];
$free=['state'=>'Kerala','pins'=>'','method'=>'free','fee'=>'','minimum'=>'1000'];
$zones=ShippingService::normaliseZones([$blank,$flat,$free,$blank]);
check(count($zones)===3&&$zones[0]===$blank,'Preserve internal order, trim only empty trailing slots');
check($zones[1]===['state'=>'Tamil Nadu','pins'=>'641*, 600001','method'=>'flat','fee'=>'75.20','minimum'=>''],'Canonical state, patterns and decimal amount');
check($zones[2]['minimum']==='1000.00','Canonical free threshold');
check(ShippingService::normaliseZones($zones)===$zones,'Canonical rules are idempotent');
check(ShippingService::preview($zones,'Tamil Nadu','641001','100')===['status'=>'quoted','zone'=>2,'feePaise'=>7520],'Saved rule preview preserves original position and exact amount');
check(ShippingService::storedDraft(null)===['revision'=>'0','zones'=>[]],'Absent draft has explicit initial revision');
$rev=str_repeat('a',32);
check(ShippingService::storedDraft(['revision'=>$rev,'zones'=>$zones])===['revision'=>$rev,'zones'=>$zones],'Stored revision and validated ordered rules');
foreach ([['revision'=>'0','zones'=>[]],['revision'=>$rev,'zones'=>[],'active'=>true],['revision'=>$rev,'zones'=>[array_replace($flat,['fee'=>'1.001'])]]] as $bad) {
    rejects(fn()=>ShippingService::storedDraft($bad),RuntimeException::class,'Corrupt stored draft must fail closed');
}
foreach ([array_replace($flat,['active'=>true]),array_replace($flat,['fee'=>'0']),array_replace($flat,['method'=>'anything'])] as $bad) {
    rejects(fn()=>ShippingService::normaliseZones([$bad]),InvalidArgumentException::class,'Reject invalid or activation fields');
}
if (!$mysql) {echo "Shipping draft normalization and revision validation PASS ($checks checks)\n";exit;}
$db=new DatabaseService(true);$pdo=$db->connection();
check($pdo->query('SELECT DATABASE()')->fetchColumn()==='sps_order_test','Only isolated database');
$pdo->exec('CREATE TEMPORARY TABLE settings (id VARCHAR(36) PRIMARY KEY,_data JSON NOT NULL,_owner VARCHAR(255),_status VARCHAR(64),_created_at VARCHAR(40),_updated_at VARCHAR(40)) ENGINE=InnoDB');
try {
    $stmt=$pdo->prepare('INSERT INTO settings (id,_data) VALUES (?,?)');
    $stmt->execute(['legacy_fixture',json_encode(['id'=>'legacy_fixture','shipping_mode'=>'flat','flat_rate'=>25,'timezone'=>'Asia/Kolkata'],JSON_THROW_ON_ERROR)]);
    check($db->saveShippingDraft($zones,'0'),'Save initial draft in canonical row');
    $record=$db->find('settings','app_settings');$saved=ShippingService::storedDraft($record['shipping_policy_draft']);
    check($saved['revision']!=='0'&&$saved['zones']===$zones,'Actual stored revision and rules round-trip');
    check($record['shipping_mode']==='flat'&&(float)$record['flat_rate']===25.0&&$record['timezone']==='Asia/Kolkata','Legacy defaults retained and active charges untouched');
    $db->upsert('settings',['id'=>'app_settings','timezone'=>'UTC']);
    $record=$db->find('settings','app_settings');
    check(ShippingService::storedDraft($record['shipping_policy_draft'])===$saved&&$record['timezone']==='UTC','Ordinary settings patch preserves draft');
    check(!$db->saveShippingDraft([$free],'0'),'Stale first editor is refused');
    check(ShippingService::storedDraft($db->find('settings','app_settings')['shipping_policy_draft'])===$saved,'Stale save preserves winner');
    rejects(fn()=>$db->saveShippingDraft([array_replace($flat,['fee'=>'-1'])],$saved['revision']),InvalidArgumentException::class,'Invalid fee never reaches write');
    check($db->saveShippingDraft([$free],$saved['revision']),'Matching revision can update the draft');
    $second=ShippingService::storedDraft($db->find('settings','app_settings')['shipping_policy_draft']);
    check($second['revision']!==$saved['revision']&&$second['zones']===ShippingService::normaliseZones([$free]),'New revision owns the updated draft');
    check(!$db->saveShippingDraft([], $saved['revision']),'Stale edit cannot clear latest rules');
    check($db->saveShippingDraft([], $second['revision']),'Explicit latest-revision empty draft allowed');
    $record=$db->find('settings','app_settings');
    check(ShippingService::storedDraft($record['shipping_policy_draft'])['zones']===[]&&$record['shipping_mode']==='flat'&&(float)$record['flat_rate']===25.0,'Draft clearing does not alter active charges');
    SettingsService::flushCache();
    check((new SettingsService($db))->shippingDraft()===ShippingService::storedDraft($record['shipping_policy_draft']),'Settings service reads the same persisted draft');
    $db->upsert('settings',['id'=>'app_settings','shipping_policy_draft'=>['revision'=>'invalid','zones'=>[]]]);
    rejects(fn()=>$db->saveShippingDraft([$flat],'0'),RuntimeException::class,'Malformed stored revision refuses overwrite');
    check($db->find('settings','app_settings')['shipping_policy_draft']['revision']==='invalid','Corrupt state is not silently replaced');
} finally {
    SettingsService::flushCache();
    $pdo->exec('DROP TEMPORARY TABLE settings');
}
echo "Shipping draft real MySQL revision CAS, legacy defaults, isolation and unchanged charges PASS ($checks checks)\n";
