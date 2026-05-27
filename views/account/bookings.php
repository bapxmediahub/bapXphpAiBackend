<div class="section" style="padding-top:var(--space-xl);">
    <div class="account-layout">
        <aside class="account-nav">
            <a href="/account/orders" class="<?= (strpos($_SERVER['REQUEST_URI'], '/account/orders') === 0 ? 'active' : '') ?>">📦 My Orders</a>
            <a href="/account/bookings" class="<?= (strpos($_SERVER['REQUEST_URI'], '/account/bookings') === 0 ? 'active' : '') ?>">📅 My Bookings</a>
            <a href="/">← Back to Home</a>
        </aside>
        <div class="account-content">
            <h1>My Bookings</h1>
            <?php if(empty($bookings)): ?>
                <div style="text-align:center; padding:var(--space-2xl);">
                    <span style="font-size:2.5rem; display:block; margin-bottom:var(--space-md);">📅</span>
                    <h3 style="font-family:var(--font-serif); margin:0 0 var(--space-sm);">No Bookings Yet</h3>
                    <p style="color:var(--color-text-muted); margin-bottom:var(--space-lg);">Book a session with our expert astrologers.</p>
                    <a href="/astrologers" class="btn btn-primary">Browse Astrologers</a>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Date</th><th>Time</th><th>Astrologer</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach($bookings as $booking): ?>
                            <tr>
                                <td><?= e($booking['date'] ?? '') ?></td>
                                <td><?= e($booking['time'] ?? '') ?></td>
                                <td><?= e($booking['astrologer_name'] ?? $booking['astrologer_slug'] ?? '') ?></td>
                                <td>
                                    <?php $status = $booking['status'] ?? ''; ?>
                                    <span class="badge badge--<?= $status === 'confirmed' ? 'success' : ($status === 'payment_pending' ? 'warning' : 'default') ?>"><?= e(ucfirst(str_replace('_', ' ', $status))) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>