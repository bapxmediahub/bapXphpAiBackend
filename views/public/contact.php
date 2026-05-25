<h1>Contact Us</h1>
<div class="panel contact-panel">
    <p>Email: <a href="mailto:<?= e($settings['contact_email'] ?? 'sripanchamispiritual@gmail.com') ?>"><?= e($settings['contact_email'] ?? 'sripanchamispiritual@gmail.com') ?></a></p>
    <?php if(!empty($settings['contact_phone'])): ?><p>Phone: <a href="tel:<?= e(preg_replace('/\s+/', '', $settings['contact_phone'])) ?>"><?= e($settings['contact_phone']) ?></a></p><?php endif; ?>
    <?php $whatsappNumber = ($settings['whatsapp_number'] ?? '') ?: preg_replace('/\D+/', '', (string)($settings['contact_phone'] ?? '')); ?>
    <?php if($whatsappNumber): ?><p><a class="button-link" href="https://wa.me/<?= e($whatsappNumber) ?>" target="_blank" rel="noopener">Chat on WhatsApp</a></p><?php endif; ?>
</div>
