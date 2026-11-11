<?php if (!$temple): ?>
    <section class="section">
        <div class="container container--narrow section-header">
            <h1 class="section-title">Temple not found</h1>
            <a href="/temples" class="btn btn-primary">Browse temples</a>
        </div>
    </section>
<?php else: ?>
    <?php
    $directions = \App\Services\TempleService::directionsUrl($temple);
    $hasVisitDetails = !empty($temple['address']) || !empty($temple['timings']) || !empty($temple['phone']);
    ?>
    <section class="section temple-visit">
        <div class="container">
            <a class="temple-visit__back" href="/temples">← All temples</a>
            <div class="temple-visit__overview">
                <div class="temple-visit__info">
                    <h1 class="section-title"><?= e($temple['name']) ?></h1>
                    <?php if ($hasVisitDetails): ?>
                        <dl class="temple-visit__facts">
                            <?php if (!empty($temple['address'])): ?>
                                <div><dt>Address</dt><dd><?= e($temple['address']) ?></dd></div>
                            <?php endif; ?>
                            <?php if (!empty($temple['timings'])): ?>
                                <div><dt>Opening hours</dt><dd><?= e($temple['timings']) ?></dd></div>
                            <?php endif; ?>
                            <?php if (!empty($temple['phone'])): ?>
                                <div><dt>Phone</dt><dd><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $temple['phone'])) ?>"><?= e($temple['phone']) ?></a></dd></div>
                            <?php endif; ?>
                        </dl>
                    <?php endif; ?>
                    <div class="temple-directory-card__actions">
                        <?php if ($directions !== null): ?>
                            <a href="<?= e($directions) ?>" class="btn btn-primary" target="_blank" rel="noopener" aria-label="Get directions to <?= e($temple['name']) ?> (opens in a new tab)">Directions</a>
                        <?php endif; ?>
                        <a href="/contact#contact-form" class="btn <?= $directions === null ? 'btn-primary' : 'btn-outline' ?>">Ask about pooja</a>
                    </div>
                </div>
                <?php if (!empty($temple['image_url'])): ?>
                    <img class="temple-visit__image" src="<?= e(webp_src($temple['image_url'])) ?>" alt="<?= e($temple['name']) ?>" decoding="async">
                <?php endif; ?>
            </div>
            <div class="temple-visit__content">
                <?php if (!empty($temple['description'])): ?>
                    <h2>About the temple</h2>
                    <p><?= e($temple['description']) ?></p>
                <?php endif; ?>
                <?php if (!empty($temple['pooja_types']) && is_array($temple['pooja_types'])): ?>
                    <h2>Available poojas</h2>
                    <ul>
                        <?php foreach ($temple['pooja_types'] as $pooja): ?>
                            <li><?= e($pooja) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if (!empty($temple['map_embed_url'])): ?>
                    <iframe class="temple-visit__map" src="<?= e($temple['map_embed_url']) ?>" title="Map for <?= e($temple['name']) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>
