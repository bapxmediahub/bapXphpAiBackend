<section class="home-hero">
    <div class="container home-hero-inner">
        <div class="hero-copy">
            <span class="eyebrow">Sacred Emblems · Spiritual Jewelry · Pooja Idols</span>
            <h1>Authentic Spiritual Products for Your Sacred Journey</h1>
            <p class="lede">Discover sacred rudraksha, pooja items, spiritual jewellery, and temple idols. Free shipping across India on every order.</p>
            <div class="hero-actions">
                <a href="/shop" class="btn btn-primary">Shop Now</a>
                <a href="/consult" class="btn btn-outline">Consult Astrologers</a>
            </div>
            <div class="hero-stats">
                <div>
                    <div class="hero-stat-value"><?= e((string)count($products)) ?></div>
                    <div class="hero-stat-label">Products</div>
                </div>
                <div>
                    <div class="hero-stat-value"><?= e((string)count($categories)) ?></div>
                    <div class="hero-stat-label">Categories</div>
                </div>
                <div>
                    <div class="hero-stat-value">Free</div>
                    <div class="hero-stat-label">Shipping</div>
                </div>
            </div>
        </div>
        <div class="hero-deity" data-varahi-slider>
            <div class="deity-frame">
                <?php for($slide=1;$slide<=10;$slide++): ?>
                    <img class="varahi-slide <?= $slide===1?'is-active':'' ?>" src="/assets/images/hero/varahi/varahi-<?= str_pad((string)$slide,2,'0',STR_PAD_LEFT) ?>.png" alt="Sri Maha Varahi Amman devotional image <?= $slide ?>" width="480" height="640" <?= $slide===1?'fetchpriority="high"':'loading="lazy"' ?>>
                <?php endfor; ?>
            </div>
            <div class="varahi-dots" role="tablist" aria-label="Varahi slides">
                <?php for($dot=1;$dot<=10;$dot++): ?>
                    <button class="varahi-dot <?= $dot===1?'is-active':'' ?>" type="button" role="tab" aria-label="Slide <?= $dot ?>" <?= $dot===1?'aria-current="true"':'aria-current="false"' ?> data-slide="<?= $dot-1 ?>"></button>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</section>
<script>
(() => {
    const root = document.querySelector('[data-varahi-slider]');
    if (!root) return;
    const slides = [...root.querySelectorAll('.varahi-slide')];
    const dots = [...root.querySelectorAll('.varahi-dot')];
    let index = 0, timer;
    const show = n => {
        slides[index].classList.remove('is-active');
        dots[index].classList.remove('is-active');
        dots[index].setAttribute('aria-current', 'false');
        index = (n + slides.length) % slides.length;
        slides[index].classList.add('is-active');
        dots[index].classList.add('is-active');
        dots[index].setAttribute('aria-current', 'true');
    };
    const play = () => {
        if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        clearInterval(timer);
        timer = setInterval(() => show(index + 1), 5000);
    };
    dots.forEach(d => {
        d.addEventListener('click', () => { show(parseInt(d.dataset.slide)); play(); });
    });
    root.addEventListener('mouseenter', () => clearInterval(timer));
    root.addEventListener('mouseleave', play);
    play();
})();
</script>

<div class="trust-bar">
    <div class="trust-item">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        Secure Payments
    </div>
    <div class="trust-item">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
        Wallet Credits
    </div>
    <div class="trust-item">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        Call & Message
    </div>
    <div class="trust-item">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
        Spiritual Products
    </div>
</div>

<section class="category-section section">
    <div class="section-header">
        <h2 class="section-title">Shop by Category</h2>
        <p class="lede">Curated collections of authentic spiritual products for every need — from rudraksha malas to complete pooja kits</p>
    </div>
    <div class="category-grid">
        <?php foreach($categories as $cat): ?>
            <a class="category-card" href="/shop?category=<?= e($cat['slug']) ?>">
                <div class="category-img-wrap">
                    <img src="<?= e($cat['image_url'] ?? placeholder_img($cat['name'])) ?>" alt="Buy <?= e($cat['name']) ?> online in Chennai" decoding="async">
                </div>
                <h3><?= e($cat['name']) ?></h3>
                <p><?= e($cat['description']) ?></p>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="section">
    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-xl); flex-wrap:wrap; gap:var(--space-sm);">
            <h2 class="section-title" style="margin:0;">Most Liked By People</h2>
            <a href="/shop" class="btn btn-sm btn-ghost">View Shop</a>
        </div>
        <div class="product-grid">
        <?php foreach(array_slice($products, 0, min(4, count($products))) as $item): ?>
            <?php $hasOffer = !empty($item['offer_price']) && $item['offer_price'] < $item['price']; ?>
            <article class="product-card reveal">
                <div class="product-card__image">
                    <img src="<?= e($item['image_url'] ?? placeholder_img($item['name'])) ?>" alt="<?= e($item['name']) ?> — Buy online at Sri Panchami Spiritual, Chennai" decoding="async">
                    <?php if($hasOffer): ?>
                        <span class="product-card__badge product-card__badge--sale">Sale</span>
                    <?php endif; ?>
                </div>
                <div class="product-card__body">
                    <h3><?= e($item['name']) ?></h3>
                    <p class="product-card__desc"><?= e($item['description']) ?></p>
                    <div class="product-card__price-row">
                        <span class="price">₹<?= e((string)($item['offer_price'] ?: $item['price'] ?: 0)) ?></span>
                        <?php if($hasOffer): ?>
                            <span class="old-price">₹<?= e($item['price']) ?></span>
                            <?php $pct = round((1 - $item['offer_price'] / $item['price']) * 100); ?>
                            <span class="discount-pct">-<?= $pct ?>%</span>
                        <?php endif; ?>
                    </div>
                     <div class="product-card__actions">
                         <a href="/product/<?= e($item['slug']) ?>" class="btn btn-sm btn-ghost">View →</a>
                         <form method="post" action="/cart/add" class="product-card__form">
                             <div class="qty-input qty-input--sm">
                                 <button type="button" onclick="var i=this.parentElement.querySelector('input[type=number]'); i.stepDown(); i.dispatchEvent(new Event('change'));">−</button>
                                 <input type="number" name="qty" value="1" min="1" max="99" required>
                                 <button type="button" onclick="var i=this.parentElement.querySelector('input[type=number]'); i.stepUp(); i.dispatchEvent(new Event('change'));">+</button>
                             </div>
                             <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
                             <input type="hidden" name="redirect" value="/">
                             <button class="btn-cart-circle" aria-label="Add to Cart">
                                 <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                             </button>
                         </form>
                     </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    </div>
</section>

<section class="section section--full">
    <div class="section-header">
        <span class="eyebrow serif-accent">Guidance · Clarity · Remedies</span>
        <h2 class="section-title">Online Astrology Consultation</h2>
        <p class="lede">Consult experienced Vedic astrologers by private message or direct call for kundli matching, horoscope reading, career guidance, and personalized remedies.</p>
    </div>
    <?php if(!empty($astrologers)): ?>
    <div class="astro-carousel" aria-label="Astrologers carousel">
        <div class="astro-carousel-track">
        <?php foreach(array_values(array_merge($astrologers, $astrologers)) as $astro): ?>
            <?php
                $availability = $astro['availability_status'] ?? 'offline';
                $state = $availability === 'available' ? 'online' : (in_array($availability, ['busy', 'waitlist'], true) ? 'busy' : 'offline');
                $statusLabel = $state === 'online' ? 'Available' : ($state === 'busy' ? 'Waitlist' : 'Offline');
                $languageText = implode(', ', array_slice(array_values(array_filter($astro['languages'] ?? [])), 0, 2));
                $experience = trim((string)($astro['experience_years'] ?? ''));
                $speciality = $astro['speciality'] ?? 'Vedic Astrology';
            ?>
            <article class="astro-market-card astro-market-card--<?= e($state) ?> reveal">
                <a class="astro-market-photo" href="/consult/<?= e($astro['slug'] ?? '') ?>" aria-label="View <?= e($astro['name'] ?? 'Astrologer') ?>">
                    <span class="astro-market-photo-frame"><img class="astro-market-photo-img astro-market-photo-img--<?= e($astro['slug'] ?? 'default') ?>" src="<?= e($astro['photo_url'] ?? placeholder_img($astro['name'] ?? 'Astrologer')) ?>" alt="<?= e($astro['name'] ?? 'Astrologer') ?>" loading="lazy"></span>
                    <span class="astro-status-dot" aria-label="<?= e(ucfirst($state)) ?>"></span>
                    <span class="astro-status-label"><?= e($statusLabel) ?></span>
                </a>
                <div class="astro-market-info">
                    <a href="/consult/<?= e($astro['slug'] ?? '') ?>" class="astro-market-name"><?= e($astro['name'] ?? 'Astrologer') ?></a>
                    <p class="astro-market-speciality"><?= e($speciality) ?></p>
                    <?php if($languageText !== '' || $experience !== ''): ?><div class="astro-market-meta"><?php if($languageText !== ''): ?><span><?= e($languageText) ?></span><?php endif; ?><?php if($experience !== ''): ?><span><?= e($experience) ?> years</span><?php endif; ?></div><?php endif; ?>
                </div>
                <div class="astro-market-price">
                    <strong><?= e((string)($astro['message_credit_cost'] ?? 5)) ?> credits/message</strong>
                    <span><?= e((string)($astro['call_credit_per_second'] ?? 0.5)) ?> credits/sec call</span>
                </div>
                <div class="astro-market-actions">
                    <div class="astro-action-row">
                        <?php if($state === 'online'): ?>
                            <form class="astro-session-form" action="/appointments/book" method="post">
                                <input type="hidden" name="astrologer_slug" value="<?= e($astro['slug'] ?? '') ?>">
                                <input type="hidden" name="mode" value="text_session">
                                <button type="submit" class="astro-action astro-action--icon astro-action--chat" aria-label="Start message session" title="Message">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.5 9.2 9.2 0 0 1-3.7-.8L3 21l1.8-5.3A8.2 8.2 0 0 1 4 11.5 8.4 8.4 0 0 1 12.5 3 8.4 8.4 0 0 1 21 11.5Z"/><path d="M8 10h8M8 14h5"/></svg>
                                    <span class="sr-only">Message</span>
                                </button>
                            </form>
                            <form class="astro-session-form" action="/appointments/book" method="post">
                                <input type="hidden" name="astrologer_slug" value="<?= e($astro['slug'] ?? '') ?>">
                                <input type="hidden" name="mode" value="direct_call">
                                <button type="submit" class="astro-action astro-action--icon astro-action--call" aria-label="Start call session" title="Call">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.7 19.7 0 0 1-8.6-3.1 19.1 19.1 0 0 1-5.9-5.9A19.7 19.7 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.4 2.1L8.1 10a16 16 0 0 0 5.9 5.9l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2Z"/></svg>
                                    <span class="sr-only">Call</span>
                                </button>
                            </form>
                        <?php elseif($state === 'busy'): ?>
                            <form class="astro-session-form" action="/appointments/book" method="post">
                                <input type="hidden" name="astrologer_slug" value="<?= e($astro['slug'] ?? '') ?>">
                                <input type="hidden" name="mode" value="text_session">
                                <input type="hidden" name="queue_status" value="waitlist">
                                <button type="submit" class="astro-action astro-action--icon astro-action--chat" aria-label="Join message waitlist" title="Join message waitlist">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.5 9.2 9.2 0 0 1-3.7-.8L3 21l1.8-5.3A8.2 8.2 0 0 1 4 11.5 8.4 8.4 0 0 1 12.5 3 8.4 8.4 0 0 1 21 11.5Z"/><path d="M8 10h8M8 14h5"/></svg>
                                    <span class="sr-only">Join message waitlist</span>
                                </button>
                            </form>
                            <span class="astro-action astro-action--icon astro-action--disabled" aria-disabled="true" title="Call unavailable">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.7 19.7 0 0 1-8.6-3.1 19.1 19.1 0 0 1-5.9-5.9A19.7 19.7 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.4 2.1L8.1 10a16 16 0 0 0 5.9 5.9l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2Z"/></svg>
                                <span class="sr-only">Call unavailable</span>
                            </span>
                        <?php else: ?>
                            <span class="astro-action astro-action--icon astro-action--disabled" aria-disabled="true" title="Message unavailable"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.5 9.2 9.2 0 0 1-3.7-.8L3 21l1.8-5.3A8.2 8.2 0 0 1 4 11.5 8.4 8.4 0 0 1 12.5 3 8.4 8.4 0 0 1 21 11.5Z"/><path d="M8 10h8M8 14h5"/></svg><span class="sr-only">Message unavailable</span></span>
                            <span class="astro-action astro-action--icon astro-action--disabled" aria-disabled="true" title="Call unavailable"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.7 19.7 0 0 1-8.6-3.1 19.1 19.1 0 0 1-5.9-5.9A19.7 19.7 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.4 2.1L8.1 10a16 16 0 0 0 5.9 5.9l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2Z"/></svg><span class="sr-only">Call unavailable</span></span>
                        <?php endif; ?>
                        <a href="/consult/<?= e($astro['slug'] ?? '') ?>" class="astro-action astro-action--icon astro-action--profile" aria-label="View Profile" title="View Profile">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                            <span class="sr-only">View Profile</span>
                        </a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <div style="text-align:center;">
        <a href="/consult" class="btn btn-primary">View Consultants</a>
    </div>
</section>

<?php if(!empty($temples)): ?>
<section class="section section--alt">
    <div class="section-header">
        <span class="eyebrow serif-accent">Sacred Spaces · Divine Energy</span>
        <h2 class="section-title">Panchami Temples Guide</h2>
        <p class="lede">Explore temple guides for divine blessings, traditional pooja details, and spiritual routes around Chennai. <a href="/temples">Click here</a></p>
    </div>
    <div class="temple-carousel temple-carousel--single" data-temple-slider aria-label="Temple guide carousel">
        <div class="temple-carousel-track">
        <?php foreach(array_values($temples) as $index => $temple): ?>
            <a class="showcase-card temple-feature-card reveal <?= $index === 0 ? 'is-active' : '' ?>" href="/temples/<?= e($temple['slug'] ?? '') ?>" aria-label="View <?= e($temple['name'] ?? 'Temple') ?>">
                <div class="temple-feature-card__media">
                    <?php if(!empty($temple['image_url'])): ?>
                        <img src="<?= e($temple['image_url']) ?>" alt="<?= e($temple['name']) ?> — Temple guide at Sri Panchami Spiritual, Chennai" decoding="async">
                    <?php else: ?>
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l8-4 8 4v14"/><path d="M9 21v-4a2 2 0 012-2h2a2 2 0 012 2v4"/></svg>
                    <?php endif; ?>
                </div>
                <div class="temple-feature-card__body">
                    <h2><?= e($temple['name']) ?></h2>
                    <p><?= e($temple['description']) ?></p>
                    <?php if(!empty($temple['address'])): ?>
                        <p class="temple-feature-card__meta">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <?= e($temple['address']) ?>
                        </p>
                    <?php endif; ?>
                    <?php if(!empty($temple['timings'])): ?>
                        <p class="temple-feature-card__meta">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <?= e($temple['timings']) ?>
                        </p>
                    <?php endif; ?>
                    <span class="btn btn-sm btn-primary temple-feature-card__cta">View Details</span>
                </div>
            </a>
        <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var slider = document.querySelector('[data-temple-slider]');
    if (!slider) return;
    var slides = Array.prototype.slice.call(slider.querySelectorAll('.temple-feature-card'));
    if (slides.length < 2) return;
    var index = 0;
    setInterval(function () {
        slides[index].classList.remove('is-active');
        index = (index + 1) % slides.length;
        slides[index].classList.add('is-active');
    }, 6500);
});
</script>

<section class="section section--warm">
    <div class="container">
        <div class="section-header">
            <span class="eyebrow serif-accent">Why Sri Panchami Spiritual</span>
            <h2 class="section-title">Faith · Trust · Tradition</h2>
            <p class="lede">Rooted in devotion, committed to authenticity — every product and service reflects our reverence for India's spiritual heritage.</p>
        </div>
        <div class="value-strip">
            <article class="value-card reveal">
            <div class="value-card__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
            </div>
            <h3>Authenticity</h3>
            <p>Every item sourced with devotion — authentic rudraksha, pure pooja essentials, and sacred jewellery verified for spiritual genuineness.</p>
        </article>
        <article class="value-card reveal">
            <div class="value-card__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <h3>Spiritual Growth</h3>
            <p>Our products are more than offerings — they are symbols of faith that help keep alive the divine traditions connecting every devotee with spirituality.</p>
        </article>
        <article class="value-card reveal">
            <div class="value-card__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <h3>Devotion</h3>
            <p>Crafted with reverence, our products support sacred rituals and deepen your connection with the divine through every offering.</p>
        </article>
        <article class="value-card reveal">
            <div class="value-card__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            </div>
            <h3>Community</h3>
            <p>Fostering belonging and connection through shared spiritual experiences — bringing temples, traditions, and devotees closer together.</p>
        </article>
    </div>
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

<!-- FAQ Schema for SEO -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "FAQPage",
    "mainEntity": [
        {
            "@type": "Question",
            "name": "Where can I buy original rudraksha online in Chennai?",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "Sri Panchami Spiritual offers certified original rudraksha beads and malas online with free shipping across India. Order online through our web store."
            }
        },
        {
            "@type": "Question",
            "name": "Do you offer Vedic astrology consultation in Chennai?",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "Yes, we have 13 expert Vedic astrologers offering private text sessions and direct call sessions in Tamil, English, and other Indian languages. Services include kundli matching, horoscope reading, career guidance, and personalized remedies."
            }
        },
        {
            "@type": "Question",
            "name": "What pooja items do you sell online?",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "We sell a complete range of pooja samagri including brass items, dhoop sticks, agarbatti, camphor, kumkum, havan samagri, pooja thalis, and complete pooja kits for all occasions."
            }
        },
        {
            "@type": "Question",
            "name": "Is free shipping available on spiritual products?",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "Yes, we offer free shipping on all spiritual products across India. Orders are carefully packed and delivered to your doorstep."
            }
        }
    ]
}
</script>
