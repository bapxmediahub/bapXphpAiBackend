<div class="auth-page">
    <div class="auth-form-container">
        <div class="auth-card">
            <h1>Reset your password</h1>
            <p>Enter the email address associated with your account.</p>
            <form method="post" action="/forgot-password" class="auth-form">
                <?php $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16)); ?>
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <div class="form-group">
                    <label for="recovery-email">Email address</label>
                    <input id="recovery-email" type="email" name="email" required autocomplete="email">
                </div>
                <button class="btn btn-primary btn-block" type="submit">Send reset link</button>
            </form>
            <div class="auth-footer">
                <p><a href="/login">Return to sign in</a></p>
            </div>
        </div>
    </div>
</div>
