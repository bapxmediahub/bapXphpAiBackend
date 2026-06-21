<?php $accountPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; ?>
<aside class="account-nav" aria-label="Account navigation">
    <a href="/account/orders"<?= str_starts_with($accountPath, '/account/orders') ? ' class="active" aria-current="page"' : '' ?>>My Orders</a>
    <a href="/account/bookings"<?= str_starts_with($accountPath, '/account/bookings') ? ' class="active" aria-current="page"' : '' ?>>My Sessions</a>
    <a href="/account/wallet"<?= (str_starts_with($accountPath, '/account/wallet') || str_starts_with($accountPath, '/recharge')) ? ' class="active" aria-current="page"' : '' ?>>Wallet</a>
    <a href="/">Back to Home</a>
</aside>
