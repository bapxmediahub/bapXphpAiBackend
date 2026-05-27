<section class="home-hero">
    <div class="hero-copy">
        <span class="eyebrow">Blessings · Protection · Prosperity</span>
        <h1>Divine Grace.<br>Timeless Protection.</h1>
        <p class="lede">Authentic spiritual products, sacred jewelry, expert astrology and temple guidance to elevate your life.</p>
        <div class="hero-actions">
            <a href="/shop" class="btn btn-primary">Shop Spiritual Products →</a>
            <a href="/astrologers" class="btn btn-outline">Book Astrology 📅</a>
        </div>
        <div class="hero-stats">
            <div>
                <div class="hero-stat-value">500+</div>
                <div class="hero-stat-label">Happy Devotees</div>
            </div>
            <div>
                <div class="hero-stat-value">14+</div>
                <div class="hero-stat-label">Sacred Items</div>
            </div>
            <div>
                <div class="hero-stat-value">3</div>
                <div class="hero-stat-label">Expert Astrologers</div>
            </div>
        </div>
    </div>
    <div class="hero-deity">
        <img src="/assets/images/varahi-amman.png" alt="Sri Maha Varahi Amman">
    </div>
</section>

<div class="trust-bar">
    <div class="trust-item"><span>🔒</span> Secure Payments</div>
    <div class="trust-item"><span>📦</span> Fast Delivery</div>
    <div class="trust-item"><span>✅</span> Authentic Products</div>
    <div class="trust-item"><span>🙏</span> Blessed Items</div>
</div>

<section class="category-section section">
    <div class="section-header">
        <h2 class="section-title">Shop by Category</h2>
        <p class="lede">Curated collections for every spiritual need</p>
    </div>
    <div class="category-grid">
        <?php foreach($categories as $cat): ?>
            <a class="category-card" href="/category/<?= e($cat['slug']) ?>">
                <div class="category-img-wrap">
                    <img src="<?= e($cat['image_url'] ?? 'https://placehold.co/120x120/fdfbf7/d4af37?text='.urlencode($cat['name'])) ?>" alt="<?= e($cat['name']) ?>" loading="lazy">
                </div>
                <h3><?= e($cat['name']) ?></h3>
                <p><?= e($cat['description']) ?></p>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="section">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-xl); flex-wrap:wrap; gap:var(--space-sm);">
        <h2 class="section-title" style="margin:0;">Featured Products</h2>
        <a href="/shop" class="btn btn-sm btn-ghost">View All →</a>
    </div>
    <div class="product-grid">
        <?php foreach(array_slice($products, 0, min(5, count($products))) as $item): ?>
            <?php $hasOffer = !empty($item['offer_price']) && $item['offer_price'] < $item['price']; ?>
            <article class="product-card reveal">
                <div class="product-card__image">
                    <img src="<?= e($item['image_url'] ?? 'https://placehold.co/400x400/fdfbf7/8c7e6d?text='.urlencode($item['name'])) ?>" alt="<?= e($item['name']) ?>" loading="lazy">
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
                            <button class="btn btn-sm btn-primary" style="width:100%;">Add to Cart</button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="section section--alt">
    <div class="section-header">
        <span class="eyebrow">Guidance · Clarity · Remedies</span>
        <h2 class="section-title">Expert Astrology for a Better Tomorrow</h2>
        <p class="lede">Consult experienced astrologers for accurate predictions, remedy guidance tailored to your life path.</p>
    </div>
    <?php if(!empty($astrologers)): ?>
    <div class="astrologer-grid" style="margin-bottom: var(--space-xl);">
        <?php foreach(array_slice($astrologers, 0, 3) as $astro): ?>
            <article class="astrologer-card reveal">
                <div class="astrologer-card__header">
                    <img class="astrologer-card__photo" src="<?= e($astro['photo_url'] ?? 'https://placehold.co/100x100/fdfbf7/d4af37?text=👤') ?>" alt="<?= e($astro['name']) ?>" loading="lazy">
                    <div>
                        <h3 class="astrologer-card__name"><?= e($astro['name']) ?></h3>
                        <p class="astrologer-card__speciality"><?= e($astro['speciality'] ?? 'Vedic Astrology') ?></p>
                    </div>
                </div>
                <div class="astrologer-card__body">
                    <div class="astrologer-card__stat"><span class="astrologer-card__stat-label">Experience</span><span class="astrologer-card__stat-value"><?= e($astro['experience_years'] ?? 'N/A') ?> yrs</span></div>
                    <div class="astrologer-card__stat"><span class="astrologer-card__stat-label">Languages</span><span class="astrologer-card__stat-value"><?= e(implode(', ', array_slice($astro['languages'] ?? [], 0, 2))) ?></span></div>
                </div>
                <div class="astrologer-card__footer">
                    <span class="astrologer-card__price">₹<?= e((string)($astro['price'] ?? 0)) ?></span>
                    <a href="/astrologers/<?= e($astro['slug']) ?>" class="btn btn-sm btn-outline">Book Now</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div style="text-align:center;">
        <a href="/astrologers" class="btn btn-primary">Book Astrology Consultation 📅</a>
    </div>
</section>

<section class="section">
    <div class="section-header">
        <h2 class="section-title">Why Choose Us</h2>
    </div>
    <div class="feature-strip">
        <article class="panel reveal"><span class="icon">🛕</span><h3>Authentic</h3><p>Genuine spiritual products sourced with devotion</p></article>
        <article class="panel reveal"><span class="icon">⭐</span><h3>Expert Guidance</h3><p>Experienced astrologers with proven track record</p></article>
        <article class="panel reveal"><span class="icon">🔒</span><h3>Secure</h3><p>Safe payments via Razorpay with encryption</p></article>
        <article class="panel reveal"><span class="icon">📦</span><h3>Fast Delivery</h3><p>Quick and careful shipping across India</p></article>
    </div>
</section>
