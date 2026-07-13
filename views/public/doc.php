<section class="section document-page">
    <div class="container container--narrow">
        <nav class="breadcrumbs document-breadcrumbs" aria-label="Breadcrumb">
            <a href="/docs">Help Center</a><span aria-hidden="true">/</span><span><?= e($document['title'] ?? 'Guide') ?></span>
        </nav>
        <article class="document-guide">
            <header class="document-page__header">
                <span class="eyebrow serif-accent">Customer Guide</span>
                <h1 class="section-title"><?= e($document['title'] ?? 'Guide') ?></h1>
                <?php if (!empty($document['summary'])): ?><p class="lede"><?= e($document['summary']) ?></p><?php endif; ?>
            </header>
            <div class="document-page__content"><?= $document['html'] ?? '' ?></div>
        </article>
        <div class="document-help-cta">
            <div><strong>Still need help?</strong><span>Send your question to our support team.</span></div>
            <a class="btn btn-primary" href="/support">Contact support</a>
        </div>
    </div>
</section>
