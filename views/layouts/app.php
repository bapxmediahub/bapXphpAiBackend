<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sri Panchami Spiritual</title>
<link rel="stylesheet" href="/assets/css/band.css">
</head>
<body>
<header class="site-header">
    <a href="/" class="brand"><img src="/assets/images/logo-square.jpeg" alt="Sri Panchami Spiritual logo"><span>Sri Panchami Spiritual</span></a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-label="Toggle navigation">☰</button>
    <nav id="primary-nav">
        <a href="/">Home</a><a href="/shop">Shop</a><a href="/astrologers">Astrologers</a><a href="/about">About Us</a><a href="/contact">Contact Us</a><?php if(!empty($_SESSION['user'])): ?><a href="/account/bookings">My Bookings</a><a href="/logout">Logout</a><?php else: ?><a href="/login">Login</a><?php endif; ?>
    </nav>
</header>
<main><?php require $viewFile; ?></main>
<footer>© <?= date('Y') ?> Sri Panchami Spiritual · Chennai, Tamil Nadu</footer>
<script>
const toggle=document.querySelector('.menu-toggle');
const nav=document.querySelector('#primary-nav');
if(toggle&&nav){toggle.addEventListener('click',()=>{const open=nav.classList.toggle('open');toggle.setAttribute('aria-expanded',open?'true':'false');});}
const revealItems=document.querySelectorAll('.panel,.journey-step,.showcase-card,.feature-strip article');
const io=new IntersectionObserver((entries)=>entries.forEach((entry)=>{if(entry.isIntersecting){entry.target.classList.add('revealed');io.unobserve(entry.target);}}),{threshold:0.15});
revealItems.forEach(item=>io.observe(item));
</script>
</body>
</html>
