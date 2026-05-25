<section class="home-hero">
    <div class="hero-copy">
        <p class="eyebrow">Premium devotion, guided with care</p>
        <h1>Sacred essentials for a beautiful daily pooja ritual</h1>
        <p class="lede">Shop refined devotional products, book astrology guidance, and discover Sri Maha Varahi Amman temple wisdom from one calm, trusted experience.</p>
        <div class="actions"><a href="/shop">Explore the shop</a><a href="/astrologers">Book astrology session</a></div>
        <div class="hero-notes"><span>Editable product catalog</span><span>Secure checkout ready</span><span>WhatsApp support</span></div>
    </div>
    <div class="hero-product-stage">
        <img src="/assets/images/varahi-amman.png" alt="Sri Maha Varahi Amman">
        <div class="hero-product-card"><span>Featured blessing</span><strong>Sri Varahi Amman Collection</strong><small>Jewelry · Yantras · Pooja essentials</small></div>
    </div>
</section>
<section class="feature-strip"><article><span>01</span><h2>Premium Store</h2><p>Devotional items presented like a modern boutique, with clear categories and pricing.</p></article><article><span>02</span><h2>Astrology Booking</h2><p>Guide visitors from questions to available experts, slots, and booking actions.</p></article><article><span>03</span><h2>Admin Editable</h2><p>Products, categories, pricing, SMTP, and WhatsApp details stay manageable from admin.</p></article></section>
<section class="section section-head"><p class="eyebrow">Shop by devotion need</p><h2>Curated categories</h2></section>
<section class="category-rail">
    <?php foreach(array_slice($categories ?? [], 0, 6) as $cat): ?>
        <a class="category-card" href="/shop?category=<?= e($cat['slug'] ?? '') ?>"><span><?= e(substr($cat['name'] ?? 'Category', 0, 1)) ?></span><strong><?= e($cat['name'] ?? 'Category') ?></strong><small><?= e($cat['description'] ?? '') ?></small></a>
    <?php endforeach; ?>
</section>
<section class="section section-head"><p class="eyebrow">Featured edit-ready catalog</p><h2>Devotional products</h2><a href="/shop">View all products</a></section>
<section class="product-grid">
    <?php foreach(array_slice($products, 0, 4) as $item): ?>
        <article class="product-card">
            <a class="product-art" href="/product/<?= e($item['slug'] ?? '') ?>"><?php if(!empty($item['image_url'])): ?><img src="<?= e($item['image_url']) ?>" alt="<?= e($item['name']) ?>"><?php else: ?><span><?= e(substr($item['name'] ?? 'S', 0, 1)) ?></span><?php endif; ?></a>
            <div class="product-body">
                <p class="product-category"><?= e(str_replace('-', ' ', $item['category'] ?? 'Devotional')) ?></p>
                <h3><?= e($item['name'] ?? 'Product') ?></h3>
                <p><?= e($item['description'] ?? '') ?></p>
                <div class="price-row"><strong>₹<?= e((string)($item['offer_price'] ?: $item['price'] ?: 0)) ?></strong><?php if(!empty($item['offer_price'])): ?><small>₹<?= e((string)$item['price']) ?></small><?php endif; ?></div>
                <?php if(!empty($item['slug'])): ?><a class="text-link" href="/product/<?= e($item['slug']) ?>">View details</a><?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</section>
<section class="story-band">
    <div><p class="eyebrow">Designed for trust</p><h2>From inspiration to checkout in fewer steps</h2><p>Visitors can browse by category, compare prices, open product details, add to cart, ask on WhatsApp, and continue into checkout without losing context.</p></div>
    <div class="trust-grid"><div class="trust-card"><strong>Ritual-ready</strong><span>Clear usage context</span></div><div class="trust-card"><strong>Transparent</strong><span>INR price display</span></div><div class="trust-card"><strong>Supported</strong><span>WhatsApp contact</span></div></div>
</section>
<section class="section"><h2>Featured Astrologers</h2><div class="grid">
    <?php foreach(array_slice($astrologers, 0, 3) as $item): ?>
        <article class="panel">
            <h3><?= e($item['name'] ?? 'Astrologer') ?></h3>
            <p><?= e($item['description'] ?? '') ?></p>
            <?php if(!empty($item['slug'])): ?><a class="button-link" href="/astrologers/<?= e($item['slug']) ?>">View availability</a><?php endif; ?>
        </article>
    <?php endforeach; ?>
</div></section>
