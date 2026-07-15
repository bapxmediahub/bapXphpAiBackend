<section class="section" style="padding-top:var(--space-xl);">
    <div class="container container--narrow" style="margin-bottom:var(--space-xl);">
        <nav class="breadcrumb breadcrumb--page" aria-label="Breadcrumb">
            <a href="/shop">Shop</a><span aria-hidden="true">/</span><a href="/cart">Cart</a><span aria-hidden="true">/</span><span>Checkout</span>
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
                                <input type="text" id="checkout-name" name="name" value="<?= e($_SESSION['user']['name'] ?? '') ?>" placeholder="Your full name" required>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" id="checkout-email" name="email" value="<?= e($_SESSION['user']['email'] ?? '') ?>" placeholder="your@email.com" required>
                            </div>
                        </div>
                        <?php if (!empty($addresses)): ?>
                        <div class="form-group" style="margin-top:var(--space-md);">
                            <label for="saved-address">Use a saved address</label>
                            <select id="saved-address">
                                <option value="">Enter a new address</option>
                                <?php foreach ($addresses as $savedAddress): ?>
                                    <option value="<?= e((string)$savedAddress['id']) ?>" <?= !empty($savedAddress['is_default']) ? 'selected' : '' ?> data-address='<?= e(json_encode($savedAddress, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>'><?= e($savedAddress['name']) ?><?= !empty($savedAddress['is_default']) ? ' (Default)' : '' ?> — <?= e($savedAddress['city']) ?>, <?= e($savedAddress['pincode']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="form-group" style="margin-top:var(--space-md);">
                            <label>Phone</label>
                            <input type="tel" id="checkout-phone" name="phone" placeholder="+91 XXXXX XXXXX" required>
                        </div>
                        <div class="form-group" style="margin-top:var(--space-md);">
                            <label>Address</label>
                            <textarea id="checkout-address" name="address" placeholder="Door no, Street, Area" required rows="2"></textarea>
                        </div>
                        <div class="checkout-form__row" style="margin-top:var(--space-md);">
                            <div class="form-group">
                                <label>City</label>
                                <input type="text" id="checkout-city" name="city" placeholder="City" required>
                            </div>
                            <div class="form-group">
                                <label>PIN Code</label>
                                <input type="text" id="checkout-pincode" name="pincode" placeholder="600001" required>
                            </div>
                        </div>
                        <?php if (!empty($_SESSION['user']['email'])): ?>
                        <div class="checkout-form__row" style="margin-top:var(--space-md); align-items:end;">
                            <div class="form-group"><label for="address-name">Address name</label><input type="text" id="address-name" name="address_name" placeholder="Home, Office, Parents"></div>
                            <div class="checkout-address-options">
                                <label><input type="checkbox" id="save-address" name="save_address" value="1"> Save for next time</label>
                                <label><input type="checkbox" id="default-address" name="is_default" value="1"> Make default</label>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group" style="margin-top:var(--space-md);">
                        <label>Coupon Code</label>
                        <div style="display:flex; gap:var(--space-sm);">
                            <input type="text" id="coupon-code" name="coupon_code" placeholder="Enter coupon code" style="flex:1;">
                        </div>
                    </div>
                    <?php
                        $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16));
                        $hasRazorpay = !empty($razorpayReady);
                        $hasStripe = !empty($secrets['stripe_secret_key']);
                        $hasPaymentGateway = $hasRazorpay || $hasStripe;
                        $defaultPaymentMethod = $hasRazorpay ? 'razorpay' : 'stripe';
                    ?>
                    <input type="hidden" id="csrf-token" value="<?= $csrf ?>">
                    <?php if($hasPaymentGateway): ?>
                    <div id="payment-method-toggle" style="margin-bottom:var(--space-md);">
                        <?php if ($hasRazorpay): ?>
                        <label style="display:inline-flex; align-items:center; gap:var(--space-sm); margin-right:var(--space-lg); cursor:pointer;">
                            <input type="radio" name="payment_method" value="razorpay" <?= $defaultPaymentMethod === 'razorpay' ? 'checked' : '' ?> onchange="togglePaymentMethod()"> Pay securely with Razorpay <small>(Live)</small>
                        </label>
                        <?php endif; ?>
                        <?php if ($hasStripe): ?>
                        <label style="display:inline-flex; align-items:center; gap:var(--space-sm); cursor:pointer;">
                            <input type="radio" name="payment_method" value="stripe" <?= $defaultPaymentMethod === 'stripe' ? 'checked' : '' ?> onchange="togglePaymentMethod()"> Pay with Stripe
                        </label>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if($hasPaymentGateway): ?>
                        <button id="pay-now" class="btn btn-primary btn-block btn-lg">Pay ₹<?= e((string)$total) ?></button>
                        <p style="margin:var(--space-sm) 0 0; color:var(--color-text-muted); font-size:0.85rem;">Product orders use secure online card or UPI payment. Cash on delivery is not available.</p>
                        <?php if($hasRazorpay): ?>
                        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
                        <?php endif; ?>
                        <script>
                        const checkoutGateways = {
                            razorpay: <?= $hasRazorpay ? 'true' : 'false' ?>,
                            stripe: <?= $hasStripe ? 'true' : 'false' ?>
                        };
                        function togglePaymentMethod() {
                            var method = document.querySelector('input[name="payment_method"]:checked');
                            if (!method) return;
                            var btn = document.getElementById('pay-now');
                            if (!btn) return;
                            if (method.value === 'stripe') {
                                btn.textContent = 'Pay ₹<?= e((string)$total) ?> with Stripe';
                            } else {
                                btn.textContent = 'Pay ₹<?= e((string)$total) ?> with Razorpay';
                            }
                        }
                        const savedAddress = document.getElementById('saved-address');
                        if (savedAddress) savedAddress.addEventListener('change', function () {
                            const option = this.options[this.selectedIndex];
                            const data = option && option.dataset.address ? JSON.parse(option.dataset.address) : {};
                            ['name', 'phone', 'address', 'city', 'pincode'].forEach(function (field) {
                                const input = document.querySelector('[name="' + field + '"]');
                                if (input && data[field === 'name' ? 'recipient_name' : field] !== undefined) input.value = data[field === 'name' ? 'recipient_name' : field];
                            });
                            const addressName = document.querySelector('[name="address_name"]');
                            const save = document.querySelector('[name="save_address"]');
                            if (addressName) addressName.value = data.name || '';
                            if (save) save.checked = false;
                        });
                        if (savedAddress && savedAddress.value) savedAddress.dispatchEvent(new Event('change'));
                        (() => {
                            const button = document.getElementById('pay-now');
                            const form = document.querySelector('.checkout-form');
                            button.addEventListener('click', async () => {
                                const method = document.querySelector('input[name="payment_method"]:checked');
                                const paymentMethod = method ? method.value : 'razorpay';
                                const fields = ['name', 'email', 'phone', 'address', 'city', 'pincode'];
                                for (const field of fields) {
                                    if (!form.querySelector(`[name="${field}"]`).reportValidity()) return;
                                }
                                button.disabled = true;
                                const csrf = document.getElementById('csrf-token').value;
                                const bodyParams = {
                                    _csrf: csrf,
                                    payment_method: paymentMethod,
                                    coupon_code: document.getElementById('coupon-code').value,
                                    amount: '<?= (int)($total * 100) ?>',
                                    name: form.querySelector('[name="name"]').value,
                                    email: form.querySelector('[name="email"]').value,
                                    phone: form.querySelector('[name="phone"]').value,
                                    address: form.querySelector('[name="address"]').value,
                                    city: form.querySelector('[name="city"]').value,
                                    pincode: form.querySelector('[name="pincode"]').value,
                                    address_name: form.querySelector('[name="address_name"]')?.value || '',
                                    save_address: form.querySelector('[name="save_address"]')?.checked ? '1' : ''
                                    ,is_default: form.querySelector('[name="is_default"]')?.checked ? '1' : ''
                                };
                                if (paymentMethod === 'stripe') {
                                    showToast('Opening secure Stripe checkout...', 'info');
                                    try {
                                        const response = await fetch('/checkout/create-order', {
                                            method: 'POST',
                                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                            body: new URLSearchParams(bodyParams)
                                        });
                                        const result = await response.json();
                                        if (!response.ok || result.error) {
                                            throw new Error(result.error || 'Unable to create Stripe checkout session.');
                                        }
                                        if (result.stripe_url) {
                                            window.location.href = result.stripe_url;
                                        } else {
                                            throw new Error('No checkout URL returned.');
                                        }
                                    } catch (error) {
                                        button.disabled = false;
                                        showToast(error.message || 'Payment could not be started.', 'error');
                                    }
                                    return;
                                }
                                if (!checkoutGateways.razorpay || typeof Razorpay === 'undefined') {
                                    button.disabled = false;
                                    showToast('Razorpay is not configured yet. Choose another payment method or contact the administrator.', 'error');
                                    return;
                                }
                                showToast('Opening secure Razorpay checkout...', 'info');
                                try {
                                    const response = await fetch('/checkout/create-order', {
                                        method: 'POST',
                                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                        body: new URLSearchParams(bodyParams)
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
                                                _csrf: csrf,
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
                        togglePaymentMethod();
                        </script>
                    <?php else: ?>
                        <div class="payment-status payment-status--unavailable"><strong>Online payment temporarily unavailable</strong><span>Your order details are safe, but checkout cannot take payment until the live gateway is connected.</span></div>
                    <?php endif; ?>
                </div>
                <div class="checkout-summary reveal">
                    <h2>Order Review</h2>
                    <?php foreach($items as $item): ?>
                        <div class="checkout-item">
                            <img class="checkout-item__img" src="<?= e(webp_src($item['product']['image_url'] ?? placeholder_img($item['product']['name']))) ?>" alt="<?= e($item['product']['name']) ?>">
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
                    <?php
                    $gstConfigured = !empty($settings['gstin']);
                    $anyGstRate = false;
                    foreach ($items as $item) {
                        if (!empty($item['product']['gst_rate']) && (float)$item['product']['gst_rate'] > 0) {
                            $anyGstRate = true; break;
                        }
                    }
                    ?>
                    <div style="margin-top:var(--space-lg); padding-top:var(--space-md); border-top:1px solid var(--color-border);">
                        <h4 style="margin:0 0 var(--space-sm); font-size:0.9rem;">Tax Information</h4>
                        <?php if ($gstConfigured && $anyGstRate): ?>
                            <div style="font-size:0.85rem; color:var(--color-text-muted); display:grid; gap:var(--space-2xs);">
                                <span>Tax inclusive pricing</span>
                                <span>GST will be calculated at payment confirmation based on your delivery state.</span>
                            </div>
                        <?php elseif ($gstConfigured): ?>
                            <div style="font-size:0.85rem; color:var(--color-text-muted); display:grid; gap:var(--space-2xs);">
                                <span>GST will be calculated at payment confirmation.</span>
                            </div>
                        <?php else: ?>
                            <div style="font-size:0.85rem; color:var(--color-text-muted); display:grid; gap:var(--space-2xs);">
                                <span>GST not configured for this store.</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($gstConfigured && !empty($settings['gstin'])): ?>
                            <div style="font-size:0.75rem; color:var(--color-text-muted); margin-top:var(--space-xs);">
                                GSTIN: <?= e($settings['gstin']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>
