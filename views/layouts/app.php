<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sri Panchami Spiritual</title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<?php $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; ?>
<body class="<?= str_starts_with($currentPath, '/admin') ? 'admin-view' : 'public-view' ?>">
<?php
$layoutSettings = (new \App\Services\SettingsService())->public();
$whatsappNumber = $layoutSettings['whatsapp_number'] ?: preg_replace('/\D+/', '', (string)($layoutSettings['contact_phone'] ?? ''));
$isHome = $currentPath === '/';
?>
<header class="site-header">
    <a href="/" class="brand"><img src="/assets/images/logo-square.jpeg" alt="Sri Panchami Spiritual logo"><span>Sri Panchami Spiritual</span></a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-label="Toggle navigation">☰</button>
    <nav id="primary-nav">
        <a href="/">Home</a><a href="/shop">Shop</a><a href="/astrologers">Astrologers</a><a href="/cart">Cart</a><a href="/contact">Contact</a><?php if(!empty($_SESSION['user'])): ?><a href="/account/bookings">My Bookings</a><a href="/logout">Logout</a><?php else: ?><a href="/login">Login</a><?php endif; ?>
    </nav>
</header>
<main><?php require $viewFile; ?></main>
<?php if($isHome): ?>
    <a class="whatsapp-float" href="<?= $whatsappNumber ? 'https://wa.me/' . e($whatsappNumber) : '/contact' ?>" <?= $whatsappNumber ? 'target="_blank" rel="noopener"' : '' ?> aria-label="<?= $whatsappNumber ? 'Chat on WhatsApp' : 'Contact Sri Panchami Spiritual' ?>">
        <svg viewBox="0 0 32 32" aria-hidden="true"><path d="M16.1 4C9.5 4 4.2 9.2 4.2 15.7c0 2.1.6 4.2 1.7 6L4 28l6.5-1.7c1.7.9 3.6 1.4 5.6 1.4 6.6 0 11.9-5.2 11.9-11.7C28 9.2 22.7 4 16.1 4Zm0 21.5c-1.8 0-3.5-.5-5-1.4l-.4-.2-3.8 1 1-3.7-.2-.4c-1-1.5-1.5-3.3-1.5-5.1 0-5.2 4.4-9.5 9.8-9.5s9.8 4.3 9.8 9.5-4.4 9.8-9.7 9.8Zm5.4-7.1c-.3-.1-1.8-.9-2.1-1-.3-.1-.5-.1-.7.2-.2.3-.8 1-.9 1.2-.2.2-.3.2-.6.1-.3-.1-1.2-.4-2.3-1.4-.8-.7-1.4-1.6-1.6-1.9-.2-.3 0-.5.1-.6.1-.1.3-.3.4-.5.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5-.1-.1-.7-1.7-1-2.3-.3-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4s1 2.8 1.2 3c.1.2 2 3.1 4.9 4.3.7.3 1.2.5 1.6.6.7.2 1.3.2 1.8.1.5-.1 1.8-.7 2-1.4.2-.7.2-1.3.2-1.4-.1-.2-.3-.3-.6-.4Z"/></svg>
        <span><?= $whatsappNumber ? 'WhatsApp' : 'Contact' ?></span>
    </a>
<?php endif; ?>
<footer>© <?= date('Y') ?> Sri Panchami Spiritual · Chennai, Tamil Nadu</footer>
<script>
const toggle=document.querySelector('.menu-toggle');
const nav=document.querySelector('#primary-nav');
if(toggle&&nav){toggle.addEventListener('click',()=>{const open=nav.classList.toggle('open');toggle.setAttribute('aria-expanded',open?'true':'false');});}
window.addEventListener('scroll',()=>document.body.classList.toggle('is-scrolled',window.scrollY>12),{passive:true});
const revealItems=document.querySelectorAll('.panel,.journey-step,.showcase-card,.feature-strip article,.category-card,.product-card,.trust-card,.story-band');
const io=new IntersectionObserver((entries)=>entries.forEach((entry)=>{if(entry.isIntersecting){entry.target.classList.add('revealed');io.unobserve(entry.target);}}),{threshold:0.15});
revealItems.forEach(item=>io.observe(item));
</script>
</body>
</html>
