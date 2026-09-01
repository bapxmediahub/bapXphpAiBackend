<?php
/**
 * One order in full, and the thank-you screen for a payment that has just gone through.
 *
 * Before this page the customer was dropped back onto a seven-column table with a toast
 * that vanished, so the moment they had just paid money was the moment they were given
 * the least. The banner only appears with ?placed=1; every other visit is a plain
 * order view.
 */
$__raw = (string)($order['status'] ?? 'pending');
$__map = [
    'pending'    => ['Order placed', 'default', 'We have your order and are getting it ready.'],
    'confirmed'  => ['Order placed', 'default', 'We have your order and are getting it ready.'],
    'processing' => ['Processing', 'default', 'We are packing your order now.'],
    'shipped'    => ['Shipped', 'success', 'Your parcel is on its way.'],
    'delivered'  => ['Delivered', 'success', 'Your order has arrived. We hope it brings you blessings.'],
    'cancelled'  => ['Cancelled', 'error', 'This order was cancelled. Nothing further will be charged.'],
];
[$__label, $__tone, $__note] = $__map[$__raw] ?? [ucfirst($__raw), 'default', ''];
$__country = strtolower(trim((string)($order['shipping_country'] ?? 'india')));
$__window = ($__country === '' || str_contains($__country, 'india'))
    ? \App\Services\OrderService::DELIVERY_DAYS_DOMESTIC
    : \App\Services\OrderService::DELIVERY_DAYS_INTERNATIONAL;
$__name = trim((string)($order['customer_name'] ?? ''));
$__first = $__name !== '' ? explode(' ', $__name)[0] : '';
?>
<div class="section" style="padding-top:var(--space-xl);">
    <div class="account-layout">
        <?php require __DIR__ . '/_nav.php'; ?>
        <div class="account-content">

            <?php if (!empty($justPlaced)): ?>
                <div class="order-thanks">
                    <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M20 6L9 17l-5-5"/>
                    </svg>
                    <h1>Thank you<?= $__first !== '' ? ', ' . e($__first) : '' ?>.</h1>
                    <p>
                        Your payment went through and your order is confirmed. We are preparing it with care,
                        and you will get an email the moment it is on its way.
                    </p>
                    <p class="order-thanks__meta">
                        Order <strong><?= e((string)($order['id'] ?? '')) ?></strong>
                        <?php if (!empty($order['invoice_number'])): ?>
                            · Invoice <strong><?= e((string)$order['invoice_number']) ?></strong>
                        <?php endif; ?>
                        <br>A confirmation is being sent to <strong><?= e((string)($order['customer_email'] ?? '')) ?></strong>.
                    </p>
                    <div class="order-thanks__actions">
                        <a href="/shop" class="btn btn-primary">Continue shopping</a>
                        <a href="/account/dashboard/orders" class="btn btn-ghost">All my orders</a>
                    </div>
                </div>
            <?php else: ?>
                <p style="margin:0 0 var(--space-sm);">
                    <a href="/account/dashboard/orders" style="font-size:0.85rem; font-weight:600;">&larr; Back to my orders</a>
                </p>
                <h1 style="margin:0 0 var(--space-xs);">Order</h1>
            <?php endif; ?>

            <div class="order-card">
                <div class="order-card__head">
                    <div>
                        <span class="order-card__label">Order ID</span>
                        <code class="order-card__id"><?= e((string)($order['id'] ?? '')) ?></code>
                    </div>
                    <div style="text-align:right;">
                        <span class="badge badge--<?= e($__tone) ?>"><?= e($__label) ?></span>
                        <?php if ($__note !== ''): ?>
                            <p class="order-card__note"><?= e($__note) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="order-grid">
                    <div>
                        <span class="order-card__label">Delivering to</span>
                        <p class="order-card__value">
                            <?php if ($__name !== ''): ?><strong><?= e($__name) ?></strong><br><?php endif; ?>
                            <?= e((string)($order['shipping_address'] ?? 'Not recorded')) ?><br>
                            <?= e((string)($order['shipping_city'] ?? '')) ?> <?= e((string)($order['shipping_pincode'] ?? '')) ?>
                            <?php if (!empty($order['customer_phone'])): ?><br><?= e((string)$order['customer_phone']) ?><?php endif; ?>
                        </p>
                    </div>
                    <div>
                        <span class="order-card__label">Delivery</span>
                        <p class="order-card__value">
                            <?php if (!empty($order['shipped_at'])): ?>
                                Shipped <?= e(date('d M Y', strtotime((string)$order['shipped_at']))) ?><br>
                                Arrives in <?= e($__window) ?>
                                <?php if (!empty($order['courier_name'])): ?>
                                    <br>Courier: <?= e((string)$order['courier_name']) ?>
                                <?php endif; ?>
                                <?php if (!empty($order['tracking_id'])): ?>
                                    <br>Tracking: <code><?= e((string)$order['tracking_id']) ?></code>
                                <?php endif; ?>
                                <?php if (!empty($order['tracking_url'])): ?>
                                    <br><a href="<?= e((string)$order['tracking_url']) ?>" target="_blank" rel="noopener noreferrer"
                                           style="font-weight:600;">Track parcel &rarr;</a>
                                <?php endif; ?>
                            <?php else: ?>
                                Not shipped yet.<br>Once it ships, delivery takes <?= e($__window) ?>.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <h2 class="order-card__heading">Items</h2>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Product</th><th>Qty</th><th style="text-align:right;">Total</th></tr></thead>
                        <tbody>
                        <?php foreach (($order['items'] ?? []) as $item): ?>
                            <tr>
                                <td><?= e((string)($item['name'] ?? $item['slug'] ?? 'Product')) ?></td>
                                <td><?= e((string)($item['qty'] ?? 1)) ?></td>
                                <td style="text-align:right;">₹<?= e((string)($item['line_total'] ?? 0)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2" style="text-align:right;">Total paid</th>
                                <th style="text-align:right; color:var(--color-maroon);">₹<?= e((string)($order['total'] ?? 0)) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="order-card__actions">
                    <?php if (!empty($order['invoice_number'])): ?>
                        <a href="/account/orders/<?= e((string)$order['id']) ?>/invoice" class="btn btn-sm">View invoice</a>
                    <?php endif; ?>
                    <a href="/contact" class="btn btn-sm btn-ghost">Need help with this order?</a>
                </div>
            </div>
        </div>
    </div>
</div>
