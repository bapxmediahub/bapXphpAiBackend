<?php
/**
 * Generates docs/project-index.json — the committed, queryable index of what this
 * project actually contains.
 *
 * The Mermaid maps are for humans; 51KB of flowchart is not something an agent can
 * query. This file is the machine-readable counterpart: a flat, greppable inventory
 * of every route, controller, service, view and collection that really exists, so an
 * agent can check a feature before claiming or inventing one.
 *
 * Generated from ProjectMapService::scan(), the same scan that builds the maps, so
 * the two can never disagree. Committed so a fresh clone has it.
 *
 *   php cli/generate-project-index.php [root] [outfile]
 */
require_once __DIR__ . '/../app/bootstrap.php';

use App\Services\ProjectMapService;

$root = $argv[1] ?? dirname(__DIR__);
$out  = $argv[2] ?? $root . '/docs/project-index.json';

$map = ProjectMapService::scan();

$routes = [];
foreach ($map['routes'] as $route) {
    $routes[] = [
        'method' => $route['method'] ?? 'GET',
        'path' => $route['path'] ?? '',
        'name' => $route['name'] ?? '',
        'controller' => $route['controller'] ?? '',
        'view' => $route['page'] ?? '',
        'services' => array_values($route['services'] ?? []),
    ];
}
usort($routes, fn($a, $b) => [$a['path'], $a['method']] <=> [$b['path'], $b['method']]);

$normalise = static function (array $rows): array {
    $names = [];
    foreach ($rows as $key => $row) {
        $names[] = is_string($row) ? $row : (string)($row['name'] ?? $row['file'] ?? $key);
    }
    $names = array_values(array_unique(array_filter($names)));
    sort($names);
    return $names;
};

$index = [
    'generated_by' => 'cli/generate-project-index.php',
    'source' => 'App\\Services\\ProjectMapService::scan()',
    'note' => 'Authoritative inventory of what exists. If it is not here, it does not exist — do not assume, add it first.',
    'summary' => [
        'routes' => count($routes),
        'controllers' => count($map['controllers']),
        'services' => count($map['services']),
        'views' => count($map['views']),
        'collections' => count($map['schema_collections']),
    ],
    'routes' => $routes,
    'controllers' => $normalise($map['controllers']),
    'services' => $normalise($map['services']),
    'views' => $normalise($map['views']),
    'collections' => $normalise($map['schema_collections']),
    'integrations' => $normalise($map['integrations']),
    // Keyed by gap category (missing_view_files, unwired_services, ...). Kept keyed
    // rather than flattened so an agent can ask "is X actually wired up?" directly.
    'gaps' => array_map('array_values', $map['gaps']),
];

$json = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

if (!is_dir(dirname($out))) mkdir(dirname($out), 0775, true);
file_put_contents($out, $json);

echo "Wrote {$out} (" . strlen($json) . " bytes, {$index['summary']['routes']} routes, "
   . "{$index['summary']['services']} services, {$index['summary']['collections']} collections)\n";
