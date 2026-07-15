<?php $accountPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; ?>
<aside class="account-nav" aria-label="Account navigation">
    <a href="/account/dashboard/orders"<?= str_starts_with($accountPath, '/account/dashboard/orders') ? ' class="active" aria-current="page"' : '' ?>>My Orders</a>
    <a href="/account/dashboard/sessions"<?= str_starts_with($accountPath, '/account/dashboard/sessions') ? ' class="active" aria-current="page"' : '' ?>>My Sessions</a>
    <a href="/account/dashboard/install"<?= str_starts_with($accountPath, '/account/dashboard/install') ? ' class="active" aria-current="page"' : '' ?>>Install App</a>
    <a href="/">Back to Home</a>
</aside>
