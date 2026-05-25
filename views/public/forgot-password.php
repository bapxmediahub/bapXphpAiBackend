<h1>Forgot Password</h1>
<?php if(!empty($_SESSION['flash'])): ?><p class="notice"><?= e($_SESSION['flash']); unset($_SESSION['flash']); ?></p><?php endif; ?>
<div class="panel auth-panel">
    <p>Enter your account email and we will send a secure reset link.</p>
    <form method="post" action="/forgot-password" class="auth-form">
        <label>Email <input type="email" name="email" required></label>
        <button>Send reset link</button>
    </form>
</div>
