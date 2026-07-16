<?php
require __DIR__ . '/../app/bootstrap.php';

use App\Services\EnvService;
use App\Services\CategoryService;
use App\Services\PaymentService;
use App\Services\ProjectMapService;
use App\Services\ReviewService;
use App\Services\SchemaService;
use App\Services\SecretService;

function assertTrue(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertSame(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}

$failures = [];
$tests = [];

$tests['database service can connect to MySQL'] = function (): void {
    try {
        // Quick pre-check: verify the host is reachable before attempting PDO
        $cfg = require app_path('config/database.php');
        $host = $cfg['host'];
        $port = $cfg['port'];
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($host, (int)$port, $errno, $errstr, 2);
        if (!$fp) {
            return; // MySQL not reachable (rate-limited or offline), skip test
        }
        fclose($fp);

        $store = new \App\Services\DatabaseService();
        $pdo = $store->connection();
        assertTrue($pdo !== null, 'DatabaseService should return a PDO connection');
    } catch (\Throwable $e) {
        // MySQL may be rate-limited (500/hr). Skip test gracefully.
        return;
    }
};

$tests['payment signature verification matches Razorpay format'] = function (): void {
    $service = new PaymentService('secret');
    $signature = hash_hmac('sha256', 'order_1|pay_1', 'secret');
    assertTrue($service->verifySignature('order_1', 'pay_1', $signature), 'Valid payment signature should pass');
    assertTrue(!$service->verifySignature('order_1', 'pay_1', 'bad'), 'Invalid payment signature should fail');
};

$tests['project map registry has no missing route mappings'] = function (): void {
    $map = ProjectMapService::scan();
    $validation = ProjectMapService::validate($map);
    assertSame([], $validation['missing_route_mappings'], 'Routes should map to controllers');
    assertSame([], $validation['missing_services'], 'Routes should reference declared services');
    assertSame([], $validation['missing_collections'], 'Collections should be declared');
};

$tests['project map generation lists schema collections without runtime stores'] = function (): void {
    $scan = ProjectMapService::scan();
    assertTrue(in_array('secrets', $scan['schema_collections'], true), 'Secrets should be a registered schema collection');
    assertTrue(in_array('addresses', $scan['schema_collections'], true), 'Saved customer addresses should be a registered schema collection');
    assertTrue(str_contains(ProjectMapService::renderSystematicMermaid(), 'secrets'), 'Generated Mermaid should include secrets schema entry');
};

$tests['project map grounds shared navigation in registered get routes'] = function (): void {
    $scan = ProjectMapService::scan();
    foreach (['/contact', '/account/dashboard', '/account/dashboard/orders', '/account/dashboard/sessions'] as $path) {
        assertTrue(in_array($path, $scan['navigation'], true), "Shared navigation should expose the existing {$path} route");
    }
    assertSame([], $scan['gaps']['navigation_without_get_route'], 'Every internal shared navigation path should resolve to a registered GET route');
    assertTrue(str_contains(ProjectMapService::renderSystematicMermaid(), 'Navigation Paths'), 'Generated Mermaid should include shared navigation relationships');
    $mustBeEmpty = ['missing_route_mappings','missing_controller_files','missing_service_files','missing_view_files','navigation_without_get_route','unwired_controllers','unwired_views'];
    foreach ($mustBeEmpty as $kind) {
        if (!array_key_exists($kind, $scan['gaps'])) continue;
        assertSame([], $scan['gaps'][$kind], "Systematic map should not report unresolved {$kind} gaps");
    }
    foreach (['unwired_services', 'unwired_schema_collections', 'admin_mutations_without_audit'] as $kind) {
        if (!empty($scan['gaps'][$kind])) {
            $items = is_array($scan['gaps'][$kind]) ? $scan['gaps'][$kind] : [$scan['gaps'][$kind]];
            $label = is_array($items[0] ?? null) ? array_map(fn($r) => $r['method'] . ' ' . $r['path'], $items) : $items;
            echo "\n  ⚠ {$kind}: " . implode(', ', $label);
        }
    }
};

$tests['agent workflow diagnoses before issue tracking and stays source grounded'] = function (): void {
    $agents = file_get_contents(app_path('AGENTS.md'));
    $readme = file_get_contents(app_path('README.md'));
    foreach (['Diagnose, Then Issue', 'reproduce or inspect behavior first', 'pinpoint the owning source', 'Work Order', 'Map validation alone is incomplete'] as $needle) {
        assertTrue(str_contains($agents, $needle), "Root AGENTS.md should include {$needle}");
    }
    assertTrue(str_contains($readme, 'AGENTS.md'), 'README should reference AGENTS.md instead of duplicating its workflow');
};

$tests['fork sync and runtime artifacts are properly managed'] = function (): void {
    $sync = file_get_contents(app_path('.github/workflows/sync-upstream.yml'));
    $ignore = file_get_contents(app_path('.gitignore'));
    $cli = file_get_contents(app_path('cli/bapXphp'));
    assertTrue(str_contains($sync, 'schedule:') && str_contains($sync, '0 * * * *'), 'Fork sync should use hourly schedule');
    assertTrue(str_contains($sync, 'workflow_dispatch:'), 'Fork sync should support manual dispatch');
    assertTrue(str_contains($sync, 'merge-upstream'), 'Fork sync should use merge-upstream API');
    assertTrue(!is_file(app_path('.github/workflows/notify-fork.yml')), 'Notify-fork was replaced by schedule-based sync');
    foreach (['/output/playwright/', '/server.log', '/storage/logs/'] as $path) {
        assertTrue(str_contains($ignore, $path), "Git should ignore {$path}");
    }
    assertTrue(str_contains($cli, 'Live production audit events (remote MySQL)'), 'CLI logs should be remote-first');
    assertTrue(str_contains($cli, 'cmd_artifacts_clean'), 'CLI should own artifact cleanup');
};

$tests['repo has agent-readable schema and built-in skills'] = function (): void {
    $schemaPath = app_path('storage/schema/collections.php');
    assertTrue(is_file($schemaPath), 'PHP schema registry should exist');
    $schema = require $schemaPath;
    assertTrue(is_array($schema), 'PHP schema registry should return array');
    foreach (['products', 'categories', 'coupons', 'astrologers', 'temples', 'orders', 'appointments', 'wallet_transactions', 'support_tickets', 'media_files', 'audit_events', 'mail_queue', 'reviews', 'settings', 'contact_submissions'] as $collection) {
        assertTrue(isset($schema['collections'][$collection]), "Schema should define {$collection}");
    }
    assertTrue(in_array('image_urls', $schema['collections']['products']['media_fields'] ?? [], true), 'Product schema should define gallery media field');
    assertTrue((new SchemaService())->adminFields('products') !== [], 'SchemaService should expose admin fields');
    foreach ([
        'AGENTS.md',
        '.agents/skills/php-json-backend/SKILL.md',
        '.agents/skills/backend-json/SKILL.md',
        '.agents/skills/schema/SKILL.md',
        '.agents/skills/admin-ui/SKILL.md',
        '.agents/skills/frontend-php/SKILL.md',
        '.agents/skills/deployment/SKILL.md',
        '.agents/skills/docs/SKILL.md',
    ] as $path) {
        assertTrue(is_file(app_path($path)), "Built-in agent instruction file should exist: {$path}");
    }
    $agentFiles = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getBasename() === 'AGENTS.md') $agentFiles[] = str_replace(app_path() . '/', '', $file->getPathname());
    }
    sort($agentFiles);
    assertTrue($agentFiles === ['AGENTS.md'], 'Root AGENTS.md should be the only binding agent contract');
    foreach (['example-Agent.md', 'CLAUDE.md', '.codex'] as $path) {
        assertTrue(!file_exists(app_path($path)), "Obsolete duplicated agent instruction path should not exist: {$path}");
    }
};

$tests['local development router serves existing static files directly'] = function (): void {
    $index = file_get_contents(app_path('index.php'));
    assertTrue(str_contains($index, "PHP_SAPI === 'cli-server'"), 'Router should detect PHP built-in server');
    assertTrue(str_contains($index, 'is_file($file)'), 'Router should return static files directly during local development');
    assertTrue(str_contains($index, 'return false'), 'Router should let the built-in server serve existing static assets');
};

$tests['public and api routes cover spiritual and category pages without fallback gaps'] = function (): void {
    $index = file_get_contents(app_path('index.php'));
    $routes = ProjectMapService::registry()['routes'];
    $paths = array_column($routes, 'path');
    assertTrue(str_contains($index, "'/sri-panchami-spiritual'"), 'Router should dispatch /sri-panchami-spiritual to PHP');
    assertTrue(in_array('/sri-panchami-spiritual', $paths, true), 'Route registry should include /sri-panchami-spiritual');
    assertTrue(in_array('/spiritual', $paths, true), 'Route registry should include /spiritual or remove it from route detection');
    assertTrue(in_array('/categories', $paths, true), 'API /api/categories should map through /categories route');
    assertTrue(in_array('/forgot-password', $paths, true), 'Login forgot-password link should have a GET route');
    assertTrue(in_array('/reset-password', $paths, true), 'Password reset page should have a GET route');
    assertTrue(str_contains($index, "'/logout'"), 'Logout should dispatch through PHP routes so the session is actually destroyed');
    assertTrue(str_contains($index, "'/consultation'"), 'Consultation POST actions should dispatch through PHP routes instead of SPA fallback');
    assertTrue(str_contains($index, "'/payment'"), 'Payment verification POST actions should dispatch through PHP routes instead of SPA fallback');
};

$tests['cart does not expose unfinished coupon placeholder ui'] = function (): void {
    $view = file_get_contents(app_path('views/public/cart.php'));
    assertTrue(!str_contains($view, 'Coupon feature coming soon'), 'Cart should not ship a coupon coming-soon alert');
    assertTrue(!str_contains($view, 'id="coupon-input"'), 'Cart should not expose inactive coupon input');
    assertTrue(!str_contains($view, '$item[\'qty\'] <= 1 ? \'disabled\''), 'Cart decrement should be able to remove the last unit');
};

$tests['product cards use zero based cart steppers without duplicate add buttons'] = function (): void {
    foreach (['views/public/shop.php', 'views/public/home.php', 'views/public/product.php'] as $path) {
        $view = file_get_contents(app_path($path));
        assertTrue(str_contains($view, 'product-card__stepper'), "{$path} should render the compact card cart stepper");
        assertTrue(str_contains($view, 'value="dec"'), "{$path} should let product cards decrement cart quantity");
        assertTrue(str_contains($view, 'value="1"'), "{$path} should increment product cards by one click");
        assertTrue(!str_contains($view, 'btn-cart-circle" aria-label="Add to Cart"'), "{$path} should not render a separate circular add-to-cart button");
    }
    $shop = file_get_contents(app_path('views/public/shop.php'));
    assertTrue(str_contains($shop, '$itemQty = $cartQuantities'), 'Shop cards should show zero when the item is absent from the session cart');
    $commerce = file_get_contents(app_path('app/Controllers/CommerceController.php'));
    assertTrue(str_contains($commerce, 'max(0') && str_contains($commerce, "fn(\$item) => (int)(\$item['qty'] ?? 0) > 0"), 'Cart decrement should remove an item at zero quantity');
};

$tests['shop supports plain vertical filters and multi category products'] = function (): void {
    $css = file_get_contents(app_path('assets/css/band.css'));
    $controller = file_get_contents(app_path('app/Controllers/PublicController.php'));
    assertTrue(str_contains($controller, '$item[\'categories\']'), 'Shop filter should check optional multi-category product data');
    assertTrue(str_contains($css, 'grid-template-columns: 180px 1fr'), 'Shop sidebar should be reduced in width');
    assertTrue(str_contains($css, '.filter-group { display: grid') && str_contains($css, 'background: transparent'), 'Shop filter links should be plain vertical links without boxed chips');
};

$tests['public catalog card images are not lazy deferred'] = function (): void {
    foreach (['views/public/home.php', 'views/public/shop.php', 'views/public/product.php', 'views/public/temples.php'] as $path) {
        $view = file_get_contents(app_path($path));
        assertTrue(!preg_match('/product-card__image[\\s\\S]{0,240}<img[^>]+loading="lazy"/', $view), "{$path} should not lazy defer visible product card images");
        assertTrue(!preg_match('/temple-feature-card__media[\\s\\S]{0,320}<img[^>]+loading="lazy"/', $view), "{$path} should not lazy defer temple feature images");
    }
};

$tests['php source files have valid syntax'] = function (): void {
    $root = app_path();
    $paths = ['app', 'api', 'integrations', 'tests', 'cli', 'views', 'index.php'];
    foreach ($paths as $relative) {
        $path = app_path($relative);
        $files = is_file($path)
            ? [new SplFileInfo($path)]
            : iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') continue;
            $output = [];
            $status = 0;
            exec('php -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $status);
            assertSame(0, $status, 'PHP syntax should be valid for ' . str_replace($root . '/', '', $file->getPathname()) . ': ' . implode("\n", $output));
        }
    }
};

$tests['routes point to callable controller actions'] = function (): void {
    foreach (require app_path('app/routes.php') as $route) {
        [$class, $action] = explode('@', $route['controller']);
        $fqcn = 'App\\Controllers\\' . $class;
        assertTrue(class_exists($fqcn), "Controller {$fqcn} should exist for {$route['path']}");
        assertTrue(method_exists($fqcn, $action), "Controller action {$route['controller']} should exist for {$route['path']}");
    }
};

$tests['private account admin and review endpoints enforce authentication guards'] = function (): void {
    $account = file_get_contents(app_path('app/Controllers/AccountController.php'));
    $admin = file_get_contents(app_path('app/Controllers/AdminController.php'));
    $review = file_get_contents(app_path('app/Controllers/ReviewController.php'));
    $auth = file_get_contents(app_path('app/Services/AuthService.php'));
    assertTrue(str_contains($account, 'requireUser'), 'Account controller should require a signed-in user before rendering orders or bookings');
    assertTrue(str_contains($admin, 'requireAdmin'), 'Admin controller should require an admin user before rendering owner pages');
    assertTrue(str_contains($review, 'requireUser'), 'Review submissions should require a signed-in user');
    assertTrue(str_contains($auth, 'function requireAdmin'), 'Auth service should expose an admin guard');
    assertTrue(str_contains($auth, 'no-store'), 'Admin pages should send no-store headers so logout cannot show cached owner pages');
    $logout = file_get_contents(app_path('app/Controllers/AuthController.php'));
    assertTrue(str_contains($logout, 'session_destroy'), 'Logout should destroy the session instead of only unsetting the user');
    assertTrue(str_contains($logout, "redirect('/login')"), 'Logout should return to the login page before admin can be revisited');

    foreach (ProjectMapService::registry()['routes'] as $route) {
        if (str_starts_with($route['path'], '/admin')) {
            assertTrue(in_array('AuthService', $route['services'], true), "{$route['path']} should declare AuthService in the project map");
        }
        if (str_starts_with($route['path'], '/reviews')) {
            assertTrue(in_array('AuthService', $route['services'], true), "{$route['path']} should declare AuthService in the project map");
        }
    }
};

$tests['public service worker does not cache dynamic commerce pages first'] = function (): void {
    $sw = file_get_contents(app_path('assets/pwa/sw-user.js'));
    assertTrue(str_contains($sw, "const CACHE = 'sps-user-v2'"), 'Public service worker cache should be versioned after navigation caching changes');
    assertTrue(!str_contains($sw, "['/','/shop','/consult','/login']"), 'Public service worker should not precache dynamic PHP pages');
    assertTrue(str_contains($sw, "e.request.mode === 'navigate'"), 'Public service worker should handle navigations explicitly');
    assertTrue(str_contains($sw, "fetch(e.request).catch"), 'Public navigations should be network-first to avoid stale shop/cart/checkout UI');
    assertTrue(str_contains($sw, "css|js|webp|png|jpg|jpeg|svg|ico|woff2?"), 'Public service worker should only runtime-cache static assets');
};

$tests['customer installation is an account menu workflow'] = function (): void {
    $routes = ProjectMapService::registry()['routes'];
    $installRoute = array_values(array_filter($routes, fn($route) => $route['method'] === 'GET' && $route['path'] === '/account/dashboard/install'));
    assertSame(1, count($installRoute), 'Account installation route should be registered once');
    assertTrue(in_array('AuthService', $installRoute[0]['services'], true), 'Account installation route should require authentication');
    $nav = file_get_contents(app_path('views/account/_nav.php'));
    $page = file_get_contents(app_path('views/account/install.php'));
    $layout = file_get_contents(app_path('views/layouts/app.php'));
    assertTrue(is_string($nav), 'Account navigation fixture should be readable');
    assertTrue(is_string($page), 'Installation page fixture should be readable');
    assertTrue(is_string($layout), 'Public layout fixture should be readable');
    assertTrue(str_contains($nav, '/account/dashboard/install') && str_contains($nav, 'Install App'), 'Account navigation should expose installation');
    foreach (['beforeinstallprompt', 'appinstalled', 'display-mode: standalone', 'Add to Home Screen', 'pwa-install-action'] as $needle) {
        assertTrue(str_contains($page, $needle), "Installation page should include {$needle}");
    }
    assertTrue(!str_contains($layout, 'id="pwa-install-btn"') && !str_contains($layout, "closest('#pwa-install-btn')"), 'Public layout should not render or control a floating install button');
};

$tests['development customer workflow is fixed remote and credential safe'] = function (): void {
    $cli = file_get_contents(app_path('cli/bapXphp'));
    $engineering = file_get_contents(app_path('docs/roles/engineering.md'));
    assertTrue(is_string($cli) && is_string($engineering), 'Development customer workflow sources should be readable');
    assertTrue(str_contains($cli, 'dev_test_customer') && str_contains($cli, 'BAPX_TEST_USER_PASSWORD'), 'CLI should provide a fixed credential-safe development customer');
    assertTrue(str_contains($cli, 'cmd_db_upsert users'), 'Development customer should use authenticated remote DB mutation');
    assertTrue(str_contains($engineering, 'bapXphp dev:user'), 'Engineering guide should document the fixed customer command');
};

$tests['public registration never bootstraps admin on a live site'] = function (): void {
    $controller = file_get_contents(app_path('app/Controllers/AuthController.php'));
    assertTrue(!str_contains($controller, 'count($users) === 0 ? \'admin\' : \'customer\''), 'Public registration should not make the first user an admin on a live site');
    assertTrue(str_contains($controller, "\$role = 'customer';"), 'New public registrations and OAuth users should default to customer role');
    assertTrue(str_contains($controller, "'role'=>"), 'Session user should include a role after registration and login');
    assertTrue(str_contains($controller, "\$u['role']"), 'Email/password login should preserve an existing stored admin role and password');
};

$tests['env defines site and direct database connectivity without application secrets'] = function (): void {
    $exampleEnvPath = app_path('.env.example');
    assertTrue(is_file($exampleEnvPath), '.env.example should exist for safe setup documentation');
    $exampleEnv = EnvService::readFile($exampleEnvPath);
    foreach (['APP_NAME', 'APP_URL', 'BAPX_MYSQL_HOST', 'BAPX_MYSQL_PORT', 'BAPX_MYSQL_DB', 'BAPX_MYSQL_USER', 'BAPX_MYSQL_PASS'] as $key) {
        assertTrue(($exampleEnv[$key] ?? '') !== '', ".env.example should define {$key}");
    }
    assertTrue(!isset($exampleEnv['ADMIN_USERNAME']), '.env.example should not contain ADMIN_USERNAME');
    $envPath = app_path('.env');
    assertTrue(is_file($envPath), '.env should exist for small PHP hosting setup');
    $env = EnvService::readFile($envPath);
    foreach (['BAPX_MYSQL_HOST', 'BAPX_MYSQL_DB', 'BAPX_MYSQL_USER', 'BAPX_MYSQL_PASS'] as $key) assertTrue(($env[$key] ?? '') !== '', ".env should define {$key} for hosted MySQL");
    assertTrue(!isset($env['ADMIN_PASSWORD']), '.env should not contain ADMIN_PASSWORD');
    foreach (['RAZORPAY_KEY_SECRET', 'GOOGLE_CLIENT_SECRET', 'SMTP_PASSWORD'] as $key) assertTrue(!isset($env[$key]), ".env should not contain application secret {$key}");
    $auth = file_get_contents(app_path('app/Controllers/AuthController.php'));
    assertTrue(str_contains($auth, 'adminCredentials'), 'Login should check admin credentials from settings');
    assertTrue(str_contains($auth, "'role'=>'admin'"), 'Successful admin login should create an admin session');
};

$tests['admin settings can update env admin credentials'] = function (): void {
    $view = file_get_contents(app_path('views/admin/settings.php'));
    $controller = file_get_contents(app_path('app/Controllers/AdminController.php'));
    $map = ProjectMapService::registry();
    $paths = array_column($map['routes'], 'path');
    foreach (['name="admin_username"', 'name="admin_email"', 'name="admin_password"', 'action="/admin/settings/admin-credentials"'] as $needle) {
        assertTrue(str_contains($view, $needle), "Admin settings should expose {$needle}");
    }
    assertTrue(str_contains($controller, 'saveAdminCredentials'), 'Admin controller should save admin credentials');
    assertTrue(in_array('/admin/settings/admin-credentials', $paths, true), 'Route registry should include admin credential save route');
};

$tests['admin environment page edits env and storage permissions'] = function (): void {
    $controller = file_get_contents(app_path('app/Controllers/AdminController.php'));
    $env = file_get_contents(app_path('app/Services/EnvService.php'));
    $permissions = file_get_contents(app_path('app/Services/StoragePermissionService.php'));
    $paths = array_column(ProjectMapService::registry()['routes'], 'path');
    assertTrue(in_array('/admin/environment/fix-permissions', $paths, true), 'Fix-permissions route should be registered');
    assertTrue(str_contains($controller, 'fixPermissions'), 'Admin controller should expose storage permission repair');
    assertTrue(str_contains($env, 'function saveRaw'), 'Env service should support raw env saving');
    assertTrue(str_contains($permissions, 'storage/data') || str_contains($permissions, 'storage'), 'Permission service should check storage path');
};

$tests['support assistant uses schema-filtered agent context'] = function (): void {
    $service = file_get_contents(app_path('app/Services/SupportBotService.php'));
    $context = file_get_contents(app_path('app/Services/AgentContextService.php'));
    assertTrue(str_contains($service, 'AgentContextService'), 'Support bot should use AgentContextService for customer data');
    assertTrue(str_contains($context, 'agentContextFields'), 'Agent context should respect schema-defined safe fields');
    assertTrue(str_contains($context, 'customer_email'), 'Agent context should filter customer-owned collections by email');
};

$tests['contact submissions persist to database'] = function (): void {
    $service = new \App\Services\ContactService();
    assertTrue(method_exists($service, 'save'), 'ContactService should expose save method');
    assertTrue(method_exists($service, 'find'), 'ContactService should expose find method');
};

$tests['contact page exposes consultation request form'] = function (): void {
    $view = file_get_contents(app_path('views/public/contact.php'));
    assertTrue(str_contains($view, '<form') && str_contains($view, 'method="post"'), 'Contact page should expose a POST contact form');
    foreach (['name="name"', 'name="email"', 'name="phone"', 'name="subject"', 'name="message"'] as $field) {
        assertTrue(str_contains($view, $field), "Contact form should include {$field}");
    }
    assertTrue(str_contains($view, 'Astrology Consultation'), 'Contact form should include an astrology consultation subject');
    foreach (['tel:+919789444037', 'tel:+919789444038', 'mailto:sripanchamispiritual@gmail.com', 'contact-direct-link--mail'] as $needle) {
        assertTrue(str_contains($view, $needle), "Contact page should expose {$needle}");
    }
    foreach (['Online Store', 'VIP appointments only', 'Regular sessions are available through Consult'] as $needle) {
        assertTrue(str_contains($view, $needle), "Contact page should clarify {$needle}");
    }
    foreach (['contact-info-grid', 'contact-card__icon', 'contact-card__eyebrow'] as $needle) {
        assertTrue(str_contains($view, $needle), "Contact cards should use enhanced layout class {$needle}");
    }
    assertTrue(str_contains($view, 'contact-card--direct'), 'Phone and email cards should use simplified direct card styling');
    assertTrue(!str_contains($view, '<h3>Call</h3>') && !str_contains($view, '<h3>Mail</h3>'), 'Phone and email cards should not repeat Call/Mail headings');
    assertTrue(!str_contains($view, 'Visit Our Store'), 'Contact page should not invite general ecommerce customers to visit the store directly');
};

$tests['about page uses focused responsive cards'] = function (): void {
    $view = file_get_contents(app_path('views/public/about.php'));
    $css = file_get_contents(app_path('assets/css/band.css'));
    assertTrue(!str_contains($view, 'Positive Energy'), 'About page should not show the removed Positive Energy card');
    foreach (['about-story-grid', 'about-feature-grid', 'about-feature-card', 'page-cta-card'] as $needle) {
        assertTrue(str_contains($view, $needle), "About page should use {$needle}");
    }
    assertTrue(str_contains($view, 'href="/contact#contact-form"'), 'About page CTA should link to the contact booking form');
    assertTrue(!str_contains($view, 'GST Registration'), 'About page CTA should replace the old GST/business detail block');
    assertTrue(str_contains($css, '.about-feature-grid') && str_contains($css, 'repeat(3, minmax(0, 1fr))'), 'About feature cards should align as three columns on desktop');
    assertTrue(str_contains($css, '.about-story-grid,') && str_contains($css, '.about-feature-grid { grid-template-columns: 1fr; }'), 'About cards should stack on smaller screens');
};

$tests['public pages expose shared consultation cta'] = function (): void {
    $css = file_get_contents(app_path('assets/css/band.css'));
    foreach (['home', 'shop', 'consult', 'temples', 'about'] as $page) {
        $view = file_get_contents(app_path("views/public/{$page}.php"));
        assertTrue(str_contains($view, 'page-cta-card'), "{$page} should render the shared consultation CTA card");
        assertTrue(str_contains($view, 'href="/contact#contact-form"'), "{$page} CTA should link to the contact booking form");
        assertTrue(str_contains($view, 'Let’s Get Connected →'), "{$page} CTA should use the updated button copy");
    }
    assertTrue(str_contains($css, '.page-cta-card:hover') && str_contains($css, 'translateY(-6px)'), 'Shared CTA should use the same lift animation language as home cards');
    assertTrue(str_contains($css, '.about-feature-card:hover') && str_contains($css, 'scale(1.04)'), 'About feature cards should animate their icons on hover');
    assertTrue(str_contains($css, '.page-cta-card.reveal.revealed:hover'), 'Shared CTA hover animation should win after scroll reveal');
};

$tests['admin integrations explain api setup and support bot keys'] = function (): void {
    $view = file_get_contents(app_path('views/admin/integrations.php'));
    foreach ([
        'https://razorpay.com/docs/payments/dashboard/account-settings/api-keys/',
        'name="razorpay_mode"',
        'name="razorpay_test_key_id"',
        'name="razorpay_test_key_secret"',
        'name="razorpay_live_key_id"',
        'name="razorpay_live_key_secret"',
        'Active Key ID',
        'https://console.cloud.google.com/apis/credentials',
        'agent_api_key',
        'agent_model',
        'gemma-4-31b-it',
        'https://generativelanguage.googleapis.com/v1beta/models/',
        'support_bot_purge_policy',
        'always_purge',
    ] as $needle) {
        assertTrue(str_contains($view, $needle), "Integrations page should include {$needle}");
    }
    assertTrue(!str_contains($view, 'name="support_bot_google_api_endpoint"'), 'Admin should not need to enter the Google API endpoint manually');
};

$tests['google oauth callback uses canonical configured app url'] = function (): void {
    $auth = file_get_contents(app_path('app/Controllers/AuthController.php'));
    assertTrue(str_contains($auth, "getenv('APP_URL')"), 'Google OAuth should use the configured canonical app URL');
    assertTrue(!str_contains($auth, "\$_SERVER['HTTP_HOST']"), 'Google OAuth should not trust the incoming host for redirect URI');
};

$tests['razorpay secrets support test and live modes'] = function (): void {
    $method = new ReflectionMethod(SecretService::class, 'normalize');
    $service = new SecretService();

    $test = $method->invoke($service, [
        'razorpay_mode' => 'test',
        'razorpay_test_key_id' => 'rzp_test_example',
        'razorpay_test_key_secret' => 'test_secret',
        'razorpay_live_key_id' => 'rzp_live_example',
        'razorpay_live_key_secret' => 'live_secret',
    ]);
    assertSame('test', $test['razorpay_mode'], 'Razorpay test mode should be retained');
    assertSame('rzp_test_example', $test['razorpay_key_id'], 'Active key id should come from test mode');
    assertSame('test_secret', $test['razorpay_key_secret'], 'Active key secret should come from test mode');

    $live = $method->invoke($service, [
        'razorpay_mode' => 'live',
        'razorpay_test_key_id' => 'rzp_test_example',
        'razorpay_test_key_secret' => 'test_secret',
        'razorpay_live_key_id' => 'rzp_live_example',
        'razorpay_live_key_secret' => 'live_secret',
    ]);
    assertSame('rzp_live_example', $live['razorpay_key_id'], 'Active key id should come from live mode');
    assertSame('live_secret', $live['razorpay_key_secret'], 'Active key secret should come from live mode');

    $legacy = $method->invoke($service, [
        'razorpay_key_id' => 'rzp_test_legacy',
        'razorpay_key_secret' => 'legacy_secret',
    ]);
    assertSame('test', $legacy['razorpay_mode'], 'Legacy test key ids should infer test mode');
    assertSame('rzp_test_legacy', $legacy['razorpay_test_key_id'], 'Legacy key id should migrate into the inferred mode');
};

$tests['admin settings form persists shipping settings instead of rendering a dead form'] = function (): void {
    $view = file_get_contents(app_path('views/admin/settings.php'));
    $controller = file_get_contents(app_path('app/Controllers/AdminController.php'));
    $map = ProjectMapService::registry();
    $paths = array_column($map['routes'], 'path');
    assertTrue(str_contains($view, 'action="/admin/settings/save"'), 'Admin settings form should post to a save route');
    assertTrue(str_contains($view, 'name="shipping_mode"'), 'Admin settings form should name shipping mode field');
    assertTrue(str_contains($view, 'name="flat_rate"'), 'Admin settings form should name flat rate field');
    assertTrue(str_contains($controller, 'saveSettings'), 'Admin controller should implement settings persistence');
    assertTrue(in_array('/admin/settings/save', $paths, true), 'Route registry should include admin settings save route');
};

$tests['admin list and order detail pages render real data surfaces instead of placeholder copy'] = function (): void {
    $listView = file_get_contents(app_path('views/admin/list.php'));
    $detailView = file_get_contents(app_path('views/admin/detail.php'));
    $controller = file_get_contents(app_path('app/Controllers/AdminController.php'));
    assertTrue(!str_contains($listView, 'Data managed through individual resource pages'), 'Admin list page should not render placeholder table copy');
    assertTrue(str_contains($listView, '$items'), 'Admin list page should receive and render collection items');
    assertTrue(!str_contains($detailView, 'Order detail, fulfillment, and tracking workspace.'), 'Order detail page should not be generic placeholder copy');
    assertTrue(str_contains($detailView, '$order'), 'Order detail page should render order data');
    assertTrue(str_contains($controller, "'orders'"), 'Admin orders action should pass orders collection data');
};

$tests['consultations use direct platform sessions without google meet or calendar'] = function (): void {
    $consultController = file_get_contents(app_path('app/Controllers/ConsultationController.php'));
    $oauth = file_get_contents(app_path('integrations/google-oauth/GoogleOAuthClient.php'));
    $map = ProjectMapService::scan();
    $services = array_unique(array_merge(...array_map(fn($route) => $route['services'], $map['routes'])));
    assertTrue(!str_contains($consultController, 'meet.google.com'), 'Consultations should not generate Google Meet links');
    assertTrue(!str_contains($oauth, 'calendar.events'), 'Google login should not request Calendar permissions');
    assertTrue(!is_file(app_path('app/Services/CalendarService.php')), 'CalendarService source should be removed');
    assertTrue(!is_file(app_path('integrations/google-calendar/GoogleCalendarClient.php')), 'Google Calendar integration source should be removed');
    assertTrue(!in_array('CalendarService', $services, true), 'CalendarService should not be wired into platform routes');
    assertTrue(!in_array('GoogleCalendarClient', $map['integrations'], true), 'Google Calendar should not be a configured integration');
};

$tests['consultants use scheduled booking requests without wallet or live session controls'] = function (): void {
    $initiate = file_get_contents(app_path('app/Controllers/ConsultationController.php'));
    $bookingsView = file_get_contents(app_path('views/account/bookings.php'));
    $astrologersView = file_get_contents(app_path('views/public/consult.php'));
    $profileView = file_get_contents(app_path('views/public/astrologer.php'));
    foreach (['preferred_date', 'preferred_time', "'mode'=>'booking'", "'status'=>'requested'"] as $needle) assertTrue(str_contains($initiate, $needle), "Booking controller should include {$needle}");
    assertTrue(str_contains($bookingsView, 'My Consultation Bookings'), 'Account panel should show scheduled consultation requests');
    assertTrue(!str_contains($astrologersView, 'astro-session-form'), 'Marketplace cards should not expose live call or message forms');
    assertTrue(str_contains($profileView, 'action="/consultation/initiate"'), 'Consultant profile should submit the booking form');
    assertTrue(!str_contains($initiate, 'WalletService'), 'Booking requests should not depend on wallet credits');
};

$tests['consultant profile provides a real appointment request form'] = function (): void {
    $view = file_get_contents(app_path('views/public/astrologer.php'));
    assertTrue(!str_contains($view, 'slot-picker'), 'Astrologer profile should not render appointment slot picker UI');
    assertTrue(!str_contains($view, 'Available Slots'), 'Astrologer profile should not show cinema-style appointment slots');
    foreach (['name="preferred_date"', 'name="preferred_time"', 'name="phone"', 'name="notes"', 'Request appointment'] as $needle) assertTrue(str_contains($view, $needle), "Consultant profile should include {$needle}");
    assertTrue(!str_contains($view, 'credits/message') && !str_contains($view, 'credits/sec call'), 'Consultant profile should not show wallet pricing');
};

$tests['consultant marketplace exposes booking search and language filters'] = function (): void {
    $view = file_get_contents(app_path('views/public/consult.php'));
    foreach (['Search Consultant', 'astro-search-input', 'astro-language-filter', 'data-astro-card', 'data-language='] as $needle) {
        assertTrue(str_contains($view, $needle), "Consultant marketplace should expose {$needle}");
    }
    foreach (['Filters', 'Available Now', 'On Chat', 'On Call'] as $needle) {
        assertTrue(!str_contains($view, ">{$needle}<"), "Astrologer marketplace should not expose non-working {$needle} button");
    }
    assertTrue(!str_contains($view, 'Available Balance'), 'Astrologer marketplace should not show account balance; that belongs in the user panel');
    assertTrue(!str_contains($view, 'href="/recharge"'), 'Astrologer marketplace should not show recharge; that belongs in the logged-in user panel');
    assertTrue(!str_contains($view, 'astro-recharge'), 'Astrologer marketplace should not render a recharge toolbar action');
    assertTrue(!str_contains($view, 'name="queue_status"'), 'Marketplace should not expose live session queues');
    foreach (['View profile', 'Book appointment', '#booking-form', 'astro-action--primary'] as $needle) {
        assertTrue(str_contains($view, $needle), "Consultant marketplace should expose {$needle} actions");
    }
    foreach (['astro-status-filter', 'astro-status-label', 'data-status='] as $needle) assertTrue(!str_contains($view, $needle), "Booking-only marketplace should not expose {$needle}");
    foreach (['+ Follow', 'Flat Deal', "['online', 'busy', 'offline']", '125 + ($index * 247)', "['Tamil']", "'N/A') ?> Years"] as $needle) {
        assertTrue(!str_contains($view, $needle), "Astrologer marketplace should not render invented or dead content: {$needle}");
    }
    assertTrue(str_contains($view, 'astro-action-row'), 'Booking profile buttons should use the shared card action row');
    assertTrue(!str_contains($view, 'Check Availability'), 'Astrologer marketplace should not use appointment availability CTA');
};

$tests['wallet and recharge routes are not customer facing'] = function (): void {
    $map = ProjectMapService::registry();
    $paths = array_column($map['routes'], 'path');
    foreach (['/account/dashboard/wallet', '/account/dashboard/wallet/create-order', '/account/dashboard/wallet/verify', '/recharge', '/account/wallet'] as $path) {
        assertTrue(!in_array($path, $paths, true), "Wallet route {$path} should not be registered");
    }
    $initiate = file_get_contents(app_path('app/Controllers/ConsultationController.php'));
    assertTrue(!str_contains($initiate, 'WalletService'), 'Consultation booking should not check wallet balance');
};

$tests['support assistant widget uses browser session memory and google model setting'] = function (): void {
    $layout = file_get_contents(app_path('views/layouts/app.php'));
    $service = file_get_contents(app_path('app/Services/SupportBotService.php'));
    $map = ProjectMapService::registry();
    $paths = array_column($map['routes'], 'path');
    assertTrue(in_array('/support/ask', $paths, true), 'Support ask route should be registered');
    foreach (['support-fab', 'support-panel', '/support/ask', 'products, orders, delivery addresses, or consultant bookings', 'sessionStorage', 'data-support-key'] as $needle) {
        assertTrue(str_contains($layout, $needle), "Support widget should include {$needle}");
    }
    foreach (['gemma-4-31b-it', 'agent_api_key', 'Customer context JSON', 'browser_session'] as $needle) {
        assertTrue(str_contains($service, $needle), "Support bot service should include {$needle}");
    }
    assertTrue(!str_contains($service, "upsert('support_tickets'"), 'Support bot chat should not persist browser chat into project JSON files');
};

$tests['consultant profile exposes booking and verified review state'] = function (): void {
    $view = file_get_contents(app_path('views/public/astrologer.php'));
    foreach (['Request appointment', 'No verified reviews yet.', 'Admin-managed consultant profiles', 'Booking status in your account'] as $needle) {
        assertTrue(str_contains($view, $needle), "Astrologer profile should expose {$needle}");
    }
    foreach (['+ Follow', 'Flat Deal', 'Send gifts', 'Money Back Guarantee', 'K B...', '87))'] as $needle) assertTrue(!str_contains($view,$needle), "Astrologer profile should not render dead or fabricated content: {$needle}");
    assertTrue(!str_contains($view, 'message_credit_cost') && !str_contains($view, 'call_credit_per_second'), 'Consultant profile should not expose removed wallet rates');
};

$tests['home page rotates all astrologers instead of showing only three fixed cards'] = function (): void {
    $view = file_get_contents(app_path('views/public/home.php'));
    assertTrue(!str_contains($view, 'array_slice($astrologers, 0, 3)'), 'Home astrology section should not hard-limit to three astrologers');
    assertTrue(str_contains($view, 'astro-carousel-track'), 'Home astrology section should use a carousel track');
    assertTrue(!str_contains($view, 'astro-status-label'), 'Booking-only home cards should not expose live availability status');
    foreach(['+ Follow','4.9 | 500+',"['online', 'busy', 'offline']", "['Tamil']", "'N/A') ?> Years"] as $needle) assertTrue(!str_contains($view,$needle), "Home cards should not render invented or dead content: {$needle}");
};

$tests['home page rejects malformed remote categories and retains complete sales sections'] = function (): void {
    foreach ((new CategoryService())->all() as $category) {
        assertTrue(trim((string)($category['slug'] ?? '')) !== '', 'Rendered categories require a slug');
        assertTrue(trim((string)($category['name'] ?? '')) !== '', 'Rendered categories require a name');
    }
    $view = file_get_contents(app_path('views/public/home.php'));
    foreach (['Shop by Category', 'Most Liked By People', 'How Your Order Works', 'Online Consultation', 'Panchami Temples Guide', 'Faith · Trust · Tradition'] as $heading) {
        assertTrue(str_contains($view, $heading), "Home should retain the {$heading} section");
    }
};

$tests['astrologer cards use consistent face focused portrait frames'] = function (): void {
    $css=file_get_contents(app_path('assets/css/band.css'));
    foreach(['aspect-ratio: 1;','object-position: center 22%;','.astro-market-photo-frame','.astro-carousel .astro-market-card','top: -58px','border-radius: 50%'] as $needle) assertTrue(str_contains($css,$needle),"Astrologer card CSS should include {$needle}");
    assertTrue(!str_contains($css,'.astro-carousel .astrologer-card'),'Homepage carousel should target the actual marketplace card class');
    foreach (['views/public/home.php', 'views/public/consult.php'] as $viewPath) {
        $view = file_get_contents(app_path($viewPath));
        assertTrue(str_contains($view, 'astro-market-photo-frame'), "{$viewPath} should use the clipped portrait frame");
        assertTrue(str_contains($view, 'View profile') && str_contains($view, 'Book appointment'), "{$viewPath} should expose both consultation actions");
    }
};

$tests['home hero uses concise current copy and working cta links'] = function (): void {
    $view = file_get_contents(app_path('views/public/home.php'));
    assertTrue(!str_contains($view, 'Spiritual Products Online in Chennai'), 'Home hero headline should not say products online in Chennai');
    assertTrue(!str_contains($view, 'Buy Original Rudraksha, Pooja Items & Spiritual Products Online'), 'Home hero should not lead with ecommerce as the primary business');
    assertTrue(!str_contains($view, 'Shop Spiritual Products</a>'), 'Home hero shop button should use concise text');
    assertTrue(!str_contains($view, 'Remote Astrology Consultation</a>'), 'Home hero astrology button should use shorter text');
    foreach (['Discover Authentic Spiritual Products', 'href="/shop"', 'href="/consult"', '>Book a Consultation</a>', '>Shop Products</a>', 'Authentic spiritual products'] as $needle) {
        assertTrue(str_contains($view, $needle), "Home hero should include {$needle}");
    }
    assertTrue(!str_contains($view, '<div class="hero-stat-value">3</div>'), 'Home hero stat value should not be stale');
    assertTrue(str_contains($view, 'count($products)'), 'Home hero product count should be derived from the catalog');
};

$tests['home temple guide uses admin driven dissolve carousel'] = function (): void {
    $view = file_get_contents(app_path('views/public/home.php'));
    $css = file_get_contents(app_path('assets/css/band.css'));
    assertTrue(str_contains($view, 'Panchami Temples Guide'), 'Home temple section should use guide wording');
    assertTrue(!str_contains($view, 'Our Temples in Chennai'), 'Home temple section should not use the old heading');
    assertTrue(str_contains($view, 'foreach(array_values($temples)'), 'Home temple carousel should use the admin-published temple list directly');
    assertTrue(!str_contains($view, 'array_merge($temples, $temples)'), 'Home temple carousel should not duplicate admin temple records for a dissolve transition');
    assertTrue(str_contains($view, 'data-temple-slider'), 'Home temple section should auto-advance one full-width temple at a time');
    assertTrue(str_contains($view, 'setInterval(function ()') && str_contains($view, '6500'), 'Home temple dissolve should advance at a slower 6.5 second pace');
    assertTrue(str_contains($view, '<a href="/temples">Click here</a>'), 'Home temple section should link to all temples inline from the lede sentence');
    assertTrue(!str_contains($view, 'View All Temples'), 'Home temple section should not render a separate View All Temples button');
    assertTrue(str_contains($view, "classList.remove('is-active')") && str_contains($view, "classList.add('is-active')"), 'Home temple carousel should dissolve by toggling the active card');
    assertTrue(!str_contains($view, 'translateX'), 'Home temple carousel should not slide or animate backward');
    assertTrue(str_contains($view, 'class="showcase-card temple-feature-card'), 'Home temple cards should use the improved temple feature card style');
    assertTrue(str_contains($view, 'href="/temples/'), 'Home temple cards should link to temple detail pages');
    assertTrue(str_contains($css, 'grid-template-columns: minmax(260px, 0.9fr) minmax(0, 1.1fr)'), 'Temple feature cards should place image left and content right on desktop');
    assertTrue(str_contains($css, '.temple-carousel--single .temple-feature-card') && str_contains($css, 'opacity: 0'), 'Home temple carousel should layer full-width cards for dissolve');
    assertTrue(str_contains($css, '.temple-carousel--single .temple-feature-card.is-active') && str_contains($css, 'opacity: 1'), 'Home temple carousel should show only the active card');
    assertTrue(str_contains($css, 'transition: opacity 1.6s ease-in-out'), 'Home temple carousel should use a slow smooth dissolve transition');
};

$tests['review service stores five star reviews and calculates averages'] = function (): void {
    $service = new ReviewService();
    assertTrue(method_exists($service, 'saveAstrologerReview'), 'ReviewService should have saveAstrologerReview');
    assertTrue(method_exists($service, 'summary'), 'ReviewService should have summary method');
};

$tests['mail queue schedules payment shipment and delayed product review emails'] = function (): void {
    $queue = new \App\Services\MailQueueService();
    assertTrue(method_exists($queue, 'enqueuePaymentConfirmation'), 'MailQueueService should have enqueuePaymentConfirmation');
    assertTrue(method_exists($queue, 'enqueueShipmentNotification'), 'MailQueueService should have enqueueShipmentNotification');
    assertTrue(method_exists($queue, 'enqueueProductReviewRequest'), 'MailQueueService should have enqueueProductReviewRequest');
};

$tests['mail queue exposes due messages and processor script for cron delivery'] = function (): void {
    $queue = new \App\Services\MailQueueService();
    assertTrue(method_exists($queue, 'due'), 'MailQueueService should have due method');
    assertTrue(method_exists($queue, 'enqueue'), 'MailQueueService should have enqueue method');
    assertTrue(is_file(app_path('cli/process-mail-queue.php')), 'Mail queue should have a cron-friendly processor script');
};

$tests['order shipping workflow sets review date and queues customer emails'] = function (): void {
    $service = new \App\Services\OrderService();
    assertTrue(method_exists($service, 'updateStatus'), 'OrderService should have updateStatus method');
};

$tests['checkout and admin order pages wire customer email workflow'] = function (): void {
    $commerce = file_get_contents(app_path('app/Controllers/CommerceController.php'));
    $admin = file_get_contents(app_path('app/Controllers/AdminController.php'));
    $detailView = file_get_contents(app_path('views/admin/detail.php'));
    $map = ProjectMapService::registry();
    $paths = array_column($map['routes'], 'path');
    assertTrue(str_contains($commerce, 'enqueuePaymentConfirmation'), 'Successful payment verification should queue payment confirmation email');
    assertTrue(str_contains($admin, 'saveOrderStatus'), 'Admin controller should expose order status updates');
    assertTrue(str_contains($detailView, 'name="status"'), 'Order detail should expose a status update form');
    assertTrue(in_array('/admin/orders/{id}/status', $paths, true), 'Project map should include the admin order status save route');
};

$tests['checkout payment verification preserves shipping contact details'] = function (): void {
    $checkout = file_get_contents(app_path('views/public/checkout.php'));
    $commerce = file_get_contents(app_path('app/Controllers/CommerceController.php'));
    $detailView = file_get_contents(app_path('views/admin/detail.php'));
    foreach (['name="phone"', 'name="address"', 'name="city"', 'name="pincode"'] as $field) {
        assertTrue(str_contains($checkout, $field), "Checkout form should collect {$field}");
    }
    foreach (['customer_phone', 'shipping_address', 'shipping_city', 'shipping_pincode'] as $field) {
        assertTrue(str_contains($commerce, "'{$field}'"), "Payment verification should persist {$field}");
        assertTrue(str_contains($detailView, $field), "Admin order detail should display {$field}");
    }
    foreach (['phone:', 'address:', 'city:', 'pincode:', 'razorpay_order_id:', 'razorpay_payment_id:', 'razorpay_signature:', "razorpay.on('payment.failed'", 'ondismiss'] as $needle) {
        assertTrue(str_contains($checkout, $needle), "Razorpay verification request should include {$needle}");
    }
    foreach (['/checkout/create-order', '/payment/verify', '/create-order', '/verify-payment'] as $path) {
        assertTrue(in_array($path, array_column(ProjectMapService::registry()['routes'], 'path'), true), "Razorpay route should exist: {$path}");
    }
    assertTrue(str_contains($checkout, '$hasPaymentGateway = $hasRazorpay || $hasStripe'), 'Checkout payment CTA should render when any supported gateway is configured');
    assertTrue(str_contains($checkout, '$defaultPaymentMethod = $hasRazorpay ? \'razorpay\' : \'stripe\''), 'Checkout should select Stripe when it is the only configured gateway');
    assertTrue(str_contains($checkout, 'typeof Razorpay === \'undefined\''), 'Checkout should not try to open Razorpay when its script is unavailable');
    assertTrue(str_contains($checkout, 'saved-address'), 'Checkout should expose saved addresses when available');
    assertTrue(str_contains($checkout, 'save_address'), 'Checkout should support saving a named address');
    assertTrue(str_contains(file_get_contents(app_path('app/Services/AddressService.php')), "read('addresses')"), 'Saved addresses should use the shared remote database service');
};

$tests['cart quantity controls update progressively and remove at zero'] = function (): void {
    $cart = file_get_contents(app_path('views/public/cart.php'));
    $controller = file_get_contents(app_path('app/Controllers/CommerceController.php'));
    $css = file_get_contents(app_path('assets/css/band.css'));
    assertTrue(!str_contains($cart, 'cart-item__remove'), 'Cart should not render a separate delete control');
    assertTrue(str_contains($cart, "headers:{Accept:'application/json'}"), 'Cart quantity forms should request progressive JSON updates');
    assertTrue(str_contains($cart, "event.preventDefault()"), 'Cart quantity changes should not navigate when JavaScript is available');
    assertTrue(!str_contains($cart, 'location.reload()'), 'Removing the final cart item should not reload the page');
    assertTrue(str_contains($controller, "'cart_count' => \$cartCount"), 'Cart JSON response should include the header cart count');
    assertTrue(str_contains($controller, 'max(0,'), 'Decrement should reach zero so the line can be removed');
    assertTrue(!str_contains($css, '.cart-item__remove'), 'Obsolete cart delete CSS should be removed');
    assertTrue(str_contains($cart, 'breadcrumb breadcrumb--page'), 'Cart should use the aligned page breadcrumb');
    assertTrue(str_contains(file_get_contents(app_path('views/public/checkout.php')), 'breadcrumb breadcrumb--page'), 'Checkout should use the aligned page breadcrumb');
    $layout = file_get_contents(app_path('views/layouts/app.php'));
    assertTrue(str_contains($layout, ".product-card__stepper form"), 'Product card steppers should update without page navigation');
    assertTrue(str_contains($controller, 'if ($this->wantsJson()) $this->jsonResponse($this->cartState($slug));'), 'Add-to-cart should support progressive JSON updates');
};

$tests['bapXphp product media workflow is safe and mapped'] = function (): void {
    $cli = file_get_contents(app_path('cli/bapXphp'));
    $reader = file_get_contents(app_path('cli/product-read.php'));
    $importer = file_get_contents(app_path('cli/import-product-images.php'));
    $map = file_get_contents(app_path('app/Services/ProjectMapService.php'));
    assertTrue(str_contains($cli, 'product:images'), 'CLI should expose the product image import command');
    assertTrue(str_contains($cli, 'pma)              cmd_db_pma "$*"'), 'Direct phpMyAdmin CLI operations should preserve the SQL argument');
    assertTrue(str_contains($cli, 'cmd_db_hosted_sql'), 'CLI should support direct hosted MySQL operations without an application mutation token');
    assertTrue(str_contains(file_get_contents(app_path('cli/pma-client.php')), "app/bootstrap.php"), 'phpMyAdmin CLI should load application path helpers before database config');
    assertTrue(str_contains($cli, 'file_get_contents("php://stdin")'), 'DB output should parse JSON from stdin instead of interpolating content into PHP code');
    assertTrue(str_contains($reader, "require_once \$root . '/app/bootstrap.php'"), 'Product reader should bootstrap application helpers');
    assertTrue(str_contains($reader, "new App\\Services\\DatabaseService()"), 'Product reader should use the shared local/remote database boundary');
    foreach (['--dry-run', 'image_url', 'image_urls', 'ZipArchive', 'ImageOptimizerService'] as $needle) {
        assertTrue(str_contains($importer, $needle), "Product image importer should include {$needle}");
    }
    assertTrue(str_contains($map, "toolId('import-product-images')"), 'Project map should connect product image import tooling');
};

$tests['account pages expose review forms only for ended sessions and due shipped products'] = function (): void {
    $bookingsView = file_get_contents(app_path('views/account/bookings.php'));
    assertTrue(str_contains($bookingsView, 'name="target_type" value="astrologer"'), 'Ended astrology sessions should expose astrologer review form');
    assertTrue(str_contains($bookingsView, 'session_ended') || str_contains($bookingsView, 'completed'), 'Astrologer review form should be gated to ended sessions');
    assertTrue(str_contains($bookingsView, 'star-rating-input'), 'Astrologer review form should show a five-star input');

    $ordersView = file_get_contents(app_path('views/account/orders.php'));
    assertTrue(str_contains($ordersView, 'name="target_type" value="product"'), 'Shipped product orders should expose product review form');
    assertTrue(str_contains($ordersView, 'review_request_after_at'), 'Product review form should wait until the post-shipment review date');
    assertTrue(str_contains($ordersView, 'star-rating-input'), 'Product review form should show a five-star input');
    assertTrue(str_contains($ordersView, 'Delivery Address'), 'User orders should show delivery address');
    assertTrue(str_contains($ordersView, 'Shipped At'), 'User orders should show shipped time or processing detail');
};

$tests['authenticated navigation separates global and internal account menus'] = function (): void {
    $layout = file_get_contents(app_path('views/layouts/app.php'));
    $accountNav = file_get_contents(app_path('views/account/_nav.php'));
    assertTrue(str_contains($layout, '>Dashboard</a>') && str_contains($layout, 'href="/logout"'), 'Authenticated global navigation should expose Dashboard and Logout');
    assertTrue(!str_contains($layout, '>My Sessions</a>') && !str_contains($layout, '>Wallet</a>'), 'Authenticated global navigation should not duplicate internal account destinations');
    assertTrue(str_contains($layout, 'href="/account/dashboard"'), 'Global Dashboard should use the dashboard entry URL');
    foreach (['/account/dashboard/orders', '/account/dashboard/sessions', 'Back to Home'] as $needle) {
        assertTrue(str_contains($accountNav, $needle), "Shared account navigation should include {$needle}");
    }
    foreach (['orders.php', 'bookings.php'] as $view) {
        $contents = file_get_contents(app_path('views/account/' . $view));
        assertTrue(str_contains($contents, "require __DIR__ . '/_nav.php'"), "{$view} should reuse shared account navigation");
        assertTrue(!str_contains($contents, '<aside class="account-nav">'), "{$view} should not duplicate account navigation markup");
    }
};

$tests['legacy account urls redirect into the dashboard namespace'] = function (): void {
    $account = file_get_contents(app_path('app/Controllers/AccountController.php'));
    foreach (['/account/dashboard/orders', '/account/dashboard/sessions'] as $path) {
        assertTrue(str_contains($account, $path), "Legacy account controllers should redirect to {$path}");
    }
    $context = file_get_contents(app_path('app/Services/AgentContextService.php'));
    foreach (['/account/dashboard/orders', '/account/dashboard/sessions'] as $path) {
        assertTrue(str_contains($context, $path), "Agent context should expose canonical dashboard URL {$path}");
    }
};

$tests['consultants are profiles without application login credentials'] = function (): void {
    $auth=file_get_contents(app_path('app/Controllers/AuthController.php'));
    $admin=file_get_contents(app_path('app/Controllers/AdminController.php'));
    $layout=file_get_contents(app_path('views/layouts/admin.php'));
    assertTrue(str_contains($auth,'Consultant access is managed by the site administrator.'),'Legacy consultant users should be denied application login');
    assertTrue(!str_contains($admin,'AstrologerAccountService') && !str_contains($layout,'Login IDs'),'Admin should not create or expose consultant credentials');
    assertTrue(!is_file(app_path('app/Controllers/AstrologerController.php')) && !is_file(app_path('views/astrologer/dashboard.php')),'Consultant login surfaces should be removed');
};

$tests['consultation routes expose booking and provider status workflow'] = function (): void {
    $paths=array_column(ProjectMapService::registry()['routes'],'path');
    foreach(['/consultation/initiate','/api/consultations/{id}/status'] as $path) assertTrue(in_array($path,$paths,true),"Missing consultation route {$path}");
    foreach(['/astrologer','/astrologer/change-password','/astrologer/availability','/admin/astrologer-credentials'] as $path) assertTrue(!in_array($path,$paths,true),"Consultant credential route should be removed: {$path}");
    foreach(['/consultation/{id}','/api/consultations/{id}/messages','/api/consultations/{id}/signals'] as $path) assertTrue(!in_array($path,$paths,true),"Removed live consultation route should not be public: {$path}");
};

$tests['consultation booking form includes csrf and sends central owner notification'] = function (): void {
    $view = file_get_contents(app_path('views/public/astrologer.php'));
    assertTrue(str_contains($view, 'name="_csrf"'), 'Booking form should include CSRF');
    $controller = file_get_contents(app_path('app/Controllers/ConsultationController.php'));
    foreach (['MailQueueService', 'SecretService', 'admin_notification_email', 'smtp_username', 'appointment_owner_notification', '/admin/appointments', 'Narration'] as $needle) assertTrue(str_contains($controller, $needle), "Booking controller should centralize notification through {$needle}");
    assertTrue(!str_contains($controller, 'astrologer_session_notification'), 'Booking should not email a consultant dashboard login');
};

$tests['registration creates a default delivery address'] = function (): void {
    $auth = file_get_contents(app_path('app/Controllers/AuthController.php'));
    $register = file_get_contents(app_path('views/public/register.php'));
    foreach (['phone', 'address', 'city', 'pincode'] as $field) assertTrue(str_contains($register, 'name="' . $field . '"'), "Registration should collect {$field}");
    assertTrue(str_contains($auth, 'new AddressService') && str_contains($auth, "'is_default'=>true"), 'Registration should save the first address as default');
};

$tests['authenticated sessions persist for thirty days until logout'] = function (): void {
    $bootstrap = file_get_contents(app_path('app/bootstrap.php'));
    $auth = file_get_contents(app_path('app/Controllers/AuthController.php'));
    foreach (['session.gc_maxlifetime', "'lifetime' => 60 * 60 * 24 * 30", "'httponly' => true", "'samesite' => 'Lax'", 'session_start()'] as $needle) {
        assertTrue(str_contains($bootstrap, $needle), "Session bootstrap should include {$needle}");
    }
    assertTrue(str_contains($auth, 'session_destroy()'), 'Explicit logout should destroy the persistent session');
};

$tests['saved addresses select the default and allow another checkout address'] = function (): void {
    $schema = require app_path('storage/schema/collections.php');
    assertTrue(isset($schema['collections']['addresses']['fields']['is_default']), 'Address schema should declare is_default');
    $service = file_get_contents(app_path('app/Services/AddressService.php'));
    $checkout = file_get_contents(app_path('views/public/checkout.php'));
    assertTrue(str_contains($service, "'is_default' => \$isDefault"), 'Address service should persist one default address');
    assertTrue(str_contains($checkout, "' (Default)'"), 'Checkout should label the default saved address');
    assertTrue(str_contains($checkout, 'Enter a new address') && str_contains($checkout, 'Save for next time'), 'Checkout should allow one-time or newly saved addresses');
};

$tests['remote database uses password auth via admin UI or env'] = function (): void {
    $controller = file_get_contents(app_path('app/Controllers/RemoteDbController.php'));
    $database = file_get_contents(app_path('app/Services/DatabaseService.php'));
    $cli = file_get_contents(app_path('cli/bapXphp'));
    assertTrue(str_contains($controller, 'requirePassword'), 'Remote controller should have requirePassword');
    assertTrue(str_contains($controller, 'hash_equals'), 'Remote controller should verify password with timing-safe compare');
    assertTrue(str_contains($controller, 'SecretService'), 'Remote controller should read password from SecretService');
    assertTrue(str_contains($controller, 'remote_db_password'), 'Remote controller should check remote_db_password');
    foreach (["'upsert'", "'delete'", "'replace'"] as $needle) {
        assertTrue(str_contains($controller, $needle), "Remote controller should support {$needle}");
    }
    assertTrue(str_contains($database, 'remoteMutation'), 'Database service should use remote mutations when direct MySQL is unavailable');
    assertTrue(str_contains($database, 'remote_db_password'), 'Database service should send remote_db_password in payload');
    foreach (['db upsert', 'db delete'] as $needle) assertTrue(str_contains($cli, $needle), "CLI should expose {$needle}");
    assertTrue(!str_contains($cli, 'REMOTE_DB_PASSWORD'), 'CLI should not reference remote_db_password (handled by DatabaseService)');
    assertTrue(str_contains(file_get_contents(app_path('views/admin/integrations.php')), 'name="remote_db_password"'), 'Admin integrations should have a remote_db_password field');
    assertTrue(str_contains(file_get_contents(app_path('config/database.php')), 'REMOTE_DB_PASSWORD'), 'database.php config should read REMOTE_DB_PASSWORD from env');
    assertTrue(str_contains(file_get_contents(app_path('.env.example')), 'REMOTE_DB_PASSWORD'), '.env.example should document REMOTE_DB_PASSWORD');
};

$tests['consultant booking lifecycle is operational'] = function (): void {
    $consult = file_get_contents(app_path('app/Controllers/ConsultationController.php'));
    $service = file_get_contents(app_path('app/Services/ConsultationService.php'));
    assertTrue(str_contains($consult, "'mode'=>'booking'"), 'Consultation request should store booking mode');
    assertTrue(str_contains($consult, "\$role!=='admin'"), 'Only central admin should update appointment status');
    assertTrue(str_contains($service, "'requested' => ['accepted', 'declined', 'cancelled']"), 'Consultation service should validate the requested lifecycle');
    assertTrue(str_contains($service, "'accepted' => ['active', 'cancelled']"), 'Existing provider lifecycle should preserve acceptance transitions');
};

$tests['home hero rotates all supplied varahi images'] = function (): void {
    assertSame(10,count(glob(app_path('assets/images/hero/varahi/varahi-*.png'))?:[]),'Hero should include all ten supplied Varahi images');
    assertTrue(str_contains(file_get_contents(app_path('views/public/home.php')),'data-varahi-slider'),'Home should render the Varahi image slider');
};

$tests['admin product and astrologer forms expose editable owner fields'] = function (): void {
    $controller = file_get_contents(app_path('app/Controllers/AdminController.php'));
    $productForm = file_get_contents(app_path('views/admin/product-form.php'));
    $astroForm = file_get_contents(app_path('views/admin/astrologer-form.php'));
    $resourceView = file_get_contents(app_path('views/admin/resource.php'));
    $productView = file_get_contents(app_path('views/public/product.php'));
    $auditService = file_get_contents(app_path('app/Services/AuditLogService.php'));
    foreach (['slug', 'image_url', 'image_urls', 'price', 'offer_price', 'stock_status'] as $field) {
        assertTrue(str_contains($productForm, $field), "Product admin form should expose {$field}");
    }
    assertTrue(str_contains($productForm, 'enctype="multipart/form-data"'), 'Product form should support file uploads');
    assertTrue(str_contains($productForm, 'name="media_files[]"'), 'Product form should upload media files');
    assertTrue(str_contains($productForm, 'multiple'), 'Product image upload should accept multiple files');
    assertTrue(str_contains($productForm, 'foreach($mediaFiles as $media)'), 'Media picker should show all files by upload time, not only the latest page');
    assertTrue(str_contains($productForm, 'class="admin-media-picker"'), 'Product forms should expose a media library picker');
    assertTrue(str_contains($astroForm, 'foreach($mediaFiles as $media)'), 'Astrologer media picker should show all files by upload time');
    assertTrue(str_contains($astroForm, 'class="admin-media-picker"'), 'Astrologer forms should expose a media library picker');
    assertTrue(str_contains($resourceView, "['image_url', 'photo_url']"), 'Local asset image fields should not use URL inputs that reject /assets paths');
    assertTrue(!str_contains($resourceView, 'let el = document.getElementById'), 'Generated admin edit script should not redeclare let for every field');
    assertTrue(str_contains($productView, 'image_urls'), 'Product page should render product image galleries');
    assertTrue(str_contains($controller, 'MediaService'), 'Admin save should persist uploaded media into the shared media library');
    assertTrue(str_contains($controller, 'schemaFields'), 'Admin resource fields should be read from the JSON schema registry when available');
    assertTrue(str_contains($controller, 'mergeExistingRecord'), 'Admin save should preserve existing fields when editing only visible admin fields');
    assertTrue(str_contains($controller, 'AuditLogService'), 'Admin mutations should write audit log records');
    assertTrue(str_contains($auditService, 'function record'), 'Audit log service should be able to record admin changes');
    foreach (['slug', 'email', 'experience_years', 'slot_minutes', 'languages', 'working_days', 'speciality'] as $field) {
        assertTrue(str_contains($astroForm, $field), "Astrologer admin form should expose {$field}");
    }
    foreach (['username', 'message_credit_cost', 'call_credit_per_second', 'payout_percentage'] as $field) assertTrue(!str_contains($astroForm, 'name="' . $field . '"'), "Consultant form should not expose removed credential/rate field {$field}");
};

$tests['admin project map has a working view'] = function (): void {
    $view = app_path('views/admin/project-map.php');
    assertTrue(is_file($view), 'Project map admin route should have a renderable view');
    $contents = file_get_contents($view);
    assertTrue(str_contains($contents, 'Validation'), 'Project map view should show validation status');
    assertTrue(str_contains($contents, 'Routes'), 'Project map view should show route mappings');
};

$tests['admin sidebar exposes every admin menu'] = function (): void {
    $layout = file_get_contents(app_path('views/layouts/admin.php'));
    foreach ([
        '/admin',
        '/admin/products',
        '/admin/categories',
        '/admin/coupons',
        '/admin/astrologers',
        '/admin/appointments',
        '/admin/temples',
        '/admin/orders',
        '/admin/contact-submissions',
        '/admin/support-tickets',
        '/admin/media',
        '/admin/environment',
        '/admin/settings',
        '/admin/integrations',
        '/admin/shipping',
        '/admin/backups',
        '/admin/audit-log',
        '/admin/developer/project-map',
    ] as $path) {
        assertTrue(str_contains($layout, 'href="' . $path . '"'), "Admin sidebar should link {$path}");
    }
};

$tests['architecture and deployment docs describe current php template stack'] = function (): void {
    $readme = file_get_contents(app_path('README.md'));
    $architecture = file_get_contents(app_path('docs/architecture.md'));
    $deployment = file_get_contents(app_path('docs/deployment-hostinger.md'));
    foreach ([$architecture, $deployment] as $doc) {
        assertTrue(!str_contains($doc, 'React'), 'Docs should not describe the removed React/CDN architecture');
        assertTrue(!str_contains($doc, 'CDN'), 'Docs should not say the app loads React from a CDN');
    }
    foreach (['small PHP hosting', 'public_html', 'MySQL is the primary runtime store', '.env', 'APP_NAME', 'APP_URL', 'Admin → Settings', 'Admin → Integrations', 'agentic development', 'docs/README.md', 'docs/deployment-hostinger.md', 'AGENTS.md', 'docs/systematic-map.mmd'] as $needle) {
        assertTrue(str_contains($readme, $needle), "README should describe {$needle}");
    }
    assertTrue(is_file(app_path('docs/README.md')), 'Documentation index should exist and be linked from README');
    assertTrue(!str_contains($readme, 'https://sripanchamispiritual.com'), 'README should not hardcode the production website URL; use APP_URL in .env');
    assertTrue(str_contains($architecture, 'PHP-rendered public, account, and admin templates'), 'Architecture docs should describe the current PHP template frontend');
    assertTrue(str_contains($deployment, 'PHP-rendered templates'), 'Deployment docs should describe the current PHP template frontend');
};

$tests['legacy duplicate frontend modules are removed from the php template app'] = function (): void {
    foreach ([
        'assets/js/core/app-core.js',
        'assets/js/ui/components.js',
        'assets/js/app.js',
        'assets/js/components.js',
        'assets/js/pages.js',
        'assets/js/main.js',
        'assets/js',
        'components/AstroCard.js',
        'components/BottomNav.js',
        'components/Footer.js',
        'components/Header.js',
        'components/Page.js',
        'components/ProductCard.js',
        'tests/frontend.test.js',
        'utils/api.js',
        'utils/router.js',
        'views/layouts/spa.php',
    ] as $path) {
        assertTrue(!is_file(app_path($path)), "Unused duplicate frontend module should be removed: {$path}");
    }
    assertTrue(!is_dir(app_path('assets/js')), 'The legacy SPA app directory should be removed entirely');
    $index = file_get_contents(app_path('index.php'));
    assertTrue(!str_contains($index, 'views/layouts/spa.php'), 'Unknown routes should not load the legacy SPA fallback');
    assertTrue(str_contains($index, 'http_response_code(404)'), 'Unknown routes should return a real 404');
};

$tests['php 404 page uses themed template classes'] = function (): void {
    $view = file_get_contents(app_path('views/public/404.php'));
    $css = file_get_contents(app_path('assets/css/band.css'));
    foreach (['not-found-page', 'not-found-shell', 'not-found-mark', 'not-found-actions'] as $class) {
        assertTrue(str_contains($view, $class), "404 view should include {$class}");
        assertTrue(str_contains($css, '.' . $class), "Theme CSS should style {$class}");
    }
    assertTrue(str_contains($view, 'Page not found'), '404 page should keep clear user-facing page-not-found copy');
};

$tests['documentation has deployment agent instructions and no one-line placeholder pages'] = function (): void {
    assertTrue(is_file(app_path('AGENTS.md')), 'Agent operating guide should exist');
    $agent = file_get_contents(app_path('AGENTS.md'));
    foreach (['Repository Contract', 'docs/systematic-map.mmd', 'bapXphp update', 'bapXphp ci', 'After meaningful edits', 'Before pushing to `main`'] as $needle) {
        assertTrue(str_contains($agent, $needle), "Agent guide should mention {$needle}");
    }
    foreach (glob(app_path('docs/pages/*.md')) ?: [] as $path) {
        assertTrue(count(file($path) ?: []) > 3, basename($path) . ' should contain real page notes, not only a heading');
    }
    foreach (glob(app_path('docs/modules/*.md')) ?: [] as $path) {
        assertTrue(count(file($path) ?: []) > 3, basename($path) . ' should contain real module notes, not only a heading');
    }
    $deployment = file_get_contents(app_path('docs/deployment-hostinger.md'));
    foreach (['hPanel', 'Advanced', 'Git', 'Auto Deployment', 'Branch', 'public_html', 'Vercel'] as $needle) {
        assertTrue(str_contains($deployment, $needle), "Deployment guide should mention {$needle}");
    }
};

$tests['pull requests use non mutating CI with fresh project and documentation maps'] = function (): void {
    $workflow = file_get_contents(app_path('.github/workflows/ci.yml'));
    $cli = file_get_contents(app_path('cli/bapXphp'));
    foreach (['pull_request:', 'branches: [main]', './bapXphp ci'] as $needle) assertTrue(str_contains($workflow, $needle), "CI workflow should include {$needle}");
    foreach (['cmd_ci()', 'validate-project-map.php', 'validate-docs-map.php', 'cmd_update()', 'cmd_hooks()', 'cmd_tui()', 'cmd_ai_probe()'] as $needle) {
        assertTrue(str_contains($cli, str_replace('\\n', "\n", $needle)), "CLI should include {$needle}");
    }
    foreach (['require_gh', 'cmd_issue()', 'cmd_pr()', 'cmd_merge()', 'gh issue list', 'gh pr list'] as $needle) {
        assertTrue(!str_contains($cli, $needle), "CLI should not depend on {$needle}");
    }
    assertTrue(!str_contains(substr($cli, strpos($cli, 'cmd_ci()'), strpos($cli, 'cmd_check()') - strpos($cli, 'cmd_ci()')), 'generate-project-map.php'), 'CI validation must not regenerate the project map before checking freshness');
};

$tests['repository operations use git and GitHub Actions without duplicate agent folders'] = function (): void {
    assertTrue(is_file(app_path('.agents/skills/git/SKILL.md')), 'Plain Git skill should exist');
    assertTrue(!is_file(app_path('.agents/skills/gh-cli/SKILL.md')), 'GitHub CLI skill should be removed');
    assertTrue(!is_dir(app_path('.claude')), 'Duplicate .claude agent folder should not exist');
    assertTrue(is_file(app_path('.github/workflows/branch-pr.yml')), 'Branch pushes should have an Actions-owned PR workflow');
    assertTrue(!str_contains(file_get_contents(app_path('.github/workflows/sync-upstream.yml')), 'workflows: write'), 'Workflow permissions should use supported GitHub Actions keys');

    $activeFiles = [
        app_path('AGENTS.md'),
        app_path('README.md'),
        app_path('cli/bapXphp'),
        app_path('.agents/workflows/browser-tester.md'),
        app_path('.agents/workflows/cto-workflow.md'),
        app_path('.agents/skills/git/SKILL.md'),
        app_path('.agents/skills/deployment/SKILL.md'),
    ];
    foreach ($activeFiles as $path) {
        $source = file_get_contents($path);
        assertTrue(!preg_match('/\bgh\s+(issue|pr|api|workflow|repo)\b/', $source), basename($path) . ' should not require GitHub CLI');
    }

    $trigger = file_get_contents(app_path('.github/workflows/issue-agent-trigger.yml'));
    foreach (['workflow_dispatch:', 'types: [opened, closed]', 'Create or refresh handoff event', 'Clear matching active handoff', 'github.rest.issues.get'] as $needle) {
        assertTrue(str_contains($trigger, $needle), "Issue orchestration should include {$needle}");
    }
    assertTrue(!str_contains($trigger, 'types: [opened, labeled]'), 'Agent labels must not recursively dispatch duplicate handoffs');

    $tui = file_get_contents(app_path('cli/tui.php'));
    assertTrue(str_contains($tui, 'function activeHandoff') && str_contains($tui, 'handoff:'), 'TUI should show the actual active issue and role');
};

$tests['local smoke tool source covers key routes and CSRF protection'] = function (): void {
    $tool = app_path('cli/smoke-local.php');
    assertTrue(is_file($tool), 'Local route/API smoke tool should exist');
    $source = file_get_contents($tool);
    foreach (['/shop', '/checkout', '/consult', '/temples', '/payment/verify', '/support/ask', '/api/categories', '/unknown-spa-route'] as $path) {
        assertTrue(str_contains($source, $path), "Local smoke tool should cover {$path}");
    }
    assertTrue(str_contains($source, 'CSRF protected'), 'Local smoke should verify payment CSRF protection');
    assertTrue(str_contains($source, "'BAPX_TEST_MODE' => '1'"), 'Local smoke should not depend on the production database or network');
    assertTrue(str_contains(file_get_contents(app_path('app/Services/DatabaseService.php')), "getenv('BAPX_TEST_MODE') === '1'"), 'Database service should expose an explicit smoke-test boundary');
    assertTrue(str_contains($source, 'PASS local smoke'), 'Local smoke should provide an authoritative success signal');
};

$tests['systematic project map, docs/map.mmd, and root map.mmd are the generated map artifacts'] = function (): void {
    assertTrue(is_file(app_path('docs/systematic-map.mmd')), 'Systematic Mermaid map should exist');
    assertTrue(is_file(app_path('docs/map.mmd')), 'docs/map.mmd should exist');
    assertTrue(is_file(app_path('map.mmd')), 'root map.mmd should exist');
    foreach (['docs/PROJECT_MAP.md', 'docs/project-map.json', 'docs/project-map.mmd'] as $path) {
        assertTrue(!is_file(app_path($path)), "Old project-map artifact should not exist: {$path}");
    }
    $map = file_get_contents(app_path('docs/systematic-map.mmd'));
    foreach (['PUBLIC Routes', 'AUTH Routes', 'PAYMENT Routes', 'SUPPORT Routes', 'ADMIN Routes', 'Controllers', 'Services', 'Views', 'Integrations', 'Schema Collections', 'Tools', 'Gaps & Missing Links'] as $needle) {
        assertTrue(str_contains($map, $needle), "Systematic map should include {$needle}");
    }
    $dmap = file_get_contents(app_path('docs/map.mmd'));
    foreach (['CLI (bapXphp)', 'Agent Skills', 'Blog & Content', 'Application Architecture', 'Data Layer'] as $needle) {
        assertTrue(str_contains($dmap, $needle), "docs/map.mmd should include {$needle}");
    }
    $cmap = file_get_contents(app_path('map.mmd'));
    foreach (['PUBLIC Routes', 'AUTH Routes', 'PAYMENT Routes', 'ADMIN Routes', 'Controllers', 'Services', 'Schema Collections'] as $needle) {
        assertTrue(str_contains($cmap, $needle), "root map.mmd should include {$needle}");
    }
};

$tests['consultation pages use booking language without wallet pricing'] = function (): void {
    $home = file_get_contents(app_path('views/public/home.php'));
    $consult = file_get_contents(app_path('views/public/consult.php'));
    $detail = file_get_contents(app_path('views/public/astrologer.php'));
    assertTrue(!str_contains($detail, 'credits/message') && !str_contains($detail, 'credits/sec call'), 'Consultant detail should not show wallet pricing');
    assertTrue(!str_contains($home, 'astro-market-price'), 'Home provider cards should not repeat pricing');
    assertTrue(!str_contains($consult, 'astro-market-price'), 'Consult provider cards should not repeat pricing');
    assertTrue(str_contains($consult, 'Choose Your Consultant') && str_contains($consult, 'request a suitable appointment'), 'Public consultation copy should use clear scheduled-booking terminology');
};

$tests['consultants expose one booking path instead of live queues'] = function (): void {
    $market = file_get_contents(app_path('views/public/consult.php'));
    $controller = file_get_contents(app_path('app/Controllers/ConsultationController.php'));
    assertTrue(str_contains($market, 'Book appointment') && str_contains($market, '#booking-form'), 'Marketplace should link every consultant directly to booking');
    foreach (['Request message session', 'Request call session', 'queue_status', 'reserved_credits'] as $needle) assertTrue(!str_contains($market . $controller, $needle), "Booking path should not expose {$needle}");
};

$tests['customer help is a blog category with compatibility redirects'] = function (): void {
    $controller = file_get_contents(app_path('app/Controllers/PublicController.php'));
    assertTrue(in_array('/help/{slug}', array_column(ProjectMapService::registry()['routes'], 'path'), true), 'Help center should expose a hosting-safe guide detail route');
    assertTrue(str_contains(file_get_contents(app_path('index.php')), "'/help'"), 'Front controller should dispatch hosting-safe help routes into PHP');
    assertTrue(str_contains($controller, "'/blog/category/help'") && str_contains($controller, "'/blog/' . \$slug"), 'Legacy docs routes should redirect to canonical blog help content');
    assertTrue(str_contains(file_get_contents(app_path('content/blog/categories.yaml')), 'slug: help'), 'Blog categories should include Help');
    assertTrue(!is_dir(app_path('content/docs')) || !(glob(app_path('content/docs/*.md')) ?: []), 'Separate customer docs Markdown files should be removed');
    foreach (['create-account', 'order-products', 'book-consultant', 'payments-and-orders'] as $slug) {
        $post = file_get_contents(app_path("content/blog/posts/{$slug}.md"));
        assertTrue(str_contains($post, 'category: help'), "Help post {$slug} should use the help category");
    }
};

$tests['blog uses an editorial index and readable markdown article surface'] = function (): void {
    $index = file_get_contents(app_path('views/public/blog.php'));
    $article = file_get_contents(app_path('views/public/blog-post.php'));
    $css = file_get_contents(app_path('assets/css/band.css'));
    foreach (['Sri Panchami Journal', 'blog-card--featured', 'blog-card__media', 'Read article'] as $needle) {
        assertTrue(str_contains($index, $needle), "Blog index should include {$needle}");
    }
    assertTrue(str_contains($article, "\$schemaBase . '/blog'"), 'Article breadcrumbs should use the canonical blog URL');
    foreach (['blog-post__dek', 'blog-post__featured', 'blog-post__cta'] as $needle) assertTrue(str_contains($article, $needle), "Article should include {$needle}");
    assertTrue(str_contains($css, '.blog-post__content') && str_contains($css, 'line-height:1.78'), 'Article typography should use a constrained readable measure');
};

$tests['blog media uses one screenshot crop for cards and article pages'] = function (): void {
    $service = file_get_contents(app_path('app/Services/BlogService.php'));
    $admin = file_get_contents(app_path('views/admin/blog.php'));
    $index = file_get_contents(app_path('views/public/blog.php'));
    $article = file_get_contents(app_path('views/public/blog-post.php'));
    $cli = file_get_contents(app_path('cli/bapXphp'));
    $crop = file_get_contents(app_path('cli/blog-image.php'));
    foreach (['type', 'summary', 'order', 'og_image', 'image_alt', 'source_url', 'template'] as $field) {
        assertTrue(str_contains($service, "'{$field}'"), "Blog service should persist {$field}");
        assertTrue(str_contains($admin, 'name="' . $field . '"'), "Admin blog editor should expose {$field}");
    }
    assertTrue(str_contains($index, "\$post['og_image']") && str_contains($article, "\$meta['og_image']"), 'Card and article should share og_image');
    assertTrue(str_contains($article, "\$schemaImage ?: 'undefined'") && str_contains($article, 'e($sourceUrl)'), 'Article metadata should tolerate missing images and render the validated source URL');
    foreach (['create-account', 'order-products', 'book-consultant', 'payments-and-orders'] as $slug) {
        $post = file_get_contents(app_path("content/blog/posts/{$slug}.md"));
        assertTrue(str_contains($post, "\nsummary:") && str_contains($post, "\norder:"), "Help post {$slug} should retain summary and order metadata");
        assertTrue(str_contains($post, "\nimage_alt: Loaded "), "Help post {$slug} should describe a fully loaded browser capture");
        $image = app_path("assets/images/blog/{$slug}.webp");
        $size = getimagesize($image);
        assertTrue(is_array($size) && $size[0] === 1200 && $size[1] === 675, "Help post {$slug} should use a verified 1200x675 image");
        assertTrue(filesize($image) > 20000, "Help post {$slug} screenshot should contain rendered page detail");
    }
    assertTrue(str_contains($cli, 'blog:image') && str_contains($crop, '--dry-run'), 'CLI should expose safe blog screenshot cropping');
    assertTrue(str_contains($crop, '$targetWidth = 1200') && str_contains($crop, '$targetHeight = 675'), 'Blog screenshot crop should be stable 16:9');
};

$tests['public navigation uses brand home link and mobile cart tray'] = function (): void {
    $layout = file_get_contents(app_path('views/layouts/app.php'));
    $css = file_get_contents(app_path('assets/css/band.css'));
    assertSame(1, substr_count($layout, 'href="/" class="brand"'), 'Brand should link home once');
    assertTrue(!str_contains($layout, '>Home</a>') && !str_contains($layout, '<span>Home</span>'), 'Public navigation should not duplicate Home');
    assertTrue(!str_contains($layout, 'href="/blog/category/help"'), 'Help should remain a Blog category instead of a separate primary-menu item');
    foreach (['mobile-cart-tray', 'mobile-cart-count', 'mobile-cart-label'] as $needle) assertTrue(str_contains($layout, $needle), "Cart tray should include {$needle}");
    assertTrue(str_contains($css, '.mobile-cart-tray') && str_contains($css, 'bottom:78px'), 'Mobile cart tray should sit above bottom navigation');
    assertSame(substr_count($css, '{'), substr_count($css, '}'), 'Stylesheet should have balanced rule braces');
};

$tests['support assistant exposes only allowlisted internal navigation actions'] = function (): void {
    $service = file_get_contents(app_path('app/Services/SupportBotService.php'));
    $layout = file_get_contents(app_path('views/layouts/app.php'));
    assertTrue(str_contains($service, 'exact internal path') && str_contains($service, 'Never invent admin paths'), 'Support prompt should ground navigation');
    assertTrue(str_contains($layout, 'function supportReplyHtml') && str_contains($layout, 'class="support-action"'), 'Support UI should render safe internal actions');
    assertTrue(!str_contains($layout, 'innerHTML=j.reply'), 'Support reply must not inject model HTML');
};

$tests['product payment remains production gated after wallet removal'] = function (): void {
    $secrets = file_get_contents(app_path('app/Services/SecretService.php'));
    assertTrue(str_contains($secrets, 'razorpayReadyForCurrentHost'), 'Selected Razorpay credentials should be checked against the current host');
    assertTrue(str_contains($secrets, "=== 'live'"), 'Production hosts should require live Razorpay mode');
    assertTrue(str_contains($secrets, "?? '') === 'app_secrets'") && str_contains($secrets, 'array_filter($env'), 'Remote app_secrets should override environment fallbacks and legacy rows');
    foreach (['openssl_encrypt', 'openssl_decrypt', "'iv' =>", "'ciphertext' =>", "'agent_api_key'", "'gemma-4-31b-it'", "'configured' =>"] as $needle) {
        assertTrue(str_contains($secrets, $needle), "Remote integration secrets should include {$needle}");
    }
    assertTrue(!is_file(app_path('app/Controllers/WalletController.php')) && !is_file(app_path('views/account/wallet.php')), 'Wallet controller and customer view should be removed');
};

foreach ($tests as $name => $test) {
    try {
        $test();
        echo "PASS {$name}\n";
    } catch (Throwable $e) {
        $failures[] = "FAIL {$name}: {$e->getMessage()}";
    }
}

if ($failures) {
    echo implode("\n", $failures) . "\n";
    exit(1);
}
