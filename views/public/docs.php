<section class="section document-page">
    <div class="container container--narrow">
        <header class="document-page__header">
            <span class="eyebrow serif-accent">Help Center</span>
            <h1 class="section-title">How can we help?</h1>
            <p class="lede">Simple guides for creating your account, ordering products, and speaking with a consultant.</p>
        </header>
        <div class="document-index">
            <?php foreach (($pages ?? []) as $page): ?>
                <a class="document-index__item reveal" href="/docs/<?= e($page['slug']) ?>">
                    <span class="document-index__icon" aria-hidden="true"><?= e(strtoupper(substr((string)($page['icon'] ?? 'G'), 0, 1))) ?></span>
                    <h2><?= e($page['title']) ?></h2>
                    <p><?= e($page['summary']) ?></p>
                    <span class="document-index__link">Read guide</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
