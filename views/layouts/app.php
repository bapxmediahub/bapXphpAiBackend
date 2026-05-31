<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="format-detection" content="telephone=no">
<title>Sri Panchami Spiritual – Online Astrology Consultation, Chat, Call & Spiritual Products</title>
<meta name="description" content="Consult verified astrologers online by private message or direct call. Recharge wallet credits, review session history, and shop spiritual products, rudraksha, pooja items, and sacred jewellery.">
<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large">
<link rel="canonical" href="https://<?= e($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Sri Panchami Spiritual">
<meta property="og:title" content="Sri Panchami Spiritual – Online Astrology Consultation, Chat, Call & Spiritual Products">
<meta property="og:description" content="Consult verified astrologers by private message or direct call, recharge wallet credits, and shop spiritual products online.">
<meta property="og:url" content="https://<?= e($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>">
<meta property="og:image" content="https://<?= e($_SERVER['HTTP_HOST']) ?>/assets/images/og-image.jpg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale" content="en_IN">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Sri Panchami Spiritual – Online Astrology Consultation">
<meta name="twitter:description" content="Consult verified astrologers by chat or call, recharge wallet credits, and shop spiritual products online.">
<meta name="twitter:image" content="https://<?= e($_SERVER['HTTP_HOST']) ?>/assets/images/og-image.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<style>
<?php
// Inline critical CSS for instant first paint — header, nav, hero, product cards, mobile nav
$critical = '
:root{--color-ink:#2c1e14;--color-gold:#d4af37;--color-gold-light:#f1e5ac;--color-maroon:#800000;--color-maroon-deep:#5c0000;--color-bg:#fffdfa;--color-bg-alt:#f8f4ee;--color-border:#e8dcc5;--color-text-muted:#8c7e6d;--color-white:#ffffff;--color-success:#2d8a4e;--color-error:#d64045;--shadow-sm:0 1px 3px rgba(44,30,20,0.06);--shadow-md:0 4px 12px rgba(44,30,20,0.08);--shadow-lg:0 10px 30px rgba(44,30,20,0.1);--radius-md:12px;--radius-lg:16px;--radius-xl:24px;--radius-pill:999px;--space-xs:0.5rem;--space-sm:0.75rem;--space-md:1rem;--space-lg:1.5rem;--space-xl:2rem;--space-2xl:3rem}
*,*::before,*::after{box-sizing:border-box;-webkit-font-smoothing:antialiased}
html{scroll-behavior:smooth}
body{margin:0;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;background:var(--color-bg);color:var(--color-ink);line-height:1.7;overflow-x:hidden}
a{color:var(--color-maroon);text-decoration:none}
img{max-width:100%;height:auto;display:block}
.site-header{position:sticky;top:0;z-index:100;display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:var(--space-lg);padding:var(--space-sm) var(--space-md);background:rgba(255,253,250,0.95);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border-bottom:1px solid var(--color-border);transition:box-shadow 0.25s ease}
.site-header.scrolled{box-shadow:var(--shadow-md)}
.brand{display:flex;align-items:center;gap:var(--space-xs);color:var(--color-ink);font-weight:700;font-size:1rem;text-decoration:none}
 .brand img{width:40px;height:40px;border-radius:50%;border:2px solid var(--color-gold);object-fit:cover;box-shadow:0 0 0 1px rgba(255,255,255,0.9) inset}
nav{display:flex;gap:var(--space-lg);font-size:0;justify-content:center}
nav a{font-weight:500;color:var(--color-ink);padding:var(--space-xs) 0;font-size:0.9rem;text-decoration:none}
nav a:hover{color:var(--color-maroon)}
.header-actions{display:flex;align-items:center;gap:var(--space-md)}
.cart-btn{background:transparent;border:0;font-size:1.3rem;cursor:pointer;position:relative;color:var(--color-ink);padding:var(--space-xs);border-radius:var(--radius-md)}
 .cart-count{position:absolute;top:-6px;right:-8px;background:var(--color-maroon);color:var(--color-white);font-size:0.6rem;width:16px;height:16px;border-radius:4px;display:flex;align-items:center;justify-content:center;font-weight:bold}
.menu-toggle{display:none;border:1px solid var(--color-border);background:var(--color-white);padding:var(--space-xs) var(--space-sm);border-radius:var(--radius-md);cursor:pointer;font-size:1.2rem}
main{padding-bottom:0}
.container{max-width:1300px;margin:0 auto;padding:0 var(--space-xl)}
.section{padding:var(--space-2xl) 0}
.section--alt{background:var(--color-bg-alt)}
.home-hero{position:relative;min-height:60vh;display:grid;grid-template-columns:1fr 1fr;gap:var(--space-2xl);align-items:center;padding:var(--space-2xl) 0;background:linear-gradient(135deg,rgba(44,30,20,0.08),rgba(212,175,55,0.06))}
.hero-copy h1{font-family:Georgia,serif;font-size:clamp(1.8rem,4vw,2.8rem);line-height:1.2;margin:0 0 var(--space-md);color:var(--color-ink)}
.lede{font-size:1rem;line-height:1.7;color:var(--color-text-muted);margin-bottom:var(--space-lg)}
 .btn{display:inline-flex;align-items:center;justify-content:center;gap:var(--space-xs);padding:0.75rem 1.5rem;border-radius:8px;font-weight:600;cursor:pointer;border:0;text-decoration:none;font-size:0.85rem;white-space:nowrap;line-height:1.4;transition:all 0.25s ease}
.btn-primary{background:linear-gradient(135deg,var(--color-maroon),var(--color-maroon-deep));color:var(--color-white);box-shadow:0 4px 15px rgba(128,0,0,0.25)}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(128,0,0,0.35)}
.btn-outline{background:transparent;border:1.5px solid var(--color-gold);color:var(--color-ink)}
.hero-actions{display:flex;gap:var(--space-md);margin-bottom:var(--space-xl)}
.section-header{text-align:center;margin-bottom:var(--space-2xl)}
.section-title{font-family:Georgia,serif;font-size:clamp(1.5rem,3vw,2rem);margin:0;padding:0 0 var(--space-sm)}
.section-title::before,.section-title::after{content:\' ✧ \';color:var(--color-gold);font-size:1rem}
.section-header .lede{color:var(--color-text-muted);max-width:500px;margin:var(--space-sm) auto 0;font-size:0.9rem}
.product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:var(--space-xl)}
 .product-card{background:var(--color-white);border:1px solid var(--color-border);border-radius:var(--radius-lg);overflow:hidden;transition:all 0.3s ease;box-shadow:var(--shadow-sm)}
.product-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-lg);border-color:var(--color-gold)}
.product-card__image{position:relative;overflow:hidden;aspect-ratio:1}
.product-card__image img{width:100%;height:100%;object-fit:cover;transition:transform 0.4s ease}
.product-card:hover .product-card__image img{transform:scale(1.05)}
.product-card__badge{position:absolute;top:var(--space-sm);left:var(--space-sm);padding:0.2rem 0.6rem;border-radius:var(--radius-pill);font-size:0.65rem;font-weight:700;text-transform:uppercase}
.product-card__badge--sale{background:var(--color-error);color:var(--color-white)}
.product-card__body{padding:var(--space-md)}
.product-card h3{font-family:Georgia,serif;font-size:0.95rem;margin:0 0 var(--space-xs);color:var(--color-ink)}
.product-card__desc{font-size:0.8rem;color:var(--color-text-muted);margin-bottom:var(--space-sm);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.product-card__price-row{display:flex;align-items:center;gap:var(--space-xs);margin-bottom:var(--space-sm)}
.price{font-weight:700;color:var(--color-maroon);font-size:1.1rem}
.old-price{text-decoration:line-through;color:var(--color-text-muted);font-size:0.85rem}
.product-card__actions{display:flex;gap:var(--space-xs)}
.product-card__actions .btn{flex:1}
.feature-strip{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:var(--space-xl)}
.panel{background:var(--color-white);border:1px solid var(--color-border);border-radius:var(--radius-lg);padding:var(--space-xl);transition:all 0.25s ease;box-shadow:var(--shadow-sm)}
.panel:hover{box-shadow:var(--shadow-md)}
.astrologer-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:var(--space-xl)}
 .astrologer-card{background:var(--color-white);border:1px solid var(--color-border);border-radius:18px;overflow:hidden;transition:all 0.3s ease;box-shadow:var(--shadow-sm)}
.astrologer-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-xl);border-color:rgba(212,175,55,0.65)}
.astrologer-card__media{position:relative;aspect-ratio:3/4;overflow:hidden;background:linear-gradient(180deg,rgba(44,30,20,0.02),rgba(44,30,20,0.08)),linear-gradient(135deg,rgba(212,175,55,0.12),rgba(255,255,255,0.2))}
 .astrologer-card__photo{width:100%;height:100%;object-fit:cover;object-position:center top;display:block;transform:scale(1.01)}
.astrologer-card__media::after{content:\'\';position:absolute;inset:auto 0 0 0;height:42%;background:linear-gradient(180deg,rgba(18,12,8,0),rgba(18,12,8,0.28));pointer-events:none}
.astrologer-card__media-badge{position:absolute;left:var(--space-sm);bottom:var(--space-sm);z-index:1;padding:0.3rem 0.65rem;border-radius:var(--radius-pill);background:rgba(44,30,20,0.78);color:var(--color-white);font-size:0.64rem;letter-spacing:0.08em;text-transform:uppercase;backdrop-filter:blur(8px)}
.astrologer-card__body--portrait{padding:var(--space-md) var(--space-md) var(--space-sm);display:grid;gap:var(--space-xs)}
.astrologer-card__title-row{display:flex;justify-content:space-between;align-items:flex-start;gap:var(--space-sm)}
.astrologer-card__status{padding:0.22rem 0.55rem;border-radius:var(--radius-pill);background:rgba(212,175,55,0.16);color:var(--color-maroon);font-size:0.64rem;font-weight:700;text-transform:uppercase;white-space:nowrap}
.astrologer-card__speciality{margin:0;color:var(--color-text-muted);font-size:0.84rem}
.astrologer-card__bio{margin:0;color:var(--color-ink);font-size:0.83rem;line-height:1.55;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.astrologer-card__meta{display:flex;flex-wrap:wrap;gap:var(--space-xs);font-size:0.72rem;color:var(--color-text-muted)}
.astrologer-card__meta span{padding:0.25rem 0.5rem;border:1px solid var(--color-border);border-radius:var(--radius-pill);background:var(--color-bg-alt)}
.astrologers-hero{margin-bottom:var(--space-lg)}
.astrologers-hero .lede{max-width:640px;margin:var(--space-sm) auto 0;line-height:1.55;color:var(--color-text-muted)}
.astrologer-card__footer{padding:var(--space-md);border-top:1px solid var(--color-border);display:grid;gap:var(--space-sm)}
.astrologer-card__price{font-size:0.88rem;font-weight:700;color:var(--color-maroon)}
.astrologer-card__actions{display:grid;grid-template-columns:1fr 0.8fr 1fr;gap:var(--space-xs)}
.astrologer-card__actions .btn{width:100%;padding-left:0.75rem;padding-right:0.75rem}
.btn-call{background:var(--color-success);color:white;border:none;padding:0.5rem 1rem;border-radius:var(--radius-pill);font-weight:600;cursor:pointer;font-size:0.85rem}
.btn-message{background:#3b82f6;color:white;border:none;padding:0.5rem 1rem;border-radius:var(--radius-pill);font-weight:600;cursor:pointer;font-size:0.85rem}
 .category-grid{grid-template-columns:repeat(auto-fit,minmax(180px,220px));justify-content:center;gap:var(--space-xl);max-width:760px;margin:0 auto}
 .category-card{cursor:pointer;transition:all 0.3s ease;text-align:center;text-decoration:none;color:var(--color-ink)}
 .category-img-wrap{width:clamp(124px,14vw,178px);height:clamp(124px,14vw,178px);border-radius:50%;overflow:hidden;margin:0 auto var(--space-xs);border:4px solid var(--color-white);background:radial-gradient(circle at 50% 50%,rgba(92,0,0,0.92),rgba(44,30,20,0.96));box-shadow:0 4px 15px rgba(44,30,20,0.1)}
 .category-img-wrap img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.temple-scroll{display:grid;grid-auto-flow:column;grid-auto-columns:minmax(720px,82vw);gap:var(--space-xl);max-width:1300px;margin:0 auto;overflow-x:auto;scroll-snap-type:x mandatory;padding:0 0 var(--space-md)}
.temple-slide{display:grid;grid-template-columns:minmax(280px,0.85fr) minmax(320px,1fr);align-items:stretch;gap:var(--space-xl);scroll-snap-align:center;min-height:320px}
.temple-slide__media{background:var(--color-bg-alt);border-radius:var(--radius-md);min-height:280px;overflow:hidden;display:flex;align-items:center;justify-content:center}
.temple-slide__media img{width:100%;height:100%;min-height:280px;object-fit:cover;margin:0;border-radius:0}
.temple-slide__copy{display:flex;flex-direction:column;justify-content:center;text-align:left}
.temple-slide__address{display:flex;align-items:flex-start;gap:var(--space-xs);margin-top:var(--space-sm);font-size:0.82rem!important}
.bottom-nav{display:none;position:fixed;bottom:0;left:0;right:0;background:rgba(255,255,255,0.95);backdrop-filter:blur(20px);border-top:1px solid var(--color-border);padding:var(--space-xs) 0;z-index:1000}
.nav-grid{display:grid;grid-template-columns:repeat(5,1fr);max-width:480px;margin:0 auto}
.nav-item{display:flex;flex-direction:column;align-items:center;padding:var(--space-xs) 0;color:var(--color-text-muted);font-size:0.6rem;text-decoration:none;min-height:48px;justify-content:center}
.nav-item .icon svg{width:20px;height:20px;margin-bottom:2px}
.site-footer{background:var(--color-ink);color:rgba(255,255,255,0.7);padding:var(--space-2xl) 0 var(--space-md);font-size:0.85rem}
.footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:var(--space-xl);margin-bottom:var(--space-xl)}
.footer-brand{font-family:Georgia,serif;font-size:1.2rem;color:var(--color-gold);font-weight:600;display:block;margin-bottom:var(--space-xs)}
.footer-desc{font-size:0.85rem;line-height:1.6;opacity:0.7}
.footer-heading{font-size:0.75rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--color-gold);margin:0 0 var(--space-sm)}
.footer-links{list-style:none;padding:0;margin:0}
.footer-links li{margin-bottom:var(--space-xs)}
.footer-links a{color:rgba(255,255,255,0.55);text-decoration:none;font-size:0.85rem}
.footer-links a:hover{color:var(--color-gold)}
.footer-bottom{text-align:center;padding-top:var(--space-md);border-top:1px solid rgba(255,255,255,0.08);font-size:0.75rem;color:rgba(255,255,255,0.4)}
.flash{padding:var(--space-md);border-radius:var(--radius-md);margin-bottom:var(--space-md);font-size:0.85rem;font-weight:500}
.flash--success{background:#e8f5ed;color:var(--color-success)}
.flash--error{background:#fde8e9;color:var(--color-error)}
.flash--info{background:#eff6ff;color:#3b82f6}
.reveal{opacity:0;transform:translateY(20px);transition:opacity 0.5s ease,transform 0.5s ease}
.reveal.revealed{opacity:1;transform:translateY(0)}
@media(max-width:860px){
nav{display:none;position:absolute;top:100%;left:0;right:0;background:rgba(255,255,255,0.98);flex-direction:column;padding:var(--space-lg);border-bottom:1px solid var(--color-border);box-shadow:var(--shadow-lg)}
nav a{font-size:0.95rem;padding:var(--space-sm) var(--space-md);border-radius:var(--radius-md)}
nav.open{display:flex}
.menu-toggle{display:block}
.home-hero{grid-template-columns:1fr;text-align:center;width:100vw;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);padding:var(--space-2xl) var(--space-md) var(--space-lg)}
.hero-actions{justify-content:center}
.category-grid{grid-template-columns:repeat(2,minmax(0,128px));justify-content:center;gap:var(--space-md)}
.temple-scroll{grid-auto-columns:minmax(82vw,1fr);gap:var(--space-md);padding-left:var(--space-sm);padding-right:var(--space-sm)}
.temple-slide{grid-template-columns:1fr;min-height:0;gap:var(--space-md)}
.temple-slide__media,.temple-slide__media img{min-height:190px}
.temple-slide__copy{text-align:center}
.footer-grid{grid-template-columns:1fr 1fr}
.bottom-nav{display:block}
.main-content{padding-bottom:calc(60px + var(--space-md))}
.site-header{grid-template-columns:48px 1fr 48px}
.site-header .brand{justify-self:start}
.site-header .menu-toggle{justify-self:center;width:46px;height:46px;display:inline-flex;align-items:center;justify-content:center}
.site-header .header-actions{justify-self:end}
.cart-btn{width:44px;height:44px;display:inline-flex;align-items:center;justify-content:center}
.brand span{display:none}
.astrologers-page{padding-top:var(--space-md)!important}
.astrologers-hero{margin-bottom:var(--space-lg)}
.astrologers-hero .lede{font-size:0.86rem;line-height:1.45;max-width:88%}
.astrologer-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--space-sm)}
.astrologer-card__media{aspect-ratio:1/1.1}
.astrologer-card__media-badge{font-size:0.52rem;padding:0.18rem 0.4rem;left:0.45rem;bottom:0.45rem}
.astrologer-card__name{font-size:0.86rem;line-height:1.15}
.astrologer-card__status{display:none}
.astrologer-card__speciality{font-size:0.72rem;line-height:1.25}
.astrologer-card__bio{display:none}
.astrologer-card__meta{gap:0.25rem;font-size:0.62rem}
.astrologer-card__meta span{padding:0.15rem 0.35rem}
.astrologer-card__body--portrait{padding:var(--space-sm)}
.astrologer-card__footer{padding:var(--space-sm);gap:var(--space-xs)}
.astrologer-card__price{font-size:0.68rem;line-height:1.3}
.astrologer-card__actions{grid-template-columns:repeat(3,minmax(0,1fr));gap:0.3rem}
.astrologer-card__actions .btn{min-height:34px;padding:0.35rem 0.2rem;font-size:0.62rem;border-radius:4px;letter-spacing:0}
}
@media(max-width:480px){
.product-grid{grid-template-columns:1fr}
.astrologer-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
.footer-grid{grid-template-columns:1fr}
.hero-actions{flex-direction:column;align-items:center}
}
';
echo $critical;
?>
</style>
<link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet"></noscript>
<link rel="preload" href="/assets/css/band.css?v=<?= filemtime(__DIR__ . '/../../assets/css/band.css') ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="/assets/css/band.css?v=<?= filemtime(__DIR__ . '/../../assets/css/band.css') ?>"></noscript>
<script type="application/ld+json">
{"@context":"https://schema.org","@type":["LocalBusiness","Store"],"name":"Sri Panchami Spiritual","description":"Authentic spiritual products, sacred jewellery, expert Vedic astrology consultation, and temple guidance in Chennai, Tamil Nadu.","url":"https://<?= e($_SERVER['HTTP_HOST']) ?>","email":"sripanchamispiritual@gmail.com","address":{"@type":"PostalAddress","streetAddress":"23, 1st Cross Street Kothari Nagar, Ramapuram","addressLocality":"Chennai","addressRegion":"Tamil Nadu","postalCode":"600089","addressCountry":"IN"},"geo":{"@type":"GeoCoordinates","latitude":"13.0166","longitude":"80.1833"},"openingHoursSpecification":[{"@type":"OpeningHoursSpecification","dayOfWeek":["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"],"opens":"09:00","closes":"19:00"},{"@type":"OpeningHoursSpecification","dayOfWeek":"Sunday","opens":"10:00","closes":"17:00"}],"priceRange":"₹₹"}
</script>
</head>
<body>
<header class="site-header" id="site-header">
    <a href="/" class="brand"><img src="/assets/images/logo-small.jpeg" width="52" height="52" alt="Sri Panchami Spiritual logo"><span>Sri Panchami Spiritual</span></a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-label="Menu">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <nav id="primary-nav">
        <a href="/">Home</a>
        <a href="/shop">Shop</a>
        <a href="/astrologers">Consult</a>
        <a href="/temples">Temples</a>
        <a href="/about">About SPS</a>
        <a href="/contact">Contact</a>
        <?php if(!empty($_SESSION['user'])): ?>
            <a href="/account/bookings">My Sessions</a>
            <a href="/account/wallet">Wallet</a>
            <a href="/logout">Logout</a>
        <?php else: ?>
            <a href="/login">Login</a>
        <?php endif; ?>
    </nav>
    <div class="header-actions">
        <a href="/cart" class="cart-btn" aria-label="Cart">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
            <?php $cartCount = 0; if(!empty($_SESSION['cart'])){foreach($_SESSION['cart'] as $c){$cartCount += $c['qty'] ?? 1;}} if($cartCount > 0): ?><span class="cart-count"><?= $cartCount ?></span><?php endif; ?>
        </a>
    </div>
</header>
<?php $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; ?>
<main class="<?= $currentPath === '/' ? 'home-main' : '' ?>">
<?php if(!empty($_SESSION['flash'])): ?>
    <div class="flash flash--info" style="margin:var(--space-lg) auto;max-width:1300px;padding:0 var(--space-lg)"><?= e($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>
<?php require $viewFile; ?>
</main>

<nav class="bottom-nav" id="bottom-nav">
    <div class="nav-grid">
        <a href="/" class="nav-item <?= ($_SERVER['REQUEST_URI'] === '/' ? 'active' : '') ?>">
            <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>Home</span>
        </a>
        <a href="/shop" class="nav-item <?= (strpos($_SERVER['REQUEST_URI'], '/shop') === 0 ? 'active' : '') ?>">
            <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
            <span>Shop</span>
        </a>
        <a href="/astrologers" class="nav-item <?= (strpos($_SERVER['REQUEST_URI'], '/astrologers') === 0 ? 'active' : '') ?>">
            <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 000 20 14.5 14.5 0 000-20"/><path d="M2 12h20"/></svg>
            <span>Consult</span>
        </a>
        <a href="/temples" class="nav-item <?= (strpos($_SERVER['REQUEST_URI'], '/temples') === 0 ? 'active' : '') ?>">
            <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l8-4 8 4v14"/><path d="M9 21v-4a2 2 0 012-2h2a2 2 0 012 2v4"/></svg>
            <span>Temples</span>
        </a>
        <a href="/cart" class="nav-item <?= (strpos($_SERVER['REQUEST_URI'], '/cart') === 0 ? 'active' : '') ?>">
            <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
            <span>Cart</span>
        </a>
    </div>
</nav>

<button class="support-fab" type="button" aria-controls="support-panel" aria-expanded="false" title="Support">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>
    <span class="sr-only">Support</span>
</button>
<section class="support-panel" id="support-panel" data-support-key="sps-support-chat-<?= !empty($_SESSION['user']['email']) ? e(strtolower((string)$_SESSION['user']['email'])) : 'guest' ?>" hidden>
    <div class="support-panel__head">
        <strong>Support</strong>
        <button type="button" class="support-panel__close" aria-label="Close support">×</button>
    </div>
    <div class="support-panel__body" id="support-log">
        <p>Ask about your orders, wallet recharge, products, or astrologer sessions.</p>
        <?php if(empty($_SESSION['user'])): ?><p>Sign in to ask about your personal order or session data.</p><?php endif; ?>
    </div>
    <form class="support-panel__form" id="support-form">
        <textarea name="message" rows="3" required placeholder="Ask about an order, recharge, product, or session"></textarea>
        <button class="btn btn-primary btn-sm">Send</button>
    </form>
</section>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <span class="footer-brand">Sri Panchami Spiritual</span>
                <p class="footer-desc">Online astrology consultation by private message or direct call, plus authentic spiritual products, sacred jewellery, rudraksha, pooja items, and temple guidance.</p>
            </div>
            <div>
                <h4 class="footer-heading">Shop</h4>
                <ul class="footer-links">
                    <li><a href="/shop">All Products</a></li>
                    <li><a href="/astrologers">Consult</a></li>
                    <li><a href="/temples">Temples</a></li>
                    <li><a href="/about">About SPS</a></li>
                    <li><a href="/contact">Contact</a></li>
                </ul>
            </div>
            <div>
                <h4 class="footer-heading">Services</h4>
                <ul class="footer-links">
                    <li><a href="/astrologers">Consult</a></li>
                    <li><a href="/temples">Temples</a></li>
                    <li><a href="/about">About SPS</a></li>
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
        <div class="footer-bottom">&copy; <?= date('Y') ?> Sri Panchami Spiritual &middot; Chennai, Tamil Nadu</div>
    </div>
</footer>
<script>
document.getElementById('site-header').querySelector('.menu-toggle').addEventListener('click',function(){
    var n=document.getElementById('primary-nav');n.classList.toggle('open');
    this.setAttribute('aria-expanded',n.classList.contains('open')?'true':'false');
});
document.addEventListener('click',function(e){
    var n=document.getElementById('primary-nav'),t=document.querySelector('.menu-toggle');
    if(!n.contains(e.target)&&!t.contains(e.target)){n.classList.remove('open');t.setAttribute('aria-expanded','false');}
});
var h=document.getElementById('site-header');
var s=document.createElement('div');s.style.cssText='height:1px;position:absolute;top:0';
document.body.prepend(s);
new IntersectionObserver(function(e){h.classList.toggle('scrolled',!e[0].isIntersecting);},{threshold:0}).observe(s);
var io=new IntersectionObserver(function(entries){entries.forEach(function(e){if(e.isIntersecting){e.target.classList.add('revealed');io.unobserve(e.target);}});},{threshold:0.1,rootMargin:'0px 0px -50px 0px'});
document.querySelectorAll('.reveal,.panel,.product-card,.astrologer-card').forEach(function(el){io.observe(el);});
var supportFab=document.querySelector('.support-fab'),supportPanel=document.getElementById('support-panel'),supportClose=document.querySelector('.support-panel__close'),supportForm=document.getElementById('support-form'),supportLog=document.getElementById('support-log');
function supportEscape(value){return String(value).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
function supportToggle(open){supportPanel.hidden=!open;supportFab.setAttribute('aria-expanded',open?'true':'false');}
function supportSaveLog(){try{sessionStorage.setItem(supportPanel.dataset.supportKey,supportLog.innerHTML);}catch(e){}}
function supportLoadLog(){try{var saved=sessionStorage.getItem(supportPanel.dataset.supportKey);if(saved){supportLog.innerHTML=saved;}}catch(e){}}
supportLoadLog();
supportFab.addEventListener('click',function(){supportToggle(supportPanel.hidden);});
supportClose.addEventListener('click',function(){supportToggle(false);});
supportForm.addEventListener('submit',async function(e){e.preventDefault();var data=new FormData(supportForm),msg=data.get('message');supportLog.insertAdjacentHTML('beforeend','<p><strong>You:</strong> '+supportEscape(msg)+'</p>');supportSaveLog();supportForm.reset();try{var r=await fetch('/support/ask',{method:'POST',body:data});var j=await r.json();supportLog.insertAdjacentHTML('beforeend','<p><strong>Support:</strong> '+supportEscape(j.reply||j.error||'Unable to answer right now.')+'</p>');}catch(err){supportLog.insertAdjacentHTML('beforeend','<p><strong>Support:</strong> Unable to answer right now.</p>');}supportSaveLog();supportLog.scrollTop=supportLog.scrollHeight;});
</script>
</body>
</html>
