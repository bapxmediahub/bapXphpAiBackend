<?php
declare(strict_types=1);

// Guard dynamic responses before session/environment output; CLI retains its own failure contract.
if (PHP_SAPI !== 'cli') {
    require_once __DIR__.'/Services/RequestFailureService.php';
    \App\Services\RequestFailureService::register(dirname(__DIR__));
}

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

if (PHP_SAPI === 'cli') {
    set_exception_handler(static function (\Throwable $error): never {
        // Keep CLI failures nonzero and suppress potentially sensitive message content.
        fwrite(STDERR, '[cli failure] ' . $error::class . ' at ' . basename($error->getFile()) . ':' . $error->getLine() . "\n");
        exit(1);
    });
}
