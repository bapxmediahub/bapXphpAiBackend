<h1>Register</h1>
<?php if(!empty($_SESSION['flash'])): ?><p class="notice"><?= e($_SESSION['flash']); unset($_SESSION['flash']); ?></p><?php endif; ?>
<form method="post" action="/register" class="auth-form">
    <label>Name <input name="name" required></label>
    <label>Email <input type="email" name="email" required></label>
    <label>Password <input type="password" name="password" required></label>
    <label>Confirm Password <input type="password" name="password_confirm" required></label>
    <button>Register</button>
</form>
<p>Already have an account? <a href="/login">Sign in</a></p>
