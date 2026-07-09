<?php
/**
 * Project map validator.
 *
 * The single project-map artifact is docs/systematic-map.mmd. This validator
 * regenerates the Mermaid in memory and compares it byte-for-byte to the
 * on-disk file. If they differ, regenerate with:
 *
 *   php cli/generate-project-map.php
 */
require __DIR__ . '/../app/bootstrap.php';

$path = app_path('docs/systematic-map.mmd');
$expected = App\Services\ProjectMapService::renderSystematicMermaid();

if (!is_file($path) || trim((string)file_get_contents($path)) !== trim($expected)) {
    fwrite(STDERR, "Generated project map is stale. Run php cli/generate-project-map.php and commit docs/systematic-map.mmd.\n");
    exit(1);
}

echo "Project map valid\n";
