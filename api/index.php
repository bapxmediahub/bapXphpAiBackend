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

$router = new class(require __DIR__ . '/../app/routes.php') extends App\Router {
    public function dispatchApi(string $method, string $uri): void {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = str_replace('/api', '', $path) ?: '/';
        
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;
            $pattern = preg_replace('#\{[^/]+\}#', '([^/]+)', $route['path']);
            if (preg_match('#^' . $pattern . '$#', $path, $matches)) {
                array_shift($matches);
                [$class, $action] = explode('@', $route['controller']);
                $fqcn = 'App\\Controllers\\' . $class;
                $controller = new $fqcn();
                
                if (method_exists($controller, $action . 'Api')) {
                    $controller->{$action . 'Api'}(...$matches);
                } else {
                    $controller->{$action}(...$matches);
                }
                return;
            }
        }
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    }
};

$router->dispatchApi($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
