<?php $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16)); ?>
<section class="section">
    <div class="account-layout">
        <?php require __DIR__ . '/_nav.php'; ?>
        <div class="account-content">
            <h1>Saved Addresses</h1>
            <?php if ($addresses === []): ?>
                <p>You have no saved addresses.</p>
            <?php endif; ?>
            <?php foreach ($addresses as $address): ?>
                <section>
                    <h2><?= e($address['name'] ?? 'Saved address') ?></h2>
                    <?php if (!empty($address['is_default'])): ?><p>Default at checkout</p><?php endif; ?>
                    <p><?= e($address['recipient_name'] ?? '') ?><br>
                       <?= e($address['address'] ?? '') ?><br>
                       <?= e(trim(($address['city'] ?? '') . ' ' . ($address['state'] ?? '') . ' ' . ($address['pincode'] ?? ''))) ?></p>
                    <form method="post" action="/account/dashboard/addresses/delete" onsubmit="return confirm('Remove this saved address?')">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="address_id" value="<?= e($address['id']) ?>">
                        <a class="btn btn-outline" href="?edit=<?= e(rawurlencode($address['id'])) ?>#address-form">Edit</a>
                        <button class="btn btn-outline" type="submit">Remove</button>
                    </form>
                </section>
            <?php endforeach; ?>
            <form id="address-form" class="form-group" method="post" action="/account/dashboard/addresses/save">
                <h2><?= $editing ? 'Edit address' : 'Add an address' ?></h2>
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="address_id" value="<?= e($editing['id'] ?? '') ?>">
                <label for="address-name">Address label</label>
                <input id="address-name" name="address_name" value="<?= e($editing['name'] ?? '') ?>" required>
                <label for="recipient-name">Recipient name</label>
                <input id="recipient-name" name="name" autocomplete="name" value="<?= e($editing['recipient_name'] ?? '') ?>" required>
                <label for="address-phone">Phone</label>
                <input id="address-phone" name="phone" type="tel" autocomplete="tel" value="<?= e($editing['phone'] ?? '') ?>">
                <label for="street-address">Street address</label>
                <textarea id="street-address" name="address" autocomplete="street-address" required><?= e($editing['address'] ?? '') ?></textarea>
                <label for="address-city">City</label>
                <input id="address-city" name="city" autocomplete="address-level2" value="<?= e($editing['city'] ?? '') ?>" required>
                <label for="address-state">State or union territory</label>
                <select id="address-state" name="state" autocomplete="address-level1" required>
                    <option value="">Select a state</option>
                    <?php foreach ($states as $state): ?>
                        <option value="<?= e($state) ?>"<?= ($editing['state'] ?? '') === $state ? ' selected' : '' ?>><?= e($state) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="address-pin">PIN code</label>
                <input id="address-pin" name="pincode" inputmode="numeric" autocomplete="postal-code" value="<?= e($editing['pincode'] ?? '') ?>" required>
                <div class="form-check"><label class="checkbox-label"><input type="checkbox" name="is_default" value="1"<?= !empty($editing['is_default']) ? ' checked' : '' ?>> Use by default at checkout</label></div>
                <div>
                    <button class="btn btn-primary" type="submit">Save address</button>
                    <?php if ($editing): ?><a class="btn btn-outline" href="/account/dashboard/addresses">Cancel</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</section>
