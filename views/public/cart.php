 <section class="section" style="padding-top:var(--space-xl);">
     <div class="container container--narrow" style="margin-bottom:var(--space-xl);">
         <nav style="font-size:0.8rem; color:var(--color-text-muted);">
             <a href="/shop" style="color:var(--color-text-muted);">Shop</a> / <span style="color:var(--color-ink);">Cart</span>
         </nav>
    </div>

    <?php if(empty($items)): ?>
        <div class="container container--narrow" style="text-align:center; padding:var(--space-4xl) 0;">
            <span style="display:block; margin-bottom:var(--space-md);"><svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg></span>
            <h1 style="font-family:var(--font-serif); margin:0 0 var(--space-sm);">Your Cart is Empty</h1>
            <p style="color:var(--color-text-muted); margin-bottom:var(--space-lg);">Discover our spiritual products and add items to your cart.</p>
            <a href="/shop" class="btn btn-primary">Browse Shop</a>
        </div>
    <?php else: ?>
         <div class="container">
             <div class="cart-layout">
                 <div class="cart-items">
                     <?php foreach($items as $i => $item): $lineTotal = ($item['offer_price'] ?: $item['price'] ?: 0) * $item['qty']; ?>
                         <div class="cart-item reveal" style="animation-delay:<?= $i * 0.05 ?>s">
                             <a href="/product/<?= e($item['slug']) ?>"><img class="cart-item__img" src="<?= e($item['image_url'] ?? placeholder_img($item['name'])) ?>" alt="<?= e($item['name']) ?>"></a>
                             <div>
                                 <h3 class="cart-item__name"><a href="/product/<?= e($item['slug']) ?>"><?= e($item['name']) ?></a></h3>
                                 <p class="cart-item__meta"><?= e($item['category'] ?? 'Spiritual Product') ?></p>
                                 <div class="cart-item__price--mobile">₹<?= e((string)$lineTotal) ?></div>
                             </div>
                             <div class="cart-item__qty">
<form method="post" action="/cart/update" style="display:flex; align-items:center; gap:4px;">
                                         <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
                                         <input type="hidden" name="action" value="dec">
                                         <?php $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16)); ?>
                                         <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                                         <button type="submit" class="btn btn-sm btn-outline">−</button>
                                     </form>
                                     <span class="cart-item__qty-val"><?= e((string)$item['qty']) ?></span>
                                     <form method="post" action="/cart/update" style="display:flex; align-items:center; gap:4px;">
                                         <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
                                         <input type="hidden" name="action" value="inc">
                                         <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                                         <button type="submit" class="btn btn-sm btn-outline">+</button>
                                     </form>
                             </div>
                             <div class="cart-item__price">₹<?= e((string)$lineTotal) ?></div>
<form method="post" action="/cart/remove" class="cart-item__remove-wrap">
                                  <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
                                  <?php $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16)); ?>
                                  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                                  <button class="cart-item__remove" title="Remove" aria-label="Remove <?= e($item['name']) ?> from cart">
                                     <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                                 </button>
                             </form>
                         </div>
                     <?php endforeach; ?>
                 </div>
                <div class="cart-summary">
                    <h2>Order Summary</h2>
                    <div class="cart-summary__row">
                        <span>Subtotal (<?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?>)</span>
                        <span>₹<?= e((string)($total ?? 0)) ?></span>
                    </div>
                    <div class="cart-summary__row">
                        <span>Shipping</span>
                        <span style="color:var(--color-success);">Free</span>
                    </div>
                    <div class="cart-summary__row cart-summary__row--total">
                        <span>Total</span>
                        <span>₹<?= e((string)($total ?? 0)) ?></span>
                    </div>
                    <a href="/checkout" class="btn btn-primary btn-block btn-lg">Proceed to Checkout</a>
                    <div style="text-align:center; margin-top:var(--space-sm);">
                        <a href="/shop" style="font-size:0.85rem; color:var(--color-text-muted);">← Continue Shopping</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>
