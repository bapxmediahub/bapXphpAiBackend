<?php if (isset($imageHealth)): ?>
<div class="admin-card">
    <h2>Product image checks</h2>
    <?php if (!$imageHealth): ?>
        <p>All local product image files are present and readable.</p>
    <?php else: ?>
        <p><?= count($imageHealth) ?> product image reference<?= count($imageHealth) === 1 ? '' : 's' ?> need attention. These checks inspect the files on this server and look for matching originals in public images and storage.</p>
        <div class="table-wrap"><table><thead><tr><th>Product</th><th>Saved image</th><th>File check</th><th>Original found</th></tr></thead><tbody>
        <?php foreach ($imageHealth as $check): ?>
            <tr><td><?= e($check['product']) ?></td><td><code><?= e($check['url']) ?></code></td><td><?= e($check['status']) ?></td><td>
                <?php foreach ($check['originals'] as $original): ?><a href="<?= e($original) ?>" target="_blank" rel="noopener"><?= e(basename($original)) ?></a><br><?php endforeach; ?>
                <?php foreach ($check['storage_matches'] as $original): ?><code><?= e($original) ?></code><br><?php endforeach; ?>
                <?php if (!$check['originals'] && !$check['storage_matches']): ?>No matching original in images or storage<?php endif; ?>
            </td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</div>
<?php endif; ?>
<div class="admin-card">
    <h2>Upload Media</h2>
    <form method="post" action="/admin/media/upload" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="admin-form__row">
            <label>Context
                <select name="context">
                    <option value="shared">Shared</option>
                    <option value="products">Products</option>
                    <option value="temples">Temples</option>
                    <option value="astrologers">Astrologers</option>
                </select>
            </label>
            <label>Description
                <input type="text" name="description" placeholder="e.g. Product hero image" maxlength="255">
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
                    <img src="<?= e($media['url'] ?? $media['path']) ?>" alt="<?= e($media['original_name'] ?? $media['filename'] ?? 'Media') ?>">
                    <strong><?= e($media['original_name'] ?? $media['filename'] ?? 'Media') ?></strong>
                    <?php if(!empty($media['description'])): ?>
                        <small class="media-desc"><?= e($media['description']) ?></small>
                    <?php endif; ?>
                    <span class="badge badge--info"><?= e($media['context'] ?? 'shared') ?></span>
                    <span><?= e(substr((string)($media['created_at'] ?? ''), 0, 10)) ?></span>
                    <code><?= e($media['url'] ?? $media['path']) ?></code>
                    <?php if(!empty($media['used_in'])): ?>
                        <details style="font-size:0.85rem;margin-top:4px;">
                            <summary style="cursor:pointer;color:var(--c-accent);">Used in <?= count($media['used_in']) ?> place(s)</summary>
                            <ul style="margin:4px 0 0 12px;padding:0;">
                                <?php foreach($media['used_in'] as $u): ?>
                                    <li><a href="/admin/<?= e($u['type'] ?? '') ?>" style="text-decoration:none;"><?= e($u['name'] ?? $u['id'] ?? '') ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
