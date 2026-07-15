<div class="admin-card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:var(--space-sm); margin-bottom:var(--space-lg);">
        <h2 style="margin:0; font-size:1.1rem;"><?= e($title) ?></h2>
        <span class="badge badge--default"><?= count($items) ?> record<?= count($items) !== 1 ? 's' : '' ?></span>
    </div>
    <form id="astrologer-form" method="post" action="/admin/<?= e($collection) ?>/save" class="admin-form" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="id" id="resource-id">
        <input type="hidden" name="photo_url" id="field-photo_url">
        <textarea name="photo_urls" id="field-photo_urls" style="display:none"></textarea>
        <div style="display:grid; grid-template-columns: 1fr 340px; gap: var(--space-xl); align-items:start;">
            <div style="display:grid; gap:var(--space-sm);">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-sm);">
                    <label>Name
                        <input type="text" name="name" id="field-name" placeholder="Full name" required>
                    </label>
                    <label>Slug
                        <input type="text" name="slug" id="field-slug" placeholder="astrologer-slug">
                    </label>
                </div>
                <label>Contact email (admin reference only)
                    <input type="email" name="email" id="field-email" placeholder="email@example.com">
                </label>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:var(--space-sm);">
                    <label>Experience (years)
                        <input type="number" name="experience_years" id="field-experience_years" placeholder="0" step="any">
                    </label>
                    <label>Slot Minutes
                        <input type="number" name="slot_minutes" id="field-slot_minutes" placeholder="30" step="any">
                    </label>
                    <label>Start Time
                        <input type="time" name="start_time" id="field-start_time" value="09:00">
                    </label>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-sm);">
                    <label>End Time
                        <input type="time" name="end_time" id="field-end_time" value="21:00">
                    </label>
                    <label>Speciality
                        <input type="text" name="speciality" id="field-speciality" placeholder="e.g. Vedic Astrology">
                    </label>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-sm);">
                    <label>Working Days
                        <input type="text" name="working_days" id="field-working_days" placeholder="Monday, Tuesday, ...">
                    </label>
                    <label>Modes
                        <input type="text" name="modes" id="field-modes" placeholder="message, call">
                    </label>
                </div>
                <label>Languages
                    <input type="text" name="languages" id="field-languages" placeholder="English, Hindi, ...">
                </label>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-sm);">
                    <label>Availability Status
                        <select name="availability_status" id="field-availability_status">
                            <option value="available">Available</option>
                            <option value="busy">Busy</option>
                            <option value="offline">Offline</option>
                            <option value="waitlist">Waitlist</option>
                        </select>
                    </label>
                    <label></label>
                </div>
                <label>Description
                    <textarea name="description" id="field-description" rows="4"></textarea>
                </label>
            </div>
            <div style="display:grid; gap:var(--space-lg);">
                <div>
                    <label style="display:block; margin-bottom:var(--space-xs); font-weight:600; font-size:0.85rem; text-transform:uppercase; letter-spacing:0.05em;">Profile Photo</label>
                    <div id="featured-preview" style="aspect-ratio:3/4; max-height:280px; background:var(--color-bg-alt); border:2px dashed var(--color-border); border-radius:var(--radius-md); display:flex; align-items:center; justify-content:center; overflow:hidden; margin-bottom:var(--space-xs); position:relative;">
                        <span style="color:var(--color-text-muted); font-size:0.8rem;" id="featured-placeholder">No photo</span>
                        <img id="featured-img" src="" alt="" style="display:none; width:100%; height:100%; object-fit:cover; position:absolute; inset:0;">
                    </div>
                    <button type="button" class="btn btn-sm btn-ghost" onclick="document.getElementById('gallery-file-input').click()" style="width:100%;">Upload Photo</button>
                </div>
                <div>
                    <label style="display:block; margin-bottom:var(--space-xs); font-weight:600; font-size:0.85rem; text-transform:uppercase; letter-spacing:0.05em;">Photo Gallery <span id="gallery-count" style="font-weight:400; color:var(--color-text-muted); text-transform:none;">(0)</span></label>
                    <div id="gallery-grid" style="display:grid; grid-template-columns:repeat(3, 1fr); gap:var(--space-xs); margin-bottom:var(--space-xs); min-height:80px;"></div>
                    <div style="display:flex; gap:var(--space-xs);">
                        <button type="button" class="btn btn-sm btn-ghost" onclick="document.getElementById('gallery-file-input').click()" style="flex:1;">+ Add Photos</button>
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
            <button type="submit" class="btn btn-primary" id="save-btn">Save Astrologer</button>
            <button type="reset" class="btn btn-ghost" onclick="resetForm()">Clear</button>
        </div>
    </form>
</div>

<div class="admin-card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Photo</th><th>Name</th><th>Speciality</th><th>Languages</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if(empty($items)): ?>
                <tr><td colspan="6" style="text-align:center; color:var(--color-text-muted); padding:var(--space-2xl);">No consultants yet. Add one using the form above.</td></tr>
            <?php else: ?>
                <?php foreach($items as $item): ?>
                    <tr>
                        <td>
                            <?php if(!empty($item['photo_url'])): ?>
                                <img src="<?= e($item['photo_url']) ?>" alt="" style="width:48px; height:48px; object-fit:cover; border-radius:50%;">
                            <?php else: ?>
                                <span style="color:var(--color-text-muted); font-size:0.75rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight:600;"><?= e($item['name'] ?? '') ?></td>
                        <td><?= e($item['speciality'] ?? '—') ?></td>
                        <td><?= e(is_array($item['languages'] ?? null) ? implode(', ', $item['languages']) : ($item['languages'] ?? '—')) ?></td>
                        <td><span class="badge badge--<?= ($item['availability_status'] ?? 'available') === 'available' ? 'success' : 'default' ?>"><?= e(ucfirst($item['availability_status'] ?? 'available')) ?></span></td>
                        <td>
                            <div style="display:flex; gap:var(--space-xs); align-items:center;">
                                <?php if(!empty($item['slug'])): ?>
                                    <a href="/consult/<?= e($item['slug']) ?>" target="_blank" class="btn btn-sm" style="padding:0.4rem 0.7rem; font-size:0.75rem; gap:0.25rem;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                        View
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-ghost edit-item" data-item='<?= e(json_encode(array_merge($item, ['__id' => $item['id'] ?? '']), JSON_HEX_APOS)) ?>'>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    Edit
                                </button>
                                <form method="post" action="/admin/<?= e($collection) ?>/delete" onsubmit="return confirm('Delete this astrologer? This cannot be undone.');">
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
            + '<button type="button" class="gallery-feature-btn" title="Set as profile photo" style="width:22px;height:22px;border:0;border-radius:4px;background:rgba(0,0,0,0.55);color:#fff;cursor:pointer;font-size:12px;line-height:1;display:flex;align-items:center;justify-content:center;">★</button>'
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
    document.getElementById('field-photo_urls').value = galleryImages.join('\n');
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
    else { document.getElementById('field-photo_url').value = ''; document.getElementById('featured-img').style.display = 'none'; document.getElementById('featured-placeholder').style.display = 'inline'; }
}

function updateFeatured() {
    if (galleryImages.length === 0) return;
    const src = galleryImages[0];
    document.getElementById('field-photo_url').value = src;
    const img = document.getElementById('featured-img');
    img.src = src; img.style.display = 'block';
    document.getElementById('featured-placeholder').style.display = 'none';
}

function addImages(paths) {
    paths.forEach(p => { if (p && !galleryImages.includes(p)) galleryImages.push(p); });
    renderGallery();
    if (galleryImages.length > 0 && !document.getElementById('field-photo_url').value) updateFeatured();
}

function resetForm() {
    document.getElementById('resource-id').value = '';
    document.getElementById('save-btn').textContent = 'Save Astrologer';
    galleryImages = []; renderGallery();
    document.getElementById('field-photo_url').value = '';
    document.getElementById('featured-img').style.display = 'none';
    document.getElementById('featured-placeholder').style.display = 'inline';
    document.querySelectorAll('#astrologer-form input, #astrologer-form select, #astrologer-form textarea').forEach(el => {
        if (el.type !== 'hidden' && el.id !== 'field-photo_urls' && el.name !== 'media_files[]') {
            if (el.type === 'select-one') el.selectedIndex = 0; else el.value = '';
        }
    });
}

document.querySelectorAll('.edit-item').forEach(button => {
    button.addEventListener('click', () => {
        const item = JSON.parse(button.dataset.item || '{}');
        document.getElementById('resource-id').value = item.__id || '';
        document.getElementById('save-btn').textContent = 'Update Astrologer';
        document.getElementById('field-name').value = item.name || '';
        document.getElementById('field-slug').value = item.slug || '';
        document.getElementById('field-email').value = item.email || '';
        document.getElementById('field-experience_years').value = item.experience_years || '';
        document.getElementById('field-slot_minutes').value = item.slot_minutes || '';
        document.getElementById('field-start_time').value = item.start_time || '';
        document.getElementById('field-end_time').value = item.end_time || '';
        document.getElementById('field-speciality').value = item.speciality || '';
        document.getElementById('field-availability_status').value = item.availability_status || 'available';
        document.getElementById('field-working_days').value = Array.isArray(item.working_days) ? item.working_days.join(', ') : (item.working_days || '');
        document.getElementById('field-modes').value = Array.isArray(item.modes) ? item.modes.join(', ') : (item.modes || '');
        document.getElementById('field-languages').value = Array.isArray(item.languages) ? item.languages.join(', ') : (item.languages || '');
        document.getElementById('field-description').value = item.description || '';
        galleryImages = parseImages(item.photo_urls || item.photo_url || []);
        renderGallery();
        if (galleryImages.length > 0) updateFeatured();
        else {
            document.getElementById('field-photo_url').value = '';
            document.getElementById('featured-img').style.display = 'none';
            document.getElementById('featured-placeholder').style.display = 'inline';
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});

document.getElementById('open-media-library').addEventListener('click', () => {
    const picker = document.querySelector('.admin-media-picker');
    if (picker) picker.style.display = picker.style.display === 'none' ? '' : 'none';
});
document.querySelectorAll('.use-media').forEach(btn => {
    btn.addEventListener('click', () => {
        addImages([btn.dataset.path]);
    });
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

document.getElementById('astrologer-form').addEventListener('reset', resetForm);
</script>
