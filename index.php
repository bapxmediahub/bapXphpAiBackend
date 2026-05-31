<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;

if (PHP_SAPI === 'cli-server' && is_file($file)) {
    return false;
}

// API routes - JSON only
if (strpos($uri, '/api/') === 0) {
    require __DIR__ . '/api/index.php';
    exit;
}

// PHP routes (admin + public pages)
$phpRoutes = ['/','/shop','/shop/','/product','/cart','/checkout','/payment','/recharge','/support','/about','/contact','/temples','/astrologers','/appointments','/login','/logout','/register','/forgot-password','/reset-password','/account','/reviews','/sri-panchami-spiritual','/spiritual','/categories'];
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

http_response_code(404);
require __DIR__ . '/app/bootstrap.php';
$viewFile = __DIR__ . '/views/public/404.php';
if (is_file($viewFile)) {
    require __DIR__ . '/views/layouts/app.php';
} else {
    echo 'Page not found';
}
