<section class="section document-page">
    <div class="container container--narrow">
        <header class="document-page__header">
            <span class="eyebrow serif-accent">Legal</span>
            <h1 class="section-title"><?= e($document['title'] ?? 'Privacy Policy') ?></h1>
            <p class="lede">A clear explanation of how the platform handles personal information.</p>
        </header>
        <article class="document-page__content reveal"><?= $document['html'] ?? '' ?></article>
    </div>
</section>
