<?php $editing = ($form['original_slug'] ?? '') !== ''; ?>
<section class="admin-card" aria-labelledby="pages-title">
    <h1 id="pages-title">Pages</h1>
    <?php if ($pages): ?>
        <ul><?php foreach ($pages as $item): ?>
            <li><a href="/admin/pages?edit=<?= e(rawurlencode($item['slug'])) ?>"><?= e($item['title']) ?></a> · <?= e(ucfirst($item['status'])) ?></li>
        <?php endforeach; ?></ul>
    <?php else: ?><p>No authored pages yet.</p><?php endif; ?>
    <?php if ($editing): ?><a class="btn btn-outline" href="/admin/pages#page-editor">New page</a><?php endif; ?>
</section>
<section id="page-editor" class="admin-card" aria-labelledby="page-editor-title">
    <h2 id="page-editor-title"><?= $editing ? 'Edit page' : 'Create a page' ?></h2>
    <?php if (!empty($error)): ?><p role="alert"><?= e($error) ?></p>
        <?php if (!empty($form['slug'])): ?><p><a href="/admin/pages?edit=<?= e(rawurlencode($form['slug'])) ?>">Reopen the saved page</a></p><?php endif; ?>
    <?php endif; ?>
    <form class="admin-form" action="/admin/pages/save" method="post">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="original_slug" value="<?= e($form['original_slug'] ?? '') ?>">
        <input type="hidden" name="expected_revision" value="<?= e($form['expected_revision'] ?? '0') ?>">
        <label>Title <input name="title" maxlength="160" value="<?= e($form['title'] ?? '') ?>" required></label>
        <label>Page address under /pages/ <input name="slug" maxlength="32" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="visiting-guide" value="<?= e($form['slug'] ?? '') ?>" <?= $editing ? 'readonly' : '' ?> required>
        <small><?= $editing ? 'The address is fixed for this page.' : 'Use lowercase letters, digits and hyphens. The address stays fixed after saving.' ?></small></label>
        <label>Page content <textarea name="content" rows="13" required><?= e($form['content'] ?? '') ?></textarea>
        <small>Write in Markdown. HTML tags appear as text.</small></label>
        <label>Visibility <select name="status">
            <option value="draft">Draft</option>
            <option value="published" <?= ($form['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
        </select></label>
        <div class="admin-card__actions">
            <button class="btn btn-outline" name="action" value="preview">Preview</button>
            <button class="btn btn-primary" name="action" value="save">Save page</button>
        </div>
    </form>
    <?php if ($editing && empty($error) && !isset($preview) && ($form['status'] ?? '') === 'published'): ?>
        <p><a href="/pages/<?= e(rawurlencode($form['slug'])) ?>">Open published page</a></p>
    <?php endif; ?>
    <?php if ($editing && empty($error) && !isset($preview)): ?>
        <details><summary>Delete page</summary>
            <form class="admin-form" method="post" action="/admin/pages/delete">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="slug" value="<?= e($form['slug']) ?>">
                <input type="hidden" name="expected_revision" value="<?= e($form['expected_revision']) ?>">
                <label><input type="checkbox" required name="confirm_delete" value="yes"> Permanently delete this page and its content</label>
                <button class="btn btn-outline" type="submit">Delete this page</button>
            </form>
        </details>
    <?php endif; ?>
</section>
<?php if (isset($preview)): ?>
    <section class="admin-card" aria-labelledby="page-preview"><h2 id="page-preview">Unsaved preview</h2>
        <h3><?= e($form['title']) ?></h3><div class="blog-post__body"><?= $preview ?></div>
    </section>
<?php endif; ?>
