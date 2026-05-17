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
