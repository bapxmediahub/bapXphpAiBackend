<?php $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16)); ?>
<section class="section account-page">
    <div class="account-layout">
        <?php require __DIR__ . '/_nav.php'; ?>
        <div class="account-content">
            <header class="account-page__header">
                <div>
                    <h1>Saved Addresses</h1>
                    <p>Manage the delivery addresses available at checkout.</p>
                </div>
                <?php if (!$editing && $addresses !== []): ?>
                    <a class="btn btn-primary" href="#address-form">Add an address</a>
                <?php endif; ?>
            </header>

            <?php if ($addresses === []): ?>
                <p class="account-page__notice">You have no saved addresses yet.</p>
            <?php else: ?>
                <div class="account-addresses">
                    <?php foreach ($addresses as $address): ?>
                        <?php $addressLabel = $address['name'] ?? 'Saved address'; ?>
                        <section class="account-address">
                            <div>
                                <div class="account-address__name">
                                    <h2><?= e($addressLabel) ?></h2>
                                    <?php if (!empty($address['is_default'])): ?>
                                        <span class="badge badge--default">Default at checkout</span>
                                    <?php endif; ?>
                                </div>
                                <p>
                                    <?= e($address['recipient_name'] ?? '') ?><br>
                                    <?= e($address['address'] ?? '') ?><br>
                                    <?= e(trim(($address['city'] ?? '') . ' ' . ($address['state'] ?? '') . ' ' . ($address['pincode'] ?? ''))) ?>
                                </p>
                            </div>
                            <form class="account-page__actions" method="post" action="/account/dashboard/addresses/delete" onsubmit="return confirm('Remove this saved address?')">
                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="address_id" value="<?= e($address['id']) ?>">
                                <a class="btn btn-outline" href="?edit=<?= e(rawurlencode($address['id'])) ?>#address-form" aria-label="Edit <?= e($addressLabel) ?>">Edit</a>
                                <button class="btn btn-ghost" type="submit" aria-label="Remove <?= e($addressLabel) ?>">Remove</button>
                            </form>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form id="address-form" class="account-form" tabindex="-1" aria-labelledby="address-form-title" method="post" action="/account/dashboard/addresses/save">
                <h2 id="address-form-title"><?= $editing ? 'Edit address' : 'Add an address' ?></h2>
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="address_id" value="<?= e($editing['id'] ?? '') ?>">
                <div class="account-form__fields">
                    <div class="form-group">
                        <label for="address-name">Address label</label>
                        <input id="address-name" name="address_name" value="<?= e($editing['name'] ?? '') ?>" placeholder="Home or work" required>
                    </div>
                    <div class="form-group">
                        <label for="recipient-name">Recipient name</label>
                        <input id="recipient-name" name="name" autocomplete="name" value="<?= e($editing['recipient_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group account-form__wide">
                        <label for="street-address">Street address</label>
                        <textarea id="street-address" name="address" autocomplete="street-address" required><?= e($editing['address'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="address-city">City</label>
                        <input id="address-city" name="city" autocomplete="address-level2" value="<?= e($editing['city'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="address-pin">PIN code</label>
                        <input id="address-pin" name="pincode" inputmode="numeric" autocomplete="postal-code" value="<?= e($editing['pincode'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="address-state">State or union territory</label>
                        <select id="address-state" name="state" autocomplete="address-level1" required>
                            <option value="">Select a state</option>
                            <?php foreach ($states as $state): ?>
                                <option value="<?= e($state) ?>"<?= ($editing['state'] ?? '') === $state ? ' selected' : '' ?>><?= e($state) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="address-phone">Phone (optional)</label>
                        <input id="address-phone" name="phone" type="tel" autocomplete="tel" value="<?= e($editing['phone'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-check">
                    <label class="checkbox-label"><input type="checkbox" name="is_default" value="1"<?= !empty($editing['is_default']) ? ' checked' : '' ?>> Use by default at checkout</label>
                </div>
                <div class="account-page__actions">
                    <button class="btn btn-primary" type="submit">Save address</button>
                    <?php if ($editing): ?><a class="btn btn-outline" href="/account/dashboard/addresses">Cancel</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
(() => {
    const focusAddressForm = () => {
        if (window.location.hash === '#address-form') {
            document.getElementById('address-form').focus({preventScroll: true});
        }
    };
    focusAddressForm();
    window.addEventListener('hashchange', focusAddressForm);
})();
</script>
