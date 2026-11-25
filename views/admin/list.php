<div class="admin-card">
    <h2 style="font-size:1.1rem; margin:0 0 var(--space-md);"><?= e($title) ?></h2>
    <?php if (isset($paging)) require __DIR__.'/list-controls.php'; ?>
    <?php $tickets = ($collection ?? '') === 'support_tickets'; ?>
    <div class="table-wrap admin-records<?= $tickets ? ' admin-records--tickets' : '' ?>"<?= isset($paging) ? ' data-no-table-search' : '' ?>>
        <table<?= $tickets ? ' role="table"' : '' ?>>
            <thead><tr><th scope="col">ID</th><th scope="col">Status</th><th scope="col">Details</th><th scope="col">Created</th></tr></thead>
            <tbody>
            <?php if(empty($items)): ?>
                <tr><td colspan="4" style="text-align:center; color:var(--color-text-muted); padding:var(--space-2xl);"><?= !empty($paging['search']) ? 'No matching records.' : 'No '.e(strtolower($title)).' records yet.' ?></td></tr>
            <?php else: ?>
                <?php foreach($items as $item): ?>
                    <?php
                    // A record carrying nothing but an id is not a record the admin can
                    // act on. One such row rendered as "Active · ₹0" at the top of
                    // Orders, above real orders, with no date and no customer.
                    if (count(array_filter($item, fn($v, $k) => $k !== 'id' && $v !== '' && $v !== null, ARRAY_FILTER_USE_BOTH)) === 0) continue;
                    ?>
                    <tr class="<?= $tickets ? 'ticket-row' : (($collection ?? '') === 'orders' ? 'admin-order-row' : '') ?>"<?= $tickets ? ' role="row"' : '' ?>>
                        <td<?= $tickets ? ' role="cell"' : '' ?>><code class="admin-records__ref"><?= e(substr((string)($item['id'] ?? $item['slug'] ?? $item['code'] ?? 'record'), 0, 16)) ?></code></td>
                        <td<?= $tickets ? ' role="cell"' : '' ?>>
                            <?php if ($tickets): ?>
                                <?php $ticketTone = match ($item['status'] ?? 'open') { 'open' => 'warning', 'answered' => 'info', default => 'default' }; ?>
                                <span class="badge badge--<?= $ticketTone ?>"><?= e(ucfirst($item['status'] ?? 'open')) ?></span>
                            <?php else: ?>
                                <?= e(ucfirst(str_replace('_', ' ', (string)($item['status'] ?? 'active')))) ?>
                            <?php endif; ?>
                        </td>
                        <td<?= $tickets ? ' role="cell"' : '' ?>>
                            <?php if ($tickets): ?>
                                <strong class="ticket-identity"><?= e($item['customer_email'] ?? 'Guest') ?></strong>
                                <small class="ticket-state"><?= e(ucfirst((string)($item['priority'] ?? 'normal'))) ?> priority · <?= e(ucfirst(str_replace('_', ' ', (string)($item['work_state'] ?? 'active')))) ?><?php if (!empty($item['assigned_admin_id'])): ?> · Assigned<?php endif; ?></small>
                                <?php if(preg_match('/^[a-f0-9]{32}$/D',(string)($item['conversation_id']??''))): ?>
                                    <a class="ticket-evidence" href="/admin/agent/conversation/<?= e($item['conversation_id']) ?>">Authorized conversation evidence</a>
                                <?php endif; ?>
                                <span class="ticket-message"><?= e(mb_substr((string)($item['message'] ?? ''), 0, 120)) ?></span>
                                <?php if (!empty($item['context'])): ?>
                                <div class="ticket-context"><strong>Context</strong> <?= e($item['context']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($item['reply'])): ?>
                                <div class="ticket-reply"><strong>Saved reply</strong> <?= e(mb_substr((string)$item['reply'], 0, 200)) ?></div>
                                <?php endif; ?>
                                <details class="ticket-management">
                                    <summary>Manage ticket</summary>
                                    <form class="admin-form ticket-reply-form" method="POST" action="/admin/support-tickets/save">
                                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="id" value="<?= e($item['id'] ?? '') ?>">
                                        <input type="hidden" name="version" value="<?= (int)($item['version']??0) ?>">
                                        <div class="admin-form__row">
                                            <label>Priority
                                                <select name="priority">
                                                <?php foreach (['low', 'normal', 'high', 'urgent'] as $priority): ?>
                                                    <option value="<?= e($priority) ?>" <?= ($item['priority'] ?? 'normal') === $priority ? 'selected' : '' ?>><?= e(ucfirst($priority)) ?></option>
                                                <?php endforeach; ?>
                                                </select>
                                            </label>
                                            <label>Waiting on
                                                <select name="waiting_on">
                                                    <option value="customer">Customer</option>
                                                    <option value="team">Team</option>
                                                </select>
                                            </label>
                                        </div>
                                        <label>Snooze until (UTC) <input name="snoozed_until" type="datetime-local"></label>
                                        <label>Reply
                                            <textarea name="reply" rows="3"><?= e($item['reply'] ?? '') ?></textarea>
                                        </label>
                                        <div class="ticket-management__actions">
                                            <button class="btn btn-primary btn-sm" type="submit" name="action" value="reply">Save reply</button>
                                            <?php foreach(['assign'=>'Claim ticket','release'=>'Release','wait'=>'Mark waiting','snooze'=>'Snooze','close'=>'Close','reopen'=>'Reopen'] as $action=>$label): ?><button class="btn btn-sm btn-ghost" type="submit" name="action" value="<?= e($action) ?>"><?= e($label) ?></button><?php endforeach; ?>
                                            <button class="btn btn-sm btn-ghost" type="button" data-ticket-cancel hidden>Cancel</button>
                                        </div>
                                    </form>
                                </details>
                            <?php elseif(($collection ?? '') === 'appointments'): ?>
                                <strong><?= e($item['customer_email'] ?? 'Customer') ?></strong>
                                <span style="display:block;color:var(--color-text-muted);"><?= e(trim(($item['preferred_date'] ?? '') . ' ' . ($item['preferred_time'] ?? ''))) ?></span>
                                <span style="display:block;"><?= e($item['astrologer_slug'] ?? '') ?> · <?= e(str_replace('_', ' ', $item['mode'] ?? 'booking')) ?></span>
                            <?php elseif(($collection ?? '') === 'orders' && !empty($item['id'])): ?>
                                <a aria-label="Open order <?= e($item['id']) ?>" href="/admin/orders/<?= e(rawurlencode((string)$item['id'])) ?>"><?= e($item['customer_email'] ?? 'Order') ?></a>
                                <span style="color:var(--color-text-muted);"> · ₹<?= e((string)($item['total'] ?? 0)) ?></span>
                                <?php if (!empty($item['tax_review_required'])): ?><strong> · Tax review required</strong><?php endif; ?>
                                <?php if (!empty($item['fulfillment_review_required'])): ?><strong> · Fulfillment review required</strong><?php endif; ?>
                            <?php else: ?>
                                <?= e($item['name'] ?? $item['email'] ?? $item['message'] ?? $item['event'] ?? 'Record') ?>
                            <?php endif; ?>
                        </td>
                        <td<?= $tickets ? ' role="cell" class="ticket-date"' : '' ?>><?= e(substr((string)($item['created_at'] ?? ''), 0, 10)) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if ($tickets): ?>
<script>
document.querySelectorAll('[data-ticket-cancel]').forEach(button => {
    const disclosure = button.closest('details');
    const summary = disclosure.querySelector('summary');
    button.hidden = false;
    button.addEventListener('click', () => {
        disclosure.open = false;
        summary.focus();
    });
});
</script>
<?php endif; ?>
