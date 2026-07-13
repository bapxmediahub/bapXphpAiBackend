<div class="section" style="padding-top:var(--space-xl);">
    <?php $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16)); ?>
    <div class="account-layout">
        <?php require __DIR__ . '/_nav.php'; ?>
        <div class="account-content">
            <h1>Wallet Recharge</h1>
            <div class="wallet-panel">
                <div>
                    <span>Remaining Balance</span>
                    <strong><?= e((string)$balance) ?> credits</strong>
                    <small>1 rupee adds 20 credits. Credits are only for astrologer message and call sessions.</small>
                </div>
                <form method="get" action="/account/dashboard/wallet" class="wallet-recharge-form">
                    <label>Recharge Amount
                        <input type="number" name="amount" min="10" step="1" value="<?= e((string)$quote['amount_rupees']) ?>">
                    </label>
                    <button class="btn btn-ghost">Update Pricing</button>
                </form>
            </div>
            <div class="panel" style="margin-top:var(--space-lg);">
                <h2 style="font-family:var(--font-serif); margin-top:0;">Pricing Breakdown</h2>
                <div class="cart-summary__row"><span>Credit amount</span><span>₹<?= e((string)$quote['amount_rupees']) ?></span></div>
                <div class="cart-summary__row"><span>Service charge</span><span>₹<?= e((string)$quote['service_charge']) ?></span></div>
                <div class="cart-summary__row"><span>GST/tax estimate</span><span>₹<?= e((string)$quote['tax']) ?></span></div>
                <div class="cart-summary__row cart-summary__row--total"><span>Total payable</span><span>₹<?= e((string)$quote['total_rupees']) ?></span></div>
                <p style="color:var(--color-text-muted);">You will receive <?= e((string)$quote['credits']) ?> consultation credits after successful payment verification.</p>
                <?php if(!empty($razorpayReady)): ?>
                    <div class="payment-status payment-status--live"><strong>Live payment</strong><span>Secured by Razorpay</span></div>
                    <button id="wallet-pay-now" class="btn btn-primary">Pay securely with Razorpay</button>
                    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
                    <script>
                    document.getElementById('wallet-pay-now').onclick=async()=>{const amount='<?= (int)$quote['amount_rupees'] ?>';const csrf='<?= e($csrf) ?>';const r=await fetch('/account/dashboard/wallet/create-order',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({amount_rupees:amount,_csrf:csrf})});const order=await r.json();if(order.error){showToast(order.error,'error');return;}new Razorpay({key:'<?= e($secrets['razorpay_key_id']) ?>',amount:order.amount,currency:'INR',order_id:order.id,name:'Sri Panchami Spiritual Credits',handler:async(resp)=>{const vr=await fetch('/account/dashboard/wallet/verify',{method:'POST',body:new URLSearchParams({order_id:order.id,payment_id:resp.razorpay_payment_id,signature:resp.razorpay_signature,amount_rupees:amount,_csrf:csrf})});const result=await vr.json();showToast(result.verified?'Recharge complete.':'Payment verification failed.',result.verified?'success':'error');if(result.verified)window.location.href='/account/dashboard/wallet';}}).open();};
                    </script>
                <?php else: ?>
                    <div class="payment-status payment-status--unavailable"><strong>Online recharge temporarily unavailable</strong><span>No payment will be taken. Please contact support if you need credits added.</span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
