<section class="section document-page">
    <div class="container container--narrow">
        <header class="document-page__header">
            <span class="eyebrow serif-accent">Documentation</span>
            <h1 class="section-title">Platform Documentation</h1>
            <p class="lede">Guides are maintained as Markdown files so the product and its documentation stay aligned.</p>
        </header>
        <div class="document-index">
            <?php foreach (($pages ?? []) as $page): ?>
                <a class="document-index__item reveal" href="/docs#<?= e($page['slug']) ?>" id="<?= e($page['slug']) ?>">
                    <span class="eyebrow">Guide</span>
                    <h2><?= e($page['title']) ?></h2>
                    <p><?= e($page['summary']) ?></p>
                    <span class="document-index__link">Read guide</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
