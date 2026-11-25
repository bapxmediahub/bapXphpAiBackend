<section class="section product-page">
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
            $canPurchase = in_array($product['stock_status'] ?? '', ['in_stock', 'active'], true);
            $placeholder = placeholder_img($product['name']);
            $gallery = [];
            if (!empty($product['image_urls'])) {
                $gallery = is_array($product['image_urls']) ? $product['image_urls'] : preg_split('/[\r\n,]+/', (string)$product['image_urls']);
            }
            if (!empty($product['image_url'])) array_unshift($gallery, $product['image_url']);
            $gallery = array_values(array_unique(array_filter(array_map(fn($image) => webp_src(trim((string)$image)), $gallery))));
            if (empty($gallery)) $gallery[] = $placeholder;
        ?>
        <?php if (!empty($adminPreview)): ?>
            <div class="container product-copy-block" role="note">
                <h2>Admin preview · Not for sale</h2>
                <p>This preview does not publish the product. Purchase controls are disabled.</p>
                <a href="/admin/products" class="btn btn-outline">Back to products</a>
            </div>
        <?php endif; ?>
        <div class="product-detail">
            <div class="product-gallery reveal" data-product-gallery>
                <div class="product-gallery__main" id="gallery-main">
                    <img id="product-main-image" src="<?= e($gallery[0]) ?>" data-product-image-fallback="<?= e($placeholder) ?>" alt="<?= e($product['name']) ?>">
                    <?php if(count($gallery) > 1): ?>
                        <button type="button" id="gallery-prev" class="product-gallery-arrow" aria-label="Previous image" hidden>‹</button>
                        <button type="button" id="gallery-next" class="product-gallery-arrow" aria-label="Next image" hidden>›</button>
                        <span id="gallery-position" class="product-gallery-position" role="status" hidden></span>
                    <?php endif; ?>
                </div>
                <?php if(count($gallery) > 1): ?>
                    <div class="product-gallery__thumbs" role="group" aria-label="Product images" hidden>
                        <?php foreach($gallery as $index => $image): ?>
                            <button type="button" class="product-gallery__thumb <?= $index === 0 ? 'active' : '' ?>" data-index="<?= e((string)$index) ?>" data-image="<?= e($image) ?>" aria-label="View product image <?= e((string)($index + 1)) ?>">
                                <img src="<?= e($image) ?>" data-product-image-fallback="<?= e($placeholder) ?>" alt="" loading="lazy" decoding="async">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="product-info">
                 <nav class="breadcrumb" aria-label="Breadcrumb">
                    <a href="/">Home</a> / <a href="/shop">Shop</a><?php if(!empty($product['category'])): ?> / <a href="/shop?category=<?= e(rawurlencode(trim((string)$product['category']))) ?>"><?= e($product['category']) ?></a><?php endif; ?><span class="product-info__breadcrumb-current" aria-current="page"> / <?= e($product['name']) ?></span>
                </nav>
                <?php if(!empty($product['category'])): ?>
                    <span class="eyebrow serif-accent product-info__category"><?= e($product['category']) ?></span>
                <?php endif; ?>
                <h1><?= e($product['name']) ?></h1>
                <?php $productRating = $reviewSummary['average'] ?? 0; $productReviewCount = $reviewSummary['count'] ?? 0; ?>
                <div class="product-info__meta">
                    <div class="product-info__rating">
                        <span class="rating-stars" aria-hidden="true">
                            <?php for($i=1;$i<=5;$i++):?>
                                <svg class="icon-star" width="16" height="16" viewBox="0 0 24 24" fill="<?= $productRating >= $i ? 'var(--color-rating)' : 'none' ?>" stroke="var(--color-rating)" stroke-width="1"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <?php endfor;?>
                        </span>
                        <span style="color:var(--color-text-muted); font-size:0.8rem;"><?= $productReviewCount > 0 ? e((string)$productRating) . ' from ' . e((string)$productReviewCount) . ($productReviewCount === 1 ? ' review' : ' reviews') : 'No reviews yet' ?></span>
                    </div>
                </div>
                <div class="product-info__price">
                    <span class="price">₹<?= e(number_format((float)(($product['offer_price'] ?? 0) ?: ($product['price'] ?? 0)), 2)) ?></span>
                    <?php if($hasOffer): ?>
                        <span class="old-price">₹<?= e(number_format((float)$product['price'], 2)) ?></span>
                        <?php $pct = round((1 - $product['offer_price'] / ($product['price'] ?: 1)) * 100); ?>
                        <span class="badge badge--success">Save <?= $pct ?>%</span>
                    <?php endif; ?>
                </div>
                <?php if (empty($adminPreview)): ?>
                    <div class="product-buy" id="product-purchase" data-page-section="Purchase" tabindex="-1">
                        <?php if ($canPurchase): ?>
                            <form id="product-cart-form" method="post" action="/cart/add">
                                <input type="hidden" name="slug" value="<?= e($product['slug']) ?>">
                                <?php $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16)); ?>
                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                <div class="product-buy__quantity">
                                    <label for="qty-input">Quantity</label>
                                    <div class="qty-input">
                                        <button type="button" aria-label="Decrease quantity" data-quantity-step="-1" hidden>−</button>
                                        <input type="number" name="qty" value="1" min="1" max="99" id="qty-input" required>
                                        <button type="button" aria-label="Increase quantity" data-quantity-step="1" hidden>+</button>
                                    </div>
                                </div>
                                <div class="product-buy__actions">
                                    <button name="redirect" value="/product/<?= e(rawurlencode(trim($product['slug']))) ?>" class="btn btn-primary">Add to Cart</button>
                                    <button name="redirect" value="/checkout" class="btn btn-outline">Buy Now</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <strong>Out of stock</strong>
                            <a href="/shop">Browse available products</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($product['description'])): ?>
                    <p class="product-info__desc"><?= e($product['description']) ?></p>
                <?php endif; ?>
                <div data-section-index hidden></div>
                <?php if(!empty($product['highlights']) && is_array($product['highlights'])): ?>
                    <div class="product-copy-block">
                        <h2 id="product-features" data-page-section="Features" tabindex="-1">Key Features</h2>
                        <ul>
                            <?php foreach($product['highlights'] as $point): ?>
                                <li><?= e($point) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if(!empty($product['description_points']) && is_array($product['description_points'])): ?>
                    <div class="product-copy-block">
                        <h2 id="product-description" data-page-section="Description" tabindex="-1">Product Description</h2>
                        <ul>
                            <?php foreach($product['description_points'] as $point): ?>
                                <li><?= e($point) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if(!empty($product['specifications']) && is_array($product['specifications'])): ?>
                    <div class="product-copy-block product-copy-block--specs">
                        <h2 id="product-specifications" data-page-section="Specifications" tabindex="-1">Specifications</h2>
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
            <h2 class="section-title" style="font-size:1.5rem; margin-bottom:var(--space-xl);" id="product-related" data-page-section="Related products" tabindex="-1">Related Products</h2>
            <div class="product-grid">
                <?php foreach(array_slice($related, 0, 4) as $item): ?>
                    <?php
                        $itemSlug = trim((string)($item['slug'] ?? ''));
                        $productUrl = '/product/' . rawurlencode($itemSlug);
                        $itemPlaceholder = placeholder_img($item['name']);
                        $itemImage = trim((string)($item['image_url'] ?? '')) ?: $itemPlaceholder;
                        $isPurchasable = in_array((string)($item['stock_status'] ?? ''), ['in_stock', 'active'], true);
                    ?>
                    <article class="product-card reveal">
                        <a class="product-card__image" href="<?= e($productUrl) ?>" aria-label="View <?= e($item['name']) ?>">
                            <img src="<?= e(webp_src($itemImage)) ?>" data-product-image-fallback="<?= e($itemPlaceholder) ?>" alt="<?= e($item['name']) ?>" decoding="async">
                        </a>
                        <div class="product-card__body">
                            <h3><a class="product-card__title" href="<?= e($productUrl) ?>"><?= e($item['name']) ?></a></h3>
                            <div class="product-card__price-row">
                                <span class="price">₹<?= e((string)(($item['offer_price'] ?? 0) ?: ($item['price'] ?? 0))) ?></span>
                                <?php if(!empty($item['offer_price']) && $item['offer_price'] < $item['price']): ?>
                                    <span class="old-price">₹<?= e($item['price']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="product-card__actions">
                                <?php if($isPurchasable): ?>
                        <?php $inCart = (int)($cartQuantities[$itemSlug] ?? 0); ?>
                        <div class="product-purchase" data-cart-control data-slug="<?= e($itemSlug) ?>">
                            <form method="post" action="/cart/add">
                                <input type="hidden" name="slug" value="<?= e($itemSlug) ?>">
                                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                                <input type="hidden" name="redirect" value="/checkout">
                                <button class="btn btn-sm btn-primary" type="submit" aria-label="Buy Now: <?= e($item['name']) ?>">Buy Now</button>
                            </form>
                            <div class="product-cart-control">
                                <form method="post" action="/cart/add" data-cart-add <?= $inCart > 0 ? 'hidden' : '' ?>>
                                    <input type="hidden" name="slug" value="<?= e($itemSlug) ?>">
                                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                                    <input type="hidden" name="redirect" value="<?= e('/product/' . rawurlencode(trim($product['slug']))) ?>">
                                    <button class="btn btn-sm btn-outline" type="submit" aria-label="Add to Cart: <?= e($item['name']) ?>">Add to Cart</button>
                                </form>
                                <div class="product-cart-control__stepper" data-cart-stepper <?= $inCart > 0 ? '' : 'hidden' ?>>
                                    <?php foreach (['dec' => '−', 'inc' => '+'] as $cartAction => $symbol): ?>
                                    <?php if ($cartAction === 'inc'): ?><output data-cart-quantity aria-live="polite" aria-label="Quantity in cart for <?= e($item['name']) ?>"><?= $inCart ?></output><?php endif; ?>
                                    <form method="post" action="/cart/update" data-cart-change>
                                        <input type="hidden" name="slug" value="<?= e($itemSlug) ?>">
                                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                                        <input type="hidden" name="action" value="<?= $cartAction ?>">
                                        <input type="hidden" name="redirect" value="<?= e('/product/' . rawurlencode(trim($product['slug']))) ?>">
                                        <button type="submit" aria-label="<?= $cartAction === 'dec' ? 'Decrease' : 'Increase' ?> cart quantity for <?= e($item['name']) ?>"><?= $symbol ?></button>
                                    </form>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                                <?php else: ?>
                                <span class="product-card__unavailable">Out of stock</span>
                                <?php endif; ?>
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
(() => {
    const quantity = document.getElementById('qty-input');
    quantity?.closest('.qty-input').querySelectorAll('[data-quantity-step]').forEach(button => {
        button.hidden = false;
        button.addEventListener('click', () => {
            if (button.dataset.quantityStep === '-1') quantity.stepDown();
            else quantity.stepUp();
            quantity.dispatchEvent(new Event('change', {bubbles: true}));
        });
    });

    const gallery = document.querySelector('[data-product-gallery]');
    const group = gallery?.querySelector('.product-gallery__thumbs');
    if (!group) return;
    const thumbs = Array.from(group.querySelectorAll('.product-gallery__thumb'));
    const image = gallery.querySelector('#product-main-image');
    const previous = gallery.querySelector('#gallery-prev');
    const next = gallery.querySelector('#gallery-next');
    const position = gallery.querySelector('#gallery-position');
    let current = 0;
    let currentImage = image.getAttribute('src');

    function show(index) {
        current = (index + thumbs.length) % thumbs.length;
        const source = thumbs[current].dataset.image;
        if (currentImage !== source) {
            currentImage = source;
            delete image.dataset.fallbackApplied;
            image.src = source;
        }
        position.textContent = `Image ${current + 1} of ${thumbs.length}`;
        thumbs.forEach((thumb, i) => {
            thumb.classList.toggle('active', i === current);
            if (i === current) thumb.setAttribute('aria-current', 'true');
            else thumb.removeAttribute('aria-current');
        });
    }

    thumbs.forEach((thumb, index) => thumb.addEventListener('click', () => show(index)));
    previous.addEventListener('click', () => show(current - 1));
    next.addEventListener('click', () => show(current + 1));
    image.addEventListener('click', () => show(current + 1));
    gallery.addEventListener('keydown', event => {
        if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
        event.preventDefault();
        show(current + (event.key === 'ArrowLeft' ? -1 : 1));
    });
    group.hidden = previous.hidden = next.hidden = position.hidden = false;
    show(0);
})();
</script>
