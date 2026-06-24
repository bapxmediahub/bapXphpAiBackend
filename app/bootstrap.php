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
    if (str_starts_with($relative, 'Integrations/MetaPixel/')) $paths[] = app_path('integrations/meta-pixel/' . basename($relative));
    if (str_starts_with($relative, 'Integrations/GoogleSiteKit/')) $paths[] = app_path('integrations/google-site-kit/' . basename($relative));
    foreach ($paths as $path) { if (is_file($path)) { require $path; return; } }
});

function app_path(string $path = ''): string { return dirname(__DIR__) . ($path ? '/' . ltrim($path, '/') : ''); }
function storage_path(string $path = ''): string { return app_path('storage' . ($path ? '/' . ltrim($path, '/') : '')); }
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function placeholder_img(string $label = ''): string {
    $label = $label ?: 'Sri Panchami';
    $label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400"><rect fill="#fdfbf7" width="400" height="400"/><text x="200" y="180" text-anchor="middle" font-family="serif" font-size="28" fill="#3A0003">🪷</text><text x="200" y="230" text-anchor="middle" font-family="sans-serif" font-size="14" fill="#8c7e6d">' . $label . '</text></svg>';
    return 'data:image/svg+xml,' . rawurlencode($svg);
}

\App\Services\EnvService::load();
