<?php
use App\Services\{CampaignPaymentService, RazorpayPaymentLinkService};

$captured = ($booking['status'] ?? null) === 'captured';
$expired = $booking !== null && ($booking['payment_expires_at'] <= time() || in_array($booking['status'], ['expired','cancelled'], true));
$payable = !$expired && ($booking['status'] ?? null) === 'pending' && RazorpayPaymentLinkService::validUrl($booking['provider_url'] ?? null);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Campaign booking</title>
    <link rel="stylesheet" href="/assets/css/band.css">
</head>
<body>
<main class="section">
    <div class="container document-page__content">
        <section class="contact-form-card campaign-enquiry" aria-labelledby="booking-title">
            <h1 id="booking-title" class="campaign-enquiry__title"><?= $captured ? 'Payment confirmed' : 'Campaign booking' ?></h1>
            <?php if ($booking === null): ?>
                <p role="alert"><?= e($error) ?></p>
            <?php else: ?>
                <p><?= e($booking['title']) ?></p>
                <p><strong>₹<?= e(CampaignPaymentService::rupees($booking['amount_paise'])) ?> INR</strong></p>
                <?php if ($booking['mode'] === 'test'): ?><p><strong>Test payment mode</strong></p><?php endif; ?>
                <?php if ($captured): ?>
                    <p>Booking reference <strong><?= e($booking['receipt']['id']) ?></strong></p>
                    <?php if ($booking['fulfillment']): ?>
                        <h2 class="campaign-enquiry__title">Your downloads</h2>
                        <ul class="campaign-booking__downloads">
                            <?php foreach ($booking['fulfillment'] as $file): ?>
                                <li><a href="<?= e($bookingPath.'/downloads/'.$file['id']) ?>"><?= e($file['name']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php elseif ($payable): ?>
                    <p>Complete payment on Razorpay, then return here to check your booking.</p>
                    <p><a class="btn btn-primary" href="<?= e($booking['provider_url']) ?>" target="_blank" rel="noopener noreferrer">Open Razorpay in a new tab</a></p>
                <?php elseif ($booking['status'] === 'retryable'): ?>
                    <p>Payment setup is temporarily unavailable. Return to the campaign page to retry this booking.</p>
                <?php elseif ($expired): ?>
                    <p>This payment link has expired. If you already paid, check here for confirmation.</p>
                <?php else: ?>
                    <p>The payment provider has not confirmed this booking. Check its status again before paying.</p>
                <?php endif; ?>
                <?php if (!$captured && $booking['status'] !== 'retryable'): ?><p><a href="<?= e($bookingPath) ?>">Check payment status</a></p><?php endif; ?>
            <?php endif; ?>
            <p><a href="<?= e($pagePath) ?>">Return to campaign page</a></p>
        </section>
    </div>
</main>
</body>
</html>
