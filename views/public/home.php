<section class="home-hero">
    <div class="hero-copy">
        <span class="eyebrow">Blessings · Protection · Prosperity</span>
        <h1>Divine Grace.<br>Timeless Protection.</h1>
        <p class="lede">Authentic spiritual products, sacred jewelry, expert astrology and temple guidance to elevate your life.</p>
        <div class="hero-actions">
            <a href="/shop" class="btn btn-primary">SHOP SPIRITUAL PRODUCTS <span>&rarr;</span></a>
            <a href="/astrologers" class="btn btn-outline">BOOK ASTROLOGY <span>📅</span></a>
        </div>
    </div>
    <div class="hero-deity">
        <img src="/assets/images/varahi-amman.png" alt="Sri Maha Varahi Amman">
    </div>
</section>

<section class="category-section">
    <h2 class="section-title">SHOP BY CATEGORY</h2>
    <div class="category-grid">
        <?php foreach($categories as $cat): ?>
            <div class="category-card" onclick="window.location.href='/category/<?= e($cat['slug']) ?>'">
                <div class="category-img-wrap">
                    <img src="<?= e($cat['image_url'] ?? 'https://placehold.co/120x120?text='.urlencode($cat['name'])) ?>" alt="<?= e($cat['name']) ?>">
                </div>
                <h3><?= e($cat['name']) ?></h3>
                <p><?= e($cat['description']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="section">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
        <h2 class="section-title" style="margin:0;">FEATURED PRODUCTS</h2>
        <a href="/shop" style="font-weight:600; font-size:0.9rem; color:var(--color-maroon);">VIEW ALL PRODUCTS &rarr;</a>
    </div>
    <div class="product-grid">
        <?php foreach(array_slice($products, 0, 5) as $item): ?>
            <article class="product-card">
                <img src="<?= e($item['image_url'] ?? 'https://placehold.co/300x300?text='.urlencode($item['name'])) ?>" alt="<?= e($item['name']) ?>">
                <h3><?= e($item['name']) ?></h3>
                <p style="font-size:0.85rem; color:var(--color-text-muted); margin-bottom:1rem;"><?= e($item['description']) ?></p>
                <div class="price-row" style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1rem;">
                    <span class="price">₹<?= e((string)($item['offer_price'] ?: $item['price'] ?: 0)) ?></span>
                    <?php if(!empty($item['offer_price']) && $item['offer_price'] < $item['price']): ?>
                        <span class="old-price">₹<?= e($item['price']) ?></span>
                    <?php endif; ?>
                </div>
                <a href="/product/<?= e($item['slug']) ?>" class="btn btn-primary" style="width:100%; justify-content:center;">ADD TO CART</a>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="section" style="background:var(--color-cream); padding:4rem 5vw; border-radius:30px; margin-top:4rem;">
    <div style="text-align:center; margin-bottom:3rem;">
        <span class="eyebrow" style="color:var(--color-maroon)">GUIDANCE · CLARITY · REMEDIES</span>
        <h2 class="section-title">Expert Astrology<br>for a Better Tomorrow</h2>
        <p class="lede" style="color:var(--color-text-muted); max-width:600px; margin: 1rem auto 2rem;">Consult experienced astrologers for accurate predictions, remedy guidance tailored to your life path.</p>
        <a href="/astrologers" class="btn btn-primary">BOOK ASTROLOGY CONSULTATION <span>📅</span></a>
    </div>
    <div class="grid" style="grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1.5rem;">
        <div class="panel" style="text-align:center;">
            <div style="font-size:2rem; margin-bottom:0.5rem;">📜</div>
            <h3>Janma Kundli</h3>
            <p style="font-size:0.85rem; color:var(--color-text-muted);">Detailed Analysis</p>
        </div>
        <div class="panel" style="text-align:center;">
            <div style="font-size:2rem; margin-bottom:0.5rem;">📅</div>
            <h3>Panchanga</h3>
            <p style="font-size:0.85rem; color:var(--color-text-muted);">Timely Guidance</p>
        </div>
        <div class="panel" style="text-align:center;">
            <div style="font-size:2rem; margin-bottom:0.5rem;">❤️</div>
            <h3>Compatibility</h3>
            <p style="font-size:0.85rem; color:var(--color-text-muted);">Match Making</p>
        </div>
        <div class="panel" style="text-align:center;">
            <div style="font-size:2rem; margin-bottom:0.5rem;">🌿</div>
            <h3>Remedies</h3>
            <p style="font-size:0.85rem; color:var(--color-text-muted);">Effective Solutions</p>
        </div>
    </div>
</section>
