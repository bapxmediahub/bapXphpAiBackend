<section class="section">
    <div class="shop-layout">
        <aside class="shop-sidebar">
            <div class="shop-filters">
                <h3>Categories</h3>
                <div class="filter-group">
                    <a href="/shop" class="filter-chip <?= ($category ?? '') === '' ? 'active' : '' ?>">All</a>
                    <?php foreach($categories as $cat): ?>
                        <a href="/shop?category=<?= e($cat['slug'] ?? '') ?>" class="filter-chip <?= ($category === ($cat['slug'] ?? '')) ? 'active' : '' ?>"><?= e($cat['name'] ?? 'Category') ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>
        <div>
            <div class="shop-toolbar">
                <span class="shop-toolbar__count"><?= count($items) ?> product<?= count($items) !== 1 ? 's' : '' ?></span>
            </div>
            <?php if(empty($items)): ?>
                <div class="panel" style="text-align:center; padding:var(--space-2xl);">
                    <span style="display:block; margin-bottom:var(--space-md);"><svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="1"/><path d="M9 20l-1-4 3-2-1-4 3-2-1-4"/><path d="M15 20l1-4-3-2 1-4-3-2 1-4"/><path d="M12 7l-3 2 1 4-3 2 1 4h8l1-4-3-2 1-4-3-2z"/></svg></span>
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
                                <img src="<?= e($item['image_url'] ?? 'https://placehold.co/400x400/fdfbf7/8c7e6d?text='.urlencode($item['name'])) ?>" alt="<?= e($item['name']) ?>" loading="lazy">
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
                                        <?php $pct = round((1 - $item['offer_price'] / $item['price']) * 100); ?>
                                        <span class="discount-pct">-<?= $pct ?>%</span>
                                    <?php endif; ?>
                                </div>
                                <div class="product-card__actions">
                                    <a href="/product/<?= e($item['slug']) ?>" class="btn btn-sm btn-ghost">View</a>
                                    <form method="post" action="/cart/add" style="flex:1;">
                                        <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
                                        <input type="hidden" name="qty" value="1">
                                        <button class="btn btn-sm btn-primary" style="width:100%;">Add to Cart</button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
