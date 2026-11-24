<section class="admin-card" aria-labelledby="media-delete-title">
    <?php if (isset($results)): ?>
        <?php $blockedCount = count(array_filter($results, static fn(array $upload): bool => $upload['blocked'] !== null)); ?>
        <h1 id="media-delete-title">Media deletion results</h1>
        <p role="status"><?= count($results) - $blockedCount ?> removed · <?= $blockedCount ?> kept</p>
        <ul style="overflow-wrap:anywhere">
            <?php foreach ($results as $upload): ?>
                <li><strong><?= e($upload['name']) ?></strong> · <?= e($upload['blocked'] ?? 'Deleted') ?></li>
            <?php endforeach; ?>
        </ul>
        <a class="btn btn-outline" href="/admin/media#media-library">Back to Media Library</a>
    <?php else: ?>
        <h1 id="media-delete-title">Review media deletion</h1>
        <p>Deleting uploads is permanent. The library checks current references before removing each file and keeps images that are still in use.</p>
        <ul style="overflow-wrap:anywhere">
            <?php foreach ($uploads as $upload): ?>
                <li><?= e($upload['name']) ?></li>
            <?php endforeach; ?>
        </ul>
        <form method="post" action="/admin/media/delete-selection" class="admin-form">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="review_token" value="<?= e($token) ?>">
            <div class="admin-form__row">
                <button class="btn btn-outline" name="step" value="cancel" type="submit">Cancel</button>
                <button class="btn admin-media-delete" name="step" value="confirm" type="submit">Permanently delete <?= count($uploads) === 1 ? '1 upload' : count($uploads) . ' uploads' ?></button>
            </div>
        </form>
    <?php endif; ?>
</section>
