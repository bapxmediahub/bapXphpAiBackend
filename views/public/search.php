<section class="section">
    <div class="container document-page__content">
        <h1>Search the site</h1>
        <form role="search" action="/search" method="get">
            <label for="site-search-query">Search by name or keyword</label>
            <input id="site-search-query" type="search" name="q" maxlength="120" value="<?= e($query) ?>" required>
            <button class="btn btn-primary" type="submit">Search</button>
        </form>
        <?php if ($query !== '' && $total === 0): ?>
            <p>No results for “<?= e($query) ?>”. Try a shorter name or a different spelling.</p>
        <?php endif; ?>
        <?php $paths = ['Products' => '/product/', 'Articles' => '/blog/', 'Temples' => '/temples/']; ?>
        <?php foreach ($groups as $heading => $items): ?>
            <?php if (!$items) continue; ?>
            <h2><?= e($heading) ?></h2>
            <ul>
                <?php foreach ($items as $item): ?>
                    <li><a href="<?= e($paths[$heading] . rawurlencode((string)$item['slug'])) ?>"><?= e($item['name'] ?? $item['title'] ?? $item['slug']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
        <?php if ($pages > 1): ?>
        <nav class="page-sections" aria-label="Search results pages">
            <?php if ($page > 1): ?><a href="/search?<?= e(http_build_query(['q'=>$query, 'page'=>$page - 1])) ?>">Previous</a><?php endif; ?>
            <span>Page <?= $page ?> of <?= $pages ?></span>
            <?php if ($page < $pages): ?><a href="/search?<?= e(http_build_query(['q'=>$query, 'page'=>$page + 1])) ?>">Next</a><?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>
</section>
