<section class="section auth-inline-page">
    <div class="auth-card">
        <span class="eyebrow">Secure Your Account</span>
        <h1>Change temporary password</h1>
        <p>Your administrator created this account. Choose a private password before opening the astrologer workspace.</p>
        <form method="post" action="/astrologer/change-password" class="auth-form">
            <div class="form-group"><label for="astrologer-new-password">New password</label><input id="astrologer-new-password" type="password" name="password" minlength="10" autocomplete="new-password" required></div>
            <div class="form-group"><label for="astrologer-confirm-password">Confirm password</label><input id="astrologer-confirm-password" type="password" name="password_confirm" minlength="10" autocomplete="new-password" required></div>
            <button class="btn btn-primary btn-block">Save Password</button>
        </form>
    </div>
</section>
