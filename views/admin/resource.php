<div class="admin-header">
    <h1><?= e($title) ?></h1>
    <a href="/admin" class="btn btn-sm btn-ghost">← Dashboard</a>
</div>

<div class="admin-card">
    <form id="resource-form" method="post" action="/admin/<?= e($collection) ?>/save" class="admin-form">
        <input type="hidden" name="id" id="resource-id">
        <div class="admin-form__row" style="grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));">
            <?php foreach($fields as $field): ?>
                <label><?= e(ucwords(str_replace('_',' ',$field))) ?>
                    <?php if($field === 'description'): ?>
                        <textarea name="<?= e($field) ?>" id="field-<?= e($field) ?>" rows="3"></textarea>
                    <?php elseif($field === 'active'): ?>
                        <label style="flex-direction:row; align-items:center; gap:var(--space-xs); text-transform:none; font-weight:400;">
                            <input type="checkbox" name="<?= e($field) ?>" id="field-<?= e($field) ?>" value="1" checked> Active
                        </label>
                    <?php elseif(str_contains($field, '_url')): ?>
                        <input type="url" name="<?= e($field) ?>" id="field-<?= e($field) ?>" placeholder="https://...">
                    <?php elseif(str_contains($field, 'price') || str_contains($field, 'amount') || str_contains($field, 'value') || $field === 'slot_minutes' || $field === 'experience_years'): ?>
                        <input type="number" name="<?= e($field) ?>" id="field-<?= e($field) ?>" placeholder="0" step="any">
                    <?php elseif(str_contains($field, 'email')): ?>
                        <input type="email" name="<?= e($field) ?>" id="field-<?= e($field) ?>" placeholder="email@example.com">
                    <?php elseif(str_contains($field, '_days') || str_contains($field, 'modes') || str_contains($field, 'languages')): ?>
                        <input type="text" name="<?= e($field) ?>" id="field-<?= e($field) ?>" placeholder="Comma-separated values">
                    <?php elseif(str_contains($field, '_time')): ?>
                        <input type="time" name="<?= e($field) ?>" id="field-<?= e($field) ?>">
                    <?php elseif(str_contains($field, 'status')): ?>
                        <select name="<?= e($field) ?>" id="field-<?= e($field) ?>">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="draft">Draft</option>
                        </select>
                    <?php else: ?>
                        <input type="text" name="<?= e($field) ?>" id="field-<?= e($field) ?>" placeholder="">
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
        </div>
        <div style="margin-top:var(--space-md); display:flex; gap:var(--space-sm);">
            <button type="submit" class="btn btn-primary">Save <?= e(rtrim($title,'s')) ?></button>
            <button type="reset" class="btn btn-ghost" onclick="document.getElementById('resource-id').value='';">Clear Form</button>
        </div>
    </form>
</div>

<div class="admin-card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-md);">
        <h2 style="margin:0;">All <?= e($title) ?> (<?= count($items) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><?php foreach($fields as $field): ?><th><?= e(ucwords(str_replace('_',' ',$field))) ?></th><?php endforeach; ?><th>Actions</th></tr></thead>
            <tbody>
            <?php if(empty($items)): ?>
                <tr><td colspan="<?= count($fields) + 1 ?>" style="text-align:center; color:var(--color-text-muted); padding:var(--space-2xl);">No records yet. Add one using the form above.</td></tr>
            <?php else: ?>
                <?php foreach($items as $item): ?>
                    <tr>
                        <?php foreach($fields as $field): ?>
                            <td style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                <?php if(str_contains($field, '_url') && !empty($item[$field])): ?>
                                    <a href="<?= e($item[$field]) ?>" target="_blank" style="font-size:0.8rem;">View ↗</a>
                                <?php elseif($field === 'active'): ?>
                                    <span class="badge badge--<?= !empty($item[$field]) ? 'success' : 'default' ?>"><?= !empty($item[$field]) ? 'Yes' : 'No' ?></span>
                                <?php else: ?>
                                    <?= e(is_array($item[$field]??null)?implode(', ',$item[$field]):(string)($item[$field]??'')) ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td>
                            <div style="display:flex; gap:var(--space-xs);">
                                <button type="button" class="btn btn-sm btn-ghost edit-item" data-item='<?= e(json_encode(array_merge($item, ['__id' => $item['id'] ?? '']))) ?>'>Edit</button>
                                <form method="post" action="/admin/<?= e($collection) ?>/delete" onsubmit="return confirm('Delete this record?');">
                                    <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                    <button class="btn btn-sm" style="background:var(--color-error-light); color:var(--color-error);">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
document.querySelectorAll('.edit-item').forEach(button => {
    button.addEventListener('click', () => {
        const item = JSON.parse(button.dataset.item || '{}');
        document.getElementById('resource-id').value = item.__id || '';
        <?php foreach($fields as $field): ?>
            let el = document.getElementById('field-<?= e($field) ?>');
            if (el) {
                if (el.type === 'checkbox') {
                    el.checked = !!item['<?= e($field) ?>'];
                } else {
                    el.value = Array.isArray(item['<?= e($field) ?>']) ? item['<?= e($field) ?>'].join(', ') : (item['<?= e($field) ?>'] ?? '');
                }
            }
        <?php endforeach; ?>
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});
document.getElementById('resource-form').addEventListener('reset', () => {
    document.getElementById('resource-id').value = '';
});
</script>
