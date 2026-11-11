<?php foreach (($homeCollections ?? []) as $collection): ?>
<section class="section"><div class="container">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
        <h2 class="section-title"><?= e($collection['name']) ?></h2>
        <a class="btn btn-ghost" href="/shop?category=<?= e(rawurlencode($collection['slug'])) ?>">View collection</a>
    </div>
    <div class="product-grid">
        <?php foreach (array_slice($collection['products'], 0, 4) as $item): ?>
            <article class="product-card">
                <a class="product-card__image" href="/product/<?= e(rawurlencode($item['slug'])) ?>"><img src="<?= e(webp_src($item['image_url'] ?? placeholder_img($item['name']))) ?>" data-product-image-fallback="<?= e(placeholder_img($item['name'])) ?>" alt="<?= e($item['name']) ?>" decoding="async"></a>
                <div class="product-card__body"><h3><a class="product-card__title" href="/product/<?= e(rawurlencode($item['slug'])) ?>"><?= e($item['name']) ?></a></h3>
                    <div class="product-card__price-row"><span class="price">₹<?= e((string)(($item['offer_price'] ?? 0) ?: ($item['price'] ?? 0))) ?></span></div>
                    <a class="btn btn-outline" href="/product/<?= e(rawurlencode($item['slug'])) ?>">View product</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</div></section>
<?php endforeach; ?>
