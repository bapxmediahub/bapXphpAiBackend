<div class="auth-page auth-page--register">
    <div class="auth-form-container">
        <div class="auth-card">
            <h1>Create an account</h1>
            <p>Set up your sign-in details and default delivery address.</p>
            <form method="post" action="/register" class="auth-form">
                <?php $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16)); ?>
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <fieldset class="auth-form__section">
                    <legend>Account details</legend>
                    <div class="auth-form__row">
                        <div class="form-group">
                            <label for="register-name">Full name</label>
                            <input id="register-name" type="text" name="name" required autocomplete="name">
                        </div>
                        <div class="form-group">
                            <label for="register-email">Email address</label>
                            <input id="register-email" type="email" name="email" required autocomplete="email">
                        </div>
                    </div>
                    <div class="auth-form__row">
                        <div class="form-group">
                            <label for="register-password">Password</label>
                            <input id="register-password" type="password" name="password" required autocomplete="new-password" minlength="6" aria-describedby="register-password-hint">
                            <p id="register-password-hint" class="auth-form__hint">At least 6 characters.</p>
                        </div>
                        <div class="form-group">
                            <label for="register-password-confirm">Confirm password</label>
                            <input id="register-password-confirm" type="password" name="password_confirm" required autocomplete="new-password" minlength="6">
                        </div>
                    </div>
                </fieldset>
                <fieldset class="auth-form__section">
                    <legend>Delivery address</legend>
                    <div class="form-group">
                        <label for="register-address">Street address</label>
                        <textarea id="register-address" name="address" required autocomplete="shipping street-address" rows="2" placeholder="Door number, street and area"></textarea>
                    </div>
                    <div class="auth-form__row">
                        <div class="form-group">
                            <label for="register-city">City</label>
                            <input id="register-city" type="text" name="city" required autocomplete="shipping address-level2">
                        </div>
                        <div class="form-group">
                            <label for="register-pincode">PIN code</label>
                            <input id="register-pincode" type="text" name="pincode" required autocomplete="shipping postal-code" inputmode="numeric">
                        </div>
                    </div>
                    <div class="auth-form__row">
                        <div class="form-group">
                            <label for="register-state">State or union territory</label>
                            <select id="register-state" name="state" required autocomplete="shipping address-level1">
                                <option value="">Select a state</option>
                                <?php foreach (\App\Services\TaxService::states() as $state): ?>
                                    <option value="<?= e($state) ?>"><?= e($state) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="register-phone">Delivery phone</label>
                            <input id="register-phone" type="tel" name="phone" required autocomplete="shipping tel">
                        </div>
                    </div>
                </fieldset>
                <div class="form-group form-check">
                    <label class="checkbox-label">
                        <input type="checkbox" name="accept_terms" required>
                        <span>I accept the <a href="/terms" target="_blank" rel="noopener noreferrer">Terms of Service</a> and <a href="/privacy" target="_blank" rel="noopener noreferrer">Privacy Policy</a></span>
                    </label>
                </div>
                <button class="btn btn-primary btn-block" type="submit">Create account</button>
            </form>
            <?php if (!empty($googleAuthEnabled)): ?>
            <div class="auth-divider">or</div>
            <a href="/auth/google" class="btn-google">
                <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                Sign up with Google
            </a>
            <?php endif; ?>
            <div class="auth-footer">
                <p>Already have an account? <a href="/login">Sign in</a></p>
            </div>
        </div>
    </div>
</div>
