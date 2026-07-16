<section class="home-hero">
    <div class="container home-hero-inner">
        <div class="hero-copy">
            <span class="eyebrow">Authentic spiritual products · Delivered across India</span>
            <h1>Discover Authentic Spiritual Products</h1>
            <p class="lede">Browse our curated collection of genuine spiritual items — from rudraksha malas and pooja kits to sacred jewellery. Every product sourced with devotion.</p>
            <div class="hero-actions">
                <a href="/shop" class="btn btn-primary">Shop Products</a>
                <a href="/consult" class="btn btn-outline">Book a Consultation</a>
            </div>
            <div class="hero-stats">
                <div>
                    <div class="hero-stat-value"><?= e((string)count($products)) ?></div>
                    <div class="hero-stat-label">Products</div>
                </div>
                <div>
                    <div class="hero-stat-value"><?= e((string)count($astrologers ?? [])) ?></div>
                    <div class="hero-stat-label">Consultants</div>
                </div>
                <div>
                    <div class="hero-stat-value">Online</div>
                    <div class="hero-stat-label">Appointments</div>
                </div>
            </div>
        </div>
        <div class="hero-deity" data-varahi-slider>
            <div class="deity-frame">
                <?php for($slide=1;$slide<=10;$slide++): ?>
                    <img class="varahi-slide <?= $slide===1?'is-active':'' ?>" src="/assets/images/hero/varahi/varahi-<?= str_pad((string)$slide,2,'0',STR_PAD_LEFT) ?>.webp" alt="Sri Maha Varahi Amman devotional image <?= $slide ?>" width="480" height="640" <?= $slide===1?'fetchpriority="high"':'loading="lazy"' ?>>
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
        Saved Addresses
    </div>
    <div class="trust-item">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        Scheduled Consultations
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
        <?php
            $cartQuantities = [];
            foreach ($_SESSION['cart'] ?? [] as $cartItem) {
                $cartQuantities[(string)($cartItem['slug'] ?? '')] = (int)($cartItem['qty'] ?? 0);
            }
            $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16));
        ?>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-xl); flex-wrap:wrap; gap:var(--space-sm);">
            <h2 class="section-title" style="margin:0;">Most Liked By People</h2>
            <a href="/shop" class="btn btn-sm btn-ghost">View Shop</a>
        </div>
        <div class="product-grid">
        <?php foreach(array_slice($products, 0, min(4, count($products))) as $item): ?>
            <?php $hasOffer = !empty($item['offer_price']) && $item['offer_price'] < $item['price']; ?>
            <article class="product-card reveal">
                <div class="product-card__image">
                    <img src="<?= e(webp_src($item['image_url'] ?? placeholder_img($item['name']))) ?>" alt="<?= e($item['name']) ?> — Buy online at Sri Panchami Spiritual, Chennai" decoding="async">
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
                            <?php $pct = round((1 - $item['offer_price'] / ($item['price'] ?: 1)) * 100); ?>
                            <span class="discount-pct">-<?= $pct ?>%</span>
                        <?php endif; ?>
                    </div>
                    <?php $itemQty = $cartQuantities[(string)($item['slug'] ?? '')] ?? 0; ?>
                    <div class="product-card__actions">
                        <a href="/product/<?= e($item['slug']) ?>" class="btn btn-sm btn-ghost">View →</a>
                        <div class="product-card__form product-card__stepper" aria-label="<?= e($item['name']) ?> cart quantity">
                            <form method="post" action="/cart/update">
                                <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
                                <input type="hidden" name="action" value="dec">
                                <input type="hidden" name="redirect" value="/">
                                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                                <button type="submit" aria-label="Remove one <?= e($item['name']) ?>" <?= $itemQty <= 0 ? 'disabled' : '' ?>>−</button>
                            </form>
                            <span class="qty-input__value"><?= e((string)$itemQty) ?></span>
                            <form method="post" action="/cart/add">
                                <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
                                <input type="hidden" name="qty" value="1">
                                <input type="hidden" name="redirect" value="/">
                                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                                <button type="submit" aria-label="Add one <?= e($item['name']) ?>">+</button>
                            </form>
                        </div>
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
        <h2 class="section-title">Online Consultation</h2>
        <p class="lede">Choose an experienced consultant and request a private appointment at your preferred date and time.</p>
    </div>
    <?php if(!empty($astrologers)): ?>
    <div class="astro-carousel" aria-label="Consultants carousel">
        <div class="astro-carousel-track">
        <?php foreach(array_values(array_merge($astrologers, $astrologers)) as $astro): ?>
            <?php
                $languageText = implode(', ', array_slice(array_values(array_filter($astro['languages'] ?? [])), 0, 2));
                $experience = trim((string)($astro['experience_years'] ?? ''));
                $speciality = $astro['speciality'] ?? 'Vedic Astrology';
            ?>
            <article class="astro-market-card reveal">
                <a class="astro-market-photo" href="/consult/<?= e($astro['slug'] ?? '') ?>" aria-label="View <?= e($astro['name'] ?? 'Astrologer') ?>">
                    <span class="astro-market-photo-frame"><img class="astro-market-photo-img astro-market-photo-img--<?= e($astro['slug'] ?? 'default') ?>" src="<?= e(webp_src($astro['photo_url'] ?? placeholder_img($astro['name'] ?? 'Astrologer'))) ?>" alt="<?= e($astro['name'] ?? 'Astrologer') ?>" loading="lazy"></span>
                </a>
                <div class="astro-market-info">
                    <a href="/consult/<?= e($astro['slug'] ?? '') ?>" class="astro-market-name"><?= e($astro['name'] ?? 'Astrologer') ?></a>
                    <p class="astro-market-speciality"><?= e($speciality) ?></p>
                    <?php if($languageText !== '' || $experience !== ''): ?><div class="astro-market-meta"><?php if($languageText !== ''): ?><span><?= e($languageText) ?></span><?php endif; ?><?php if($experience !== ''): ?><span><?= e($experience) ?> years</span><?php endif; ?></div><?php endif; ?>
                </div>
                <div class="astro-market-actions">
                    <div class="astro-action-row">
                        <a href="/consult/<?= e($astro['slug'] ?? '') ?>" class="astro-action">View profile</a>
                        <a href="/consult/<?= e($astro['slug'] ?? '') ?>#booking-form" class="astro-action astro-action--primary">Book appointment</a>
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

<section class="section section--alt">
    <div class="container">
        <div class="section-header">
            <span class="eyebrow serif-accent">Simple · Secure · Trackable</span>
            <h2 class="section-title">How Your Order Works</h2>
            <p class="lede">Choose an authentic product, pay securely online, and follow the order from your account.</p>
        </div>
        <div class="feature-strip home-order-steps">
            <article><span class="home-order-step">1</span><h3>Choose Products</h3><p>Browse clear product details and add the quantity you need.</p></article>
            <article><span class="home-order-step">2</span><h3>Select an Address</h3><p>Reuse a saved address or enter a different delivery address at checkout.</p></article>
            <article><span class="home-order-step">3</span><h3>Pay and Track</h3><p>Complete Razorpay payment and follow confirmation from My Orders.</p></article>
        </div>
        <div class="home-order-actions">
            <a class="btn btn-primary" href="/shop">Browse Products</a>
            <a class="btn btn-ghost" href="/blog/category/help">Ordering Help</a>
        </div>
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
                        <img src="<?= e(webp_src($temple['image_url'])) ?>" alt="<?= e($temple['name']) ?> — Temple guide at Sri Panchami Spiritual, Chennai" decoding="async">
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
                "text": "Yes, you can request a scheduled appointment with an expert consultant in Tamil, English, and other Indian languages. Services include kundli matching, horoscope reading, career guidance, and personalized remedies."
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
