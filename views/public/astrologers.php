<section class="section astrologers-page" style="padding-top:var(--space-xl);">
    <div class="container astrologers-hero">
        <div style="text-align:center;">
            <span class="eyebrow">Expert Guidance · Call and Message Only</span>
            <h1 class="section-title" style="margin-bottom:var(--space-sm);">Talk to Astrologers Online</h1>
            <p class="lede">Compare live experts by status, rating, language and credit cost. Start remote chat or call sessions without appointment date slots.</p>
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
            <div class="astro-market-toolbar reveal">
                <div class="astro-wallet">
                    <span>Available Balance</span>
                    <strong>0 credits</strong>
                    <small>1 rupee adds 20 credits</small>
                </div>
                <a href="/contact?subject=astrology#contact-form" class="astro-recharge">Recharge</a>
                <div class="astro-filters" aria-label="Astrologer filters">
                    <button type="button">Filters</button>
                    <button type="button">Available Now</button>
                    <button type="button">On Chat</button>
                    <button type="button">On Call</button>
                </div>
                <label class="astro-search">
                    <span>Search Astrologer</span>
                    <input type="search" placeholder="Search Astrologer">
                </label>
            </div>
            <div class="astro-market-grid">
                <?php foreach($items as $index => $item): ?>
                    <?php
                        $states = ['online', 'busy', 'offline'];
                        $state = $states[$index % count($states)];
                        $rating = number_format(4.9 - (($index % 3) * 0.03), 2);
                        $orders = 125 + ($index * 247);
                        $languageText = implode(', ', array_slice($item['languages'] ?? ['Tamil'], 0, 2));
                        $speciality = $item['speciality'] ?? 'Vedic Astrology';
                    ?>
                    <article class="astro-market-card astro-market-card--<?= e($state) ?> reveal">
                        <a class="astro-market-photo" href="/astrologers/<?= e($item['slug'] ?? '') ?>" aria-label="View <?= e($item['name'] ?? 'Astrologer') ?>">
                            <img src="<?= e($item['photo_url'] ?? 'https://placehold.co/800x1000/fdfbf7/d4af37?text=Guru') ?>" alt="<?= e($item['name'] ?? 'Astrologer') ?>" loading="lazy">
                            <span class="astro-status-dot" aria-label="<?= e(ucfirst($state)) ?>"></span>
                            <span class="astro-rating-pill"><?= e($rating) ?> | <?= e((string)$orders) ?></span>
                        </a>
                        <div class="astro-market-info">
                            <div class="astro-market-title-row">
                                <a href="/astrologers/<?= e($item['slug'] ?? '') ?>" class="astro-market-name"><?= e($item['name'] ?? 'Astrologer') ?></a>
                                <button class="astro-follow" type="button">+ Follow</button>
                            </div>
                            <p><?= e($languageText) ?></p>
                            <p><?= e($item['experience_years'] ?? 'N/A') ?> Years</p>
                            <p><?= e($speciality) ?></p>
                        </div>
                        <div class="astro-market-price">
                            <strong>5 credits/message</strong>
                            <span>0.5 credits/sec call</span>
                            <?php if($index % 2 === 0): ?><em>Flat Deal</em><?php endif; ?>
                        </div>
                        <div class="astro-market-actions">
                            <?php if(!empty($item['slug'])): ?>
                                <?php if($state === 'online'): ?>
                                    <a href="/astrologers/<?= e($item['slug']) ?>?mode=text_session" class="astro-action astro-action--chat">CHAT</a>
                                    <a href="/astrologers/<?= e($item['slug']) ?>?mode=direct_call" class="astro-action astro-action--call">CALL</a>
                                <?php elseif($state === 'busy'): ?>
                                    <a href="/astrologers/<?= e($item['slug']) ?>?mode=text_session" class="astro-action astro-action--queue">JOIN Q</a>
                                    <a href="/astrologers/<?= e($item['slug']) ?>?mode=direct_call" class="astro-action astro-action--queue">JOIN Q</a>
                                <?php else: ?>
                                    <span class="astro-action astro-action--disabled">OFFLINE</span>
                                    <a href="/astrologers/<?= e($item['slug']) ?>" class="astro-action astro-action--call">CALL</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
