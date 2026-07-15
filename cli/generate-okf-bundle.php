<?php
require_once __DIR__ . '/../app/bootstrap.php';

use App\Services\KnowledgeGraphService;

$root = $argv[1] ?? dirname(__DIR__);
$service = new KnowledgeGraphService($root);
$graph = $service->build();

$okfDir = $root . '/.okf';
$service->writeOkfBundle($root);

$mmdFile = $root . '/docs/knowledge-graph.mmd';
file_put_contents($mmdFile, $service->renderMermaid());

echo json_encode([
    'concepts' => count($graph['concepts']),
    'edges' => count($graph['edges']),
    'okf_dir' => $okfDir,
    'mmd_file' => $mmdFile,
], JSON_PRETTY_PRINT) . "\n";
