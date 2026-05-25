<?php
require __DIR__ . '/app/bootstrap.php';
$router = new App\Router(require __DIR__ . '/app/routes.php');
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
