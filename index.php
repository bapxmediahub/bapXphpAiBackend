<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// API routes - JSON only
if (strpos($uri, '/api/') === 0) {
    require __DIR__ . '/api/index.php';
    exit;
}

// PHP routes (admin + public pages)
$phpRoutes = ['/','/shop','/shop/','/product','/cart','/checkout','/about','/contact','/temples','/astrologers','/login','/register','/account','/spiritual'];
$isPhpRoute = false;
foreach ($phpRoutes as $route) {
    if (strpos($uri, $route . '/') === 0 || $uri === $route) {
        $isPhpRoute = true;
        break;
    }
}

if (strpos($uri, '/admin') === 0 || $isPhpRoute) {
    require __DIR__ . '/app/bootstrap.php';
    $router = new App\Router(require __DIR__ . '/app/routes.php');
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    exit;
}

// Everything else - SPA (Vanilla JS)
require __DIR__ . '/views/layouts/spa.php';
