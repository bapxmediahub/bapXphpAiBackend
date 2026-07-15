<?php
require_once __DIR__ . '/../app/bootstrap.php';

use App\Services\DocsMapService;

$root = $argv[1] ?? dirname(__DIR__);
$path = $root . '/docs/map.mmd';
$expected = (new DocsMapService($root))->generate();

if (!is_file($path) || trim((string)file_get_contents($path)) !== trim($expected)) {
    fwrite(STDERR, "Generated documentation map is stale. Run bapXphp update and commit docs/map.mmd.\n");
    exit(1);
}

echo "Documentation map valid\n";
