<section class="section astrologers-page">
    <?php $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16)); ?>
    <div class="container astrologers-hero">
        <div style="text-align:center;">
            <span class="eyebrow">Private guidance · Scheduled online</span>
            <h1 class="section-title" style="margin-bottom:var(--space-sm);">Choose Your Consultant</h1>
            <p class="lede">Compare expertise, languages, and experience, then request a suitable appointment.</p>
        </div>
    </div>
    <?php if(empty($items)): ?>
        <div class="container" style="text-align:center; padding:var(--space-4xl) 0;">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto var(--space-md);"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 000 20 14.5 14.5 0 000-20"/><path d="M2 12h20"/></svg>
            <h2 style="font-family:var(--font-serif); margin:0 0 var(--space-sm);">No Consultants Available</h2>
            <p style="color:var(--color-text-muted);">Consultant profiles will appear here soon.</p>
        </div>
    <?php else: ?>
        <?php
            $filterLanguages = [];
            foreach ($items as $filterItem) {
                foreach (($filterItem['languages'] ?? []) as $filterLanguage) {
                    $filterLanguage = trim((string)$filterLanguage);
                    if ($filterLanguage !== '') $filterLanguages[$filterLanguage] = $filterLanguage;
                }
            }
            ksort($filterLanguages);
        ?>
        <div class="container">
            <div class="astro-market-toolbar reveal">
                <label class="astro-search">
                    <span>Search Consultant</span>
                    <input type="search" id="astro-search-input" placeholder="Search by name, language, speciality">
                </label>
                <?php if($filterLanguages): ?>
                <label class="astro-filter">
                    <span>Language</span>
                    <select id="astro-language-filter">
                        <option value="">All</option>
                        <?php foreach($filterLanguages as $filterLanguage): ?>
                            <option value="<?= e(strtolower($filterLanguage)) ?>"><?= e($filterLanguage) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php endif; ?>
            </div>
            <div class="astro-market-grid">
                <?php foreach($items as $item): ?>
                    <?php
                        $summary = isset($reviews) ? $reviews->summary('astrologer', $item['slug'] ?? '') : ['average' => 0, 'count' => 0];
                        $languageText = implode(', ', array_slice(array_values(array_filter($item['languages'] ?? [])), 0, 2));
                        $experience = trim((string)($item['experience_years'] ?? ''));
                        $speciality = $item['speciality'] ?? 'Vedic Astrology';
                    ?>
                    <article class="astro-market-card reveal" data-astro-card data-language="<?= e(strtolower(implode(' ', $item['languages'] ?? []))) ?>" data-search="<?= e(strtolower(($item['name'] ?? '') . ' ' . $languageText . ' ' . $speciality)) ?>">
                        <a class="astro-market-photo" href="/consult/<?= e($item['slug'] ?? '') ?>" aria-label="View <?= e($item['name'] ?? 'Astrologer') ?>">
                            <span class="astro-market-photo-frame"><img class="astro-market-photo-img astro-market-photo-img--<?= e($item['slug'] ?? 'default') ?>" src="<?= e($item['photo_url'] ?? placeholder_img($item['name'] ?? 'Astrologer')) ?>" alt="<?= e($item['name'] ?? 'Astrologer') ?>" loading="lazy"></span>
                            <?php if(($summary['count'] ?? 0) > 0): ?><span class="astro-rating-pill"><?= e(number_format((float)$summary['average'], 1)) ?> · <?= e((string)$summary['count']) ?></span><?php endif; ?>
                        </a>
                        <div class="astro-market-info">
                            <a href="/consult/<?= e($item['slug'] ?? '') ?>" class="astro-market-name"><?= e($item['name'] ?? 'Astrologer') ?></a>
                            <p class="astro-market-speciality"><?= e($speciality) ?></p>
                            <?php if($languageText !== '' || $experience !== ''): ?><div class="astro-market-meta"><?php if($languageText !== ''): ?><span><?= e($languageText) ?></span><?php endif; ?><?php if($experience !== ''): ?><span><?= e($experience) ?> years</span><?php endif; ?></div><?php endif; ?>
                        </div>
                        <div class="astro-market-actions">
                            <?php if(!empty($item['slug'])): ?>
                                <div class="astro-action-row">
                                    <a class="astro-action" href="/consult/<?= e($item['slug']) ?>">View profile</a>
                                    <a class="astro-action astro-action--primary" href="/consult/<?= e($item['slug']) ?>#booking-form">Book appointment</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    <div class="container" style="margin-top:var(--space-2xl);">
        <div class="page-cta-card reveal">
            <div>
                <span class="page-cta-card__eyebrow">Need Guidance?</span>
                <h3>Start a Consultation Request</h3>
                <p>Use the contact form for consultation sessions, product questions, temple guidance, or VIP direct visits.</p>
            </div>
            <a class="btn btn-primary page-cta-card__button" href="/contact#contact-form">Let’s Get Connected →</a>
        </div>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('astro-search-input');
    var language = document.getElementById('astro-language-filter');
    if (!input) return;
    var cards = Array.prototype.slice.call(document.querySelectorAll('[data-astro-card]'));
    function filterCards() {
        var term = input.value.trim().toLowerCase();
        var languageTerm = language ? language.value : '';
        cards.forEach(function (card) {
            var searchMatch = term === '' || String(card.dataset.search || '').includes(term);
            var languageMatch = languageTerm === '' || String(card.dataset.language || '').includes(languageTerm);
            card.hidden = !(searchMatch && languageMatch);
        });
    }
    input.addEventListener('input', filterCards);
    if (language) language.addEventListener('change', filterCards);
});
</script>
