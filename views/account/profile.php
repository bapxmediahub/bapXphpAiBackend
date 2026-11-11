<?php
$csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16));
$picture = (string)($profile['picture'] ?? '');
$showPicture = filter_var($picture, FILTER_VALIDATE_URL) && parse_url($picture, PHP_URL_SCHEME) === 'https';
?>
<section class="section">
    <div class="account-layout">
        <?php require __DIR__ . '/_nav.php'; ?>
        <div class="account-content">
            <h1>My Profile</h1>
            <?php if ($showPicture): ?>
                <img src="<?= e($picture) ?>" alt="" width="56" height="56" referrerpolicy="no-referrer">
            <?php else: ?>
                <span aria-hidden="true"><?= e(mb_strtoupper(mb_substr($profile['name'] ?? '', 0, 1))) ?></span>
            <?php endif; ?>
            <p><?= e($profile['email'] ?? '') ?></p>
            <form method="post" class="form-group" action="/account/dashboard/profile">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <label for="profile-name">Name</label>
                <input id="profile-name" name="name" autocomplete="name" maxlength="100" required value="<?= e($profile['name'] ?? '') ?>">
                <label for="profile-phone">Phone</label>
                <input id="profile-phone" name="phone" type="tel" autocomplete="tel" maxlength="25" value="<?= e($profile['phone'] ?? '') ?>">
                <label for="profile-gender">Gender (optional)</label>
                <select id="profile-gender" name="gender">
                    <option value="">Select</option>
                    <?php foreach (['female' => 'Female', 'male' => 'Male', 'other' => 'Other', 'prefer_not_to_say' => 'Prefer not to say'] as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= ($profile['gender'] ?? '') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="profile-birth">Date of birth (optional)</label>
                <input id="profile-birth" name="date_of_birth" type="date" autocomplete="bday" max="<?= e(date('Y-m-d')) ?>" value="<?= e($profile['date_of_birth'] ?? '') ?>">
                <div><button class="btn btn-primary" type="submit">Save profile</button></div>
            </form>
            <p><a href="/forgot-password">Reset account password</a></p>
        </div>
    </div>
</section>
