<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;

if (PHP_SAPI === 'cli-server' && is_file($file)) {
    return false;
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
$pwaFiles = [
    '/manifest.json' => 'assets/pwa/manifest-user.json',
    '/sw.js' => 'assets/pwa/sw-user.js',
];
if (isset($pwaFiles[$uri])) {
    $pwaAsset = __DIR__ . '/' . $pwaFiles[$uri];
    if (is_file($pwaAsset)) {
        $mime = str_ends_with($uri, '.json') ? 'application/json' : 'application/javascript';
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
        readfile($pwaAsset);
        exit;
    }
}
if ($uri === '/admin/manifest.json' || $uri === '/astrologer/manifest.json') {
    $isAdmin = $uri === '/admin/manifest.json';
    $manifest = [
        'name' => $isAdmin ? 'SPS Admin' : 'SPS Consultant',
        'short_name' => $isAdmin ? 'SPS Admin' : 'SPS Con',
        'description' => $isAdmin ? 'Admin panel for Sri Panchami Spiritual.' : 'Astrologer consultation workspace.',
        'start_url' => $isAdmin ? '/admin' : '/astrologer',
        'scope' => $isAdmin ? '/admin/' : '/astrologer/',
        'display' => 'standalone',
        'background_color' => $isAdmin ? '#222222' : '#240002',
        'theme_color' => '#3a0003',
        'icons' => [
            ['src' => '/assets/images/logo-square.jpeg', 'sizes' => '192x192', 'type' => 'image/jpeg'],
            ['src' => '/assets/images/logo.jpeg', 'sizes' => '512x512', 'type' => 'image/jpeg'],
        ],
    ];
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}
if ($uri === '/admin/sw.js' || $uri === '/astrologer/sw.js') {
    $role = $uri === '/admin/sw.js' ? 'admin' : 'consultant';
    $cacheName = 'sps-cache-' . $role . '-v1';
    $precache = json_encode($role === 'admin' ? ['/admin', '/login'] : ['/astrologer', '/login']);
    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: no-cache');
    echo "const CACHE_NAME = {$cacheName}; const PRECACHE_URLS = {$precache};";
    echo "self.addEventListener('install',e=>{e.waitUntil(caches.open(CACHE_NAME).then(c=>c.addAll(PRECACHE_URLS)).then(()=>self.skipWaiting()));});";
    echo "self.addEventListener('activate',e=>{e.waitUntil(caches.keys().then(k=>Promise.all(k.map(n=>n!==CACHE_NAME?caches.delete(n):null))).then(()=>self.clients.claim()));});";
    echo "self.addEventListener('fetch',e=>{if(e.request.method!=='GET')return;e.respondWith(caches.match(e.request).then(c=>c||fetch(e.request).then(r=>{if(!r||r.status!==200||r.type!=='basic')return r;const url=new URL(e.request.url);if(!url.pathname.startsWith('/api')){caches.open(CACHE_NAME).then(ca=>ca.put(e.request,r.clone()));}return r;}).catch(()=>e.request.mode==='navigate'?caches.match('/'):new Response('Offline',{status:503}))));});";
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
if ($uri !== null && $uri !== '/' && str_ends_with($uri, '/')) {
    header('Location: ' . rtrim($uri, '/') . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit;
}

// PHP routes (admin + public pages)
$phpRoutes = ['/','/shop','/shop/','/product','/cart','/checkout','/payment','/support','/about','/contact','/temples','/consult','/consultation','/astrologer','/auth','/login','/logout','/register','/forgot-password','/reset-password','/account','/reviews','/sri-panchami-spiritual','/spiritual','/categories','/terms','/privacy','/blog','/docs','/help','/sitemap.xml'];
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
