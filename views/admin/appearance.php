<div class="admin-card">
    <h2 style="font-size:1.1rem; margin:0 0 var(--space-sm);">Logo & Favicon</h2>
    <p style="margin:0 0 var(--space-lg); color:var(--color-text-muted); font-size:0.9rem;">Upload your brand assets. Files are saved to <code>assets/images/brand/</code> and applied site-wide.</p>
</div>

<div class="admin-card">
    <form method="post" action="/admin/appearance/save" class="admin-form" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <h3 style="font-size:1rem; margin:0 0 var(--space-md);">Site Logo</h3>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-xl); align-items:start;">
            <div>
                <label>Upload Logo</label>
                <div style="background:var(--color-bg-alt); border:2px dashed var(--color-border); border-radius:var(--radius-md); padding:var(--space-lg); text-align:center; margin-bottom:var(--space-sm);">
                    <input type="file" name="logo_file" accept="image/png,image/jpeg,image/jpg" style="margin:0 auto var(--space-sm); display:block;">
                    <p style="margin:0; font-size:0.8rem; color:var(--color-text-muted);"><strong>Required:</strong> 512×512 px · Max 100 KB · PNG or JPEG</p>
                </div>
                <label style="flex-direction:row; align-items:center; gap:var(--space-xs); font-weight:400; font-size:0.85rem;">
                    <input type="checkbox" name="logo_remove" value="1"> Remove logo (use site name text only)
                </label>
            </div>
            <div>
                <label>Current Logo Preview</label>
                <div style="background:var(--color-ink); border-radius:var(--radius-md); padding:var(--space-lg); display:flex; align-items:center; justify-content:center; min-height:120px;">
                    <?php if(!empty($logo_url)): ?>
                        <img src="<?= e($logo_url) ?>" alt="Logo" style="max-width:120px; max-height:60px; object-fit:contain;">
                    <?php else: ?>
                        <span style="color:rgba(255,255,255,0.5); font-size:0.85rem; font-family:var(--font-serif);">Sri Panchami Spiritual</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <h3 style="font-size:1rem; margin:var(--space-xl) 0 var(--space-md);">Favicon</h3>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-xl); align-items:start;">
            <div>
                <label>Upload Favicon</label>
                <div style="background:var(--color-bg-alt); border:2px dashed var(--color-border); border-radius:var(--radius-md); padding:var(--space-lg); text-align:center; margin-bottom:var(--space-sm);">
                    <input type="file" name="favicon_file" accept="image/png,image/svg+xml,image/x-icon" style="margin:0 auto var(--space-sm); display:block;">
                    <p style="margin:0; font-size:0.8rem; color:var(--color-text-muted);"><strong>Required:</strong> 64×64 px · Max 50 KB · PNG or SVG</p>
                </div>
                <label style="flex-direction:row; align-items:center; gap:var(--space-xs); font-weight:400; font-size:0.85rem;">
                    <input type="checkbox" name="favicon_remove" value="1"> Remove favicon (use default)
                </label>
            </div>
            <div>
                <label>Current Favicon Preview</label>
                <div style="background:var(--color-bg-alt); border-radius:var(--radius-md); padding:var(--space-lg); display:flex; align-items:center; justify-content:center; min-height:80px;">
                    <?php if(!empty($favicon_url)): ?>
                        <img src="<?= e($favicon_url) ?>" alt="Favicon" style="max-width:64px; max-height:64px;">
                    <?php else: ?>
                        <span style="color:var(--color-text-muted); font-size:0.75rem;">No favicon set</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <h3 style="font-size:1rem; margin:var(--space-xl) 0 var(--space-md);">Color Palette</h3>
        <p style="margin:0 0 var(--space-md); color:var(--color-text-muted); font-size:0.85rem;">Customize brand colors site-wide. Uses the <code>--color-*</code> CSS custom property naming.</p>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-md) var(--space-xl);">
            <div>
                <label>Primary <span style="font-weight:400; font-size:0.8rem; color:var(--color-text-muted);">(maroon)</span></label>
                <div style="display:flex; gap:var(--space-sm); align-items:center;">
                    <input type="color" name="palette_primary" value="<?= e($palette_primary) ?>" style="width:44px; height:44px; border:0; padding:0; cursor:pointer; border-radius:6px; flex-shrink:0;">
                    <code style="font-size:0.85rem;"><?= e($palette_primary) ?></code>
                </div>
            </div>
            <div>
                <label>Secondary <span style="font-weight:400; font-size:0.8rem; color:var(--color-text-muted);">(gold)</span></label>
                <div style="display:flex; gap:var(--space-sm); align-items:center;">
                    <input type="color" name="palette_secondary" value="<?= e($palette_secondary) ?>" style="width:44px; height:44px; border:0; padding:0; cursor:pointer; border-radius:6px; flex-shrink:0;">
                    <code style="font-size:0.85rem;"><?= e($palette_secondary) ?></code>
                </div>
            </div>
            <div>
                <label>Canvas <span style="font-weight:400; font-size:0.8rem; color:var(--color-text-muted);">(page background)</span></label>
                <div style="display:flex; gap:var(--space-sm); align-items:center;">
                    <input type="color" name="palette_canvas" value="<?= e($palette_canvas) ?>" style="width:44px; height:44px; border:0; padding:0; cursor:pointer; border-radius:6px; flex-shrink:0;">
                    <code style="font-size:0.85rem;"><?= e($palette_canvas) ?></code>
                </div>
            </div>
            <div>
                <label>Text <span style="font-weight:400; font-size:0.8rem; color:var(--color-text-muted);">(body copy)</span></label>
                <div style="display:flex; gap:var(--space-sm); align-items:center;">
                    <input type="color" name="palette_text" value="<?= e($palette_text) ?>" style="width:44px; height:44px; border:0; padding:0; cursor:pointer; border-radius:6px; flex-shrink:0;">
                    <code style="font-size:0.85rem;"><?= e($palette_text) ?></code>
                </div>
            </div>
            <div>
                <label>Link <span style="font-weight:400; font-size:0.8rem; color:var(--color-text-muted);">(inline links)</span></label>
                <div style="display:flex; gap:var(--space-sm); align-items:center;">
                    <input type="color" name="palette_link" value="<?= e($palette_link) ?>" style="width:44px; height:44px; border:0; padding:0; cursor:pointer; border-radius:6px; flex-shrink:0;">
                    <code style="font-size:0.85rem;"><?= e($palette_link) ?></code>
                </div>
            </div>
        </div>

        <?php if(!empty($upload_error)): ?>
            <div class="flash flash--error" style="margin-top:var(--space-md);"><?= e($upload_error) ?></div>
        <?php endif; ?>
        <?php if(!empty($upload_success)): ?>
            <div class="flash flash--success" style="margin-top:var(--space-md);"><?= e($upload_success) ?></div>
        <?php endif; ?>

        <div style="display:flex; gap:var(--space-md); margin-top:var(--space-lg);">
            <button class="btn btn-primary">Save Appearance</button>
            <button type="submit" name="reset_palette" value="1" class="btn btn-ghost">Reset to Defaults</button>
        </div>
    </form>
</div>
