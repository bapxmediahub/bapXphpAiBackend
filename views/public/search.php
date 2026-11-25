<section class="section site-search" aria-labelledby="site-search-title">
    <div class="container site-search__inner">
        <header class="site-search__heading">
            <h1 id="site-search-title">Search the site</h1>
        </header>
        <form class="site-search__form" role="search" action="/search" method="get">
            <div class="form-group">
                <label for="site-search-query">Search by name or keyword</label>
                <input id="site-search-query" type="search" name="q" maxlength="120" value="<?= e($query) ?>" required>
            </div>
            <button class="btn btn-primary" type="submit">Search</button>
        </form>

        <?php if ($query !== '' && $total === 0): ?>
            <div class="site-search__empty">
                <h2>No results found</h2>
                <p>We found no matches for “<?= e($query) ?>”. Try a shorter name or a different spelling.</p>
                <a href="/search">Clear search</a>
            </div>
        <?php elseif ($total > 0): ?>
            <p class="site-search__count">
                <strong><?= $total ?> <?= $total === 1 ? 'result' : 'results' ?></strong> for “<?= e($query) ?>”
            </p>
            <?php $paths = ['Products' => '/product/', 'Articles' => '/blog/', 'Pages' => '/pages/', 'Temples' => '/temples/']; ?>
            <?php foreach ($groups as $heading => $items): ?>
                <?php if (!$items) continue; ?>
                <section class="site-search__group">
                    <h2><?= e($heading) ?></h2>
                    <ul class="site-search__results" role="list">
                        <?php foreach ($items as $item): ?>
                            <li>
                                <a href="<?= e($paths[$heading] . rawurlencode((string)$item['slug'])) ?>">
                                    <span><?= e($item['name'] ?? $item['title'] ?? $item['slug']) ?></span>
                                    <span aria-hidden="true">→</span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
            <?php if ($pages > 1): ?>
                <nav class="site-search__pages" aria-label="Search results pages">
                    <span>Page <?= $page ?> of <?= $pages ?></span>
                    <div>
                        <?php if ($page > 1): ?>
                            <a class="btn btn-outline" href="/search?<?= e(http_build_query(['q' => $query, 'page' => $page - 1])) ?>">Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $pages): ?>
                            <a class="btn btn-outline" href="/search?<?= e(http_build_query(['q' => $query, 'page' => $page + 1])) ?>">Next</a>
                        <?php endif; ?>
                    </div>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
