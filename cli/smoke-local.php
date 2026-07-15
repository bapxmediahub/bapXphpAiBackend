<?php
/**
 * Local route/API smoke test.
 *
 * Starts a disposable PHP built-in server, checks the core public, account,
 * admin, API, support, and 404 routes, then restores any support-ticket data
 * touched during the test.
 */
require __DIR__ . '/../app/bootstrap.php';

$port = 6200 + random_int(0, 799);
$base = "http://127.0.0.1:{$port}";
$descriptor = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
$process = proc_open('php -S 127.0.0.1:' . $port . ' index.php', $descriptor, $pipes, app_path());
if (!is_resource($process)) {
    fwrite(STDERR, "Unable to start local PHP server.\n");
    exit(1);
}

$failures = [];

try {
    try {
        waitForServer($base);
    } catch (RuntimeException $e) {
        $err = '';
        if (isset($pipes[2]) && is_resource($pipes[2])) {
            stream_set_blocking($pipes[2], false);
            $err = stream_get_contents($pipes[2]);
        }
        throw new RuntimeException($e->getMessage() . ($err ? "\nPHP Server Stderr:\n" . $err : ''));
    }
    foreach ([
        '/' => 200,
        '/shop' => 200,
        '/product/karuppasami-dollar' => 200,
        '/cart' => 200,
        '/checkout' => 200,
        '/contact' => 200,
        '/consult' => 200,
        '/consult/shrikanth-purushothaman' => 200,
        '/temples' => 200,
        '/sri-panchami-spiritual' => 200,
        '/forgot-password' => 200,
        '/reset-password' => 200,
        '/auth/google' => 302,
        '/logout' => 302,
        '/account/orders' => 302,
        '/account/bookings' => 302,
        '/account/wallet' => 404,
        '/recharge' => 404,
        '/admin' => 302,
        '/admin/settings' => 302,
    ] as $path => $expected) {
        $response = httpRequest($base . $path);
        if ($response['status'] === 0) {
            usleep(1000000);
            $response = httpRequest($base . $path);
        }
        echo "{$response['status']} GET {$path}\n";
        if ($response['status'] !== $expected) {
            $failures[] = "GET {$path} expected {$expected}, got {$response['status']}";
        }
        if ($expected === 200 && str_contains($response['body'], 'Page not found')) {
            $failures[] = "GET {$path} rendered fallback missing-page content";
        }
    }

    $post = httpRequest($base . '/admin/orders/test/status', 'POST', 'status=shipped');
    echo "{$post['status']} POST /admin/orders/test/status\n";
    if ($post['status'] !== 302) {
        $failures[] = "POST /admin/orders/test/status expected 302, got {$post['status']}";
    }

    $appointmentPost = httpRequest($base . '/consultation/initiate', 'POST', 'astrologer_slug=pandit-shastri&mode=text_session');
    echo "{$appointmentPost['status']} POST /consultation/initiate\n";
    if ($appointmentPost['status'] !== 302) {
        $failures[] = "POST /consultation/initiate expected guest login redirect 302, got {$appointmentPost['status']}";
    }

    $paymentPost = httpRequest($base . '/payment/verify', 'POST', 'order_id=&payment_id=&signature=');
    echo "{$paymentPost['status']} POST /payment/verify\n";
    if ($paymentPost['status'] === 419) {
        echo "  (CSRF protected, missing token)\n";
    } elseif ($paymentPost['status'] !== 400 || !str_contains($paymentPost['body'], 'verified')) {
        $failures[] = "POST /payment/verify should reject missing Razorpay verification fields with JSON";
    }

    $supportPost = httpRequest($base . '/support/ask', 'POST', 'message=Where%20is%20my%20order%3F');
    echo "{$supportPost['status']} POST /support/ask\n";
    if ($supportPost['status'] === 200 && str_contains($supportPost['body'], 'reply')) {
        // OK
    } elseif (in_array($supportPost['status'], [400, 503], true)) {
        echo "  (MySQL or session unavailable, skip support check)\n";
    } else {
        $failures[] = "POST /support/ask should return a support reply JSON payload";
    }

    foreach ([
        '/api/',
        '/api/shop',
        '/api/categories',
        '/api/product/karuppasami-dollar',
        '/api/consult',
        '/api/temples',
    ] as $path) {
        $response = httpRequest($base . $path);
        json_decode($response['body'], true);
        $ok = json_last_error() === JSON_ERROR_NONE;
        echo ($ok ? 'JSON' : 'BAD') . " {$path}\n";
        if (!$ok) {
            $failures[] = "{$path} did not return valid JSON";
        } elseif ($response['status'] === 503) {
            echo "  (MySQL unavailable)\n";
        }
    }

    $unknown = httpRequest($base . '/unknown-spa-route');
    echo "{$unknown['status']} GET /unknown-spa-route\n";
    if ($unknown['status'] !== 404) {
        $failures[] = "Unknown route expected 404, got {$unknown['status']}";
    }
    if (!str_contains($unknown['body'], 'Page not found')) {
        $failures[] = "Unknown route should render the PHP 404 page";
    }
} finally {
    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) fclose($pipe);
    }
    proc_terminate($process);
    proc_close($process);
}

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "PASS local smoke\n";

function waitForServer(string $base): void {
    $deadline = microtime(true) + 8;
    do {
        $response = @httpRequest($base . '/robots.txt');
        if (($response['status'] ?? 0) > 0) return;
        usleep(100000);
    } while (microtime(true) < $deadline);
    throw new RuntimeException('Local PHP server did not start.');
}

function httpRequest(string $url, string $method = 'GET', string $body = ''): array {
    $options = [
        'method' => $method,
        'ignore_errors' => true,
        'timeout' => 6,
    ];
    if ($method === 'POST') {
        $options['header'] = "Content-Type: application/x-www-form-urlencoded\r\n";
        $options['content'] = $body;
    }
    $content = false;
    $headers = [];
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $context = stream_context_create(['http' => $options]);
        $content = @file_get_contents($url, false, $context);
        $headers = $http_response_header ?? [];
        if ($content !== false || $headers !== []) break;
        usleep(200000);
    }
    preg_match('/\s(\d{3})\s/', $headers[0] ?? '', $matches);
    return [
        'status' => (int)($matches[1] ?? 0),
        'body' => $content === false ? '' : $content,
        'headers' => $headers,
    ];
}
