<?php $url = '/admin/blog/history/'.rawurlencode($current['slug']); ?>
<section class="admin-card" aria-labelledby="history-title">
    <p><a href="/admin/blog">Back to blog posts</a></p>
    <h2 id="history-title">Revision history for <?= e((string)($current['title'] ?? $current['slug'])) ?></h2>
    <p>Current publication state: <?= e($current['_publication_state']) ?>.</p>
    <?php if (!$versions): ?>
        <p>No earlier versions have been saved.</p>
    <?php else: ?>
        <p>Choose a previous version to inspect its content and publication settings. Times are UTC.</p>
        <ul>
            <?php foreach ($versions as $version): ?>
                <li><a href="<?= e($url.'?revision='.$version['id'].'#version') ?>"<?= $version['id'] === $selected ? ' aria-current="true"' : '' ?>><?= e($version['archived_at']) ?> UTC</a></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php if ($preview !== null): ?>
<section id="version" class="admin-card" aria-labelledby="version-title">
    <h2 id="version-title">Archived version of <?= e((string)($preview['title'] ?? $current['slug'])) ?></h2>
    <p>Restoring replaces the current article, including its publication settings. Its resulting state would be <strong><?= e($preview['_publication_state']) ?></strong>. Subscriber email is sent only through a new article save.</p>
    <pre style="white-space:pre-wrap;overflow-wrap:anywhere"><?= e($preview['_markdown']) ?></pre>
    <form method="post" action="<?= e($url) ?>/restore">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="expected_revision" value="<?= e($current['_revision']) ?>">
        <input type="hidden" name="revision" value="<?= e($selected) ?>">
        <button class="btn btn-primary" type="submit">Restore this version</button>
    </form>
</section>
<?php endif; ?>
