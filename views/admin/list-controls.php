<?php
$mediaList = array_key_exists('context', $paging);
$params = ['q' => $paging['search'], 'sort' => $paging['sort'], 'per_page' => $paging['pageSize']];
if ($mediaList) $params['context'] = $paging['context'];
$listPath = $mediaList ? '/admin/media' : '/admin/'.$collection;
$anchor = $mediaList ? '#media-library' : '';
$listLink = static fn(int $page): string => $listPath.'?'.http_build_query($params + ['page' => $page]).$anchor;
?>
<form method="get" action="<?= e($listPath.$anchor) ?>" class="admin-form" aria-label="Filter <?= e($mediaList ? 'media' : strtolower($title)) ?>">
    <div class="admin-form__row">
        <label>Search
            <input type="search" name="q" value="<?= e($paging['search']) ?>" maxlength="150"
                placeholder="<?= $mediaList ? 'Filename, description or path' : 'ID, customer, status or date' ?>">
        </label>
        <?php if ($mediaList): ?>
            <label>Context
                <select name="context"><option value="">All contexts</option>
                    <?php foreach (['shared','products','temples','blog'] as $ctx): ?>
                        <option value="<?= e($ctx) ?>" <?= $paging['context'] === $ctx ? 'selected' : '' ?>><?= e(ucfirst($ctx)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <label>Sort
            <select name="sort"><option value="newest">Newest first</option>
                <option value="oldest" <?= $paging['sort'] === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
            </select>
        </label>
        <label>Per page
            <select name="per_page">
                <?php foreach ([10,20,25,50,100,200] as $n): ?>
                    <option value="<?= $n ?>" <?= $paging['pageSize'] === $n ? 'selected' : '' ?>><?= $n ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <div><button class="btn btn-primary btn-sm">Apply</button> <a class="btn btn-ghost btn-sm" href="<?= e($listPath.$anchor) ?>">Reset</a></div>
</form>
<nav class="merchant-actions" aria-label="List pages">
    <span><?= $paging['count'] ? $paging['offset'] + 1 : 0 ?>–<?= min($paging['offset'] + $paging['pageSize'], $paging['count']) ?> of <?= (int)$paging['count'] ?></span>
    <?php if ($paging['page'] > 1): ?><a rel="prev" href="<?= e($listLink($paging['page'] - 1)) ?>">Previous</a><?php endif; ?>
    <?php if ($paging['page'] < $paging['pages']): ?><a rel="next" href="<?= e($listLink($paging['page'] + 1)) ?>">Next</a><?php endif; ?>
</nav>
