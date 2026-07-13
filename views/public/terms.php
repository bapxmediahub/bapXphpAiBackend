<section class="section document-page">
    <div class="container container--narrow">
        <header class="document-page__header">
            <span class="eyebrow serif-accent">Legal</span>
            <h1 class="section-title"><?= e($document['title'] ?? 'Terms & Conditions') ?></h1>
            <p class="lede">The rules for using the platform, purchasing products, and starting consultations.</p>
        </header>
        <article class="document-page__content reveal"><?= $document['html'] ?? '' ?></article>
    </div>
</section>
