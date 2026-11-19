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
    $mapUrl = trim((string)($temple['map_embed_url'] ?? ''));
    $canEmbedMap = filter_var($mapUrl, FILTER_VALIDATE_URL) && parse_url($mapUrl, PHP_URL_SCHEME) === 'https';
    $hasVisitDetails = !empty($temple['address']) || !empty($temple['timings']) || !empty($temple['phone']);
    ?>
    <section class="section temple-visit">
        <div class="container">
            <a class="temple-visit__back" href="/temples">← All temples</a>
            <div class="temple-visit__overview">
                <?php if (!empty($temple['image_url'])): ?>
                    <div class="temple-photo temple-visit__media">
                        <img src="<?= e(webp_src($temple['image_url'])) ?>" alt="<?= e($temple['name']) ?>" decoding="async">
                    </div>
                <?php endif; ?>
                <div class="temple-visit__info">
                    <h1 class="section-title" id="temple-visit-details" data-page-section="Visit details" tabindex="-1"><?= e($temple['name']) ?></h1>
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
                        <a href="/contact#contact-form" class="btn btn-outline">Ask about pooja</a>
                    </div>
                </div>
            </div>
            <div data-section-index hidden></div>
            <div class="temple-visit__content">
                <?php if (!empty($temple['description'])): ?>
                    <h2 id="temple-about" data-page-section="About" tabindex="-1">About the temple</h2>
                    <p><?= e($temple['description']) ?></p>
                <?php endif; ?>
                <?php if (!empty($temple['pooja_types']) && is_array($temple['pooja_types'])): ?>
                    <h2 id="temple-poojas" data-page-section="Poojas" tabindex="-1">Available poojas</h2>
                    <ul>
                        <?php foreach ($temple['pooja_types'] as $pooja): ?>
                            <li><?= e($pooja) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($canEmbedMap || $directions !== null): ?>
                    <h2 id="temple-location" data-page-section="Location" tabindex="-1">Location</h2>
                    <details class="temple-map" data-temple-map>
                        <summary><span class="temple-map__show">Show map</span><span class="temple-map__hide">Hide map</span></summary>
                        <div class="temple-map__content">
                            <?php if ($canEmbedMap): ?>
                                <template>
                                    <iframe class="temple-visit__map" src="<?= e($mapUrl) ?>" title="Map for <?= e($temple['name']) ?>" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                                </template>
                            <?php endif; ?>
                            <a href="<?= e($directions ?? $mapUrl) ?>" target="_blank" rel="noopener noreferrer">Open map in a new tab <span aria-hidden="true">↗</span></a>
                        </div>
                    </details>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>
