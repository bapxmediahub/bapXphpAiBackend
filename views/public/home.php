<link rel="stylesheet" href="/assets/css/home-editorial.css?v=<?= filemtime(__DIR__ . '/../../assets/css/home-editorial.css') ?>">
<div class="home-editorial-stack" data-home-stack>
<div class="home-opening">
<section class="home-hero">
    <div class="container home-hero-inner">
        <div class="hero-copy">
            <h1><?= e($siteProfile->isReference() ? 'Authentic Spiritual Products' : $siteProfile->name()) ?></h1>
            <?php $heroIntro = $siteProfile->summary() ?: ($siteProfile->isReference() ? 'Sacred jewellery, rudraksha and pooja essentials, delivered across India.' : ''); ?>
            <?php if ($heroIntro !== ''): ?><p class="lede"><?= e($heroIntro) ?></p><?php endif; ?>
            <?php if (module_on('shop')): ?>
            <div class="hero-actions">
                <a href="/shop" class="btn btn-primary">Shop Products</a>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($siteProfile->isReference()): ?>
        <?php
            // The supplied Guruji portrait leads the carousel; the existing deity
            // images remain available in their original order afterwards.
            $heroSlides = [[
                'src' => '/assets/images/hero/guruji-portrait-cutout.png',
                'alt' => 'Guruji devotional portrait',
                'class' => 'hero-guruji',
            ]];
            for ($slide = 1; $slide <= 10; $slide++) {
                $heroSlides[] = [
                    'src' => '/assets/images/hero/varahi/varahi-' . str_pad((string)$slide, 2, '0', STR_PAD_LEFT) . '.webp',
                    'alt' => 'Sri Maha Varahi Amman devotional image ' . $slide,
                    'class' => '',
                ];
            }
        ?>
        <div class="hero-deity" data-varahi-slider data-current-slide="guruji" data-active-index="0" role="region" aria-roledescription="carousel" aria-label="Devotional portraits">
            <div class="deity-frame">
                <?php foreach ($heroSlides as $index => $slide): ?>
                    <img class="varahi-slide <?= e($slide['class']) ?> <?= $index === 0 ? 'is-active' : '' ?>" src="<?= e($slide['src']) ?>" alt="<?= e($slide['alt']) ?>" <?= $index === 0 ? '' : 'aria-hidden="true"' ?> width="480" height="640" <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
                <?php endforeach; ?>
            </div>
            <div class="portrait-controls" data-portrait-controls hidden>
                <button type="button" aria-label="Previous portrait" title="Previous portrait; stop automatic rotation" data-portrait-step="-1">‹</button>
                <div class="portrait-dots" role="group" aria-label="Choose a portrait and stop automatic rotation">
                    <?php foreach ($heroSlides as $index => $slide): ?>
                    <button type="button" data-portrait-index="<?= $index ?>" aria-label="<?= e($slide['alt']) ?>; stop automatic rotation" title="Select portrait and stop automatic rotation" aria-current="<?= $index === 0 ? 'true' : 'false' ?>"></button>
                    <?php endforeach; ?>
                </div>
                <button type="button" aria-label="Next portrait" title="Next portrait; stop automatic rotation" data-portrait-step="1">›</button>
                <span class="sr-only" data-portrait-status aria-live="off"><?= e($heroSlides[0]['alt']) ?></span>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>


<?php if (module_on('shop')): ?>
<div class="trust-bar">
    <ul class="trust-bar__inner" role="list">
        <li class="trust-item">
            <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            <span>Secure Payments</span>
        </li>
        <li class="trust-item">
            <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
            <span>Saved Addresses</span>
        </li>
        <li class="trust-item">
            <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
            <span>Spiritual Products</span>
        </li>
    </ul>
</div>
<?php endif; ?>
</div>

<?php if (module_on('shop')): ?>
<section class="section home-bestsellers">
    <div class="container">
        <?php
            $cartQuantities = [];
            foreach ($_SESSION['cart'] ?? [] as $cartItem) {
                $cartQuantities[(string)($cartItem['slug'] ?? '')] = (int)($cartItem['qty'] ?? 0);
            }
            $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16));
        ?>
        <div class="home-bestsellers__heading">
            <h2 class="section-title" id="home-top-sellers" data-page-section="Top sellers" tabindex="-1">Top Selling Products</h2>
            <a href="/shop" class="btn btn-primary home-bestsellers__shop">
                Shop all products <span aria-hidden="true">→</span>
            </a>
        </div>
        <div data-section-index hidden></div>
        <?php if (!empty($bestSellers)): ?>
            <div data-home-rail>
                <?php if (count($bestSellers) > 1): ?>
                    <?php $railId = 'home-bestseller-rail'; require __DIR__ . '/home-rail-controls.php'; ?>
                <?php endif; ?>
                <div class="product-grid home-product-rail" id="home-bestseller-rail" data-home-track tabindex="0" role="region" aria-label="Top selling products">
                    <?php foreach ($bestSellers as $item): ?>
                        <?php require __DIR__ . '/home-product-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <p class="home-bestsellers__empty">Our top sellers will appear here as orders are completed.</p>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if (module_on('shop')): ?>
<?php require __DIR__ . '/home-collections.php'; ?>

<div class="home-chapter">
<section class="section home-order-guide" aria-labelledby="home-order-guide-title">
    <div class="container">
        <div class="home-order-guide__panel">
            <h2 id="home-order-guide-title" data-page-section="Ordering" tabindex="-1" class="section-title">How Your Order Works</h2>
            <ol class="home-order-guide__steps" role="list">
                <li data-home-enter="panel">
                    <h3>Choose your products</h3>
                    <p>Add the items and quantities you need to your cart.</p>
                </li>
                <li data-home-enter="panel">
                    <h3>Check out</h3>
                    <p>Choose a delivery address and pay securely online.</p>
                </li>
                <li data-home-enter="panel">
                    <h3>Track your delivery</h3>
                    <p>Open My Orders in your account to see your delivery status.</p>
                </li>
            </ol>
            <div class="home-order-guide__actions">
                <a class="btn btn-primary" href="/shop">Browse products</a>
                <?php if (module_on('blog')): ?>
                <a class="home-order-guide__help" href="/blog/order-products">Ordering help</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
</div>
<?php endif; ?>

<?php if (!empty($temples)): ?>
<div class="home-chapter">
<section class="section section--alt home-temples" aria-labelledby="home-temple-guide">
    <div class="container">
        <div class="home-temples__header">
            <h2 class="section-title" id="home-temple-guide" data-page-section="Temples" tabindex="-1"><?= $siteProfile->isReference() ? 'Panchami Temples Guide' : 'Temple guides' ?></h2>
        </div>
        <div class="temple-carousel" data-temple-rail>
            <div class="home-temples__navigation">
                <a href="/temples" class="home-temples__all">Explore all temples <span aria-hidden="true">→</span></a>
                <?php if (count($temples) > 1): ?>
                    <div class="temple-carousel__controls" data-temple-controls hidden>
                        <button type="button" class="btn btn-outline" aria-label="Previous temple" aria-controls="home-temple-track" data-temple-step="-1">←</button>
                        <span data-temple-position role="status"></span>
                        <button type="button" class="btn btn-outline" aria-label="Next temple" aria-controls="home-temple-track" data-temple-step="1">→</button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="temple-carousel-track" id="home-temple-track"<?= count($temples) > 1 ? ' tabindex="0" role="region" aria-label="Temple previews"' : '' ?>>
                <?php foreach ($temples as $temple): ?>
                    <article class="temple-feature-card">
                        <div class="temple-photo">
                            <?php if (!empty($temple['image_url'])): ?>
                                <img src="<?= e(webp_src($temple['image_url'])) ?>" alt="" loading="lazy" decoding="async" data-home-enter="photo">
                            <?php else: ?>
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6a3 3 0 0 1 6 0v6"/></svg>
                            <?php endif; ?>
                        </div>
                        <div class="temple-feature-card__body">
                            <h3><?= e($temple['name']) ?></h3>
                            <?php if (!empty($temple['address'])): ?>
                                <p class="temple-feature-card__address"><?= e($temple['address']) ?></p>
                            <?php endif; ?>
                            <?php if (empty($temple['address']) && !empty($temple['description'])): ?>
                                <p class="temple-feature-card__summary"><?= e($temple['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="temple-feature-card__visit">
                            <?php if (!empty($temple['timings'])): ?>
                                <span><?= e($temple['timings']) ?></span>
                            <?php endif; ?>
                            <a class="btn btn-primary" href="/temples/<?= e(rawurlencode((string)$temple['slug'])) ?>" aria-label="View details for <?= e($temple['name']) ?>">View details</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

        </div>
    </div>
</section>
</div>
<?php endif; ?>

<div class="home-chapter">
<section class="section section--warm" aria-labelledby="home-values">
    <div class="container">
        <div class="section-header">
            <span class="eyebrow serif-accent">Why <?= e($siteProfile->name()) ?></span>
            <h2 class="section-title" id="home-values" data-page-section="Our values" tabindex="-1">Faith · Trust · Tradition</h2>
            <p class="lede">Rooted in devotion, committed to authenticity — every product and service reflects our reverence for India's spiritual heritage.</p>
        </div>
        <div class="value-strip">
            <article class="value-card" data-home-enter="panel">
            <div class="value-card__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
            </div>
            <h3>Authenticity</h3>
            <p>Every item sourced with devotion — authentic rudraksha, pure pooja essentials, and sacred jewellery verified for spiritual genuineness.</p>
        </article>
        <article class="value-card" data-home-enter="panel">
            <div class="value-card__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <h3>Spiritual Growth</h3>
            <p>Our products are more than offerings — they are symbols of faith that help keep alive the divine traditions connecting every devotee with spirituality.</p>
        </article>
        <article class="value-card" data-home-enter="panel">
            <div class="value-card__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <h3>Devotion</h3>
            <p>Crafted with reverence, our products support sacred rituals and deepen your connection with the divine through every offering.</p>
        </article>
        <article class="value-card" data-home-enter="panel">
            <div class="value-card__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            </div>
            <h3>Community</h3>
            <p>Fostering belonging and connection through shared spiritual experiences — bringing temples, traditions, and devotees closer together.</p>
        </article>
    </div>
    <div class="page-cta-card">
        <div data-home-enter="panel">
            <span class="page-cta-card__eyebrow">Need Guidance?</span>
            <h3>Get in Touch</h3>
            <p>Use the contact form for product questions, order help, or temple guidance.</p>
        </div>
        <a class="btn btn-primary page-cta-card__button" href="/contact#contact-form">Let’s Get Connected →</a>
    </div>
    </div>
</section>
</div>

<?php $homeFaq = [
    [
        'question' => 'How do I order a spiritual product?',
        'answer' => 'Choose a product, add it to your cart or select Buy Now, enter your delivery details at checkout, and complete the available online payment step.',
    ],
    [
        'question' => 'What details can I check before I order?',
        'answer' => 'Each product page can show its description, key features, specifications, price, availability, and images. Contact support if you need help with a product detail.',
    ],
    [
        'question' => 'Where can I track an order?',
        'answer' => 'After signing in, open My Orders from your account dashboard to review the order status and any shipment tracking details that are available.',
    ],
    [
        'question' => 'How can I get product or temple guidance?',
        'answer' => 'Use the contact form for product questions, order support, or temple guidance. The support team can also help with store navigation.',
    ],
]; ?>
<section class="section section--alt home-faq" aria-labelledby="home-faq-title">
    <div class="container home-faq__inner">
        <div class="section-header">
            <h2 class="section-title" id="home-faq-title" data-page-section="FAQs" tabindex="-1">Frequently asked questions</h2>
            <p class="lede">Helpful answers for shopping, orders, and temple guidance.</p>
        </div>
        <div class="home-faq__list">
            <?php foreach ($homeFaq as $faq): ?>
            <details class="home-faq__item">
                <summary><?= e($faq['question']) ?><span aria-hidden="true"></span></summary>
                <p><?= e($faq['answer']) ?></p>
            </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>
</div>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(static fn(array $faq): array => [
        '@type' => 'Question',
        'name' => $faq['question'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
    ], $homeFaq),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
