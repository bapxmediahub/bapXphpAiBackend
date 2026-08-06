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
                    Pick the courier, then enter the tracking ID. The customer receives both in the shipment email,
                    with the courier's tracking page link.
                </p>
                <div style="flex:1 1 220px;">
                    <label for="courier_name" style="display:block; font-size:0.78rem; font-weight:700; text-transform:uppercase; color:var(--color-text-muted); margin-bottom:var(--space-xs);">Courier *</label>
                    <select id="courier_name" name="courier_name" style="width:100%;">
                        <option value="">Select courier…</option>
                        <?php foreach (\App\Services\CourierService::all() as $__courier => $__url): ?>
                            <option value="<?= e($__courier) ?>" data-url="<?= e($__url) ?>"
                                <?= (($order['courier_name'] ?? '') === $__courier ? 'selected' : '') ?>><?= e($__courier) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:1 1 220px;">
                    <label for="tracking_id" style="display:block; font-size:0.78rem; font-weight:700; text-transform:uppercase; color:var(--color-text-muted); margin-bottom:var(--space-xs);">Tracking ID *</label>
                    <input id="tracking_id" name="tracking_id" type="text" placeholder="Courier tracking number" style="width:100%;" value="<?= e((string)($order['tracking_id'] ?? '')) ?>">
                </div>
                <p id="tracking-url-preview" style="flex:1 1 100%; margin:var(--space-xs) 0 0; font-size:0.8rem; color:var(--color-text-muted);"></p>
            </div>
        </form>
        <script>
        (function () {
            var sel     = document.getElementById('order-status');
            var box     = document.getElementById('ship-fields');
            var id      = document.getElementById('tracking_id');
            var courier = document.getElementById('courier_name');
            var preview = document.getElementById('tracking-url-preview');
            function sync() {
                var shipping = sel.value === 'shipped';
                box.style.display = shipping ? 'flex' : 'none';
                // Required only while shipping, so other status changes are not blocked.
                id.required = shipping;
                courier.required = shipping;
            }
            // The link is no longer typed, so show the admin exactly which page the
            // customer will be sent to before they save.
            function showUrl() {
                var opt = courier.options[courier.selectedIndex];
                var href = opt ? opt.getAttribute('data-url') : '';
                preview.textContent = href ? 'Customer will be sent to ' + href : '';
            }
            sel.addEventListener('change', sync);
            courier.addEventListener('change', showUrl);
            sync();
            showUrl();
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
