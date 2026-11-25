<?php
$authoredPreview ??= false;
$headerUrl = $page['header_image_url'] ?? '';
?>
<section class="section authored-page<?= $authoredPreview ? ' authored-page--preview' : '' ?>">
    <article class="container document-page__content">
        <header>
            <?php if ($authoredPreview): ?><h3 class="authored-page__title"><?= e($page['title']) ?></h3>
            <?php else: ?><h1 class="authored-page__title"><?= e($page['title']) ?></h1><?php endif; ?>
            <?php if (\App\Services\MediaService::isPageHeaderPath($headerUrl)): ?>
                <img class="authored-page__image" src="<?= e($headerUrl) ?>"
                    alt="<?= e(is_string($page['header_image_alt'] ?? null) ? $page['header_image_alt'] : '') ?>" decoding="async">
            <?php endif; ?>
        </header>
        <div class="blog-post__body"><?= $html ?></div>
    </article>
</section>
