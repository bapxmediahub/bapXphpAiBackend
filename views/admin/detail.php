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

            <div id="ship-fields" style="flex:1 1 100%; display:none; gap:var(--space-sm); flex-wrap:wrap; padding:var(--space-md); margin-top:var(--space-sm); background:var(--color-bg-alt); border:1px solid var(--color-border); border-radius:var(--radius-md);">
                <p style="flex:1 1 100%; margin:0 0 var(--space-xs); font-size:0.82rem; color:var(--color-text-muted);">
                    Both fields are required to mark an order shipped. The customer receives them in the shipment email.
                </p>
                <div style="flex:1 1 200px;">
                    <label for="courier_name" style="display:block; font-size:0.78rem; font-weight:700; text-transform:uppercase; color:var(--color-text-muted); margin-bottom:var(--space-xs);">Courier</label>
                    <input id="courier_name" name="courier_name" type="text" placeholder="e.g. India Post, DTDC" style="width:100%;" value="<?= e((string)($order['courier_name'] ?? '')) ?>">
                </div>
                <div style="flex:1 1 200px;">
                    <label for="tracking_id" style="display:block; font-size:0.78rem; font-weight:700; text-transform:uppercase; color:var(--color-text-muted); margin-bottom:var(--space-xs);">Tracking ID *</label>
                    <input id="tracking_id" name="tracking_id" type="text" placeholder="Courier tracking number" style="width:100%;" value="<?= e((string)($order['tracking_id'] ?? '')) ?>">
                </div>
                <div style="flex:1 1 260px;">
                    <label for="tracking_url" style="display:block; font-size:0.78rem; font-weight:700; text-transform:uppercase; color:var(--color-text-muted); margin-bottom:var(--space-xs);">Tracking Page Link *</label>
                    <input id="tracking_url" name="tracking_url" type="url" placeholder="https://courier.example/track/123" style="width:100%;" value="<?= e((string)($order['tracking_url'] ?? '')) ?>">
                </div>
            </div>
        </form>
        <script>
        (function () {
            var sel = document.getElementById('order-status');
            var box = document.getElementById('ship-fields');
            var id  = document.getElementById('tracking_id');
            var url = document.getElementById('tracking_url');
            function sync() {
                var shipping = sel.value === 'shipped';
                box.style.display = shipping ? 'flex' : 'none';
                // Required only while shipping, so other status changes are not blocked.
                id.required = shipping;
                url.required = shipping;
            }
            sel.addEventListener('change', sync);
            sync();
        })();
        </script>
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
