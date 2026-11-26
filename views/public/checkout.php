<section class="section checkout-page" aria-labelledby="checkout-title">
    <div class="container">
        <nav class="breadcrumb breadcrumb--page" aria-label="Breadcrumb">
            <a href="/shop">Shop</a><span aria-hidden="true">/</span><a href="/cart">Cart</a><span aria-hidden="true">/</span><span aria-current="page">Checkout</span>
        </nav>
        <header class="checkout-page__header">
            <h1 id="checkout-title">Checkout</h1>
            <?php if ($items): ?>
                <a class="checkout-page__review" href="#checkout-order-review">Review order <span aria-hidden="true">↓</span></a>
            <?php endif; ?>
        </header>
        <?php if (!$items): ?>
            <div class="cart-empty-state">
                <h2>Your cart is empty</h2>
                <p>Add a product to your cart to continue to checkout.</p>
                <a href="/shop" class="btn btn-primary">Browse Shop</a>
            </div>
        <?php else: ?>
            <div class="checkout-layout">
                <div class="checkout-form reveal">
                    <?php // A failed payment used to be a toast that disappeared before it could
                          // be read, leaving the shopper on a full cart with no idea what happened. ?>
                    <div class="pay-failed" id="pay-failed" role="alert" aria-live="assertive">
                        <h2 id="pay-failed-title">Payment needs attention</h2>
                        <p id="pay-failed-reason">We could not confirm the payment result.</p>
                        <p id="pay-failed-next">Your cart is still available. Check your payment status before trying again.</p>
                        <div class="pay-failed__actions">
                            <button type="button" class="btn btn-primary btn-sm" id="pay-retry">Try payment again</button>
                            <a href="/contact" class="btn btn-sm btn-ghost">Contact us</a>
                        </div>
                    </div>
                    <div class="checkout-form__section">
                        <h2 class="checkout-form__section-title">Shipping details</h2>
                        <div class="checkout-form__row">
                            <div class="form-group">
                                <label for="checkout-name">Full Name</label>
                                <input type="text" id="checkout-name" name="name" autocomplete="section-delivery shipping name" value="<?= e($_SESSION['user']['name'] ?? '') ?>" placeholder="Your full name" required>
                            </div>
                            <div class="form-group">
                                <label for="checkout-email">Email</label>
                                <input type="email" id="checkout-email" name="email" autocomplete="section-delivery shipping email" value="<?= e($_SESSION['user']['email'] ?? '') ?>" placeholder="your@email.com" required>
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
                            <label for="checkout-phone">Phone</label>
                            <input type="tel" id="checkout-phone" name="phone" autocomplete="section-delivery shipping tel" value="<?= e($_SESSION['user']['phone'] ?? '') ?>" placeholder="+91 XXXXX XXXXX" required>
                        </div>
                        <div class="form-group" style="margin-top:var(--space-md);">
                            <label for="checkout-address">Address</label>
                            <textarea id="checkout-address" name="address" autocomplete="section-delivery shipping street-address" placeholder="Door no, Street, Area" required rows="2"></textarea>
                        </div>
                        <div class="checkout-form__row" style="margin-top:var(--space-md);">
                            <div class="form-group">
                                <label for="checkout-city">City</label>
                                <input type="text" id="checkout-city" name="city" autocomplete="section-delivery shipping address-level2" placeholder="City" required>
                            </div>
                            <div class="form-group">
                                <label for="checkout-pincode">PIN Code</label>
                                <input type="text" id="checkout-pincode" name="pincode" autocomplete="section-delivery shipping postal-code" inputmode="numeric" placeholder="600001" required>
                            </div>
                        </div>
                        <div class="form-group" style="margin-top:var(--space-md);">
                            <label for="checkout-state">State / Union Territory</label>
                            <select id="checkout-state" name="state" required autocomplete="section-delivery shipping address-level1">
                                <option value="">Select delivery state</option>
                                <?php foreach (\App\Services\TaxService::states() as $state): ?>
                                    <option value="<?= e($state) ?>"><?= e($state) ?></option>
                                <?php endforeach; ?>
                            </select>
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
                        <label for="coupon-code">Coupon Code</label>
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
                    <fieldset id="payment-method-toggle" class="checkout-payment-options">
                        <legend>Payment method</legend>
                        <?php if ($hasRazorpay): ?>
                        <label>
                            <input type="radio" name="payment_method" value="razorpay" <?= $defaultPaymentMethod === 'razorpay' ? 'checked' : '' ?> onchange="togglePaymentMethod()"> <span>Razorpay<?php if (($secrets['razorpay_mode'] ?? 'test') === 'test'): ?> <small>Test mode</small><?php endif; ?></span>
                        </label>
                        <?php endif; ?>
                        <?php if ($hasStripe): ?>
                        <label>
                            <input type="radio" name="payment_method" value="stripe" <?= $defaultPaymentMethod === 'stripe' ? 'checked' : '' ?> onchange="togglePaymentMethod()"> Stripe
                        </label>
                        <?php endif; ?>
                    </fieldset>
                        <button type="button" id="pay-now" class="btn btn-primary btn-block btn-lg">Pay ₹<?= e((string)$total) ?></button>
                        <p style="margin:var(--space-sm) 0 0; color:var(--color-text-muted); font-size:0.85rem;">Product orders use secure online card or UPI payment. Cash on delivery is not available.</p>
                        <?php if($hasRazorpay): ?>
                        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
                        <?php endif; ?>
                        <script>
                        // Shown in place of a toast: a payment failure is the one message a
                        // shopper must be able to read at their own pace and act on.
                        function showPaymentFailure(reason, state = 'failed') {
                            var panel = document.getElementById('pay-failed');
                            if (!panel) return;
                            var pending = state === 'pending' || state === 'verified';
                            var title = document.getElementById('pay-failed-title');
                            var line = document.getElementById('pay-failed-reason');
                            var next = document.getElementById('pay-failed-next');
                            var retry = document.getElementById('pay-retry');
                            if (title) title.textContent = state === 'verified' ? 'Payment verified; order confirmation pending'
                                : pending ? 'Payment confirmation needs checking' : 'Payment was not completed';
                            if (line) line.textContent = reason || 'We could not confirm the payment result.';
                            if (next) next.textContent = pending
                                ? 'Do not make another payment. Check this same payment again, or contact us if confirmation remains unavailable.'
                                : 'Your cart is still available. Check your bank or payment app before trying payment again.';
                            if (retry) retry.textContent = pending ? 'Check payment status' : 'Try payment again';
                            panel.classList.add('is-open');
                            const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
                            panel.scrollIntoView({behavior: reducedMotion ? 'instant' : 'smooth', block: 'center'});
                        }
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
                            ['name', 'phone', 'address', 'city', 'pincode', 'state'].forEach(function (field) {
                                const input = document.querySelector('[name="' + field + '"]');
                                if (!input) return;
                                const value = data[field === 'name' ? 'recipient_name' : field];
                                if (field === 'state') input.value = value || '';
                                else if (value !== undefined) input.value = value;
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
                            const retry = document.getElementById('pay-retry');
                            let pendingVerification = null;
                            let verifying = false;
                            // Provider callbacks run after the outer click handler has returned.
                            // A failed verification must retry this payment, never create another one.
                            const verifyPayment = async () => {
                                if (!pendingVerification || verifying) return;
                                verifying = true;
                                button.disabled = true;
                                if (retry) retry.disabled = true;
                                let state = 'pending';
                                try {
                                    showToast('Verifying payment...', 'info');
                                    const response = await fetch('/payment/verify', {
                                        method: 'POST', body: new URLSearchParams(pendingVerification)
                                    });
                                    const result = await response.json();
                                    if (result?.payment_verified === true) state = 'verified';
                                    if (!response.ok || result?.verified !== true || typeof result.order_id !== 'string'
                                        || !/^[a-f0-9]{16}$/.test(result.order_id)) {
                                        throw new Error(result?.error || 'Payment confirmation is not available yet.');
                                    }
                                    window.location.href = '/account/orders/' + encodeURIComponent(result.order_id) + '?placed=1';
                                } catch (error) {
                                    showPaymentFailure(error.message || 'Payment confirmation is not available yet.', state);
                                } finally {
                                    verifying = false;
                                    if (retry) retry.disabled = false;
                                }
                            };
                            if (retry) retry.addEventListener('click', () => {
                                if (pendingVerification) { void verifyPayment(); return; }
                                document.getElementById('pay-failed').classList.remove('is-open');
                                button.disabled = false;
                                button.click();
                            });
                            button.addEventListener('click', async () => {
                                if (pendingVerification) { await verifyPayment(); return; }
                                const method = document.querySelector('input[name="payment_method"]:checked');
                                const paymentMethod = method ? method.value : 'razorpay';
                                const fields = ['name', 'email', 'phone', 'address', 'city', 'pincode', 'state'];
                                for (const field of fields) {
                                    if (!form.querySelector(`[name="${field}"]`).reportValidity()) return;
                                }
                                button.disabled = true;
                                const csrf = document.getElementById('csrf-token').value;
                                const bodyParams = {
                                    _csrf: csrf,
                                    payment_method: paymentMethod,
                                    coupon_code: document.getElementById('coupon-code').value,
                                    name: form.querySelector('[name="name"]').value,
                                    email: form.querySelector('[name="email"]').value,
                                    phone: form.querySelector('[name="phone"]').value,
                                    address: form.querySelector('[name="address"]').value,
                                    city: form.querySelector('[name="city"]').value,
                                    pincode: form.querySelector('[name="pincode"]').value,
                                    state: form.querySelector('[name="state"]').value,
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
                                                if (pendingVerification) return;
                                                button.disabled = false;
                                                showToast('Payment was cancelled before completion.', 'info');
                                            }
                                        },
                                        handler: async (resp) => {
                                            pendingVerification = {
                                                _csrf: csrf,
                                                razorpay_order_id: order.order_id,
                                                razorpay_payment_id: resp.razorpay_payment_id,
                                                razorpay_signature: resp.razorpay_signature
                                            };
                                            await verifyPayment();
                                        }
                                    });
                                    razorpay.on('payment.failed', (event) => {
                                        if (pendingVerification) return;
                                        button.disabled = false;
                                        const reason = event.error && event.error.description ? event.error.description : '';
                                        showPaymentFailure(reason);
                                    });
                                    razorpay.open();
                                } catch (error) {
                                    button.disabled = false;
                                    showPaymentFailure(error.message || '');
                                }
                            });
                        })();
                        togglePaymentMethod();
                        </script>
                    <?php else: ?>
                        <div class="payment-status payment-status--unavailable"><strong>Online payment is temporarily unavailable</strong><span>Try again later or <a href="/contact">contact us</a> for help.</span></div>
                    <?php endif; ?>
                </div>
                <section class="checkout-summary reveal" aria-labelledby="checkout-order-review">
                    <header class="checkout-summary__header">
                        <h2 id="checkout-order-review" tabindex="-1">Order review</h2>
                        <a href="/cart">Edit cart</a>
                    </header>
                    <?php foreach($items as $item): ?>
                        <?php
                        $placeholder = placeholder_img($item['product']['name']);
                        $image = trim((string)($item['product']['image_url'] ?? '')) ?: $placeholder;
                        ?>
                        <div class="checkout-item">
                            <img class="checkout-item__img" src="<?= e(webp_src($image)) ?>" data-product-image-fallback="<?= e($placeholder) ?>" alt="" width="60" height="60">
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
                    // Resolve the effective rate the same way TaxService will at payment,
                    // so the customer sees the real number here instead of a vague promise.
                    $gstRate = 0.0;
                    foreach ($items as $item) {
                        $gstRate = \App\Services\TaxService::rateFor($item['product'] ?? [], $settings);
                        if ($gstRate > 0) break;
                    }
                    $anyGstRate = $gstRate > 0;
                    $gstAmount = $anyGstRate ? round($total - ($total / (1 + $gstRate / 100)), 2) : 0.0;
                    $gstRateLabel = rtrim(rtrim(number_format($gstRate, 2, '.', ''), '0'), '.');
                    ?>
                    <?php if ($anyGstRate): ?>
                    <div class="checkout-summary__row" style="display:flex; justify-content:space-between;">
                        <span>GST (<?= e($gstRateLabel) ?>% incl.)</span>
                        <span>₹<?= e(number_format($gstAmount, 2)) ?></span>
                    </div>
                    <?php endif; ?>
                    <div style="margin-top:var(--space-lg); padding-top:var(--space-md); border-top:1px solid var(--color-border);">
                        <h3 style="margin:0 0 var(--space-sm); font-size:0.9rem;">Tax information</h3>
                        <?php if ($gstConfigured && $anyGstRate): ?>
                            <div style="font-size:0.85rem; color:var(--color-text-muted); display:grid; gap:var(--space-2xs);">
                                <span>Prices include GST at <?= e($gstRateLabel) ?>% — ₹<?= e(number_format($gstAmount, 2)) ?> of this order.</span>
                                <span>Your delivery state decides the CGST/SGST or IGST split on the invoice.</span>
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
                </section>
            </div>
        <?php endif; ?>
    </div>
</section>
