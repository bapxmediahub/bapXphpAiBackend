<?php
/**
 * Main Entry Point
 * Routes to either:
 * - SPA layout (React) for public pages
 * - API endpoints (/api/*)
 * - Admin pages (PHP)
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Admin routes - use PHP
if (strpos($uri, '/admin') === 0) {
    require __DIR__ . '/app/bootstrap.php';
    $router = new App\Router(require __DIR__ . '/app/routes.php');
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    exit;
}

// API routes - JSON only
if (strpos($uri, '/api/') === 0) {
    require __DIR__ . '/api/index.php';
    exit;
}

// All other routes - React SPA
require __DIR__ . '/views/layouts/spa.php';
