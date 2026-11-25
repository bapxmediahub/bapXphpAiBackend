<section class="section category-directory" aria-labelledby="category-directory-title">
    <div class="container">
        <header class="category-directory__header">
            <div>
                <h1 id="category-directory-title">Shop by category</h1>
                <?php if ($categories): ?><p>Choose a category to view its products.</p><?php endif; ?>
            </div>
            <a class="btn btn-outline" href="/shop">All products <span aria-hidden="true">→</span></a>
        </header>
        <?php if ($categories): ?>
            <div class="category-directory__grid">
                <?php foreach ($categories as $category): ?>
                    <?php
                    $name = $category['name'] ?? 'Category';
                    $url = '/shop?category=' . rawurlencode((string)($category['slug'] ?? ''));
                    $placeholder = placeholder_img($name);
                    $image = trim((string)($category['image_url'] ?? '')) ?: $placeholder;
                    ?>
                    <article class="category-directory__card">
                        <a href="<?= e($url) ?>" aria-label="<?= e('Browse ' . $name) ?>">
                            <img src="<?= e(webp_src($image)) ?>" data-product-image-fallback="<?= e($placeholder) ?>" alt="" loading="lazy" decoding="async">
                            <div class="category-directory__body">
                                <h2><?= e($name) ?></h2>
                                <?php if (!empty($category['description'])): ?>
                                    <p><?= e($category['description']) ?></p>
                                <?php endif; ?>
                                <span>Browse category <span aria-hidden="true">→</span></span>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="panel category-directory__empty">
                <h2>No categories yet</h2>
                <p>See the full shop for available products.</p>
            </div>
        <?php endif; ?>
    </div>
</section>
