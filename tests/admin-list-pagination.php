<?php
declare(strict_types=1);

use App\Services\{DatabaseService, MailQueueService, MailStorageService, MediaService, ResourceService};

require dirname(__DIR__).'/app/bootstrap.php';

// MySQL temporary tables shadow the normal collections for this connection only.
$config = require app_path('config/database.php');
if (!in_array($config['host'], ['127.0.0.1','localhost','::1'], true) || getenv('BAPX_TEST_MODE') === '1') {
    throw new RuntimeException('Use a loopback MySQL test database with temporary table privileges.');
}
$store = new DatabaseService(true);
$pdo = $store->connection();
$needle = 'Find_100%';
foreach (['orders','appointments','media_files'] as $table) {
    $pdo->exec("CREATE TEMPORARY TABLE $table (id VARCHAR(36) PRIMARY KEY, _data JSON NOT NULL, _created_at DATETIME)");
    $insert = $pdo->prepare("INSERT INTO $table VALUES (?, ?, ?)");
    $pdo->beginTransaction();
    for ($i = 0; $i < 501; $i++) {
        $id = sprintf('fixture-%03d', $i);
        $record = ['id' => $id, 'customer_email' => $i === 1 ? $needle : 'Fixture customer',
            'status' => 'active', 'created_at' => '2025-01-01', 'context' => 'products',
            'filename' => $i === 1 ? $needle.'.png' : $id.'.png', 'url' => '/fixture-media/'.$id.'.png'];
        if ($i === 1 && $table === 'appointments') {
            $record += ['preferred_date' => '2026-08-09', 'preferred_time' => '14:55', 'mode' => 'text_session'];
        }
        if ($table === 'orders') {
            $record['status'] = $i === 1 ? 'on_hold' : 'processing';
            $record['total'] = $i === 1 ? 91.25 : 12;
            $record['tax_review_required'] = $i === 1;
            $record['fulfillment_review_required'] = $i === 2;
        }
        $insert->execute([$id, json_encode($record, JSON_THROW_ON_ERROR), gmdate('Y-m-d H:i:s', 1700000000 + $i)]);
    }
    if ($table !== 'media_files') {
        $insert->execute(['fixture-empty', '{"id":"fixture-empty","status":"","name":null}', '2030-01-01']);
    }
    $pdo->commit();
}

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

foreach (['orders','appointments'] as $table) {
    $records = new ResourceService($table, $store);
    $first = $records->adminPage(['per_page' => '10']);
    check(count($first['items']) === 10 && $first['paging']['count'] === 501, 'First page and total');
    check($first['items'][0]['id'] === 'fixture-500', 'Newest first');
    $last = $records->adminPage(['page' => '9999','per_page' => '10']);
    check(count($last['items']) === 1 && $last['paging']['page'] === 51 && $last['items'][0]['id'] === 'fixture-000', 'Clamp last page');
    $match = $records->adminPage(['q' => mb_strtolower($needle), 'page' => '40']);
    check(count($match['items']) === 1 && $match['items'][0]['id'] === 'fixture-001' && $match['paging']['page'] === 1, 'Full collection literal search');
    $displayed = $table === 'appointments'
        ? ['2026-08-09' => 'fixture-001', '14:55' => 'fixture-001', '2026-08-09 14:55' => 'fixture-001', 'text session' => 'fixture-001']
        : ['on hold' => 'fixture-001', '₹91.25' => 'fixture-001', 'tax review required' => 'fixture-001',
            'fulfillment review required' => 'fixture-002'];
    foreach ($displayed as $text => $expectedId) {
        $matches = $records->adminPage(['q' => $text]);
        check(array_column($matches['items'], 'id') === [$expectedId], "Displayed $table text is searchable: $text");
    }
    check($records->adminPage(['sort' => 'oldest'])['items'][0]['id'] === 'fixture-000', 'Oldest first');
}

$media = new MediaService(store: $store);
$page = $media->adminPage(['q' => '/fixture-media/', 'page' => '51', 'per_page' => '10']);
check($page['paging']['count'] === 501 && count($page['items']) === 1, 'Uploads from all MySQL batches remain available');
$match = $media->adminPage(['q' => $needle, 'context' => 'products']);
check(count($match['items']) === 1 && $match['items'][0]['id'] === 'fixture-001', 'Media search includes older uploads');
check($media->adminPage(['q' => $needle, 'context' => 'blog'])['items'] === [], 'Media context and search combine');
// Context ownership follows the full library's merge, including bundles whose
// upload metadata assigns them to another context and paths claimed by two uploads.
$bundle = '/assets/images/products/yamthiram-dollar-front.webp';
check(is_file(app_path(ltrim($bundle, '/'))), 'Bundled product collision fixture exists');
$claims = [
    ['id' => 'bundle-in-blog', 'context' => 'blog', 'url' => $bundle],
    ['id' => 'shared-claim', 'context' => 'shared', 'url' => '/fixture-media/fixture-001.png'],
    ['id' => 'blog-claim', 'context' => 'blog', 'path' => '/fixture-media/fixture-002.png'],
];
$insert = $pdo->prepare('INSERT INTO media_files VALUES (?, ?, NOW())');
foreach ($claims as $claim) $insert->execute([$claim['id'], json_encode($claim, JSON_THROW_ON_ERROR)]);
$global = $media->all();
foreach (['shared','products','temples','blog'] as $ctx) {
    $expected = array_column(array_filter($global, static fn(array $item): bool => ($item['context'] ?? 'shared') === $ctx), 'id');
    $actual = [];
    for ($pageNum = 1; ; $pageNum++) {
        $scoped = $media->adminPage(['context' => $ctx, 'per_page' => '200', 'page' => $pageNum]);
        $actual = array_merge($actual, array_column($scoped['items'], 'id'));
        if ($pageNum >= $scoped['paging']['pages']) break;
    }
    sort($expected);
    sort($actual);
    check($actual === $expected, "Filtered $ctx preserves authoritative records and bundled URL ownership");
}

// Native metadata edits use a locked existing row, not a replacement catalogue.
$pdo->exec('ALTER TABLE media_files ADD _updated_at DATETIME NULL');
$upload = ['id'=>'0123456789abcdef','url'=>'/assets/images/media/2099/01/metadata-fixture.webp',
    'context'=>'shared','description'=>'Before','used_in'=>[['id'=>'keep']],'original_url'=>'/keep-original.webp'];
$pdo->prepare('INSERT INTO media_files (id, _data, _created_at) VALUES (?, ?, NOW())')->execute([$upload['id'], json_encode($upload, JSON_THROW_ON_ERROR)]);
$revision = MediaService::metadataRevision($upload);
$fields = ['description'=>'After','alt_text'=>'Searchable accessible fixture','caption'=>'A caption','url'=>'/forged'];
$media->saveMetadata($upload['id'], $fields, $revision);
$stored = $store->find('media_files', $upload['id']);
check($stored['description'] === 'After' && $stored['alt_text'] === $fields['alt_text'] && $stored['caption'] === 'A caption', 'Metadata persisted');
check($stored['url'] === $upload['url'] && $stored['original_url'] === $upload['original_url'] && $stored['used_in'] === $upload['used_in'], 'Other fields preserved');
check(array_column($media->adminPage(['q'=>$fields['alt_text']])['items'],'id') === [$upload['id']], 'New metadata searchable');
try { $media->saveMetadata($upload['id'], $fields, $revision); throw new RuntimeException('Stale write accepted'); }
catch (InvalidArgumentException $e) { check(str_contains($e->getMessage(),'changed'),'Stale revision rejected'); }
try { $media->saveMetadata('abcdef0123456789', $fields, $revision); throw new RuntimeException('Missing row recreated'); }
catch (InvalidArgumentException $e) { check(str_contains($e->getMessage(),'not found'),'Missing row rejected'); }
$media->saveMetadata($upload['id'], ['description'=>'','alt_text'=>'','caption'=>''], MediaService::metadataRevision($stored));
check($store->find('media_files',$upload['id'])['caption'] === '', 'Explicit clearing persists');
echo "PASS media metadata persistence, stale edits, search and field isolation.\n";

$invalid = ResourceService::listOptions(['page' => [], 'q' => [], 'per_page' => '-1', 'sort' => []]);
check($invalid === ['search' => '', 'pageSize' => 25, 'page' => 1, 'sort' => 'newest'], 'Invalid input falls back to defaults');
echo "PASS 501-row order, appointment and media lists, search, sort and page bounds.\n";

// The Outbox reads delivery evidence from the canonical queue by exact reference.
foreach (['mail_queue','mail_outbox'] as $table) {
    $pdo->exec("CREATE TEMPORARY TABLE $table (id VARCHAR(64) PRIMARY KEY, _data JSON NOT NULL)");
}
$insertQueue = $pdo->prepare('INSERT INTO mail_queue VALUES (?, ?)');
$insertOutbox = $pdo->prepare('INSERT INTO mail_outbox VALUES (?, ?)');
$now = strtotime('2026-11-25T08:00:00+05:30');
$queueRecords = [
    ['id'=>'older', 'type'=>'password_reset', 'status'=>'sent', 'delivery_state'=>'accepted_by_smtp',
        'message_id'=>'<fixture-old@example.test>', 'error_code'=>null],
    ['id'=>'held', 'type'=>'admin_notification', 'status'=>'pending', 'meta'=>['contact_kind'=>'appointment'], 'available_at'=>gmdate('c',$now-123)],
    ['id'=>'due', 'type'=>'product_review_request', 'status'=>'pending', 'available_at'=>gmdate('c',$now-200)],
    ['id'=>'due-sample', 'type'=>'scheduled_template_test', 'status'=>'pending', 'meta'=>['synthetic_sample'=>true,'template'=>'welcome'],
        'created_at'=>gmdate('c',$now-400), 'available_at'=>gmdate('c',$now-280)],
    ['id'=>'expired-sample', 'type'=>'scheduled_template_test', 'status'=>'pending', 'meta'=>['synthetic_sample'=>true,'template'=>'welcome'],
        'created_at'=>gmdate('c',$now-1900), 'available_at'=>gmdate('c',$now-1780)],
    ['id'=>'newsletter', 'type'=>'blog_newsletter', 'status'=>'pending', 'available_at'=>gmdate('c',$now+200)],
    ['id'=>'orphan', 'type'=>'legacy', 'status'=>'older_success'],
    ['id'=>'missing-state', 'type'=>'legacy'],
    ['id'=>'interrupted', 'type'=>'account', 'status'=>'sending'],
];
foreach ($queueRecords as $record) $insertQueue->execute([$record['id'],json_encode($record,JSON_THROW_ON_ERROR)]);
$older = ['id'=>'out_older', 'queue_id'=>'older', 'to_email'=>'test@example.test', 'subject'=>$needle,
    'body'=>'Unsearchable fixture body', 'status'=>'failed', 'error_code'=>'smtp_temporary', 'created_at'=>'2020-01-01T00:00:00Z'];
$insertOutbox->execute([$older['id'],json_encode($older,JSON_THROW_ON_ERROR)]);
for ($i=1; $i<=130; $i++) {
    $id = sprintf('out_fixture-%03d',$i);
    $record = ['id'=>$id, 'status'=>$i<=5?'sent':'failed', 'subject'=>'Later subject',
        'source'=>$i<=5?'blog_newsletter':'password_reset', 'created_at'=>gmdate('c',$now+$i)];
    $insertOutbox->execute([$id,json_encode($record,JSON_THROW_ON_ERROR)]);
}
$mail = new MailStorageService($store);
$page = $mail->outboxPage(['page'=>'2','per_page'=>'10','state'=>'failed'],false);
check($page['paging']['count'] === 125 && $page['paging']['page'] === 2 && count($page['items']) === 10, 'Filter canonical state before paging');
check($page['items'][0]['id'] === 'out_fixture-120', 'Page uses stable newest-first order');
foreach (['find_100%', 'test@example.test', 'older', 'fixture-old@example.test'] as $search) {
    $olderPage = $mail->outboxPage(['q'=>$search,'state'=>'sent','page'=>'50'],false);
    check($olderPage['paging']['count'] === 1 && $olderPage['paging']['page'] === 1, 'Global search finds older canonical acceptance and clamps page');
    $match = $olderPage['items'][0];
    check($match['id'] === 'out_older' && $match['delivery_state'] === 'accepted_by_smtp' && $match['error_code'] === null,
        'Canonical acceptance and explicitly cleared error replace the stale projection');
}
check($mail->outboxPage(['q'=>'Unsearchable fixture body'],false)['items'] === [], 'Search excludes message contents');
check($mail->outboxPage(['q'=>$needle,'state'=>'failed'],false)['items'] === [], 'Stale projected failure is excluded');
$news = $mail->outboxPage(['state'=>'sent','per_page'=>'200'],true);
check($news['paging']['count'] === 5 && $news['paging']['pageSize'] === 25, 'Newsletter scope and bounded page size');
check($news['items'][0]['delivery_state'] === null, 'Legacy sent does not invent acceptance');
$queue = new MailQueueService($store);
check($queue->outboxHealth(false,$now) === MailQueueService::health($queueRecords,$now),
    'Aggregate queue health preserves due, held, legacy and active/expired sample states');
check($queue->outboxHealth(true,$now) === MailQueueService::health([$queueRecords[5]],$now), 'Newsletter queue health remains scoped');
echo "PASS paged Outbox search, canonical delivery evidence and reduced queue-health reads.\n";

// A temporary settings table keeps publication and compare-and-swap fixtures
// isolated from all site settings and from parallel test connections.
$pdo->exec('CREATE TEMPORARY TABLE settings (id VARCHAR(36) PRIMARY KEY, _data JSON NOT NULL,
    _owner VARCHAR(100), _status VARCHAR(40), _created_at VARCHAR(40), _updated_at VARCHAR(40))');
$pages = new \App\Services\AuthoredPageService($store);
$initial = ['slug'=>'guide', 'title'=>'A guide', 'content'=>'Private phrase', 'status'=>'draft', 'expected_revision'=>'0'];
$pages->save($initial);
$createdRevision = $pages->find('guide')['revision'];
check($pages::validRevision($createdRevision), 'Creating the page initializes its revision');
check($pages->findPublic('guide') === null && $pages->published('Private phrase') === [], 'Draft excluded from all public reads');
try { $pages->save($initial); throw new RuntimeException('Duplicate create overwrote an existing page'); }
catch (DomainException) {}
$publication = array_replace($initial, ['content'=>'Published phrase', 'status'=>'published', 'expected_revision'=>$createdRevision]);
$pages->save($publication);
check($pages->findPublic('guide')['content'] === 'Published phrase', 'Publishing exposes current content');
check(array_column($pages->published('a phrase'), 'slug') === ['guide'], 'Public full-content word search');
try { $pages->save($publication); throw new RuntimeException('Stale editor overwrote the page'); }
catch (DomainException) {}
$pages->save(array_replace($initial, ['expected_revision'=>$pages->find('guide')['revision']]));
check($pages->findPublic('guide') === null && $pages->published() === [], 'Withholding removes the public URL and index entry');
$deletedRevision = $pages->find('guide')['revision'];
$pages->delete('guide', $deletedRevision);
check($pages->find('guide') === null, 'Delete removes the authored row');
$pages->save($initial);
try { $pages->delete('guide', $deletedRevision); throw new RuntimeException('Old deletion affected a recreated page'); }
catch (DomainException) {}
check($pages->find('guide') !== null, 'A new page at the same address has independent revision identity');
echo "PASS authored page creation, collision, publication, public exclusions, and stale revisions.\n";
