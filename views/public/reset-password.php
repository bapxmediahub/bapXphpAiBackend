<div class="auth-page">
    <div class="auth-visual">
        <h2>Secure Your Account</h2>
        <p>Set a strong password to protect your account and ensure a seamless experience with your sacred collections.</p>
    </div>
    <div class="auth-form-container">
        <div class="auth-card">
            <h1>Reset Password</h1>
            <p>Create a new secure password</p>
            <form method="post" action="/reset-password" class="auth-form">
                <?php $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16)); ?>
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="password" required placeholder="••••••••" minlength="6">
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="password_confirm" required placeholder="••••••••" minlength="6">
                </div>
                <button class="btn btn-primary btn-block">Update Password</button>
            </form>
            <div class="auth-footer">
                <p>Still having trouble? <a href="/contact">Contact Support</a></p>
            </div>
        </div>
    </div>
</div>
