<section class="section">
    <div class="container container--narrow">
        <div style="text-align:center; margin-bottom:var(--space-2xl);">
            <span class="eyebrow serif-accent">Contact</span>
            <h1 class="section-title" style="margin-bottom:var(--space-sm);">Sri Panchami Spiritual</h1>
            <p class="lede" style="margin:0 auto;">Shop spiritual products online. For astrology guidance, use Consult for regular sessions or contact us for VIP direct astrology visits.</p>
        </div>
        <div class="contact-form-card reveal" id="contact-form" style="scroll-margin-top:110px;">
            <h2 style="font-family:var(--font-serif); text-align:center; margin:0 0 var(--space-sm);">Contact Us</h2>
            <p style="text-align:center; color:var(--color-text-muted); margin:0 auto var(--space-lg); max-width:620px;">Use this form for astrology consultation requests, product questions, temple guidance, or store support.</p>
            <?php if(!empty($success)): ?>
                <script>document.addEventListener('DOMContentLoaded',function(){showToast('Thank you. Sri Panchami Spiritual will contact you soon.','success');});</script>
            <?php endif; ?>
            <form method="post" action="/contact" class="contact-form" style="max-width:720px; margin:0 auto;">
                <?php $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(16)); ?>
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <div class="contact-form__row">
                    <div class="form-group">
                        <label for="contact-name">Name</label>
                        <input id="contact-name" type="text" name="name" required placeholder="Your name">
                    </div>
                    <div class="form-group">
                        <label for="contact-email">Email</label>
                        <input id="contact-email" type="email" name="email" required placeholder="your@email.com">
                    </div>
                </div>
                <div class="contact-form__row">
                    <div class="form-group">
                        <label for="contact-phone">Phone</label>
                        <input id="contact-phone" type="tel" name="phone" placeholder="+91 XXXXX XXXXX">
                    </div>
                    <div class="form-group">
                        <label for="contact-subject">Subject</label>
                        <select id="contact-subject" name="subject" required>
                            <option value="">Select a subject</option>
                            <option value="astrology" <?= (($subject ?? '') === 'astrology') ? 'selected' : '' ?>>Astrology Consultation</option>
                            <option value="product">Product Inquiry</option>
                            <option value="temple">Temple Guidance</option>
                            <option value="order">Order Support</option>
                            <option value="general">General Question</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="contact-message">Message</label>
                    <textarea id="contact-message" name="message" rows="5" required placeholder="Tell us what guidance or support you need"></textarea>
                </div>
                <button class="btn btn-primary btn-block" type="submit">Send Request</button>
            </form>
        </div>
        <div class="contact-info-grid">
            <div class="contact-card reveal">
                <span class="contact-card__icon" aria-hidden="true"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></span>
                <div class="contact-card__body">
                    <span class="contact-card__eyebrow">Hours</span>
                    <h3>Sacred Service Hours</h3>
                    <p>Monday – Saturday: 9:00 AM – 7:00 PM<br>Sunday: 10:00 AM – 5:00 PM</p>
                </div>
            </div>
            <div class="contact-card reveal">
                <span class="contact-card__icon" aria-hidden="true"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
                <div class="contact-card__body">
                    <span class="contact-card__eyebrow">Shop & visits</span>
                    <h3>Online Store</h3>
                    <p>Products are available through the online shop.<br>Direct astrology visits are for VIP appointments only.<br>Regular sessions are available through Consult.</p>
                </div>
            </div>
            <div class="contact-card contact-card--direct contact-direct-panel reveal">
                <div class="contact-card__body">
                    <span class="contact-card__eyebrow">phone</span>
                    <div class="contact-direct-list">
                        <a class="contact-direct-link" href="tel:+919789444037">
                            <span class="contact-direct-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.7 19.7 0 0 1-8.6-3.1 19.1 19.1 0 0 1-5.9-5.9A19.7 19.7 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.4 2.1L8.1 10a16 16 0 0 0 5.9 5.9l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2Z"/></svg></span>
                            <span>+91 97894 44037</span>
                        </a>
                        <a class="contact-direct-link" href="tel:+919789444038">
                            <span class="contact-direct-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.7 19.7 0 0 1-8.6-3.1 19.1 19.1 0 0 1-5.9-5.9A19.7 19.7 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.4 2.1L8.1 10a16 16 0 0 0 5.9 5.9l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2Z"/></svg></span>
                            <span>+91 97894 44038</span>
                        </a>
                    </div>
                </div>
            </div>
            <div class="contact-card contact-card--direct contact-direct-panel reveal">
                <div class="contact-card__body">
                    <span class="contact-card__eyebrow">email</span>
                    <a class="contact-direct-link contact-direct-link--mail" href="mailto:sripanchamispiritual@gmail.com">
                        <span class="contact-direct-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><path d="m22 6-10 7L2 6"/></svg></span>
                        <span>sripanchamispiritual@gmail.com</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "ContactPage",
    "name": "Contact Sri Panchami Spiritual",
    "description": "Contact Sri Panchami Spiritual for spiritual products, astrology consultation, and pooja services.",
    "url": "https://sripanchamispiritual.com/contact"
}
</script>
