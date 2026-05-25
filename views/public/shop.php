<h1>Online Store</h1>
<?php if(!empty($categories)): ?>
    <div class="filters">
        <a href="/shop" class="<?= $category === '' ? 'active' : '' ?>">All categories</a>
        <?php foreach($categories as $cat): ?>
            <a href="/shop?category=<?= e($cat['slug'] ?? '') ?>" class="<?= ($category === ($cat['slug'] ?? '')) ? 'active' : '' ?>"><?= e($cat['name'] ?? 'Category') ?></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<div class="grid">
    <?php foreach (($items ?: [['name'=>'Sacred products will appear here','description'=>'Admin can add products from dashboard.']]) as $item): ?>
        <article class="panel">
            <?php if(!empty($item['image_url'])): ?><img src="<?= e($item['image_url']) ?>" alt="<?= e($item['name']) ?>"><?php endif; ?>
            <h2><?= e($item['name']) ?></h2>
            <p><?= e($item['description'] ?? '') ?></p>
            <?php if(!empty($item['category'])): ?><p><strong>Category:</strong> <?= e($item['category']) ?></p><?php endif; ?>
            <?php if(!empty($item['price'])): ?>
                <p>₹<?= e((string)($item['offer_price'] ?: $item['price'])) ?></p>
                <form method="post" action="/cart/add">
                    <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
                    <input type="hidden" name="qty" value="1">
                    <button>Add to cart</button>
                </form>
                <?php if(!empty($item['slug'])): ?><a href="/product/<?= e($item['slug']) ?>">View product</a><?php endif; ?>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
