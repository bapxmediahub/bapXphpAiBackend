<section class="mail-template-workspace" aria-labelledby="mail-templates-title">
<header style="max-width:800px;margin-bottom:24px"><span class="eyebrow">EMAIL · RIGHT EVENT, RIGHT RECIPIENT</span>
<h2 id="mail-templates-title">Templates &amp; triggers</h2>
<p>Review the actual email renderer with synthetic data. Customer messages and owner notifications are different audiences. Previewing never creates an order, issues an invoice or sends a message.</p></header>
<?php if(is_array($testResult??null)): ?>
<div class="admin-card" role="status" data-template-test-result data-queue-id="<?= e((string)($testResult['queue_id']??'')) ?>" data-send-status="<?= e((string)$testResult['status']) ?>">
    <strong><?= $testResult['status']==='sent'?'SMTP accepted this test sample; inbox receipt is not verified.':'The sample did not produce a confirmed accepted-send result.' ?></strong>
    <p>Destination role: <?= e($testResult['target']==='owner'?'configured owner notification inbox':'explicit customer test inbox') ?>. Check the actual recipient inbox and <a href="/admin/email-outbox">outbox evidence</a>. Do not repeat an uncertain send blindly.</p>
</div>
<?php endif; ?>
<div class="mail-template-layout">
<aside class="admin-card">
    <form action="/admin/email-templates" method="get">
        <label for="mail-template-choice">Message to preview</label>
        <select name="template" id="mail-template-choice" style="width:100%;padding:12px;margin:8px 0 12px"><?php foreach($templates as $key=>$item): ?><option value="<?= e($key) ?>"<?= $key===$template?' selected':'' ?>><?= e($item['title']) ?></option><?php endforeach; ?></select>
        <button class="btn btn-primary" type="submit">Preview template</button>
    </form>
    <h3 style="margin-top:24px">When it is sent</h3><p><?= e($templates[$template]['trigger']) ?></p>
    <p><strong>Audience:</strong> <?= $preview['audience']==='admin'?'Owner notification — never substitute the customer recipient':'Customer account/order recipient' ?></p>
    <p><strong>Preview data:</strong> synthetic only. Sample invoice links, order references and password-reset links are not usable.</p>
    <p>Only existing issued invoices get a real invoice link. The customer must sign in to their own account. No PDF attachment is promised by this implementation.</p>
</aside>
<div class="admin-card mail-template-preview">
    <h3>Inbox preview</h3><p style="overflow-wrap:anywhere"><strong>Subject:</strong> <?= e($preview['subject']) ?><br><strong>Preview text:</strong> <?= e($preview['preheader']) ?></p>
    <iframe title="Synthetic email preview" sandbox="" referrerpolicy="no-referrer" srcdoc="<?= e($preview['html']) ?>" style="width:100%;min-height:920px;border:1px solid var(--color-border);background:#faf7f0"></iframe>
    <details style="margin-top:16px"><summary>Plain-text alternative</summary>
        <p>The same visible sample content, without CSS or hidden preview text. No message is sent by opening this section.</p>
        <pre data-mail-plain-preview style="white-space:pre-wrap;overflow-wrap:anywhere;max-width:100%;font-size:.85rem;line-height:1.6"><?= e(\App\Services\SmtpMailer::plainText($preview['html'])) ?></pre>
    </details>
</div>
</div>
<section class="admin-card" aria-labelledby="mail-test-title" style="margin-top:24px">
    <h3 id="mail-test-title">Send one clearly labelled test sample</h3>
    <p>This uses the configured SMTP sender and the normal mail queue. It creates no purchase, invoice, account or payment. A received sample tests transport/layout—not the real paid-order trigger.</p>
    <form action="/admin/email-templates/test" method="post" class="admin-form" data-mail-template-test>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="template" value="<?= e($template) ?>"><input type="hidden" name="test_token" value="<?= e($testToken) ?>">
        <fieldset style="border:1px solid var(--color-border);padding:18px"><legend>Choose the destination role explicitly</legend>
            <label><input type="radio" name="target" value="customer"<?= $preview['audience']!=='admin'?' checked':' disabled' ?>> Customer test recipient</label>
            <label for="template-recipient">Customer test email<input id="template-recipient" name="recipient" type="email" placeholder="Enter the customer test mailbox" autocomplete="off"<?= $preview['audience']==='admin'?' disabled':' required' ?>></label>
            <label><input type="radio" name="target" value="owner"<?= !$ownerConfigured||$preview['audience']!=='admin'?' disabled':' checked' ?>> Configured owner notification inbox — <?= e($ownerLabel) ?></label>
            <small>Customer templates go only to the entered customer test address; owner templates go only to the configured owner. Choosing owner ignores the customer email field. It does not change the saved owner address. The connected customer Gmail cannot prove receipt in this different inbox.</small>
        </fieldset>
        <label><input type="checkbox" name="confirm_sample" value="1" required> I confirm this is synthetic test content and I am authorized to send one sample to the selected recipient.</label>
        <button class="btn btn-primary" type="submit">Send test sample once</button>
    </form>
</section>
<section class="admin-card" style="margin-top:24px"><h3>Other current lifecycle messages</h3>
    <p>Contact enquiries are saved in Contacts and Email Inbox; automatic visitor and owner contact mail remains held. Security sign-in notices and explicitly selected newsletters keep their existing triggers. A delayed product-review message is only scheduled in the queue; the due-pending processor must run to send it. Opening this page never processes a backlog.</p>
    <p>Refund/cancellation email automation and PDF attachments are not implied by an order status or this preview menu. Confirm the actual implemented trigger before promising a message. Consult <a href="/admin/docs/email">Email help</a> and <a href="/admin/integrations">SMTP configuration</a>.</p>
</section>
</section>
