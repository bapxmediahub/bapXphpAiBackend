<div class="admin-card collection-editor">
    <div class="collection-editor__heading"><div><span class="collection-eyebrow">Catalog / merchandising</span><h2>Special Collections</h2>
    <p>Bring existing products together for a seasonal sale or promotion. Choose where shoppers can find each collection.</p></div><span class="collection-count"><?= count($collections) ?> saved</span></div>
    <form method="post" action="/admin/collections/save" class="admin-form" id="collection-form">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="id" id="collection-id">
        <label>Collection title <input name="name" id="collection-name" required maxlength="150" placeholder="Diwali Festive Sale"></label>
        <label>Collection position <input type="number" name="sort_order" id="collection-sort-order" min="0" step="1" value="0"><small>Lower numbers appear first on the homepage and in the shop menu.</small></label>
        <div class="collection-placements" aria-label="Collection visibility">
            <label class="collection-placement"><input type="checkbox" name="show_home" id="collection-home" value="1"><span><strong>Homepage</strong><small>Feature this collection on the home page</small></span></label>
            <label class="collection-placement"><input type="checkbox" name="show_shop" id="collection-shop" value="1"><span><strong>Shop menu</strong><small>List this collection alongside shop filters</small></span></label>
        </div>
        <fieldset class="collection-products"><legend>Choose existing products and their display order</legend>
            <div class="collection-products__tools"><label for="collection-product-search">Find a product</label><input type="search" id="collection-product-search" placeholder="Search product names" autocomplete="off"><span id="collection-selection-count" aria-live="polite">0 selected</span></div>
            <p id="collection-no-results" hidden>No products match your search.</p>
            <?php foreach ($products as $product): ?>
                <div class="collection-product" data-product-name="<?= e((string)$product['name']) ?>">
                    <label><input type="checkbox" name="product_ids[]" value="<?= e((string)$product['id']) ?>"> <?= e((string)$product['name']) ?><?= !empty($product['is_hidden']) ? ' (hidden from shoppers)' : '' ?></label>
                    <label>Position <input type="number" min="0" step="1" name="product_order[<?= e((string)$product['id']) ?>]" value="0" aria-label="Display position for <?= e((string)$product['name']) ?>"></label>
                </div>
            <?php endforeach; ?>
        </fieldset>
        <div><button class="btn btn-primary">Save collection</button> <button type="reset" class="btn btn-ghost" id="collection-clear">Clear</button></div>
    </form>
</div>
<div class="admin-card collection-saved"><h2>Saved collections</h2>
    <?php if (!$collections): ?><p>No collections yet.</p><?php endif; ?>
    <?php foreach ($collections as $collection): ?>
        <div class="collection-saved__row">
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
const collectionForm = document.getElementById('collection-form');
const productSearch = document.getElementById('collection-product-search');
const productRows = Array.from(document.querySelectorAll('.collection-product'));
const selectionCount = document.getElementById('collection-selection-count');
function updateCollectionPicker() {
    const query = productSearch.value.trim().toLocaleLowerCase();
    let visible = 0;
    productRows.forEach(row => { const match = row.dataset.productName.toLocaleLowerCase().includes(query); row.hidden = !match; if (match) visible++; });
    document.getElementById('collection-no-results').hidden = visible > 0;
    selectionCount.textContent = collectionForm.querySelectorAll('input[name="product_ids[]"]:checked').length + ' selected';
}
productSearch.addEventListener('input', updateCollectionPicker);
collectionForm.addEventListener('change', updateCollectionPicker);
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
    productSearch.value = ''; updateCollectionPicker();
    form.scrollIntoView({behavior:'smooth'}); document.getElementById('collection-name').focus();
}));
document.getElementById('collection-clear').addEventListener('click', () => { document.getElementById('collection-id').value = ''; productSearch.value = ''; requestAnimationFrame(updateCollectionPicker); });
updateCollectionPicker();
</script>
