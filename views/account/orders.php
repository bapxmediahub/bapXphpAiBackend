<div class="section" style="padding-top:var(--space-xl);">
    <div class="account-layout">
        <?php require __DIR__ . '/_nav.php'; ?>
        <div class="account-content">
            <h1>My Orders</h1>
            <?php if(empty($orders)): ?>
                <div style="text-align:center; padding:var(--space-2xl);">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto var(--space-md);"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                    <h3 style="font-family:var(--font-serif); margin:0 0 var(--space-sm);">No Orders Yet</h3>
                    <p style="color:var(--color-text-muted); margin-bottom:var(--space-lg);">Start exploring our spiritual products.</p>
                    <a href="/shop" class="btn btn-primary">Browse Shop</a>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Order ID</th><th>Status</th><th>Total</th><th>Delivery Address</th><th>Shipped At</th><th>Invoice</th><th>Product Review</th></tr></thead>
                        <tbody>
                        <?php foreach($orders as $order): ?>
                            <tr>
                                <td><code style="font-size:0.8rem; background:var(--color-bg-alt); padding:0.2rem 0.5rem; border-radius:var(--radius-sm);"><?= e(substr($order['id'] ?? '', 0, 12)) ?></code></td>
                                <td><span class="badge badge--default"><?= e(ucfirst($order['status'] ?? 'pending')) ?></span></td>
                                <td style="font-weight:600; color:var(--color-maroon);">₹<?= e((string)($order['total'] ?? 0)) ?></td>
                                <td style="font-size:0.85rem; color:var(--color-text-muted);">
                                    <?= e($order['shipping_address'] ?? 'Not recorded') ?><br>
                                    <?= e($order['shipping_city'] ?? '') ?> <?= e($order['shipping_pincode'] ?? '') ?>
                                </td>
                                <td style="font-size:0.85rem; color:var(--color-text-muted);">
                                    <?= !empty($order['shipped_at']) ? e($order['shipped_at']) : e(ucfirst(str_replace('_', ' ', (string)($order['status'] ?? 'processing')))) ?>
                                </td>
                                <td>
                                    <?php if (!empty($order['invoice_number'])): ?>
                                        <a href="/account/orders/<?= e($order['id']) ?>/invoice" class="btn btn-sm" style="padding:0.3rem 0.6rem; font-size:0.75rem;">View Invoice</a>
                                    <?php else: ?>
                                        <span style="color:var(--color-text-muted); font-size:0.8rem;">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td data-review_request_after_at="<?= e((string)($order['review_request_after_at'] ?? '')) ?>">
                                    <?php if(isset($reviewService) && $reviewService->productReviewIsDue($order)): ?>
                                        <?php foreach(($order['items'] ?? []) as $item): ?>
                                            <?php if(empty($item['slug'])) continue; ?>
                                            <?php $reviewRowId = ($order['id'] ?? bin2hex(random_bytes(4))) . '-' . $item['slug']; ?>
                                            <form class="review-inline-form" action="/reviews/product" method="post">
                                                <input type="hidden" name="target_type" value="product">
                                                <input type="hidden" name="target_slug" value="<?= e($item['slug']) ?>">
                                                <input type="hidden" name="source_id" value="<?= e($order['id'] ?? '') ?>">
                                                <input type="hidden" name="redirect" value="/account/dashboard/orders">
                                                <strong><?= e($item['name'] ?? 'Product') ?></strong>
                                                <div class="star-rating-input" aria-label="Rate product out of 5">
                                                    <?php for($i=5;$i>=1;$i--): ?>
                                                        <input id="product-<?= e($reviewRowId) ?>-<?= $i ?>" type="radio" name="rating" value="<?= $i ?>" required>
                                                        <label for="product-<?= e($reviewRowId) ?>-<?= $i ?>" title="<?= $i ?> stars">★</label>
                                                    <?php endfor; ?>
                                                </div>
                                                <textarea name="review" placeholder="Write a short review"></textarea>
                                                <button type="submit" class="btn btn-sm btn-primary">Submit Review</button>
                                            </form>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span style="color:var(--color-text-muted); font-size:0.8rem;">After shipped date plus review wait</span>
                                    <?php endif; ?>
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
