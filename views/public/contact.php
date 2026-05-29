<section class="section">
    <div class="container container--narrow">
        <div style="text-align:center; margin-bottom:var(--space-2xl);">
            <span class="eyebrow">Get in Touch</span>
            <h1 class="section-title" style="margin-bottom:var(--space-sm);">Contact Sri Panchami Spiritual — Chennai</h1>
            <p class="lede" style="margin:0 auto;">Visit our store in Ramapuram or reach out for spiritual guidance, astrology consultation, or product inquiries. We are here to help.</p>
        </div>
        <div class="grid" style="margin-bottom:var(--space-2xl);">
            <div class="panel reveal" style="text-align:center;">
                <span style="display:block; margin-bottom:var(--space-sm);"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
                <h3 style="font-family:var(--font-serif); margin:0 0 var(--space-xs);">Email</h3>
                <a href="mailto:sripanchamispiritual@gmail.com" style="color:var(--color-maroon);">sripanchamispiritual@gmail.com</a>
            </div>
            <div class="panel reveal" style="text-align:center;">
                <span style="display:block; margin-bottom:var(--space-sm);"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
                <h3 style="font-family:var(--font-serif); margin:0 0 var(--space-xs);">Visit Our Store</h3>
                <p style="color:var(--color-text-muted); font-size:0.9rem; margin:0;">23, 1st Cross Street Kothari Nagar,<br>Ramapuram, Chennai,<br>Tamil Nadu 600089</p>
            </div>
            <div class="panel reveal" style="text-align:center;">
                <span style="display:block; margin-bottom:var(--space-sm);"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6a6 6 0 000 12"/><path d="M12 8v8"/><path d="M9 10l6 4"/><path d="M15 10l-6 4"/></svg></span>
                <h3 style="font-family:var(--font-serif); margin:0 0 var(--space-xs);">Business Info</h3>
                <p style="color:var(--color-text-muted); font-size:0.9rem; margin:0;">GST: 33BZRPM8732J2ZQ</p>
            </div>
        </div>
        <div class="admin-card reveal" style="text-align:center;">
            <span style="display:block; margin-bottom:var(--space-sm);"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="var(--color-gold)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 100 20 10 10 0 000-20z"/><path d="M12 8v8"/><path d="M8 12h8"/></svg></span>
            <h3 style="font-family:var(--font-serif); margin:0 0 var(--space-sm);">Sacred Service Hours</h3>
            <p style="color:var(--color-text-muted); font-size:0.9rem; margin:0;">Monday – Saturday: 9:00 AM – 7:00 PM<br>Sunday: 10:00 AM – 5:00 PM</p>
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
