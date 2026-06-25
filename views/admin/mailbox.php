<div class="admin-card">
    <h2 style="font-size:1.1rem; margin:0 0 var(--space-md);"><?= e($title ?? 'Email') ?></h2>
    <p style="color:var(--color-text-muted); margin:0 0 var(--space-lg);">
        <?= ($box ?? '') === 'outbox'
            ? 'Outbound messages are stored here when the app queues payment, shipping, review, and account emails.'
            : 'Inbound website messages are stored here from the contact form so the admin panel does not depend on an external mailbox.' ?>
    </p>
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
                        <td><?= e(ucfirst(str_replace('_', ' ', (string)($item['status'] ?? 'pending')))) ?></td>
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
