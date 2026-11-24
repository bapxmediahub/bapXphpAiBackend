<?php
    $hasOffer = !empty($item['offer_price']) && $item['offer_price'] < $item['price'];
    $itemSlug = trim((string)($item['slug'] ?? ''));
    $productUrl = '/product/' . rawurlencode($itemSlug);
    $placeholder = placeholder_img($item['name']);
    $image = trim((string)($item['image_url'] ?? '')) ?: $placeholder;
    $isPurchasable = in_array((string)($item['stock_status'] ?? ''), ['in_stock', 'active'], true);
?>
<article class="product-card home-product-card">
    <a class="product-card__image" href="<?= e($productUrl) ?>" aria-label="View <?= e($item['name']) ?>">
        <img src="<?= e(webp_src($image)) ?>" data-product-image-fallback="<?= e($placeholder) ?>" alt="<?= e($item['name']) ?>" decoding="async">
        <?php if($hasOffer): ?>
            <span class="product-card__badge product-card__badge--sale">Sale</span>
        <?php endif; ?>
    </a>
    <div class="product-card__body">
        <h3><a class="product-card__title" href="<?= e($productUrl) ?>" title="<?= e($item['name']) ?>"><?= e($item['name']) ?></a></h3>
        <p class="product-card__desc"><?= e(strip_tags((string)($item['description'] ?? ''))) ?></p>
        <div class="product-card__price-row">
            <span class="price">₹<?= e((string)(($item['offer_price'] ?? 0) ?: ($item['price'] ?? 0))) ?></span>
            <?php if($hasOffer): ?>
                <span class="old-price">₹<?= e($item['price']) ?></span>
                <?php $pct = round((1 - $item['offer_price'] / ($item['price'] ?: 1)) * 100); ?>
                <span class="discount-pct">-<?= $pct ?>%</span>
            <?php endif; ?>
        </div>
        <div class="product-card__actions">
            <?php if($isPurchasable): ?>
            <?php $inCart = (int)($cartQuantities[$itemSlug] ?? 0); ?>
            <div class="product-purchase" data-cart-control data-slug="<?= e($itemSlug) ?>">
                <form method="post" action="/cart/add">
                    <input type="hidden" name="slug" value="<?= e($itemSlug) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="redirect" value="/checkout">
                    <button class="btn btn-sm btn-primary" type="submit" aria-label="Buy Now: <?= e($item['name']) ?>">Buy Now</button>
                </form>
                <div class="product-cart-control">
                    <form method="post" action="/cart/add" data-cart-add <?= $inCart > 0 ? 'hidden' : '' ?>>
                        <input type="hidden" name="slug" value="<?= e($itemSlug) ?>">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="redirect" value="/">
                        <button class="btn btn-sm btn-outline" type="submit" aria-label="Add to Cart: <?= e($item['name']) ?>">Add to Cart</button>
                    </form>
                    <div class="product-cart-control__stepper" data-cart-stepper <?= $inCart > 0 ? '' : 'hidden' ?>>
                        <?php foreach (['dec' => '−', 'inc' => '+'] as $cartAction => $symbol): ?>
                        <?php if ($cartAction === 'inc'): ?><output data-cart-quantity aria-live="polite" aria-label="Quantity in cart for <?= e($item['name']) ?>"><?= $inCart ?></output><?php endif; ?>
                        <form method="post" action="/cart/update" data-cart-change>
                            <input type="hidden" name="slug" value="<?= e($itemSlug) ?>">
                            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="action" value="<?= $cartAction ?>">
                            <input type="hidden" name="redirect" value="/">
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
