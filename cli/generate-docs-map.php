<?php
require_once __DIR__ . '/../app/bootstrap.php';

use App\Services\DocsMapService;

$root = $argv[1] ?? dirname(__DIR__);
$output = $argv[2] ?? $root . '/docs/map.mmd';

$service = new DocsMapService($root);
$mermaid = $service->generate();

file_put_contents($output, $mermaid);
echo "Generated: $output\n";
