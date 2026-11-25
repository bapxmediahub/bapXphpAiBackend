<?php $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16)); $csrf = $_SESSION['csrf_token'];
$__settingsService = new \App\Services\SettingsService();
$__modules = $__settingsService->modules();
$siteProfile ??= \App\Services\SiteProfileService::current();
$__adminBrand = $siteProfile->name();
$__adminAppName = $siteProfile->hasReferenceName() ? 'SPS Admin' : $__adminBrand . ' Admin';
$__settings = $__settingsService->public();
$adminPath = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH), '/');
$sectionOpen = static fn (string $pattern): string => preg_match('~^/admin/(?:' . $pattern . ')(?:/|$)~', $adminPath) ? 'open' : '';
$navState = static function (string $path, string $class = '') use ($adminPath): string {
    if ($adminPath === $path) return 'class="' . trim($class . ' active') . '" aria-current="page"';
    if (str_starts_with($adminPath, $path . '/')) return 'class="' . trim($class . ' active') . '" aria-current="location"';
    return $class === '' ? '' : 'class="' . $class . '"';
};
$__adminLogo = ($__settings['logo_url'] ?? '') ?: ($siteProfile->isReference() ? '/assets/images/logo-square.jpeg' : '');

// Admin templates supply only literal icon names from this private set.
$navIcon = static function (string $name): string {
    $path = match ($name) {
        'home' => 'm3 10 9-7 9 7v11H3zM9 21v-7h6v7',
        'page' => 'M6 3h9l4 4v14H6zM14 3v5h5M9 12h7m-7 4h7',
        'chat' => 'M4 5h16v11H9l-5 4zM8 9h8m-8 3h5',
        'pulse' => 'M3 12h4l3-7 4 14 3-7h4',
        'ticket' => 'M3 6h18v4a2 2 0 0 0 0 4v4H3v-4a2 2 0 0 0 0-4zM15 9v1m0 4v1',
        'calendar' => 'M4 6h16v14H4zM4 10h16M8 4v4m8-4v4',
        'channel' => 'M3 4h18v13H3zM9 21h6m-3-4v4M10 8l5 2.5-5 2.5z',
        'bag' => 'M4 8h16l1 13H3zM8 9V7a4 4 0 0 1 8 0v2',
        'truck' => 'M3 6h12v11H3zM15 10h3l3 3v4h-6M6 17a2 2 0 1 0 4 0m5 0a2 2 0 1 0 4 0',
        'contact' => 'M5 4h15v16H5zM3 8h3m-3 4h3m-3 4h3m4 0h6m-6-5a2 2 0 1 0 4 0 2 2 0 0 0-4 0',
        'box' => 'm12 3 9 5-9 5-9-5 9-5ZM3 8v8l9 5 9-5V8m-9 5v8',
        'grid' => 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z',
        'layers' => 'm12 3 9 5-9 5-9-5 9-5Zm-9 9 9 5 9-5M3 16l9 5 9-5',
        'tag' => 'M3 5h9l9 9-7 7-9-9V5zm5 3h.01',
        'bars' => 'M3 3v18h18M8 16v-4m5 4V8m5 8V5',
        'receipt' => 'M5 3h14v18l-3-2-4 2-4-2-3 2zM8 8h8m-8 4h8',
        'line' => 'M3 3v18h18M6 16l4-5 4 2 6-7',
        'globe' => 'M2 12h20M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 0a15 15 0 0 1 0 20 15 15 0 0 1 0-20Z',
        'template' => 'M4 5h16v14H4zM4 9h16M8 13h8m-8 3h5',
        'inbox' => 'm3 13 3-8h12l3 8v6H3zM3 13h5l2 3h4l2-3h5',
        'send' => 'm3 4 18 8-18 8 3-8-3-8Zm3 8h15',
        'mail' => 'M3 5h18v14H3zM4 7l8 6 8-6',
        'temple' => 'M3 10l9-7 9 7H3zm2 10h14M7 10v10m5-10v10m5-10v10',
        'image' => 'M3 4h18v16H3zM3 17l6-6 5 5 3-3 4 4M15 8h.01',
        'brand' => 'M12 2l2.5 6.5L21 11l-6.5 2.5L12 20l-2.5-6.5L3 11l6.5-2.5z',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'plug' => 'M9 3v4m6-4v4M6 7h12v3a6 6 0 0 1-12 0V7Zm6 9v5',
        'settings' => 'M4 7h4m5 0h7M4 17h9m5 0h2M8 4v6m10 4v6',
        'search' => 'M15.5 15.5 21 21M18 10.5a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z',
        'filter' => 'M4 7h16M7 12h10m-7 5h4',
        'check' => 'm5 12 4 4L19 6',
        'previous' => 'm15 5-7 7 7 7',
        'next' => 'm9 5 7 7-7 7',
        'reset' => 'M3 10V4m0 6h6M4 9a8 8 0 1 1 0 6',
        'help' => 'M3 5h5a4 4 0 0 1 4 4v11a4 4 0 0 0-4-3H3zM21 5h-5a4 4 0 0 0-4 4v11a4 4 0 0 1 4-3h5z',
    };

    return '<svg class="admin-nav-icon" viewBox="0 0 24 24" width="16" height="16" fill="none"'
        . ' stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"'
        . ' aria-hidden="true" focusable="false"><path d="' . $path . '"/></svg>';
};
?>
<!doctype html>
<html lang="en" class="admin-document">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle ?? 'Admin') ?> — <?= e($__adminBrand) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="manifest" href="/admin/manifest.json">
<meta name="theme-color" content="#3a0003">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e($__adminAppName) ?>">
<?php if ($__adminLogo !== ''): ?><link rel="apple-touch-icon" href="<?= e($__adminLogo) ?>"><?php endif; ?>
<link rel="stylesheet" href="/assets/css/band.css?v=<?= filemtime(__DIR__.'/../../assets/css/band.css') ?>">
<link rel="stylesheet" href="/assets/css/admin-refinement.css?v=<?= filemtime(__DIR__.'/../../assets/css/admin-refinement.css') ?>">
<link rel="stylesheet" href="/assets/css/admin-workspaces.css?v=<?= filemtime(__DIR__.'/../../assets/css/admin-workspaces.css') ?>">
<script src="/assets/agent-status.js?v=<?= filemtime(__DIR__.'/../../assets/agent-status.js') ?>" defer></script>
<link rel="stylesheet" href="/assets/css/site-motion.css?v=<?= filemtime(__DIR__ . '/../../assets/css/site-motion.css') ?>">
<script src="/assets/site-motion.js?v=<?= filemtime(__DIR__ . '/../../assets/site-motion.js') ?>" defer></script>
<link rel="stylesheet" href="/assets/css/page-actions.css?v=<?= filemtime(app_path('assets/css/page-actions.css')) ?>">
<script src="/assets/page-actions.js?v=<?= filemtime(app_path('assets/page-actions.js')) ?>" defer></script>
</head>
<body>
<a class="admin-skip-link" href="#admin-content">Skip to page content</a>
<div class="admin-shell">
    <div class="admin-sidebar-backdrop" id="admin-sidebar-backdrop" hidden aria-hidden="true"></div>
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="Owner navigation" tabindex="-1">
        <button type="button" id="admin-sidebar-close" class="admin-sidebar__close" aria-label="Close navigation">
            <svg aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg>
        </button>
        <a class="admin-sidebar-close-link" href="#admin-content">Close navigation</a>
        <div class="admin-sidebar__brand">
            <?php if ($__adminLogo !== ''): ?><img src="<?= e($__adminLogo) ?>" alt="" width="42" height="42"><?php endif; ?>
            <div><span><?= e($__adminBrand) ?></span><small>Owner workspace</small></div>
        </div>
        <nav class="admin-sidebar__nav" id="admin-nav" aria-label="Owner workspace">
            <a href="/admin/" class="admin-nav-top<?= $adminPath === '/admin' ? ' active' : '' ?>"<?= $adminPath === '/admin' ? ' aria-current="page"' : '' ?>>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Dashboard
            </a>
            <?php $agentNavPath = $adminPath; ?>
            <details class="admin-agent-nav" <?= $sectionOpen('agent|support-tickets') ?>>
                <summary class="admin-nav-top admin-nav-agent"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 11.5a8 8 0 0 1-8 8H6l-3 2V11.5a8 8 0 0 1 8-8h2a8 8 0 0 1 8 8Z"/><path d="M8 10h8M8 14h5"/></svg>Enterprise Agent<svg class="admin-nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><polyline points="6 9 12 15 18 9"/></svg></summary>
                <div class="admin-nav-group">
                    <a href="/admin/agent" <?= $agentNavPath === '/admin/agent' ? 'class="active" aria-current="page"' : '' ?>><?= $navIcon('chat') ?>Chat</a>
                    <a href="/admin/agent/monitoring" <?= $agentNavPath === '/admin/agent/monitoring' ? 'class="active" aria-current="page"' : '' ?>><?= $navIcon('pulse') ?>Agent Monitoring</a>
                    <a href="/admin/support-tickets" <?= $agentNavPath === '/admin/support-tickets' ? 'class="active" aria-current="page"' : '' ?>><?= $navIcon('ticket') ?>AI Support Tickets</a>
                </div>
            </details>
            <details class="admin-sidebar-group" <?= $sectionOpen('appointments') ?>>
                <summary class="admin-nav-toggle">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 000 20 14.5 14.5 0 000-20"/><path d="M2 12h20"/></svg>
                    Services
                    <svg class="admin-nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><polyline points="6 9 12 15 18 9"/></svg>
                </summary>
                <div class="admin-submenu">
                    <a href="/admin/appointments" <?= $navState('/admin/appointments') ?>><?= $navIcon('calendar') ?>Sessions</a>
                </div>
            </details>
            <a href="/admin/pages" <?= $navState('/admin/pages', 'admin-nav-top') ?>><?= $navIcon('page') ?>Pages</a>
            <?php if ($__modules['blog']): ?>
            <a href="/admin/blog" <?= $navState('/admin/blog', 'admin-nav-top') ?>>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                Blog
            </a>
            <?php endif; ?>
            <details class="admin-sidebar-group" <?= $sectionOpen('google-merchant|orders|shipping|contact-submissions|products|categories|collections|coupons') ?>>
                <summary class="admin-nav-toggle">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                    Commerce
                    <svg class="admin-nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><polyline points="6 9 12 15 18 9"/></svg>
                </summary>
                <div class="admin-submenu">
                    <a data-google-channel-nav href="/admin/google-merchant" <?= $navState('/admin/google-merchant') ?>><?= $navIcon('channel') ?>Google &amp; YouTube</a>
                    <a href="/admin/orders" <?= $navState('/admin/orders') ?>><?= $navIcon('bag') ?>Orders</a>
                    <a href="/admin/shipping" <?= $navState('/admin/shipping') ?>><?= $navIcon('truck') ?>Shipping</a>
                    <a href="/admin/contact-submissions" <?= $navState('/admin/contact-submissions') ?>><?= $navIcon('contact') ?>Contacts</a>
                    <?php if ($__modules['shop']): ?>
                        <span class="admin-nav-section-label">Catalog</span>
                    <a href="/admin/products" <?= $navState('/admin/products') ?>><?= $navIcon('box') ?>Products</a>
                    <a href="/admin/categories" <?= $navState('/admin/categories') ?>><?= $navIcon('grid') ?>Categories</a>
                    <a href="/admin/collections" <?= $navState('/admin/collections') ?>><?= $navIcon('layers') ?>Special Collections</a>
                    <a href="/admin/coupons" <?= $navState('/admin/coupons') ?>><?= $navIcon('tag') ?>Coupons</a>
                    <?php endif; ?>
                </div>
            </details>
            <a href="/admin/users" class="admin-nav-top<?= $agentNavPath === '/admin/users' ? ' active' : '' ?>"<?= $agentNavPath === '/admin/users' ? ' aria-current="page"' : '' ?>>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><circle cx="9" cy="7" r="4"/><path d="M2 21v-3a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v3m1-18a4 4 0 0 1 0 8m5 10v-3a4 4 0 0 0-3-4"/></svg>
                Users
            </a>
            <details class="admin-sidebar-group" <?= $sectionOpen('tax-report|consultation-analytics') ?>>
                <summary class="admin-nav-toggle"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 3v18h18M8 16v-3m5 3V9m5 7V5"/></svg>Analytics<svg class="admin-nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><polyline points="6 9 12 15 18 9"/></svg></summary>
                <div class="admin-submenu">
                    <a href="/admin/#dashboard-commerce-title"><?= $navIcon('bars') ?>Commerce overview</a>
                    <a href="/admin/tax-report" <?= $navState('/admin/tax-report') ?>><?= $navIcon('receipt') ?>Commerce Reports</a>
                    <a href="/admin/consultation-analytics" <?= $navState('/admin/consultation-analytics') ?>><?= $navIcon('line') ?>Session analytics</a>
                    <a href="/admin/#web-analytics"><?= $navIcon('globe') ?>Web analytics</a>
                </div>
            </details>
            <details class="admin-sidebar-group" <?= $sectionOpen('email-templates|email-inbox|email-outbox') ?>>
                <summary class="admin-nav-toggle"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>Email<svg class="admin-nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><polyline points="6 9 12 15 18 9"/></svg></summary>
                <div class="admin-submenu">
                    <a data-mail-templates-nav href="/admin/email-templates" <?= $navState('/admin/email-templates') ?>><?= $navIcon('template') ?>Templates &amp; triggers</a>
                    <a href="/admin/email-inbox" <?= $navState('/admin/email-inbox') ?>><?= $navIcon('inbox') ?>Inbox</a>
                    <a href="/admin/email-outbox" class="<?= ($agentNavPath === '/admin/email-outbox' && ($_GET['type'] ?? '') !== 'newsletter' ? 'active' : '') ?>"<?= $agentNavPath === '/admin/email-outbox' && ($_GET['type'] ?? '') !== 'newsletter' ? ' aria-current="page"' : '' ?>><?= $navIcon('send') ?>Outbox</a>
                    <a href="/admin/email-outbox?type=newsletter" class="<?= $agentNavPath === '/admin/email-outbox' && ($_GET['type'] ?? '') === 'newsletter' ? 'active' : '' ?>"<?= $agentNavPath === '/admin/email-outbox' && ($_GET['type'] ?? '') === 'newsletter' ? ' aria-current="page"' : '' ?>><?= $navIcon('mail') ?>Newsletters</a>
                </div>
            </details>
            <details class="admin-sidebar-group" <?= $sectionOpen('temples|media|appearance') ?>>
                <summary class="admin-nav-toggle">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                    Appearance
                    <svg class="admin-nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><polyline points="6 9 12 15 18 9"/></svg>
                </summary>
                <div class="admin-submenu">
                    <a href="/admin/temples" <?= $navState('/admin/temples') ?>><?= $navIcon('temple') ?>Temples</a>
                    <a href="/admin/media" <?= $navState('/admin/media') ?>><?= $navIcon('image') ?>Media Library</a>
                    <a href="/admin/appearance" <?= $agentNavPath === '/admin/appearance' ? 'class="active" aria-current="page"' : '' ?>><?= $navIcon('brand') ?>Logo &amp; Favicon</a>
                    <a href="/admin/appearance/menu" <?= $agentNavPath === '/admin/appearance/menu' ? 'aria-current="page" class="active"' : '' ?>><?= $navIcon('menu') ?>Header menu</a>
                </div>
            </details>
            <details class="admin-sidebar-group" <?= $sectionOpen('system-health|integrations|settings') ?>>
                <summary class="admin-nav-toggle">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                    Settings
                    <svg class="admin-nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><polyline points="6 9 12 15 18 9"/></svg>
                </summary>
                <div class="admin-submenu">
                    <a data-system-health-nav href="/admin/system-health" <?= $navState('/admin/system-health') ?>><?= $navIcon('pulse') ?>System Health</a>
                    <a href="/admin/integrations" <?= $navState('/admin/integrations') ?>><?= $navIcon('plug') ?>Integrations</a>
                    <a href="/admin/settings" <?= $navState('/admin/settings') ?>><?= $navIcon('settings') ?>Site Settings</a>
                </div>
            </details>
            <?php $helpPath=(string)(parse_url($_SERVER['REQUEST_URI']??'/admin',PHP_URL_PATH)?:'/admin'); ?>
            <a data-admin-help-nav href="/admin/docs" <?= $navState('/admin/docs', 'admin-nav-top') ?>>
                <?= $navIcon('help') ?>
                Help &amp; Docs
            </a>
        </nav>
        <div class="admin-sidebar__footer">
            <a href="/">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                View Site
            </a>
            <a href="/logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 17"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Logout
            </a>
        </div>
    </aside>
    <main class="admin-main" id="admin-content" tabindex="-1">
        <div class="admin-topbar">
            <button type="button" class="btn btn-sm btn-ghost admin-sidebar-toggle" id="sidebarToggle" aria-label="Open navigation" aria-controls="admin-sidebar" aria-expanded="false">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <a class="btn btn-sm btn-ghost admin-sidebar-fallback" href="#admin-sidebar" aria-controls="admin-sidebar"><?= $navIcon('menu') ?>Menu</a>
            <h1><?= e($pageTitle ?? 'Dashboard') ?></h1>
            <div class="admin-topbar__actions">
                <a class="btn btn-sm btn-ghost" data-admin-help-topbar href="/admin/docs"><?= $navIcon('help') ?>Help &amp; Docs</a>
                <a href="/" class="btn btn-sm btn-ghost" target="_blank" rel="noopener">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    View Site
                </a>
                <a href="/logout" class="btn btn-sm btn-ghost">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 17"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Logout
                </a>
            </div>
        </div>
        <div class="admin-breadcrumb" role="navigation" aria-label="Breadcrumb">
            <a href="/admin" class="admin-breadcrumb__home"<?= $adminPath === '/admin' ? ' aria-current="page"' : '' ?>><?= $navIcon('home') ?>Dashboard</a>
            <?php
            $__segments = array_values(array_filter(explode('/', $adminPath)));
            $__crumb = '/admin';
            $__labelMap = [
                'system-health'=>'System Health','docs'=>'Help & Docs','google-merchant'=>'Google & YouTube','products'=>'Products','categories'=>'Categories','coupons'=>'Coupons',
                'orders'=>'Orders','shipping'=>'Shipping','tax-report'=>'Reports','astrologers'=>'Astrologers',
                'appointments'=>'Sessions',
                'consultation-analytics'=>'Analytics','temples'=>'Temples',
                'settings'=>'Site Settings','integrations'=>'Integrations','agent'=>'Enterprise Agent','monitoring'=>'Agent Monitoring',
                'backups'=>'Backups','audit-log'=>'Audit Log',
                'contact-submissions'=>'Contacts','support-tickets'=>'AI Support Tickets',
                'email-templates'=>'Email Templates','email-inbox'=>'Email Inbox','email-outbox'=>'Email Outbox',
                'appearance'=>'Logo & Favicon','media'=>'Media Library',
                'environment'=>'Environment','developer'=>'Developer',
                'project-map'=>'Project Map','contact_submissions'=>'Contacts',
            ];
            foreach(\App\Services\AdminHelpService::references() as $helpReference)$__labelMap[$helpReference['slug']]=$helpReference['title'];
            foreach ($__segments as $__i => $__seg):
                if ($__seg === 'admin' && $__i === 0) continue;
                $__crumb .= '/' . $__seg;
                $__label = $__labelMap[$__seg] ?? ucwords(str_replace(['-','_'], ' ', $__seg));
                if ($__i === count($__segments) - 1):
            ?>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
                <span aria-current="page"><?= e($__label) ?></span>
            <?php else: ?>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
                <a href="<?= e($__crumb) ?>"><?= e($__label) ?></a>
            <?php endif; endforeach; ?>
        </div>
        <div class="admin-body">
        <?php
        $__flash = $_SESSION['flash'] ?? null;
        if ($__flash):
            $__msg = is_array($__flash) ? $__flash['message'] : $__flash;
            $__type = is_array($__flash) ? ($__flash['type'] ?? 'info') : 'info';
            $__flashLabels = ['success' => 'Success', 'error' => 'Action failed', 'warning' => 'Attention', 'info' => 'Update'];
            if (!is_string($__type) || !isset($__flashLabels[$__type])) $__type = 'info';
            unset($_SESSION['flash']);
        ?>
        <div class="admin-feedback admin-feedback--<?= e($__type) ?>" role="<?= $__type === 'error' ? 'alert' : 'status' ?>">
            <strong><?= e($__flashLabels[$__type]) ?></strong>
            <p><?= e((string) $__msg) ?></p>
        </div>
        <?php endif; ?>
        <?php $helpContext=\App\Services\AdminHelpService::adminContext($_SERVER['REQUEST_URI']??'/admin'); ?>
        <?php if($helpPath!=='/admin/users'&&$helpPath!=='/admin/docs'&&!str_starts_with($helpPath,'/admin/docs/')): ?>
        <details class="section-help" data-admin-context-help>
            <summary>Help for this page <span><?= e($helpContext['title']) ?></span></summary>
            <p><?= e($helpContext['summary']) ?></p>
            <a class="help-context-link" href="<?= e($helpContext['url']) ?>">Read this guide <span aria-hidden="true">→</span></a>
        </details>
        <?php endif; ?>
        <?php require $viewFile; ?>
        </div>
    </main>
</div>
<script>
if ('serviceWorker' in navigator) { navigator.serviceWorker.register('/admin/sw.js', { scope: '/admin/' }).catch(function(){}); }
var installPrompt=null;window.addEventListener('beforeinstallprompt',function(e){e.preventDefault();installPrompt=e;document.getElementById('pwa-install-btn').style.display='flex';});window.addEventListener('appinstalled',function(){installPrompt=null;document.getElementById('pwa-install-btn').style.display='none';});document.addEventListener('click',function(e){if(e.target.closest('#pwa-install-btn')){if(!installPrompt)return;installPrompt.prompt();installPrompt.userChoice.then(function(){installPrompt=null;document.getElementById('pwa-install-btn').style.display='none';});}});
const sidebar = document.getElementById('admin-sidebar');
const toggle = document.getElementById('sidebarToggle');
const closeSidebar = document.getElementById('admin-sidebar-close');
const sidebarBackdrop = document.getElementById('admin-sidebar-backdrop');
const adminMain = document.querySelector('.admin-main');
function setAdminSidebar(open, restoreFocus = true) {
    const mobile = window.innerWidth <= 768;
    open = Boolean(open && mobile);
    sidebar.classList.toggle('open', open);
    toggle.setAttribute('aria-expanded', String(open));
    sidebarBackdrop.hidden = !open;
    document.documentElement.classList.toggle('admin-nav-open', open);
    if (open) {
        sidebar.setAttribute('role','dialog');
        sidebar.setAttribute('aria-modal','true');
        adminMain.setAttribute('inert','');
        closeSidebar.focus({preventScroll:true});
    } else {
        sidebar.removeAttribute('role');
        sidebar.removeAttribute('aria-modal');
        adminMain.removeAttribute('inert');
        if (mobile && restoreFocus) toggle.focus({preventScroll:true});
    }
}
if (toggle && sidebar && closeSidebar && sidebarBackdrop && adminMain) {
    document.documentElement.classList.add('admin-navigation-ready');
    toggle.addEventListener('click', () => setAdminSidebar(!sidebar.classList.contains('open')));
    closeSidebar.addEventListener('click', () => setAdminSidebar(false));
    sidebarBackdrop.addEventListener('click', () => setAdminSidebar(false));
    sidebar.addEventListener('click', event => { if (event.target.closest('a[href]') && window.innerWidth <= 768) setAdminSidebar(false, false); });
    document.addEventListener('keydown', event => {
        if (!sidebar.classList.contains('open') || window.innerWidth > 768) return;
        if (event.key === 'Escape') { event.preventDefault(); setAdminSidebar(false); return; }
        if (event.key !== 'Tab') return;
        const focusable = Array.from(sidebar.querySelectorAll('a[href],button:not(:disabled),summary,[tabindex="0"]'))
            .filter(el => el.getClientRects().length > 0 && !el.closest('[hidden]'));
        const first = focusable[0], last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    });
    window.addEventListener('resize', () => { if (window.innerWidth > 768 && sidebar.classList.contains('open')) setAdminSidebar(false, false); }, {passive:true});
}
document.querySelectorAll('.admin-body .table-wrap').forEach(wrap => {
    const table = wrap.querySelector(':scope > table');
    if (!table) return;
    const labelledBy = wrap.getAttribute('aria-labelledby');
    const heading = (labelledBy && document.getElementById(labelledBy)) ||
        wrap.querySelector('caption') || wrap.parentElement.querySelector(':scope > :is(h2,h3)');
    const tableName = wrap.getAttribute('aria-label') || heading?.textContent.trim() || 'Table';
    const updateScrollAccess = () => {
        const needsScroll = wrap.scrollWidth > wrap.clientWidth + 1;
        if (needsScroll) {
            wrap.tabIndex = 0;
            if (!wrap.hasAttribute('role')) wrap.setAttribute('role', 'region');
            if (!wrap.hasAttribute('aria-label') && !wrap.hasAttribute('aria-labelledby')) {
                wrap.setAttribute('aria-label', tableName);
            }
        }
        wrap.classList.toggle('admin-table-scrolls', needsScroll);
    };
    if ('ResizeObserver' in window) {
        const observer = new ResizeObserver(updateScrollAccess);
        observer.observe(wrap);
        observer.observe(table);
    }
    updateScrollAccess();

    if (wrap.hasAttribute('data-no-table-search')) return;
    const rows = Array.from(table.tBodies).flatMap(body => Array.from(body.rows))
        .filter(row => !row.hidden && row.cells.length > 1);
    if (rows.length < 2) return;
    const searchableRows = rows.map(row => ({ row, text: row.innerText.toLowerCase() }));
    const toolbar = document.createElement('div');
    toolbar.className = 'admin-table-filter';
    const label = document.createElement('label');
    label.textContent = tableName === 'Table' ? 'Filter table rows' : 'Filter ' + tableName + ' rows';
    const search = document.createElement('input');
    search.type = 'search';
    label.append(search);
    const status = document.createElement('p');
    status.setAttribute('role', 'status');
    toolbar.append(label, status);
    wrap.before(toolbar);
    function filterRows() {
        const query = search.value.trim().toLowerCase();
        let shown = 0;
        searchableRows.forEach(({ row, text }) => {
            row.hidden = !text.includes(query);
            if (!row.hidden) shown++;
        });
        status.textContent = shown
            ? shown + ' of ' + rows.length + ' rows shown'
            : 'No matching rows. Clear the filter to see all ' + rows.length + ' rows.';
    }
    search.addEventListener('input', filterRows);
    filterRows();
});
</script>
<button id="pwa-install-btn" class="pwa-install-btn">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    Install App
</button>
<script>
// Saves travel over HTTP to the remote database and take seconds. Without this the
// button stays live, an impatient second click posts the form again, and two records
// are created. Disable on first submit and say what is happening.
(function () {
    document.querySelectorAll('form.admin-form[method="post"]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.saving === '1') { event.preventDefault(); return; }
            var btn = event.submitter || form.querySelector('button[type="submit"], button:not([type])');
            if (!btn) return;
            form.dataset.saving = '1';
            btn.dataset.label = btn.textContent;
            // Named submitters must remain successful controls in the native POST.
            btn.setAttribute('aria-disabled', 'true');
            btn.textContent = 'Saving\u2026';
            // Re-enable if the browser restores the page from cache instead of navigating.
            setTimeout(function () {
                btn.removeAttribute('aria-disabled');
                delete form.dataset.saving;
                if (btn.dataset.label) btn.textContent = btn.dataset.label;
            }, 20000);
        });
    });
})();
</script>
</body>
</html>
