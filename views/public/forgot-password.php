<div class="auth-page">
    <div class="auth-visual">
        <h2>Recover Access</h2>
        <p>Enter your email address and we will send you a secure link to reset your password and return to your spiritual journey.</p>
    </div>
    <div class="auth-form-container">
        <div class="auth-card">
            <h1>Forgot Password</h1>
            <p>Secure password recovery</p>
            <form method="post" action="/forgot-password" class="auth-form">
                <?php $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16)); ?>
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="your@email.com">
                </div>
                <button class="btn btn-primary btn-block">Send Reset Link</button>
            </form>
            <div class="auth-footer">
                <p>Remembered your password? <a href="/login">Sign in here</a></p>
            </div>
        </div>
    </div>
</div>
