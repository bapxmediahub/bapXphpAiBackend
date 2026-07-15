<div class="admin-card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:var(--space-sm); margin-bottom:var(--space-lg);">
        <h2 style="margin:0; font-size:1.1rem;"><?= e($title) ?></h2>
        <span class="badge badge--default"><?= count($items) ?> record<?= count($items) !== 1 ? 's' : '' ?></span>
    </div>
    <form id="product-form" method="post" action="/admin/<?= e($collection) ?>/save" class="admin-form" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="id" id="resource-id">
        <input type="hidden" name="image_url" id="field-image_url">
        <textarea name="image_urls" id="field-image_urls" style="display:none"></textarea>
        <div style="display:grid; grid-template-columns: 1fr 340px; gap: var(--space-xl); align-items:start;">
            <div style="display:grid; gap:var(--space-sm);">
                <label>Slug
                    <input type="text" name="slug" id="field-slug" placeholder="product-slug">
                </label>
                <label>Name
                    <input type="text" name="name" id="field-name" placeholder="Product name" required>
                </label>
                <label>Category
                    <input type="text" name="category" id="field-category" placeholder="e.g. puja-items" style="margin-bottom:var(--space-xs);">
                    <div id="category-chips" style="display:flex; flex-wrap:wrap; gap:0.3rem;">
                    <?php $catValue = ''; ?>
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $cat): ?>
                            <?php $catName = $cat['name'] ?? $cat['slug'] ?? ''; ?>
                            <?php if ($catName): ?>
                            <span class="cat-chip" data-slug="<?= e($cat['slug'] ?? '') ?>" style="display:inline-flex; align-items:center; gap:0.2rem; padding:0.2rem 0.6rem; font-size:0.75rem; border-radius:var(--radius-sm); border:1px solid var(--color-border); background:var(--color-bg-alt); cursor:pointer; transition:all 0.15s; user-select:none;">
                                <?= e($catName) ?>
                            </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div>
                </label>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-sm);">
                    <label>Price (₹)
                        <input type="number" name="price" id="field-price" placeholder="0" step="any">
                    </label>
                    <label>Offer Price (₹)
                        <input type="number" name="offer_price" id="field-offer_price" placeholder="0" step="any">
                    </label>
                </div>
                <label>Stock Status
                    <select name="stock_status" id="field-stock_status">
                        <option value="in_stock">In Stock</option>
                        <option value="out_of_stock">Out of Stock</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="draft">Draft</option>
                    </select>
                </label>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-sm);">
                    <label>HSN Code
                        <input type="text" name="hsn_code" id="field-hsn_code" placeholder="e.g. 3307" maxlength="8">
                    </label>
                    <label>GST Rate (%)
                        <input type="number" name="gst_rate" id="field-gst_rate" placeholder="e.g. 12" min="0" max="100" step="0.01">
                    </label>
                </div>
                <label>Description
                    <textarea name="description" id="field-description" rows="4"></textarea>
                </label>
            </div>
            <div style="display:grid; gap:var(--space-lg);">
                <div>
                    <label style="display:block; margin-bottom:var(--space-xs); font-weight:600; font-size:0.85rem; text-transform:uppercase; letter-spacing:0.05em;">Featured Image</label>
                    <div id="featured-preview" style="aspect-ratio:1; background:var(--color-bg-alt); border:2px dashed var(--color-border); border-radius:var(--radius-md); display:flex; align-items:center; justify-content:center; overflow:hidden; margin-bottom:var(--space-xs); position:relative;">
                        <span style="color:var(--color-text-muted); font-size:0.8rem;" id="featured-placeholder">No image</span>
                        <img id="featured-img" src="" alt="" style="display:none; width:100%; height:100%; object-fit:cover; position:absolute; inset:0;">
                    </div>
                    <button type="button" class="btn btn-sm btn-ghost" onclick="document.getElementById('gallery-file-input').click()" style="width:100%;">Upload Featured</button>
                </div>
                <div>
                    <label style="display:block; margin-bottom:var(--space-xs); font-weight:600; font-size:0.85rem; text-transform:uppercase; letter-spacing:0.05em;">Gallery <span id="gallery-count" style="font-weight:400; color:var(--color-text-muted); text-transform:none;">(0)</span></label>
                    <div id="gallery-grid" style="display:grid; grid-template-columns:repeat(3, 1fr); gap:var(--space-xs); margin-bottom:var(--space-xs); min-height:80px;"></div>
                    <div style="display:flex; gap:var(--space-xs);">
                        <button type="button" class="btn btn-sm btn-ghost" onclick="document.getElementById('gallery-file-input').click()" style="flex:1;">+ Add Images</button>
                        <button type="button" class="btn btn-sm btn-ghost" id="open-media-library" style="flex:1;">From Library</button>
                    </div>
                    <input type="file" id="gallery-file-input" name="media_files[]" accept="image/png,image/jpeg,image/webp,image/gif" multiple style="display:none">
                </div>
                <?php if(!empty($mediaFiles)): ?>
                <div class="admin-media-picker" style="margin-top:var(--space-sm); display:none;">
                    <div class="admin-media-picker__head">
                        <strong>Media Library</strong>
                        <span>Newest first</span>
                    </div>
                    <div class="admin-media-grid">
                        <?php foreach($mediaFiles as $media): ?>
                            <div class="admin-media-tile">
                                <img src="<?= e($media['url'] ?? $media['path']) ?>" alt="<?= e($media['original_name'] ?? $media['filename'] ?? 'Media') ?>">
                                <small><?= e(substr((string)($media['created_at'] ?? ''), 0, 10)) ?></small>
                                <button type="button" class="btn btn-sm btn-ghost use-media" data-path="<?= e($media['url'] ?? $media['path']) ?>">Add</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div style="margin-top:var(--space-lg); display:flex; gap:var(--space-sm);">
            <button type="submit" class="btn btn-primary" id="save-btn">Save Product</button>
            <button type="reset" class="btn btn-ghost" onclick="resetForm()">Clear</button>
        </div>
    </form>
</div>

<div class="admin-card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Image</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if(empty($items)): ?>
                <tr><td colspan="6" style="text-align:center; color:var(--color-text-muted); padding:var(--space-2xl);">No products yet. Add one using the form above.</td></tr>
            <?php else: ?>
                <?php foreach($items as $item): ?>
                    <tr>
                        <td>
                            <?php if(!empty($item['image_url'])): ?>
                                <img src="<?= e($item['image_url']) ?>" alt="" style="width:48px; height:48px; object-fit:cover; border-radius:var(--radius-sm);">
                            <?php else: ?>
                                <span style="color:var(--color-text-muted); font-size:0.75rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:600;"><?= e($item['name'] ?? '') ?></td>
                        <td><?= e($item['category'] ?? '—') ?></td>
                        <td>₹<?= e((string)($item['offer_price'] ?: $item['price'] ?: '0')) ?></td>
                        <td><span class="badge badge--<?= ($item['stock_status'] ?? 'in_stock') === 'in_stock' ? 'success' : 'default' ?>"><?= e(ucwords(str_replace('_', ' ', $item['stock_status'] ?? 'in_stock'))) ?></span></td>
                        <td>
                            <div style="display:flex; gap:var(--space-xs); align-items:center;">
                                <?php if(!empty($item['slug'])): ?>
                                    <a href="/product/<?= e($item['slug']) ?>" target="_blank" class="btn btn-sm" style="padding:0.4rem 0.7rem; font-size:0.75rem; gap:0.25rem;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                        View
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-ghost edit-item" data-item='<?= e(json_encode(array_merge($item, ['__id' => $item['id'] ?? '']), JSON_HEX_APOS)) ?>'>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    Edit
                                </button>
                                <form method="post" action="/admin/<?= e($collection) ?>/delete" onsubmit="return confirm('Delete this product? This cannot be undone.');">
                                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                    <button class="btn btn-sm" style="background:var(--color-error-light); color:var(--color-error);">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                                    </button>
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
let galleryImages = [];
let dragIndex = null;

function parseImages(value) {
    if (Array.isArray(value)) return value.filter(Boolean);
    if (typeof value === 'string') return value.split(/\n+/).map(s => s.trim()).filter(Boolean);
    return [];
}

function renderGallery() {
    const grid = document.getElementById('gallery-grid');
    const count = document.getElementById('gallery-count');
    grid.innerHTML = '';
    galleryImages.forEach((src, i) => {
        const div = document.createElement('div');
        div.draggable = true;
        div.dataset.index = i;
        div.style.cssText = 'position:relative; aspect-ratio:1; border-radius:var(--radius-sm); overflow:hidden; border:2px solid var(--color-border); background:var(--color-bg-alt); cursor:grab; transition:opacity 0.15s, border-color 0.15s;';
        div.innerHTML = '<img src="' + String(src).replaceAll('"', '&quot;') + '" alt="" style="width:100%;height:100%;object-fit:cover;display:block;pointer-events:none;">'
            + '<div style="position:absolute;top:2px;left:2px;font-size:10px;color:rgba(255,255,255,0.6);background:rgba(0,0,0,0.4);border-radius:3px;padding:1px 5px;pointer-events:none;">' + (i + 1) + '</div>'
            + '<div style="position:absolute;top:2px;right:2px;display:flex;gap:2px;">'
            + '<button type="button" class="gallery-feature-btn" title="Set as featured" style="width:22px;height:22px;border:0;border-radius:4px;background:rgba(0,0,0,0.55);color:#fff;cursor:pointer;font-size:12px;line-height:1;display:flex;align-items:center;justify-content:center;">★</button>'
            + '<button type="button" class="gallery-remove-btn" title="Remove" style="width:22px;height:22px;border:0;border-radius:4px;background:rgba(214,64,69,0.85);color:#fff;cursor:pointer;font-size:12px;line-height:1;display:flex;align-items:center;justify-content:center;">✕</button>'
            + '</div>';
        div.addEventListener('dragstart', (e) => { dragIndex = i; e.dataTransfer.effectAllowed = 'move'; div.style.opacity = '0.4'; });
        div.addEventListener('dragenter', (e) => { e.preventDefault(); div.style.borderColor = 'var(--color-gold)'; });
        div.addEventListener('dragover', (e) => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; });
        div.addEventListener('dragleave', () => { div.style.borderColor = ''; });
        div.addEventListener('drop', (e) => { e.preventDefault(); div.style.borderColor = ''; if (dragIndex !== null && dragIndex !== i) { const [moved] = galleryImages.splice(dragIndex, 1); galleryImages.splice(i, 0, moved); renderGallery(); if (dragIndex === 0 || i === 0) updateFeatured(); } dragIndex = null; });
        div.addEventListener('dragend', () => { dragIndex = null; div.style.opacity = ''; });
        div.querySelector('.gallery-feature-btn').addEventListener('click', (e) => { e.stopPropagation(); setFeatured(i); });
        div.querySelector('.gallery-remove-btn').addEventListener('click', (e) => { e.stopPropagation(); removeImage(i); });
        grid.appendChild(div);
    });
    count.textContent = '(' + galleryImages.length + ')';
    document.getElementById('field-image_urls').value = galleryImages.join('\n');
}

function setFeatured(index) {
    if (index < 0 || index >= galleryImages.length) return;
    const src = galleryImages[index];
    galleryImages.splice(index, 1);
    galleryImages.unshift(src);
    renderGallery();
    updateFeatured();
}

function removeImage(index) {
    galleryImages.splice(index, 1);
    renderGallery();
    if (galleryImages.length > 0) updateFeatured();
    else { document.getElementById('field-image_url').value = ''; document.getElementById('featured-img').style.display = 'none'; document.getElementById('featured-placeholder').style.display = 'inline'; }
}

function updateFeatured() {
    if (galleryImages.length === 0) return;
    const src = galleryImages[0];
    document.getElementById('field-image_url').value = src;
    const img = document.getElementById('featured-img');
    img.src = src; img.style.display = 'block';
    document.getElementById('featured-placeholder').style.display = 'none';
}

function addImages(paths) {
    paths.forEach(p => { if (p && !galleryImages.includes(p)) galleryImages.push(p); });
    renderGallery();
    if (galleryImages.length > 0 && !document.getElementById('field-image_url').value) updateFeatured();
}

function selectCategoryChip(el) {
    const slug = el.dataset.slug || el.textContent.trim().toLowerCase().replace(/\s+/g, '-');
    document.getElementById('field-category').value = slug;
    document.querySelectorAll('#category-chips .cat-chip').forEach(c => { c.style.borderColor = 'var(--color-border)'; c.style.background = 'var(--color-bg-alt)'; c.style.color = ''; });
    el.style.borderColor = 'var(--color-gold)';
    el.style.background = 'var(--color-gold)';
    el.style.color = '#fff';
}
document.querySelectorAll('#category-chips .cat-chip').forEach(chip => {
    chip.addEventListener('click', () => selectCategoryChip(chip));
});

function clearCategoryChips() {
    document.getElementById('field-category').value = '';
    document.querySelectorAll('#category-chips .cat-chip').forEach(c => { c.style.borderColor = 'var(--color-border)'; c.style.background = 'var(--color-bg-alt)'; c.style.color = ''; });
}

function setCategoryChips(val) {
    clearCategoryChips();
    if (!val) return;
    document.getElementById('field-category').value = val;
    document.querySelectorAll('#category-chips .cat-chip').forEach(c => {
        if ((c.dataset.slug || '').toLowerCase() === val.toLowerCase() || c.textContent.trim().toLowerCase() === val.toLowerCase()) {
            c.style.borderColor = 'var(--color-gold)';
            c.style.background = 'var(--color-gold)';
            c.style.color = '#fff';
        }
    });
}

function resetForm() {
    document.getElementById('resource-id').value = '';
    document.getElementById('save-btn').textContent = 'Save Product';
    galleryImages = []; renderGallery();
    document.getElementById('field-image_url').value = '';
    document.getElementById('featured-img').style.display = 'none';
    document.getElementById('featured-placeholder').style.display = 'inline';
    clearCategoryChips();
    document.getElementById('field-hsn_code').value = '';
    document.getElementById('field-gst_rate').value = '';
    document.querySelectorAll('#product-form input, #product-form select, #product-form textarea').forEach(el => {
        if (el.type !== 'hidden' && el.id !== 'field-image_urls' && el.name !== 'media_files[]') {
            if (el.type === 'select-one') el.selectedIndex = 0; else el.value = '';
        }
    });
}

document.querySelectorAll('.edit-item').forEach(button => {
    button.addEventListener('click', () => {
        const item = JSON.parse(button.dataset.item || '{}');
        document.getElementById('resource-id').value = item.__id || '';
        document.getElementById('save-btn').textContent = 'Update Product';
        document.getElementById('field-slug').value = item.slug || '';
        document.getElementById('field-name').value = item.name || '';
        setCategoryChips(item.category || '');
        document.getElementById('field-price').value = item.price || '';
        document.getElementById('field-offer_price').value = item.offer_price || '';
        document.getElementById('field-stock_status').value = item.stock_status || 'in_stock';
        document.getElementById('field-hsn_code').value = item.hsn_code || '';
        document.getElementById('field-gst_rate').value = item.gst_rate || '';
        document.getElementById('field-description').value = item.description || '';
        galleryImages = parseImages(item.image_urls || item.image_url || []);
        renderGallery();
        if (galleryImages.length > 0) updateFeatured();
        else { document.getElementById('field-image_url').value = ''; document.getElementById('featured-img').style.display = 'none'; document.getElementById('featured-placeholder').style.display = 'inline'; }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});

document.getElementById('open-media-library').addEventListener('click', () => {
    const picker = document.querySelector('.admin-media-picker');
    if (picker) picker.style.display = picker.style.display === 'none' ? '' : 'none';
});
document.querySelectorAll('.use-media').forEach(btn => {
    btn.addEventListener('click', () => { addImages([btn.dataset.path]); });
});
document.getElementById('gallery-file-input').addEventListener('change', () => {
    const preview = document.getElementById('gallery-grid');
    [...document.getElementById('gallery-file-input').files].forEach(file => {
        const url = URL.createObjectURL(file);
        const div = document.createElement('div');
        div.style.cssText = 'position:relative; aspect-ratio:1; border-radius:var(--radius-sm); overflow:hidden; border:2px solid var(--color-gold); opacity:0.6;';
        div.innerHTML = '<img src="' + url + '" alt="' + file.name + '" style="width:100%;height:100%;object-fit:cover;display:block;">'
            + '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.3);color:#fff;font-size:0.65rem;font-weight:600;text-transform:uppercase;">New</div>';
        preview.appendChild(div);
    });
});
document.getElementById('product-form').addEventListener('reset', resetForm);
</script>
