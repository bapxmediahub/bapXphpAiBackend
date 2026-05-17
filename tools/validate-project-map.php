<?php
require __DIR__ . '/../app/bootstrap.php';
$map = App\Services\ProjectMapService::registry();
$validation = App\Services\ProjectMapService::validate($map);
foreach ($validation as $issues) if ($issues) { fwrite(STDERR, json_encode($validation, JSON_PRETTY_PRINT) . PHP_EOL); exit(1); }
$expected = json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (!is_file(app_path('docs/project-map.json')) || trim(file_get_contents(app_path('docs/project-map.json'))) !== trim($expected)) { fwrite(STDERR, "Generated project map is stale\n"); exit(1); }
echo "Project map valid\n";
