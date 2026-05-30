<div class="admin-card">
    <h2 style="font-size:1.1rem; margin:0 0 var(--space-md);"><?= e($title) ?></h2>
    <?php if(empty($order)): ?>
        <p style="color:var(--color-text-muted);">Order not found.</p>
    <?php else: ?>
        <div class="admin-detail-grid">
            <div>
                <strong>Status</strong>
                <p><?= e(ucfirst(str_replace('_', ' ', (string)($order['status'] ?? 'pending')))) ?></p>
            </div>
            <div>
                <strong>Total</strong>
                <p>₹<?= e((string)($order['total'] ?? 0)) ?></p>
            </div>
            <div>
                <strong>Customer</strong>
                <p><?= e($order['customer_name'] ?? '') ?> <?= e($order['customer_email'] ?? '') ?></p>
            </div>
            <div>
                <strong>Payment</strong>
                <p><?= e($order['payment_id'] ?? 'Not recorded') ?></p>
            </div>
        </div>
        <h3 style="font-size:1rem; margin:var(--space-lg) 0 var(--space-sm);">Items</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Product</th><th>Qty</th><th>Total</th></tr></thead>
                <tbody>
                    <?php foreach(($order['items'] ?? []) as $item): ?>
                        <tr>
                            <td><?= e($item['name'] ?? $item['slug'] ?? 'Product') ?></td>
                            <td><?= e((string)($item['qty'] ?? 1)) ?></td>
                            <td>₹<?= e((string)($item['line_total'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
