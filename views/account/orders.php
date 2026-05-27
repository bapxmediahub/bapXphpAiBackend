<div class="section" style="padding-top:var(--space-xl);">
    <div class="account-layout">
        <aside class="account-nav">
            <a href="/account/orders" class="active">📦 My Orders</a>
            <a href="/account/bookings">📅 My Bookings</a>
            <a href="/">← Back to Home</a>
        </aside>
        <div class="account-content">
            <h1>My Orders</h1>
            <?php if(empty($orders)): ?>
                <div style="text-align:center; padding:var(--space-2xl);">
                    <span style="font-size:2.5rem; display:block; margin-bottom:var(--space-md);">📦</span>
                    <h3 style="font-family:var(--font-serif); margin:0 0 var(--space-sm);">No Orders Yet</h3>
                    <p style="color:var(--color-text-muted); margin-bottom:var(--space-lg);">Start exploring our spiritual products.</p>
                    <a href="/shop" class="btn btn-primary">Browse Shop</a>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Order ID</th><th>Status</th><th>Total</th><th>Email</th></tr></thead>
                        <tbody>
                        <?php foreach($orders as $order): ?>
                            <tr>
                                <td><code style="font-size:0.8rem; background:var(--color-bg-alt); padding:0.2rem 0.5rem; border-radius:var(--color-ink);"><?= e(substr($order['id'] ?? '', 0, 12)) ?></code></td>
                                <td><span class="badge badge--default"><?= e(ucfirst($order['status'] ?? 'pending')) ?></span></td>
                                <td style="font-weight:600; color:var(--color-maroon);">₹<?= e((string)($order['total'] ?? 0)) ?></td>
                                <td style="font-size:0.85rem; color:var(--color-text-muted);"><?= e($order['customer_email'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
