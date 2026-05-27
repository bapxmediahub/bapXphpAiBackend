<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sri Panchami Spiritual</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/band.css">
</head>
<body>
<header class="site-header" id="site-header">
    <a href="/" class="brand"><img src="/assets/images/logo-square.jpeg" alt="Sri Panchami Spiritual logo"><span>Sri Panchami Spiritual</span></a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-label="Toggle navigation">☰</button>
    <nav id="primary-nav">
        <a href="/">Home</a>
        <a href="/shop">Shop</a>
        <a href="/astrologers">Astrologers</a>
        <a href="/about">About Us</a>
        <a href="/contact">Contact Us</a>
        <?php if(!empty($_SESSION['user'])): ?>
            <a href="/account/bookings">My Bookings</a>
            <a href="/logout">Logout</a>
        <?php else: ?>
            <a href="/login">Login</a>
        <?php endif; ?>
    </nav>
    <div class="header-actions">
        <button class="cart-btn" aria-label="Shopping cart" onclick="window.location.href='/cart'">
            🛒
            <?php
            $cartCount = 0;
            if (!empty($_SESSION['cart'])) {
                foreach ($_SESSION['cart'] as $c) { $cartCount += $c['qty'] ?? 1; }
            }
            if ($cartCount > 0): ?><span class="cart-count"><?= $cartCount ?></span><?php endif; ?>
        </button>
    </div>
</header>
<main>
<?php if(!empty($_SESSION['flash'])): ?>
    <div class="flash flash--info" style="margin: var(--space-lg) auto; max-width: var(--container-max); padding: 0 var(--space-lg);">
        <?= e($_SESSION['flash']); unset($_SESSION['flash']); ?>
    </div>
<?php endif; ?>
<?php require $viewFile; ?>
</main>

<nav class="bottom-nav" id="bottom-nav">
    <div class="nav-grid">
        <a href="/" class="nav-item <?= ($_SERVER['REQUEST_URI'] === '/' ? 'active' : '') ?>">
            <span class="icon">🏠</span>
            <span>Home</span>
        </a>
        <a href="/shop" class="nav-item <?= (strpos($_SERVER['REQUEST_URI'], '/shop') === 0 ? 'active' : '') ?>">
            <span class="icon">🛍️</span>
            <span>Shop</span>
        </a>
        <a href="/astrologers" class="nav-item <?= (strpos($_SERVER['REQUEST_URI'], '/astrologers') === 0 ? 'active' : '') ?>">
            <span class="icon">🔮</span>
            <span>Astro</span>
        </a>
        <a href="/account/bookings" class="nav-item <?= (strpos($_SERVER['REQUEST_URI'], '/account') === 0 ? 'active' : '') ?>">
            <span class="icon">📖</span>
            <span>Account</span>
        </a>
        <a href="/cart" class="nav-item <?= (strpos($_SERVER['REQUEST_URI'], '/cart') === 0 ? 'active' : '') ?>">
            <span class="icon">🛒</span>
            <span>Cart</span>
        </a>
    </div>
</nav>

<footer class="site-footer">
    <div class="footer-grid">
        <div>
            <span class="footer-brand">Sri Panchami Spiritual</span>
            <p class="footer-desc">Authentic spiritual products, sacred jewelry, expert astrology and temple guidance to elevate your life in Chennai, Tamil Nadu.</p>
        </div>
        <div>
            <h4 class="footer-heading">Shop</h4>
            <ul class="footer-links">
                <li><a href="/shop">All Products</a></li>
                <li><a href="/shop?category=pendants">Pendants</a></li>
                <li><a href="/shop?category=rings">Rings</a></li>
                <li><a href="/shop?category=earrings">Earrings</a></li>
            </ul>
        </div>
        <div>
            <h4 class="footer-heading">Services</h4>
            <ul class="footer-links">
                <li><a href="/astrologers">Astrologers</a></li>
                <li><a href="/about">About Us</a></li>
                <li><a href="/contact">Contact</a></li>
            </ul>
        </div>
        <div>
            <h4 class="footer-heading">Contact</h4>
            <ul class="footer-links">
                <li>23, 1st Cross Street Kothari Nagar</li>
                <li>Ramapuram, Chennai, Tamil Nadu 600089</li>
                <li><a href="mailto:sripanchamispiritual@gmail.com">sripanchamispiritual@gmail.com</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        &copy; <?= date('Y') ?> Sri Panchami Spiritual &middot; Chennai, Tamil Nadu
    </div>
</footer>
<script>
const toggle = document.querySelector('.menu-toggle');
const nav = document.querySelector('#primary-nav');
if (toggle && nav) {
    toggle.addEventListener('click', () => {
        const open = nav.classList.toggle('open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
}
document.addEventListener('click', (e) => {
    if (!nav.contains(e.target) && !toggle.contains(e.target)) {
        nav.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
    }
});
const header = document.getElementById('site-header');
const scrollObserver = new IntersectionObserver(([e]) => {
    header.classList.toggle('scrolled', !e.isIntersecting);
}, { threshold: 0 });
const sentinel = document.createElement('div');
sentinel.style.height = '1px';
sentinel.style.position = 'absolute';
sentinel.style.top = '0';
document.body.prepend(sentinel);
scrollObserver.observe(sentinel);
const revealItems = document.querySelectorAll('.reveal, .panel, .product-card, .astrologer-card, .section');
const io = new IntersectionObserver((entries) => entries.forEach((entry) => {
    if (entry.isIntersecting) {
        entry.target.classList.add('revealed');
        io.unobserve(entry.target);
    }
}), { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
revealItems.forEach(item => io.observe(item));
</script>
</body>
</html>
