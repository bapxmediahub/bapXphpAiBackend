<h1><?= e($title) ?></h1><?php if(!empty($_SESSION['flash'])): ?><p class="notice"><?= e($_SESSION['flash']); unset($_SESSION['flash']); ?></p><?php endif; ?>
<form id="resource-form" method="post" action="/admin/<?= e($collection) ?>/save" class="admin-form">
    <input type="hidden" name="id" id="resource-id">
    <?php foreach($fields as $field): ?>
        <label><?= e(ucwords(str_replace('_',' ',$field))) ?>
            <?php if($field === 'description'): ?>
                <textarea name="<?= e($field) ?>" id="field-<?= e($field) ?>"></textarea>
            <?php elseif(str_starts_with($field, 'is_')): ?>
                <input type="checkbox" name="<?= e($field) ?>" id="field-<?= e($field) ?>" value="1">
            <?php else: ?>
                <input name="<?= e($field) ?>" id="field-<?= e($field) ?>">
            <?php endif; ?>
        </label>
    <?php endforeach; ?>
    <button type="submit">Save <?= e(rtrim($title,'s')) ?></button>
</form>
<table>
    <tr><?php foreach($fields as $field): ?><th><?= e(ucwords(str_replace('_',' ',$field))) ?></th><?php endforeach; ?><th>Action</th><th>Edit</th></tr>
    <?php foreach($items as $item): ?>
        <tr>
            <?php foreach($fields as $field): ?>
                <td><?= e(is_array($item[$field]??null)?implode(', ',$item[$field]):(string)($item[$field]??'')) ?></td>
            <?php endforeach; ?>
            <td>
                <form method="post" action="/admin/<?= e($collection) ?>/delete">
                    <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                    <button>Delete</button>
                </form>
            </td>
            <td>
                <button type="button" class="edit-item" data-item="<?= e(json_encode(array_merge($item, ['__id' => $item['id'] ?? '']))) ?>">Edit</button>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<script>
document.querySelectorAll('.edit-item').forEach(button => {
    button.addEventListener('click', () => {
        const item = JSON.parse(button.dataset.item || '{}');
        document.getElementById('resource-id').value = item.__id || '';
        <?php foreach($fields as $field): ?>
            let field = document.getElementById('field-<?= e($field) ?>');
            if (field) {
                if (field.type === 'checkbox') {
                    field.checked = !!item['<?= e($field) ?>'];
                } else {
                    field.value = Array.isArray(item['<?= e($field) ?>']) ? item['<?= e($field) ?>'].join(', ') : (item['<?= e($field) ?>'] ?? '');
                }
            }
        <?php endforeach; ?>
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});
</script>
