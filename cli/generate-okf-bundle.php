<?php
require_once __DIR__ . '/../app/bootstrap.php';

use App\Services\KnowledgeGraphService;

$root = $argv[1] ?? dirname(__DIR__);
$service = new KnowledgeGraphService($root);
$graph = $service->build();

$indexFile = $argv[2] ?? $root . '/index.yaml';
$service->writeYamlIndex($indexFile);

echo json_encode([
    'concepts' => count($graph['concepts']),
    'edges' => count($graph['edges']),
    'index_file' => $indexFile,
], JSON_PRETTY_PRINT) . "\n";
