<?php
require __DIR__ . '/../app/bootstrap.php';

use App\Services\JsonStoreService;
use App\Services\AvailabilityService;
use App\Services\PaymentService;
use App\Services\ProjectMapService;

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

$tests['availability excludes booked slots'] = function (): void {
    $service = new AvailabilityService();
    $astrologer = [
        'working_days' => ['monday'],
        'start_time' => '09:00',
        'end_time' => '11:00',
        'slot_minutes' => 60,
    ];
    $slots = $service->slotsForDate($astrologer, '2026-05-18', [['date' => '2026-05-18', 'time' => '09:00']]);
    assertSame(['10:00'], $slots, 'Booked slots should be removed');
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
    assertSame([], $validation['missing_collections'], 'Collections should be declared');
};

$tests['local development router serves existing static files directly'] = function (): void {
    $index = file_get_contents(app_path('index.php'));
    assertTrue(str_contains($index, "PHP_SAPI === 'cli-server'"), 'Router should detect PHP built-in server');
    assertTrue(str_contains($index, 'is_file($file)'), 'Router should return static files directly during local development');
    assertTrue(str_contains($index, 'return false'), 'Router should let the built-in server serve existing static assets');
};

$tests['catalog image paths point to existing local assets'] = function (): void {
    $store = new JsonStoreService();
    foreach (['products', 'categories', 'temples'] as $collection) {
        foreach ($store->read($collection) as $item) {
            $image = $item['image_url'] ?? '';
            if ($image === '' || str_starts_with($image, 'http')) continue;
            $path = parse_url($image, PHP_URL_PATH);
            assertTrue(is_file(app_path($path)), "{$collection} image should exist: {$image}");
        }
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
};

$tests['frontend router and api error tests pass'] = function (): void {
    $output = [];
    $status = 0;
    exec('node ' . escapeshellarg(app_path('tests/frontend.test.js')) . ' 2>&1', $output, $status);
    assertSame(0, $status, "Frontend regression tests should pass:\n" . implode("\n", $output));
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

$tests['astrologer profile uses remote consultation contact panel instead of appointment slot forms'] = function (): void {
    $view = file_get_contents(app_path('views/public/astrologer.php'));
    assertTrue(!str_contains($view, 'slot-picker'), 'Astrologer profile should not render appointment slot picker UI');
    assertTrue(!str_contains($view, 'Available Slots'), 'Astrologer profile should not show cinema-style appointment slots');
    assertTrue(!str_contains($view, 'action="/appointments/book"'), 'Astrologer profile should not post appointment bookings from slot cards');
    assertTrue(str_contains($view, '/contact'), 'Astrologer profile should direct consultation requests to the contact page');
    assertTrue(str_contains($view, 'Remote Call') || str_contains($view, 'Remote consultation'), 'Astrologer profile should describe remote call/message consultation');
};

$tests['astrologer marketplace exposes credit balance filters and direct session actions'] = function (): void {
    $view = file_get_contents(app_path('views/public/astrologers.php'));
    foreach (['Available Balance', 'Recharge', 'Filters', 'Available Now', 'On Chat', 'Search Astrologer'] as $needle) {
        assertTrue(str_contains($view, $needle), "Astrologer marketplace should expose {$needle}");
    }
    foreach (['CHAT', 'CALL', 'JOIN Q', 'OFFLINE', '+ Follow'] as $needle) {
        assertTrue(str_contains($view, $needle), "Astrologer marketplace should expose {$needle} actions");
    }
    assertTrue(!str_contains($view, 'Check Availability'), 'Astrologer marketplace should not use appointment availability CTA');
};

$tests['astrologer profile exposes competitor style remote action rating and trust panels'] = function (): void {
    $view = file_get_contents(app_path('views/public/astrologer.php'));
    foreach (['Flat Deal', 'CHAT', 'CALL', 'BOOK SESSION', 'Ratings', 'Money Back Guarantee', 'Verified Expert Astrologers', '100% Secure Payments', 'Send gifts'] as $needle) {
        assertTrue(str_contains($view, $needle), "Astrologer profile should expose {$needle}");
    }
    assertTrue(str_contains($view, '5 credits/message'), 'Astrologer profile should explain message credit cost');
    assertTrue(str_contains($view, '0.5 credits/sec call'), 'Astrologer profile should explain call credit cost');
};

$tests['astrologer catalog has thirteen editable priced profiles'] = function (): void {
    $astrologers = (new JsonStoreService())->read('astrologers');
    assertSame(13, count($astrologers), 'Astrologer seed data should include the original 3 plus 10 more profiles');
    foreach ($astrologers as $astrologer) {
        assertTrue(!empty($astrologer['slug']), 'Every astrologer should have a slug');
        assertSame(15, (int)($astrologer['text_session_prm'] ?? 0), 'Text session PRM should default to 15');
        assertSame(15, (int)($astrologer['call_session_prm'] ?? 0), 'Call session PRM should default to 15');
        assertSame(5, (int)($astrologer['message_credit_cost'] ?? 0), 'Message session should cost 5 credits per user message');
        assertSame(0.5, (float)($astrologer['call_credit_per_second'] ?? 0), 'Call session should cost 0.5 credits per second');
    }
};

$tests['admin product and astrologer forms expose editable owner fields'] = function (): void {
    $controller = file_get_contents(app_path('app/Controllers/AdminController.php'));
    foreach (['slug', 'image_url', 'price', 'offer_price', 'stock_status'] as $field) {
        assertTrue(str_contains($controller, "'{$field}'"), "Product admin form should expose {$field}");
    }
    foreach (['slug', 'message_credit_cost', 'call_credit_per_second', 'text_session_prm', 'call_session_prm', 'payout_percentage', 'languages', 'working_days'] as $field) {
        assertTrue(str_contains($controller, "'{$field}'"), "Astrologer admin form should expose {$field}");
    }
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
