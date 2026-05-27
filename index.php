<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// API routes - JSON only
if (strpos($uri, '/api/') === 0) {
    require __DIR__ . '/api/index.php';
    exit;
}

// Admin routes - PHP
if (strpos($uri, '/admin') === 0) {
    require __DIR__ . '/app/bootstrap.php';
    $router = new App\Router(require __DIR__ . '/app/routes.php');
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    exit;
}

// Everything else - SPA (Vanilla JS)
require __DIR__ . '/views/layouts/spa.php';
