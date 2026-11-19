<?php
declare(strict_types=1);

use App\Services\{DatabaseService, MediaService, ResourceService};

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
$invalid = ResourceService::listOptions(['page' => [], 'q' => [], 'per_page' => '-1', 'sort' => []]);
check($invalid === ['search' => '', 'pageSize' => 25, 'page' => 1, 'sort' => 'newest'], 'Invalid input falls back to defaults');
echo "PASS 501-row order, appointment and media lists, search, sort and page bounds.\n";
