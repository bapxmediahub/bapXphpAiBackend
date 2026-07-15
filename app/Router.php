<?php
namespace App;

final class Router {
    public function __construct(
        private array $routes,
        private ?\Closure $controllerFactory = null
    ) {}
    public function dispatch(string $method, string $uri): void {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;
            $pattern = preg_replace('#\{[^/]+\}#', '([^/]+)', $route['path']);
            if (preg_match('#^' . $pattern . '$#', $path, $matches)) {
                array_shift($matches);
                [$class, $action] = explode('@', $route['controller']);
                $fqcn = 'App\\Controllers\\' . $class;
                $factory = $this->controllerFactory ?? fn(string $c) => new $c();
                $controller = $factory($fqcn);
                $controller->{$action}(...$matches);
                return;
            }
        }
        $this->renderNotFound();
    }

    private function renderNotFound(): void {
        http_response_code(404);
        $secrets = (new \App\Services\SecretService())->all();
        $seo = (new \App\Services\SeoService($secrets))->page('404', [
            'title' => 'Page not found',
            'description' => 'The page you requested could not be found.',
            'robots' => 'noindex, follow',
        ]);
        $pageTitle = $seo['title'];
        $metaDescription = $seo['description'];
        $metaRobots = $seo['robots'];
        $viewFile = app_path('views/public/404.php');
        require app_path('views/layouts/app.php');
    }
}
