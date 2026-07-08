<?php
require __DIR__ . '/../app/bootstrap.php';

use App\Services\GitHubDocService;

$root = $argv[1] ?? dirname(__DIR__);
$cacheDir = $root . '/storage/cache';

$service = new GitHubDocService($cacheDir, 0);
$count = $service->refreshCache();

echo "Refreshed $count cached items from GitHub.\n";
