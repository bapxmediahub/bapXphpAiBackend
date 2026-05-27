<section class="section" style="padding-top:var(--space-xl);">
    <div class="container container--narrow" style="margin-bottom:var(--space-xl);">
        <nav style="font-size:0.8rem; color:var(--color-text-muted);">
            <a href="/shop" style="color:var(--color-text-muted);">Shop</a> / <a href="/cart" style="color:var(--color-text-muted);">Cart</a> / <span style="color:var(--color-ink);">Checkout</span>
        </nav>
    </div>

    <?php if(empty($items)): ?>
        <div class="container container--narrow" style="text-align:center; padding:var(--space-4xl) 0;">
            <span style="font-size:3rem; display:block; margin-bottom:var(--space-md);">🛒</span>
            <h1 style="font-family:var(--font-serif); margin:0 0 var(--space-sm);">Your Cart is Empty</h1>
            <a href="/shop" class="btn btn-primary">Browse Shop</a>
        </div>
    <?php else: ?>
        <div class="container">
            <div class="checkout-layout">
                <div class="checkout-form reveal">
                    <div class="checkout-form__section">
                        <h3 class="checkout-form__section-title">Shipping Details</h3>
                        <div class="checkout-form__row">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="name" value="<?= e($_SESSION['user']['name'] ?? '') ?>" placeholder="Your full name" required>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" value="<?= e($_SESSION['user']['email'] ?? '') ?>" placeholder="your@email.com" required>
                            </div>
                        </div>
                        <div class="form-group" style="margin-top:var(--space-md);">
                            <label>Phone</label>
                            <input type="tel" name="phone" placeholder="+91 XXXXX XXXXX" required>
                        </div>
                        <div class="form-group" style="margin-top:var(--space-md);">
                            <label>Address</label>
                            <textarea name="address" placeholder="Door no, Street, Area" required rows="2"></textarea>
                        </div>
                        <div class="checkout-form__row" style="margin-top:var(--space-md);">
                            <div class="form-group">
                                <label>City</label>
                                <input type="text" name="city" placeholder="City" required>
                            </div>
                            <div class="form-group">
                                <label>PIN Code</label>
                                <input type="text" name="pincode" placeholder="600001" required>
                            </div>
                        </div>
                    </div>
                    <?php if(!empty($secrets['razorpay_key_id'])): ?>
                        <button id="pay-now" class="btn btn-primary btn-block btn-lg">Pay ₹<?= e((string)$total) ?> with Razorpay</button>
                        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
                        <script>document.getElementById('pay-now').onclick=async()=>{const r=await fetch('/checkout/create-order',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'amount=<?= $total*100 ?>'});const order=await r.json();new Razorpay({key:'<?= e($secrets['razorpay_key_id']) ?>',amount:order.amount,currency:'INR',order_id:order.id,name:'Sri Panchami Spiritual',handler:async(resp)=>{const body=new URLSearchParams({order_id:order.id,payment_id:resp.razorpay_payment_id,signature:resp.razorpay_signature});const vr=await fetch('/payment/verify',{method:'POST',body});const result=await vr.json();alert(result.verified?'Order placed! Thank you.':'Verification failed. Contact support.');if(result.verified)window.location.href='/account/orders';}}).open();};</script>
                    <?php else: ?>
                        <div class="flash flash--info" style="margin:0;">
                            Razorpay is not configured yet. Please contact the administrator.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="checkout-summary reveal">
                    <h2>Order Review</h2>
                    <?php foreach($items as $item): ?>
                        <div class="checkout-item">
                            <img class="checkout-item__img" src="<?= e($item['product']['image_url'] ?? 'https://placehold.co/60x60/fdfbf7/8c7e6d?text=📿') ?>" alt="<?= e($item['product']['name']) ?>">
                            <div>
                                <div class="checkout-item__name"><?= e($item['product']['name']) ?></div>
                                <div class="checkout-item__meta">Qty: <?= e((string)$item['qty']) ?></div>
                            </div>
                            <div class="checkout-item__price">₹<?= e((string)$item['line_total']) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <div class="cart-summary__row" style="margin-top:var(--space-md);">
                        <span>Subtotal</span>
                        <span>₹<?= e((string)$total) ?></span>
                    </div>
                    <div class="cart-summary__row">
                        <span>Shipping</span>
                        <span style="color:var(--color-success);">Free</span>
                    </div>
                    <div class="cart-summary__row cart-summary__row--total">
                        <span>Total</span>
                        <span>₹<?= e((string)$total) ?></span>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>
