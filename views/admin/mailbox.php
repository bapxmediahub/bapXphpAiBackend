<?php
$outbox = ($box ?? '') === 'outbox';
if ($outbox) {
    $scope = !empty($newsletter) ? ['type' => 'newsletter'] : [];
    $listUrl = static function (array $params = []) use ($scope): string {
        $query = http_build_query(array_filter($scope + $params, static fn($value) => $value !== ''));
        return '/admin/email-outbox' . ($query !== '' ? '?' . $query : '');
    };
    $activeFilters = ['q' => $paging['search'], 'state' => $state, 'per_page' => $paging['pageSize']];
    $deliveryStates = \App\Services\MailStorageService::DELIVERY_STATES;
}
?>
<div class="admin-card">
    <h2 style="font-size:1.1rem; margin:0 0 var(--space-md);"><?= e($title ?? 'Email') ?></h2>
    <p style="color:var(--color-text-muted); margin:0 0 var(--space-lg);">
        <?php if (!empty($newsletter)): ?>
            Blog newsletter messages and their delivery status.
        <?php else: ?>
        <?= ($box ?? '') === 'outbox'
            ? 'Outbound messages are stored here when the app queues payment, shipping, review, account, and newsletter emails.'
            : 'Inbound website messages are stored here from the contact form so the admin panel does not depend on an external mailbox.' ?>
        <?php endif; ?>
    </p>
    <?php if (!empty($newsletter) && (new \App\Services\SettingsService())->moduleEnabled('blog')): ?>
        <p><a href="/admin/blog">Open Blog editor</a> to prepare an article and notify subscribers.</p>
    <?php endif; ?>
    <?php if(isset($mailHealth)): ?>
    <section aria-labelledby="mail-health-title" style="margin-bottom:var(--space-lg)">
        <h3 id="mail-health-title">Queue health and delivery evidence</h3>
        <p>All <?= (int)$mailHealth['total'] ?> canonical queue records in this selection.
            <?= (int)$mailHealth['due_pending'] ?> eligible pending messages are due.
            <?= (int)($mailHealth['held_contact_pending'] ?? 0) ?> pending contact messages are held (included in Pending).</p>
        <?php if(!empty($mailHealth['scheduled_samples']) || !empty($mailHealth['inactive_samples'])): ?>
        <p>The sample-only worker has <?= (int)$mailHealth['scheduled_samples'] ?> pending test samples. <?= (int)$mailHealth['inactive_samples'] ?> samples are expired or otherwise inactive.</p>
        <?php endif; ?>
        <div class="admin-stats">
        <?php foreach(['pending'=>'Pending','sending'=>'Attempt in progress or interrupted','sent'=>'SMTP accepted or legacy sent','failed'=>'Not accepted / failed','needs_review'=>'Acceptance unknown: review before retry','unknown'=>'Legacy/unclassified'] as $state=>$label): ?>
            <div class="admin-stat"><div class="admin-stat__value"><?= (int)$mailHealth['states'][$state] ?></div><div class="admin-stat__label"><?= e($label) ?></div></div>
        <?php endforeach; ?>
        </div>
        <p><strong>Inbox receipt is unverified.</strong> SMTP acceptance is not proof of inbox placement.
            Automatic contact confirmations and owner enquiry notifications are paused. Review contact messages in the Inbox. Unknown or interrupted attempts are never automatically replayed. This view does not retry the historical backlog.</p>
    </section>
    <?php endif; ?>
    <?php if ($outbox): ?>
        <form class="mail-outbox-filters" method="get" action="/admin/email-outbox" aria-label="Filter outbox">
            <?php if ($scope): ?><input type="hidden" name="type" value="newsletter"><?php endif; ?>
            <label>Search messages
                <input type="search" name="q" value="<?= e($paging['search']) ?>" maxlength="150" placeholder="Recipient, subject or reference">
            </label>
            <label>Delivery state
                <select name="state">
                    <option value="">All states</option>
                    <?php foreach ($deliveryStates as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= $state === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Per page
                <select name="per_page">
                    <?php foreach (\App\Services\MailStorageService::OUTBOX_PAGE_SIZES as $size): ?>
                        <option value="<?= $size ?>"<?= $paging['pageSize'] === $size ? ' selected' : '' ?>><?= $size ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn btn-primary" type="submit">Apply filters</button>
            <?php if ($paging['search'] !== '' || $state !== '' || $paging['pageSize'] !== 25): ?>
                <a class="btn btn-outline" href="<?= e($listUrl()) ?>">Reset filters</a>
            <?php endif; ?>
        </form>
        <p class="mail-outbox-results">
            <?php if ($items): ?>
                Showing <?= number_format($paging['offset'] + 1) ?>–<?= number_format($paging['offset'] + count($items)) ?>
                of <?= number_format($paging['count']) ?> outbox records
            <?php else: ?>0 matching outbox records<?php endif; ?>
        </p>
    <?php endif; ?>
    <div class="table-wrap"<?= $outbox ? ' data-no-table-search role="region" aria-label="Outbox results" tabindex="0"' : '' ?>>
        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th><?= ($box ?? '') === 'outbox' ? 'To' : 'From' ?></th>
                    <th>Subject</th>
                    <th>Message</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
            <?php if(empty($items)): ?>
                <tr><td colspan="5" style="text-align:center; color:var(--color-text-muted); padding:var(--space-2xl);">
                    <?= $outbox && ($paging['search'] !== '' || $state !== '') ? 'Try another search or reset the filters.' : 'No ' . e(strtolower($title ?? 'email')) . ' records yet.' ?>
                </td></tr>
            <?php else: ?>
                <?php foreach($items as $item):
                    $status = (string)($item['status'] ?? 'pending');
                    $label = $outbox ? ($deliveryStates[$status] ?? $deliveryStates['unknown']) : ucfirst(str_replace('_', ' ', $status));
                    if ($outbox && $status === 'sent') {
                        $label = ($item['delivery_state'] ?? null) === 'accepted_by_smtp'
                            ? 'SMTP accepted / receipt unverified' : 'Legacy sent / receipt unverified';
                    }
                    ?>
                    <tr<?= $outbox ? ' data-mail-delivery-state="' . e($status) . '"' : '' ?>>
                        <td><?= e($label) ?>
                            <?php if(!empty($item['error_code'])): ?><br><small><?= e(str_replace('_',' ',(string)$item['error_code'])) ?></small><?php endif; ?>
                            <?php if ($outbox && (!empty($item['queue_id']) || !empty($item['message_id']))): ?>
                                <details class="mail-outbox-references"><summary>References</summary>
                                    <?php if (!empty($item['queue_id'])): ?><span>Queue <code><?= e((string)$item['queue_id']) ?></code></span><?php endif; ?>
                                    <?php if (!empty($item['message_id'])): ?><span>Message <code><?= e((string)$item['message_id']) ?></code></span><?php endif; ?>
                                </details>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if(($box ?? '') === 'outbox'): ?>
                                <strong><?= e($item['to_email'] ?? '') ?></strong>
                                <?php if(!empty($item['from_email'])): ?><br><span style="color:var(--color-text-muted); font-size:0.8rem;">from <?= e($item['from_email']) ?></span><?php endif; ?>
                            <?php else: ?>
                                <strong><?= e($item['from_name'] ?? 'Website visitor') ?></strong>
                                <br><span style="color:var(--color-text-muted); font-size:0.8rem;"><?= e($item['from_email'] ?? '') ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($item['subject'] ?? '') ?></td>
                        <?php $preview = strip_tags((string)($item['body'] ?? '')); ?>
                        <td style="max-width:420px; color:var(--color-text-muted);"><?= e(mb_strimwidth($preview, 0, 180, '…', 'UTF-8')) ?></td>
                        <td><?= e(substr((string)($item['created_at'] ?? ''), 0, 16)) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($outbox && $paging['pages'] > 1): ?>
        <nav class="mail-outbox-pages" aria-label="Outbox pages">
            <?php if ($paging['page'] > 1): ?><a class="btn btn-outline" href="<?= e($listUrl($activeFilters + ['page' => $paging['page'] - 1])) ?>">Previous</a><?php endif; ?>
            <span>Page <?= number_format($paging['page']) ?> of <?= number_format($paging['pages']) ?></span>
            <?php if ($paging['page'] < $paging['pages']): ?><a class="btn btn-outline" href="<?= e($listUrl($activeFilters + ['page' => $paging['page'] + 1])) ?>">Next</a><?php endif; ?>
        </nav>
    <?php endif; ?>
</div>
