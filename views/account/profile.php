<?php
$csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16));
$email = (string)($profile['email'] ?? '');
$name = trim((string)($profile['name'] ?? ''));
$initial = mb_strtoupper(mb_substr($name !== '' ? $name : $email, 0, 1));
$picture = (string)($profile['picture'] ?? '');
$showPicture = filter_var($picture, FILTER_VALIDATE_URL) && parse_url($picture, PHP_URL_SCHEME) === 'https';
?>
<section class="section">
    <div class="account-layout">
        <?php require __DIR__ . '/_nav.php'; ?>
        <div class="account-content">
            <header class="account-profile__identity">
                <div class="account-profile__avatar" aria-hidden="true">
                    <?= e($initial) ?>
                    <?php if ($showPicture): ?>
                        <img id="account-profile-photo" src="<?= e($picture) ?>" alt="" width="56" height="56" referrerpolicy="no-referrer" hidden>
                    <?php endif; ?>
                </div>
                <div>
                    <h1 class="account-profile__heading">My Profile</h1>
                    <p><?= e($email) ?></p>
                </div>
            </header>
            <?php if ($showPicture): ?>
                <script>
                (() => {
                    const photo = document.getElementById('account-profile-photo');
                    const reveal = () => {
                        if (photo.naturalWidth > 0) photo.hidden = false;
                    };
                    photo.addEventListener('load', reveal, {once: true});
                    if (photo.complete) reveal();
                })();
                </script>
            <?php endif; ?>
            <div data-section-index hidden></div>
            <h2 id="profile-details" data-page-section="Profile details" tabindex="-1">Profile details</h2>
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
            <?php if (!empty($hasPassword)): ?>
                <form method="post" class="form-group" action="/account/dashboard/profile/password">
                    <h2 id="profile-credentials" data-page-section="Change password" tabindex="-1">Change password</h2>
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <label for="current-password">Current password</label>
                    <input id="current-password" name="current_password" type="password" autocomplete="current-password" required>
                    <label for="new-password">New password</label>
                    <input id="new-password" name="new_password" type="password" autocomplete="new-password" minlength="8" required>
                    <label for="confirm-password">Confirm new password</label>
                    <input id="confirm-password" name="confirm_password" type="password" autocomplete="new-password" minlength="8" required>
                    <div id="profile-password-visibility" hidden>
                        <div class="form-check">
                            <label class="checkbox-label" style="min-height:44px"><input id="profile-show-passwords" type="checkbox"> Show new passwords</label>
                        </div>
                    </div>
                    <div><button class="btn btn-primary" type="submit">Change password</button></div>
                </form>
                <script>
                (() => {
                    const toggle = document.getElementById('profile-show-passwords');
                    const newPasswords = toggle.form.querySelectorAll('[autocomplete="new-password"]');
                    const updateVisibility = () => {
                        for (const field of newPasswords) field.type = toggle.checked ? 'text' : 'password';
                    };
                    toggle.addEventListener('change', updateVisibility);
                    updateVisibility();
                    document.getElementById('profile-password-visibility').hidden = false;
                })();
                </script>
            <?php endif; ?>
            <p><a href="/forgot-password">Reset account password</a></p>
        </div>
    </div>
</section>
