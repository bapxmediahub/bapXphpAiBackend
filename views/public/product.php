<section class="section" style="padding-top:var(--space-xl);">
    <?php if(empty($product)): ?>
        <div class="container container--narrow" style="text-align:center; padding:var(--space-4xl) 0;">
            <span style="font-size:3rem; display:block; margin-bottom:var(--space-md);">😔</span>
            <h1 style="font-family:var(--font-serif); margin:0 0 var(--space-sm);">Product Not Found</h1>
            <p style="color:var(--color-text-muted); margin-bottom:var(--space-lg);">The item you're looking for is unavailable.</p>
            <a href="/shop" class="btn btn-primary">Browse Shop</a>
        </div>
    <?php else: ?>
        <?php $hasOffer = !empty($product['offer_price']) && $product['offer_price'] < $product['price']; ?>
        <div class="product-detail">
            <div class="product-gallery reveal">
                <div class="product-gallery__main">
                    <img src="<?= e($product['image_url'] ?? 'https://placehold.co/600x600/fdfbf7/8c7e6d?text='.urlencode($product['name'])) ?>" alt="<?= e($product['name']) ?>">
                </div>
            </div>
            <div class="product-info">
                <nav style="font-size:0.8rem; color:var(--color-text-muted); margin-bottom:var(--space-md);">
                    <a href="/shop" style="color:var(--color-text-muted);">Shop</a> / <span><?= e($product['name']) ?></span>
                </nav>
                <?php if(!empty($product['category'])): ?>
                    <span style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.1em; color:var(--color-gold); font-weight:600;"><?= e($product['category']) ?></span>
                <?php endif; ?>
                <h1><?= e($product['name']) ?></h1>
                <div class="product-info__meta">
                    <div class="product-info__rating">★★★★★ <span style="color:var(--color-text-muted); font-size:0.8rem;">(Trusted)</span></div>
                </div>
                <div class="product-info__price">
                    <span class="price">₹<?= e((string)($product['offer_price'] ?: $product['price'] ?: 0)) ?></span>
                    <?php if($hasOffer): ?>
                        <span class="old-price">₹<?= e($product['price']) ?></span>
                        <?php $pct = round((1 - $product['offer_price'] / $product['price']) * 100); ?>
                        <span class="badge badge--success">Save <?= $pct ?>%</span>
                    <?php endif; ?>
                </div>
                <p class="product-info__desc"><?= e($product['description'] ?? 'A sacred spiritual product crafted with devotion and care.') ?></p>
                <div class="product-info__form">
                    <form method="post" action="/cart/add" style="display:flex; gap:var(--space-md); align-items:center; width:100%;">
                        <input type="hidden" name="slug" value="<?= e($product['slug']) ?>">
                        <div class="qty-input">
                            <button type="button" onclick="this.nextElementSibling.stepDown(); this.nextElementSibling.dispatchEvent(new Event('change'))">−</button>
                            <input type="number" name="qty" value="1" min="1" max="99" id="qty-input">
                            <button type="button" onclick="this.previousElementSibling.stepUp(); this.previousElementSibling.dispatchEvent(new Event('change'))">+</button>
                        </div>
                        <button class="btn btn-primary btn-lg" style="flex:1;">Add to Cart</button>
                    </form>
                </div>
                <div class="product-info__actions">
                    <a href="/checkout" class="btn btn-outline btn-lg btn-block">Buy Now →</a>
                </div>
                <div class="product-info__features">
                    <div class="product-feature"><span>🔒</span> Secure Payment</div>
                    <div class="product-feature"><span>📦</span> Fast Delivery</div>
                    <div class="product-feature"><span>✅</span> Authentic Product</div>
                    <div class="product-feature"><span>🙏</span> Blessed & Energized</div>
                </div>
            </div>
        </div>
        <?php if (!empty($related) && is_array($related)): ?>
        <div class="container" style="margin-top:var(--space-4xl);">
            <h2 class="section-title" style="font-size:1.5rem; margin-bottom:var(--space-xl);">Related Products</h2>
            <div class="product-grid">
                <?php foreach(array_slice($related, 0, 4) as $item): ?>
                    <article class="product-card reveal">
                        <div class="product-card__image">
                            <img src="<?= e($item['image_url'] ?? 'https://placehold.co/400x400/fdfbf7/8c7e6d?text='.urlencode($item['name'])) ?>" alt="<?= e($item['name']) ?>" loading="lazy">
                        </div>
                        <div class="product-card__body">
                            <h3><?= e($item['name']) ?></h3>
                            <div class="product-card__price-row">
                                <span class="price">₹<?= e((string)($item['offer_price'] ?: $item['price'] ?: 0)) ?></span>
                                <?php if(!empty($item['offer_price']) && $item['offer_price'] < $item['price']): ?>
                                    <span class="old-price">₹<?= e($item['price']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="product-card__actions">
                                <a href="/product/<?= e($item['slug']) ?>" class="btn btn-sm btn-block btn-ghost">View Product</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
