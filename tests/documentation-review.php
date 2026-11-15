<?php
declare(strict_types=1);
$implementation=dirname(__DIR__).'/cli/lib/DocumentationReview.php';
if(!is_file($implementation)){fwrite(STDERR,"FAIL: source-to-documentation review checker is not implemented\n");exit(1);}
require $implementation;
$root=sys_get_temp_dir().'/sps-doc-review-'.bin2hex(random_bytes(6));mkdir($root.'/app',0700,true);mkdir($root.'/docs',0700,true);
$rules=['feature'=>['sources'=>['app/*'],'references'=>['docs/feature.md']]];
file_put_contents($root.'/app/Feature.php','<?php function example(){return 1;}');file_put_contents($root.'/docs/feature.md','# Actual feature');
$count=0;
$assert=static function(bool $value,string $message)use(&$count):void{if(!$value)throw new RuntimeException($message);$count++;};
try{
 $review=new DocumentationReview($root,$rules);
 $assert($review->check()!==[],'Missing receipt must fail');
 $review->record('feature','Compared the actual feature function with its documented behavior.');
 $assert($review->check()===[],'Exact reviewed source/doc pair passes');
 file_put_contents($root.'/app/Feature.php','<?php function example(){return 2;}');
 $assert(str_contains(implode('\n',$review->check()),'feature'),'Changed code invalidates reviewed fingerprint');
 $review->record('feature','Reviewed return-value change; updated guide is not needed for this internal fixture.');
 $assert($review->check()===[],'Reviewed non-user-visible change can be acknowledged with rationale');
 file_put_contents($root.'/docs/feature.md','# Changed guide');$assert($review->check()!==[],'Changed prose also requires cross-review');
 $review->record('feature','Compared updated feature guide and the current implementation.');
 mkdir($root.'/docs/issues');file_put_contents($root.'/docs/issues/42.md','# Tracking backlog');
 $assert(str_contains(implode('\n',$review->check()),'GitHub'),'Issue documents cannot become a parallel backlog');
 unlink($root.'/docs/issues/42.md');rmdir($root.'/docs/issues');
 file_put_contents($root.'/docs/issue-42-status.md','# Tracker');
 $assert($review->check()!==[],'Issue-named ledger is rejected outside docs/issues too');unlink($root.'/docs/issue-42-status.md');
 file_put_contents($root.'/docs/research.md','# Historical source comparison');$assert($review->check()===[],'Durable research evidence is not an issue tracker');
 unlink($root.'/docs/feature.md');$assert($review->check()!==[],'Missing required document fails');
 $denied=false;try{$review->record('feature','Reviewed missing document should not pass.');}catch(RuntimeException){$denied=true;}$assert($denied,'Cannot certify nonexistent reference');
 file_put_contents($root.'/docs/feature.md','# Actual feature');
 $denied=false;try{$review->record('feature','ok');}catch(InvalidArgumentException){$denied=true;}$assert($denied,'Trivial review note is refused');
 $denied=false;try{$review->record('../escape','A sufficiently detailed but invalid area must be denied.');}catch(InvalidArgumentException){$denied=true;}$assert($denied,'Area is enum, not path');
 $outside=$root.'-external.md';file_put_contents($outside,'external');unlink($root.'/docs/feature.md');symlink($outside,$root.'/docs/feature.md');
 $assert($review->check()!==[],'Linked documentation outside root must fail');unlink($outside);
 echo "Documentation review: $count assertions passed.\n";
}finally{
 $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
 foreach($files as $f){if($f->isDir()&&!$f->isLink())rmdir($f->getPathname());else unlink($f->getPathname());}rmdir($root);
}
