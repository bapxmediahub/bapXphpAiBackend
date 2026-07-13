<?php
$messageRate = $messageRate ?? 5;
$callRate = $callRate ?? 0.5;
?>
<aside class="consultation-pricing-card" aria-label="Consultation pricing">
    <div>
        <span class="eyebrow">Simple credit pricing</span>
        <h2>One clear rate for every consultant</h2>
        <p>Use wallet credits for private guidance by message or live call. Rates are shown before you start.</p>
    </div>
    <div class="consultation-pricing-card__rates">
        <div><strong><?= e((string)$messageRate) ?></strong><span>credits/message</span></div>
        <div><strong><?= e((string)$callRate) ?></strong><span>credits/sec call</span></div>
    </div>
</aside>
