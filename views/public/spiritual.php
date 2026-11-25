<?php $shopEnabled = module_on('shop'); ?>
<section class="section spiritual-guide" aria-labelledby="spiritual-guide-title">
    <div class="container">
        <header class="spiritual-guide__header">
            <h1 id="spiritual-guide-title">Devotional practice and temple visits</h1>
            <p class="lede">Explore temple timings, addresses, and pooja information<?= $shopEnabled ? ', or find products for daily worship' : '' ?>.</p>
        </header>
        <div class="spiritual-guide__paths">
            <article class="about-story-card">
                <h2>Temple guides</h2>
                <p>Find opening hours, addresses, and directions where available.</p>
                <a class="btn btn-primary" href="/temples">Explore temple guides</a>
            </article>
            <?php if ($shopEnabled): ?>
                <article class="about-story-card">
                    <h2>Spiritual products</h2>
                    <p>Browse the collection for daily worship and sacred gifting.</p>
                    <a class="btn btn-outline" href="/shop">Browse products</a>
                </article>
            <?php endif; ?>
            <article class="about-story-card">
                <h2>General enquiries</h2>
                <p>Ask our team about a temple visit, a pooja, or an order.</p>
                <a class="btn btn-outline" href="/contact#contact-form">Contact us</a>
            </article>
        </div>
    </div>
</section>
