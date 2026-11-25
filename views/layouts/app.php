<?php
$__settingsService = new \App\Services\SettingsService();
$__settings = $__settingsService->public();
$__modules = $__settingsService->modules();
$siteProfile ??= \App\Services\SiteProfileService::current();
$__siteName = $siteProfile->name();
$__siteContact = $siteProfile->contact();
$__referenceSite = $siteProfile->isReference();
$__aboutLabel = $siteProfile->hasReferenceName() ? 'About SPS' : 'About';
$__logo = ($__settings['logo_url'] ?? '') ?: ($__referenceSite ? '/assets/images/logo-small.jpeg' : '');
$__favicon = ($__settings['favicon_url'] ?? '') ?: ($__referenceSite ? '/assets/images/sps-favicon.svg' : '');
$__footerDescription = $siteProfile->summary() ?: ($__referenceSite
    ? 'Authentic spiritual products, sacred jewellery, rudraksha, pooja items, and temple guidance.' : '');
$__faviconMime = str_contains($__favicon,'.svg') ? 'image/svg+xml' : 'image/png';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="format-detection" content="telephone=no">
<title><?= e($pageTitle ?? $__siteName) ?></title>
<meta name="description" content="<?= e($metaDescription ?? 'Shop spiritual products, rudraksha, pooja items, sacred jewellery, and devotional essentials.') ?>">
<meta name="robots" content="<?= e($metaRobots ?? 'index, follow') ?>">
<?php $__seoKeywords = $seo['keywords'] ?? ''; if ($__seoKeywords !== ''): ?><meta name="keywords" content="<?= e($__seoKeywords) ?>"><?php endif; ?>

<?php if ($__favicon !== ''): ?><link rel="icon" type="<?= e($__faviconMime) ?>" href="<?= e($__favicon) ?>"><?php endif; ?>
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#3a0003">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e($__siteName) ?>">
<?php if ($__referenceSite || $__logo !== ''): ?>
<link rel="apple-touch-icon" href="<?= e($__referenceSite ? '/assets/images/logo-square.jpeg' : $__logo) ?>">
<?php endif; ?>
<link rel="canonical" href="<?= e($seo['canonical']) ?>">
<link rel="help" href="/ai-assistants" title="Connect an AI assistant">
<link rel="describedby" type="text/plain" href="/llms.txt" title="Advisory guide for AI assistants">
<meta property="og:type" content="<?= e($seo['og_type'] ?? 'website') ?>">
<meta property="og:site_name" content="<?= e($seo['og_site_name'] ?? $__siteName) ?>">
<meta property="og:title" content="<?= e($seo['og_title'] ?? $pageTitle) ?>">
<meta property="og:description" content="<?= e($seo['og_description'] ?? $metaDescription) ?>">
<meta property="og:url" content="<?= e($seo['og_url']) ?>">
<?php if (!empty($seo['og_image'])): ?>
<meta property="og:image" content="<?= e($seo['og_image']) ?>">
<?php if ($__referenceSite): ?>
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<?php endif; ?>
<?php endif; ?>
<meta property="og:locale" content="en_IN">
<?php if (!empty($seo['twitter_site'])): ?><meta name="twitter:site" content="<?= e($seo['twitter_site']) ?>"><?php endif; ?>
<meta name="twitter:card" content="<?= !empty($seo['twitter_image']) ? 'summary_large_image' : 'summary' ?>">
<meta name="twitter:title" content="<?= e($seo['twitter_title'] ?? $seo['og_title'] ?? $pageTitle) ?>">
<meta name="twitter:description" content="<?= e($seo['twitter_description'] ?? $seo['og_description'] ?? $metaDescription) ?>">
<?php if (!empty($seo['twitter_image'])): ?><meta name="twitter:image" content="<?= e($seo['twitter_image']) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,600;1,600&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,600;1,600&display=swap" rel="stylesheet"></noscript>
<style>
<?php
// Inline critical CSS for instant first paint — header, nav, hero, product cards, mobile nav
$critical = '
:root{--color-ink:#222222;--color-ink-light:#3f3f3f;--color-gold:#d1b368;--color-gold-light:#f3e8c9;--color-gold-dark:#b89440;--color-maroon:#3a0003;--color-maroon-deep:#240002;--color-accent:#7a4a35;--color-accent-light:#a67a64;--color-bg:#faf7f0;--color-bg-alt:#f7f0e4;--color-bg-warm:#f6ede4;--color-border:#d8ccb7;--color-border-light:#eadfcd;--color-text-muted:#6a6259;--color-white:#ffffff;--color-success:#2d8a4e;--color-error:#d64045;--color-rating:#d68641;--font-display:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;--font-serif:Georgia,"Times New Roman",serif;--font-accent:"Playfair Display",Georgia,serif;--shadow-sm:0 1px 2px rgba(0,0,0,0.08);--shadow-md:0 2px 8px rgba(0,0,0,0.12);--shadow-lg:0 6px 20px rgba(34,12,8,0.14);--shadow-xl:0 12px 32px rgba(34,12,8,0.16);--radius-md:8px;--radius-lg:8px;--radius-xl:8px;--radius-pill:999px;--space-xs:0.5rem;--space-sm:0.75rem;--space-md:1rem;--space-lg:1.5rem;--space-xl:2rem;--space-2xl:3rem}
*,*::before,*::after{box-sizing:border-box;-webkit-font-smoothing:antialiased}
html{scroll-behavior:smooth}
body{margin:0;font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:var(--color-bg);color:var(--color-ink);line-height:1.55;overflow-x:hidden}
a{color:var(--color-maroon);text-decoration:none}
img{max-width:100%;height:auto;display:block}
.site-header{position:relative;top:auto;z-index:100;display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:var(--space-lg);min-height:80px;padding:12px 24px;background:rgba(250,247,240,0.98);border-bottom:1px solid rgba(209,179,104,0.45);transition:box-shadow 0.25s ease}
.site-header.scrolled{box-shadow:var(--shadow-md)}
.brand{display:flex;align-items:center;gap:var(--space-xs);color:var(--color-ink);font-weight:700;font-size:1rem;text-decoration:none}
 .brand img{width:40px;height:40px;border-radius:50%;border:2px solid var(--color-gold);object-fit:cover;box-shadow:0 0 0 1px rgba(255,255,255,0.9) inset}
nav:where(#primary-nav){display:flex;gap:var(--space-lg);font-size:0;justify-content:center}
nav:where(#primary-nav) a{position:relative;font-weight:500;color:var(--color-ink);padding:var(--space-xs) 0;font-size:0.9rem;text-decoration:none}
nav:where(#primary-nav) a:hover,nav:where(#primary-nav) a[aria-current="page"]{color:var(--color-ink)}
nav:where(#primary-nav) a[aria-current="page"]::after{position:absolute;right:0;bottom:-7px;left:0;height:2px;border-radius:999px;background:var(--color-gold);content:""}
@media(min-width:861px){.site-header{grid-template-columns:auto minmax(0,1fr) auto}.site-header #primary-nav{min-inline-size:0;flex-wrap:wrap;row-gap:var(--space-xs)}}
.header-page-title{display:block;max-inline-size:14ch;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
@media(max-width:860px){.header-page-title{max-inline-size:100%;white-space:normal;overflow-wrap:anywhere}}
.nav-dropdown{position:relative;display:inline-flex}
.nav-dropdown__trigger{padding-right:4px!important;cursor:default}
.nav-dropdown__arrow{font-size:0.65rem;margin-left:2px;opacity:0.6}
.nav-dropdown__menu{position:absolute;top:100%;left:50%;transform:translateX(-50%);min-width:160px;background:var(--color-white);border:1px solid var(--color-border);border-radius:var(--radius-md);box-shadow:var(--shadow-lg);opacity:0;visibility:hidden;transition:opacity 0.2s ease,visibility 0.2s ease;z-index:200;padding:6px 0;margin-top:8px}
.nav-dropdown__menu::before{content:"";position:absolute;top:-6px;left:50%;transform:translateX(-50%);border:6px solid transparent;border-bottom-color:var(--color-white);filter:drop-shadow(0 -1px 1px rgba(0,0,0,0.1))}
.nav-dropdown__menu a{display:block;padding:8px 18px;font-size:0.85rem;color:var(--color-ink);white-space:nowrap;text-decoration:none}
.nav-dropdown__menu a:hover{background:var(--color-bg-alt);color:var(--color-maroon)}
.nav-dropdown:hover .nav-dropdown__menu,.nav-dropdown:focus-within .nav-dropdown__menu{opacity:1;visibility:visible}
@media(max-width:768px){.nav-dropdown__menu{position:static;transform:none;box-shadow:none;border:0;opacity:1;visibility:visible;margin:0;padding:0 0 0 16px;background:transparent}
.nav-dropdown__menu::before{display:none}
.nav-dropdown__menu a{padding:6px 0;font-size:0.85rem}}
.header-actions{display:flex;align-items:center;gap:4px}
.header-actions .search-btn{display:inline-grid;place-items:center;width:44px;height:44px;border:0;border-radius:var(--radius-pill);background:transparent;color:var(--color-ink);cursor:pointer}
.header-actions .search-btn[hidden],.site-search-popup:not([open]){display:none}
.cart-btn{background:transparent;border:0;font-size:1.3rem;cursor:pointer;position:relative;color:var(--color-ink);padding:var(--space-xs);border-radius:var(--radius-md)}
 .cart-count{position:absolute;top:-6px;right:-8px;background:var(--color-maroon);color:var(--color-white);font-size:0.6rem;width:16px;height:16px;border-radius:4px;display:flex;align-items:center;justify-content:center;font-weight:bold}
.menu-toggle{display:none;border:1px solid var(--color-border);background:var(--color-white);padding:var(--space-xs) var(--space-sm);border-radius:var(--radius-md);cursor:pointer;font-size:1.2rem}
main{padding-bottom:0}
.container{max-width:1300px;margin:0 auto;padding:0 var(--space-xl)}
.section{padding:64px 0}
.section--alt{background:var(--color-bg-alt)}
.home-hero{position:relative;min-height:560px;padding:48px 5vw;background:var(--color-maroon-deep);color:var(--color-white);overflow:hidden}
.hero-copy h1{font-family:Inter,system-ui,sans-serif;font-size:1.75rem;line-height:1.2;margin:0 0 var(--space-md);color:var(--color-ink)}
.lede{font-size:1rem;line-height:1.7;color:var(--color-text-muted);margin-bottom:var(--space-lg)}
 .btn{display:inline-flex;align-items:center;justify-content:center;gap:var(--space-xs);min-height:48px;padding:0 24px;border-radius:8px;font-weight:600;cursor:pointer;border:0;text-decoration:none;font-size:0.85rem;white-space:nowrap;line-height:1.4;transition:background 0.2s ease,border-color 0.2s ease}
.btn-primary{background:var(--color-maroon);color:var(--color-white);box-shadow:none}
.btn-primary:hover{background:var(--color-maroon-deep)}
.btn-outline{background:var(--color-white);border:1px solid var(--color-border);color:var(--color-ink)}
.hero-actions{display:flex;gap:var(--space-md);margin-bottom:var(--space-xl)}
.section-header{text-align:center;margin-bottom:var(--space-2xl)}
.section-title{font-family:Inter,system-ui,sans-serif;font-size:clamp(1.375rem,2.5vw,1.75rem);margin:0;padding:0 0 var(--space-sm)}
.section-title::before,.section-title::after{display:none}
.section-header .lede{color:var(--color-text-muted);max-width:500px;margin:var(--space-sm) auto 0;font-size:0.9rem}
.product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:var(--space-xl)}
 .product-card{background:var(--color-white);border:1px solid var(--color-border-light);border-radius:var(--radius-md);overflow:hidden;transition:box-shadow 0.2s ease,border-color 0.2s ease;box-shadow:none}
.product-card:hover{box-shadow:var(--shadow-md);border-color:var(--color-border)}
.product-card__image{position:relative;overflow:hidden;aspect-ratio:1}
.product-card__image{background:var(--color-bg-alt)}
.product-card__image img{width:100%;height:100%;object-fit:cover;transition:transform 0.4s ease}
.product-card:hover .product-card__image img{transform:scale(1.05)}
.product-card__badge{position:absolute;top:var(--space-sm);left:var(--space-sm);padding:0.2rem 0.6rem;border-radius:var(--radius-pill);font-size:0.65rem;font-weight:700;text-transform:uppercase}
.product-card__badge--sale{background:var(--color-error);color:var(--color-white)}
.product-card__body{padding:var(--space-md)}
.product-card h3{font-family:Inter,system-ui,sans-serif;font-size:0.95rem;margin:0 0 var(--space-xs);color:var(--color-ink)}
.product-card__desc{font-size:0.8rem;color:var(--color-text-muted);margin-bottom:var(--space-sm);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.product-card__price-row{display:flex;align-items:center;gap:var(--space-xs);margin-bottom:var(--space-sm)}
.price{font-weight:700;color:var(--color-maroon);font-size:1.1rem}
.old-price{text-decoration:line-through;color:var(--color-text-muted);font-size:0.85rem}
.product-card__actions{display:flex;gap:var(--space-xs);align-items:center}
.product-card__actions .btn{flex:1}
.product-card__add-form{display:flex;flex:1;margin:0}
.product-card__add-form .btn{width:100%}
.product-card__actions .product-card__form{display:flex;align-items:center;gap:6px;margin-left:auto}
.product-card__stepper{height:30px;min-height:30px;border:1.5px solid var(--color-gold);background:var(--color-gold-light);border-radius:999px;overflow:hidden}
.product-card__stepper form{display:flex;height:100%;margin:0}
.product-card__stepper button{width:30px;height:100%;border:0;background:transparent;color:var(--color-maroon);cursor:pointer;font-size:.9rem;line-height:1;padding:0}
.product-card__stepper button:disabled{color:var(--color-text-light);cursor:default;opacity:.55}
.qty-input__value{min-width:22px;color:var(--color-maroon);font-size:.8rem;font-weight:700;text-align:center}
.feature-strip{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:var(--space-xl)}
.panel{background:var(--color-white);border:1px solid var(--color-border);border-radius:var(--radius-lg);padding:var(--space-xl);transition:all 0.25s ease;box-shadow:var(--shadow-sm)}
.panel:hover{box-shadow:var(--shadow-md)}
.astrologer-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:var(--space-xl)}
 .astrologer-card{background:var(--color-white);border:1px solid var(--color-border);border-radius:18px;overflow:hidden;transition:all 0.3s ease;box-shadow:var(--shadow-sm)}
.astrologer-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-xl);border-color:rgba(58, 0, 3,0.65)}
.astrologer-card__media{position:relative;aspect-ratio:3/4;overflow:hidden;background:linear-gradient(180deg,rgba(34, 34, 34,0.02),rgba(34, 34, 34,0.08)),linear-gradient(135deg,rgba(58, 0, 3,0.12),rgba(255,255,255,0.2))}
 .astrologer-card__photo{width:100%;height:100%;object-fit:cover;object-position:center top;display:block;transform:scale(1.01)}
.astrologer-card__media::after{content:\'\';position:absolute;inset:auto 0 0 0;height:42%;background:linear-gradient(180deg,rgba(18,12,8,0),rgba(18,12,8,0.28));pointer-events:none}
.astrologer-card__media-badge{position:absolute;left:var(--space-sm);bottom:var(--space-sm);z-index:1;padding:0.3rem 0.65rem;border-radius:var(--radius-pill);background:rgba(34, 34, 34,0.78);color:var(--color-white);font-size:0.64rem;letter-spacing:0.08em;text-transform:uppercase;backdrop-filter:blur(8px)}
.astrologer-card__body--portrait{padding:var(--space-md) var(--space-md) var(--space-sm);display:grid;gap:var(--space-xs)}
.astrologer-card__title-row{display:flex;justify-content:space-between;align-items:flex-start;gap:var(--space-sm)}
.astrologer-card__status{padding:0.22rem 0.55rem;border-radius:var(--radius-pill);background:rgba(58, 0, 3,0.16);color:var(--color-maroon);font-size:0.64rem;font-weight:700;text-transform:uppercase;white-space:nowrap}
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
 .category-img-wrap{width:clamp(124px,14vw,178px);height:clamp(124px,14vw,178px);border-radius:50%;overflow:hidden;margin:0 auto var(--space-xs);border:4px solid var(--color-white);background:radial-gradient(circle at 50% 50%,rgba(224, 11, 65,0.92),rgba(34, 34, 34,0.96));box-shadow:0 4px 15px rgba(34, 34, 34,0.1)}
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
.astro-search,.astro-filter{min-height:64px;border-radius:var(--radius-pill);background:var(--color-white);border-color:var(--color-border);box-shadow:var(--shadow-sm)}
.consultation-pricing-card{grid-column:1/-1;display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:var(--space-xl);padding:var(--space-lg) var(--space-xl);border:1px solid var(--color-border);border-radius:var(--radius-md);background:var(--color-white);color:var(--color-ink);box-shadow:var(--shadow-sm)}
.consultation-pricing-card h2{margin:0 0 var(--space-xs);font-size:1.35rem}.consultation-pricing-card p{margin:0;color:var(--color-text-muted);max-width:58ch}.consultation-pricing-card__rates{display:flex;gap:var(--space-lg);align-items:stretch}.consultation-pricing-card__rates div{display:grid;gap:2px;min-width:130px;padding-left:var(--space-lg);border-left:1px solid var(--color-border)}.consultation-pricing-card__rates strong{font-size:1.35rem;color:var(--color-maroon)}.consultation-pricing-card__rates span{font-size:.78rem;color:var(--color-text-muted)}
.astrologer-card,.panel{border-color:var(--color-border-light);border-radius:var(--radius-md);box-shadow:none}
.astrologer-card:hover,.panel:hover{transform:none;border-color:var(--color-border);box-shadow:var(--shadow-md)}
.support-fab{background:var(--color-maroon);box-shadow:var(--shadow-md)}
.site-footer{background:var(--color-maroon);color:var(--color-gold-light);padding:var(--space-2xl) 0 var(--space-md);font-size:0.85rem;border-top:1px solid var(--color-gold)}
.footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:var(--space-xl);margin-bottom:var(--space-xl)}
.footer-brand{font-family:Inter,system-ui,sans-serif;font-size:1.2rem;color:var(--color-gold);font-weight:600;display:block;margin-bottom:var(--space-xs)}
.footer-desc{font-size:0.85rem;line-height:1.6;color:var(--color-gold-light);opacity:1}
.footer-heading{font-size:0.75rem;text-transform:uppercase;letter-spacing:0;color:var(--color-gold);margin:0 0 var(--space-sm)}
.footer-links{list-style:none;padding:0;margin:0}
.footer-links li{margin-bottom:var(--space-xs)}
.footer-links a{color:var(--color-gold-light);text-decoration:none;font-size:0.85rem}
.footer-links a:hover{color:var(--color-white)}
.footer-bottom{text-align:center;padding-top:var(--space-md);border-top:1px solid rgba(209,179,104,0.3);font-size:0.75rem;color:var(--color-gold-light)}
.flash{padding:var(--space-md);border-radius:var(--radius-md);margin-bottom:var(--space-md);font-size:0.85rem;font-weight:500}
.flash--success{background:#e8f5ed;color:var(--color-success)}
.flash--error{background:#fde8e9;color:var(--color-error)}
.flash--info{background:#eff6ff;color:#3b82f6}
.reveal{opacity:1;transform:none}
.motion-ready .reveal{opacity:0;transform:translateY(16px);transition:opacity .45s ease,transform .45s ease}
.motion-ready .reveal.revealed{opacity:1;transform:translateY(0)}
@media(prefers-reduced-motion:reduce){.motion-ready .reveal{opacity:1;transform:none;transition:none}}
@media(max-width:860px){
nav:where(#primary-nav){display:none;position:absolute;top:100%;left:0;right:0;background:rgba(255,255,255,0.98);flex-direction:column;padding:var(--space-lg);border-bottom:1px solid var(--color-border);box-shadow:var(--shadow-lg)}
nav:where(#primary-nav) a{font-size:0.95rem;padding:var(--space-sm) var(--space-md);border-radius:var(--radius-md)}
nav:where(#primary-nav).open{display:flex}
.menu-toggle{display:block}
.home-hero{grid-template-columns:1fr;text-align:center;width:100vw;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);padding:20px 16px 28px}
.hero-actions{justify-content:center}
.category-grid{grid-template-columns:repeat(2,minmax(0,128px));justify-content:center;gap:var(--space-md)}
.temple-scroll{grid-auto-columns:minmax(82vw,1fr);gap:var(--space-md);padding-left:var(--space-sm);padding-right:var(--space-sm)}
.temple-slide{grid-template-columns:1fr;min-height:0;gap:var(--space-md)}
.temple-slide__media,.temple-slide__media img{min-height:190px}
.temple-slide__copy{text-align:center}
.footer-grid{grid-template-columns:1fr 1fr}
.bottom-nav{display:block}
.main-content{padding-bottom:calc(60px + var(--space-md))}
/* Mobile header order: brand left, then search and cart, then the menu button hard right. */
.site-header{display:flex;align-items:center;gap:4px}
.site-header .brand{order:1;margin-right:auto}
.site-header .header-actions{order:2}
.site-header .menu-toggle{order:3;width:46px;height:46px;display:inline-flex;align-items:center;justify-content:center}
.cart-btn{width:44px;height:44px;display:inline-flex;align-items:center;justify-content:center}
.site-header .brand span{display:block}
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
@media(max-width:743px){.site-header{min-height:64px;padding:8px 12px}.section{padding:48px 0}.home-hero{min-height:auto;padding:20px 16px 28px}.astro-search,.astro-filter{min-height:56px}.consultation-pricing-card{grid-template-columns:1fr;gap:var(--space-md);padding:var(--space-md)}.consultation-pricing-card__rates{gap:var(--space-sm)}.consultation-pricing-card__rates div{min-width:0;flex:1;padding-left:var(--space-sm)}}
';
echo $critical;
?>
</style>
<link rel="stylesheet" href="/assets/css/band.css?v=<?= filemtime(__DIR__ . '/../../assets/css/band.css') ?>">
<script src="/assets/agent-status.js?v=<?= filemtime(__DIR__ . '/../../assets/agent-status.js') ?>" defer></script>
<style>
<?php
$__palette_semantic = [
    '--color-primary' => ['set' => ($__settings['palette_primary'] ?? '#3A0003'), 'alias' => '--color-maroon'],
    '--color-secondary' => ['set' => ($__settings['palette_secondary'] ?? '#D1B368'), 'alias' => '--color-gold'],
    '--color-canvas' => ['set' => ($__settings['palette_canvas'] ?? '#FAF7F0'), 'alias' => '--color-bg'],
    '--color-text-primary' => ['set' => ($__settings['palette_text'] ?? '#222222'), 'alias' => '--color-ink'],
    '--color-link' => ['set' => ($__settings['palette_link'] ?? '#3A0003'), 'alias' => ''],
];
$__palette_css = ':root{';
foreach ($__palette_semantic as $__n => $__c) {
    $__v = $__c['set'];
    $__palette_css .= $__n . ':' . e($__v) . ';';
    if ($__c['alias'] !== '') $__palette_css .= $__c['alias'] . ':' . e($__v) . ';';
}
$__palette_css .= '}';
echo $__palette_css;
?>
</style>
<style>
.document-page__header{max-width:760px;margin:0 auto var(--space-2xl)}.document-page__content{max-width:760px;margin:0 auto;padding:clamp(24px,4vw,48px);border:1px solid var(--color-border);border-radius:var(--radius-lg);background:var(--color-white);box-shadow:var(--shadow-sm)}.document-page__content h2{margin:2rem 0 .65rem;color:var(--color-maroon);font-size:1.35rem}.document-page__content h2:first-child{margin-top:0}.document-page__content p,.document-page__content li{color:var(--color-text-muted);line-height:1.7}.document-page__content ul{padding-left:1.25rem}.document-index{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--space-md)}.document-index__item{display:grid;gap:var(--space-xs);padding:var(--space-lg);border:1px solid var(--color-border);border-radius:var(--radius-md);background:var(--color-white);color:inherit;text-decoration:none;box-shadow:var(--shadow-sm);transition:transform var(--transition-base),box-shadow var(--transition-base),border-color var(--transition-base)}.document-index__item:hover{transform:translateY(-3px);border-color:var(--color-gold);box-shadow:var(--shadow-md)}.document-index__item h2{margin:0;color:var(--color-maroon);font-size:1.15rem}.document-index__item p{margin:0;color:var(--color-text-muted);line-height:1.55}.document-index__link{color:var(--color-maroon);font-weight:700;font-size:.82rem;margin-top:var(--space-sm)}
.blog-page{padding:var(--space-3xl) 0}.blog-page .container{max-width:1180px}.page-title{margin:0 0 var(--space-lg);font-size:clamp(1.8rem,4vw,2.6rem);color:var(--color-ink)}.blog-categories{display:flex;flex-wrap:wrap;gap:var(--space-sm);margin-bottom:var(--space-2xl)}.blog-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:var(--space-xl)}.blog-card{overflow:hidden;border:1px solid var(--color-border);border-radius:var(--radius-md);background:var(--color-white);box-shadow:var(--shadow-sm)}.blog-card__body{display:grid;gap:var(--space-sm);padding:var(--space-lg)}.blog-card__title{margin:0;font-size:1.15rem;line-height:1.3}.blog-card__title a{color:var(--color-maroon)}.blog-card__excerpt{margin:0;color:var(--color-text-muted);line-height:1.55}.blog-card__date{font-size:.8rem;color:var(--color-text-soft)}.blog-card__category{font-size:.72rem;color:var(--color-maroon);font-weight:700;text-transform:uppercase}
@media(max-width:900px){.blog-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.document-index,.blog-grid{grid-template-columns:1fr}.blog-page{padding:var(--space-2xl) 0}}
</style>
<noscript><link rel="stylesheet" href="/assets/css/band.css?v=<?= filemtime(__DIR__ . '/../../assets/css/band.css') ?>"></noscript>
<?= $seo['organization_json_ld'] ?>
<?php if (!empty($seo['json_ld'])): ?><?= $seo['json_ld'] ?><?php endif; ?>
<?php
$__secrets = (new \App\Services\SecretService())->all();
// Meta Pixel
$__metaPixelId = $__secrets['meta_pixel_id'] ?? '';
if ($__metaPixelId !== ''):
?>
<!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '<?= e($__metaPixelId) ?>');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id=<?= e($__metaPixelId) ?>&ev=PageView&noscript=1"
/></noscript>
<!-- End Meta Pixel Code -->
<?php endif;
// The public head and owner status share one validating implementation.
$__googleTags=new \App\Integrations\GoogleSiteKit\GoogleSiteKitClient(
    (string)($__secrets['google_analytics_id']??''),(string)($__secrets['google_ads_id']??''),(string)($__secrets['google_site_verification']??''));
echo $__googleTags->siteVerificationTag();
echo $__googleTags->gtagCode();
?>
<link rel="stylesheet" href="/assets/css/site-motion.css?v=<?= filemtime(__DIR__ . '/../../assets/css/site-motion.css') ?>">
<script src="/assets/site-motion.js?v=<?= filemtime(__DIR__ . '/../../assets/site-motion.js') ?>" defer></script>
<link rel="stylesheet" href="/assets/css/page-actions.css?v=<?= filemtime(app_path('assets/css/page-actions.css')) ?>">
<link rel="stylesheet" href="/assets/css/a2ui-public.css?v=<?= filemtime(app_path('assets/css/a2ui-public.css')) ?>">
<script defer src="/assets/a2ui-public-renderer.js?v=<?= filemtime(app_path('assets/a2ui-public-renderer.js')) ?>"></script>
<?php
try { $__supportCommerceOrigin=\App\Services\PublicSiteUrlService::origin(); }
catch (\Throwable) { $__supportCommerceOrigin=null; }
?>
<link rel="stylesheet" href="/assets/css/chat-commerce.css?v=<?= filemtime(app_path('assets/css/chat-commerce.css')) ?>">
<link rel="stylesheet" href="/assets/css/support-checkout.css?v=<?= filemtime(app_path('assets/css/support-checkout.css')) ?>">
<script type="application/json" id="bapx-support-commerce-config"><?= json_encode([
    'publicOrigin'=>$__supportCommerceOrigin,'protocolVersion'=>\App\Services\PublicMcpService::PROTOCOL,
    'deliveryStates'=>\App\Services\TaxService::states(), 'canShop'=>$__modules['shop']
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script defer src="/assets/checkout-form.js?v=<?= filemtime(app_path('assets/checkout-form.js')) ?>"></script>
<script defer src="/assets/public-mcp-shopping.js?v=<?= filemtime(app_path('assets/public-mcp-shopping.js')) ?>"></script>
<script defer src="/assets/support-checkout.js?v=<?= filemtime(app_path('assets/support-checkout.js')) ?>"></script>
<script src="/assets/page-actions.js?v=<?= filemtime(app_path('assets/page-actions.js')) ?>" defer></script>
<noscript><style>
.site-header>noscript,#primary-nav>noscript,.nojs-menu-open,.nojs-menu-close{display:none}
@media(max-width:860px){
    .site-header button.menu-toggle{display:none}
    .site-header>noscript{display:block;order:3}
    #primary-nav>noscript{display:contents}
    .site-header .nojs-menu-open,#primary-nav .nojs-menu-close{display:inline-flex;align-items:center;min-height:44px;padding-inline:12px}
    .site-header #primary-nav:target{display:flex;max-height:75vh;overflow-y:auto}
}
</style></noscript>
<!-- Pinned official W3C WebMCP polyfill. Does not replace a native modelContext. -->
<script src="/assets/vendor/webmcp/polyfill-0.1.1.js?v=<?= filemtime(app_path('assets/vendor/webmcp/polyfill-0.1.1.js')) ?>"></script>
<?php
// Reuse the real HTTP registry, including status recovery after Shop is disabled.
$__commerceToolNames=['create_cart','get_cart','set_cart_quantity','review_checkout','prepare_checkout_payment','get_checkout_status'];
try {
    $__commerceTools=array_values(array_filter((new \App\Services\PublicMcpService())->toolDefinitions(),
        static fn(array $tool):bool=>in_array($tool['name'],$__commerceToolNames,true)));
} catch (\Throwable) { $__commerceTools=[]; }
?>
<script type="application/json" id="bapx-public-commerce-schema"><?= json_encode([
    'publicOrigin'=>$__supportCommerceOrigin, 'protocolVersion'=>\App\Services\PublicMcpService::PROTOCOL, 'tools'=>$__commerceTools
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script defer src="/assets/public-commerce-webmcp.js?v=<?= filemtime(app_path('assets/public-commerce-webmcp.js')) ?>"></script>
<script type="application/json" id="bapx-public-business-schema"><?= json_encode([
    'contact'=>[
        'inputSchema'=>\App\Services\PublicKnowledgeService::contactInputSchema(),
        'outputSchema'=>\App\Services\PublicKnowledgeService::contactOutputSchema()
    ],
    'services'=>[
        'inputSchema'=>\App\Services\PublicKnowledgeService::serviceInputSchema(),
        'outputSchema'=>\App\Services\PublicKnowledgeService::serviceOutputSchema()
    ]
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script defer src="/assets/public-business-webmcp.js?v=<?= filemtime(app_path('assets/public-business-webmcp.js')) ?>"></script>
<script type="application/json" id="bapx-a2ui-schema"><?= json_encode([
    'inputSchema'=>\App\Services\PublicA2uiService::inputSchema(),
    'outputSchema'=>\App\Services\PublicA2uiService::outputSchema()
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script defer src="/assets/public-a2ui-webmcp.js?v=<?= filemtime(app_path('assets/public-a2ui-webmcp.js')) ?>"></script>

<?php if ($__modules['shop']): ?>
<?php
// Invalid public connection configuration must not take the normal storefront down.
try { $__publicProductsOrigin=\App\Services\PublicSiteUrlService::origin(); }
catch (\RuntimeException) { $__publicProductsOrigin=null; }
?>
<script type="application/json" id="bapx-public-products-schema"><?= json_encode([
    'publicOrigin'=>$__publicProductsOrigin,
    'inputSchema'=>\App\Services\PublicKnowledgeService::productInputSchema(),
    'outputSchema'=>\App\Services\PublicKnowledgeService::productOutputSchema()
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script defer src="/assets/public-products-webmcp.js?v=<?= filemtime(__DIR__ . '/../../assets/public-products-webmcp.js') ?>"></script>
<?php endif; ?>
<?php if ($__modules['blog']): ?>
<script type="application/json" id="bapx-public-knowledge-schema"><?= json_encode([
    'inputSchema'=>\App\Services\PublicKnowledgeService::articleInputSchema(),
    'outputSchema'=>\App\Services\PublicKnowledgeService::articleOutputSchema()
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script defer src="/assets/public-knowledge-webmcp.js?v=<?= filemtime(__DIR__ . '/../../assets/public-knowledge-webmcp.js') ?>"></script>
<?php endif; ?>
</head>
<body>
<?php $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; ?>
<?php $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16)); ?>
<header class="site-header" id="site-header" tabindex="-1">
    <a href="/" class="brand">
        <?php if ($__logo !== ''): ?><img src="<?= e($__logo) ?>" width="52" height="52" alt="<?= e($__siteName . ' logo') ?>"><?php endif; ?>
        <span><?= e($__siteName) ?></span>
    </a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-nav" aria-label="Menu">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <?php
    $__blogCats = [];
    if ($__modules['blog']) {
        try { $__blogCats = (new \App\Services\BlogService())->categories(); } catch (\Throwable) {}
    }
    ?>
    <noscript><a class="nojs-menu-open" href="#primary-nav">Menu</a></noscript>
    <nav id="primary-nav" aria-label="Main navigation" tabindex="-1">
        <noscript><a class="nojs-menu-close" href="#site-header">Close menu</a></noscript>
        <?php foreach ($__settingsService->publicHeaderMenu() as $linkId=>$link):
            if ($linkId === 'about') $link['label'] = $__aboutLabel; ?>
            <?php $active = $currentPath === $link['path'] || str_starts_with($currentPath, $link['path'] . '/'); ?>
            <?php if ($linkId === 'blog'): ?>
                <div class="nav-dropdown">
                    <a href="/blog" class="nav-dropdown__trigger"<?= $active ? ' aria-current="page"' : '' ?>><?= e($link['label']) ?> <span class="nav-dropdown__arrow">▾</span></a>
                    <div class="nav-dropdown__menu">
                        <a href="/blog">All Posts</a>
                        <?php foreach ($__blogCats as $__cat): ?>
                            <a href="/blog/category/<?= e($__cat['slug'] ?? '') ?>"><?= e($__cat['name'] ?? '') ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= e($link['path']) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                    <?php if (str_starts_with($linkId, 'pg:')): ?>
                        <span class="header-page-title" title="<?= e($link['label']) ?>"><?= e($link['label']) ?></span>
                    <?php else: ?>
                        <?= e($link['label']) ?>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if(!empty($_SESSION['user'])): ?>
            <a href="/account/dashboard"<?= str_starts_with($currentPath, '/account/dashboard') ? ' aria-current="page"' : '' ?>>Dashboard</a>
            <a href="/logout">Logout</a>
        <?php else: ?>
            <a href="/login">Login</a>
        <?php endif; ?>
    </nav>
    <div class="header-actions">
        <?php $__searchIcon = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/></svg>'; ?>
        <a href="/search" class="search-btn" id="header-search-link" aria-label="Search"><?= $__searchIcon ?></a>
        <button type="button" class="search-btn" id="header-search-button" aria-label="Search" aria-haspopup="dialog" aria-controls="header-search-popup" hidden><?= $__searchIcon ?></button>
        <a href="/cart" class="cart-btn" aria-label="Cart">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
            <?php $cartCount = 0; if(!empty($_SESSION['cart'])){foreach($_SESSION['cart'] as $c){$cartCount += $c['qty'] ?? 1;}} ?><span class="cart-count"><?= $cartCount ?></span>
        </a>
    </div>
</header>
<dialog class="site-search-popup" id="header-search-popup" aria-label="Search the site" closedby="any">
    <form class="site-search-popup__form" role="search" action="/search" method="get">
        <input class="site-search-popup__input" type="search" name="q" maxlength="120" aria-label="Search by name or keyword"
            placeholder="Search by name or keyword" value="<?= e($currentPath === '/search' ? ($query ?? '') : '') ?>" required autofocus>
        <button class="site-search-popup__submit" type="submit">Search</button>
        <button class="site-search-popup__close" type="button" aria-label="Close search">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
        </button>
    </form>
</dialog>
<main class="<?= $currentPath === '/' ? 'home-main' : ($currentPath === '/about' ? 'about-main' : '') ?>">
<?php
$__flash = $_SESSION['flash'] ?? null;
if ($__flash):
    $__msg = is_array($__flash) ? $__flash['message'] : $__flash;
    $__type = is_array($__flash) ? ($__flash['type'] ?? 'info') : 'info';
    unset($_SESSION['flash']);
?>
<script>document.addEventListener('DOMContentLoaded',function(){showToast(<?= json_encode($__msg) ?>,<?= json_encode($__type) ?>);});</script>
<?php endif; ?>
<?php require $viewFile; ?>
<?php $publicHelp=\App\Services\AdminHelpService::publicContext($currentPath); ?>
<?php if($publicHelp!==null&&module_on('blog')): ?>
<aside class="public-help-hint container" aria-label="Help for this task" data-public-context-help>
    <div><strong><?= e($publicHelp['title']) ?></strong><p><?= e($publicHelp['summary']) ?></p></div>
    <a class="help-context-link" href="<?= e($publicHelp['url']) ?>">Read guide <span aria-hidden="true">→</span></a>
</aside>
<?php endif; ?>

</main>

<?php /* The redundant mobile bottom navigation was removed; the header remains the primary site navigation. */ ?>

<?php if ($__modules['shop'] && (in_array(rtrim($currentPath, '/'), ['', '/shop', '/categories'], true) || str_starts_with($currentPath, '/product/'))): ?>
<a href="/cart" class="mobile-cart-tray" id="mobile-cart-tray" <?= $cartCount > 0 ? '' : 'hidden' ?>>
    <span><strong id="mobile-cart-count"><?= (int)$cartCount ?></strong> <span id="mobile-cart-label"><?= $cartCount === 1 ? 'item' : 'items' ?></span> in cart</span>
    <span>View cart <span aria-hidden="true">→</span></span>
</a>
<?php endif; ?>

<?php if (!in_array(rtrim($currentPath, '/'), ['/login', '/register', '/contact'], true)): ?>
<?php
    $__supportKey = '';
    if (session_status() === PHP_SESSION_ACTIVE) {
        $__supportActor = \App\Services\AgentConversationService::actor('support', $_SESSION['user'] ?? null);
        $__supportKey = 'sps-support-chat:' . hash_hmac('sha256',
            'support-browser-chat:v2:' . $__supportActor['type'] . ':' . $__supportActor['id'], session_id());
    }
?>
<button class="support-fab" type="button" aria-controls="support-panel" aria-expanded="false" title="Support">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>
    <span class="sr-only">Support</span>
</button>
<section class="support-panel" id="support-panel" data-support-key="<?= e($__supportKey) ?>" hidden>
    <div class="support-panel__head">
        <strong>Support</strong>
        <button type="button" id="support-commerce-toggle" class="support-commerce-toggle" aria-controls="support-commerce" aria-expanded="false" disabled><?= $__modules['shop'] ? 'Cart &amp; checkout' : 'Checkout status' ?></button>
        <button type="button" id="support-new-chat" class="btn btn-ghost btn-sm support-panel__new" disabled>New chat</button>
        <button type="button" class="support-panel__close" aria-label="Close support">×</button>
    </div>
    <div class="support-panel__body" id="support-log" aria-live="polite">
        <p>Ask about products, delivery, orders, or support tickets.</p>
        <?php if(empty($_SESSION['user'])): ?><p>Guest mode: you can ask about products and policies. Sign in only to check your own orders or support tickets.</p><?php endif; ?>
    </div>
    <section id="support-commerce" class="support-commerce" aria-label="Cart and checkout" hidden>
        <div class="support-commerce__head"><h2 tabindex="-1">Chat cart &amp; checkout</h2><button type="button" id="support-commerce-back" class="support-commerce-back">Back to chat</button></div>
        <p id="support-commerce-status" class="support-commerce__status" role="status" aria-live="polite"></p>
        <div id="support-commerce-content" class="support-commerce__content"></div>
        <p id="support-commerce-recovery" class="support-commerce__recovery">Delivery details stay in this form and are sent only when you review checkout.</p>
        <button type="button" id="support-commerce-reset" class="support-commerce-back">Clear chat shopping access</button>
    </section>
    <section id="support-enquiry" class="support-enquiry" aria-label="<?= e('Send an enquiry to ' . $__siteName) ?>" hidden>
        <div class="support-enquiry__head">
            <h2 tabindex="-1">Send an enquiry</h2>
            <button type="button" id="support-enquiry-back" class="support-commerce-back">Back to chat</button>
        </div>
        <p>New consultation bookings are not available. Describe what you need, including a preferred date if useful; the team can reply but no appointment is reserved.</p>
        <p id="support-enquiry-status" role="status" aria-live="polite"></p>
        <div id="support-enquiry-form-container"></div>
    </section>
    <?php if(module_on('blog')): ?><a class="support-help-guide" href="/blog/get-support">Read support guide</a><?php endif; ?>
    <form class="support-panel__form" id="support-form">
        <input type="hidden" name="_csrf" value="<?= e((string)($_SESSION['csrf_token'] ?? '')) ?>">
        <textarea name="message" rows="3" required aria-label="Support message" placeholder="Ask about a product, order, or ticket"></textarea>
        <button class="btn btn-primary btn-sm">Send</button>
    </form>
</section>
<?php endif; ?>

<?php if (!in_array($currentPath, ['/login', '/register'])): ?>
<footer class="site-footer" role="contentinfo">
    <div class="container">
        <div class="footer-grid">
            <div>
                <span class="footer-brand"><?= e($__siteName) ?></span>
                <?php if ($__footerDescription !== ''): ?><p class="footer-desc"><?= e($__footerDescription) ?></p><?php endif; ?>
            </div>
            <div>
                <h4 class="footer-heading">Shop</h4>
                <ul class="footer-links">
                    <?php if ($__modules['shop']): ?><li><a href="/shop">All Products</a></li><?php endif; ?>
                    <li><a href="/temples">Temples</a></li>
                    <li><a href="/about"><?= e($__aboutLabel) ?></a></li>
                    <?php if ($__modules['blog']): ?><li><a href="/blog">Blog</a></li><?php endif; ?>
                    <li><a href="/contact">Contact</a></li>
                    <li><a href="/terms">Terms</a></li>
                    <li><a href="/privacy">Privacy</a></li>
                    <li><a href="/ai-assistants">AI assistants</a></li>
                </ul>
            </div>
            <div>
                <h4 class="footer-heading">Explore</h4>
            <ul class="footer-links">
                <li><a href="/temples">Temples</a></li>
                <?php if ($__modules['blog']): ?>
                <li><a href="/blog">Blog</a></li>
                <?php foreach ($__blogCats as $__cat): ?>
                <li><a href="/blog/category/<?= e($__cat['slug'] ?? '') ?>"><?= e($__cat['name'] ?? '') ?></a></li>
                <?php endforeach; ?>
                <?php endif; ?>
                <li><a href="/about"><?= e($__aboutLabel) ?></a></li>
                <li><a href="/contact">Contact</a></li>
            </ul>
            </div>
            <div>
                <h4 class="footer-heading">Customer Support</h4>
                <ul class="footer-links">
                    <?php foreach ($__siteContact['phones'] as $phone): ?>
                    <li><a href="tel:<?= e($phone['number']) ?>"><?= e($phone['label']) ?></a></li>
                    <?php endforeach; ?>
                    <?php if (isset($__siteContact['email'])): ?>
                    <li><a href="mailto:<?= e($__siteContact['email']) ?>"><?= e($__siteContact['email']) ?></a></li>
                    <?php endif; ?>
                    <?php if (!$__siteContact['phones'] && !isset($__siteContact['email'])): ?>
                    <li><a href="/contact#contact-form">Send an enquiry</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">&copy; <?= date('Y') ?> <?= e($__siteName) ?> &middot; <a href="/terms">Terms</a> &middot; <a href="/privacy">Privacy</a> &middot; developed with &#10084;&#65039; by <a href="https://mediahub.bapx.in/" target="_blank" rel="noopener noreferrer">@bapxmediahub</a></div>
    </div>
</footer>
<?php endif; ?>
<script>
(() => {
    const popup = document.getElementById('header-search-popup');
    if (typeof popup.showModal !== 'function') return;

    const button = document.getElementById('header-search-button');
    button.addEventListener('click', () => {
        button.focus({preventScroll: true});
        popup.showModal();
    });
    popup.querySelector('.site-search-popup__close').addEventListener('click', () => popup.close());
    popup.querySelector('.site-search-popup__input').addEventListener('keydown', event => {
        if (event.key !== 'Escape' || event.isComposing) return;
        // Chromium otherwise clears a filled search field before the dialog can dismiss.
        event.preventDefault();
        popup.close();
    });

    // Older dialog implementations support Escape but do not light-dismiss with closedby.
    if (!('closedBy' in popup)) {
        const isBackdrop = event => {
            if (event.target !== popup) return false;
            const {left, right, top, bottom} = popup.getBoundingClientRect();
            return event.clientX < left || event.clientX > right || event.clientY < top || event.clientY > bottom;
        };
        let startedOutside = false;
        popup.addEventListener('pointerdown', event => { startedOutside = isBackdrop(event); });
        popup.addEventListener('click', event => {
            if (startedOutside && isBackdrop(event)) popup.close();
            startedOutside = false;
        });
    }
    document.getElementById('header-search-link').hidden = true;
    button.hidden = false;
})();

if ('serviceWorker' in navigator) {
    var swPath = '/sw.js';
    var swScope = '/';
    navigator.serviceWorker.register(swPath, { scope: swScope }).catch(function(){});
}
document.getElementById('site-header').querySelector('.menu-toggle').addEventListener('click',function(){
    var n=document.getElementById('primary-nav');n.classList.toggle('open');
    this.setAttribute('aria-expanded',n.classList.contains('open')?'true':'false');
});
document.addEventListener('click',function(e){
    var n=document.getElementById('primary-nav'),t=document.querySelector('.menu-toggle');
    if(!n.contains(e.target)&&!t.contains(e.target)){n.classList.remove('open');t.setAttribute('aria-expanded','false');}
});
// Shared progressive motion is installed by assets/site-motion.js.
document.addEventListener('error',function(event){
    var image=event.target;
    if(!(image instanceof HTMLImageElement)||!image.dataset.productImageFallback||image.dataset.fallbackApplied==='true')return;
    image.dataset.fallbackApplied='true';
    image.src=image.dataset.productImageFallback;
},true);
var supportFab=document.querySelector('.support-fab'),supportPanel=document.getElementById('support-panel'),supportClose=document.querySelector('.support-panel__close'),supportForm=document.getElementById('support-form'),supportLog=document.getElementById('support-log'),supportConversationId=null;
var supportNewChat=document.getElementById('support-new-chat'),supportInitialLog=supportLog?.innerHTML||'';
var supportCacheKey=supportPanel?.dataset.supportKey||'',supportCacheScope='sps-support-chat:active';
var supportPendingRequest=null,supportFeedbackPending=false,supportSessionChanged=false;
function supportRandomId(){var bytes=new Uint8Array(16);crypto.getRandomValues(bytes);return Array.from(bytes,function(x){return x.toString(16).padStart(2,'0');}).join('');}
function supportEscape(value){return String(value).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
function supportReplyHtml(value){var safe=supportEscape(value);var allowed=/\/(?:shop|cart|checkout|temples|contact|blog(?:\/[a-z0-9-]+|\/category\/[a-z0-9-]+)?|product\/(?:[a-z0-9-]|%20)+|account\/dashboard(?:\/orders|\/install)?)(?=$|[\s.,)])/gi;return safe.replace(allowed,function(path){return '<a class="support-action" href="'+path+'">Open '+supportEscape(path.replace(/^\//,'').replace(/%20|[-/]/g,' '))+'</a>';});}
function supportActionsHtml(actions){if(!actions||!actions.length)return'';var h='<div class="support-actions">';for(var i=0;i<actions.length;i++){var a=actions[i];if(a.type==='navigate'&&a.path){h+='<a class="btn btn-sm btn-outline support-action-btn" href="'+supportEscape(a.path)+'">'+supportEscape(a.label)+'</a>';}}return h+'</div>';}
var supportEnquiry=document.getElementById('support-enquiry'),
    supportEnquiryBack=document.getElementById('support-enquiry-back'),
    supportEnquiryStatus=document.getElementById('support-enquiry-status'),
    supportEnquiryContainer=document.getElementById('support-enquiry-form-container'),
    supportEnquiryLoading=false;
function supportCloseEnquiry(){
    if(!supportEnquiry||!supportPanel)return;
    supportPanel.dataset.enquiryOpen='false'; supportEnquiry.hidden=true;
}
async function supportOpenEnquiry(){
    if(!supportEnquiry||!supportPanel||!supportEnquiryContainer||supportEnquiryLoading)return;
    var commerceBack=document.getElementById('support-commerce-back');
    if(supportPanel.dataset.commerceOpen==='true'&&commerceBack)commerceBack.click();
    supportPanel.dataset.enquiryOpen='true';supportEnquiry.hidden=false;
    supportToggle(true);
    supportEnquiry.querySelector('h2')?.focus({preventScroll:true});
    if(supportEnquiryContainer.querySelector('form.contact-form'))return;
    supportEnquiryLoading=true;
    supportEnquiryStatus.textContent='Loading the existing contact form…';
    try{
        var response=await fetch('/contact?subject=general',{method:'GET',credentials:'same-origin',cache:'no-store',headers:{Accept:'text/html'}});
        if(!response.ok||!String(response.headers.get('Content-Type')||'').includes('text/html'))throw Error();
        var html=await response.text();if(html.length>500000)throw Error();
        var page=new DOMParser().parseFromString(html,'text/html');
        var source=page.querySelector('form.contact-form');
        if(!source||source.getAttribute('method')?.toLowerCase()!=='post')throw Error();
        var target=new URL(source.getAttribute('action')||'',location.href);
        if(target.origin!==location.origin||target.pathname!=='/contact')throw Error();
        var fields=Array.from(source.querySelectorAll('[name]'));
        if(fields.some(function(field){return !['_csrf','name','email','subject','phone','message'].includes(field.name);})
            || !['_csrf','name','email','subject','message'].every(function(name){return fields.some(function(field){return field.name===name;});}))throw Error();
        var form=document.importNode(source,true);
        form.action='/contact';form.id='support-enquiry-form';
        var subject=form.elements.namedItem('subject');
        if(subject && Array.from(subject.options||[]).some(function(option){return option.value==='general';}))subject.value='general';
        supportEnquiryContainer.replaceChildren(form);supportEnquiryStatus.textContent='';
    }catch(_){
        supportEnquiryStatus.textContent='The form could not be loaded here. The existing contact page is still available.';
        var fallback=document.createElement('a');
        fallback.href='/contact#contact-form';fallback.textContent='Open the contact form';
        supportEnquiryContainer.replaceChildren(fallback);
    }finally{supportEnquiryLoading=false;}
}
supportEnquiryBack?.addEventListener('click',function(){supportCloseEnquiry();supportLog?.focus?.();});
document.getElementById('support-commerce-toggle')?.addEventListener('click',supportCloseEnquiry);
supportLog?.addEventListener('click',function(event){
    var anchor=event.target instanceof Element?event.target.closest('a[href]'):null;
    if(!anchor)return;
    try{
        var dest=new URL(anchor.href,location.href);
        if(dest.origin===location.origin&&dest.pathname==='/contact'){
            event.preventDefault();void supportOpenEnquiry();
        }
    }catch(_){}
});
supportEnquiry?.addEventListener('submit',async function(event){
    var form=event.target;
    if(!(form instanceof HTMLFormElement)||!form.classList.contains('contact-form'))return;
    event.preventDefault();
    if(form.dataset.busy==='true'||!form.reportValidity())return;
    var submit=form.querySelector('button[type="submit"]');
    form.dataset.busy='true';if(submit)submit.disabled=true;
    supportEnquiryStatus.textContent='Sending your enquiry…';
    try{
        var response=await fetch('/contact',{method:'POST',credentials:'same-origin',
            headers:{Accept:'text/html'},body:new FormData(form),redirect:'follow'});
        var reply=await response.text();
        if(reply.length>500000)throw Error();
        var page=new DOMParser().parseFromString(reply,'text/html');
        if(response.ok&&new URL(response.url).origin===location.origin
            &&page.querySelector('[data-contact-feedback]')?.textContent?.trim().startsWith('Message received')){
            supportEnquiryContainer.replaceChildren();
            supportEnquiryStatus.textContent='Your enquiry was received. The team can reply, but no appointment has been booked.';
        }else if(response.status===422){
            supportEnquiryStatus.textContent=page.querySelector('.contact-form__errors')?.textContent?.trim()
                ||'Please check the required fields and try again.';
        }else if(response.status===429){
            supportEnquiryStatus.textContent='Too many requests. Please try later, without submitting repeated messages now.';
        }else{
            supportEnquiryStatus.textContent='The submission could not be confirmed. Please check with the team before sending it again.';
        }
    }catch(_){
        supportEnquiryStatus.textContent='The submission status is unknown. Your form remains here; please check before resending.';
    }finally{form.dataset.busy='false';if(submit)submit.disabled=false;}
});
function supportRenderAnswer(answer){
    var block=document.createElement('div');block.className='support-answer';
    var rendered=answer.a2ui&&window.BapxA2ui&&window.BapxA2ui.render(block,answer.a2ui,{scope:'support'});
    if(!rendered)block.innerHTML='<p><strong>Support:</strong> '+supportReplyHtml(answer.reply||answer.error)+'</p>'+(answer.actions?supportActionsHtml(answer.actions):'');
    supportLog.appendChild(block);
}
function supportToggle(open){if(!supportPanel||!supportFab)return;supportPanel.hidden=!open;supportFab.setAttribute('aria-expanded',open?'true':'false');}
function supportSaveLog(){
    try{
        if(!supportCacheKey||!supportLog||sessionStorage.getItem(supportCacheScope)!==supportCacheKey)return;
        sessionStorage.setItem(supportCacheKey,supportLog.innerHTML);
        sessionStorage.setItem(supportCacheKey+':conversation_id',supportConversationId||'');
    }catch(e){}
}
function supportLoadLog(){
    if(!supportCacheKey||!supportLog)return;
    try{
        var retiredKeys=Array.from({length:sessionStorage.length},function(_,i){return sessionStorage.key(i);});
        retiredKeys.forEach(function(key){if(key?.startsWith('sps-support-chat-'))sessionStorage.removeItem(key);});
        var previousKey=sessionStorage.getItem(supportCacheScope)||'';
        if(previousKey!==supportCacheKey&&/^sps-support-chat:[a-f0-9]{64}$/.test(previousKey)){
            sessionStorage.removeItem(previousKey);
            sessionStorage.removeItem(previousKey+':conversation_id');
        }
        sessionStorage.setItem(supportCacheScope,supportCacheKey);
        var saved=sessionStorage.getItem(supportCacheKey);
        if(saved)supportLog.innerHTML=saved;
        var cid=sessionStorage.getItem(supportCacheKey+':conversation_id');
        if(cid&&/^[a-f0-9]{32}$/.test(cid))supportConversationId=cid;
    }catch(e){}
}
function supportSyncNewChat(){if(supportNewChat)supportNewChat.disabled=supportSessionChanged||supportForm?.dataset.busy==='true'||supportFeedbackPending;}
window.addEventListener('pageshow',function(event){
    if(!event.persisted||!supportCacheKey||!supportLog||!supportForm)return;
    try{if(sessionStorage.getItem(supportCacheScope)===supportCacheKey)return;}catch(e){return;}
    supportSessionChanged=true;supportConversationId=null;supportPendingRequest=null;
    supportLog.textContent='This Support session changed. Refresh the page to continue.';
    supportForm.querySelectorAll('textarea,button').forEach(function(control){control.disabled=true;});
    supportSyncNewChat();
});
if(supportNewChat&&supportForm&&supportLog){
    supportNewChat.addEventListener('click',function(){
        if(supportNewChat.disabled)return;
        supportConversationId=null;supportPendingRequest=null;
        supportLog.innerHTML=supportInitialLog;
        supportSaveLog();
        supportForm.querySelector('textarea')?.focus();
    });
    supportSyncNewChat();
}
if(supportFab&&supportPanel){supportLoadLog();
supportFab.addEventListener('click',function(){supportToggle(supportPanel.hidden);});
supportClose.addEventListener('click',function(){supportToggle(false);});}
if(supportForm){supportForm.addEventListener('submit',async function(e){
    e.preventDefault();
    if(supportSessionChanged||supportForm.dataset.busy==='true')return;
    var data=new FormData(supportForm),msg=String(data.get('message')||'').trim();
    if(!msg||!supportLog)return;
    if(!supportConversationId)supportConversationId=supportRandomId();
    if(!supportPendingRequest||supportPendingRequest.message!==msg){supportPendingRequest={message:msg,id:supportRandomId()};}
    data.set('conversation_id',supportConversationId);
    data.set('request_id',supportPendingRequest.id);
    supportForm.dataset.busy='true';
    supportSyncNewChat();
    var controls=Array.from(supportForm.querySelectorAll('textarea,button'));
    controls.forEach(function(control){control.disabled=true;});
    supportLog.querySelectorAll('.support-feedback').forEach(function(el){el.remove();});
    supportLog.insertAdjacentHTML('beforeend','<p><strong>You:</strong> '+supportEscape(msg)+'</p>');
    supportSaveLog();supportForm.reset();
    var progress=window.AgentRequestStatus.start(supportLog),failed=false;
    supportLog.scrollTop=supportLog.scrollHeight;
    var abort=new AbortController(),timeout=setTimeout(function(){abort.abort();},window.AgentRequestStatus.timeoutMs);
    try{
        var r=await fetch('/support/ask',{method:'POST',body:data,signal:abort.signal});
        var j=await r.json();
        if(supportSessionChanged)return;
        var hasReply=typeof j.reply==='string'&&j.reply.trim()!=='';
        failed=!r.ok||!!j.error||!hasReply;
        if(!failed||typeof j.turn_id==='string')supportPendingRequest=null;
        if(typeof j.conversation_id==='string'&&/^[a-f0-9]{32}$/.test(j.conversation_id))supportConversationId=j.conversation_id;
        supportRenderAnswer(hasReply||j.error?j:{error:'The server returned no answer.'});
        if(failed&&['incomplete','pending_or_interrupted'].includes(j.request_status)){
            var recovery=document.createElement('p');
            recovery.textContent='Check whether your last request completed before repeating it. Choose New chat to ask a different question.';
            supportLog.appendChild(recovery);
        }
        if(!failed&&j.ui?.kind==='contact_enquiry')void supportOpenEnquiry();
        if(!failed&&!j.ticket_id&&typeof j.turn_id==='string'&&/^[a-f0-9]{32}$/.test(j.turn_id)){
            var feedback=document.createElement('div');feedback.className='support-feedback';feedback.dataset.turnId=j.turn_id;
            feedback.innerHTML='<span>Did this answer your question?</span> <button type="button" data-support-rating="helpful">Yes, resolved</button> <button type="button" data-support-rating="not_helpful">No, need help</button>';
            supportLog.appendChild(feedback);
        }
    }catch(err){failed=true;if(!supportSessionChanged)supportLog.insertAdjacentHTML('beforeend','<p><strong>Support:</strong> The reply was interrupted. Check whether your last request completed before repeating it. Choose New chat to ask a different question.</p>');}
    finally{
        clearTimeout(timeout);progress.finish(failed);
        supportForm.dataset.busy='false';controls.forEach(function(control){control.disabled=supportSessionChanged;});
        supportSyncNewChat();
        supportSaveLog();supportLog.scrollTop=supportLog.scrollHeight;
    }
});}
if(supportPanel){supportPanel.addEventListener('click',async function(event){
    var button=event.target.closest('[data-support-rating]');
    if(!button || !supportPanel.contains(button) || !supportConversationId || supportFeedbackPending) return;
    var feedback=button.closest('.support-feedback');
    if(!feedback || !/^[a-f0-9]{32}$/.test(feedback.dataset.turnId||''))return;
    var rating=button.dataset.supportRating;
    if(rating!=='helpful'&&rating!=='not_helpful')return;
    var buttons=Array.from(feedback.querySelectorAll('button'));
    buttons.forEach(function(el){el.disabled=true;});
    supportFeedbackPending=true;supportSyncNewChat();
    var body=new FormData();
    body.set('_csrf',supportForm.querySelector('input[name="_csrf"]')?.value||'');
    body.set('conversation_id',supportConversationId);
    body.set('turn_id',feedback.dataset.turnId);
    body.set('rating',rating);
    try{
        var response=await fetch('/support/feedback',{method:'POST',body,credentials:'same-origin'});
        var data=await response.json();
        if(!response.ok||!data.recorded)throw new Error('Feedback unavailable');
        feedback.textContent=rating==='helpful'
            ? 'Thank you. We recorded your confirmation that this answer resolved your question.'
            : 'Thank you. Your question is marked unresolved. Please continue chatting or contact our support team.';
        supportSaveLog();
    }catch(error){
        feedback.insertAdjacentText('beforeend',' Could not save feedback; please retry.');
        buttons.forEach(function(el){el.disabled=false;});
    }finally{
        supportFeedbackPending=false;supportSyncNewChat();
    }
});}
function showToast(msg,type){type=type||'info';var c=document.getElementById('toast-container');if(!c){c=document.createElement('div');c.id='toast-container';document.body.appendChild(c);}var t=document.createElement('div');t.className='toast toast--'+type;var icons={success:'✓',error:'✕',warning:'⚠',info:'ℹ'};t.innerHTML='<span class="toast__icon">'+(icons[type]||'ℹ')+'</span><span class="toast__text"></span><button class="toast__close" aria-label="Dismiss">&times;</button>';t.querySelector('.toast__text').textContent=String(msg);t.querySelector('.toast__close').addEventListener('click',function(e){e.stopPropagation();dismiss(t);});t.addEventListener('click',function(){dismiss(t);});c.appendChild(t);var timer=setTimeout(function(){dismiss(t);},4000);function dismiss(el){if(el.classList.contains('toast--out'))return;el.classList.add('toast--out');clearTimeout(timer);setTimeout(function(){if(el.parentNode)el.parentNode.removeChild(el);},250);}}
let cartUpdatePending = false;
document.addEventListener('submit', async function (event) {
    const form = event.target.closest('[data-cart-add],[data-cart-change]');
    if (!form || !window.fetch) return;
    event.preventDefault();
    if (cartUpdatePending) return;

    const control = form.closest('[data-cart-control]');
    const focused = form.contains(document.activeElement) ? document.activeElement : null;
    const buttons = document.querySelectorAll('[data-cart-control] button:not(:disabled)');
    cartUpdatePending = true;
    buttons.forEach(button => { button.disabled = true; });
    try {
        const response = await fetch(form.getAttribute('action'), {
            method: 'POST',
            body: new FormData(form),
            headers: {Accept: 'application/json'}
        });
        // A hosting/WAF 429 can return an empty body without Content-Type.
        // A failed POST is NOT evidence that an item was added; never invent a
        // cart count, retry a non-idempotent add automatically, or open the tray.
        const data = await response.json().catch(() => null);
        if (!response.ok) {
            const message = response.status === 429
                ? 'Cart requests are temporarily limited. Please wait and try again.'
                : (typeof data?.error === 'string' ? data.error : 'Unable to update cart. Please try again.');
            throw new Error(message);
        }
        if (!data || typeof data.slug !== 'string'
            || !Number.isInteger(data.quantity) || data.quantity < 0
            || !Number.isInteger(data.cart_count) || data.cart_count < 0) {
            throw new Error('Cart update was not confirmed. Please reload the page before trying again.');
        }

        document.querySelectorAll('[data-cart-control]').forEach(card => {
            if (card.dataset.slug !== data.slug) return;
            const add = card.querySelector('[data-cart-add]');
            const stepper = card.querySelector('[data-cart-stepper]');
            const quantity = card.querySelector('[data-cart-quantity]');
            if (add) add.hidden = data.quantity > 0;
            if (stepper) stepper.hidden = data.quantity <= 0;
            if (quantity) quantity.textContent = data.quantity;
        });
        const badge = document.querySelector('.cart-count');
        const tray = document.getElementById('mobile-cart-tray');
        const count = document.getElementById('mobile-cart-count');
        const label = document.getElementById('mobile-cart-label');
        if (badge) badge.textContent = data.cart_count;
        if (tray) tray.hidden = data.cart_count <= 0;
        if (count) count.textContent = data.cart_count;
        if (label) label.textContent = data.cart_count === 1 ? 'item' : 'items';
    } catch (error) {
        showToast(error.message || 'Unable to update cart.', 'error');
    } finally {
        cartUpdatePending = false;
        buttons.forEach(button => { button.disabled = false; });
        // Keep focus in this card when the button changes or the request fails.
        if (focused && (document.activeElement === document.body || document.activeElement === focused)) {
            const button = focused.closest('[hidden]')
                ? control.querySelector('[data-cart-add]:not([hidden]) button, [data-cart-stepper]:not([hidden]) button')
                : focused;
            button?.focus({preventScroll: true});
        }
    }
});
</script>
<div id="toast-container" role="alert" aria-live="polite"></div>
</body>
</html>
