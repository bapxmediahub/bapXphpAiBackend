<section class="booking-layout">
    <?php if(!$astrologer): ?>
        <div style="text-align:center; padding:var(--space-4xl) 0;">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto var(--space-md);"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <h1 style="font-family:var(--font-serif); margin:0 0 var(--space-sm);">Astrologer Not Found</h1>
            <p style="color:var(--color-text-muted); margin-bottom:var(--space-lg);">The astrologer profile you're looking for doesn't exist.</p>
            <a href="/consult" class="btn btn-primary">View All Astrologers</a>
        </div>
    <?php else: ?>
        <?php $profileLanguages=array_values(array_filter($astrologer['languages']??[])); $profileExperience=trim((string)($astrologer['experience_years']??'')); $profileReviewCount=(int)($reviewSummary['count']??0); ?>
        <div class="expert-layout">
            <div class="expert-main">
                <section class="expert-profile-card reveal">
                    <div class="expert-photo-wrap">
                        <img class="booking-profile__photo" src="<?= e($astrologer['photo_url'] ?? placeholder_img($astrologer['name'])) ?>" alt="<?= e($astrologer['name']) ?>">
                        <?php if($profileReviewCount>0): ?><span class="astro-rating-pill"><?= e(number_format((float)$reviewSummary['average'],1)) ?> · <?= e((string)$profileReviewCount) ?></span><?php endif; ?>
                    </div>
                    <div class="booking-profile__content">
                        <h1 class="booking-profile__name"><?= e($astrologer['name']) ?></h1>
                        <p class="booking-profile__meta"><?= e($astrologer['speciality'] ?? 'Vedic Astrology') ?></p>
                        <?php if($profileLanguages): ?><p class="booking-profile__meta">Languages: <?= e(implode(', ', $profileLanguages)) ?></p><?php endif; ?>
                        <?php if($profileExperience!==''): ?><p class="booking-profile__meta"><?= e($profileExperience) ?> years experience</p><?php endif; ?>
                        <p class="booking-profile__meta">Remote consultation by chat and direct call</p>
                        <p class="expert-credit-line">5 credits/message <span>0.5 credits/sec call</span></p>
                    </div>
                </section>

                <section class="expert-copy-panel reveal">
                    <span class="eyebrow">Remote consultation only</span>
                    <h2>About</h2>
                    <p>
                        <?= e($astrologer['description'] ?? 'Connect for practical spiritual guidance, horoscope clarity and family ritual support.') ?>
                    </p>
                    <p>
                        This service is handled through remote call and message consultation. Appointment date slots and per-astrologer booking forms are not used on this page.
                    </p>
                </section>

                <section class="expert-copy-panel reveal"><div class="expert-tabs"><strong>Reviews</strong><span><?= e((string)$profileReviewCount) ?> verified</span></div><p><?= $profileReviewCount>0?'Verified customer rating: '.e(number_format((float)$reviewSummary['average'],1)).' out of 5.':'No verified reviews yet.' ?></p></section>
            </div>

            <aside class="expert-side">
                <section class="expert-action-card reveal">
                    <div class="expert-price">
                        <strong>5 credits/message</strong>
                        <span>0.5 credits/sec call</span>
                    </div>
                    <div class="expert-action-grid">
                        <div class="astro-action-row">
                            <form class="astro-session-form" action="/consultation/initiate" method="post">
                                <input type="hidden" name="astrologer_slug" value="<?= e($astrologer['slug']) ?>">
                                <input type="hidden" name="mode" value="text_session">
                                <button type="submit" class="astro-action astro-action--icon astro-action--chat" aria-label="Start message session" title="Message">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.5 9.2 9.2 0 0 1-3.7-.8L3 21l1.8-5.3A8.2 8.2 0 0 1 4 11.5 8.4 8.4 0 0 1 12.5 3 8.4 8.4 0 0 1 21 11.5Z"/><path d="M8 10h8M8 14h5"/></svg>
                                <span class="sr-only">Message</span>
                                </button>
                            </form>
                            <form class="astro-session-form" action="/consultation/initiate" method="post">
                                <input type="hidden" name="astrologer_slug" value="<?= e($astrologer['slug']) ?>">
                                <input type="hidden" name="mode" value="direct_call">
                                <button type="submit" class="astro-action astro-action--icon astro-action--call" aria-label="Start call session" title="Call">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.7 19.7 0 0 1-8.6-3.1 19.1 19.1 0 0 1-5.9-5.9A19.7 19.7 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.4 2.1L8.1 10a16 16 0 0 0 5.9 5.9l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2Z"/></svg>
                                <span class="sr-only">Call</span>
                                </button>
                            </form>
                        </div>
                        <a href="/contact?subject=astrology#contact-form" class="astro-action astro-action--session">BOOK SESSION</a>
                    </div>
                    <p>1 rupee adds 20 credits. Minimum top-up is ₹10. Credits are only for astrologer text and call sessions.</p>
                </section>

                <?php if($profileReviewCount>0): ?><section class="ratings-panel reveal">
                    <h2>Ratings</h2>
                    <div class="ratings-panel__score"><?= e(number_format((float)$reviewSummary['average'],1)) ?></div>
                    <p><?= e((string)$profileReviewCount) ?> verified ratings</p>
                </section><?php endif; ?>

                <section class="trust-panel reveal">
                    <p>Private consultation rooms</p>
                    <p>Admin-managed astrologer profiles</p>
                    <p>100% Secure Payments</p>
                </section>

                <section class="consultation-panel__contact reveal">
                    <h3 style="font-family:var(--font-serif); margin:0 0 var(--space-xs);">Contact Sri Panchami Spiritual</h3>
                    <p style="margin:0 0 var(--space-sm); color:var(--color-text-muted); font-size:0.9rem;">For ritual requests and support-assisted sessions.</p>
                </section>
            </aside>
        </div>
    <?php endif; ?>
</section>
