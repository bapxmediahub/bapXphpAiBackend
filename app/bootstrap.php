<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Kolkata');
ini_set('session.gc_maxlifetime', (string)(60 * 60 * 24 * 30));
session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 30,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
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
/** True when a site module (consult|shop|blog) is switched on in Admin → Site Settings. */
/**
 * Singular form of an admin collection label. rtrim($title,'s') produced "Categorie"
 * from "Categories" — it strips the trailing s without handling -ies.
 */
function singular_label(string $plural): string {
    $plural = trim($plural);
    if (preg_match('/ies$/i', $plural)) return preg_replace('/ies$/i', 'y', $plural);
    if (preg_match('/(ses|xes|zes|ches|shes)$/i', $plural)) return preg_replace('/es$/i', '', $plural);
    if (preg_match('/s$/i', $plural) && !preg_match('/ss$/i', $plural)) return preg_replace('/s$/i', '', $plural);
    return $plural;
}

function module_on(string $key): bool { return (new App\Services\SettingsService())->moduleEnabled($key); }
function placeholder_img(string $label = ''): string {
    $label = $label ?: 'Sri Panchami';
    $label = htmlspecialchars($label, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400"><rect fill="#fdfbf7" width="400" height="400"/><text x="200" y="180" text-anchor="middle" font-family="serif" font-size="28" fill="#3A0003">🪷</text><text x="200" y="230" text-anchor="middle" font-family="sans-serif" font-size="14" fill="#8c7e6d">' . $label . '</text></svg>';
    return 'data:image/svg+xml,' . rawurlencode($svg);
}

function webp_src(string $src): string {
    if (str_starts_with($src, 'data:') || str_contains($src, '.webp')) return $src;
    $webpPath = preg_replace('/\.(png|jpg|jpeg)$/i', '.webp', $src);
    $filePath = app_path($webpPath);
    if (is_file($filePath)) return $webpPath;
    return $src;
}

function img_tag(string $src, string $alt = '', array $attrs = []): string {
    $attrStr = '';
    foreach ($attrs as $k => $v) $attrStr .= ' ' . $k . '="' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '"';
    $webp = webp_src($src);
    if ($webp !== $src) {
        return '<picture><source srcset="' . htmlspecialchars($webp, ENT_QUOTES, 'UTF-8') . '" type="image/webp"><img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"' . $attrStr . '></picture>';
    }
    return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"' . $attrStr . '>';
}

\App\Services\EnvService::load();

set_exception_handler(static function (\Throwable $error): void {
    error_log('[unhandled] ' . $error::class . ': ' . $error->getMessage()
        . ' @ ' . $error->getFile() . ':' . $error->getLine());
    http_response_code(503);
    // Every Throwable lands here, not only database failures, so a signed-in admin is
    // shown what actually broke. Without this a TypeError in a view reports itself as
    // "we could not reach our secure database", which is untrue and undebuggable.
    $isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';
    $detail = $isAdmin
        ? $error::class . ': ' . $error->getMessage()
            . ' @ ' . str_replace(dirname(__DIR__) . '/', '', $error->getFile()) . ':' . $error->getLine()
        : '';
    header('Cache-Control: no-store');
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $acceptsJson = str_starts_with($uri, '/api/')
        || str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json')
        || str_contains(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json');
    if ($acceptsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_filter([
            'error' => 'The database is temporarily unavailable. Please retry.',
            'detail' => $detail !== '' ? $detail : null,
        ]));
        return;
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Service temporarily unavailable</title><style>body{margin:0;background:#fdfbf7;color:#2b1712;font:16px/1.6 system-ui,sans-serif;display:grid;min-height:100vh;place-items:center}.panel{width:min(560px,calc(100% - 40px));padding:40px;border:1px solid #d7bd72;border-radius:24px;background:#fff;box-shadow:0 18px 50px #3a00031a;text-align:center}h1{font-family:Georgia,serif;color:#3a0003}a{display:inline-block;margin-top:12px;padding:12px 20px;border-radius:999px;background:#3a0003;color:#fff;text-decoration:none}</style></head><body><main class="panel"><p>Temporary service interruption</p><h1>We could not reach our secure database.</h1><p>Your request was not recorded. Please wait a moment and try again. If you were completing a payment, contact support before retrying.</p><a href="javascript:location.reload()">Try again</a>' . ($detail !== '' ? '<pre style="margin-top:24px;padding:14px;background:#fdf2f2;border:1px solid #dc3545;border-radius:10px;text-align:left;white-space:pre-wrap;font-size:12px;color:#7f1d1d;">' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</pre>' : '') . '</main></body></html>';
});
