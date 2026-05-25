<h1>Login</h1><?php if(!empty($_SESSION['flash'])): ?><p class="notice"><?= e($_SESSION['flash']); unset($_SESSION['flash']); ?></p><?php endif; ?>
<?php if(!empty($_SESSION['user'])): ?>
    <p>Signed in as <?= e($_SESSION['user']['name'] ?? $_SESSION['user']['email'] ?? 'user') ?>.</p>
    <p><a class="button-link" href="/logout">Sign out</a></p>
<?php else: ?>
    <p>Continue with Google to shop, book astrology sessions, or check your orders.</p>
    <p><a class="button-link" href="/auth/google">Continue with Google</a></p>
<?php endif; ?>
