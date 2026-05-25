<h1>Reset Password</h1>
<?php if(!empty($_SESSION['flash'])): ?><p class="notice"><?= e($_SESSION['flash']); unset($_SESSION['flash']); ?></p><?php endif; ?>
<div class="panel auth-panel">
    <form method="post" action="/reset-password" class="auth-form">
        <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
        <label>New password <input type="password" name="password" required></label>
        <label>Confirm password <input type="password" name="password_confirm" required></label>
        <button>Update password</button>
    </form>
</div>
