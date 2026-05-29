<section class="booking-layout">
    <?php if(!$astrologer): ?>
        <div style="text-align:center; padding:var(--space-4xl) 0;">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto var(--space-md);"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <h1 style="font-family:var(--font-serif); margin:0 0 var(--space-sm);">Astrologer Not Found</h1>
            <p style="color:var(--color-text-muted); margin-bottom:var(--space-lg);">The astrologer profile you're looking for doesn't exist.</p>
            <a href="/astrologers" class="btn btn-primary">View All Astrologers</a>
        </div>
    <?php else: ?>
        <div class="booking-profile reveal">
            <img class="booking-profile__photo" src="<?= e($astrologer['photo_url'] ?? 'https://placehold.co/150x150/fdfbf7/d4af37?text=Guru') ?>" alt="<?= e($astrologer['name']) ?>">
            <div>
                <h1 class="booking-profile__name"><?= e($astrologer['name']) ?></h1>
                <p class="booking-profile__meta"><?= e($astrologer['speciality'] ?? 'Vedic Astrology') ?> · <?= e($astrologer['experience_years'] ?? 'N/A') ?> years experience</p>
                <p class="booking-profile__meta">Languages: <?= e(implode(', ', $astrologer['languages'] ?? [])) ?></p>
                <p class="booking-profile__meta">Modes: <?= e(implode(', ', array_map(fn($mode) => ucwords(str_replace(['-', '_'], ' ', $mode)), $astrologer['modes'] ?? []))) ?></p>
                <p style="margin-top:var(--space-sm); display:flex; flex-wrap:wrap; gap:var(--space-sm);">
                    <span style="font-size:1.05rem; font-weight:700; color:var(--color-maroon);">₹<?= e((string)($astrologer['text_session_prm'] ?? 15)) ?> PRM <span style="color:var(--color-text-muted); font-size:0.8rem; font-weight:400;">text</span></span>
                    <span style="font-size:1.05rem; font-weight:700; color:var(--color-maroon);">₹<?= e((string)($astrologer['call_session_prm'] ?? 15)) ?> PRM <span style="color:var(--color-text-muted); font-size:0.8rem; font-weight:400;">call</span></span>
                </p>
            </div>
        </div>

        <div class="slot-picker reveal">
            <h3>Select a Date</h3>
            <form method="get" class="slot-picker__form">
                <div class="form-group">
                    <label>Appointment Date</label>
                    <input type="date" name="date" value="<?= e($date) ?>" min="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
                </div>
            </form>

            <h3 style="margin-top:var(--space-lg);">Available Slots for <?= date('M j, Y', strtotime($date)) ?></h3>
            <?php if(empty($slots)): ?>
                <div style="text-align:center; padding:var(--space-2xl);">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto var(--space-sm);"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <p style="color:var(--color-text-muted); margin:0;">No slots available for this date. Please select another date.</p>
                </div>
            <?php else: ?>
                <div class="slot-grid">
                    <?php foreach($slots as $slot): ?>
                        <div class="slot-card">
                            <div class="slot-card__time"><?= e($slot) ?></div>
                            <form method="post" action="/appointments/book">
                                <input type="hidden" name="astrologer_slug" value="<?= e($astrologer['slug']) ?>">
                                <input type="hidden" name="date" value="<?= e($date) ?>">
                                <input type="hidden" name="time" value="<?= e($slot) ?>">
                                <div class="form-group">
                                    <label>Name</label>
                                    <input type="text" name="customer_name" value="<?= e($_SESSION['user']['name'] ?? '') ?>" required placeholder="Your name">
                                </div>
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="customer_email" value="<?= e($_SESSION['user']['email'] ?? '') ?>" required placeholder="your@email.com">
                                </div>
                                <div class="form-group">
                                    <label>Mode</label>
                                    <select name="mode">
                                        <?php foreach($astrologer['modes'] ?? ['text_session', 'direct_call'] as $mode): ?>
                                            <option value="<?= e($mode) ?>"><?= e(ucwords(str_replace(['-', '_'], ' ', $mode))) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button class="btn btn-sm btn-primary">Book <?= e($slot) ?></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
