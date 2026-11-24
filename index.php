<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;

if (str_starts_with((string)$uri, '/assets/images/astrologers/')) {
    http_response_code(410);
    header('Cache-Control: no-store');
    exit('This image has been retired.');
}

// The built-in PHP server otherwise serves an existing /storage/*.json file
// before application routing; Apache blocks /storage via .htaccess as well.
if ($uri === '/storage' || str_starts_with((string)$uri, '/storage/')) {
    http_response_code(404);
    header('Cache-Control: no-store');
    exit('Not found');
}

// Runtime agent rules and developer harness instructions are private even when
// php -S would otherwise serve an existing Markdown file before routing.
$privatePath = (string)$uri;
for ($decodePass=0; $decodePass<3; $decodePass++) {
    $decoded = rawurldecode($privatePath);
    if ($decoded === $privatePath) break;
    $privatePath = $decoded;
}
if (preg_match('~(?:^|/)(?:\.agents|\.bapX)(?:/|$)|^/cli(?:/|$)|^/AGENTS\.md$|^/content/help/admin(?:/|$)|^/content/blog/posts(?:/|$)~i', $privatePath)) {
    http_response_code(404);
    header('Cache-Control: no-store');
    exit('Not found');
}

if (PHP_SAPI === 'cli-server' && is_file($file)) {
    return false;
}

// Uploaded image binaries live outside the deployment checkout. Keep their
// existing public URLs while serving only the tightly constrained image path.
if (str_starts_with((string)$uri, '/assets/images/media/') && !is_file($file)) {
    require_once __DIR__ . '/app/Services/MediaService.php';
    $uploaded = \App\Services\MediaService::storedPathForUrl((string)$uri);
    if ($uploaded !== null) {
        header('Content-Type: ' . (mime_content_type($uploaded) ?: 'application/octet-stream'));
        header('Content-Length: ' . (string)filesize($uploaded));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile($uploaded);
    } else {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo 'Image not found';
    }
    exit;
}

// Fallback static file serving for local dev when php -S is started from a different cwd.
if (PHP_SAPI === 'cli-server' && preg_match('#^/(assets|storage)/#', $uri) === 1) {
    $asset = realpath(__DIR__ . $uri);
    $root = realpath(__DIR__);
    if ($asset && $root && str_starts_with($asset, $root . DIRECTORY_SEPARATOR) && is_file($asset)) {
        $mime = mime_content_type($asset) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($asset));
        $ext = strtolower(pathinfo($asset, PATHINFO_EXTENSION));
        $cacheMaxAge = in_array($ext, ['webp','png','jpg','jpeg','gif','svg','ico','css','js','woff2','woff','ttf','eot'], true) ? 31536000 : 86400;
        header('Cache-Control: public, max-age=' . $cacheMaxAge . ', immutable');
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + $cacheMaxAge));
        readfile($asset);
        exit;
    }
}

// PWA static files
if ($uri === '/sw.js') {
    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    readfile(__DIR__ . '/assets/pwa/sw-user.js');
    exit;
}
if (in_array($uri, ['/manifest.json', '/admin/manifest.json'], true)) {
    define('BAPX_PUBLIC_MANIFEST', true);
    require_once __DIR__ . '/app/bootstrap.php';
    $profile = \App\Services\SiteProfileService::current();
    $settings = (new \App\Services\SettingsService())->public();
    $admin = $uri === '/admin/manifest.json';
    $manifest = $admin ? [
        // Keep the installed app's original identity and scope.
        'id' => '/admin', 'start_url' => '/admin/', 'scope' => '/admin/',
        'display' => 'standalone', 'background_color' => '#222222', 'theme_color' => '#3a0003',
        'icons' => [
            ['src' => '/assets/images/pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => '/assets/images/pwa/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
        ],
    ] : json_decode((string)file_get_contents(__DIR__ . '/assets/pwa/manifest-user.json'), true, flags: JSON_THROW_ON_ERROR);
    $manifest['name'] = $admin ? $profile->name() . ' Admin' : $profile->name();
    $manifest['short_name'] = $profile->hasReferenceName() ? ($admin ? 'SPS Admin' : 'SPS') : $manifest['name'];
    if ($admin) $manifest['description'] = 'Owner workspace for ' . $profile->name() . '.';
    elseif ($profile->summary() !== '') $manifest['description'] = $profile->summary();
    elseif ($profile->isReference()) $manifest['description'] = 'Authentic spiritual products and temple guides.';
    if ($admin && $profile->hasReferenceName()) $manifest['name'] = 'SPS Admin';
    if (!$profile->isReference()) {
        $manifest['icons'] = [];
        foreach (array_unique(array_filter([$settings['logo_url'] ?? null, $settings['favicon_url'] ?? null], 'is_string')) as $url) {
            if (!preg_match('~^/assets/images/[A-Za-z0-9_./-]+\.(?:png|jpe?g)$~iD', $url) || str_contains($url, '..')) continue;
            $path = \App\Services\MediaService::storedPathForUrl($url) ?? app_path(ltrim($url, '/'));
            $image = is_file($path) ? @getimagesize($path) : false;
            if (!$image || !in_array($image['mime'], ['image/png', 'image/jpeg'], true)) continue;
            $manifest['icons'][] = ['src' => $url, 'sizes' => $image[0] . 'x' . $image[1], 'type' => $image['mime']];
        }
    }
    header('Content-Type: application/manifest+json; charset=utf-8');
    header('Cache-Control: no-cache');
    echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}
if ($uri === '/admin/sw.js') {
    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: no-cache');
    readfile(__DIR__ . '/assets/pwa/sw-admin.js');
    exit;
}

// Remote DB query endpoint
if ($uri === '/remotedb') {
    require __DIR__ . '/app/bootstrap.php';
    header('Content-Type: application/json');
    (new \App\Controllers\RemoteDbController())();
    exit;
}

// API routes - JSON only
if (strpos($uri, '/api/') === 0) {
    header('Content-Type: application/json');
    require __DIR__ . '/api/index.php';
    exit;
}

// Trailing slash redirect for web routes (prevents 404s on /docs/ etc.)
if ($uri !== null && $uri !== '/' && $uri !== '/admin/' && str_ends_with($uri, '/')) {
    header('Location: ' . rtrim($uri, '/') . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit;
}

// PHP routes (admin + public pages). Public discovery is generated by the
// controller, so its URLs must pass this gate after the hosting rewrite too.
$phpRoutes = ['/','/pages','/search','/shop','/shop/','/product','/cart','/checkout','/payment','/support','/about','/ai-assistants','/llms.txt','/contact','/temples','/consult','/consultation','/auth','/login','/logout','/register','/forgot-password','/reset-password','/account','/orders','/reviews','/sri-panchami-spiritual','/spiritual','/categories','/terms','/privacy','/blog','/docs','/help','/sitemap.xml','/feeds/google-products.xml','/is-sha-valid'];
$isPhpRoute = false;
foreach ($phpRoutes as $route) {
    if (strpos($uri, $route . '/') === 0 || $uri === $route) {
        $isPhpRoute = true;
        break;
    }
}

if (strpos($uri, '/admin') === 0 || $isPhpRoute) {
    require __DIR__ . '/app/bootstrap.php';
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    $router = new App\Router(require __DIR__ . '/app/routes.php');
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    exit;
}

http_response_code(404);
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
require __DIR__ . '/app/bootstrap.php';
$secrets = (new App\Services\SecretService())->all();
$seo = (new App\Services\SeoService($secrets))->page('404', [
    'title' => 'Page not found',
    'description' => 'The page you requested could not be found.',
    'robots' => 'noindex, follow',
]);
$pageTitle = $seo['title'];
$metaDescription = $seo['description'];
$metaRobots = $seo['robots'];
$viewFile = __DIR__ . '/views/public/404.php';
if (is_file($viewFile)) {
    require __DIR__ . '/views/layouts/app.php';
} else {
    echo 'Page not found';
}
