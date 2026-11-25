<?php
$emptyCart = <<<'HTML'
<div class="cart-empty-state">
    <h2>Your cart is empty</h2>
    <p>Browse the shop to find devotional products.</p>
    <a href="/shop" class="btn btn-primary">Browse Shop</a>
</div>
HTML;
?>
<section class="section cart-page" aria-labelledby="cart-title">
    <div class="container cart-page__container">
        <nav class="breadcrumb breadcrumb--page" aria-label="Breadcrumb">
            <a href="/shop">Shop</a><span aria-hidden="true">/</span><span>Cart</span>
        </nav>
        <header class="cart-page__header">
            <div>
                <h1 id="cart-title">Your cart</h1>
                <?php if ($items): ?>
                    <a class="cart-page__jump" href="#cart-summary-title" data-cart-top-actions>
                        <span data-cart-item-count><?= (int)$itemCount ?> item<?= $itemCount === 1 ? '' : 's' ?></span>
                        <span>· Order summary <span aria-hidden="true">↓</span></span>
                    </a>
                <?php endif; ?>
            </div>
            <?php if ($items): ?>
                <a href="/checkout" class="btn btn-primary" data-cart-top-actions>Checkout <span aria-hidden="true">→</span></a>
            <?php endif; ?>
        </header>
        <p class="cart-update-status" role="status" data-cart-status></p>
        <?php if (!$items): ?>
            <?= $emptyCart ?>
        <?php else: ?>
            <?php $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16)); ?>
            <div class="cart-layout">
                <section aria-labelledby="cart-items-title">
                    <h2 id="cart-items-title" class="cart-page__section-title">Items in your cart</h2>
                    <div class="cart-items">
                        <?php foreach ($items as $item): ?>
                            <?php
                            $unitPrice = $item['unit_price'] ?? (($item['offer_price'] ?? 0) ?: ($item['price'] ?? 0));
                            $lineTotal = $item['line_total'];
                            $productUrl = '/product/' . rawurlencode(trim((string)$item['slug']));
                            $placeholder = placeholder_img($item['name']);
                            ?>
                            <article class="cart-item" data-cart-item data-unit-price="<?= e((string)$unitPrice) ?>">
                                <div class="cart-item__body" data-section-motion>
                                    <a class="cart-item__media" href="<?= e($productUrl) ?>" aria-label="View <?= e($item['name']) ?>">
                                        <img class="cart-item__img" src="<?= e(webp_src(($item['image_url'] ?? '') ?: $placeholder)) ?>" data-product-image-fallback="<?= e($placeholder) ?>" alt="" width="76" height="76">
                                    </a>
                                    <div>
                                        <h3 class="cart-item__name"><a href="<?= e($productUrl) ?>"><?= e($item['name']) ?></a></h3>
                                        <p class="cart-item__unit">₹<?= e((string)$unitPrice) ?> each</p>
                                    </div>
                                </div>
                                <div class="cart-item__actions">
                                    <div class="cart-item__qty">
                                        <?php foreach (['dec' => ['−', 'Decrease'], 'inc' => ['+', 'Increase']] as $action => [$symbol, $label]): ?>
                                            <?php if ($action === 'inc'): ?>
                                                <output class="cart-item__qty-val" aria-label="Quantity for <?= e($item['name']) ?>"><?= e((string)$item['qty']) ?></output>
                                            <?php endif; ?>
                                            <form method="post" action="/cart/update">
                                                <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
                                                <input type="hidden" name="action" value="<?= e($action) ?>">
                                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline" aria-label="<?= e($label) ?> quantity for <?= e($item['name']) ?>"><?= e($symbol) ?></button>
                                            </form>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="cart-item__price" data-line-total>₹<?= e((string)$lineTotal) ?></div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
                <section class="cart-summary" aria-labelledby="cart-summary-title">
                    <div data-section-motion>
                        <h2 id="cart-summary-title" tabindex="-1">Order summary</h2>
                        <div class="cart-summary__row">
                            <span data-cart-summary-label>Subtotal (<?= (int)$itemCount ?> item<?= $itemCount === 1 ? '' : 's' ?>)</span>
                            <span data-cart-subtotal>₹<?= e((string)($total ?? 0)) ?></span>
                        </div>
                        <div class="cart-summary__row" data-cart-gst-row <?= empty($gstAmount) ? 'hidden' : '' ?>>
                            <span>GST (included)</span>
                            <span data-cart-gst>₹<?= e(number_format((float)($gstAmount ?? 0), 2)) ?></span>
                        </div>
                        <div class="cart-summary__row cart-summary__row--total">
                            <span>Cart total</span>
                            <span data-cart-total>₹<?= e((string)($total ?? 0)) ?></span>
                        </div>
                        <p class="cart-summary__delivery">Any delivery charges are shown at checkout.</p>
                    </div>
                    <a href="/checkout" class="btn btn-primary btn-block" data-cart-inline-checkout>Proceed to checkout</a>
                    <a href="/shop" class="cart-summary__continue">Continue shopping</a>
                </section>
            </div>
            <aside class="cart-checkout-dock" aria-label="Checkout shortcut" data-cart-dock hidden>
                <div>
                    <span>Cart total</span>
                    <strong data-cart-dock-total>₹<?= e((string)($total ?? 0)) ?></strong>
                </div>
                <a href="/checkout" class="btn btn-primary">Checkout <span aria-hidden="true">→</span></a>
            </aside>
            <template id="cart-empty-template"><?= $emptyCart ?></template>
        <?php endif; ?>
    </div>
</section>
<script>
(() => {
    const layout = document.querySelector('.cart-layout');
    if (!window.fetch || !layout) return;

    const status = document.querySelector('[data-cart-status]');
    const buttons = Array.from(layout.querySelectorAll('.cart-item__qty button'));
    let pending = false;
    const dock = document.querySelector('[data-cart-dock]');
    const topCheckout = document.querySelector('a[data-cart-top-actions][href="/checkout"]');
    const inlineCheckout = layout.querySelector('[data-cart-inline-checkout]');
    const header = document.getElementById('site-header');
    let measuredNavHeight = -1;
    const mobile = window.matchMedia('(max-width: 767px)');
    let dockFrame = 0;

    function updateDock() {
        dockFrame = 0;
        if (!layout.isConnected || !mobile.matches) {
            if (layout.isConnected && dock.contains(document.activeElement)) inlineCheckout.focus();
            dock.hidden = true;
            return;
        }
        const navHeight = document.getElementById('bottom-nav')?.offsetHeight ?? 0;
        if (navHeight !== measuredNavHeight) {
            measuredNavHeight = navHeight;
            document.documentElement.style.setProperty('--cart-bottom-nav', navHeight + 'px');
        }
        const headerBottom = Math.max(0, header?.getBoundingClientRect().bottom ?? 0);
        const actionVisible = [topCheckout, inlineCheckout].some(action => {
            const bounds = action.getBoundingClientRect();
            return bounds.top >= headerBottom && bounds.bottom <= window.innerHeight - navHeight - 8;
        });
        if (!dock.contains(document.activeElement)) dock.hidden = actionVisible;
    }

    function scheduleDock() {
        if (!dockFrame) dockFrame = window.requestAnimationFrame(updateDock);
    }

    window.addEventListener('scroll', scheduleDock, {passive: true});
    window.addEventListener('resize', scheduleDock);
    window.addEventListener('pageshow', scheduleDock);
    document.addEventListener('DOMContentLoaded', scheduleDock, {once: true});
    dock.addEventListener('focusout', scheduleDock);
    scheduleDock();

    layout.addEventListener('submit', async event => {
        const form = event.target.closest('.cart-item__qty form');
        if (!form) return;
        event.preventDefault();
        if (pending) return;

        const button = event.submitter ?? form.querySelector('button');
        const restoreFocus = document.activeElement === button;
        let focusTarget = button;
        pending = true;
        buttons.forEach(control => { control.disabled = true; });
        delete status.dataset.error;
        status.textContent = '';

        try {
            const response = await fetch(form.getAttribute('action'), {
                method: 'POST', body: new FormData(form), headers:{Accept:'application/json'}
            });
            if (!response.ok) throw new Error('Cart update failed');
            const data = await response.json();
            const row = form.closest('[data-cart-item]');
            if (data.quantity === 0) {
                const neighbor = row.nextElementSibling ?? row.previousElementSibling;
                focusTarget = neighbor?.querySelector('.cart-item__qty button');
                row.remove();
            } else {
                row.querySelector('.cart-item__qty-val').textContent = data.quantity;
                row.querySelector('[data-line-total]').textContent = '₹' + Number((data.quantity * Number(row.dataset.unitPrice)).toFixed(2));
            }

            const badge = document.querySelector('.cart-count');
            if (badge) badge.textContent = data.cart_count;
            const label = `${data.summary.item_count} item${data.summary.item_count === 1 ? '' : 's'}`;
            document.querySelector('[data-cart-item-count]').textContent = label;
            status.textContent = data.quantity === 0 ? `Item removed. ${label} in your cart.` : `Quantity updated. ${label} in your cart.`;

            if (!layout.querySelector('[data-cart-item]')) {
                const empty = document.getElementById('cart-empty-template').content.cloneNode(true);
                focusTarget = empty.querySelector('a');
                layout.replaceWith(empty);
                dock.hidden = true;
                document.querySelectorAll('[data-cart-top-actions]').forEach(action => { action.hidden = true; });
                return;
            }

            layout.querySelector('[data-cart-summary-label]').textContent = `Subtotal (${label})`;
            layout.querySelector('[data-cart-subtotal]').textContent = '₹' + data.summary.total;
            const total = '₹' + data.summary.total;
            layout.querySelector('[data-cart-total]').textContent = total;
            dock.querySelector('[data-cart-dock-total]').textContent = total;
            scheduleDock();
            layout.querySelector('[data-cart-gst]').textContent = '₹' + Number(data.summary.gst_amount).toFixed(2);
            layout.querySelector('[data-cart-gst-row]').hidden = !data.summary.gst_amount;
        } catch {
            const message = 'Could not update the cart. Try again.';
            status.dataset.error = 'true';
            status.textContent = message;
            if (window.showToast) showToast(message, 'error');
        } finally {
            pending = false;
            buttons.forEach(control => { control.disabled = false; });
            if (restoreFocus && (document.activeElement === document.body || document.activeElement === button)) {
                focusTarget?.focus();
            }
        }
    });
})();
</script>
