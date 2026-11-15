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
            <?= (int)$mailHealth['due_pending'] ?> pending messages are due.
            This page shows up to 100 of <?= (int)$totalOutbox ?> outbox records.</p>
        <div class="admin-stats">
        <?php foreach(['pending'=>'Pending','sending'=>'Attempt in progress or interrupted','sent'=>'SMTP accepted or legacy sent','failed'=>'Not accepted / failed','needs_review'=>'Acceptance unknown: review before retry','unknown'=>'Legacy/unclassified'] as $state=>$label): ?>
            <div class="admin-stat"><div class="admin-stat__value"><?= (int)$mailHealth['states'][$state] ?></div><div class="admin-stat__label"><?= e($label) ?></div></div>
        <?php endforeach; ?>
        </div>
        <p><strong>Inbox receipt is unverified.</strong> SMTP acceptance is not proof of inbox placement.
            Unknown or interrupted attempts are never automatically replayed. This view does not retry the historical backlog.</p>
    </section>
    <?php endif; ?>
    <div class="table-wrap">
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
                <tr><td colspan="5" style="text-align:center; color:var(--color-text-muted); padding:var(--space-2xl);">No <?= e(strtolower($title ?? 'email')) ?> records yet.</td></tr>
            <?php else: ?>
                <?php foreach($items as $item): ?>
                    <tr>
                        <td><?= e(($box??'')==='outbox' && ($item['status']??'')==='sent' ? 'SMTP accepted / receipt unverified' : ucfirst(str_replace('_',' ',(string)($item['status']??'pending')))) ?>
                            <?php if(!empty($item['error_code'])): ?><br><small><?= e(str_replace('_',' ',(string)$item['error_code'])) ?></small><?php endif; ?>
                            <?php if(!empty($item['message_id'])): ?><details><summary>Message reference</summary><code style="overflow-wrap:anywhere"><?= e((string)$item['message_id']) ?></code></details><?php endif; ?>
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
                        <td style="max-width:420px; color:var(--color-text-muted);"><?= e(strlen($preview) > 180 ? substr($preview, 0, 177) . '...' : $preview) ?></td>
                        <td><?= e(substr((string)($item['created_at'] ?? ''), 0, 16)) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
