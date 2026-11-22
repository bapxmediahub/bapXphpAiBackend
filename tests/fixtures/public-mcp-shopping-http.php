<?php
declare(strict_types=1);
// A disposable CI database, never a configurable production target.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
foreach(['BAPX_MYSQL_HOST'=>'127.0.0.1','BAPX_MYSQL_PORT'=>'33068','BAPX_MYSQL_DB'=>'sps_order_test',
    'BAPX_MYSQL_USER'=>'sps_test','BAPX_MYSQL_PASS'=>'sps_ci_fixture','BAPX_REMOTE_DB_URL'=>'http://127.0.0.1:1/remotedb',
    'BAPX_TEST_MODE'=>'0','APP_URL'=>'https://cart.example.test']as$key=>$value){putenv($key.'='.$value);$_SERVER[$key]=$value;}
require dirname(__DIR__,2).'/app/bootstrap.php';
use App\Services\{DatabaseService,SettingsService};
$store=new DatabaseService(true);$pdo=$store->connection();
if($pdo->query('SELECT DATABASE()')->fetchColumn()!=='sps_order_test')throw new RuntimeException('Refuse a nonfixture database.');
$orderCount=static fn():int=>$pdo->query("SHOW TABLES LIKE 'orders'")->fetchColumn()===false?0:(int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$action=$argv[1]??'';
if($action==='seed'){
    $createdTables=[];
    foreach(['products','settings']as$table){
        if($pdo->query("SHOW TABLES LIKE '$table'")->fetchColumn()!==false)continue;
        $pdo->exec("CREATE TABLE $table (id VARCHAR(64) PRIMARY KEY,_data JSON NOT NULL,_owner VARCHAR(255),_status VARCHAR(64),_created_at VARCHAR(40),_updated_at VARCHAR(40)) ENGINE=InnoDB");
        $createdTables[]=$table;
    }
    $settings=$store->find('settings',SettingsService::SETTINGS_ID);
    $id='mcp-cart-http-'.bin2hex(random_bytes(6));
    $store->upsert('settings',['id'=>SettingsService::SETTINGS_ID,'module_shop'=>'1','module_blog'=>'1']);
    $store->upsert('products',['id'=>$id,'slug'=>$id,'name'=>'Disposable MCP Product','description'=>'Synthetic transport fixture.',
        'price'=>123.45,'stock_status'=>'in_stock','image_url'=>'/assets/images/products/varahi-amman-ring-front.webp',
        'owner_note'=>'PRIVATE_HTTP_CART_FIXTURE']);
    echo json_encode(['productId'=>$id,'settingsBefore'=>$settings,'ordersBefore'=>$orderCount(),'createdTables'=>$createdTables],JSON_THROW_ON_ERROR);
}elseif($action==='cleanup'){
    $state=json_decode(file_get_contents('php://stdin'),true,32,JSON_THROW_ON_ERROR);
    if(!is_array($state)||!preg_match('/^mcp-cart-http-[a-f0-9]{12}$/D',(string)($state['productId']??'')))throw new RuntimeException('Invalid fixture cleanup.');
    $createdTables=$state['createdTables']??null;
    if(!is_array($createdTables)||!array_is_list($createdTables)||count($createdTables)>2
        ||array_filter($createdTables,static fn(mixed $table):bool=>!is_string($table)||!in_array($table,['products','settings'],true))
        ||count(array_unique($createdTables))!==count($createdTables))throw new RuntimeException('Invalid fixture-created tables.');
    $unchanged=$orderCount()===($state['ordersBefore']??null);
    foreach($state['cartIds']??[]as$id){
        if(!is_string($id)||!preg_match('/^[a-f0-9]{32}$/D',$id))throw new RuntimeException('Invalid fixture cart id.');
        $delete=$pdo->prepare('DELETE FROM agent_carts WHERE id=?');$delete->execute([$id]);
    }
    $delete=$pdo->prepare('DELETE FROM products WHERE id=?');$delete->execute([$state['productId']]);
    if(is_array($state['settingsBefore']??null))$store->upsert('settings',$state['settingsBefore']);
    else{$delete=$pdo->prepare('DELETE FROM settings WHERE id=?');$delete->execute([SettingsService::SETTINGS_ID]);}
    // Preserve pre-existing tables and any data that appeared outside this fixture.
    foreach($createdTables as$table){
        if((int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn()===0)$pdo->exec("DROP TABLE $table");
    }
    echo json_encode(['ordersUnchanged'=>$unchanged&&$orderCount()===($state['ordersBefore']??null)],JSON_THROW_ON_ERROR);
}else throw new RuntimeException('Choose seed or cleanup.');
