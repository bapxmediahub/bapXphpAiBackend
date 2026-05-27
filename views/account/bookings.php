<div class="section" style="padding-top:var(--space-xl);">
    <div class="account-layout">
        <aside class="account-nav">
            <a href="/account/orders" class="<?= (strpos($_SERVER['REQUEST_URI'], '/account/orders') === 0 ? 'active' : '') ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:6px;"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                My Orders
            </a>
            <a href="/account/bookings" class="<?= (strpos($_SERVER['REQUEST_URI'], '/account/bookings') === 0 ? 'active' : '') ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:6px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                My Bookings
            </a>
            <a href="/">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:6px;"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Back to Home
            </a>
        </aside>
        <div class="account-content">
            <h1>My Bookings</h1>
            <?php if(empty($bookings)): ?>
                <div style="text-align:center; padding:var(--space-2xl);">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto var(--space-md);"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
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