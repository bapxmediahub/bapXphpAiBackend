<section class="report-workspace">
    <header class="report-head">
        <div><h1>Temples</h1><p>Manage the places shown in the public temple directory.</p></div>
        <a class="btn btn-primary" href="/admin/temples?new=1">Add Temple</a>
    </header>
    <div class="admin-card table-wrap">
        <table>
            <thead><tr><th scope="col">Temple</th><th scope="col">Address</th><th scope="col">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <?php $name = (string)($item['name'] ?? 'Untitled temple'); ?>
                <tr>
                    <th scope="row"><?= e($name) ?></th>
                    <td><?= e($item['address'] ?? '—') ?></td>
                    <td>
                        <a class="btn btn-sm btn-outline" href="/admin/temples?edit=<?= e(rawurlencode($item['id'] ?? '')) ?>" aria-label="Edit <?= e($name) ?>">Edit</a>
                        <?php if (!empty($item['slug'])): ?>
                            <a class="btn btn-sm btn-ghost" href="/temples/<?= e(rawurlencode($item['slug'])) ?>" target="_blank" rel="noopener" aria-label="View <?= e($name) ?> on the site (opens in a new tab)">View</a>
                        <?php endif; ?>
                        <form method="post" action="/admin/temples/delete" onsubmit="return confirm('Delete this temple from the directory?');" style="display:inline;">
                            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                            <button type="submit" class="btn btn-sm btn-ghost" aria-label="Delete <?= e($name) ?>">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$items): ?><tr><td colspan="3">No temples have been added.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
