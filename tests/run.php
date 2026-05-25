<?php
require __DIR__ . '/../App/bootstrap.php';

use App\Services\JsonStoreService;
use App\Services\AvailabilityService;
use App\Services\PaymentService;
use App\Services\ProjectMapService;
use App\Services\PasswordResetService;
use App\Services\SmtpMailer;
use App\Services\ProductService;
use App\Services\CategoryService;

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

$tests['calendar reminders preserve previous-day-evening rule'] = function (): void {
    $minutes = (new App\Services\CalendarService())->remindersFor('2026-05-18 10:00:00');
    assertSame([960,60,0], $minutes, 'Calendar reminders should include previous evening, one hour, and start time');
};

$tests['project map registry has no missing route mappings'] = function (): void {
    $map = ProjectMapService::registry();
    $validation = ProjectMapService::validate($map);
    assertSame([], $validation['missing_route_mappings'], 'Routes should map to controllers');
    assertSame([], $validation['missing_collections'], 'Collections should be declared');
};

$tests['smtp mailer builds authenticated password reset messages'] = function (): void {
    $mailer = new SmtpMailer([
        'smtp_from_email' => 'support@example.com',
        'smtp_from_name' => 'Sri Panchami Spiritual',
        'admin_notification_email' => 'admin@example.com',
    ]);
    $message = $mailer->buildMessage('customer@example.com', 'Reset your password', '<p>Reset link</p>');
    assertTrue(str_contains($message, 'To: customer@example.com'), 'Message should target customer email');
    assertTrue(str_contains($message, 'From: Sri Panchami Spiritual <support@example.com>'), 'Message should use configured sender');
    assertTrue(str_contains($message, 'Reply-To: admin@example.com'), 'Message should use admin notification reply-to');
    assertTrue(!str_contains($message, 'smtp_password'), 'Message must not expose SMTP secrets');
};

$tests['password reset tokens update only the matching user password'] = function (): void {
    $dir = sys_get_temp_dir() . '/sps-reset-' . bin2hex(random_bytes(4));
    $store = new JsonStoreService($dir);
    $store->write('users', [
        ['id' => 'u1', 'email' => 'one@example.com', 'name' => 'One', 'password_hash' => password_hash('old', PASSWORD_DEFAULT)],
        ['id' => 'u2', 'email' => 'two@example.com', 'name' => 'Two', 'password_hash' => password_hash('old', PASSWORD_DEFAULT)],
    ]);

    $service = new PasswordResetService($store);
    $token = $service->createToken('one@example.com', new DateTimeImmutable('2026-05-25 10:00:00'));
    assertTrue($token !== null && strlen($token) >= 32, 'Existing users should receive a reset token');
    assertTrue($service->resetPassword($token, 'new-secret', new DateTimeImmutable('2026-05-25 10:10:00')), 'Valid token should reset password');

    $users = $store->read('users');
    assertTrue(password_verify('new-secret', $users[0]['password_hash']), 'Matching user password should be updated');
    assertTrue(password_verify('old', $users[1]['password_hash']), 'Other user password should remain unchanged');
    assertTrue(empty($users[0]['reset_token_hash']), 'Used reset token should be cleared');
};

$tests['project map includes forgot password and smtp admin routes'] = function (): void {
    $routes = [];
    foreach (ProjectMapService::registry()['routes'] as $route) {
        $routes[$route['method'] . ' ' . $route['path']] = $route['controller'];
    }
    assertSame('AuthController@forgotPassword', $routes['GET /forgot-password'] ?? null, 'Forgot password form route should exist');
    assertSame('AuthController@forgotPasswordPost', $routes['POST /forgot-password'] ?? null, 'Forgot password submit route should exist');
    assertSame('AuthController@resetPassword', $routes['GET /reset-password'] ?? null, 'Reset password form route should exist');
    assertSame('AuthController@resetPasswordPost', $routes['POST /reset-password'] ?? null, 'Reset password submit route should exist');
    assertSame('AdminController@saveSettings', $routes['POST /admin/settings/save'] ?? null, 'Admin settings save route should exist');
    assertSame('AdminController@testSmtp', $routes['POST /admin/settings/test-smtp'] ?? null, 'Admin SMTP test route should exist');
};

$tests['seed catalog has editable devotional categories and products'] = function (): void {
    $categories = (new CategoryService())->all();
    $products = (new ProductService())->all();
    assertTrue(count($categories) >= 6, 'Shop should launch with competitor-informed editable categories');
    assertTrue(count($products) >= 8, 'Shop should launch with editable sample products');
    assertTrue(in_array('Spiritual Jewelry', array_column($categories, 'name'), true), 'Spiritual Jewelry category should exist');
    assertTrue(in_array('Yantras', array_column($categories, 'name'), true), 'Yantras category should exist');
    foreach ($products as $product) {
        assertTrue(!empty($product['slug']) && !empty($product['name']) && !empty($product['description']), 'Seed products need slug, name, and description');
        assertTrue((int)($product['price'] ?? 0) > 0, 'Seed products need a positive INR price');
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
