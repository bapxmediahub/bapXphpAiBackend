<?php
require __DIR__ . '/../app/bootstrap.php';
$map = App\Services\ProjectMapService::registry();
file_put_contents(app_path('docs/project-map.json'), json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
$md = "# Project Map\n\n";
foreach ($map['routes'] as $route) {
    $services = $route['services'] ? implode(', ', $route['services']) : 'none';
    $md .= "- `{$route['path']}` → `{$route['controller']}` → {$services}\n";
}
file_put_contents(app_path('docs/PROJECT_MAP.md'), $md);
$mmd = "flowchart LR\n";
foreach ($map['routes'] as $i => $route) {
    $routeNode = 'r'.$i;
    $mmd .= "  {$routeNode}[\"{$route['path']}\"] --> c{$i}[\"{$route['controller']}\"]\n";
    foreach ($route['services'] as $service) $mmd .= "  c{$i} --> s{$service}[\"{$service}\"]\n";
}
file_put_contents(app_path('docs/project-map.mmd'), $mmd);
