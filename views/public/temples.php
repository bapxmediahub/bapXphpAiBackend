<section class="section temple-directory">
    <div class="container">
        <header class="section-header">
            <h1 class="section-title">Our Temples</h1>
            <p class="lede">Explore temples across Tamil Nadu and find addresses, opening hours, and directions where available.</p>
        </header>
        <?php if (empty($items)): ?>
            <p class="temple-directory__empty">Temple information will appear here soon.</p>
        <?php else: ?>
            <div class="temple-feature-list">
                <?php foreach ($items as $index => $item): ?>
                    <?php
                    $detailUrl = '/temples/' . rawurlencode($item['slug']);
                    $directions = \App\Services\TempleService::directionsUrl($item);
                    ?>
                    <article class="temple-directory-card">
                        <?php if (!empty($item['image_url'])): ?>
                            <img class="temple-directory-card__image" src="<?= e(webp_src($item['image_url'])) ?>" alt="" decoding="async"<?= $index >= 3 ? ' loading="lazy"' : '' ?>>
                        <?php endif; ?>
                        <div class="temple-directory-card__body">
                            <h2><?= e($item['name']) ?></h2>
                            <?php if (!empty($item['address'])): ?>
                                <p><?= e($item['address']) ?></p>
                            <?php endif; ?>
                            <div class="temple-directory-card__actions">
                                <a href="<?= e($detailUrl) ?>" class="btn btn-primary" aria-label="View details for <?= e($item['name']) ?>">View details</a>
                                <?php if ($directions !== null): ?>
                                    <a href="<?= e($directions) ?>" target="_blank" rel="noopener" class="btn btn-outline" aria-label="Get directions to <?= e($item['name']) ?> (opens in a new tab)">Directions</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="page-cta-card temple-directory__contact">
            <div>
                <h2>Questions about a visit?</h2>
                <p>Contact us for temple or pooja enquiries.</p>
            </div>
            <a class="btn btn-outline page-cta-card__button" href="/contact#contact-form">Let’s Get Connected →</a>
        </div>
    </div>
</section>
