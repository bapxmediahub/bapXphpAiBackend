<section class="section contact-page">
    <div class="container">
        <header class="contact-page__header">
            <h1 class="section-title">Contact us</h1>
            <p>Get help with an order, ask about a product, or plan a temple visit.</p>
        </header>
        <div data-section-index hidden></div>

        <div class="contact-page__grid">
            <?php if ($publicContact['phones'] || isset($publicContact['email']) || $publicContact['hours']): ?>
            <aside class="contact-card" aria-label="Contact options">
                <?php
                $hasPhone = (bool)$publicContact['phones'];
                $hasEmail = isset($publicContact['email']);
                $directTitle = $hasPhone ? ($hasEmail ? 'Call or email us' : 'Call us') : ($hasEmail ? 'Email us' : 'Opening hours');
                ?>
                <h2 id="contact-direct" data-page-section="<?= e($hasPhone && $hasEmail ? 'Phone and email' : $directTitle) ?>" tabindex="-1"><?= e($directTitle) ?></h2>
                <div class="contact-direct-list">
                    <?php foreach ($publicContact['phones'] as $phone): ?>
                        <a class="contact-direct-link" href="tel:<?= e($phone['number']) ?>"><?= e($phone['label']) ?></a>
                    <?php endforeach; ?>
                    <?php if ($hasEmail): ?>
                    <a class="contact-direct-link contact-direct-link--mail" href="mailto:<?= e($publicContact['email']) ?>"><span class="contact-email-address"><?= e($publicContact['email']) ?></span><span class="contact-email-label">Email support</span></a>
                    <?php endif; ?>
                </div>
                <?php if ($publicContact['hours']): ?><p><?= e(implode(' · ', array_map(static fn(array $slot): string => $slot['days'] . ', ' . $slot['hours'], $publicContact['hours']))) ?></p><?php endif; ?>
            </aside>
            <?php endif; ?>

            <div class="contact-form-card" id="contact-form" data-page-section="Message" tabindex="-1">
                <?php if ($success): ?>
                    <h2 tabindex="-1" data-contact-feedback>Message received</h2>
                    <p>Thank you for getting in touch. Your enquiry is saved for our team. An automatic confirmation email is not sent.</p>
                    <a class="contact-direct-link" href="/contact#contact-form">Send another message</a>
                <?php else: ?>
                    <h2>Send a message</h2>
                    <p>Enquiries are reviewed by our team. Automatic email confirmations are paused.</p>
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
                    <form method="post" action="/contact#contact-form" class="contact-form" toolname="prepare_contact_enquiry" tooldescription="Fill this existing customer enquiry form for human review. Submission saves an enquiry for the team, not a consultation booking or automatic email, and needs the visitor to click Send message.">
                        <input type="hidden" name="_csrf" value="<?= e($_SESSION['csrf_token'] ??= bin2hex(random_bytes(16))) ?>">
                        <div class="contact-form__row">
                            <div class="form-group">
                                <label for="contact-name">Name (required)</label>
                                <input id="contact-name" type="text" name="name" maxlength="120" autocomplete="name" toolparamdescription="Customer full name" required value="<?= e($submission['name']) ?>"<?= $fieldError('name') ?>>
                                <?= $errorMessage('name') ?>
                            </div>
                            <div class="form-group">
                                <label for="contact-email">Email (required)</label>
                                <input id="contact-email" type="email" name="email" maxlength="254" autocomplete="email" toolparamdescription="Reply-to email address" required value="<?= e($submission['email']) ?>"<?= $fieldError('email') ?>>
                                <?= $errorMessage('email') ?>
                            </div>
                        </div>
                        <div class="contact-form__row">
                            <div class="form-group">
                                <label for="contact-subject">Subject (required)</label>
                                <select id="contact-subject" name="subject" toolparamdescription="Choose product, temple, order or general enquiry; no appointment booking" required<?= $fieldError('subject') ?>>
                                    <option value="">Select a subject</option>
                                    <?php foreach ($subjects as $key => $label): ?>
                                        <option value="<?= e($key) ?>"<?= $submission['subject'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= $errorMessage('subject') ?>
                            </div>
                            <div class="form-group">
                                <label for="contact-phone">Phone (optional)</label>
                                <input id="contact-phone" type="tel" name="phone" maxlength="40" autocomplete="tel" toolparamdescription="Optional callback number" value="<?= e($submission['phone']) ?>"<?= $fieldError('phone') ?>>
                                <?= $errorMessage('phone') ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="contact-message">Message (required, 12–4,000 characters)</label>
                            <textarea id="contact-message" name="message" rows="4" minlength="12" maxlength="4000" toolparamdescription="Customer enquiry details; preferred date can be mentioned but no slot is reserved" required<?= $fieldError('message') ?>><?= e($submission['message']) ?></textarea>
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

<?= $seo['contact_json_ld'] ?? '' ?>
