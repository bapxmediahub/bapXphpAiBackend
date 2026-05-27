<div class="section" style="padding-top:var(--space-xl);">
    <div class="account-layout">
        <aside class="account-nav">
            <a href="/account/orders">📦 My Orders</a>
            <a href="/account/bookings" class="active">📅 My Bookings</a>
            <a href="/">← Back to Home</a>
        </aside>
        <div class="account-content">
            <h1>My Bookings</h1>
            <?php if(empty($bookings)): ?>
                <div style="text-align:center; padding:var(--space-2xl);">
                    <span style="font-size:2.5rem; display:block; margin-bottom:var(--space-md);">📅</span>
                    <h3 style="font-family:var(--font-serif); margin:0 0 var(--space-sm);">No Bookings Yet</h3>
                    <p style="color:var(--color-text-muted); margin-bottom:var(--space-lg);">Book a session with one of our expert astrologers.</p>
                    <a href="/astrologers" class="btn btn-primary">Browse Astrologers</a>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Date</th><th>Time</th><th>Astrologer</th><th>Mode</th><th>Status</th><th>Meeting</th></tr></thead>
                        <tbody>
                        <?php foreach($bookings as $booking): ?>
                            <tr>
                                <td><?= e($booking['date'] ?? '') ?></td>
                                <td><?= e($booking['time'] ?? '') ?></td>
                                <td><?= e($booking['astrologer_name'] ?? $booking['astrologer_slug'] ?? '') ?></td>
                                <td><span class="badge badge--info"><?= e(ucfirst(str_replace('-', ' ', $booking['mode'] ?? ''))) ?></span></td>
                                <td>
                                    <?php $status = $booking['status'] ?? ''; ?>
                                    <span class="badge badge--<?= $status === 'confirmed' ? 'success' : ($status === 'payment_pending' ? 'warning' : 'default') ?>"><?= e(ucfirst(str_replace('_', ' ', $status))) ?></span>
                                </td>
                                <td><?php if(!empty($booking['meeting_link'])): ?><a href="<?= e($booking['meeting_link']) ?>" target="_blank" class="btn btn-sm btn-outline">Join ↗</a><span class="sr-only">External link</span><?php else: ?><span style="color:var(--color-text-light);">—</span><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
