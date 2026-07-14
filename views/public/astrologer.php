<section class="booking-layout">
    <?php $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16)); ?>
    <?php if(!$astrologer): ?>
        <div style="text-align:center; padding:var(--space-4xl) 0;">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto var(--space-md);"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <h1 style="font-family:var(--font-serif); margin:0 0 var(--space-sm);">Consultant Not Found</h1>
            <p style="color:var(--color-text-muted); margin-bottom:var(--space-lg);">The consultant profile you're looking for doesn't exist.</p>
            <a href="/consult" class="btn btn-primary">View All Consultants</a>
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
                        <p class="booking-profile__meta">Scheduled private consultation</p>
                    </div>
                </section>

                <section class="expert-copy-panel reveal">
                    <span class="eyebrow">Appointment consultation</span>
                    <h2>About</h2>
                    <p>
                        <?= e($astrologer['description'] ?? 'Connect for practical spiritual guidance, horoscope clarity and family ritual support.') ?>
                    </p>
                    <p>
                        Choose your preferred date and time below. The consultant will review and confirm your appointment request.
                    </p>
                </section>

                <section class="expert-copy-panel reveal"><div class="expert-tabs"><strong>Reviews</strong><span><?= e((string)$profileReviewCount) ?> verified</span></div><p><?= $profileReviewCount>0?'Verified customer rating: '.e(number_format((float)$reviewSummary['average'],1)).' out of 5.':'No verified reviews yet.' ?></p></section>
            </div>

            <aside class="expert-side">
                <section class="expert-action-card reveal">
                    <h2>Book this consultant</h2>
                    <form class="booking-request-form" action="/consultation/initiate" method="post">
                        <input type="hidden" name="astrologer_slug" value="<?= e($astrologer['slug']) ?>">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <div class="form-group"><label for="preferred-date">Preferred date</label><input id="preferred-date" type="date" name="preferred_date" min="<?= e(date('Y-m-d')) ?>" required></div>
                        <div class="form-group"><label for="preferred-time">Preferred time</label><input id="preferred-time" type="time" name="preferred_time" required></div>
                        <div class="form-group"><label for="booking-phone">Phone</label><input id="booking-phone" type="tel" name="phone" placeholder="+91 XXXXX XXXXX" required></div>
                        <div class="form-group"><label for="booking-notes">What would you like guidance about?</label><textarea id="booking-notes" name="notes" rows="4" maxlength="2000"></textarea></div>
                        <button class="btn btn-primary btn-block" type="submit">Request appointment</button>
                    </form>
                </section>

                <?php if($profileReviewCount>0): ?><section class="ratings-panel reveal">
                    <h2>Ratings</h2>
                    <div class="ratings-panel__score"><?= e(number_format((float)$reviewSummary['average'],1)) ?></div>
                    <p><?= e((string)$profileReviewCount) ?> verified ratings</p>
                </section><?php endif; ?>

                <section class="trust-panel reveal">
                    <p>Preferred date and time requests</p>
                    <p>Admin-managed consultant profiles</p>
                    <p>Booking status in your account</p>
                </section>

                <section class="consultation-panel__contact reveal">
                    <h3 style="font-family:var(--font-serif); margin:0 0 var(--space-xs);">Contact Sri Panchami Spiritual</h3>
                    <p style="margin:0 0 var(--space-sm); color:var(--color-text-muted); font-size:0.9rem;">For ritual requests and support-assisted sessions.</p>
                </section>
            </aside>
        </div>
    <?php endif; ?>
</section>
