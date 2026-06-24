<section class="section" style="padding-top:var(--space-xl);">
    <div class="container container--narrow" style="margin-bottom:var(--space-xl);">
        <nav style="font-size:0.8rem; color:var(--color-text-muted);">
            <a href="/shop" style="color:var(--color-text-muted);">Shop</a> / <a href="/cart" style="color:var(--color-text-muted);">Cart</a> / <span style="color:var(--color-ink);">Checkout</span>
        </nav>
    </div>

    <?php if(empty($items)): ?>
        <div class="container container--narrow" style="text-align:center; padding:var(--space-4xl) 0;">
            <span style="display:block; margin-bottom:var(--space-md);"><svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg></span>
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
                        <p style="margin:var(--space-sm) 0 0; color:var(--color-text-muted); font-size:0.85rem;">Ecommerce orders use direct card or UPI payments only. Consultation credits cannot be used for products, and cash on delivery is not available.</p>
                        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
                        <script>
                        (() => {
                            const button = document.getElementById('pay-now');
                            const form = document.querySelector('.checkout-form');
                            button.addEventListener('click', async () => {
                                const fields = ['name', 'email', 'phone', 'address', 'city', 'pincode'];
                                for (const field of fields) {
                                    if (!form.querySelector(`[name="${field}"]`).reportValidity()) return;
                                }
                                button.disabled = true;
                                showToast('Opening secure Razorpay checkout...', 'info');
                                try {
                                    const response = await fetch('/checkout/create-order', {
                                        method: 'POST',
                                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                        body: new URLSearchParams({amount: '<?= (int)($total * 100) ?>'})
                                    });
                                    const order = await response.json();
                                    if (!response.ok || order.error) {
                                        throw new Error(order.error || 'Unable to create Razorpay order.');
                                    }
                                    const razorpay = new Razorpay({
                                        key: '<?= e($secrets['razorpay_key_id']) ?>',
                                        amount: order.amount,
                                        currency: order.currency || 'INR',
                                        order_id: order.order_id,
                                        name: 'Sri Panchami Spiritual',
                                        description: 'Product order payment',
                                        theme: {color: '#3A0003'},
                                        prefill: {
                                            name: form.querySelector('[name="name"]').value,
                                            email: form.querySelector('[name="email"]').value,
                                            contact: form.querySelector('[name="phone"]').value
                                        },
                                        modal: {
                                            ondismiss: () => {
                                                button.disabled = false;
                                                showToast('Payment was cancelled before completion.', 'info');
                                            }
                                        },
                                        handler: async (resp) => {
                                            showToast('Verifying payment...', 'info');
                                            const body = new URLSearchParams({
                                                razorpay_order_id: order.order_id,
                                                razorpay_payment_id: resp.razorpay_payment_id,
                                                razorpay_signature: resp.razorpay_signature,
                                                name: form.querySelector('[name="name"]').value,
                                                email: form.querySelector('[name="email"]').value,
                                                phone: form.querySelector('[name="phone"]').value,
                                                address: form.querySelector('[name="address"]').value,
                                                city: form.querySelector('[name="city"]').value,
                                                pincode: form.querySelector('[name="pincode"]').value
                                            });
                                            const verifyResponse = await fetch('/payment/verify', {method: 'POST', body});
                                            const result = await verifyResponse.json();
                                            if (!verifyResponse.ok || !result.verified) {
                                                throw new Error(result.error || 'Payment verification failed.');
                                            }
                                            showToast('Order placed. Redirecting to your orders...', 'success');
                                            window.location.href = '/account/orders';
                                        }
                                    });
                                    razorpay.on('payment.failed', (event) => {
                                        button.disabled = false;
                                        const reason = event.error && event.error.description ? event.error.description : 'Payment failed. Please try again.';
                                        showToast(reason, 'error');
                                    });
                                    razorpay.open();
                                } catch (error) {
                                    button.disabled = false;
                                    showToast(error.message || 'Payment could not be started.', 'error');
                                }
                            });
                        })();
                        </script>
                    <?php else: ?>
                        <script>document.addEventListener('DOMContentLoaded',function(){showToast('Razorpay is not configured yet. Please contact the administrator.','warning');});</script>
                    <?php endif; ?>
                </div>
                <div class="checkout-summary reveal">
                    <h2>Order Review</h2>
                    <?php foreach($items as $item): ?>
                        <div class="checkout-item">
                            <img class="checkout-item__img" src="<?= e($item['product']['image_url'] ?? placeholder_img($item['product']['name'])) ?>" alt="<?= e($item['product']['name']) ?>">
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
