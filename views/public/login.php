<h1>Login</h1>
<?php if(!empty($_SESSION['flash'])): ?><p class="notice"><?= e($_SESSION['flash']); unset($_SESSION['flash']); ?></p><?php endif; ?>
<?php if(!empty($_SESSION['user'])): ?>
    <p>Signed in as <?= e($_SESSION['user']['name'] ?? $_SESSION['user']['email'] ?? 'user') ?>.</p>
    <p><a class="button-link" href="/logout">Sign out</a></p>
<?php else: ?>
    <div class="auth-grid">
        <div>
            <p>Sign in with Google</p>
            <p><a class="button-link" href="/auth/google">Continue with Google</a></p>
        </div>
        <div>
            <p>Or sign in with email</p>
            <form method="post" action="/login" class="auth-form">
                <label>Email <input type="email" name="email" required></label>
                <label>Password <input type="password" name="password" required></label>
                <button>Sign in</button>
            </form>
            <p><a href="/forgot-password">Forgot password?</a></p>
            <p>Don't have an account? <a href="/register">Register</a></p>
        </div>
    </div>
<?php endif; ?>
