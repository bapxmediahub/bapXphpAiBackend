<section class="section">
    <?php if(empty($product)): ?>
        <div class="container container--narrow">
            <div class="about-story-card reveal" style="text-align:center; padding:var(--space-3xl);">
                <span style="display:block; margin-bottom:var(--space-md);"><svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="8" y1="11" x2="14" y2="11"/></svg></span>
                <h1 style="font-family:var(--font-serif); margin:0 0 var(--space-sm);">Product Not Found</h1>
                <p style="color:var(--color-text-muted); margin-bottom:var(--space-lg);">The item you're looking for is unavailable.</p>
                <a href="/shop" class="btn btn-primary">Browse Shop</a>
            </div>
        </div>
    <?php else: ?>
        <?php
            $hasOffer = !empty($product['offer_price']) && $product['offer_price'] < $product['price'];
            $gallery = [];
            if (!empty($product['image_urls'])) {
                $gallery = is_array($product['image_urls']) ? $product['image_urls'] : preg_split('/[\r\n,]+/', (string)$product['image_urls']);
            }
            if (!empty($product['image_url'])) array_unshift($gallery, $product['image_url']);
            $gallery = array_values(array_unique(array_filter(array_map(fn($image) => webp_src(trim((string)$image)), $gallery))));
            if (empty($gallery)) $gallery[] = placeholder_img($product['name']);
        ?>
        <div class="product-detail">
            <div class="product-gallery reveal">
                <div class="product-gallery__main" id="gallery-main" style="position:relative;">
                    <img id="product-main-image" src="<?= e($gallery[0]) ?>" alt="<?= e($product['name']) ?>" style="transition:opacity 0.2s ease;">
                    <?php if(count($gallery) > 1): ?>
                        <button type="button" id="gallery-prev" aria-label="Previous image" style="position:absolute;left:8px;top:50%;transform:translateY(-50%);width:40px;height:40px;border:0;border-radius:50%;background:rgba(255,255,255,0.85);color:var(--color-ink);cursor:pointer;font-size:1.2rem;display:flex;align-items:center;justify-content:center;box-shadow:var(--shadow-md);opacity:0;transition:opacity 0.2s ease;z-index:2;">‹</button>
                        <button type="button" id="gallery-next" aria-label="Next image" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);width:40px;height:40px;border:0;border-radius:50%;background:rgba(255,255,255,0.85);color:var(--color-ink);cursor:pointer;font-size:1.2rem;display:flex;align-items:center;justify-content:center;box-shadow:var(--shadow-md);opacity:0;transition:opacity 0.2s ease;z-index:2;">›</button>
                    <?php endif; ?>
                </div>
                <?php if(count($gallery) > 1): ?>
                    <div class="product-gallery__thumbs" aria-label="Product images">
                        <?php foreach($gallery as $index => $image): ?>
                            <button type="button" class="product-gallery__thumb <?= $index === 0 ? 'active' : '' ?>" data-index="<?= e((string)$index) ?>" data-image="<?= e($image) ?>" aria-label="View product image <?= e((string)($index + 1)) ?>">
                                <img src="<?= e($image) ?>" alt="">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="product-info">
                 <nav class="breadcrumb" aria-label="Breadcrumb">
                    <a href="/">Home</a> / <a href="/shop">Shop</a><?php if(!empty($product['category'])): ?> / <a href="/shop?category=<?= e($product['category_slug'] ?? '') ?>"><?= e($product['category']) ?></a><?php endif; ?> / <span style="color:var(--color-ink);"><?= e($product['name']) ?></span>
                </nav>
                <?php if(!empty($product['category'])): ?>
                    <span class="eyebrow serif-accent" style="font-size:0.75rem; display:block; margin-bottom:var(--space-xs);"><?= e($product['category']) ?></span>
                <?php endif; ?>
                <h1><?= e($product['name']) ?></h1>
                <?php $productRating = $reviewSummary['average'] ?? 0; $productReviewCount = $reviewSummary['count'] ?? 0; ?>
                <div class="product-info__meta">
                    <div class="product-info__rating">
                        <span class="rating-stars" aria-label="<?= e((string)$productRating) ?> star product rating">
                            <?php for($i=1;$i<=5;$i++):?>
                                <svg class="icon-star" width="16" height="16" viewBox="0 0 24 24" fill="<?= $productRating >= $i ? 'var(--color-rating)' : 'none' ?>" stroke="var(--color-rating)" stroke-width="1"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <?php endfor;?>
                        </span>
                        <span style="color:var(--color-text-muted); font-size:0.8rem;"><?= $productReviewCount > 0 ? e((string)$productRating) . ' from ' . e((string)$productReviewCount) . ' reviews' : 'No reviews yet' ?></span>
                    </div>
                </div>
                <div class="product-info__price">
                    <span class="price">₹<?= e((string)($product['offer_price'] ?: $product['price'] ?: 0)) ?></span>
                    <?php if($hasOffer): ?>
                        <span class="old-price">₹<?= e($product['price']) ?></span>
                        <?php $pct = round((1 - $product['offer_price'] / ($product['price'] ?: 1)) * 100); ?>
                        <span class="badge badge--success">Save <?= $pct ?>%</span>
                    <?php endif; ?>
                </div>
                <p class="product-info__desc"><?= e($product['description'] ?? 'A sacred spiritual product crafted with devotion and care.') ?></p>
                <?php if(!empty($product['highlights']) && is_array($product['highlights'])): ?>
                    <div class="product-copy-block">
                        <h2>Key Features</h2>
                        <ul>
                            <?php foreach($product['highlights'] as $point): ?>
                                <li><?= e($point) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if(!empty($product['description_points']) && is_array($product['description_points'])): ?>
                    <div class="product-copy-block">
                        <h2>Product Description</h2>
                        <ul>
                            <?php foreach($product['description_points'] as $point): ?>
                                <li><?= e($point) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if(!empty($product['specifications']) && is_array($product['specifications'])): ?>
                    <div class="product-copy-block product-copy-block--specs">
                        <h2>Specifications</h2>
                        <dl>
                            <?php foreach($product['specifications'] as $label => $value): ?>
                                <div>
                                    <dt><?= e((string)$label) ?></dt>
                                    <dd><?= e(is_array($value) ? implode(', ', $value) : (string)$value) ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    </div>
                <?php endif; ?>
                 <div class="product-info__form">
                     <form method="post" action="/cart/add" style="display:flex; gap:var(--space-md); align-items:center; width:100%;">
                         <input type="hidden" name="slug" value="<?= e($product['slug']) ?>">
                         <input type="hidden" name="redirect" value="/product/<?= e($product['slug']) ?>">
                         <?php $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16)); ?>
                         <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                         <div class="qty-input">

                            <button type="button" onclick="this.nextElementSibling.stepDown(); this.nextElementSibling.dispatchEvent(new Event('change'))">−</button>
                            <input type="number" name="qty" value="1" min="1" max="99" id="qty-input">
                            <button type="button" onclick="this.previousElementSibling.stepUp(); this.previousElementSibling.dispatchEvent(new Event('change'))">+</button>
                        </div>
                        <button class="btn btn-primary btn-lg" style="flex:1;">Add to Cart</button>
                    </form>
                </div>
                <div class="product-info__actions">
                    <button type="button" class="btn btn-outline btn-lg btn-block" onclick="var f=this.closest('.product-info__form').querySelector('form'); f.querySelector('[name=redirect]').value='/checkout'; f.submit();">Buy Now →</button>
                </div>
                <div class="product-info__features">
                    <div class="product-feature"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg> Secure Payment</div>
                    <div class="product-feature"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg> Fast Delivery</div>
                    <div class="product-feature"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Authentic Product</div>
                    <div class="product-feature"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg> Blessed & Energized</div>
                </div>
            </div>
        </div>
        <?php if (!empty($related) && is_array($related)): ?>
        <?php
            $cartQuantities = [];
            foreach ($_SESSION['cart'] ?? [] as $cartItem) {
                $cartQuantities[(string)($cartItem['slug'] ?? '')] = (int)($cartItem['qty'] ?? 0);
            }
            $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16));
        ?>
        <div class="container" style="margin-top:var(--space-4xl);">
            <h2 class="section-title" style="font-size:1.5rem; margin-bottom:var(--space-xl);">Related Products</h2>
            <div class="product-grid">
                <?php foreach(array_slice($related, 0, 4) as $item): ?>
                    <article class="product-card reveal">
                        <div class="product-card__image">
                            <img src="<?= e(webp_src($item['image_url'] ?? placeholder_img($item['name']))) ?>" alt="<?= e($item['name']) ?>" decoding="async">
                        </div>
                        <div class="product-card__body">
                            <h3><?= e($item['name']) ?></h3>
                            <div class="product-card__price-row">
                                <span class="price">₹<?= e((string)($item['offer_price'] ?: $item['price'] ?: 0)) ?></span>
                                <?php if(!empty($item['offer_price']) && $item['offer_price'] < $item['price']): ?>
                                    <span class="old-price">₹<?= e($item['price']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php $itemQty = $cartQuantities[(string)($item['slug'] ?? '')] ?? 0; ?>
                            <div class="product-card__actions">
                                <a href="/product/<?= e($item['slug']) ?>" class="btn btn-sm btn-ghost">View →</a>
                                <div class="product-card__form product-card__stepper" aria-label="<?= e($item['name']) ?> cart quantity">
                                    <form method="post" action="/cart/update">
                                        <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
                                        <input type="hidden" name="action" value="dec">
                                        <input type="hidden" name="redirect" value="/product/<?= e($product['slug']) ?>">
                                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                                        <button type="submit" aria-label="Remove one <?= e($item['name']) ?>" <?= $itemQty <= 0 ? 'disabled' : '' ?>>−</button>
                                    </form>
                                    <span class="qty-input__value"><?= e((string)$itemQty) ?></span>
                                    <form method="post" action="/cart/add">
                                        <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
                                        <input type="hidden" name="qty" value="1">
                                        <input type="hidden" name="redirect" value="/product/<?= e($product['slug']) ?>">
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
        <?php endif; ?>
    <?php endif; ?>
</section>
<script>
(function(){
    const thumbs = document.querySelectorAll('.product-gallery__thumb');
    const main = document.getElementById('product-main-image');
    const prev = document.getElementById('gallery-prev');
    const next = document.getElementById('gallery-next');
    const mainWrap = document.getElementById('gallery-main');
    const images = [];
    let current = 0;

    thumbs.forEach(t => {
        images.push(t.dataset.image);
        t.addEventListener('click', () => show(parseInt(t.dataset.index, 10)));
    });

    function show(index) {
        if (index < 0 || index >= images.length || index === current) return;
        current = index;
        main.style.opacity = '0.4';
        setTimeout(() => {
            main.src = images[current];
            main.style.opacity = '1';
        }, 120);
        thumbs.forEach(t => t.classList.toggle('active', parseInt(t.dataset.index, 10) === current));
    }

    function nextImage() { show((current + 1) % images.length); }
    function prevImage() { show((current - 1 + images.length) % images.length); }

    if (prev) prev.addEventListener('click', e => { e.stopPropagation(); prevImage(); });
    if (next) next.addEventListener('click', e => { e.stopPropagation(); nextImage(); });
    if (mainWrap && images.length > 1) {
        mainWrap.addEventListener('click', nextImage);
        mainWrap.addEventListener('mouseenter', () => { if (prev) prev.style.opacity = '1'; if (next) next.style.opacity = '1'; });
        mainWrap.addEventListener('mouseleave', () => { if (prev) prev.style.opacity = '0'; if (next) next.style.opacity = '0'; });
    }
    document.addEventListener('keydown', e => {
        if (images.length <= 1) return;
        if (e.key === 'ArrowLeft') { e.preventDefault(); prevImage(); }
        if (e.key === 'ArrowRight') { e.preventDefault(); nextImage(); }
    });
})();
</script>
