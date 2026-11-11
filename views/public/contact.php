<section class="section contact-page">
    <div class="container">
        <header class="contact-page__header">
            <h1 class="section-title">Contact Sri Panchami Spiritual</h1>
            <p>Get help with an order, ask about a product, or plan a temple visit.</p>
        </header>

        <div class="contact-page__grid">
            <aside class="contact-card" aria-label="Contact options">
                <h2>Call or email us</h2>
                <div class="contact-direct-list">
                    <a class="contact-direct-link" href="tel:+919789444037">+91 97894 44037</a>
                    <a class="contact-direct-link" href="tel:+919789444038">+91 97894 44038</a>
                    <a class="contact-direct-link contact-direct-link--mail" href="mailto:support@sripanchamispiritual.com"><span class="contact-email-address">support@sripanchamispiritual.com</span><span class="contact-email-label">Email support</span></a>
                </div>
                <p>Mon–Sat, 9 AM–7 PM · Sun, 10 AM–5 PM</p>
            </aside>

            <div class="contact-form-card" id="contact-form">
                <?php if ($success): ?>
                    <h2 tabindex="-1" data-contact-feedback>Message received</h2>
                    <p>Thank you for getting in touch. We’ll reply to the email address you provided.</p>
                    <a class="contact-direct-link" href="/contact#contact-form">Send another message</a>
                <?php else: ?>
                    <h2>Send a message</h2>
                    <?php if ($errors): ?>
                        <div class="contact-form__errors" role="alert" tabindex="-1" data-contact-feedback>
                            <p>Check the highlighted fields and try again.</p>
                            <ul>
                                <?php foreach ($errors as $field => $error): ?>
                                    <li><a href="#contact-<?= e($field) ?>"><?= e($error) ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <?php
                    $fieldError = static fn (string $field): string => isset($errors[$field])
                        ? ' aria-invalid="true" aria-describedby="contact-' . $field . '-error"'
                        : '';
                    $errorMessage = static fn (string $field): string => isset($errors[$field])
                        ? '<p class="error" id="contact-' . $field . '-error">' . e($errors[$field]) . '</p>'
                        : '';
                    ?>
                    <form method="post" action="/contact#contact-form" class="contact-form">
                        <input type="hidden" name="_csrf" value="<?= e($_SESSION['csrf_token'] ??= bin2hex(random_bytes(16))) ?>">
                        <div class="contact-form__row">
                            <div class="form-group">
                                <label for="contact-name">Name (required)</label>
                                <input id="contact-name" type="text" name="name" autocomplete="name" required value="<?= e($submission['name']) ?>"<?= $fieldError('name') ?>>
                                <?= $errorMessage('name') ?>
                            </div>
                            <div class="form-group">
                                <label for="contact-email">Email (required)</label>
                                <input id="contact-email" type="email" name="email" autocomplete="email" required value="<?= e($submission['email']) ?>"<?= $fieldError('email') ?>>
                                <?= $errorMessage('email') ?>
                            </div>
                        </div>
                        <div class="contact-form__row">
                            <div class="form-group">
                                <label for="contact-subject">Subject (required)</label>
                                <select id="contact-subject" name="subject" required<?= $fieldError('subject') ?>>
                                    <option value="">Select a subject</option>
                                    <?php foreach ($subjects as $key => $label): ?>
                                        <option value="<?= e($key) ?>"<?= $submission['subject'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= $errorMessage('subject') ?>
                            </div>
                            <div class="form-group">
                                <label for="contact-phone">Phone (optional)</label>
                                <input id="contact-phone" type="tel" name="phone" autocomplete="tel" value="<?= e($submission['phone']) ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="contact-message">Message (required)</label>
                            <textarea id="contact-message" name="message" rows="4" required<?= $fieldError('message') ?>><?= e($submission['message']) ?></textarea>
                            <?= $errorMessage('message') ?>
                        </div>
                        <button class="btn btn-primary" type="submit">Send message</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php if ($success || $errors): ?>
<script>
    window.addEventListener('load', function () {
        var feedback = document.querySelector('[data-contact-feedback]');
        if (feedback) feedback.focus();
    }, {once: true});
</script>
<?php endif; ?>

<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "ContactPage",
    "name": "Contact Sri Panchami Spiritual",
    "description": "Contact Sri Panchami Spiritual for spiritual products, order support, temple guidance, and pooja services.",
    "url": "https://sripanchamispiritual.com/contact"
}
</script>
