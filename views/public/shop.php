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
                        <article class="product-card reveal">
                            <div class="product-card__image">
                                <img src="<?= e(webp_src($item['image_url'] ?? placeholder_img($item['name']))) ?>" alt="<?= e($item['name']) ?>" decoding="async">
                                <?php if($hasOffer): ?>
                                    <span class="product-card__badge product-card__badge--sale">Sale</span>
                                <?php endif; ?>
                            </div>
                            <div class="product-card__body">
                                <?php if(!empty($item['category'])): ?>
                                    <span style="font-size:0.7rem; text-transform:uppercase; letter-spacing:0.1em; color:var(--color-gold); font-weight:600;"><?= e($item['category']) ?></span>
                                <?php endif; ?>
                                <h3><?= e($item['name']) ?></h3>
                                <p class="product-card__desc"><?= e($item['description']) ?></p>
                                <div class="product-card__price-row">
                                    <span class="price">₹<?= e((string)($item['offer_price'] ?: $item['price'] ?: 0)) ?></span>
                                    <?php if($hasOffer): ?>
                                        <span class="old-price">₹<?= e($item['price']) ?></span>
                                        <?php $pct = round((1 - $item['offer_price'] / ($item['price'] ?: 1)) * 100); ?>
                                        <span class="discount-pct">-<?= $pct ?>%</span>
                                    <?php endif; ?>
                                </div>
                                <?php $itemQty = $cartQuantities[(string)($item['slug'] ?? '')] ?? 0; ?>
                                <div class="product-card__actions">
                                    <a href="/product/<?= e($item['slug']) ?>" class="btn btn-sm btn-ghost">View →</a>
                                    <div class="product-card__form product-card__stepper" aria-label="<?= e($item['name']) ?> cart quantity">
                                        <form method="post" action="/cart/update">
                                            <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
                                            <input type="hidden" name="action" value="dec">
                                            <input type="hidden" name="redirect" value="/shop">
                                            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                                            <button type="submit" aria-label="Remove one <?= e($item['name']) ?>" <?= $itemQty <= 0 ? 'disabled' : '' ?>>−</button>
                                        </form>
                                        <span class="qty-input__value"><?= e((string)$itemQty) ?></span>
                                        <form method="post" action="/cart/add">
                                            <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
                                            <input type="hidden" name="qty" value="1">
                                            <input type="hidden" name="redirect" value="/shop">
                                            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                                            <button type="submit" aria-label="Add one <?= e($item['name']) ?>">+</button>
                                        </form>
                                    </div>
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
                <span class="page-cta-card__eyebrow">Need Guidance?</span>
                <h3>Start a Consultation Request</h3>
                <p>Use the contact form for astrology sessions, product questions, temple guidance, or VIP direct astrology visit requests.</p>
            </div>
            <a class="btn btn-primary page-cta-card__button" href="/contact#contact-form">Let’s Get Connected →</a>
        </div>
    </div>
</section>
