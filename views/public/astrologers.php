<section class="section" style="padding-top:var(--space-xl);">
    <div class="container" style="margin-bottom:var(--space-2xl);">
        <div style="text-align:center;">
            <span class="eyebrow">Expert Guidance · Accurate Predictions</span>
            <h1 class="section-title" style="margin-bottom:var(--space-sm);">Book Vedic Astrology Consultation in Chennai</h1>
            <p class="lede">Connect with our experienced Vedic astrologers for kundli matching, horoscope reading, career guidance, and personalized remedies. Consultations are available as private text sessions or direct call sessions.</p>
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
                        <div class="astrologer-card__header">
                            <img class="astrologer-card__photo" src="<?= e($item['photo_url'] ?? 'https://placehold.co/100x100/fdfbf7/d4af37?text=Guru') ?>" alt="<?= e($item['name'] ?? 'Astrologer') ?>" loading="lazy">
                            <div>
                                <h3 class="astrologer-card__name"><?= e($item['name'] ?? 'Astrologer') ?></h3>
                                <p class="astrologer-card__speciality"><?= e($item['speciality'] ?? 'Vedic Astrology') ?></p>
                            </div>
                        </div>
                        <div class="astrologer-card__body">
                            <div class="astrologer-card__stat"><span class="astrologer-card__stat-label">Experience</span><span class="astrologer-card__stat-value"><?= e($item['experience_years'] ?? 'N/A') ?> years</span></div>
                            <div class="astrologer-card__stat"><span class="astrologer-card__stat-label">Languages</span><span class="astrologer-card__stat-value"><?= e(implode(', ', array_slice($item['languages'] ?? [], 0, 3))) ?></span></div>
                            <div class="astrologer-card__stat"><span class="astrologer-card__stat-label">Modes</span><span class="astrologer-card__stat-value"><?= e(implode(', ', array_map(fn($mode) => ucwords(str_replace(['-', '_'], ' ', $mode)), array_slice($item['modes'] ?? [], 0, 2)))) ?></span></div>
                        </div>
                        <div class="astrologer-card__footer">
                            <span class="astrologer-card__price">₹<?= e((string)($item['text_session_prm'] ?? 15)) ?> PRM <span style="font-size:0.75rem; color:var(--color-text-muted); font-weight:400;">text</span> · ₹<?= e((string)($item['call_session_prm'] ?? 15)) ?> PRM <span style="font-size:0.75rem; color:var(--color-text-muted); font-weight:400;">call</span></span>
                            <?php if(!empty($item['slug'])): ?><a href="/astrologers/<?= e($item['slug']) ?>" class="btn btn-sm btn-primary">Check Availability</a><?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
