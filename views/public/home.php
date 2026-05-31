<section class="home-hero">
    <div class="hero-copy">
        <span class="eyebrow">Live Astrology · Chat and Call Consultation</span>
        <h1>Consult Astrologers Online by Chat or Call</h1>
        <p class="lede">Start private message or direct call sessions with verified astrologers. Recharge credits, view session history, and shop spiritual products when you need remedies or sacred items.</p>
        <div class="hero-actions">
            <a href="/astrologers" class="btn btn-primary">Consult Now</a>
            <a href="/shop" class="btn btn-outline">Shop Products</a>
        </div>
        <div class="hero-stats">
            <div>
                <div class="hero-stat-value"><?= e((string)count($astrologers)) ?></div>
                <div class="hero-stat-label">Online Astrologers</div>
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
    <div class="hero-deity">
        <div class="deity-frame">
            <img src="/assets/images/varahi-amman.png" 
                 alt="Sri Maha Varahi Amman — Divine deity worshipped at Sri Panchami Spiritual"
                 width="480" 
                 height="640"
                 fetchpriority="high">
            <div class="deity-glow"></div>
        </div>
    </div>
</section>

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
        <?php foreach(array_merge($astrologers, $astrologers) as $astro): ?>
            <article class="astrologer-card reveal">
                <div class="astrologer-card__media">
                    <img class="astrologer-card__photo" src="<?= e($astro['photo_url'] ?? 'https://placehold.co/800x1000/fdfbf7/d4af37?text=Guru') ?>" alt="<?= e($astro['name']) ?> — <?= e($astro['speciality'] ?? 'Vedic Astrologer') ?>" loading="lazy">
                    <div class="astrologer-card__media-badge">Live expert</div>
                </div>
                <div class="astrologer-card__body astrologer-card__body--portrait">
                    <div class="astrologer-card__title-row">
                        <h3 class="astrologer-card__name"><?= e($astro['name']) ?></h3>
                        <span class="astrologer-card__status">Verified</span>
                    </div>
                    <p class="astrologer-card__speciality"><?= e($astro['speciality'] ?? 'Vedic Astrology') ?></p>
                    <p class="astrologer-card__bio"><?= e($astro['description'] ?? '') ?></p>
                    <div class="astrologer-card__meta">
                        <span><?= e($astro['experience_years'] ?? 'N/A') ?> yrs</span>
                        <span><?= e(implode(' · ', array_slice($astro['languages'] ?? [], 0, 2))) ?></span>
                    </div>
                </div>
                <div class="astrologer-card__footer">
                    <span class="astrologer-card__price">5 credits/message · 0.5 credits/sec call</span>
                    <div class="astrologer-card__actions">
                        <a href="/astrologers/<?= e($astro['slug']) ?>" class="btn btn-sm btn-ghost">Know More</a>
                        <a href="/astrologers/<?= e($astro['slug']) ?>?mode=direct_call" class="btn btn-sm btn-call">Call</a>
                        <a href="/astrologers/<?= e($astro['slug']) ?>?mode=text_session" class="btn btn-sm btn-message">Message</a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <div style="text-align:center;">
        <a href="/astrologers" class="btn btn-primary">View Consultants</a>
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
                    <img src="<?= e($cat['image_url'] ?? 'https://placehold.co/120x120/fdfbf7/d4af37?text='.urlencode($cat['name'])) ?>" alt="Buy <?= e($cat['name']) ?> online in Chennai" loading="lazy">
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
                    <img src="<?= e($item['image_url'] ?? 'https://placehold.co/400x400/fdfbf7/8c7e6d?text='.urlencode($item['name'])) ?>" alt="<?= e($item['name']) ?> — Buy online at Sri Panchami Spiritual, Chennai" loading="lazy">
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
        <h2 class="section-title">Our Temples in Chennai</h2>
        <p class="lede">Visit our sacred spaces for divine blessings, spiritual awakening, and traditional pooja ceremonies.</p>
    </div>
    <div class="temple-scroll" aria-label="Temple highlights">
        <?php foreach(array_slice($temples, 0, 3) as $temple): ?>
            <article class="showcase-card temple-slide reveal">
                <div class="temple-slide__media">
                    <?php if(!empty($temple['image_url'])): ?>
                        <img src="<?= e($temple['image_url']) ?>" alt="<?= e($temple['name']) ?> — Temple at Sri Panchami Spiritual, Chennai" loading="lazy">
                    <?php else: ?>
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l8-4 8 4v14"/><path d="M9 21v-4a2 2 0 012-2h2a2 2 0 012 2v4"/></svg>
                    <?php endif; ?>
                </div>
                <div class="temple-slide__copy">
                    <h2><?= e($temple['name']) ?></h2>
                    <p><?= e($temple['description']) ?></p>
                    <?php if(!empty($temple['address'])): ?>
                        <p class="temple-slide__address">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <?= e($temple['address']) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <div style="text-align:center; margin-top:var(--space-xl);">
        <a href="/temples" class="btn btn-primary">View All Temples</a>
    </div>
</section>
<?php endif; ?>

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
