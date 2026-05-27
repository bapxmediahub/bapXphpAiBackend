<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require __DIR__ . '/../app/bootstrap.php';

$routes = require __DIR__ . '/../app/routes.php';
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api', '', $uri) ?: '/';

$matched = false;
foreach ($routes as $route) {
    if ($route['method'] !== $method) continue;
    $pattern = preg_replace('#\{[^/]+\}#', '([^/]+)', $route['path']);
    if (preg_match('#^' . $pattern . '$#', $path, $matches)) {
        array_shift($matches);
        [$class, $action] = explode('@', $route['controller']);
        $fqcn = 'App\\Controllers\\' . $class;
        $controller = new $fqcn();
        $controller->{$action}(...$matches);
        $matched = true;
        break;
    }
}

if (!$matched) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
}
