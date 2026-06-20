<?php
require __DIR__ . '/../app/bootstrap.php';

use App\Services\JsonStoreService;
use App\Services\EnvService;
use App\Services\PaymentService;
use App\Services\ProjectMapService;
use App\Services\ReviewService;
use App\Services\SchemaService;

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

$tests['json store atomically persists records'] = function (): void {
    $dir = sys_get_temp_dir() . '/sps-json-' . bin2hex(random_bytes(4));
    $store = new JsonStoreService($dir);
    $store->write('products', [['id' => 'p1', 'name' => 'Lamp']]);
    assertSame([['id' => 'p1', 'name' => 'Lamp']], $store->read('products'), 'JSON store should round-trip data');
};

$tests['payment signature verification matches Razorpay format'] = function (): void {
    $service = new PaymentService('secret');
    $signature = hash_hmac('sha256', 'order_1|pay_1', 'secret');
    assertTrue($service->verifySignature('order_1', 'pay_1', $signature), 'Valid payment signature should pass');
    assertTrue(!$service->verifySignature('order_1', 'pay_1', 'bad'), 'Invalid payment signature should fail');
};

$tests['project map registry has no missing route mappings'] = function (): void {
    $map = ProjectMapService::registry();
    $validation = ProjectMapService::validate($map);
    assertSame([], $validation['missing_route_mappings'], 'Routes should map to controllers');
    assertSame([], $validation['missing_services'], 'Routes should reference declared services');
    assertSame([], $validation['missing_collections'], 'Collections should be declared');
};

$tests['repo has agent-readable schema and built-in skills'] = function (): void {
    $schemaPath = app_path('storage/schema/collections.json');
    assertTrue(is_file($schemaPath), 'JSON schema registry should exist');
    $schema = json_decode(file_get_contents($schemaPath), true);
    assertTrue(is_array($schema), 'JSON schema registry should parse');
    foreach (['products', 'astrologers', 'temples', 'orders', 'appointments', 'wallet_transactions', 'support_tickets', 'media_files'] as $collection) {
        assertTrue(isset($schema['collections'][$collection]), "Schema should define {$collection}");
    }
    assertTrue(in_array('image_urls', $schema['collections']['products']['media_fields'] ?? [], true), 'Product schema should define gallery media field');
    assertTrue((new SchemaService())->adminFields('products') !== [], 'SchemaService should expose admin fields');
    foreach ([
        'AGENTS.md',
        '.agents/AGENTS.md',
        '.agents/skills/AGENTS.md',
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
    foreach (['example-Agent.md', 'CLAUDE.md', '.codex', '.claude'] as $path) {
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
    assertTrue(str_contains($index, "'/appointments'"), 'Appointment POST actions should dispatch through PHP routes instead of SPA fallback');
    assertTrue(str_contains($index, "'/payment'"), 'Payment verification POST actions should dispatch through PHP routes instead of SPA fallback');
};

$tests['cart does not expose unfinished coupon placeholder ui'] = function (): void {
    $view = file_get_contents(app_path('views/public/cart.php'));
    assertTrue(!str_contains($view, 'Coupon feature coming soon'), 'Cart should not ship a coupon coming-soon alert');
    assertTrue(!str_contains($view, 'id="coupon-input"'), 'Cart should not expose inactive coupon input');
};

$tests['catalog image paths point to existing local assets'] = function (): void {
    $store = new JsonStoreService();
    foreach (['products', 'categories', 'temples', 'astrologers'] as $collection) {
        foreach ($store->read($collection) as $item) {
            $image = $item['image_url'] ?? $item['photo_url'] ?? '';
            if ($image === '' || str_starts_with($image, 'http')) continue;
            $path = parse_url($image, PHP_URL_PATH);
            assertTrue(is_file(app_path($path)), "{$collection} image should exist: {$image}");
        }
    }
};

$tests['shop supports plain vertical filters and multi category products'] = function (): void {
    $store = new JsonStoreService();
    $categories = $store->read('categories');
    $products = $store->read('products');
    $css = file_get_contents(app_path('assets/css/band.css'));
    $controller = file_get_contents(app_path('app/Controllers/PublicController.php'));
    assertTrue(in_array('pooja-idols', array_column($categories, 'slug'), true), 'Shop categories should include Pooja Idols');
    foreach (['lakshmi-dollar', 'murugar-vel-mayil-dollar', 'lingam-dollar'] as $slug) {
        $product = array_values(array_filter($products, fn($item) => ($item['slug'] ?? '') === $slug))[0] ?? null;
        assertTrue($product !== null, "Product should exist: {$slug}");
        foreach (['sacred-emblems', 'jewelry', 'pooja-idols'] as $category) {
            assertTrue(in_array($category, $product['categories'] ?? [], true), "{$slug} should appear in {$category}");
        }
    }
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
    $paths = ['app', 'api', 'integrations', 'tests', 'tools', 'views', 'index.php'];
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

$tests['public registration never bootstraps admin on a live site'] = function (): void {
    $controller = file_get_contents(app_path('app/Controllers/AuthController.php'));
    assertTrue(!str_contains($controller, 'count($users) === 0 ? \'admin\' : \'customer\''), 'Public registration should not make the first user an admin on a live site');
    assertTrue(str_contains($controller, "\$role = 'customer';"), 'New public registrations and OAuth users should default to customer role');
    assertTrue(str_contains($controller, "'role'=>"), 'Session user should include a role after registration and login');
    assertTrue(str_contains($controller, "\$u['role']"), 'Email/password login should preserve an existing stored admin role and password');
};

$tests['env file defines editable local admin credentials'] = function (): void {
    $exampleEnvPath = app_path('.env.example');
    assertTrue(is_file($exampleEnvPath), '.env.example should exist for safe setup documentation');
    $exampleEnv = EnvService::readFile($exampleEnvPath);
    foreach (['APP_NAME', 'APP_URL', 'ADMIN_USERNAME', 'ADMIN_EMAIL', 'ADMIN_PASSWORD'] as $key) {
        assertTrue(($exampleEnv[$key] ?? '') !== '', ".env.example should define {$key}");
    }

    $envPath = app_path('.env');
    assertTrue(is_file($envPath), '.env should exist for small PHP hosting setup');
    $env = EnvService::readFile($envPath);
    foreach (['ADMIN_USERNAME', 'ADMIN_EMAIL', 'ADMIN_PASSWORD'] as $key) {
        assertTrue(($env[$key] ?? '') !== '', ".env should define {$key}");
    }
    $auth = file_get_contents(app_path('app/Controllers/AuthController.php'));
    assertTrue(str_contains($auth, 'adminCredentials'), 'Login should check .env admin credentials');
    assertTrue(str_contains($auth, "'role'=>'admin'"), 'Successful .env admin login should create an admin session');
};

$tests['admin settings can update env admin credentials'] = function (): void {
    $view = file_get_contents(app_path('views/admin/settings.php'));
    $controller = file_get_contents(app_path('app/Controllers/AdminController.php'));
    $map = ProjectMapService::registry();
    $paths = array_column($map['routes'], 'path');
    foreach (['name="admin_username"', 'name="admin_email"', 'name="admin_password"', 'action="/admin/settings/admin-credentials"'] as $needle) {
        assertTrue(str_contains($view, $needle), "Admin settings should expose {$needle}");
    }
    assertTrue(str_contains($controller, 'saveAdminCredentials'), 'Admin controller should save admin .env credentials');
    assertTrue(in_array('/admin/settings/admin-credentials', $paths, true), 'Route registry should include admin credential save route');
};

$tests['admin environment page edits env and storage permissions'] = function (): void {
    $controller = file_get_contents(app_path('app/Controllers/AdminController.php'));
    $layout = file_get_contents(app_path('views/layouts/admin.php'));
    $view = file_get_contents(app_path('views/admin/environment.php'));
    $env = file_get_contents(app_path('app/Services/EnvService.php'));
    $permissions = file_get_contents(app_path('app/Services/StoragePermissionService.php'));
    $paths = array_column(ProjectMapService::registry()['routes'], 'path');
    foreach (['/admin/environment', '/admin/environment/save', '/admin/environment/fix-permissions'] as $path) {
        assertTrue(in_array($path, $paths, true), "Environment route should include {$path}");
    }
    assertTrue(str_contains($layout, 'href="/admin/environment"'), 'Admin sidebar should link environment page');
    assertTrue(str_contains($controller, 'saveEnvironment'), 'Admin controller should save raw env contents');
    assertTrue(str_contains($controller, 'fixPermissions'), 'Admin controller should expose storage permission repair');
    assertTrue(str_contains($view, 'name="env_raw"'), 'Environment page should expose editable env textarea');
    assertTrue(str_contains($view, 'Storage Permissions'), 'Environment page should show storage permissions');
    assertTrue(str_contains($env, 'function saveRaw'), 'Env service should support raw env saving');
    assertTrue(str_contains($permissions, 'storage/data'), 'Permission service should check JSON storage path');
};

$tests['support assistant uses schema-filtered agent context'] = function (): void {
    $service = file_get_contents(app_path('app/Services/SupportBotService.php'));
    $context = file_get_contents(app_path('app/Services/AgentContextService.php'));
    assertTrue(str_contains($service, 'AgentContextService'), 'Support bot should use AgentContextService for customer data');
    assertTrue(str_contains($context, 'agentContextFields'), 'Agent context should respect schema-defined safe fields');
    assertTrue(str_contains($context, 'customer_email'), 'Agent context should filter customer-owned collections by email');
};

$tests['contact submissions persist to json storage'] = function (): void {
    $dir = sys_get_temp_dir() . '/sps-contact-' . bin2hex(random_bytes(4));
    $service = new App\Services\ContactService(new JsonStoreService($dir));
    $id = $service->save(['name' => 'Customer', 'email' => 'customer@example.com', 'message' => 'Need help']);
    $saved = $service->find($id);
    assertTrue($saved !== null, 'Saved contact submission should be readable');
    assertSame('Customer', $saved['name'] ?? null, 'Saved contact name should round-trip');
    assertSame('new', $saved['status'] ?? null, 'Saved contact submission should default to new status');
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
        'https://console.cloud.google.com/apis/credentials',
        'https://ai.google.dev/gemini-api/docs/api-key',
        'support_bot_google_api_key',
        'support_bot_model',
        'gemma-4-31b-it',
        'https://generativelanguage.googleapis.com/v1beta/models/',
        'support_bot_purge_policy',
        'always_purge',
    ] as $needle) {
        assertTrue(str_contains($view, $needle), "Integrations page should include {$needle}");
    }
    assertTrue(!str_contains($view, 'name="support_bot_google_api_endpoint"'), 'Admin should not need to enter the Google API endpoint manually');
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

$tests['bookings use direct platform sessions without google meet or calendar'] = function (): void {
    $booking = file_get_contents(app_path('app/Controllers/BookingController.php'));
    $oauth = file_get_contents(app_path('integrations/google-oauth/GoogleOAuthClient.php'));
    $map = ProjectMapService::registry();
    $services = array_unique(array_merge(...array_map(fn($route) => $route['services'], $map['routes'])));
    assertTrue(!str_contains($booking, 'meet.google.com'), 'Bookings should not generate Google Meet links');
    assertTrue(!str_contains($oauth, 'calendar.events'), 'Google login should not request Calendar permissions');
    assertTrue(!is_file(app_path('app/Services/CalendarService.php')), 'CalendarService source should be removed');
    assertTrue(!is_file(app_path('integrations/google-calendar/GoogleCalendarClient.php')), 'Google Calendar integration source should be removed');
    assertTrue(!in_array('CalendarService', $services, true), 'CalendarService should not be wired into platform routes');
    assertTrue(!in_array('GoogleCalendarClient', $map['integrations'], true), 'Google Calendar should not be a configured integration');
};

$tests['remote astrology sessions do not use appointment slots and show per session spend'] = function (): void {
    $booking = file_get_contents(app_path('app/Controllers/BookingController.php'));
    $bookingsView = file_get_contents(app_path('views/account/bookings.php'));
    $astrologersView = file_get_contents(app_path('views/public/consult.php'));
    $profileView = file_get_contents(app_path('views/public/astrologer.php'));
    assertTrue(!str_contains($booking, 'slotsForDate'), 'Remote sessions should not validate appointment date/time slots');
    assertTrue(str_contains($booking, 'credits_spent'), 'Remote session records should track credits spent per call/message session');
    assertTrue(str_contains($bookingsView, 'Credits Spent'), 'User session panel should show per-session credits spent');
    assertTrue(str_contains($bookingsView, 'Session Type'), 'User session panel should show call/message session type');
    assertTrue(!str_contains($astrologersView, 'JOIN Q'), 'Busy astrologer action should not say JOIN Q');
    assertTrue(str_contains($astrologersView, 'Waitlist'), 'Busy astrologer action should say Waitlist');
    assertTrue(str_contains($astrologersView, 'action="/appointments/book"'), 'Astrologer listing call/message actions should create remote session requests');
    assertTrue(str_contains($profileView, 'action="/appointments/book"'), 'Astrologer profile call/message actions should create remote session requests');
};

$tests['astrologer profile uses remote consultation contact panel instead of appointment slot forms'] = function (): void {
    $view = file_get_contents(app_path('views/public/astrologer.php'));
    assertTrue(!str_contains($view, 'slot-picker'), 'Astrologer profile should not render appointment slot picker UI');
    assertTrue(!str_contains($view, 'Available Slots'), 'Astrologer profile should not show cinema-style appointment slots');
    assertTrue(!str_contains($view, 'name="date"') && !str_contains($view, 'name="time"'), 'Astrologer profile should not post dated slot booking fields');
    assertTrue(str_contains($view, 'action="/appointments/book"'), 'Astrologer profile should post remote call/message session requests');
    assertTrue(str_contains($view, '/contact'), 'Astrologer profile should direct consultation requests to the contact page');
    assertTrue(str_contains($view, 'Remote Call') || str_contains($view, 'Remote consultation'), 'Astrologer profile should describe remote call/message consultation');
};

$tests['astrologer marketplace exposes search and direct session actions'] = function (): void {
    $view = file_get_contents(app_path('views/public/consult.php'));
    foreach (['Search Astrologer', 'astro-search-input', 'astro-status-filter', 'astro-language-filter', 'data-astro-card', 'data-status=', 'data-language='] as $needle) {
        assertTrue(str_contains($view, $needle), "Astrologer marketplace should expose {$needle}");
    }
    foreach (['Filters', 'Available Now', 'On Chat', 'On Call'] as $needle) {
        assertTrue(!str_contains($view, ">{$needle}<"), "Astrologer marketplace should not expose non-working {$needle} button");
    }
    assertTrue(!str_contains($view, 'Available Balance'), 'Astrologer marketplace should not show account balance; that belongs in the user panel');
    assertTrue(!str_contains($view, 'href="/recharge"'), 'Astrologer marketplace should not show recharge; that belongs in the logged-in user panel');
    assertTrue(!str_contains($view, 'astro-recharge'), 'Astrologer marketplace should not render a recharge toolbar action');
    assertTrue(substr_count($view, 'astro-action--disabled') >= 2, 'Unavailable astrologers should keep message and call icons visibly disabled');
    assertTrue(str_contains($view, 'queue_status'), 'Busy astrologers should retain a real message waitlist action');
    foreach (['aria-label="Start message session"', 'aria-label="Start call session"', 'Join message waitlist', 'View Profile', 'astro-action--profile', 'astro-status-label'] as $needle) {
        assertTrue(str_contains($view, $needle), "Astrologer marketplace should expose {$needle} actions");
    }
    foreach (['+ Follow', 'Flat Deal', "['online', 'busy', 'offline']", '125 + ($index * 247)', "['Tamil']", "'N/A') ?> Years"] as $needle) {
        assertTrue(!str_contains($view, $needle), "Astrologer marketplace should not render invented or dead content: {$needle}");
    }
    assertTrue(str_contains($view, 'astro-action-row'), 'Message, call, and profile icon buttons should share one card action row');
    assertTrue(!str_contains($view, 'Check Availability'), 'Astrologer marketplace should not use appointment availability CTA');
};

$tests['wallet recharge is login gated and exposes pricing breakdown'] = function (): void {
    $map = ProjectMapService::registry();
    $paths = array_column($map['routes'], 'path');
    foreach (['/recharge', '/recharge/create-order', '/recharge/verify', '/account/wallet'] as $path) {
        assertTrue(in_array($path, $paths, true), "Wallet route {$path} should be registered");
    }
    $view = file_get_contents(app_path('views/account/wallet.php'));
    foreach (['Remaining Balance', 'Recharge Amount', 'Service charge', 'GST/tax estimate', 'Pay with Razorpay', 'Razorpay is not configured yet'] as $needle) {
        assertTrue(str_contains($view, $needle), "Wallet page should include {$needle}");
    }
    $booking = file_get_contents(app_path('app/Controllers/BookingController.php'));
    assertTrue(str_contains($booking, 'WalletService'), 'Session booking should check wallet balance');
    assertTrue(str_contains($booking, '/recharge?amount=100'), 'Insufficient session balance should redirect to recharge');
};

$tests['support assistant widget uses browser session memory and google model setting'] = function (): void {
    $layout = file_get_contents(app_path('views/layouts/app.php'));
    $service = file_get_contents(app_path('app/Services/SupportBotService.php'));
    $map = ProjectMapService::registry();
    $paths = array_column($map['routes'], 'path');
    assertTrue(in_array('/support/ask', $paths, true), 'Support ask route should be registered');
    foreach (['support-fab', 'support-panel', '/support/ask', 'orders, wallet recharge, products, or astrologer sessions', 'sessionStorage', 'data-support-key'] as $needle) {
        assertTrue(str_contains($layout, $needle), "Support widget should include {$needle}");
    }
    foreach (['gemma-4-31b-it', 'support_bot_google_api_key', 'Customer context JSON', 'browser_session'] as $needle) {
        assertTrue(str_contains($service, $needle), "Support bot service should include {$needle}");
    }
    assertTrue(!str_contains($service, "upsert('support_tickets'"), 'Support bot chat should not persist browser chat into project JSON files');
};

$tests['astrologer profile exposes real remote actions and verified review state'] = function (): void {
    $view = file_get_contents(app_path('views/public/astrologer.php'));
    foreach (['aria-label="Start message session"', 'aria-label="Start call session"', 'BOOK SESSION', 'No verified reviews yet.', 'Private consultation rooms', 'Admin-managed astrologer profiles', '100% Secure Payments'] as $needle) {
        assertTrue(str_contains($view, $needle), "Astrologer profile should expose {$needle}");
    }
    foreach (['+ Follow', 'Flat Deal', 'Send gifts', 'Money Back Guarantee', 'K B...', '87))'] as $needle) assertTrue(!str_contains($view,$needle), "Astrologer profile should not render dead or fabricated content: {$needle}");
    assertTrue(str_contains($view, '5 credits/message'), 'Astrologer profile should explain message credit cost');
    assertTrue(str_contains($view, '0.5 credits/sec call'), 'Astrologer profile should explain call credit cost');
};

$tests['home page rotates all astrologers instead of showing only three fixed cards'] = function (): void {
    $view = file_get_contents(app_path('views/public/home.php'));
    assertTrue(!str_contains($view, 'array_slice($astrologers, 0, 3)'), 'Home astrology section should not hard-limit to three astrologers');
    assertTrue(str_contains($view, 'astro-carousel-track'), 'Home astrology section should use a carousel track');
    assertTrue(str_contains($view, 'astro-status-label'), 'Home cards should share the marketplace status contract');
    foreach(['+ Follow','4.9 | 500+',"['online', 'busy', 'offline']", "['Tamil']", "'N/A') ?> Years"] as $needle) assertTrue(!str_contains($view,$needle), "Home cards should not render invented or dead content: {$needle}");
};

$tests['astrologer cards use consistent face focused portrait frames'] = function (): void {
    $css=file_get_contents(app_path('assets/css/band.css'));
    foreach(['aspect-ratio: 1;','object-position: center;','.astro-market-photo-frame','.astro-carousel .astro-market-card'] as $needle) assertTrue(str_contains($css,$needle),"Astrologer card CSS should include {$needle}");
    assertTrue(!str_contains($css,'.astro-carousel .astrologer-card'),'Homepage carousel should target the actual marketplace card class');
    foreach (['views/public/home.php', 'views/public/consult.php'] as $viewPath) {
        assertTrue(str_contains(file_get_contents(app_path($viewPath)), 'astro-market-photo-frame'), "{$viewPath} should use the clipped portrait frame");
    }
};

$tests['home hero uses concise current copy and working cta links'] = function (): void {
    $view = file_get_contents(app_path('views/public/home.php'));
    assertTrue(!str_contains($view, 'Spiritual Products Online in Chennai'), 'Home hero headline should not say products online in Chennai');
    assertTrue(!str_contains($view, 'Buy Original Rudraksha, Pooja Items & Spiritual Products Online'), 'Home hero should not lead with ecommerce as the primary business');
    assertTrue(!str_contains($view, 'Shop Spiritual Products</a>'), 'Home hero shop button should use concise text');
    assertTrue(!str_contains($view, 'Remote Astrology Consultation</a>'), 'Home hero astrology button should use shorter text');
    foreach (['Consult Astrologers Online by Chat or Call', 'href="/consult"', 'href="/shop"', '>Consult Now</a>', '>Shop Products</a>', 'Online Astrology Consultation'] as $needle) {
        assertTrue(str_contains($view, $needle), "Home hero should include {$needle}");
    }
    assertTrue(!str_contains($view, '<div class="hero-stat-value">3</div>'), 'Home hero astrologer count should not be stale');
    assertTrue(str_contains($view, 'count($astrologers)'), 'Home hero astrologer count should be derived from the current catalog');
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
    $dir = sys_get_temp_dir() . '/sps-reviews-' . bin2hex(random_bytes(4));
    $service = new ReviewService(new JsonStoreService($dir));
    $service->saveAstrologerReview(['target_slug' => 'pandit-shastri', 'rating' => 5, 'review' => 'Clear reading']);
    $service->saveAstrologerReview(['target_slug' => 'pandit-shastri', 'rating' => 3, 'review' => 'Helpful']);
    $summary = $service->summary('astrologer', 'pandit-shastri');
    assertSame(2, $summary['count'], 'Astrologer review count should include saved reviews');
    assertSame(4.0, $summary['average'], 'Astrologer review average should be calculated from saved ratings');
    $product = $service->saveProductReview(['target_slug' => 'rudraksha-mala', 'rating' => 4, 'review' => 'Good quality']);
    assertSame('product', $product['target_type'], 'Product review should be tagged as product');
};

$tests['mail queue schedules payment shipment and delayed product review emails'] = function (): void {
    $dir = sys_get_temp_dir() . '/sps-mail-' . bin2hex(random_bytes(4));
    $store = new JsonStoreService($dir);
    $queue = new App\Services\MailQueueService($store);
    $order = [
        'id' => 'ord_1',
        'customer_email' => 'customer@example.com',
        'customer_name' => 'Customer',
        'total' => 1200,
        'items' => [['name' => 'Rudraksha Mala', 'qty' => 1]],
        'shipped_at' => '2026-06-02T10:00:00+05:30',
    ];
    $queue->enqueuePaymentConfirmation($order);
    $queue->enqueueShipmentNotification($order);
    $queue->enqueueProductReviewRequest($order, 10);
    $records = $store->read('mail_queue');
    assertSame(3, count($records), 'Payment, shipment, and review request emails should be queued');
    assertSame('payment_confirmation', $records[0]['type'] ?? null, 'Payment email should be typed');
    assertSame('shipment_notification', $records[1]['type'] ?? null, 'Shipment email should be typed');
    assertSame('product_review_request', $records[2]['type'] ?? null, 'Delayed review email should be typed');
    assertSame('2026-06-12T10:00:00+05:30', $records[2]['available_at'] ?? null, 'Product review request should wait 10 days after shipment');
};

$tests['mail queue exposes due messages and processor script for cron delivery'] = function (): void {
    $dir = sys_get_temp_dir() . '/sps-mail-due-' . bin2hex(random_bytes(4));
    $store = new JsonStoreService($dir);
    $queue = new App\Services\MailQueueService($store);
    $queue->enqueue('past_notice', 'customer@example.com', 'Past', '<p>Past</p>', new DateTimeImmutable('2026-06-01T10:00:00+05:30'));
    $queue->enqueue('future_notice', 'customer@example.com', 'Future', '<p>Future</p>', new DateTimeImmutable('2026-06-20T10:00:00+05:30'));
    $due = $queue->due(new DateTimeImmutable('2026-06-12T10:00:00+05:30'));
    assertSame(['past_notice'], array_column($due, 'type'), 'Only pending mail available by now should be due');
    assertTrue(is_file(app_path('tools/process-mail-queue.php')), 'Mail queue should have a cron-friendly processor script');
};

$tests['order shipping workflow sets review date and queues customer emails'] = function (): void {
    $dir = sys_get_temp_dir() . '/sps-orders-' . bin2hex(random_bytes(4));
    $store = new JsonStoreService($dir);
    $store->write('orders', [[
        'id' => 'ord_2',
        'status' => 'confirmed',
        'customer_email' => 'customer@example.com',
        'customer_name' => 'Customer',
        'items' => [['name' => 'Lamp', 'qty' => 1]],
    ]]);
    $service = new App\Services\OrderService($store, new App\Services\MailQueueService($store));
    $order = $service->updateStatus('ord_2', 'shipped', new DateTimeImmutable('2026-06-02T10:00:00+05:30'));
    assertSame('shipped', $order['status'] ?? null, 'Order status should update to shipped');
    assertSame('2026-06-02T10:00:00+05:30', $order['shipped_at'] ?? null, 'Shipping timestamp should be stored');
    assertSame('2026-06-12T10:00:00+05:30', $order['review_request_after_at'] ?? null, 'Review form should unlock 10 days after shipment');
    $mail = $store->read('mail_queue');
    assertSame(['shipment_notification', 'product_review_request'], array_column($mail, 'type'), 'Shipping should queue shipment and delayed review emails');
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
    foreach (['phone:', 'address:', 'city:', 'pincode:'] as $needle) {
        assertTrue(str_contains($checkout, $needle), "Razorpay verification request should include {$needle}");
    }
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

$tests['astrologer catalog uses all twenty one client profiles'] = function (): void {
    $astrologers = (new JsonStoreService())->read('astrologers');
    assertSame(21, count($astrologers), 'Astrologer seed data should include all client profiles');
    foreach ($astrologers as $astrologer) {
        assertTrue(!empty($astrologer['slug']), 'Every astrologer should have a slug');
        assertTrue(str_contains($astrologer['photo_url'] ?? '', '/astrologers/client/'), 'Astrologer profile images should use extracted client portraits');
        $portraitPath = app_path(ltrim($astrologer['photo_url'] ?? '', '/'));
        $sourcePath = app_path('assets/images/astrologers/source/' . $astrologer['slug'] . '.jpg');
        assertTrue(is_file($portraitPath), 'Every client portrait path should exist');
        assertTrue(is_file($sourcePath), 'Every client card original should be preserved');
        assertSame([420, 420], array_slice(getimagesize($portraitPath) ?: [], 0, 2), 'Every public portrait should be a 420px square crop');
        assertSame([1080, 1080], array_slice(getimagesize($sourcePath) ?: [], 0, 2), 'Every preserved client original should retain its 1080px dimensions');
        assertSame(5, (int)($astrologer['message_credit_cost'] ?? 0), 'Message session should cost 5 credits per user message');
        assertSame(0.5, (float)($astrologer['call_credit_per_second'] ?? 0), 'Call session should cost 0.5 credits per second');
    }
};

$tests['astrologer accounts require password change and use username login'] = function (): void {
    $users=(new JsonStoreService())->read('users');
    $astrologers=array_values(array_filter($users,fn($user)=>($user['role']??'')==='astrologer'));
    assertSame(21,count($astrologers),'Every client astrologer should have an account');
    foreach($astrologers as $user){assertTrue(!empty($user['username']),'Astrologer username is required');assertTrue(!empty($user['must_change_password']),'Initial password change must be required');assertTrue(password_verify('sripanjamiconsult',$user['password_hash']??''),'Initial password hash should verify');}
    assertTrue(str_contains(file_get_contents(app_path('app/Controllers/AuthController.php')),"['username']"),'Login should accept an astrologer username');
    $admin=file_get_contents(app_path('app/Controllers/AdminController.php'));
    assertTrue(str_contains($admin,'AstrologerAccountService'),'Admin astrologer mutations should synchronize provider accounts');
};

$tests['consultation api exposes message call and status workflows'] = function (): void {
    $paths=array_column(ProjectMapService::registry()['routes'],'path');
    foreach(['/consultation/{id}','/api/consultations/{id}/messages','/api/consultations/{id}/signals','/api/consultations/{id}/status','/astrologer'] as $path) assertTrue(in_array($path,$paths,true),"Missing consultation route {$path}");
    assertTrue(is_file(app_path('storage/data/consultation_messages.json')),'Message collection should exist');
    assertTrue(is_file(app_path('storage/data/consultation_signals.json')),'Call signaling collection should exist');
};

$tests['home hero rotates all supplied varahi images'] = function (): void {
    assertSame(10,count(glob(app_path('assets/images/hero/varahi/varahi-*.jpg'))?:[]),'Hero should include all ten supplied Varahi images');
    assertTrue(str_contains(file_get_contents(app_path('views/public/home.php')),'data-varahi-slider'),'Home should render the Varahi image slider');
};

$tests['admin product and astrologer forms expose editable owner fields'] = function (): void {
    $controller = file_get_contents(app_path('app/Controllers/AdminController.php'));
    foreach (['slug', 'image_url', 'image_urls', 'price', 'offer_price', 'stock_status'] as $field) {
        assertTrue(str_contains($controller, "'{$field}'"), "Product admin form should expose {$field}");
    }
    $resourceView = file_get_contents(app_path('views/admin/resource.php'));
    $productView = file_get_contents(app_path('views/public/product.php'));
    $auditService = file_get_contents(app_path('app/Services/AuditLogService.php'));
    assertTrue(str_contains($resourceView, 'enctype="multipart/form-data"'), 'Admin resource form should support file uploads');
    assertTrue(str_contains($resourceView, 'name="media_files[]"'), 'Admin resource form should upload media files');
    assertTrue(str_contains($resourceView, 'multiple'), 'Product image upload should accept multiple files');
    assertTrue(str_contains($resourceView, "['image_url', 'photo_url']"), 'Local asset image fields should not use URL inputs that reject /assets paths');
    assertTrue(str_contains($resourceView, 'foreach($mediaFiles as $media)'), 'Media picker should show all files by upload time, not only the latest page');
    assertTrue(str_contains($resourceView, 'class="admin-media-picker"'), 'Product temple and astrologer forms should expose a media library picker');
    assertTrue(!str_contains($resourceView, 'let el = document.getElementById'), 'Generated admin edit script should not redeclare let for every field');
    assertTrue(str_contains($productView, 'image_urls'), 'Product page should render product image galleries');
    assertTrue(str_contains($controller, 'MediaService'), 'Admin save should persist uploaded media into the shared media library');
    assertTrue(str_contains($controller, 'schemaFields'), 'Admin resource fields should be read from the JSON schema registry when available');
    assertTrue(str_contains($controller, 'mergeExistingRecord'), 'Product save should preserve existing fields when editing only visible admin fields');
    assertTrue(str_contains($controller, 'AuditLogService'), 'Admin mutations should write audit log records');
    assertTrue(str_contains($auditService, 'function record'), 'Audit log service should be able to record admin changes');
    foreach (['slug', 'message_credit_cost', 'call_credit_per_second', 'text_session_prm', 'call_session_prm', 'payout_percentage', 'languages', 'working_days'] as $field) {
        assertTrue(str_contains($controller, "'{$field}'"), "Astrologer admin form should expose {$field}");
    }
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
    foreach (['small PHP hosting', 'public_html', 'JSON-backed backend', '.env', 'ADMIN_EMAIL', 'ADMIN_PASSWORD', 'agentic development', 'docs/README.md', 'docs/deployment-hostinger.md', 'AGENTS.md', 'docs/systematic-map.mmd'] as $needle) {
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
    foreach (['DOX Contract', 'docs/systematic-map.mmd', 'php tests/run.php', 'php tools/generate-project-map.php', 'php tools/smoke-local.php', 'remote `main`'] as $needle) {
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

$tests['local smoke tool verifies key routes api and unknown route 404'] = function (): void {
    $tool = app_path('tools/smoke-local.php');
    assertTrue(is_file($tool), 'Local route/API smoke tool should exist');
    $output = [];
    $status = 0;
    exec('php ' . escapeshellarg($tool) . ' 2>&1', $output, $status);
    assertSame(0, $status, "Local smoke tool should pass:\n" . implode("\n", $output));
};

$tests['systematic project map is the only generated map artifact'] = function (): void {
    assertTrue(is_file(app_path('docs/systematic-map.mmd')), 'Single systematic Mermaid map should exist');
    foreach (['docs/PROJECT_MAP.md', 'docs/project-map.json', 'docs/project-map.mmd'] as $path) {
        assertTrue(!is_file(app_path($path)), "Old project-map artifact should not exist: {$path}");
    }
    $map = file_get_contents(app_path('docs/systematic-map.mmd'));
    foreach (['PUBLIC Routes', 'AUTH Routes', 'PAYMENT Routes', 'SUPPORT Routes', 'ADMIN Routes', 'Controllers', 'Services', 'Views', 'Integrations', 'Schema Collections', 'Storage Data Files', 'Tools', 'Gaps & Missing Links'] as $needle) {
        assertTrue(str_contains($map, $needle), "Systematic map should include {$needle}");
    }
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
