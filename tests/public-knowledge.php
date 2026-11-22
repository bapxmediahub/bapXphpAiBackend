<?php
declare(strict_types=1);

use App\Services\{AgentCapabilityPolicy, AgentTraceService, BlogService, EnterpriseToolRegistry, PublicKnowledgeService, SettingsService, SupportBotService, SupportToolRegistry};

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

    $contact = $service->contact();
    if (array_keys($contact) !== ['name','path','phones','email','hours','servicePath']
        || $contact['email'] !== 'support@sripanchamispiritual.com'
        || array_column($contact['phones'], 'number') !== ['+919789444037','+919789444038']) {
        throw new RuntimeException('Published contact projection changed or exported extra fields.');
    }
    $services = $service->services('', 0, 2);
    $next = $service->services('', 2, 2);
    if ($services['total'] !== 4 || $services['nextOffset'] !== 2 || $next['nextOffset'] !== null
        || array_column(array_merge($services['services'], $next['services']), 'name') !== array_values(PublicKnowledgeService::contactSubjects())
        || $service->services('temple guidance')['services'][0]['path'] !== '/contact?subject=temple#contact-form'
        || $service->services('astrology booking')['total'] !== 0) {
        throw new RuntimeException('Enquiry services, paging, filtering or retired-service exclusion failed.');
    }
    foreach ([['', -1, 2], ['', 0, 21], [str_repeat('x', 121), 0, 2], ["\xff", 0, 2]] as $args) {
        $rejected = false;
        try { $service->services(...$args); } catch (InvalidArgumentException) { $rejected = true; }
        if (!$rejected) throw new RuntimeException('Invalid public services argument accepted.');
    }

    // Disabled modules and private settings cannot affect the independent public contact projection.
    $settings = new ReflectionProperty(SettingsService::class, 'cache');
    $previousSettings = $settings->getValue();
    try {
        $settings->setValue(null, ['module_shop'=>'0', 'module_blog'=>'0',
            'admin_email'=>'private@example.invalid', 'admin_password'=>'PRIVATE_TEST_VALUE']);
        if ($service->products()['total'] !== 0 || $service->articles()['total'] !== 0
            || $service->contact() !== $contact || $service->services()['total'] !== 4
            || str_contains(json_encode([$service->contact(), $service->services()]), 'PRIVATE_TEST_VALUE')) {
            throw new RuntimeException('Module or private-settings isolation failed.');
        }
    } finally { $settings->setValue(null, $previousSettings); }

    $tools = new SupportToolRegistry(null);
    if ($tools->run('get_public_contact', []) !== $contact
        || !isset($tools->run('get_public_contact', ['role'=>'admin'])['error'])
        || !isset($tools->run('list_public_services', ['limit'=>21])['error'])
        || !isset($tools->run('list_public_services', ['offset'=>'0'])['error'])
        || !isset($tools->run('list_public_services', ['query'=>[]])['error'])
        || !isset($tools->run('list_public_services', ['query'=>null])['error'])
        || !isset($tools->run('list_public_services', ['limit'=>null])['error'])
        || AgentCapabilityPolicy::allows('support', 'find_order', ['role'=>'admin'])) {
        throw new RuntimeException('Public Support tools accepted unsafe arguments or widened permissions.');
    }
    $previousSession = $_SESSION;
    try {
        $_SESSION['user'] = ['role'=>'customer'];
        if (!isset((new EnterpriseToolRegistry())->run('get_public_contact', [])['error'])) {
            throw new RuntimeException('Enterprise actor guard was bypassed.');
        }
        $_SESSION['user'] = ['role'=>'admin'];
        $enterprise = new EnterpriseToolRegistry();
        if (array_column($enterprise->declarations(), 'name') !== AgentCapabilityPolicy::tools('enterprise')
            || array_column($tools->declarations(), 'name') !== AgentCapabilityPolicy::tools('support')
            || $enterprise->run('get_public_contact', []) !== $contact
            || $enterprise->run('list_public_services', ['query'=>'temple']) !== $service->services('temple')) {
            throw new RuntimeException('Enterprise public knowledge diverged from Support.');
        }
    } finally { $_SESSION = $previousSession; }

    $evidence = AgentTraceService::toolEvidence('get_public_contact', [], $contact);
    $safe = AgentTraceService::safeTool(['name'=>'get_public_contact', 'status'=>'complete', 'evidence'=>$evidence]);
    $serviceEvidence = AgentTraceService::toolEvidence('list_public_services', ['query'=>'private@example.invalid'], $service->services());
    $serialized = json_encode([$safe, $serviceEvidence]);
    if ($safe['evidence']['source'] !== 'public_contact' || $safe['evidence']['result_count'] !== 1
        || $serviceEvidence['source'] !== 'public_services' || $serviceEvidence['result_count'] !== 4
        || ($serviceEvidence['filters']['search_query_provided'] ?? false) !== true
        || str_contains($serialized, 'support@') || str_contains($serialized, '97894')
        || str_contains($serialized, 'private@example.invalid')) {
        throw new RuntimeException('Public tool evidence was dropped or retained raw contact/search data.');
    }
    foreach (['What is my email address?', 'Change my phone number', 'What service did I order?'] as $privateQuestion) {
        if (SupportBotService::publicBusinessLookup($privateQuestion) !== null) {
            throw new RuntimeException('Personal question was mistaken for a public contact lookup.');
        }
    }
    foreach (['How can I contact you?'=>'get_public_contact', 'What services do you offer?'=>'list_public_services'] as $question=>$tool) {
        $answer = (new SupportBotService())->answer($question, null);
        if (($answer['run']['outcome'] ?? '') !== 'local' || ($answer['run']['tools'][0]['name'] ?? '') !== $tool
            || $answer['ticket_id'] !== null || $answer['run']['attempts'] !== []) {
            throw new RuntimeException('Public business question did not use its grounded read-only tool.');
        }
    }
    $productPath = new ReflectionMethod(PublicKnowledgeService::class, 'productPath');
    foreach (['legacy item.v2'=>'/product/legacy%20item.v2', '珊瑚 pendant'=>'/product/%E7%8F%8A%E7%91%9A%20pendant',
        '_legacy-2'=>'/product/_legacy-2'] as $input=>$expected) {
        if ($productPath->invoke(null, $input) !== $expected) throw new RuntimeException('Safe legacy product slug was lost or encoded twice.');
    }
    foreach (['', '.', '..', '../admin', 'item/private', 'item\\private', '%2e%2e', '%252e%252e',
        'item%20name', "item\0name", "item\nname", "\xff"] as $unsafeSlug) {
        if ($productPath->invoke(null, $unsafeSlug) !== null) throw new RuntimeException('Unsafe product navigation slug was exposed.');
    }
    $imagePath = new ReflectionMethod(PublicKnowledgeService::class, 'imagePath');
    $previousUrl = $_SERVER['APP_URL'] ?? null;
    $_SERVER['APP_URL'] = 'https://public.example';
    try {
        foreach (['https://public.example/assets/images/media/item.webp'=>'/assets/images/media/item.webp',
            '/assets/images/media/item%20photo.jpg'=>'/assets/images/media/item%20photo.jpg'] as $input=>$expected) {
            if ($imagePath->invoke(null, $input) !== $expected) throw new RuntimeException('Safe local product image was lost.');
        }
        foreach (['/assets/images/../private.png', '/assets/images/%2e%2e/private.png',
            '/assets/images/%252e%252e/private.png', '/assets/images/media/a%2fb.webp',
            'https://public.example:444/assets/images/item.webp',
            'https://public.example@untrusted.invalid/assets/images/item.webp',
            '//public.example/assets/images/item.webp', '/assets/images/item.svg',
            '/assets/images/item.webp?redirect=private', 'data:image/png;base64,AAAA'] as $unsafeImage) {
            if ($imagePath->invoke(null, $unsafeImage) !== null) throw new RuntimeException('Unsafe product image was exposed.');
        }
    } finally {
        if ($previousUrl === null) unset($_SERVER['APP_URL']); else $_SERVER['APP_URL'] = $previousUrl;
    }
    $plainText = new ReflectionMethod(PublicKnowledgeService::class, 'plainText');
    if ($plainText->invoke(null, '<p>Public <strong>description</strong>.</p><script>hiddenCode()</script>', 280) !== 'Public description.'
        || mb_strlen($plainText->invoke(null, str_repeat('அ', 400), 280)) !== 280
        || $plainText->invoke(null, '<b>' . str_repeat('𐐀', 161) . '</b>', 160) !== str_repeat('𐐀', 160)) {
        throw new RuntimeException('Product description was not plain text or bounded Unicode.');
    }
    $renderedContact = (static function () use ($contact): string {
        $publicContact = $contact;
        $subjects = PublicKnowledgeService::contactSubjects();
        $success = false; $errors = [];
        $submission = array_fill_keys(['name','email','phone','subject','message'], '');
        ob_start(); require app_path('views/public/contact.php'); return ob_get_clean();
    })();
    if (!str_contains($renderedContact, 'tel:+919789444037')
        || !str_contains($renderedContact, 'mailto:' . $contact['email'])
        || !str_contains($renderedContact, 'Mon–Sat, 9 AM–7 PM · Sun, 10 AM–5 PM')) {
        throw new RuntimeException('Visible contact page no longer matches the public projection.');
    }
    echo "Published public knowledge, contact/services, module isolation, actor guards and sanitized tool evidence passed.\n";
} finally {
    foreach(glob($root.'/*')?:[] as $path)unlink($path);
    rmdir($root);
}
