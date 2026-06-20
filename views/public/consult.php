<section class="section astrologers-page" style="padding-top:var(--space-xl);">
    <div class="container astrologers-hero">
        <div style="text-align:center;">
            <span class="eyebrow">Expert Guidance · Call and Message Only</span>
            <h1 class="section-title" style="margin-bottom:var(--space-sm);">Talk to Astrologers Online</h1>
            <p class="lede">Find an expert by name, language, speciality, or current status.</p>
        </div>
    </div>
    <?php if(empty($items)): ?>
        <div class="container" style="text-align:center; padding:var(--space-4xl) 0;">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto var(--space-md);"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 000 20 14.5 14.5 0 000-20"/><path d="M2 12h20"/></svg>
            <h2 style="font-family:var(--font-serif); margin:0 0 var(--space-sm);">No Astrologers Available</h2>
            <p style="color:var(--color-text-muted);">Astrologer profiles will appear here soon.</p>
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
                    <span>Search Astrologer</span>
                    <input type="search" id="astro-search-input" placeholder="Search by name, language, speciality">
                </label>
                <label class="astro-filter">
                    <span>Status</span>
                    <select id="astro-status-filter">
                        <option value="">All</option>
                        <option value="online">Online</option>
                        <option value="busy">Waitlist</option>
                        <option value="offline">Offline</option>
                    </select>
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
                        $availability = $item['availability_status'] ?? 'offline';
                        $state = $availability === 'available' ? 'online' : (in_array($availability, ['busy', 'waitlist'], true) ? 'busy' : 'offline');
                        $statusLabel = $state === 'online' ? 'Available' : ($state === 'busy' ? 'Waitlist' : 'Offline');
                        $summary = isset($reviews) ? $reviews->summary('astrologer', $item['slug'] ?? '') : ['average' => 0, 'count' => 0];
                        $languageText = implode(', ', array_slice(array_values(array_filter($item['languages'] ?? [])), 0, 2));
                        $experience = trim((string)($item['experience_years'] ?? ''));
                        $speciality = $item['speciality'] ?? 'Vedic Astrology';
                    ?>
                    <article class="astro-market-card astro-market-card--<?= e($state) ?> reveal" data-astro-card data-status="<?= e($state) ?>" data-language="<?= e(strtolower(implode(' ', $item['languages'] ?? []))) ?>" data-search="<?= e(strtolower(($item['name'] ?? '') . ' ' . $languageText . ' ' . $speciality)) ?>">
                        <a class="astro-market-photo" href="/consult/<?= e($item['slug'] ?? '') ?>" aria-label="View <?= e($item['name'] ?? 'Astrologer') ?>">
                            <span class="astro-market-photo-frame"><img class="astro-market-photo-img astro-market-photo-img--<?= e($item['slug'] ?? 'default') ?>" src="<?= e($item['photo_url'] ?? 'https://placehold.co/800x1000/fdfbf7/d4af37?text=Guru') ?>" alt="<?= e($item['name'] ?? 'Astrologer') ?>" loading="lazy"></span>
                            <span class="astro-status-dot" aria-label="<?= e(ucfirst($state)) ?>"></span>
                            <span class="astro-status-label"><?= e($statusLabel) ?></span>
                            <?php if(($summary['count'] ?? 0) > 0): ?><span class="astro-rating-pill"><?= e(number_format((float)$summary['average'], 1)) ?> · <?= e((string)$summary['count']) ?></span><?php endif; ?>
                        </a>
                        <div class="astro-market-info">
                            <a href="/consult/<?= e($item['slug'] ?? '') ?>" class="astro-market-name"><?= e($item['name'] ?? 'Astrologer') ?></a>
                            <p class="astro-market-speciality"><?= e($speciality) ?></p>
                            <?php if($languageText !== '' || $experience !== ''): ?><div class="astro-market-meta"><?php if($languageText !== ''): ?><span><?= e($languageText) ?></span><?php endif; ?><?php if($experience !== ''): ?><span><?= e($experience) ?> years</span><?php endif; ?></div><?php endif; ?>
                        </div>
                        <div class="astro-market-price">
                            <strong><?= e((string)($item['message_credit_cost'] ?? 5)) ?> credits/message</strong>
                            <span><?= e((string)($item['call_credit_per_second'] ?? 0.5)) ?> credits/sec call</span>
                        </div>
                        <div class="astro-market-actions">
                            <?php if(!empty($item['slug'])): ?>
                                <div class="astro-action-row">
                                    <?php if($state === 'online'): ?>
                                        <form class="astro-session-form" action="/appointments/book" method="post">
                                            <input type="hidden" name="astrologer_slug" value="<?= e($item['slug']) ?>">
                                            <input type="hidden" name="mode" value="text_session">
                                            <button type="submit" class="astro-action astro-action--icon astro-action--chat" aria-label="Start message session" title="Message">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.5 9.2 9.2 0 0 1-3.7-.8L3 21l1.8-5.3A8.2 8.2 0 0 1 4 11.5 8.4 8.4 0 0 1 12.5 3 8.4 8.4 0 0 1 21 11.5Z"/><path d="M8 10h8M8 14h5"/></svg>
                                            <span class="sr-only">Message</span>
                                            </button>
                                        </form>
                                        <form class="astro-session-form" action="/appointments/book" method="post">
                                            <input type="hidden" name="astrologer_slug" value="<?= e($item['slug']) ?>">
                                            <input type="hidden" name="mode" value="direct_call">
                                            <button type="submit" class="astro-action astro-action--icon astro-action--call" aria-label="Start call session" title="Call">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.7 19.7 0 0 1-8.6-3.1 19.1 19.1 0 0 1-5.9-5.9A19.7 19.7 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.4 2.1L8.1 10a16 16 0 0 0 5.9 5.9l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2Z"/></svg>
                                            <span class="sr-only">Call</span>
                                            </button>
                                        </form>
                                    <?php elseif($state === 'busy'): ?>
                                        <form class="astro-session-form" action="/appointments/book" method="post">
                                            <input type="hidden" name="astrologer_slug" value="<?= e($item['slug']) ?>">
                                            <input type="hidden" name="mode" value="text_session">
                                            <input type="hidden" name="queue_status" value="waitlist">
                                            <button type="submit" class="astro-action astro-action--icon astro-action--chat" aria-label="Join message waitlist" title="Join message waitlist">
                                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.5 9.2 9.2 0 0 1-3.7-.8L3 21l1.8-5.3A8.2 8.2 0 0 1 4 11.5 8.4 8.4 0 0 1 12.5 3 8.4 8.4 0 0 1 21 11.5Z"/><path d="M8 10h8M8 14h5"/></svg>
                                                <span class="sr-only">Join message waitlist</span>
                                            </button>
                                        </form>
                                        <span class="astro-action astro-action--icon astro-action--disabled" aria-disabled="true" title="Call unavailable"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.7 19.7 0 0 1-8.6-3.1 19.1 19.1 0 0 1-5.9-5.9A19.7 19.7 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.4 2.1L8.1 10a16 16 0 0 0 5.9 5.9l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2Z"/></svg><span class="sr-only">Call unavailable</span></span>
                                    <?php else: ?>
                                        <span class="astro-action astro-action--icon astro-action--disabled" aria-disabled="true" title="Message unavailable"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.5 9.2 9.2 0 0 1-3.7-.8L3 21l1.8-5.3A8.2 8.2 0 0 1 4 11.5 8.4 8.4 0 0 1 12.5 3 8.4 8.4 0 0 1 21 11.5Z"/><path d="M8 10h8M8 14h5"/></svg><span class="sr-only">Message unavailable</span></span>
                                        <span class="astro-action astro-action--icon astro-action--disabled" aria-disabled="true" title="Call unavailable"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.7 19.7 0 0 1-8.6-3.1 19.1 19.1 0 0 1-5.9-5.9A19.7 19.7 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.4 2.1L8.1 10a16 16 0 0 0 5.9 5.9l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2Z"/></svg><span class="sr-only">Call unavailable</span></span>
                                    <?php endif; ?>
                                    <a class="astro-action astro-action--icon astro-action--profile" href="/consult/<?= e($item['slug']) ?>" aria-label="View Profile" title="View Profile">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                                        <span class="sr-only">View Profile</span>
                                    </a>
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
                <p>Use the contact form for astrology sessions, product questions, temple guidance, or VIP direct astrology visit requests.</p>
            </div>
            <a class="btn btn-primary page-cta-card__button" href="/contact#contact-form">Let’s Get Connected →</a>
        </div>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('astro-search-input');
    var status = document.getElementById('astro-status-filter');
    var language = document.getElementById('astro-language-filter');
    if (!input || !status) return;
    var cards = Array.prototype.slice.call(document.querySelectorAll('[data-astro-card]'));
    function filterCards() {
        var term = input.value.trim().toLowerCase();
        var statusTerm = status.value;
        var languageTerm = language ? language.value : '';
        cards.forEach(function (card) {
            var searchMatch = term === '' || String(card.dataset.search || '').includes(term);
            var statusMatch = statusTerm === '' || card.dataset.status === statusTerm;
            var languageMatch = languageTerm === '' || String(card.dataset.language || '').includes(languageTerm);
            card.hidden = !(searchMatch && statusMatch && languageMatch);
        });
    }
    input.addEventListener('input', filterCards);
    status.addEventListener('change', filterCards);
    if (language) language.addEventListener('change', filterCards);
});
</script>
