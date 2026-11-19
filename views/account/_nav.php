<?php
$accountPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$ordersPage = $accountPath === '/account/dashboard/orders';
$orderDetail = str_starts_with($accountPath, '/account/orders/');
?>
<nav class="account-nav" aria-label="Account navigation">
    <a href="/account/dashboard/orders"<?= $ordersPage || $orderDetail ? ' class="active" aria-current="' . ($ordersPage ? 'page' : 'true') . '"' : '' ?>>My Orders</a>
    <a href="/account/dashboard/addresses"<?= $accountPath === '/account/dashboard/addresses' ? ' class="active" aria-current="page"' : '' ?>>Saved Addresses</a>
    <?php if (($_SESSION['user']['sub'] ?? '') !== 'env-admin'): ?>
        <a href="/account/dashboard/profile"<?= $accountPath === '/account/dashboard/profile' ? ' class="active" aria-current="page"' : '' ?>>My Profile</a>
    <?php endif; ?>
    <a href="/account/dashboard/install"<?= str_starts_with($accountPath, '/account/dashboard/install') ? ' class="active" aria-current="page"' : '' ?>>Install App</a>
    <a href="/">Back to Home</a>
    <a href="/logout">Sign out</a>
</nav>
