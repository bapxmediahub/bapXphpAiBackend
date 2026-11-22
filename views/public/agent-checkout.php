<?php
$cart=$preview['cart']??null;
$canContinue=$cart!==null&&($cart['status']==='open'||$preview['alreadyImported'])&&$cart['items']!==[]
    &&$cart['unavailableSlugs']===[]&&is_numeric($cart['estimatedTotalInr'])&&$cart['estimatedTotalInr']>0;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <title>Review your cart · Sri Panchami Spiritual</title>
    <link rel="stylesheet" href="/assets/css/band.css">
</head>
<body>
<main class="section cart-page" aria-labelledby="cart-title">
    <div class="container cart-page__container">
        <nav class="breadcrumb breadcrumb--page" aria-label="Breadcrumb"><a href="/">Sri Panchami Spiritual</a><span aria-hidden="true">/</span><span>Cart review</span></nav>
        <header class="cart-page__header"><div><h1 id="cart-title">Review your cart</h1><p>Check the selections prepared by your assistant before continuing.</p></div></header>
        <?php if($error!==null): ?><p role="alert"><?= e($error) ?></p><?php endif; ?>
        <?php if($cart===null): ?>
            <div class="cart-empty-state"><h2>Review link unavailable</h2><p>Ask your assistant for a fresh checkout link, or continue with your current cart.</p><a class="btn btn-primary" href="/cart">View current cart</a></div>
        <?php else: ?>
            <div class="cart-layout">
                <section aria-labelledby="cart-items-title">
                    <h2 id="cart-items-title" class="cart-page__section-title">Prepared selections</h2>
                    <div class="cart-items">
                    <?php foreach($cart['items'] as$item): ?>
                        <article class="cart-item">
                            <div class="cart-item__body">
                                <a class="cart-item__media" href="<?= e($item['path']) ?>" aria-label="View <?= e($item['name']) ?>">
                                    <?php if(isset($item['imagePath'])): ?><img class="cart-item__img" src="<?= e($item['imagePath']) ?>" alt="" width="76" height="76" referrerpolicy="no-referrer"><?php else: ?><span>View product</span><?php endif; ?>
                                </a>
                                <h3 class="cart-item__name"><a href="<?= e($item['path']) ?>"><?= e($item['name']) ?></a></h3>
                            </div>
                            <div class="cart-item__actions"><span>Qty <?= (int)$item['quantity'] ?> · ₹<?= e(number_format($item['unitPriceInr'],2)) ?> each</span><strong class="cart-item__price">₹<?= e(number_format($item['lineTotalInr'],2)) ?></strong></div>
                            <?php if(!$item['available']): ?><p>Currently unavailable. Remove this selection through your assistant before checkout.</p><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                    </div>
                    <?php if($cart['unavailableSlugs']!==[]): ?><p role="status">Some selections are no longer available. Ask your assistant to remove them and prepare a new review link.</p><?php endif; ?>
                </section>
                <section class="cart-summary" aria-labelledby="cart-summary-title">
                    <h2 id="cart-summary-title">Your review</h2>
                    <div class="cart-summary__row"><span>Prepared quantity</span><span><?= (int)$cart['itemCount'] ?></span></div>
                    <div class="cart-summary__row cart-summary__row--total"><span>Estimated total</span><span><?= $cart['estimatedTotalInr']===null?'Unavailable':'₹'.e(number_format($cart['estimatedTotalInr'],2)) ?></span></div>
                    <p class="cart-summary__delivery">Product GST is included. Final delivery and discounts are reviewed at checkout. No order, payment or stock reservation has been created.</p>
                    <?php if($alreadyImported): ?>
                        <p>This prepared cart has already been imported in this browser. Continue with your current cart; this link will not replace it again.</p>
                        <a class="btn btn-primary btn-block" href="/checkout">Continue to checkout</a>
                    <?php elseif($canContinue): ?>
                        <p>This will replace your current browser cart<?= $browserItemCount>0?' ('.(int)$browserItemCount.' item'.($browserItemCount===1?'':'s').')':'' ?> with the selections shown here.</p>
                        <form method="post" action="/cart/agent-checkout">
                            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="ticket" value="<?= e($ticket) ?>">
                            <input type="hidden" name="quote" value="<?= e($preview['quote']) ?>">
                            <p><label><input type="checkbox" name="confirm" value="replace" required> Replace my current cart with these selections.</label></p>
                            <button type="submit" class="btn btn-primary btn-block">Use this cart</button>
                        </form>
                        <p class="cart-summary__delivery">Then continue to delivery and payment at checkout. This review link expires <?= e(gmdate('H:i \U\T\C',strtotime($preview['expiresAt']))) ?>.</p>
                    <?php else: ?><p>Ask your assistant to update this cart before continuing.</p><?php endif; ?>
                    <a class="cart-summary__continue" href="/cart">Keep my current cart</a>
                </section>
            </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
