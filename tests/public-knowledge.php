<?php
declare(strict_types=1);

use App\Services\{BlogService, PublicKnowledgeService};

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
putenv('BAPX_TEST_MODE=1');
require dirname(__DIR__) . '/app/bootstrap.php';

$root = sys_get_temp_dir() . '/bapx-public-knowledge-' . bin2hex(random_bytes(6));
mkdir($root, 0700);
$write = static function (string $slug,string $title,bool $published,string $date,string $category='help') use ($root): void {
    file_put_contents($root.'/'.$slug.'.md',
        "---\ntitle: $title\npublished: ".($published?'true':'false')."\npublished_at: $date\ncategory: $category\nexcerpt: Safe ritual guide\n---\n\nPublic guidance.\n");
};
try {
    $write('published-guide','Public Ritual Guide',true,'2026-01-01');
    $write('second-guide','Second Ritual Guide',true,'2026-01-02');
    $write('hidden-guide','Hidden Ritual Guide',false,'2026-01-01');
    $write('future-guide','Future Ritual Guide',true,'2999-01-01');
    $write('retired-guide','Retired Ritual Guide',true,'2026-01-01','astrology');
    $private=$root.'/private-instructions.txt';
    file_put_contents($private,'PRIVATE');
    if (function_exists('symlink')) @symlink($private,$root.'/linked-guide.md');

    $service = new PublicKnowledgeService(
        blog: new BlogService($root, new DateTimeImmutable('2026-11-22 00:00:00',new DateTimeZone('Asia/Kolkata')))
    );
    $page=$service->articles('ritual',0,1);
    if($page['total']!==2 || count($page['articles'])!==1 || $page['nextOffset']!==1){
        throw new RuntimeException('Public page one pagination/visibility failed.');
    }
    $next=$service->articles('ritual',1,5);
    if($next['total']!==2 || count($next['articles'])!==1 || $next['nextOffset']!==null){
        throw new RuntimeException('Public article pagination failed.');
    }
    $joined=array_merge($page['articles'],$next['articles']);
    $paths=array_column($joined,'path');
    sort($paths);
    if($paths!==['/blog/published-guide','/blog/second-guide']){
        throw new RuntimeException('Unpublished, future, retired or linked Markdown leaked.');
    }
    if(($service->articlesForAgent('ritual')['found']??0)!==2){
        throw new RuntimeException('Enterprise/Support article selection diverged.');
    }
    if($service->articles('no-article-matches-this')[ 'total']!==0){
        throw new RuntimeException('Unknown term matched public article.');
    }
    $invalid=false;
    try{$service->articles('valid',0,21);}catch(InvalidArgumentException){$invalid=true;}
    if(!$invalid)throw new RuntimeException('Invalid page size was accepted.');
    $schema=PublicKnowledgeService::articleOutputSchema();
    if(($schema['required']??[])!==['articles','total','nextOffset']){
        throw new RuntimeException('Public article schema lacks required fields.');
    }
    echo "Published public knowledge fixtures, isolation and pagination passed.\n";
} finally {
    foreach(glob($root.'/*')?:[] as $path)unlink($path);
    rmdir($root);
}
