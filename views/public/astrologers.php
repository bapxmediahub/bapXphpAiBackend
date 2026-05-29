<section class="section astrologers-page" style="padding-top:var(--space-xl);">
    <div class="container astrologers-hero">
        <div style="text-align:center;">
            <span class="eyebrow">Expert Guidance · Accurate Predictions</span>
            <h1 class="section-title" style="margin-bottom:var(--space-sm);">Book Vedic Astrology Consultation in Chennai</h1>
            <p class="lede">Choose an astrologer, send messages for 5 credits each, or start a direct call at 0.5 credits per second.</p>
        </div>
    </div>
    <?php if(empty($items)): ?>
        <div class="container" style="text-align:center; padding:var(--space-4xl) 0;">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto var(--space-md);"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 000 20 14.5 14.5 0 000-20"/><path d="M2 12h20"/></svg>
            <h2 style="font-family:var(--font-serif); margin:0 0 var(--space-sm);">No Astrologers Available</h2>
            <p style="color:var(--color-text-muted);">Astrologer profiles will appear here soon.</p>
        </div>
    <?php else: ?>
        <div class="container">
            <div class="astrologer-grid">
                <?php foreach($items as $item): ?>
                    <article class="astrologer-card reveal">
                        <div class="astrologer-card__media">
                            <img class="astrologer-card__photo" src="<?= e($item['photo_url'] ?? 'https://placehold.co/800x1000/fdfbf7/d4af37?text=Guru') ?>" alt="<?= e($item['name'] ?? 'Astrologer') ?>" loading="lazy">
                            <div class="astrologer-card__media-badge">Live expert</div>
                        </div>
                        <div class="astrologer-card__body astrologer-card__body--portrait">
                            <div class="astrologer-card__title-row">
                                <h3 class="astrologer-card__name"><?= e($item['name'] ?? 'Astrologer') ?></h3>
                                <span class="astrologer-card__status">Verified</span>
                            </div>
                            <p class="astrologer-card__speciality"><?= e($item['speciality'] ?? 'Vedic Astrology') ?></p>
                            <p class="astrologer-card__bio"><?= e($item['description'] ?? '') ?></p>
                            <div class="astrologer-card__meta">
                                <span><?= e($item['experience_years'] ?? 'N/A') ?> years</span>
                                <span><?= e(implode(' · ', array_slice($item['languages'] ?? [], 0, 2))) ?></span>
                                <span><?= e(implode(' · ', array_map(fn($mode) => ucwords(str_replace(['-', '_'], ' ', $mode)), array_slice($item['modes'] ?? [], 0, 2)))) ?></span>
                            </div>
                        </div>
                        <div class="astrologer-card__footer">
                            <span class="astrologer-card__price">5 credits/message · 0.5 credits/sec call</span>
                            <?php if(!empty($item['slug'])): ?>
                                <div class="astrologer-card__actions">
                                    <a href="/astrologers/<?= e($item['slug']) ?>" class="btn btn-sm btn-ghost">Know More</a>
                                    <a href="/astrologers/<?= e($item['slug']) ?>?mode=direct_call" class="btn btn-sm btn-call">Call</a>
                                    <a href="/astrologers/<?= e($item['slug']) ?>?mode=text_session" class="btn btn-sm btn-message">Message</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
