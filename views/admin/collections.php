<div class="admin-card">
    <h2>Special Collections</h2>
    <p>Group existing products for a seasonal sale or promotion. Visibility can be set independently for the homepage and shop.</p>
    <form method="post" action="/admin/collections/save" class="admin-form" id="collection-form">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="id" id="collection-id">
        <label>Collection title <input name="name" id="collection-name" required maxlength="150" placeholder="Diwali Festive Sale"></label>
        <label><input type="checkbox" name="show_home" id="collection-home" value="1"> Show on homepage</label>
        <label><input type="checkbox" name="show_shop" id="collection-shop" value="1"> Show in shop menu</label>
        <fieldset><legend>Choose existing products</legend>
            <?php foreach ($products as $product): ?>
                <label><input type="checkbox" name="product_ids[]" value="<?= e((string)$product['id']) ?>"> <?= e((string)$product['name']) ?><?= !empty($product['is_hidden']) ? ' (hidden from shoppers)' : '' ?></label>
            <?php endforeach; ?>
        </fieldset>
        <div><button class="btn btn-primary">Save collection</button> <button type="reset" class="btn btn-ghost" id="collection-clear">Clear</button></div>
    </form>
</div>
<div class="admin-card"><h2>Saved collections</h2>
    <?php if (!$collections): ?><p>No collections yet.</p><?php endif; ?>
    <?php foreach ($collections as $collection): ?>
        <div class="admin-card" style="margin-bottom:1rem">
            <strong><?= e((string)$collection['name']) ?></strong>
            <p><?= count((array)($collection['product_ids'] ?? [])) ?> products · Home <?= !empty($collection['show_home']) ? 'on' : 'off' ?> · Shop <?= !empty($collection['show_shop']) ? 'on' : 'off' ?></p>
            <button type="button" class="btn btn-ghost collection-edit" data-collection="<?= e(json_encode($collection, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">Edit</button>
            <form method="post" action="/admin/collections/delete" style="display:inline" onsubmit="return confirm('Delete this collection? Its products will remain in the shop.');">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= e((string)$collection['id']) ?>"><button class="btn btn-ghost">Delete</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
<script>
document.querySelectorAll('.collection-edit').forEach(button => button.addEventListener('click', () => {
    const row = JSON.parse(button.dataset.collection), form = document.getElementById('collection-form');
    form.reset(); document.getElementById('collection-id').value = row.id;
    document.getElementById('collection-name').value = row.name;
    document.getElementById('collection-home').checked = !!row.show_home;
    document.getElementById('collection-shop').checked = !!row.show_shop;
    const ids = Array.isArray(row.product_ids) ? row.product_ids : [];
    form.querySelectorAll('input[name="product_ids[]"]').forEach(input => input.checked = ids.includes(input.value));
    form.scrollIntoView({behavior:'smooth'}); document.getElementById('collection-name').focus();
}));
document.getElementById('collection-clear').addEventListener('click', () => { document.getElementById('collection-id').value = ''; });
</script>
