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
$recorded = ['type'=>'products','id'=>'product-fixture','name'=>'Fixture product'];
$records[0]['used_in'] = [$recorded];
$records[1]['used_in'] = [$recorded, ['type'=>'products','id'=>'deleted-fixture-product']];
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

    $review = $media->reviewDeletionSelection([$ids[0], $ids[1]]);
    if (array_column($review, 'id') !== [$ids[0], $ids[1]]
        || array_column($review, 'name') !== [$names[0], $names[1]]
        || !is_file($files[0]) || !is_file($files[1])) {
        throw new RuntimeException('Selection review must show the exact uploads without deleting them');
    }
    foreach ([[], 'invalid', [[$ids[0]]], array_fill(0, 11, $ids[0]), [$ids[0], $ids[0]], ['file:/assets/images/bundled.png'], [bin2hex(random_bytes(8))]] as $selection) {
        try {
            $media->reviewDeletionSelection($selection);
            throw new RuntimeException('Malformed or stale selections must be rejected before deletion');
        } catch (InvalidArgumentException) {
            // The same primary keys and file bytes remain available after a rejected preview.
        }
    }

    $html = (static function (array $items): string {
        $csrf = 'fixture-csrf';
        ob_start();
        require dirname(__DIR__) . '/views/admin/media.php';
        return ob_get_clean();
    })([$records[1]]);
    $doc = new DOMDocument();
    $doc->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $forms = new DOMXPath($doc);
    $actionId = $forms->evaluate('string(//form[@action="/admin/media/delete"]/input[@name="id"]/@value)');
    if ($actionId !== $ids[1]) throw new RuntimeException('Historical upload must retain its owner deletion form');
    $selectedId = $forms->evaluate('string(//input[@form="media-delete-selection"]/@value)');
    if ($selectedId !== $ids[1]) throw new RuntimeException('Selection checkbox must target only its upload');

    try {
        $media->delete($ids[0]);
        throw new RuntimeException('Deletion must reject images currently used by products');
    } catch(InvalidArgumentException $e){
        if (!str_contains($e->getMessage(),'referenced'))throw $e;
    }
    if(!is_file($files[0]))throw new RuntimeException('Referenced image was removed');

    $blocked = $state;
    foreach ([['type'=>'unknown','id'=>'fixture'], ['type'=>'products'], 'legacy-label'] as $unresolved) {
        $blocked['collections']['media_files'][1]['used_in'] = [$unresolved];
        file_put_contents($stateFile,json_encode($blocked,JSON_THROW_ON_ERROR));
        DatabaseService::clearReadCache();
        try {
            $media->delete($ids[1]);
            throw new RuntimeException('Unverified recorded usage must prevent deletion');
        } catch (InvalidArgumentException $error) {
            if (!str_contains($error->getMessage(), 'verified')) throw $error;
        }
        if (!is_file($files[1])) throw new RuntimeException('Unverified reference lost its binary');
    }
    file_put_contents($stateFile,json_encode($state,JSON_THROW_ON_ERROR));
    DatabaseService::clearReadCache();
    try {
        $media->delete($ids[2]);
        throw new RuntimeException('Expected simulated metadata failure');
    } catch(RuntimeException $e) {
        if (!str_contains($e->getMessage(),'fixture metadata removal unavailable')) throw $e;
    }
    if(!is_file($files[2]))throw new RuntimeException('Failed DB mutation must restore upload');

    $pages = new \App\Services\AuthoredPageService();
    $draft = ['slug'=>'media-fixture', 'title'=>'Fixture page', 'content'=>'Fixture content', 'status'=>'draft',
        'expected_revision'=>'0', 'header_image_url'=>$urls[1], 'header_image_alt'=>'Fixture alt'];
    if (($media->findPageHeaderUpload($urls[1])['id'] ?? null) !== $ids[1]) {
        throw new RuntimeException('Exact owned upload should resolve across the bridge');
    }
    $pages->save($draft);
    $saved = $pages->find($draft['slug']);
    if (($saved['header_image_url'] ?? null) !== $urls[1] || ($saved['header_image_alt'] ?? null) !== 'Fixture alt'
        || $pages->findPublic($draft['slug']) !== null) {
        throw new RuntimeException('Header and alternative text must save with the same private draft');
    }
    $edit = array_replace($draft, ['expected_revision'=>$saved['revision'], 'status'=>'published']);
    try {
        $pages->save(array_replace($edit, ['header_image_url'=>'/assets/images/media/2026/11/unknown-fixture.webp']));
        throw new RuntimeException('An unavailable header must be rejected by the bridge');
    } catch (InvalidArgumentException $error) {
        if (!str_contains($error->getMessage(), 'available uploaded image')) throw $error;
    }
    $pages->save($edit);
    if (($pages->findPublic($draft['slug'])['header_image_url'] ?? null) !== $urls[1]) {
        throw new RuntimeException('Published page must expose the selected header');
    }
    try {
        $pages->save(array_replace($edit, ['header_image_url'=>'']));
        throw new RuntimeException('A stale edit cannot clear a newer attachment');
    } catch (DomainException) {}
    try {
        $media->delete($ids[1]);
        throw new RuntimeException('An authored-page reference must block binary deletion');
    } catch (InvalidArgumentException $error) {
        if (!str_contains($error->getMessage(), 'referenced')) throw $error;
    }
    if (!is_file($files[1])) throw new RuntimeException('The referenced header file was removed');
    $pages->save(array_replace($edit, ['expected_revision'=>$pages->find($draft['slug'])['revision'], 'header_image_url'=>'']));
    if (array_key_exists('header_image_url', $pages->findPublic($draft['slug']))) {
        throw new RuntimeException('Clearing the choice must remove the saved header field');
    }

    if($media->delete($ids[1])!==true || is_file($files[1])) {
        throw new RuntimeException('Unused disposable uploaded image was not deleted');
    }
    $after=json_decode((string)file_get_contents($stateFile),true,flags:JSON_THROW_ON_ERROR);
    $remaining=array_column($after['collections']['media_files'],'id');
    if(in_array($ids[1],$remaining,true) || !in_array($ids[0],$remaining,true)
        || !in_array($ids[2],$remaining,true)) {
        throw new RuntimeException('Fixture metadata after guarded deletion is incorrect');
    }
    $after['reject_read_collections'] = ['products'];
    file_put_contents($stateFile,json_encode($after,JSON_THROW_ON_ERROR));
    DatabaseService::clearReadCache();
    try {
        $media->delete($ids[0]);
        throw new LogicException('Unavailable canonical records must prevent deletion');
    } catch (RuntimeException $error) {
        if (!str_contains($error->getMessage(), 'fixture read unavailable')) throw $error;
        if (!is_file($files[0])) throw new RuntimeException('Unavailable records lost their binary');
    }
    echo "Isolated media deletion: current or unverifiable usage blocked; authored header save and clear verified; stale usage reconciled; failed DB mutation rolled back.\n";
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
