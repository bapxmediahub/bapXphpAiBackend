 <section class="section" style="padding-top:var(--space-xl);">
     <div class="container container--narrow" style="margin-bottom:var(--space-xl);">
         <nav class="breadcrumb breadcrumb--page" aria-label="Breadcrumb">
             <a href="/shop">Shop</a><span aria-hidden="true">/</span><span>Cart</span>
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
                         <div class="cart-item reveal" data-cart-item data-slug="<?= e($item['slug']) ?>" data-unit-price="<?= e((string)($item['offer_price'] ?: $item['price'] ?: 0)) ?>" style="animation-delay:<?= $i * 0.05 ?>s">
                             <a href="/product/<?= e($item['slug']) ?>"><img class="cart-item__img" src="<?= e(webp_src($item['image_url'] ?? placeholder_img($item['name']))) ?>" alt="<?= e($item['name']) ?>"></a>
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
                             <div class="cart-item__price" data-line-total>₹<?= e((string)$lineTotal) ?></div>
                         </div>
                     <?php endforeach; ?>
                 </div>
                <div class="cart-summary">
                    <h2>Order Summary</h2>
                    <div class="cart-summary__row">
                        <span data-cart-summary-label>Subtotal (<?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?>)</span>
                        <span data-cart-subtotal>₹<?= e((string)($total ?? 0)) ?></span>
                    </div>
                    <div class="cart-summary__row">
                        <span>Shipping</span>
                        <span style="color:var(--color-success);">Free</span>
                    </div>
                    <div class="cart-summary__row cart-summary__row--total">
                        <span>Total</span>
                        <span data-cart-total>₹<?= e((string)($total ?? 0)) ?></span>
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
<script>
(function(){
    var forms=document.querySelectorAll('[data-cart-item] .cart-item__qty form');
    if(!window.fetch||!forms.length)return;
    forms.forEach(function(form){form.addEventListener('submit',async function(event){
        event.preventDefault();
        var button=form.querySelector('button');button.disabled=true;
        try{
            var response=await fetch(form.getAttribute('action'),{method:'POST',body:new FormData(form),headers:{Accept:'application/json'}});
            var data=await response.json();if(!response.ok)throw new Error(data.error||'Unable to update cart.');
            var row=form.closest('[data-cart-item]');
            if(data.quantity<=0){row.remove();}else{
                row.querySelector('.cart-item__qty-val').textContent=data.quantity;
                var line=data.quantity*Number(row.dataset.unitPrice||0);
                row.querySelectorAll('[data-line-total],.cart-item__price--mobile').forEach(function(el){el.textContent='₹'+line;});
            }
            var rows=document.querySelectorAll('[data-cart-item]'),total=0;
            rows.forEach(function(item){total+=Number(item.dataset.unitPrice||0)*Number(item.querySelector('.cart-item__qty-val').textContent||0);});
            var subtotal=document.querySelector('[data-cart-subtotal]'),grand=document.querySelector('[data-cart-total]'),label=document.querySelector('[data-cart-summary-label]'),badge=document.querySelector('.cart-count');
            if(subtotal)subtotal.textContent='₹'+total;if(grand)grand.textContent='₹'+total;if(label)label.textContent='Subtotal ('+rows.length+' item'+(rows.length===1?'':'s')+')';if(badge)badge.textContent=data.cart_count;
            if(!rows.length){var layout=document.querySelector('.cart-layout');if(layout)layout.parentElement.innerHTML='<div class="cart-empty-state"><h1>Your Cart is Empty</h1><p>Discover our spiritual products and add items to your cart.</p><a href="/shop" class="btn btn-primary">Browse Shop</a></div>';}
        }catch(error){if(window.showToast)showToast(error.message,'error');else form.submit();}
        finally{button.disabled=false;}
    });});
})();
</script>
