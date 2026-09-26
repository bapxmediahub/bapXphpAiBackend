<section class="section">
    <?php
        $cartQuantities = [];
        foreach ($_SESSION['cart'] ?? [] as $cartItem) {
            $cartQuantities[(string)($cartItem['slug'] ?? '')] = (int)($cartItem['qty'] ?? 0);
        }
        $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16));
    ?>
    <div class="shop-layout">
        <aside class="shop-sidebar">
            <div class="shop-sidebar-card">
                <div class="shop-filters">
                    <h3>Categories</h3>
                    <div class="filter-group">
                        <a href="/shop" class="filter-chip <?= ($category ?? '') === '' ? 'active' : '' ?>">All</a>
                        <?php foreach($categories as $cat): ?>
                            <a href="/shop?category=<?= e($cat['slug'] ?? '') ?>" class="filter-chip <?= ($category === ($cat['slug'] ?? '')) ? 'active' : '' ?>"><?= e($cat['name'] ?? 'Category') ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </aside>
        <div>
            <div class="shop-toolbar">
                <span class="shop-toolbar__count"><?= count($items) ?> product<?= count($items) !== 1 ? 's' : '' ?></span>
            </div>
            <?php if(empty($items)): ?>
                <div class="panel" style="text-align:center; padding:var(--space-2xl);">
                    <span style="display:block; margin-bottom:var(--space-md);"><svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 000 20 14.5 14.5 0 000-20"/><path d="M2 12h20"/></svg></span>
                    <h3 style="font-family:var(--font-serif); margin:0 0 var(--space-sm);">No products found</h3>
                    <p style="color:var(--color-text-muted); margin:0 0 var(--space-lg);">Check back soon for new spiritual products.</p>
                    <a href="/shop" class="btn btn-primary">Browse All</a>
                </div>
            <?php else: ?>
                <div class="product-grid">
                    <?php foreach($items as $item): ?>
                        <?php $hasOffer = !empty($item['offer_price']) && $item['offer_price'] < $item['price']; ?>
                        <?php
                            $itemSlug = trim((string)($item['slug'] ?? ''));
                            $productUrl = '/product/' . rawurlencode($itemSlug);
                            $isPurchasable = in_array((string)($item['stock_status'] ?? 'in_stock'), ['in_stock', 'active'], true);
                        ?>
                        <article class="product-card reveal">
                            <a class="product-card__image" href="<?= e($productUrl) ?>" aria-label="View <?= e($item['name']) ?>">
                                <img src="<?= e(webp_src($item['image_url'] ?? placeholder_img($item['name']))) ?>" alt="<?= e($item['name']) ?>" decoding="async">
                                <?php if($hasOffer): ?>
                                    <span class="product-card__badge product-card__badge--sale">Sale</span>
                                <?php endif; ?>
                            </a>
                            <div class="product-card__body">
                                <?php if(!empty($item['category'])): ?>
                                    <span style="font-size:0.7rem; text-transform:uppercase; letter-spacing:0.1em; color:var(--color-gold); font-weight:600;"><?= e($item['category']) ?></span>
                                <?php endif; ?>
                                <h3><a class="product-card__title" href="<?= e($productUrl) ?>"><?= e($item['name']) ?></a></h3>
                                <p class="product-card__desc"><?= e($item['description']) ?></p>
                                <div class="product-card__price-row">
                                    <span class="price">₹<?= e((string)(($item['offer_price'] ?? 0) ?: ($item['price'] ?? 0))) ?></span>
                                    <?php if($hasOffer): ?>
                                        <span class="old-price">₹<?= e($item['price']) ?></span>
                                        <?php $pct = round((1 - $item['offer_price'] / ($item['price'] ?: 1)) * 100); ?>
                                        <span class="discount-pct">-<?= $pct ?>%</span>
                                    <?php endif; ?>
                                </div>
                                <div class="product-card__actions">
                                    <?php if($isPurchasable): ?>
                                    <form method="post" action="/cart/add" class="product-purchase">
                                        <input type="hidden" name="slug" value="<?= e($itemSlug) ?>">
                                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                                        <div class="product-purchase__quantity"><span>Quantity</span><div class="qty-input">
                                            <button type="button" data-quantity-step="-1" aria-label="Decrease quantity for <?= e($item['name']) ?>">−</button>
                                            <input type="number" name="qty" value="1" min="1" max="99" inputmode="numeric" aria-label="Quantity for <?= e($item['name']) ?>">
                                            <button type="button" data-quantity-step="1" aria-label="Increase quantity for <?= e($item['name']) ?>">+</button>
                                        </div></div>
                                        <div class="product-purchase__buttons">
                                            <button type="submit" name="redirect" value="/checkout" class="btn btn-sm btn-primary">Buy Now</button>
                                            <button type="submit" name="redirect" value="/shop" class="btn btn-sm btn-outline">Add to Cart</button>
                                        </div>
                                        <?php if (($cartQuantities[$itemSlug] ?? 0) > 0): ?><a class="product-purchase__in-cart" href="/cart"><?= (int)$cartQuantities[$itemSlug] ?> in cart · Edit quantity</a><?php endif; ?>
                                    </form>
                                    <?php else: ?>
                                    <span class="product-card__unavailable">Out of stock</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="container" style="margin-top:var(--space-2xl);">
        <div class="page-cta-card reveal">
            <div>
                <span class="page-cta-card__eyebrow">Need help?</span>
                <h3>Send a General Enquiry</h3>
                <p>Contact us with product questions, order support, temple guidance, or general store enquiries.</p>
            </div>
            <a class="btn btn-primary page-cta-card__button" href="/contact#contact-form">Let’s Get Connected →</a>
        </div>
    </div>
</section>
