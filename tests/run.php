<?php
require __DIR__ . '/../app/bootstrap.php';

use App\Services\JsonStoreService;
use App\Services\AvailabilityService;
use App\Services\EnvService;
use App\Services\PaymentService;
use App\Services\ProjectMapService;
use App\Services\ReviewService;

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
    $astrologersView = file_get_contents(app_path('views/public/astrologers.php'));
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

$tests['astrologer marketplace exposes credit balance filters and direct session actions'] = function (): void {
    $view = file_get_contents(app_path('views/public/astrologers.php'));
    foreach (['Available Balance', 'Recharge', 'Filters', 'Available Now', 'On Chat', 'Search Astrologer'] as $needle) {
        assertTrue(str_contains($view, $needle), "Astrologer marketplace should expose {$needle}");
    }
    foreach (['aria-label="Start message session"', 'aria-label="Start call session"', 'Waitlist', 'OFFLINE', '+ Follow'] as $needle) {
        assertTrue(str_contains($view, $needle), "Astrologer marketplace should expose {$needle} actions");
    }
    assertTrue(str_contains($view, 'astro-action-row'), 'Message and call icon buttons should sit below each astrologer card content');
    assertTrue(!str_contains($view, 'Check Availability'), 'Astrologer marketplace should not use appointment availability CTA');
};

$tests['astrologer profile exposes competitor style remote action rating and trust panels'] = function (): void {
    $view = file_get_contents(app_path('views/public/astrologer.php'));
    foreach (['Flat Deal', 'aria-label="Start message session"', 'aria-label="Start call session"', 'BOOK SESSION', 'Ratings', 'Money Back Guarantee', 'Verified Expert Astrologers', '100% Secure Payments', 'Send gifts'] as $needle) {
        assertTrue(str_contains($view, $needle), "Astrologer profile should expose {$needle}");
    }
    assertTrue(str_contains($view, '5 credits/message'), 'Astrologer profile should explain message credit cost');
    assertTrue(str_contains($view, '0.5 credits/sec call'), 'Astrologer profile should explain call credit cost');
};

$tests['home page rotates all astrologers instead of showing only three fixed cards'] = function (): void {
    $view = file_get_contents(app_path('views/public/home.php'));
    assertTrue(!str_contains($view, 'array_slice($astrologers, 0, 3)'), 'Home astrology section should not hard-limit to three astrologers');
    assertTrue(str_contains($view, 'astro-carousel-track'), 'Home astrology section should use a carousel track');
};

$tests['home hero uses concise current copy and working cta links'] = function (): void {
    $view = file_get_contents(app_path('views/public/home.php'));
    assertTrue(!str_contains($view, 'Spiritual Products Online in Chennai'), 'Home hero headline should not say products online in Chennai');
    assertTrue(!str_contains($view, 'Shop Spiritual Products</a>'), 'Home hero primary button should use shorter text');
    assertTrue(!str_contains($view, 'Remote Astrology Consultation</a>'), 'Home hero astrology button should use shorter text');
    foreach (['href="/shop"', 'href="/astrologers"', '>Shop</a>', '>Astrology</a>'] as $needle) {
        assertTrue(str_contains($view, $needle), "Home hero should include {$needle}");
    }
    assertTrue(!str_contains($view, '<div class="hero-stat-value">3</div>'), 'Home hero astrologer count should not be stale');
    assertTrue(str_contains($view, 'count($astrologers)'), 'Home hero astrologer count should be derived from the current catalog');
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

$tests['astrologer catalog has thirteen editable priced profiles'] = function (): void {
    $astrologers = (new JsonStoreService())->read('astrologers');
    assertSame(13, count($astrologers), 'Astrologer seed data should include the original 3 plus 10 more profiles');
    foreach ($astrologers as $astrologer) {
        assertTrue(!empty($astrologer['slug']), 'Every astrologer should have a slug');
        assertTrue(str_contains($astrologer['photo_url'] ?? '', '/indian-portrait-'), 'Astrologer profile images should use local Indian-style profile artwork');
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

$tests['architecture and deployment docs describe current php template stack'] = function (): void {
    $readme = file_get_contents(app_path('README.md'));
    $architecture = file_get_contents(app_path('docs/architecture.md'));
    $deployment = file_get_contents(app_path('docs/deployment-hostinger.md'));
    foreach ([$architecture, $deployment] as $doc) {
        assertTrue(!str_contains($doc, 'React'), 'Docs should not describe the removed React/CDN architecture');
        assertTrue(!str_contains($doc, 'CDN'), 'Docs should not say the app loads React from a CDN');
    }
    foreach (['small PHP hosting', 'public_html', 'JSON-backed backend', '.env', 'ADMIN_EMAIL', 'ADMIN_PASSWORD', 'agentic development'] as $needle) {
        assertTrue(str_contains($readme, $needle), "README should describe {$needle}");
    }
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

$tests['documentation has deployment agent instructions and no one-line placeholder pages'] = function (): void {
    assertTrue(is_file(app_path('example-Agent.md')), 'Agent workflow guide should exist');
    $agent = file_get_contents(app_path('example-Agent.md'));
    foreach (['Hostinger', 'Advanced', 'Git', 'Auto Deployment', 'main', 'project map', 'php tests/run.php', 'php tools/smoke-local.php', 'commit'] as $needle) {
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
