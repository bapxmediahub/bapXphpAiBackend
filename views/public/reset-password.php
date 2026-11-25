<div class="auth-page">
    <div class="auth-form-container">
        <div class="auth-card">
            <h1>Choose a new password</h1>
            <p id="reset-password-requirements">Use at least 6 characters.</p>
            <form method="post" action="/reset-password" class="auth-form">
                <?php $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16)); ?>
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
                <div class="form-group">
                    <label for="reset-password">New password</label>
                    <input id="reset-password" type="password" name="password" required autocomplete="new-password" aria-describedby="reset-password-requirements" minlength="6">
                </div>
                <div class="form-group">
                    <label for="reset-password-confirm">Confirm password</label>
                    <input id="reset-password-confirm" type="password" name="password_confirm" required autocomplete="new-password" minlength="6">
                </div>
                <button class="btn btn-primary btn-block" type="submit">Update password</button>
            </form>
            <div class="auth-footer">
                <p>Still having trouble? <a href="/contact">Contact support</a></p>
            </div>
        </div>
    </div>
</div>
