<?php
declare(strict_types=1);

use App\Router;
use App\Services\{DatabaseService, SettingsService};

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$repo = dirname(__DIR__);
$root = sys_get_temp_dir() . '/bapx-search-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
$bridge = null;
try {
    // A disposable bootstrap root lets the real route read a controlled set of Markdown posts.
    mkdir($root . '/app');
    mkdir($root . '/content/blog/posts', 0700, true);
    copy($repo . '/app/bootstrap.php', $root . '/app/bootstrap.php');
    foreach (['app/Controllers', 'app/Services', 'app/Router.php', 'app/routes.php', 'config', 'assets', 'integrations', 'views'] as $path) {
        symlink($repo . '/' . $path, $root . '/' . $path);
    }
    file_put_contents($root . '/.env', '');
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $state = $root . '/records.json';
    foreach ([
        'BAPX_MYSQL_HOST' => '127.0.0.1', 'BAPX_MYSQL_PORT' => '1', 'BAPX_MYSQL_DB' => 'fixture',
        'BAPX_MYSQL_USER' => 'fixture', 'BAPX_MYSQL_PASS' => 'sps_fixture_only_password',
        'BAPX_TEST_MODE' => '0', 'APP_URL' => 'http://store.example',
        'BAPX_REMOTE_DB_URL' => "http://$address/remotedb", 'BAPX_MEDIA_FIXTURE_STATE' => $state,
    ] as $key => $value) { putenv("$key=$value"); $_SERVER[$key] = $value; }
    $bridge = proc_open([PHP_BINARY, '-S', $address, $repo . '/tests/fixtures/media-bridge.php'],
        [0 => ['file', '/dev/null', 'r'], 1 => ['file', $root . '/bridge.log', 'a'], 2 => ['file', $root . '/bridge.log', 'a']], $pipes);
    if (!is_resource($bridge)) throw new RuntimeException('Could not start the fixture bridge.');
    for ($i = 0; $i < 100; $i++) {
        $ready = @stream_socket_client("tcp://$address", $errno, $message, 0.1);
        if ($ready) { fclose($ready); break; }
        usleep(10000);
    }

    require $root . '/app/bootstrap.php';
    $router = new Router(require $root . '/app/routes.php');
    $products = [];
    foreach (range(1, 19) as $id) {
        $products[] = ['id' => (string)$id, 'slug' => "item-$id", 'name' => "Lotus $id", 'price' => 1];
    }
    $products[] = ['id' => 'hidden', 'slug' => 'private-product', 'name' => 'Lotus draft', 'status' => 'hidden'];
    foreach ([
        'guide' => ['spiritual', 'true', '2020-01-02'],
        'shop-guide' => ['products', 'true', '2020-01-01'],
        'draft' => ['spiritual', 'false', '2020-01-01'],
        'future' => ['spiritual', 'true', '2999-01-01'],
        'retired' => ['astrology', 'true', '2020-01-01'],
    ] as $slug => [$category, $published, $date]) {
        file_put_contents("$root/content/blog/posts/$slug.md",
            "---\ntitle: Lotus $slug\ncategory: $category\npublished: $published\npublished_at: $date\n---\n\nLotus article.\n");
    }
    $settings = static function (bool $shop, bool $blog) use ($state, $products): void {
        $collections = [
            'settings' => [['id' => 'app_settings', 'module_shop' => (int)$shop, 'module_blog' => (int)$blog]],
            'products' => $products, 'temples' => [['id' => '1', 'slug' => 'temple', 'name' => 'Lotus Temple']],
        ];
        file_put_contents($state, json_encode(['collections' => $collections], JSON_THROW_ON_ERROR));
    };
    $search = static function (array $params) use ($router): array {
        DatabaseService::clearReadCache();
        SettingsService::flushCache();
        $_GET = $params;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/search?' . http_build_query($params);
        ob_start();
        try { $router->dispatch('GET', $_SERVER['REQUEST_URI']); }
        finally { $html = ob_get_clean(); }
        $doc = new DOMDocument();
        $doc->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($doc);
        $paths = [];
        foreach ($xpath->query('//main//li/a/@href') as $attribute) $paths[] = $attribute->nodeValue;
        return [$paths, $xpath];
    };
    $equal = static function (mixed $actual, mixed $expected, string $check): void {
        if ($actual !== $expected) throw new RuntimeException("Failed: $check");
    };
    $settings(true, true);
    [$first, $xpath] = $search(['q' => 'lotus']);
    $expected = array_map(static fn(int $n): string => "/product/item-$n", range(1, 19));
    $equal($first, [...$expected, '/blog/guide'], 'page one ends at the first published article');
    $equal($xpath->evaluate('string(//main//nav/a/@href)'), '/search?q=lotus&page=2', 'next preserves the search');
    [$last, $xpath] = $search(['q' => 'lotus', 'page' => 2]);
    $equal($last, ['/blog/shop-guide', '/temples/temple'], 'remaining published article and temple');
    $equal($xpath->evaluate('string(//main//nav/a/@href)'), '/search?q=lotus&page=1', 'previous preserves the search');
    $equal($search(['q' => 'lotus', 'page' => 999])[0], $last, 'page past the end');
    $equal($search(['q' => 'lotus', 'page' => []])[0], $first, 'invalid page');
    $equal($search(['q' => "\u{2003}"])[0], [], 'Unicode blank query');
    $equal($search(['q' => ['lotus']])[0], [], 'non-string query');
    $equal($search(['q' => 'none-found'])[0], [], 'nonmatching query');
    $settings(false, true);
    $equal($search(['q' => 'lotus'])[0], ['/blog/guide', '/temples/temple'], 'disabled shop also hides shop-specific articles');
    $settings(true, false);
    $equal($search(['q' => 'lotus'])[0], [...$expected, '/temples/temple'], 'disabled blog');
    $settings(false, false);
    $equal($search(['q' => 'lotus'])[0], ['/temples/temple'], 'temple available independently');
    proc_terminate($bridge);
    proc_close($bridge);
    $bridge = null;
    $failedClosed = false;
    try { $search(['q' => 'lotus']); }
    catch (RuntimeException $error) { $failedClosed = in_array(SettingsService::class, array_column($error->getTrace(), 'class'), true); }
    $equal($failedClosed, true, 'unavailable module settings');
    echo "Storefront search pagination, public visibility and module checks passed.\n";
} finally {
    if (is_resource($bridge)) { proc_terminate($bridge); proc_close($bridge); }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir() && !$file->isLink()) rmdir($file->getPathname());
        else unlink($file->getPathname());
    }
    rmdir($root);
}
