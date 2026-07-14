<div class="auth-page">
    <div class="auth-visual">
        <h2>Begin Your Journey</h2>
        <p>Join our community of devotees and seekers. Create your account to manage your bookings and sacred collections.</p>
    </div>
    <div class="auth-form-container">
        <div class="auth-card">
            <h1>Create Account</h1>
            <p>Join Sri Panchami Spiritual today</p>
            <form method="post" action="/register" class="auth-form">
                <?php $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16)); ?>
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" required placeholder="Your full name">
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="your@email.com">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" required placeholder="+91 XXXXX XXXXX">
                </div>
                <div class="form-group">
                    <label>Default delivery address</label>
                    <textarea name="address" required rows="2" placeholder="Door no, street, area"></textarea>
                </div>
                <div class="auth-form__row">
                    <div class="form-group"><label>City</label><input type="text" name="city" required placeholder="City"></div>
                    <div class="form-group"><label>PIN code</label><input type="text" name="pincode" required inputmode="numeric" placeholder="600001"></div>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="••••••••" minlength="6">
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="password_confirm" required placeholder="••••••••" minlength="6">
                </div>
                <div class="form-group form-check">
                    <label class="checkbox-label">
                        <input type="checkbox" name="accept_terms" required>
                        <span>I accept the <a href="/terms" target="_blank">Terms of Service</a> and <a href="/privacy" target="_blank">Privacy Policy</a></span>
                    </label>
                </div>
                <button class="btn btn-primary btn-block">Create Account</button>
            </form>
            <?php if (!empty($googleAuthEnabled)): ?>
            <div class="auth-divider">or</div>
            <a href="/auth/google" class="btn-google">
                <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                Sign up with Google
            </a>
            <?php endif; ?>
            <div class="auth-footer">
                <p>Already have an account? <a href="/login">Sign in here</a></p>
            </div>
        </div>
    </div>
</div>
