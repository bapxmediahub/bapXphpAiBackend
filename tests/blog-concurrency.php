<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{BlogService,BlogConflictException};
$mode=$argv[1]??'';
if($mode==='worker'){
    $dir=$argv[2]??'';$expected=$argv[3]??'';
    if(!str_starts_with($dir,sys_get_temp_dir().'/sps-blog-concurrent-'))throw new RuntimeException('Fixture directory required');
    $service=new BlogService($dir);
    try{$service->save(['slug'=>'fixture','title'=>'Fixture','content'=>'worker-'.$argv[4],'published'=>false,'expected_revision'=>$expected]);echo "saved\n";}
    catch(BlogConflictException){echo "conflict\n";}
    exit;
}
$dir=sys_get_temp_dir().'/sps-blog-concurrent-'.bin2hex(random_bytes(6));mkdir($dir,0700);
try{
    $service=new BlogService($dir);$post=$service->save(['slug'=>'fixture','title'=>'Fixture','content'=>'Original','published'=>false]);
    $jobs=[];
    for($i=0;$i<6;$i++){
        $process=proc_open([PHP_BINARY,__FILE__,'worker',$dir,$post['_revision'],(string)$i],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process))throw new RuntimeException('Worker unavailable');fclose($pipes[0]);$jobs[]=[$process,$pipes];
    }
    $counts=['saved'=>0,'conflict'=>0];
    foreach($jobs as [$process,$pipes]){$result=trim(stream_get_contents($pipes[1]));$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0||!isset($counts[$result]))throw new RuntimeException('Fixture worker failed');$counts[$result]++;}
    if($counts!==['saved'=>1,'conflict'=>5])throw new RuntimeException('Expected exactly one accepted editor');
    $final=$service->find('fixture');if(!$final||!str_starts_with($final['content'],'worker-')||$final['_revision']===$post['_revision'])throw new RuntimeException('Saved result missing');
    $history=$service->revisionHistory('fixture');
    if(count($history['versions'])!==1 || !str_ends_with($history['versions'][0]['id'],$post['_revision'])) throw new RuntimeException('Stale writers polluted revision history');
    $service->delete('fixture',$final['_revision']);
    echo "Blog concurrency passed: one accepted writer, five stale conflicts, complete Markdown read-back.\n";
}finally{foreach(glob($dir.'/*')?:[] as $file)unlink($file);@unlink($dir.'/.blog-write.lock');rmdir($dir);}
