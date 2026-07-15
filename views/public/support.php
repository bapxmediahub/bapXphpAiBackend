<section class="section" style="padding-top:var(--space-xl);">
    <div class="container">
        <span class="eyebrow serif-accent">Support</span>
        <h1 class="section__title">How can we help?</h1>
        <p class="lede">Find quick answers about products, orders, delivery addresses, payments, and consultant bookings.</p>

        <div class="support-grid">
            <?php if (!empty($supportNav)): ?>
                <?php foreach ($supportNav as $section): ?>
                <article class="support-card">
                    <h2><?= e($section['section'] ?? '') ?></h2>
                    <ul style="list-style:none; padding:0; margin:0;">
                        <?php foreach ($section['links'] ?? [] as $link): ?>
                        <li style="margin-bottom:var(--space-xs);">
                            <a href="<?= e($link['path'] ?? '#') ?>" style="font-weight:500;">
                                <?= e($link['label'] ?? $link['path'] ?? '') ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </article>
                <?php endforeach; ?>
            <?php else: ?>
            <article class="support-card">
                <h2>Saved addresses</h2>
                <p>Your signup address becomes the default at checkout. Select another saved address or enter and optionally save a different delivery address for an order.</p>
            </article>
            <article class="support-card">
                <h2>Consultant bookings</h2>
                <p>Browse consultants on the <a href="/consult">consultation marketplace</a>, choose a profile, and request your preferred appointment date and time.</p>
            </article>
            <article class="support-card">
                <h2>Orders &amp; shipping</h2>
                <p>Track your product orders from <a href="/account/dashboard/orders">your orders</a>. Shipping and delivery details appear once an order is fulfilled.</p>
            </article>
            <article class="support-card">
                <h2>Products &amp; returns</h2>
                <p>Shop authentic rudraksha, pooja items, and sacred jewellery in the <a href="/shop">store</a>. Reach out through the contact form for product or return questions.</p>
            </article>
            <article class="support-card">
                <h2>Temples &amp; guidance</h2>
                <p>Explore the <a href="/temples">temple guide</a> for temple details, timings, and directions.</p>
            </article>
            <article class="support-card">
                <h2>Talk to a person</h2>
                <p>Email <a href="mailto:sripanchamispiritual@gmail.com">sripanchamispiritual@gmail.com</a> or call <a href="tel:+919789444037">+91 97894 44037</a>. We usually reply within one business day.</p>
            </article>
            <?php endif; ?>
        </div>

        <div class="page-cta-card" style="margin-top:var(--space-xl);">
            <div>
                <h2>Still need help?</h2>
                <p>Send us the details and our team will get back to you quickly.</p>
            </div>
            <a href="/contact#contact-form" class="btn btn-primary">Contact support</a>
        </div>
    </div>
</section>