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
                <strong>Phone</strong>
                <p><?= e($order['customer_phone'] ?? 'Not recorded') ?></p>
            </div>
            <div>
                <strong>Payment</strong>
                <p><?= e($order['payment_id'] ?? 'Not recorded') ?></p>
            </div>
            <div>
                <strong>Shipping Address</strong>
                <p>
                    <?= e($order['shipping_address'] ?? 'Not recorded') ?><br>
                    <?= e($order['shipping_city'] ?? '') ?> <?= e($order['shipping_pincode'] ?? '') ?>
                </p>
            </div>
        </div>
        <form method="post" action="/admin/orders/<?= e((string)($order['id'] ?? '')) ?>/status" style="margin:var(--space-lg) 0; display:flex; gap:var(--space-sm); align-items:end; flex-wrap:wrap;" onsubmit="if(document.getElementById('order-status').value==='cancelled'&&!confirm('Cancel this order? This will mark it as cancelled and cannot be undone.'))return false">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div>
                <label for="order-status" style="display:block; font-size:0.78rem; font-weight:700; text-transform:uppercase; color:var(--color-text-muted); margin-bottom:var(--space-xs);">Update Status</label>
                <select id="order-status" name="status">
                    <?php foreach(['confirmed','processing','shipped','delivered','cancelled'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= (($order['status'] ?? '') === $status ? 'selected' : '') ?>><?= e(ucfirst($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Save Status</button>
        </form>
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
