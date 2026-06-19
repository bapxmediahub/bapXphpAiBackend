<?php
/**
 * Project map generator.
 *
 * Writes the single project-map artifact:
 *   docs/systematic-map.mmd
 *
 * The artifact is a comprehensive Mermaid flowchart covering routes,
 * controllers, services, views, integrations, schema collections, storage
 * files, tools, and detected gaps.
 */
require __DIR__ . '/../app/bootstrap.php';

$path = app_path('docs/systematic-map.mmd');
$mmd = App\Services\ProjectMapService::renderSystematicMermaid();
file_put_contents($path, $mmd);

$scan = App\Services\ProjectMapService::scan();
echo json_encode($scan['summary'], JSON_PRETTY_PRINT) . "\n";
echo "Wrote docs/systematic-map.mmd (" . strlen($mmd) . " bytes)\n";
