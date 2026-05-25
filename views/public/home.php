<section class="home-hero"><div class="hero-copy"><p class="eyebrow">Sacred products · Astrology guidance · Temple devotion</p><h1>Bring home divine grace from Sri Panchami Spiritual</h1><p class="lede">Shop devotional essentials, connect with trusted astrologers, and discover Sri Maha Varahi Amman temple guidance in one sacred destination.</p><div class="actions"><a href="/shop">Shop devotional products</a><a href="/astrologers">Book astrology session</a></div><div class="hero-notes"><span>Razorpay-secured checkout</span><span>Remote & in-person consultations</span><span>Temple information & rituals</span></div></div><div class="hero-deity"><img src="/assets/images/varahi-amman.png" alt="Sri Maha Varahi Amman"></div></section>
<section class="section"><h2>Featured Products</h2><div class="grid">
    <?php foreach(array_slice($products, 0, 3) as $item): ?>
        <article class="panel">
            <?php if(!empty($item['image_url'])): ?><img src="<?= e($item['image_url']) ?>" alt="<?= e($item['name']) ?>"><?php endif; ?>
            <h3><?= e($item['name'] ?? 'Product') ?></h3>
            <p><?= e($item['description'] ?? '') ?></p>
            <?php if(!empty($item['slug'])): ?><a href="/product/<?= e($item['slug']) ?>">View product</a><?php endif; ?>
        </article>
    <?php endforeach; ?>
</div></section>
<section class="section"><h2>Featured Astrologers</h2><div class="grid">
    <?php foreach(array_slice($astrologers, 0, 3) as $item): ?>
        <article class="panel">
            <h3><?= e($item['name'] ?? 'Astrologer') ?></h3>
            <p><?= e($item['description'] ?? '') ?></p>
            <?php if(!empty($item['slug'])): ?><a href="/astrologers/<?= e($item['slug']) ?>">View availability</a><?php endif; ?>
        </article>
    <?php endforeach; ?>
</div></section>
<section class="feature-strip"><article><h2>Online Store</h2><p>Sacred items selected for daily pooja and devotional life.</p></article><article><h2>Astro Miracle</h2><p>Choose an astrologer, view slots, and book with clarity.</p></article><article><h2>Sri Varahi Temple</h2><p>Explore temple details, locations, and spiritual guidance.</p></article></section>
