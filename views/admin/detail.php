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
        <?php if (!empty($order['fulfillment_review_required'])): ?>
            <p role="alert"><strong>Fulfillment review required.</strong> Payment was received, but product availability changed. Contact the customer and confirm stock or arrange a refund before fulfilling this order.</p>
        <?php endif; ?>
        <?php if (!empty($order['tax_review_required'])): ?>
            <div role="alert" style="padding:var(--space-md); border:1px solid var(--color-warning); border-radius:var(--radius-sm); background:var(--color-bg-alt); margin:var(--space-md) 0;">
                <?php if (($order['tax_review_reason'] ?? '') === \App\Services\TaxService::MISSING_PRODUCT_TAX_DETAILS): ?>
                    <p><strong>Tax review required.</strong> Verify the missing product tax details with your accountant before issuing an invoice.</p>
                <?php else: ?>
                <p style="margin:0 0 var(--space-sm);"><strong>Tax review required.</strong> Payment was confirmed, but no invoice was issued because delivery or supplier state was missing or invalid.</p>
                <form method="post" action="/admin/orders/<?= e(rawurlencode((string)$order['id'])) ?>/tax-review" style="display:flex; gap:var(--space-sm); align-items:end; flex-wrap:wrap;">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <div style="flex:1 1 260px; min-width:0;">
                        <label for="tax-review-state" style="display:block; font-size:0.8rem; font-weight:600; margin-bottom:var(--space-xs);">Verified delivery state</label>
                        <select id="tax-review-state" name="shipping_state" required style="width:100%;">
                            <option value="">Select state or union territory…</option>
                            <?php foreach (($taxStates ?? []) as $__state): ?>
                                <option value="<?= e($__state) ?>" <?= (\App\Services\TaxService::normaliseState((string)($order['shipping_state'] ?? '')) === $__state ? 'selected' : '') ?>><?= e($__state) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-primary" type="submit">Issue corrected invoice</button>
                </form>
                <p style="margin:var(--space-sm) 0 0; color:var(--color-text-muted); font-size:0.85rem;">Confirm the delivery state from the paid order record. This creates the stored tax snapshot once; issued invoices cannot be rewritten.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($order['invoice_number'])): ?>
            <p><strong>Invoice <?= e((string)$order['invoice_number']) ?></strong></p>
            <p><a class="btn btn-outline" href="/admin/orders/<?= e(rawurlencode((string)$order['id'])) ?>/invoice">View invoice</a>
            <a class="btn btn-primary" href="/admin/orders/<?= e(rawurlencode((string)$order['id'])) ?>/invoice?download=1">Download invoice (HTML)</a></p>
            <p>Open the downloaded invoice and choose Print to save it as a PDF.</p>
        <?php elseif (empty($order['tax_review_required'])): ?>
            <p>An invoice is available after payment is confirmed.</p>
        <?php endif; ?>
        <form method="post" action="/admin/orders/<?= e((string)($order['id'] ?? '')) ?>/status" style="margin:var(--space-lg) 0; display:flex; gap:var(--space-sm); align-items:end; flex-wrap:wrap;" onsubmit="if(document.getElementById('order-status').value==='cancelled'&&!confirm('Cancel this order? This will mark it as cancelled and cannot be undone.'))return false">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div>
                <label for="order-status" style="display:block; font-size:0.78rem; font-weight:700; text-transform:uppercase; color:var(--color-text-muted); margin-bottom:var(--space-xs);">Update Status</label>
                <select id="order-status" name="status">
                    <?php foreach(['on_hold','confirmed','processing','shipped','delivered','cancelled'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= (($order['status'] ?? '') === $status ? 'selected' : '') ?>><?= e(ucfirst($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!empty($order['fulfillment_review_required'])): ?>
                <label><input type="checkbox" name="availability_confirmed" value="1"> I have confirmed availability with the customer before fulfilling this order.</label>
            <?php endif; ?>
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
