<section class="admin-card" aria-labelledby="shipping-preview-title">
    <h1 id="shipping-preview-title">Shipping zone preview</h1>
    <p>Test a proposed delivery rule with a hypothetical subtotal. These rules are not saved and do not change storefront, agent checkout, GST or payment totals.</p>
    <p><a href="/admin/settings#settings-shipping">Existing shipping settings</a></p>
    <?php if (!empty($error)): ?><p role="alert"><?= e($error) ?></p><?php endif; ?>
    <?php if (isset($quote)): ?>
        <section class="admin-card" aria-labelledby="shipping-preview-result">
            <h2 id="shipping-preview-result">Preview result</h2>
            <?php if ($quote['status'] === 'quoted'): ?>
                <p>Zone <?= e((string)$quote['zone']) ?> · Hypothetical delivery fee: <strong>₹<?= e(number_format($quote['feePaise']/100,2)) ?></strong></p>
            <?php elseif ($quote['status'] === 'minimum_not_met'): ?>
                <p>Zone <?= e((string)$quote['zone']) ?> matched, but the minimum subtotal was not met. No fee was quoted.</p>
            <?php else: ?><p>No zone covers the selected delivery address. No fee was quoted.</p><?php endif; ?>
            <p>This is a dry run, not a customer shipping promise or payable quote.</p>
        </section>
    <?php endif; ?>
    <form class="admin-form" method="post" action="/admin/shipping/preview">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <h2>Ordered zones</h2>
        <p>First matching zone wins. Leave unused zones blank. A blank PIN list covers the entire selected state. Use commas between six-digit PINs or 2–5 digit prefixes ending in *.</p>
        <?php for ($i=0;$i<\App\Services\ShippingService::MAX_ZONES;$i++): $z=$form['zones'][$i]??[]; ?>
        <fieldset class="admin-card" style="min-width:0">
            <legend>Zone <?= $i+1 ?></legend>
            <div class="admin-form__row">
                <div class="form-group"><label for="shipping-state-<?= $i ?>">State</label><select id="shipping-state-<?= $i ?>" name="zones[<?= $i ?>][state]"><option value="">Unused</option>
                    <?php foreach (\App\Services\TaxService::states() as $state): ?><option value="<?= e($state) ?>" <?= ($z['state']??'')===$state?'selected':'' ?>><?= e($state) ?></option><?php endforeach; ?>
                </select></div>
                <label>PIN patterns<input name="zones[<?= $i ?>][pins]" maxlength="500" value="<?= e($z['pins']??'') ?>" placeholder="600001, 641*"></label>
                <div class="form-group"><label for="shipping-method-<?= $i ?>">Method</label><select id="shipping-method-<?= $i ?>" name="zones[<?= $i ?>][method]"><option value="flat">Flat rate</option><option value="free" <?= ($z['method']??'')==='free'?'selected':'' ?>>Free shipping</option></select></div>
            </div>
            <div class="admin-form__row">
                <label>Flat fee (₹)<input name="zones[<?= $i ?>][fee]" inputmode="decimal" maxlength="10" value="<?= e($z['fee']??'') ?>"></label>
                <label>Minimum subtotal for free shipping (₹)<input name="zones[<?= $i ?>][minimum]" inputmode="decimal" maxlength="10" value="<?= e($z['minimum']??'') ?>"></label>
            </div>
        </fieldset>
        <?php endfor; ?>
        <h2>Hypothetical delivery</h2>
        <div class="admin-form__row">
            <div class="form-group"><label for="shipping-delivery-state">Delivery state</label><select id="shipping-delivery-state" name="state" required><option value="">Select state</option>
                <?php foreach (\App\Services\TaxService::states() as $state): ?><option value="<?= e($state) ?>" <?= ($form['state']??'')===$state?'selected':'' ?>><?= e($state) ?></option><?php endforeach; ?>
            </select></div>
            <label>Delivery PIN<input name="pin" inputmode="numeric" maxlength="6" pattern="[1-9][0-9]{5}" value="<?= e($form['pin']??'') ?>" required></label>
            <label>Hypothetical item subtotal (₹)<input name="subtotal" inputmode="decimal" maxlength="10" value="<?= e($form['subtotal']??'') ?>" required></label>
        </div>
        <button class="btn btn-primary" type="submit">Preview shipping fee</button>
    </form>
</section>
