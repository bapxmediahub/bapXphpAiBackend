<div class="admin-card">
    <h2 style="font-size:1.1rem; margin:0 0 var(--space-md);"><?= e($title) ?></h2>
    <?php if(empty($order)): ?>
        <p style="color:var(--color-text-muted);">Order not found.</p>
    <?php else: ?>
        <?php
        $items = $order['items'] ?? [];
        $subtotal = $items !== [] ? 0.0 : null;
        foreach ($items as $item) {
            if (!is_numeric($item['line_total'] ?? null)) {
                $subtotal = null;
                break;
            }
            $subtotal += (float)$item['line_total'];
        }
        $money = static fn($amount): string => is_numeric($amount)
            ? '₹' . number_format((float)$amount, 2)
            : 'Not recorded';
        $refundSummary = \App\Services\OrderService::refundSummary($order);
        ?>
        <div class="admin-detail-grid">
            <div>
                <strong>Status</strong>
                <p><?= e(ucfirst(str_replace('_', ' ', (string)($order['status'] ?? 'pending')))) ?></p>
            </div>
            <div>
                <strong>Order date</strong>
                <p><?= e(!empty($order['created_at']) ? substr((string)$order['created_at'], 0, 10) : 'Not recorded') ?></p>
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
                <strong>Refund status</strong>
                <p><?= e($refundSummary['label']) ?><?php if ($refundSummary['state'] !== 'none'): ?> · <?= e($money($refundSummary['refunded_paise'] / 100)) ?><?php endif; ?></p>
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
                <p style="margin:0 0 var(--space-sm);"><strong>Tax review required.</strong> Payment was confirmed, but no invoice was issued because delivery or supplier state was missing or invalid.</p>
                <?php if (str_contains((string)($order['tax_review_reason'] ?? ''), 'Product tax details unavailable')): ?>
                    <p style="margin:0;">The purchased product record was unavailable at confirmation. Restore the original HSN and GST rate before issuing an invoice; do not guess tax details.</p>
                <?php else: ?>
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
            <a class="btn btn-primary" download href="/admin/orders/<?= e(rawurlencode((string)$order['id'])) ?>/invoice?download=1">Download invoice (HTML)</a></p>
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
                <label style="flex:1 1 100%;"><input type="checkbox" name="availability_confirmed" value="1"> I confirmed product availability before fulfilling this paid order.</label>
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
                <thead><tr><th>Product</th><th>Unit price</th><th>Qty</th><th>Line total</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $quantity = (int)($item['qty'] ?? 0);
                        $lineTotal = $item['line_total'] ?? null;
                        $unitPrice = $item['unit_price'] ?? ($quantity > 0 && is_numeric($lineTotal) ? (float)$lineTotal / $quantity : null);
                        ?>
                        <tr>
                            <td><?= e($item['name'] ?? $item['slug'] ?? 'Product') ?></td>
                            <td><?= e($money($unitPrice)) ?></td>
                            <td><?= e($quantity > 0 ? (string)$quantity : 'Not recorded') ?></td>
                            <td><?= e($money($lineTotal)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><th scope="row" colspan="3">Items subtotal</th><td><?= e($money($subtotal)) ?></td></tr>
                    <tr><th scope="row" colspan="3">Discount</th><td><?= e($money($order['discount'] ?? null)) ?></td></tr>
                    <tr><th scope="row" colspan="3">Shipping</th><td><?= e($money($order['shipping_total'] ?? null)) ?></td></tr>
                    <tr><th scope="row" colspan="3">Order total</th><td><?= e($money($order['total'] ?? null)) ?></td></tr>
                </tfoot>
            </table>
        </div>
        <?php if (!empty($order['payment_id'])): ?>
            <h3>Refunds</h3>
            <p><strong><?= e($refundSummary['label']) ?></strong>
                · Refunded <?= e($money($refundSummary['refunded_paise'] / 100)) ?>
                · Remaining paid <?= e($money($refundSummary['remaining_paise'] / 100)) ?></p>
            <?php foreach ($order['verified_refunds'] ?? [] as $refund): ?>
                <p><?= e($refund['id']) ?> · <?= e($money($refund['amount_paise'] / 100)) ?></p>
            <?php endforeach; ?>
            <?php if ($refundSummary['state'] !== 'full'): ?>
            <form class="admin-form" method="post" action="/admin/orders/<?= e(rawurlencode((string)$order['id'])) ?>/refund">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <label>Completed gateway refund reference <input name="refund_id" required maxlength="128"></label>
                <button class="btn btn-secondary" type="submit">Verify and record refund</button>
            </form>
            <?php else: ?>
                <p>This order is fully refunded; no additional refund can be recorded.</p>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
