<?php
declare(strict_types=1);
require __DIR__.'/lib/DocumentationReview.php';
$root=dirname(__DIR__);$review=new DocumentationReview($root,require $root.'/config/documentation.php');
$args=array_slice($argv,1);
try{
 if($args===[]||$args===['--check']){
  $errors=$review->check();foreach($errors as $error)fwrite(STDERR,$error."\n");
  if($errors!==[])exit(1);
  echo "Source/document review fingerprints and GitHub-only issue boundary passed.\n";
 }elseif($args===['--list']){
  foreach($review->snapshots() as $area=>$snapshot)echo $area.' | '.$snapshot['source_count'].' source files | '.implode(', ',$snapshot['references'])."\n";
 }elseif(count($args)===4&&$args[0]==='--record'&&$args[2]==='--reason'){
  $review->record($args[1],$args[3]);echo 'Recorded source/document review: '.$args[1]."\n";
 }else{fwrite(STDERR,"Usage: php cli/docs-check.php [--check|--list|--record AREA --reason 'What was cross-checked']\n");exit(2);}
}catch(Throwable $e){fwrite(STDERR,'Documentation review: '.$e->getMessage()."\n");exit(1);}
