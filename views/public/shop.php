<?php $categoryNames = array_column($categories ?? [], 'name', 'slug'); ?>
<section class="shop-hero">
    <p class="eyebrow">Editable premium catalog</p>
    <h1>Shop devotional essentials</h1>
    <p>Choose trusted spiritual jewelry, yantras, idols, malas, gift sets, and pooja essentials with clear pricing and a clean path to checkout.</p>
</section>
<?php if(!empty($categories)): ?>
    <div class="filters">
        <a href="/shop" class="<?= $category === '' ? 'active' : '' ?>">All categories</a>
        <?php foreach($categories as $cat): ?>
            <a href="/shop?category=<?= e($cat['slug'] ?? '') ?>" class="<?= ($category === ($cat['slug'] ?? '')) ? 'active' : '' ?>"><?= e($cat['name'] ?? 'Category') ?></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<div class="product-grid shop-grid">
    <?php foreach (($items ?: [['name'=>'Sacred products will appear here','description'=>'Admin can add products from dashboard.']]) as $item): ?>
        <article class="product-card">
            <?php if(!empty($item['slug'])): ?><a class="product-art" href="/product/<?= e($item['slug']) ?>"><?php else: ?><div class="product-art"><?php endif; ?>
                <?php if(!empty($item['image_url'])): ?><img src="<?= e($item['image_url']) ?>" alt="<?= e($item['name']) ?>"><?php else: ?><span><?= e(substr($item['name'] ?? 'S', 0, 1)) ?></span><?php endif; ?>
            <?php if(!empty($item['slug'])): ?></a><?php else: ?></div><?php endif; ?>
            <div class="product-body">
                <?php if(!empty($item['category'])): ?><p class="product-category"><?= e($categoryNames[$item['category']] ?? str_replace('-', ' ', $item['category'])) ?></p><?php endif; ?>
                <h2><?= e($item['name']) ?></h2>
                <p><?= e($item['description'] ?? '') ?></p>
                <?php if(!empty($item['price'])): ?>
                <div class="price-row"><strong>₹<?= e((string)($item['offer_price'] ?: $item['price'])) ?></strong><?php if(!empty($item['offer_price'])): ?><small>₹<?= e((string)$item['price']) ?></small><?php endif; ?></div>
                <form method="post" action="/cart/add">
                    <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
                    <input type="hidden" name="qty" value="1">
                    <button>Add to cart</button>
                </form>
                <?php if(!empty($item['slug'])): ?><a class="text-link" href="/product/<?= e($item['slug']) ?>">View product</a><?php endif; ?>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>
