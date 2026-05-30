<div class="admin-card">
    <h2 style="font-size:1.1rem; margin:0 0 var(--space-md);"><?= e($title) ?></h2>
    <p style="color:var(--color-text-muted); margin:0 0 var(--space-lg);">View <?= e(strtolower($title)) ?> records from JSON storage.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Status</th><th>Details</th><th>Created</th></tr></thead>
            <tbody>
            <?php if(empty($items)): ?>
                <tr><td colspan="4" style="text-align:center; color:var(--color-text-muted); padding:var(--space-2xl);">No <?= e(strtolower($title)) ?> records yet.</td></tr>
            <?php else: ?>
                <?php foreach($items as $item): ?>
                    <tr>
                        <td><code style="font-size:0.8rem; background:var(--color-bg-alt); padding:0.2rem 0.5rem; border-radius:var(--radius-sm);"><?= e(substr((string)($item['id'] ?? $item['slug'] ?? $item['code'] ?? 'record'), 0, 16)) ?></code></td>
                        <td><?= e(ucfirst(str_replace('_', ' ', (string)($item['status'] ?? 'active')))) ?></td>
                        <td>
                            <?php if(($collection ?? '') === 'orders' && !empty($item['id'])): ?>
                                <a href="/admin/orders/<?= e($item['id']) ?>"><?= e($item['customer_email'] ?? 'Order') ?></a>
                                <span style="color:var(--color-text-muted);"> · ₹<?= e((string)($item['total'] ?? 0)) ?></span>
                            <?php elseif(($collection ?? '') === 'support_tickets'): ?>
                                <strong><?= e($item['customer_email'] ?? 'Guest') ?></strong>
                                <span style="color:var(--color-text-muted);"> · <?= e($item['message'] ?? 'Support request') ?></span>
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
