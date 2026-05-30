<section class="booking-layout">
    <?php if(!$astrologer): ?>
        <div style="text-align:center; padding:var(--space-4xl) 0;">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto var(--space-md);"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <h1 style="font-family:var(--font-serif); margin:0 0 var(--space-sm);">Astrologer Not Found</h1>
            <p style="color:var(--color-text-muted); margin-bottom:var(--space-lg);">The astrologer profile you're looking for doesn't exist.</p>
            <a href="/astrologers" class="btn btn-primary">View All Astrologers</a>
        </div>
    <?php else: ?>
        <div class="expert-layout">
            <div class="expert-main">
                <section class="expert-profile-card reveal">
                    <div class="expert-photo-wrap">
                        <img class="booking-profile__photo" src="<?= e($astrologer['photo_url'] ?? 'https://placehold.co/800x1000/fdfbf7/d4af37?text=Guru') ?>" alt="<?= e($astrologer['name']) ?>">
                        <span class="astro-status-dot" aria-label="Online"></span>
                        <span class="astro-rating-pill"><?= e((string)(($reviewSummary['count'] ?? 0) > 0 ? $reviewSummary['average'] : 4.9)) ?></span>
                    </div>
                    <div class="booking-profile__content">
                        <h1 class="booking-profile__name"><?= e($astrologer['name']) ?></h1>
                        <p class="booking-profile__meta"><?= e($astrologer['speciality'] ?? 'Vedic Astrology') ?></p>
                        <p class="booking-profile__meta">Languages: <?= e(implode(', ', $astrologer['languages'] ?? [])) ?></p>
                        <p class="booking-profile__meta"><?= e($astrologer['experience_years'] ?? 'N/A') ?> Years experience</p>
                        <p class="booking-profile__meta">Remote consultation by chat and direct call</p>
                        <p class="expert-credit-line">5 credits/message <span>0.5 credits/sec call</span></p>
                    </div>
                    <button class="astro-follow" type="button">+ Follow</button>
                </section>

                <section class="gift-panel reveal">
                    <h2>Send gifts</h2>
                    <button type="button">Gift</button>
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

                <section class="expert-copy-panel reveal">
                    <div class="expert-tabs">
                        <strong>Reviews</strong>
                        <span>All ratings</span>
                    </div>
                    <div class="review-list">
                        <article>
                            <strong>K B...</strong>
                            <span>4.9 rating</span>
                            <p>Accurate reading and clear guidance.</p>
                        </article>
                        <article>
                            <strong>Anonymous</strong>
                            <span>4.8 rating</span>
                            <p>Helpful remote consultation for family decisions.</p>
                        </article>
                    </div>
                </section>
            </div>

            <aside class="expert-side">
                <section class="expert-action-card reveal">
                    <div class="expert-price">
                        <strong>5 credits/message</strong>
                        <span>0.5 credits/sec call</span>
                    </div>
                    <span class="flat-deal">Flat Deal</span>
                    <div class="expert-action-grid">
                        <div class="astro-action-row">
                            <form class="astro-session-form" action="/appointments/book" method="post">
                                <input type="hidden" name="astrologer_slug" value="<?= e($astrologer['slug']) ?>">
                                <input type="hidden" name="mode" value="text_session">
                                <button type="submit" class="astro-action astro-action--icon astro-action--chat" aria-label="Start message session" title="Message">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.5 9.2 9.2 0 0 1-3.7-.8L3 21l1.8-5.3A8.2 8.2 0 0 1 4 11.5 8.4 8.4 0 0 1 12.5 3 8.4 8.4 0 0 1 21 11.5Z"/><path d="M8 10h8M8 14h5"/></svg>
                                <span class="sr-only">Message</span>
                                </button>
                            </form>
                            <form class="astro-session-form" action="/appointments/book" method="post">
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

                <section class="ratings-panel reveal">
                    <h2>Ratings</h2>
                    <div class="ratings-panel__score"><?= e((string)(($reviewSummary['count'] ?? 0) > 0 ? $reviewSummary['average'] : 4.9)) ?></div>
                    <p><?= e((string)(($reviewSummary['count'] ?? 0) > 0 ? $reviewSummary['count'] : 87)) ?> ratings</p>
                    <?php foreach([5 => 92, 4 => 14, 3 => 4, 2 => 0, 1 => 0] as $stars => $width): ?>
                        <div class="rating-row"><span><?= e((string)$stars) ?></span><i style="width:<?= e((string)$width) ?>%;"></i></div>
                    <?php endforeach; ?>
                </section>

                <section class="trust-panel reveal">
                    <p>Money Back Guarantee</p>
                    <p>Verified Expert Astrologers</p>
                    <p>100% Secure Payments</p>
                </section>

                <section class="consultation-panel__contact reveal">
                    <h3 style="font-family:var(--font-serif); margin:0 0 var(--space-xs);">Contact Sri Panchami Spiritual</h3>
                    <p style="margin:0 0 var(--space-sm); color:var(--color-text-muted); font-size:0.9rem;">For ritual requests, store visit and support-assisted sessions.</p>
                    <p style="margin:0; color:var(--color-text-muted); font-size:0.9rem;">
                        23, 1st Cross Street Kothari Nagar<br>
                        Ramapuram, Chennai, Tamil Nadu 600089
                    </p>
                </section>
            </aside>
        </div>
    <?php endif; ?>
</section>
