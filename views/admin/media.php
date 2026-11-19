<?php if (isset($imageHealth)): ?>
<div class="admin-card">
    <h2>Product image checks</h2>
    <p><?= !empty($uploadStorageReady) ? 'Upload storage is ready outside the deployment folder.' : 'Upload storage needs attention: the persistent upload folder is not writable.' ?></p>
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
        <span><span id="media-result-count" role="status"><?= count($items) ?> file<?= count($items) === 1 ? '' : 's' ?></span> · newest first</span>
    </div>
    <?php if(empty($items)): ?>
        <p>No media files yet.</p>
    <?php else: ?>
        <?php
        $contexts = array_unique(array_map(static fn(array $item): string => (string)($item['context'] ?? 'shared'), $items));
        sort($contexts, SORT_NATURAL | SORT_FLAG_CASE);
        ?>
        <div id="media-filters" hidden>
            <div class="admin-form admin-form__row" style="margin-bottom:var(--space-md);">
                <label for="media-search">Find an image
                    <input type="search" id="media-search" placeholder="Search filenames or descriptions" aria-controls="media-library-grid" autocomplete="off">
                </label>
                <div class="form-group">
                    <label for="media-context-filter">Context</label>
                    <select id="media-context-filter" aria-controls="media-library-grid">
                        <option value="">All contexts</option>
                        <?php foreach ($contexts as $context): ?>
                            <option value="<?= e($context) ?>"><?= e(ucfirst($context)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <p id="media-no-results" hidden>No images match these filters.</p>
        <div class="admin-media-grid" id="media-library-grid">
            <?php foreach($items as $media): ?>
                <?php $context = (string)($media['context'] ?? 'shared'); ?>
                <div class="admin-media-tile" data-media-context="<?= e($context) ?>">
                    <img src="<?= e($media['url'] ?? $media['path']) ?>" alt="<?= e($media['original_name'] ?? $media['filename'] ?? 'Media') ?>" loading="lazy" decoding="async">
                    <strong><?= e($media['original_name'] ?? $media['filename'] ?? 'Media') ?></strong>
                    <?php if(!empty($media['description'])): ?>
                        <small class="media-desc"><?= e($media['description']) ?></small>
                    <?php endif; ?>
                    <span class="badge badge--info"><?= e($context) ?></span>
                    <span><?= e(substr((string)($media['created_at'] ?? ''), 0, 10)) ?></span>
                    <code><?= e($media['url'] ?? $media['path']) ?></code>
                    <?php if (\App\Services\MediaService::canDeleteRecord($media)): ?>
                        <?php if (!empty($media['used_in'])): ?>
                            <small>Remove existing content references before deleting this upload.</small>
                        <?php else: ?>
                            <form method="post" action="/admin/media/delete"
                                  onsubmit="return confirm('Delete this uploaded image permanently? This cannot be undone.');">
                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="id" value="<?= e($media['id']) ?>">
                                <button class="btn btn-sm admin-media-delete" type="submit">Delete upload</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
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
        <style>#media-library-grid > [hidden] { display: none; }</style>
        <script>
        (() => {
            const search = document.getElementById('media-search');
            const context = document.getElementById('media-context-filter');
            const resultCount = document.getElementById('media-result-count');
            const noResults = document.getElementById('media-no-results');
            const items = Array.from(document.querySelectorAll('#media-library-grid > [data-media-context]'), tile => ({
                tile,
                searchText: Array.from(tile.querySelectorAll('strong, .media-desc, code'), field => field.textContent.toLocaleLowerCase())
            }));

            function filterMedia() {
                const query = search.value.trim().toLocaleLowerCase();
                let visible = 0;
                items.forEach(({tile, searchText}) => {
                    const matchesContext = !context.value || tile.dataset.mediaContext === context.value;
                    const matchesQuery = searchText.some(text => text.includes(query));
                    tile.hidden = !matchesContext || !matchesQuery;
                    if (!tile.hidden) visible++;
                });
                const count = query || context.value ? `${visible} of ${items.length}` : items.length;
                const label = `${count} ${items.length === 1 ? 'file' : 'files'}`;
                if (resultCount.textContent !== label) resultCount.textContent = label;
                noResults.hidden = visible > 0;
            }

            search.addEventListener('input', filterMedia);
            context.addEventListener('change', filterMedia);
            document.getElementById('media-filters').hidden = false;
            filterMedia();
        })();
        </script>
    <?php endif; ?>
</div>
