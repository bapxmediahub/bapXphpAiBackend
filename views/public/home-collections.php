<?php foreach (array_values($homeCollections ?? []) as $collectionIndex => $collection): ?>
    <?php
        $titleId = 'home-collection-' . $collectionIndex;
        $collectionUrl = '/shop?category=' . rawurlencode((string)$collection['slug']);
    ?>
    <section class="section home-collection" aria-labelledby="<?= e($titleId) ?>">
        <div class="container">
            <div class="product-grid home-collection__grid">
                <a class="home-collection__lead" href="<?= e($collectionUrl) ?>"
                   aria-labelledby="<?= e($titleId . '-action ' . $titleId) ?>">
                    <h2 id="<?= e($titleId) ?>"><?= e($collection['name']) ?></h2>
                    <span class="home-collection__action">
                        <span id="<?= e($titleId . '-action') ?>">View collection</span>
                        <span aria-hidden="true">→</span>
                    </span>
                </a>
                <?php foreach (array_slice($collection['products'], 0, 4) as $item): ?>
                    <?php require __DIR__ . '/home-product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endforeach; ?>
