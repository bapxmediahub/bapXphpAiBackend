<div class="admin-card">
    <h2>Upload Media</h2>
    <form method="post" action="/admin/media/upload" enctype="multipart/form-data" class="admin-form">
        <div class="admin-form__row">
            <label>Context
                <select name="context">
                    <option value="shared">Shared</option>
                    <option value="products">Products</option>
                    <option value="temples">Temples</option>
                    <option value="astrologers">Astrologers</option>
                </select>
            </label>
            <label>Files
                <input type="file" name="media_files[]" accept="image/png,image/jpeg,image/webp,image/gif" multiple required>
            </label>
        </div>
        <button class="btn btn-primary">Upload</button>
    </form>
</div>

<div class="admin-card">
    <div class="admin-media-picker__head">
        <h2 style="margin:0;">Media Library</h2>
        <span><?= count($items) ?> file<?= count($items) === 1 ? '' : 's' ?> · newest first</span>
    </div>
    <?php if(empty($items)): ?>
        <p>No media files yet.</p>
    <?php else: ?>
        <div class="admin-media-grid">
            <?php foreach($items as $media): ?>
                <div class="admin-media-tile">
                    <img src="<?= e($media['path']) ?>" alt="<?= e($media['original_name'] ?? $media['filename'] ?? 'Media') ?>">
                    <strong><?= e($media['original_name'] ?? $media['filename'] ?? 'Media') ?></strong>
                    <small><?= e($media['context'] ?? 'shared') ?> · <?= e(substr((string)($media['created_at'] ?? ''), 0, 10)) ?></small>
                    <code><?= e($media['path']) ?></code>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
