<?php
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
$supportTicketsFile = storage_path('data/support_tickets.json');
$hadSupportTickets = is_file($supportTicketsFile);
$supportTicketsBefore = $hadSupportTickets ? file_get_contents($supportTicketsFile) : null;

try {
    waitForServer($base);
    foreach ([
        '/' => 200,
        '/shop' => 200,
        '/product/karuppasami-dollar' => 200,
        '/cart' => 200,
        '/checkout' => 200,
        '/contact' => 200,
        '/consult' => 200,
        '/consult/pandit-shastri' => 200,
        '/temples' => 200,
        '/sri-panchami-spiritual' => 200,
        '/forgot-password' => 200,
        '/reset-password' => 200,
        '/auth/google' => 302,
        '/logout' => 302,
        '/account/orders' => 302,
        '/account/bookings' => 302,
        '/account/wallet' => 302,
        '/recharge' => 302,
        '/admin' => 302,
        '/admin/settings' => 302,
    ] as $path => $expected) {
        $response = httpRequest($base . $path);
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

    $appointmentPost = httpRequest($base . '/appointments/book', 'POST', 'astrologer_slug=pandit-shastri&mode=text_session');
    echo "{$appointmentPost['status']} POST /appointments/book\n";
    if ($appointmentPost['status'] !== 302) {
        $failures[] = "POST /appointments/book expected guest login redirect 302, got {$appointmentPost['status']}";
    }

    $paymentPost = httpRequest($base . '/payment/verify', 'POST', 'order_id=&payment_id=&signature=');
    echo "{$paymentPost['status']} POST /payment/verify\n";
    if ($paymentPost['status'] !== 200 || !str_contains($paymentPost['body'], 'verified')) {
        $failures[] = "POST /payment/verify should return JSON verification status through PHP routing";
    }

    $supportPost = httpRequest($base . '/support/ask', 'POST', 'message=Where%20is%20my%20order%3F');
    echo "{$supportPost['status']} POST /support/ask\n";
    if ($supportPost['status'] !== 200 || !str_contains($supportPost['body'], 'reply')) {
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
        }
    }

    foreach (['/assets/js/app.js', '/assets/js/components.js', '/assets/js/pages.js', '/assets/js/main.js'] as $path) {
        $response = httpRequest($base . $path);
        echo "{$response['status']} GET {$path}\n";
        if ($response['status'] !== 404) {
            $failures[] = "Removed SPA asset {$path} expected 404, got {$response['status']}";
        }
    }

    $unknown = httpRequest($base . '/unknown-spa-route');
    echo "{$unknown['status']} GET /unknown-spa-route\n";
    if ($unknown['status'] !== 404) {
        $failures[] = "Unknown route expected 404, got {$unknown['status']}";
    }
    if (!str_contains($unknown['body'], 'Page Not Found')) {
        $failures[] = "Unknown route should render the PHP 404 page";
    }
} finally {
    if ($hadSupportTickets && $supportTicketsBefore !== false && $supportTicketsBefore !== null) {
        file_put_contents($supportTicketsFile, $supportTicketsBefore);
    } elseif (!$hadSupportTickets && is_file($supportTicketsFile)) {
        unlink($supportTicketsFile);
    }
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
        $response = @httpRequest($base . '/');
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
    $context = stream_context_create(['http' => $options]);
    $content = file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', $headers[0] ?? '', $matches);
    return [
        'status' => (int)($matches[1] ?? 0),
        'body' => $content === false ? '' : $content,
        'headers' => $headers,
    ];
}
