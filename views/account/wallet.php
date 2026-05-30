<div class="section" style="padding-top:var(--space-xl);">
    <div class="account-layout">
        <aside class="account-nav">
            <a href="/account/orders">My Orders</a>
            <a href="/account/bookings">My Sessions</a>
            <a href="/account/wallet" class="active">Wallet</a>
            <a href="/">Back to Home</a>
        </aside>
        <div class="account-content">
            <h1>Wallet Recharge</h1>
            <div class="wallet-panel">
                <div>
                    <span>Remaining Balance</span>
                    <strong><?= e((string)$balance) ?> credits</strong>
                    <small>1 rupee adds 20 credits. Credits are only for astrologer message and call sessions.</small>
                </div>
                <form method="get" action="/recharge" class="wallet-recharge-form">
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
                <?php if(!empty($secrets['razorpay_key_id'])): ?>
                    <button id="wallet-pay-now" class="btn btn-primary">Pay with Razorpay</button>
                    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
                    <script>
                    document.getElementById('wallet-pay-now').onclick=async()=>{const amount='<?= (int)$quote['amount_rupees'] ?>';const r=await fetch('/recharge/create-order',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({amount_rupees:amount})});const order=await r.json();if(order.error){alert(order.error);return;}new Razorpay({key:'<?= e($secrets['razorpay_key_id']) ?>',amount:order.amount,currency:'INR',order_id:order.id,name:'Sri Panchami Spiritual Credits',handler:async(resp)=>{const vr=await fetch('/recharge/verify',{method:'POST',body:new URLSearchParams({order_id:order.id,payment_id:resp.razorpay_payment_id,signature:resp.razorpay_signature,amount_rupees:amount})});const result=await vr.json();alert(result.verified?'Recharge complete.':'Payment verification failed.');if(result.verified)window.location.href='/account/wallet';}}).open();};
                    </script>
                <?php else: ?>
                    <div class="flash flash--info">Razorpay is not configured yet. The recharge form is ready, but the payment gateway keys must be saved in Admin Integrations.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
