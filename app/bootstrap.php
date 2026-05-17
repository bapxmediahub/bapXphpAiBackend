<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Kolkata');
session_start();

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $relative = str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    $paths = [__DIR__ . '/' . $relative];
    if (str_starts_with($relative, 'Integrations/Razorpay/')) $paths[] = app_path('integrations/razorpay/' . basename($relative));
    if (str_starts_with($relative, 'Integrations/GoogleOAuth/')) $paths[] = app_path('integrations/google-oauth/' . basename($relative));
    if (str_starts_with($relative, 'Integrations/GoogleCalendar/')) $paths[] = app_path('integrations/google-calendar/' . basename($relative));
    foreach ($paths as $path) { if (is_file($path)) { require $path; return; } }
});

function app_path(string $path = ''): string { return dirname(__DIR__) . ($path ? '/' . ltrim($path, '/') : ''); }
function storage_path(string $path = ''): string { return app_path('storage' . ($path ? '/' . ltrim($path, '/') : '')); }
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
