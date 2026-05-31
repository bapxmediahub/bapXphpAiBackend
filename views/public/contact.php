<section class="section">
    <div class="container container--narrow">
        <div style="text-align:center; margin-bottom:var(--space-2xl);">
            <span class="eyebrow">Get in Touch</span>
            <h1 class="section-title" style="margin-bottom:var(--space-sm);">Contact Sri Panchami Spiritual — Chennai</h1>
            <p class="lede" style="margin:0 auto;">Visit our store in Ramapuram or reach out for spiritual guidance, astrology consultation, or product inquiries. We are here to help.</p>
        </div>
        <div class="grid" style="margin-bottom:var(--space-2xl);">
            <div class="panel reveal" style="text-align:center;">
                <span style="display:block; margin-bottom:var(--space-sm);"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></span>
                <h3 style="font-family:var(--font-serif); margin:0 0 var(--space-xs);">Sacred Service Hours</h3>
                <p style="color:var(--color-text-muted); font-size:0.9rem; margin:0;">Monday – Saturday: 9:00 AM – 7:00 PM<br>Sunday: 10:00 AM – 5:00 PM</p>
            </div>
            <div class="panel reveal" style="text-align:center;">
                <span style="display:block; margin-bottom:var(--space-sm);"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
                <h3 style="font-family:var(--font-serif); margin:0 0 var(--space-xs);">Visit Our Store</h3>
                <p style="color:var(--color-text-muted); font-size:0.9rem; margin:0;">23, 1st Cross Street Kothari Nagar,<br>Ramapuram, Chennai,<br>Tamil Nadu 600089</p>
            </div>
            <div class="panel reveal" style="text-align:center;">
                <span style="display:block; margin-bottom:var(--space-sm);"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 01-2.2 2 19.7 19.7 0 01-8.6-3.1 19.1 19.1 0 01-5.9-5.9A19.7 19.7 0 012.1 4.2 2 2 0 014.1 2h3a2 2 0 012 1.7c.1 1 .4 2 .7 2.9a2 2 0 01-.4 2.1L8.1 10a16 16 0 005.9 5.9l1.3-1.3a2 2 0 012.1-.4c.9.3 1.9.6 2.9.7a2 2 0 011.7 2z"/></svg></span>
                <h3 style="font-family:var(--font-serif); margin:0 0 var(--space-xs);">Contact</h3>
                <p style="color:var(--color-text-muted); font-size:0.9rem; margin:0;">
                    <a href="tel:+919791122995" style="color:var(--color-maroon);">+91 97911 22995</a><br>
                    <a href="mailto:sripanchamispiritual@gmail.com" style="color:var(--color-maroon);">sripanchamispiritual@gmail.com</a>
                </p>
            </div>
        </div>
        <div class="admin-card reveal" id="contact-form" style="margin-top:var(--space-xl); scroll-margin-top:110px;">
            <h2 style="font-family:var(--font-serif); text-align:center; margin:0 0 var(--space-sm);">Send a Consultation Request</h2>
            <p style="text-align:center; color:var(--color-text-muted); margin:0 auto var(--space-lg); max-width:620px;">Use this form for remote astrology call/message requests, product questions, temple guidance, or store support.</p>
            <?php if(!empty($success)): ?>
                <div class="flash flash--success">Thank you. Sri Panchami Spiritual will contact you soon.</div>
            <?php endif; ?>
            <form method="post" action="/contact" class="admin-form" style="max-width:720px; margin:0 auto;">
                <div class="admin-form__row">
                    <div class="form-group">
                        <label for="contact-name">Name</label>
                        <input id="contact-name" type="text" name="name" required placeholder="Your name">
                    </div>
                    <div class="form-group">
                        <label for="contact-email">Email</label>
                        <input id="contact-email" type="email" name="email" required placeholder="your@email.com">
                    </div>
                </div>
                <div class="admin-form__row">
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
    </div>
</section>

<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "ContactPage",
    "name": "Contact Sri Panchami Spiritual",
    "description": "Contact Sri Panchami Spiritual in Chennai for spiritual products, astrology consultation, and pooja services.",
    "url": "https://sripanchamispiritual.com/contact"
}
</script>
