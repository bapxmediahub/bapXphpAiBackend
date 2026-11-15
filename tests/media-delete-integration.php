<?php
declare(strict_types=1);

/**
 * Isolated authenticated fake /remotedb and unique disposable media files.
 * DOES NOT connect to the production host or delete real customer images.
 */
foreach ([
    'BAPX_MYSQL_HOST'=>'127.0.0.1',
    'BAPX_MYSQL_PORT'=>'1',
    'BAPX_MYSQL_DB'=>'fixture_only',
    'BAPX_MYSQL_USER'=>'fixture_only',
    'BAPX_MYSQL_PASS'=>'sps_fixture_only_password',
    'BAPX_REMOTE_DB_URL'=>'http://127.0.0.1:18891/remotedb',
    'BAPX_TEST_MODE'=>'0',
] as $key=>$value) { putenv($key.'='.$value); $_SERVER[$key]=$value; }
require dirname(__DIR__).'/app/bootstrap.php';

use App\Services\MediaService;
use App\Services\DatabaseService;

$ids=array_map(static fn()=>bin2hex(random_bytes(8)),range(1,3));
$names=array_map(static fn()=> 'sps-fixture-'.bin2hex(random_bytes(6)).'.webp',range(1,3));
$directory=MediaService::persistentDirectory().'/2026/11';
$createdDirectory=!is_dir($directory);
if (!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)) {
    throw new RuntimeException('Could not create local disposable media fixture directory');
}
$urls=array_map(static fn(string $name):string=>'/assets/images/media/2026/11/'.$name,$names);
$files=array_map(static fn(string $name):string=>$directory.'/'.$name,$names);
$records=[];
foreach($ids as $i=>$id) {
    $records[]=['id'=>$id,'url'=>$urls[$i],'context'=>'shared','filename'=>$names[$i],
        'source'=>'upload', 'used_in'=>[]];
}
$stateFile=tempnam(sys_get_temp_dir(),'sps-media-state-');
$logFile=tempnam(sys_get_temp_dir(),'sps-media-bridge-');
$state=['collections'=>[
    'media_files'=>$records,'products'=>[['id'=>'product-fixture','name'=>'Fixture product','image_url'=>$urls[0]]],
    'temples'=>[],'categories'=>[],'settings'=>[],
], 'reject_delete_ids'=>[$ids[2]]];
file_put_contents($stateFile,json_encode($state,JSON_THROW_ON_ERROR));
putenv('BAPX_MEDIA_FIXTURE_STATE='.$stateFile);
$bridge=null;
try {
    foreach($files as $file)file_put_contents($file,'disposable-fixture-only');
    $bridge=proc_open([PHP_BINARY,'-S','127.0.0.1:18891',__DIR__.'/fixtures/media-bridge.php'],
        [0=>['pipe','r'],1=>['file',$logFile,'a'],2=>['file',$logFile,'a']],$pipes,dirname(__DIR__));
    if (!is_resource($bridge)) throw new RuntimeException('Could not start isolated fixture bridge');
    fclose($pipes[0]);
    $connected=false;
    for($i=0;$i<50;$i++) {
        $socket=@fsockopen('127.0.0.1',18891);
        if($socket!==false){fclose($socket);$connected=true;break;}
        usleep(100000);
    }
    if(!$connected)throw new RuntimeException('Isolated bridge not listening');
    $media=new MediaService(store:new DatabaseService());

    try {
        $media->delete($ids[0]);
        throw new RuntimeException('Deletion must reject images currently used by products');
    } catch(InvalidArgumentException $e){
        if (!str_contains($e->getMessage(),'referenced'))throw $e;
    }
    if(!is_file($files[0]))throw new RuntimeException('Referenced image was removed');

    try {
        $media->delete($ids[2]);
        throw new RuntimeException('Expected simulated metadata failure');
    } catch(RuntimeException $e) {
        if (!str_contains($e->getMessage(),'fixture metadata removal unavailable')) throw $e;
    }
    if(!is_file($files[2]))throw new RuntimeException('Failed DB mutation must restore upload');

    if($media->delete($ids[1])!==true || is_file($files[1])) {
        throw new RuntimeException('Unused disposable uploaded image was not deleted');
    }
    $after=json_decode((string)file_get_contents($stateFile),true,flags:JSON_THROW_ON_ERROR);
    $remaining=array_column($after['collections']['media_files'],'id');
    if(in_array($ids[1],$remaining,true) || !in_array($ids[0],$remaining,true)
        || !in_array($ids[2],$remaining,true)) {
        throw new RuntimeException('Fixture metadata after guarded deletion is incorrect');
    }
    echo "Isolated media deletion: referenced image blocked; failed DB mutation rolled back; disposable unused image removed.\n";
} finally {
    if(is_resource($bridge)){proc_terminate($bridge);proc_close($bridge);}
    foreach($files as $file)if(is_file($file))unlink($file);
    foreach($ids as $i=>$id) {
        $trash=dirname($files[$i]).'/.trash/'.$id.'-'.$names[$i];
        if(is_file($trash))unlink($trash);
    }
    if(is_file($stateFile))unlink($stateFile);
    if(is_file($logFile))unlink($logFile);
    if($createdDirectory && is_dir($directory))@rmdir($directory);
}
