<div class="admin-card">
    <h2 style="font-size:1.1rem; margin:0 0 var(--space-md);"><?= e($title) ?></h2>
    <p style="color:var(--color-text-muted); margin:0 0 var(--space-lg);">View <?= e(strtolower($title)) ?> records.</p>
    <?php if(($collection ?? '') === 'support_tickets'): ?>
    <style>
    .ticket-row{cursor:pointer}.ticket-row:hover{background:var(--color-bg-alt)}.ticket-reply-form{display:none;background:var(--color-bg-alt);padding:var(--space-md);border-radius:var(--radius-md);margin-top:var(--space-xs)}.ticket-reply-form.open{display:block}.ticket-reply-form textarea{width:100%;min-height:80px;padding:var(--space-sm);border:1px solid var(--color-border);border-radius:var(--radius-sm);font-family:inherit;font-size:0.85rem;resize:vertical}.ticket-context{font-size:0.75rem;color:var(--color-text-muted);margin-top:var(--space-xs);padding:var(--space-xs);background:var(--color-bg);border-radius:var(--radius-sm)}.ticket-reply{background:var(--color-bg);padding:var(--space-sm);border-radius:var(--radius-sm);margin-top:var(--space-xs);border-left:3px solid var(--color-gold)}
    </style>
    <?php endif; ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Status</th><th>Details</th><th>Created</th></tr></thead>
            <tbody>
            <?php if(empty($items)): ?>
                <tr><td colspan="4" style="text-align:center; color:var(--color-text-muted); padding:var(--space-2xl);">No <?= e(strtolower($title)) ?> records yet.</td></tr>
            <?php else: ?>
                <?php foreach($items as $item): ?>
                    <tr class="<?= (($collection ?? '') === 'support_tickets') ? 'ticket-row' : '' ?>" <?= (($collection ?? '') === 'support_tickets') ? 'onclick="toggleTicket(this)"' : '' ?>>
                        <td><code style="font-size:0.8rem; background:var(--color-bg-alt); padding:0.2rem 0.5rem; border-radius:var(--radius-sm);"><?= e(substr((string)($item['id'] ?? $item['slug'] ?? $item['code'] ?? 'record'), 0, 16)) ?></code></td>
                        <td>
                            <?php if(($collection ?? '') === 'support_tickets'): ?>
                                <span style="display:inline-block; padding:0.1rem 0.5rem; border-radius:var(--radius-pill); font-size:0.72rem; font-weight:600; <?= ($item['status'] ?? 'open') === 'open' ? 'background:#fef3c7;color:#92400e' : (($item['status'] ?? '') === 'answered' ? 'background:#dbeafe;color:#1e40af' : 'background:#e5e7eb;color:#374151') ?>"><?= e(ucfirst($item['status'] ?? 'open')) ?></span>
                            <?php else: ?>
                                <?= e(ucfirst(str_replace('_', ' ', (string)($item['status'] ?? 'active')))) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if(($collection ?? '') === 'support_tickets'): ?>
                                <strong><?= e($item['customer_email'] ?? 'Guest') ?></strong>
                                <span style="color:var(--color-text-muted); display:block; margin-top:0.2rem;"><?= e(mb_substr((string)($item['message'] ?? ''), 0, 120)) ?></span>
                                <?php if (!empty($item['context'])): ?>
                                <div class="ticket-context"><?= e($item['context']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($item['reply'])): ?>
                                <div class="ticket-reply"><strong>Reply:</strong> <?= e(mb_substr((string)$item['reply'], 0, 200)) ?></div>
                                <?php endif; ?>
                                <div class="ticket-reply-form" data-ticket-id="<?= e($item['id'] ?? '') ?>">
                                    <form method="POST" action="/admin/support-tickets/save">
                                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="id" value="<?= e($item['id'] ?? '') ?>">
                                        <textarea name="reply" placeholder="Write your reply…" required><?= e($item['reply'] ?? '') ?></textarea>
                                        <div style="display:flex; gap:var(--space-sm); margin-top:var(--space-sm);">
                                            <button class="btn btn-primary btn-sm" type="submit">Send Reply</button>
                                            <button class="btn btn-sm btn-ghost" type="button" onclick="event.stopPropagation();this.closest('.ticket-reply-form').classList.remove('open')">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            <?php elseif(($collection ?? '') === 'orders' && !empty($item['id'])): ?>
                                <a href="/admin/orders/<?= e($item['id']) ?>"><?= e($item['customer_email'] ?? 'Order') ?></a>
                                <span style="color:var(--color-text-muted);"> · ₹<?= e((string)($item['total'] ?? 0)) ?></span>
                            <?php else: ?>
                                <?= e($item['name'] ?? $item['email'] ?? $item['message'] ?? $item['event'] ?? 'Record') ?>
                            <?php endif; ?>
                        </td>
                        <td><?= e(substr((string)($item['created_at'] ?? ''), 0, 10)) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if(($collection ?? '') === 'support_tickets'): ?>
<script>
function toggleTicket(row){
    var form = row.querySelector('.ticket-reply-form');
    if(form) form.classList.toggle('open');
}
</script>
<?php endif; ?>