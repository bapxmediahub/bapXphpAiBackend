<section class="section">
    <div class="container" style="margin-bottom:var(--space-2xl);">
        <div style="text-align:center;">
            <span class="eyebrow serif-accent">Sacred Spaces · Divine Energy</span>
            <h1 class="section-title" style="margin-bottom:var(--space-sm);">Our Temples in Chennai</h1>
            <p class="lede">Visit our sacred spaces for divine blessings, traditional pooja ceremonies, and spiritual awakening in Chennai, Tamil Nadu.</p>
        </div>
    </div>
    <?php if(empty($items)): ?>
        <div class="container">
            <div class="about-story-card reveal" style="text-align:center; padding:var(--space-3xl);">
                <div style="font-size:3rem; margin-bottom:var(--space-md);">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto;"><path d="M3 21h18"/><path d="M5 21V7l8-4 8 4v14"/><path d="M9 21v-4a2 2 0 012-2h2a2 2 0 012 2v4"/></svg>
                </div>
                <h2 style="font-family:var(--font-serif); margin:0 0 var(--space-sm);">No Temples Listed</h2>
                <p style="color:var(--color-text-muted);">Temple information will appear here soon.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="container">
            <div class="temple-feature-list">
                <?php foreach($items as $item): ?>
                    <article class="showcase-card temple-feature-card reveal">
                        <a class="temple-feature-card__media" href="/temples/<?= e($item['slug']) ?>" aria-label="View <?= e($item['name']) ?>">
                            <?php if(!empty($item['image_url'])): ?>
                                <img src="<?= e($item['image_url']) ?>" alt="<?= e($item['name']) ?>" decoding="async">
                            <?php else: ?>
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l8-4 8 4v14"/><path d="M9 21v-4a2 2 0 012-2h2a2 2 0 012 2v4"/></svg>
                            <?php endif; ?>
                        </a>
                        <div class="temple-feature-card__body">
                            <h2><?= e($item['name']) ?></h2>
                            <p><?= e($item['description']) ?></p>
                            <?php if(!empty($item['address'])): ?>
                                <p class="temple-feature-card__meta">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                    <?= e($item['address']) ?>
                                </p>
                            <?php endif; ?>
                            <?php if(!empty($item['timings'])): ?>
                                <p class="temple-feature-card__meta">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    <?= e($item['timings']) ?>
                                </p>
                            <?php endif; ?>
                            <div class="temple-feature-card__actions">
                                <a href="/temples/<?= e($item['slug']) ?>" class="btn btn-sm btn-primary">View Details</a>
                                <?php if(!empty($item['map_link'])): ?>
                                    <a href="<?= e($item['map_link']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                        Get Directions
                                    </a>
                                <?php endif; ?>
                            </div>
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
