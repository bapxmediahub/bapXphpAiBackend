<?php foreach (array_values($homeCollections ?? []) as $collectionIndex => $collection): ?>
    <?php
        $titleId = 'home-collection-' . $collectionIndex;
        $collectionUrl = '/shop?category=' . rawurlencode((string)$collection['slug']);
        $collectionImage = trim((string)($collection['image_url'] ?? ''));
        $memberImage = trim((string)($collection['products'][0]['image_url'] ?? ''));
        $coverImage = $collectionImage ?: ($memberImage ?: placeholder_img($collection['name']));
        $description = trim(strip_tags((string)($collection['description'] ?? '')));
    ?>
    <section class="section home-collection" aria-labelledby="<?= e($titleId) ?>">
        <div class="container">
            <div class="product-grid home-collection__grid">
                <a class="product-card home-collection__lead" href="<?= e($collectionUrl) ?>"
                   aria-labelledby="<?= e($titleId . '-action ' . $titleId) ?>">
                    <div class="product-card__image">
                        <img src="<?= e(webp_src($coverImage)) ?>" data-product-image-fallback="<?= e($memberImage ?: placeholder_img($collection['name'])) ?>"
                             alt="" width="640" height="480" loading="lazy" decoding="async">
                    </div>
                    <div class="product-card__body">
                        <h2 id="<?= e($titleId) ?>"><?= e($collection['name']) ?></h2>
                        <?php if ($description !== ''): ?>
                            <p class="product-card__desc"><?= e($description) ?></p>
                        <?php endif; ?>
                        <span class="btn btn-outline home-collection__action" id="<?= e($titleId . '-action') ?>">View collection <span aria-hidden="true">→</span></span>
                    </div>
                </a>
                <?php foreach (array_slice($collection['products'], 0, 4) as $item): ?>
                    <?php require __DIR__ . '/home-product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endforeach; ?>
