<?php
declare(strict_types=1);

header('Content-Type: application/json');
$allowedOrigin = rtrim((string)(getenv('APP_URL') ?: ''), '/');
if ($allowedOrigin !== '') {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Vary: Origin');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require __DIR__ . '/../app/bootstrap.php';
} catch (\Throwable $e) {
    http_response_code(503);
    echo json_encode(['error' => 'Service temporarily unavailable', 'detail' => $e->getMessage()]);
    exit;
}

$routes = require __DIR__ . '/../app/routes.php';
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$paths = [$uri, str_replace('/api', '', $uri) ?: '/'];

$matched = false;
foreach ($routes as $route) {
    if ($route['method'] !== $method) continue;
    $pattern = preg_replace('#\{[^/]+\}#', '([^/]+)', $route['path']);
    $matches = [];
    foreach ($paths as $path) if (preg_match('#^' . $pattern . '$#', $path, $candidate)) { $matches = $candidate; break; }
    if ($matches) {
        array_shift($matches);
        [$class, $action] = explode('@', $route['controller']);
        $fqcn = 'App\\Controllers\\' . $class;
        try {
            $controller = new $fqcn();
            $controller->{$action}(...$matches);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal error', 'detail' => $e->getMessage()]);
        }
        $matched = true;
        break;
    }
}

if (!$matched) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
}
