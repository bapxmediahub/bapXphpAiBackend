<section class="home-hero">
    <div class="hero-copy">
        <span class="eyebrow">Live Astrology · Chat and Call Consultation</span>
        <h1>Consult Astrologers Online by Chat or Call</h1>
        <p class="lede">Start private message or direct call sessions with verified astrologers. Recharge credits, view session history, and shop spiritual products when you need remedies or sacred items.</p>
        <div class="hero-actions">
            <a href="/consult" class="btn btn-primary">Consult Now</a>
            <a href="/shop" class="btn btn-outline">Shop Products</a>
        </div>
        <div class="hero-stats">
            <div>
                <div class="hero-stat-value"><?= e((string)count($astrologers)) ?></div>
                <div class="hero-stat-label">Client Astrologers</div>
            </div>
            <div>
                <div class="hero-stat-value">Chat + Call</div>
                <div class="hero-stat-label">Remote Sessions</div>
            </div>
            <div>
                <div class="hero-stat-value">Credits</div>
                <div class="hero-stat-label">Wallet Based</div>
            </div>
        </div>
    </div>
    <div class="hero-deity" data-varahi-slider>
        <div class="deity-frame">
            <?php for($slide=1;$slide<=10;$slide++): ?>
                <img class="varahi-slide <?= $slide===1?'is-active':'' ?>" src="/assets/images/hero/varahi/varahi-<?= str_pad((string)$slide,2,'0',STR_PAD_LEFT) ?>.jpg" alt="Sri Maha Varahi Amman devotional image <?= $slide ?>" width="480" height="640" <?= $slide===1?'fetchpriority="high"':'loading="lazy"' ?>>
            <?php endfor; ?>
            <div class="hero-slider-controls"><button type="button" data-slider-prev aria-label="Previous image">&#8249;</button><span data-slider-count>1 / 10</span><button type="button" data-slider-next aria-label="Next image">&#8250;</button></div>
        </div>
    </div>
</section>
<script>
(() => { const root=document.querySelector('[data-varahi-slider]'); if(!root)return; const slides=[...root.querySelectorAll('.varahi-slide')],count=root.querySelector('[data-slider-count]'); let index=0,timer; const show=n=>{slides[index].classList.remove('is-active');index=(n+slides.length)%slides.length;slides[index].classList.add('is-active');count.textContent=(index+1)+' / '+slides.length}; const play=()=>{if(matchMedia('(prefers-reduced-motion: reduce)').matches)return;clearInterval(timer);timer=setInterval(()=>show(index+1),5000)};root.querySelector('[data-slider-prev]').onclick=()=>{show(index-1);play()};root.querySelector('[data-slider-next]').onclick=()=>{show(index+1);play()};root.addEventListener('mouseenter',()=>clearInterval(timer));root.addEventListener('mouseleave',play);play(); })();
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

<section class="section">
    <div class="section-header">
        <span class="eyebrow">Guidance · Clarity · Remedies</span>
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
                    <img src="<?= e($astro['photo_url'] ?? 'https://placehold.co/800x1000/fdfbf7/d4af37?text=Guru') ?>" alt="<?= e($astro['name'] ?? 'Astrologer') ?>" loading="lazy">
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
                    <a href="/consult/<?= e($astro['slug'] ?? '') ?>" class="astro-action astro-action--session">View Profile</a>
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

<section class="category-section section">
    <div class="section-header">
        <h2 class="section-title">Shop by Category</h2>
        <p class="lede">Curated collections of authentic spiritual products for every need — from rudraksha malas to complete pooja kits</p>
    </div>
    <div class="category-grid">
        <?php foreach($categories as $cat): ?>
            <a class="category-card" href="/shop?category=<?= e($cat['slug']) ?>">
                <div class="category-img-wrap">
                    <img src="<?= e($cat['image_url'] ?? 'https://placehold.co/120x120/fdfbf7/d4af37?text='.urlencode($cat['name'])) ?>" alt="Buy <?= e($cat['name']) ?> online in Chennai" decoding="async">
                </div>
                <h3><?= e($cat['name']) ?></h3>
                <p><?= e($cat['description']) ?></p>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="section">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-xl); flex-wrap:wrap; gap:var(--space-sm);">
        <h2 class="section-title" style="margin:0;">Featured Spiritual Products</h2>
        <a href="/shop" class="btn btn-sm btn-ghost">View All Products</a>
    </div>
    <div class="product-grid">
        <?php foreach(array_slice($products, 0, min(5, count($products))) as $item): ?>
            <?php $hasOffer = !empty($item['offer_price']) && $item['offer_price'] < $item['price']; ?>
            <article class="product-card reveal">
                <div class="product-card__image">
                    <img src="<?= e($item['image_url'] ?? 'https://placehold.co/400x400/fdfbf7/8c7e6d?text='.urlencode($item['name'])) ?>" alt="<?= e($item['name']) ?> — Buy online at Sri Panchami Spiritual, Chennai" decoding="async">
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
                         <a href="/product/<?= e($item['slug']) ?>" class="btn btn-sm btn-ghost">View</a>
                         <form method="post" action="/cart/add" style="flex:1;">
                             <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
                             <input type="hidden" name="qty" value="1">
                             <input type="hidden" name="redirect" value="/">
                             <button class="btn btn-sm btn-primary" style="width:100%;">Add to Cart</button>
                         </form>
                     </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php if(!empty($temples)): ?>
<section class="section section--alt">
    <div class="section-header">
        <span class="eyebrow">Sacred Spaces · Divine Energy</span>
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

<section class="section section--alt">
    <div class="section-header">
        <h2 class="section-title">Why Choose Sri Panchami Spiritual</h2>
        <p class="lede">Chennai's trusted destination for authentic spiritual products and expert astrology guidance</p>
    </div>
    <div class="feature-strip">
        <article class="panel reveal">
            <h3>100% Authentic Products</h3>
            <p>Every item sourced with devotion and verified for genuineness</p>
        </article>
        <article class="panel reveal">
            <h3>Expert Astrologers</h3>
            <p>Experienced Vedic astrologers with proven track record</p>
        </article>
        <article class="panel reveal">
            <h3>Secure Payments</h3>
            <p>Safe payments via Razorpay with bank-grade encryption</p>
        </article>
        <article class="panel reveal">
            <h3>Free Shipping</h3>
            <p>Quick and careful delivery across India</p>
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
                "text": "Sri Panchami Spiritual offers certified original rudraksha beads and malas online with free shipping across India. Visit our shop at 23, 1st Cross Street Kothari Nagar, Ramapuram, Chennai or order online."
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
