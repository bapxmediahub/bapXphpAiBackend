<?php if(empty($product)): ?>
    <h1>Product not found</h1>
    <p>The item you're looking for is unavailable.</p>
<?php else: ?>
    <section class="product-detail">
        <p class="eyebrow">Trusted devotional product</p>
        <h1><?= e($product['name']) ?></h1>
        <div class="product-detail-grid">
            <article class="detail-art">
                <?php if(!empty($product['image_url'])): ?><img src="<?= e($product['image_url']) ?>" alt="<?= e($product['name']) ?>"><?php else: ?><span><?= e(substr($product['name'] ?? 'S', 0, 1)) ?></span><?php endif; ?>
            </article>
            <article class="detail-copy">
                <?php if(!empty($product['category'])): ?><p class="product-category"><?= e($categoryNames[$product['category']] ?? str_replace('-', ' ', $product['category'])) ?></p><?php endif; ?>
                <p><?= e($product['description'] ?? '') ?></p>
                <div class="detail-price"><strong>₹<?= e((string)($product['offer_price'] ?: $product['price'] ?: 0)) ?></strong><?php if(!empty($product['offer_price'])): ?><small>₹<?= e((string)$product['price']) ?></small><?php endif; ?></div>
                <form method="post" action="/cart/add">
                    <input type="hidden" name="slug" value="<?= e($product['slug']) ?>">
                    <input type="hidden" name="qty" value="1">
                    <button>Add to cart</button>
                </form>
                <a href="/checkout" class="button-link secondary">Proceed to secure checkout</a>
                <div class="detail-notes">
                    <div><strong>Best for</strong><span>Daily devotion, gifting, and guided remedies</span></div>
                    <div><strong>Support</strong><span>Ask product questions on WhatsApp before checkout</span></div>
                    <div><strong>Admin editable</strong><span>Name, price, category, and description can be updated anytime</span></div>
                </div>
            </article>
        </div>
    </section>
<?php endif; ?>
