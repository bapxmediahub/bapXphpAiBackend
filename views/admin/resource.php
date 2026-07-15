<div class="admin-card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:var(--space-sm); margin-bottom:var(--space-lg);">
        <h2 style="margin:0; font-size:1.1rem;"><?= e($title) ?></h2>
        <span class="badge badge--default"><?= count($items) ?> record<?= count($items) !== 1 ? 's' : '' ?></span>
    </div>
    <form id="resource-form" method="post" action="/admin/<?= e($collection) ?>/save" class="admin-form" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="id" id="resource-id">
        <div class="admin-form__row" style="grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));">
            <?php foreach($fields as $field): ?>
                <label><?= e(ucwords(str_replace('_',' ',$field))) ?>
                    <?php if($field === 'description' || $field === 'image_urls'): ?>
                        <textarea name="<?= e($field) ?>" id="field-<?= e($field) ?>" rows="3"></textarea>
                    <?php elseif($field === 'active'): ?>
                        <label style="flex-direction:row; align-items:center; gap:var(--space-xs); text-transform:none; font-weight:400;">
                            <input type="checkbox" name="<?= e($field) ?>" id="field-<?= e($field) ?>" value="1" checked> Active
                        </label>
                    <?php elseif(str_contains($field, '_url') && !in_array($field, ['image_url', 'photo_url'], true)): ?>
                        <input type="url" name="<?= e($field) ?>" id="field-<?= e($field) ?>" placeholder="https://...">
                    <?php elseif(str_contains($field, 'price') || str_contains($field, 'amount') || str_contains($field, 'value') || str_contains($field, '_prm') || str_contains($field, '_percentage') || $field === 'slot_minutes' || $field === 'experience_years'): ?>
                        <input type="number" name="<?= e($field) ?>" id="field-<?= e($field) ?>" placeholder="0" step="any">
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
        <?php if(in_array($collection, ['products','temples','astrologers'], true)): ?>
            <div class="admin-upload-panel">
                <label>Upload Media Files
                    <input type="file" name="media_files[]" id="field-media-files" accept="image/png,image/jpeg,image/webp,image/gif" multiple>
                </label>
                <p>Uploaded files are saved into the media library by upload time. Product uploads are added to the gallery; temple and astrologer uploads fill the main image when empty.</p>
                <div class="admin-image-preview" id="product-image-preview"></div>
            </div>
            <?php if(!empty($mediaFiles)): ?>
                <div class="admin-media-picker">
                    <div class="admin-media-picker__head">
                        <strong>Media Library</strong>
                        <span>Newest first</span>
                    </div>
                    <div class="admin-media-grid">
                        <?php foreach($mediaFiles as $media): ?>
                            <div class="admin-media-tile">
                                <img src="<?= e($media['url'] ?? $media['path']) ?>" alt="<?= e($media['original_name'] ?? $media['filename'] ?? 'Media') ?>">
                                <small><?= e(substr((string)($media['created_at'] ?? ''), 0, 10)) ?></small>
                                <?php if($collection === 'products'): ?>
                                    <button type="button" class="btn btn-sm btn-ghost use-media" data-field="image_url" data-path="<?= e($media['url'] ?? $media['path']) ?>">Primary</button>
                                    <button type="button" class="btn btn-sm btn-ghost use-media" data-field="image_urls" data-append="1" data-path="<?= e($media['url'] ?? $media['path']) ?>">Gallery</button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-ghost use-media" data-field="<?= $collection === 'astrologers' ? 'photo_url' : 'image_url' ?>" data-path="<?= e($media['url'] ?? $media['path']) ?>">Use</button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <div style="margin-top:var(--space-md); display:flex; gap:var(--space-sm);">
            <button type="submit" class="btn btn-primary" id="save-btn">Save <?= e(rtrim($title,'s')) ?></button>
            <button type="reset" class="btn btn-ghost" onclick="document.getElementById('resource-id').value=''; document.getElementById('save-btn').textContent='Save <?= e(rtrim($title,'s')) ?>';">Clear</button>
        </div>
    </form>
</div>

<div class="admin-card">
    <div class="table-wrap">
        <table>
            <thead><tr><?php foreach($fields as $field): ?><th><?= e(ucwords(str_replace('_',' ',$field))) ?></th><?php endforeach; ?><th>Actions</th></tr></thead>
            <tbody>
            <?php if(empty($items)): ?>
                <tr><td colspan="<?= count($fields) + 1 ?>" class="text-center" style="color:var(--color-text-muted); padding:var(--space-2xl);">No records yet. Add one using the form above.</td></tr>
            <?php else: ?>
                <?php foreach($items as $item): ?>
                    <tr>
                        <?php foreach($fields as $field): ?>
                            <td style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                <?php if($field === 'image_urls' && !empty($item[$field])): ?>
                                    <?php $imageCount = is_array($item[$field]) ? count($item[$field]) : count(array_filter(preg_split('/[\r\n,]+/', (string)$item[$field]) ?: [])); ?>
                                    <span class="badge badge--info"><?= e((string)$imageCount) ?> image<?= $imageCount === 1 ? '' : 's' ?></span>
                                <?php elseif(str_contains($field, '_url') && !empty($item[$field])): ?>
                                    <a href="<?= e($item[$field]) ?>" target="_blank" style="font-size:0.8rem;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                        View
                                    </a>
                                <?php elseif($field === 'active'): ?>
                                    <span class="badge badge--<?= !empty($item[$field]) ? 'success' : 'default' ?>"><?= !empty($item[$field]) ? 'Yes' : 'No' ?></span>
                                <?php else: ?>
                                    <?= e(is_array($item[$field]??null)?implode(', ',$item[$field]):(string)($item[$field]??'')) ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td>
                            <div style="display:flex; gap:var(--space-xs);">
                                <button type="button" class="btn btn-sm btn-ghost edit-item" data-item='<?= e(json_encode(array_merge($item, ['__id' => $item['id'] ?? '']), JSON_HEX_APOS)) ?>'>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    Edit
                                </button>
                                <form method="post" action="/admin/<?= e($collection) ?>/delete" onsubmit="return confirm('Delete this record? This cannot be undone.');">
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
document.querySelectorAll('.edit-item').forEach(button => {
    button.addEventListener('click', () => {
        const item = JSON.parse(button.dataset.item || '{}');
        document.getElementById('resource-id').value = item.__id || '';
        document.getElementById('save-btn').textContent = 'Update <?= e(rtrim($title,'s')) ?>';
        <?php foreach($fields as $field): ?>
            var el = document.getElementById('field-<?= e($field) ?>');
            if (el) {
                if (el.type === 'checkbox') {
                    el.checked = !!item['<?= e($field) ?>'];
                } else {
                    el.value = Array.isArray(item['<?= e($field) ?>']) ? item['<?= e($field) ?>'].join("\n") : (item['<?= e($field) ?>'] ?? '');
                }
            }
        <?php endforeach; ?>
        renderProductImages(item.image_urls || item.image_url || []);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});
function renderProductImages(images) {
    const preview = document.getElementById('product-image-preview');
    if (!preview) return;
    const list = Array.isArray(images) ? images : [images];
    preview.innerHTML = list.filter(Boolean).map(src => `<img src="${String(src).replaceAll('"', '&quot;')}" alt="">`).join('');
}
const productImageInput = document.getElementById('field-media-files');
if (productImageInput) {
    productImageInput.addEventListener('change', () => {
        const preview = document.getElementById('product-image-preview');
        if (!preview) return;
        preview.innerHTML = '';
        [...productImageInput.files].forEach(file => {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.alt = file.name;
            preview.appendChild(img);
        });
    });
}
document.querySelectorAll('.use-media').forEach(button => {
    button.addEventListener('click', () => {
        const field = document.getElementById('field-' + button.dataset.field);
        if (!field) return;
        if (button.dataset.append === '1') {
            const current = field.value.trim();
            field.value = current ? current + "\n" + button.dataset.path : button.dataset.path;
        } else {
            field.value = button.dataset.path || '';
        }
        renderProductImages(button.dataset.field === 'image_urls' ? field.value.split(/\n+/) : [button.dataset.path]);
    });
});
document.getElementById('resource-form').addEventListener('reset', () => {
    document.getElementById('resource-id').value = '';
    document.getElementById('save-btn').textContent = 'Save <?= e(rtrim($title,'s')) ?>';
    renderProductImages([]);
});
</script>
