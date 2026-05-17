<?php
namespace App;

final class Router {
    public function __construct(private array $routes) {}
    public function dispatch(string $method, string $uri): void {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;
            $pattern = preg_replace('#\{[^/]+\}#', '([^/]+)', $route['path']);
            if (preg_match('#^' . $pattern . '$#', $path, $matches)) {
                array_shift($matches);
                [$class, $action] = explode('@', $route['controller']);
                $fqcn = 'App\\Controllers\\' . $class;
                (new $fqcn())->{$action}(...$matches);
                return;
            }
        }
        http_response_code(404);
        echo 'Page not found';
    }
}
