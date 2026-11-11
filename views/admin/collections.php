<div class="admin-card">
    <h2>Special Collections</h2>
    <p>Group existing products for a seasonal sale or promotion. Visibility can be set independently for the homepage and shop.</p>
    <form method="post" action="/admin/collections/save" class="admin-form" id="collection-form">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="id" id="collection-id">
        <label>Collection title <input name="name" id="collection-name" required maxlength="150" placeholder="Diwali Festive Sale"></label>
        <label>Collection position <input type="number" name="sort_order" id="collection-sort-order" min="0" step="1" value="0"><small>Lower numbers appear first on the homepage and in the shop menu.</small></label>
        <label><input type="checkbox" name="show_home" id="collection-home" value="1"> Show on homepage</label>
        <label><input type="checkbox" name="show_shop" id="collection-shop" value="1"> Show in shop menu</label>
        <fieldset><legend>Choose existing products and their display order</legend>
            <?php foreach ($products as $product): ?>
                <div style="display:grid;grid-template-columns:minmax(0,1fr) 7rem;gap:.75rem;align-items:center;margin-bottom:.5rem">
                    <label><input type="checkbox" name="product_ids[]" value="<?= e((string)$product['id']) ?>"> <?= e((string)$product['name']) ?><?= !empty($product['is_hidden']) ? ' (hidden from shoppers)' : '' ?></label>
                    <label>Position <input type="number" min="0" step="1" name="product_order[<?= e((string)$product['id']) ?>]" value="0" aria-label="Display position for <?= e((string)$product['name']) ?>"></label>
                </div>
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
            <p><?= count((array)($collection['product_ids'] ?? [])) ?> products · Position <?= (int)($collection['sort_order'] ?? 0) ?> · Home <?= !empty($collection['show_home']) ? 'on' : 'off' ?> · Shop <?= !empty($collection['show_shop']) ? 'on' : 'off' ?></p>
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
    document.getElementById('collection-sort-order').value = Math.max(0, Number.parseInt(row.sort_order || 0, 10));
    document.getElementById('collection-home').checked = !!row.show_home;
    document.getElementById('collection-shop').checked = !!row.show_shop;
    const ids = Array.isArray(row.product_ids) ? row.product_ids : [];
    form.querySelectorAll('input[name="product_ids[]"]').forEach(input => {
        input.checked = ids.includes(input.value);
        const order = form.querySelector(`input[name="product_order[${CSS.escape(input.value)}]"]`);
        if (order) order.value = input.checked ? ids.indexOf(input.value) + 1 : 0;
    });
    form.scrollIntoView({behavior:'smooth'}); document.getElementById('collection-name').focus();
}));
document.getElementById('collection-clear').addEventListener('click', () => { document.getElementById('collection-id').value = ''; });
</script>
