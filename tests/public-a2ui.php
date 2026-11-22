<?php
declare(strict_types=1);
use App\Services\{PublicA2uiService,PublicKnowledgeService,BlogService};
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
putenv('BAPX_TEST_MODE=1');
require dirname(__DIR__).'/app/bootstrap.php';
function checkA2ui(bool $condition,string $reason): void {
    if (!$condition) throw new RuntimeException($reason);
}
$root=sys_get_temp_dir().'/a2ui-public-'.bin2hex(random_bytes(7));
mkdir($root,0700,true);
$fixture=function(string $slug,string $title,bool $published,string $date) use($root): void {
    file_put_contents($root.'/'.$slug.'.md',"---\n".
        "title: $title\npublished: ".($published?'true':'false')."\n".
        "published_at: $date\ncategory: help\nexcerpt: A public ritual guide\n".
        "---\nPublished guide text.\n");
};
try {
    $fixture('open-guide','Ritual Open Guide',true,'2026-01-01');
    $fixture('hidden-guide','Secret Ritual Guide',false,'2026-01-01');
    $fixture('future-guide','Future Ritual Guide',true,'2999-01-01');
    file_put_contents($root.'/injected.md',"---\ntitle: <script>alert(1)</script>\npublished: true\npublished_at: 2026-01-01\n---\nText.");
    $knowledge=new PublicKnowledgeService(
        blog:new BlogService($root,new DateTimeImmutable('2026-11-22 00:00:00',new DateTimeZone('Asia/Kolkata')))
    );
    $service=new PublicA2uiService($knowledge);
    $response=$service->present('articles','ritual',4);
    $stream=$response['messages']??[];
    checkA2ui(count($stream)===1,'Expected one A2UI message');
    checkA2ui($stream[0]['version']==='1.0','Not A2UI 1.0');
    $surface=$stream[0]['createSurface']??[];
    checkA2ui($surface['catalogId']===PublicA2uiService::CATALOG,'Catalog mismatch');
    checkA2ui(preg_match('/^bapx_articles_[a-f0-9]{16}$/D',$surface['surfaceId'])===1,'Unsafe surface ID');
    $items=$surface['dataModel']['items']??[];
    checkA2ui(count($items)===1,'Unpublished or future content must not render');
    checkA2ui($items[0]['path']==='/blog/open-guide','Wrong published link');
    checkA2ui(!str_contains(json_encode($response),'Secret Ritual Guide'),'Private title leaked');
    checkA2ui(!str_contains(json_encode($response),'Future Ritual Guide'),'Future title leaked');
    checkA2ui(!str_contains(json_encode($response),'<script>'),'Dangerous unrelated content included');
    $components=$surface['components']??[];
    checkA2ui($components[0]['id']==='root' && $components[0]['component']==='Column','Root missing');
    checkA2ui(count($components)===8,'Unexpected number of rendered components');
    $ids=array_column($components,'id');
    checkA2ui(count($ids)===count(array_unique($ids)),'Duplicate component ids');
    foreach($components as $component){
        checkA2ui(in_array($component['component'],['Card','Text','Button','Column'],true),'Unapproved component');
        if($component['component']==='Button'){
            checkA2ui(($component['action']['functionCall']['@call']??'')==='openUrl','Unexpected function');
        }
    }
    $product=$service->present('products','',2);
    checkA2ui($product['messages'][0]['createSurface']['surfaceId']!==$surface['surfaceId'],'Surface IDs reused');
    checkA2ui($service->forQuestion('Where are the ritual guides?')!==null,'Guide intent not detected');
    checkA2ui($service->forQuestion('What is my private order number?')===null,'Private order request added public UI');
    foreach([['private','',2],['products','',0],['products',str_repeat('x',121),1],['articles','',5]] as $bad) {
        try { $service->present(...$bad); throw new RuntimeException('Invalid UI request accepted'); }
        catch(InvalidArgumentException) {}
    }
    checkA2ui(PublicA2uiService::inputSchema()['required']===['view'],'Tool input declaration incomplete');
    checkA2ui(PublicA2uiService::outputSchema()['required']===['messages'],'Tool output declaration incomplete');
    echo "A2UI 1.0 Basic subset, safe published Markdown, bounded components/actions and stable schemas PASS.\n";
} finally {
    foreach(glob($root.'/*')?:[] as $file) @unlink($file);
    @rmdir($root);
}
