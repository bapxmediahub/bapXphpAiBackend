<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow"><title>Continue payment · Sri Panchami Spiritual</title>
    <link rel="stylesheet" href="/assets/css/band.css">
</head>
<body>
<main class="container" aria-labelledby="payment-title">
    <h1 id="payment-title">Your confirmed checkout</h1>
    <?php if($error!==null): ?>
        <p role="alert"><?= e($error) ?></p>
        <p>Return to the chat and check this checkout’s payment status before starting another payment.</p>
    <?php elseif($payment!==null): ?>
        <p><strong>₹<?= e(number_format($payment['amountPaise']/100,2)) ?> INR</strong></p>
        <?php if($payment['mode']==='test'): ?><p><strong>Test payment mode</strong></p><?php endif; ?>
        <p><?= e($payment['notice']) ?></p>
        <?php if($payment['providerUrl']!==null): ?>
            <p>Delivery details and order review are already complete. Continue only if you want to make this payment with Razorpay. Payment credentials belong on the provider’s secure page.</p>
            <p><a class="btn btn-primary" href="<?= e($payment['providerUrl']) ?>" target="_blank" rel="noopener noreferrer">Continue to Razorpay</a></p>
            <p>This payment link expires at <time datetime="<?= e($payment['expiresAt']) ?>"><?= e($payment['expiresAt']) ?></time>.</p>
        <?php endif; ?>
        <p>After paying, return to the chat to check verified payment and order status. Closing this page does not stop server-side payment verification.</p>
    <?php endif; ?>
    <p><a href="/contact" rel="noreferrer">Contact the store</a></p>
</main>
</body>
</html>
