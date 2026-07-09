<section class="section">
    <div class="shop-layout">
        <aside class="shop-sidebar">
            <div class="shop-sidebar-card">
                <div class="shop-filters">
                    <h3>Categories</h3>
                    <div class="filter-group">
                        <a href="/shop" class="filter-chip">All Products</a>
                        <?php foreach($categories as $cat): ?>
                            <a href="/shop?category=<?= e($cat['slug'] ?? '') ?>" class="filter-chip"><?= e($cat['name'] ?? 'Category') ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </aside>
        <div>
            <h2>Shop by Category</h2>
            <div class="product-grid">
                <?php foreach($categories as $cat): ?>
                    <article class="product-card reveal">
                        <div class="product-card__image">
                            <?php if(!empty($cat['image_url'])): ?>
                                <img src="<?= e($cat['image_url']) ?>" alt="<?= e($cat['name'] ?? '') ?>" decoding="async">
                            <?php else: ?>
                                <div style="width:100%;height:200px;background:#fdfbf7;display:flex;align-items:center;justify-content:center;color:#8c7e6d;font-size:2rem;">🪷</div>
                            <?php endif; ?>
                        </div>
                        <div class="product-card__body">
                            <h3><?= e($cat['name'] ?? '') ?></h3>
                            <?php if(!empty($cat['description'])): ?>
                                <p class="product-card__desc"><?= e($cat['description']) ?></p>
                            <?php endif; ?>
                            <a href="/shop?category=<?= e($cat['slug'] ?? '') ?>" class="btn btn-sm btn-primary">Browse →</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if(empty($categories)): ?>
                <div class="panel" style="text-align:center;padding:var(--space-2xl);">
                    <h3 style="font-family:var(--font-serif);margin:0 0 var(--space-sm);">No categories yet</h3>
                    <p style="color:var(--color-text-muted);margin:0 0 var(--space-lg);">Categories will appear here once added.</p>
                    <a href="/shop" class="btn btn-primary">Browse Products</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
