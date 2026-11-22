<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
putenv('BAPX_TEST_MODE=1');
require dirname(__DIR__, 2) . '/app/bootstrap.php';
// Only synthetic view data. No catalogue, gateway, session identity or business writes.
$_SESSION = [];
$items = [['product'=>['name'=>'Fixture Ring','image_url'=>'/assets/images/fixture-ring.webp','gst_rate'=>5],
    'slug'=>'fixture-ring','name'=>'Fixture Ring','qty'=>1,'unit_price'=>1.15,'line_total'=>1.15]];
$total = 1.15;
$addresses = [];
$settings = ['gst_state'=>'Tamil Nadu'];
$razorpayReady = true;
$secrets = ['razorpay_key_id'=>'rzp_test_fixture_public','razorpay_mode'=>'test'];
require app_path('views/public/checkout.php');
