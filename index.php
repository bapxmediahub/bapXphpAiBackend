<?php
/**
 * Main Entry Point
 * - /admin/* → PHP Admin pages (via Router)
 * - /api/* → JSON API endpoints
 * - Everything else → PHP-rendered pages (via Router)
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// API routes - JSON only
if (strpos($uri, '/api/') === 0) {
    require __DIR__ . '/api/index.php';
    exit;
}

// All other routes (public + admin) → PHP Router
require __DIR__ . '/app/bootstrap.php';
$router = new App\Router(require __DIR__ . '/app/routes.php');
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);