<?php
use App\Services\{CampaignPaymentService, RazorpayPaymentLinkService};

$captured = ($booking['status'] ?? null) === 'captured';
$deadlinePassed = $booking !== null && $booking['payment_expires_at'] <= time();
$currentId = $family['current']['id'] ?? null;
$isCurrent = $booking !== null && $currentId === $booking['id'];
$capturedRows = $family['captured'] ?? [];
$blockedIds = $family['blocked_ids'] ?? [];
$blocked = ($family['blocked'] ?? true) === true;
$payable = $isCurrent && !$blocked && $capturedRows === [] && !$deadlinePassed
    && ($booking['status'] ?? null) === 'pending' && RazorpayPaymentLinkService::validUrl($booking['provider_url'] ?? null);
$canReplace = $isCurrent && !$blocked && $capturedRows === [] && ($family['can_replace'] ?? false) === true
    && is_string($csrf ?? null) && $csrf !== '';
$receiptPath = static fn(string $id): string => $pagePath.'/booking/'.rawurlencode($id);
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
                    <?php if (count($capturedRows) > 1): ?>
                        <p role="alert">More than one confirmed payment exists for this booking. Do not pay again. Contact the business to review the payments.</p>
                        <ul class="campaign-booking__downloads">
                            <?php foreach ($capturedRows as $other): if ($other['id'] === $booking['id']) continue; ?>
                                <li><a href="<?= e($receiptPath($other['id'])) ?>">Open other confirmed receipt</a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php elseif ($capturedRows): ?>
                    <p role="alert">A payment for this booking has already been confirmed. Do not pay again.</p>
                    <?php foreach ($capturedRows as $confirmed): ?>
                        <p><a href="<?= e($receiptPath($confirmed['id'])) ?>">Open confirmed booking receipt</a></p>
                    <?php endforeach; ?>
                <?php elseif ($blocked): ?>
                    <p role="alert">Payment evidence for this booking requires confirmation. Do not start another payment.</p>
                    <?php foreach ($blockedIds as $blockedId): if ($blockedId === $booking['id']) continue; ?>
                        <p><a href="<?= e($receiptPath($blockedId)) ?>">Check related payment receipt</a></p>
                    <?php endforeach; ?>
                <?php elseif (!$isCurrent): ?>
                    <p>This is an earlier booking receipt. Check the current booking before paying.</p>
                    <p><a href="<?= e($receiptPath($currentId)) ?>">Open current booking</a></p>
                <?php elseif ($payable): ?>
                    <p>Complete payment on Razorpay, then return here to check your booking.</p>
                    <p><a class="btn btn-primary" href="<?= e($booking['provider_url']) ?>" target="_blank" rel="noopener noreferrer">Open Razorpay in a new tab</a></p>
                <?php elseif ($booking['status'] === 'retryable'): ?>
                    <p>Payment setup is temporarily unavailable. Return to the campaign page to retry this booking.</p>
                <?php elseif ($booking['status'] === 'expired'): ?>
                    <p>This payment link has expired. If you already paid, check here for confirmation.</p>
                <?php elseif ($booking['status'] === 'cancelled'): ?>
                    <p>The provider confirmed that this payment link was cancelled. If you already paid, check here for confirmation.</p>
                <?php elseif ($deadlinePassed): ?>
                    <p>The payment window has ended, but the provider has not confirmed the outcome. Do not start another payment until the status is confirmed.</p>
                <?php else: ?>
                    <p>The payment provider has not confirmed this booking. Check its status again before paying.</p>
                <?php endif; ?>
                <?php if ($canReplace): ?>
                    <p>The provider confirmed this link is closed with no captured or outstanding payment evidence. A separate booking payment will preserve this receipt.</p>
                    <form method="post" action="<?= e($pagePath.'/booking') ?>">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="revision" value="<?= e($booking['revision']) ?>">
                        <input type="hidden" name="fresh_from" value="<?= e($booking['id']) ?>">
                        <button class="btn btn-primary" type="submit" name="confirm_fresh" value="1">Start a new booking payment</button>
                    </form>
                <?php elseif ($isCurrent && !$blocked && $capturedRows === [] && in_array($booking['status'], ['expired','cancelled'], true)): ?>
                    <?php if (($family['limit_reached'] ?? false) === true): ?>
                        <p>The booking attempt limit has been reached. Contact the business before starting another payment.</p>
                    <?php else: ?>
                        <p>Check payment status to verify whether a new booking is available.</p>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (!$captured && ($booking['status'] !== 'retryable' || $blocked)): ?><p><a href="<?= e($bookingPath) ?>">Check payment status</a></p><?php endif; ?>
            <?php endif; ?>
            <p><a href="<?= e($pagePath) ?>">Return to campaign page</a></p>
        </section>
    </div>
</main>
</body>
</html>
