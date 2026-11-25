<?php
$authoredPreview ??= false;
$headerUrl = $page['header_image_url'] ?? '';
?>
<section class="section document-page<?= $authoredPreview ? ' authored-page--preview' : '' ?>">
    <div class="container container--narrow">
        <header class="document-page__header">
            <?php if ($authoredPreview): ?><h3 class="section-title"><?= e($page['title']) ?></h3>
            <?php else: ?><h1 class="section-title"><?= e($page['title']) ?></h1><?php endif; ?>
            <?php if (\App\Services\MediaService::isPageHeaderPath($headerUrl)): ?>
                <img class="authored-page__image" src="<?= e($headerUrl) ?>"
                    alt="<?= e(is_string($page['header_image_alt'] ?? null) ? $page['header_image_alt'] : '') ?>" decoding="async">
            <?php endif; ?>
        </header>
        <article class="document-page__content"><?= $html ?></article>
    </div>
</section>
