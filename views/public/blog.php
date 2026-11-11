<?php
  $isHelp = $activeCategory === 'help';
  $indexUrl = $activeCategory === null ? '/blog' : '/blog/category/' . rawurlencode($activeCategory);
  $query = $search === '' ? '' : '?' . http_build_query(['q' => $search]);
?>
<section class="blog-page blog-editorial">
  <div class="container">
    <header class="blog-editorial__header">
      <h1 class="page-title"><?= e($categoryName ?? 'Sri Panchami Journal') ?></h1>
      <p><?= $isHelp ? 'Guides for your account, orders, and payments.' : 'Spiritual practice, temple guides, and news from Sri Panchami.' ?></p>
    </header>

    <div class="blog-editorial__tools">
      <nav class="blog-categories" aria-label="Article categories">
        <a href="<?= e('/blog' . $query) ?>"<?= $activeCategory === null ? ' aria-current="page"' : '' ?>>Journal</a>
        <?php foreach ($categories as $cat): ?>
          <?php $categorySlug = (string)($cat['slug'] ?? ''); ?>
          <a href="<?= e('/blog/category/' . rawurlencode($categorySlug) . $query) ?>"<?= $activeCategory === $categorySlug ? ' aria-current="page"' : '' ?>>
            <?= e($cat['name'] ?? $categorySlug) ?>
          </a>
        <?php endforeach; ?>
      </nav>
      <form class="blog-search" action="<?= e($indexUrl) ?>" method="get" role="search" aria-label="Search articles">
        <label for="blog-search-query" class="sr-only"><?= $isHelp ? 'Search Help' : 'Search articles' ?></label>
        <input type="search" id="blog-search-query" name="q" maxlength="120" value="<?= e($search) ?>" placeholder="<?= $isHelp ? 'Search Help' : 'Search articles' ?>">
        <button class="btn btn-primary" type="submit">Search</button>
      </form>
    </div>

    <?php if ($search !== ''): ?>
      <div class="blog-search-summary">
        <p><?= count($posts) ?> <?= count($posts) === 1 ? 'result' : 'results' ?> for “<?= e($search) ?>”</p>
        <a href="<?= e($indexUrl) ?>">Clear search</a>
      </div>
    <?php endif; ?>

    <?php if (!$posts): ?>
      <div class="empty-state">
        <p><?= $search === '' ? 'No articles in this category yet.' : 'No articles match. Try a different search or browse another category.' ?></p>
      </div>
    <?php else: ?>
      <div class="blog-grid">
        <?php foreach ($posts as $index => $post): ?>
          <?php
            $postImage = $post['image'] ?? $post['og_image'] ?? '/assets/images/hero-temple-bg.webp';
            $postUrl = '/blog/' . rawurlencode((string)($post['slug'] ?? ''));
            $featured = $index === 0 && $activeCategory === null && $search === '';
            $postCategory = (string)($post['category'] ?? '');
          ?>
          <article class="blog-card<?= $featured ? ' blog-card--featured' : '' ?>">
            <a class="blog-card__media" href="<?= e($postUrl) ?>" aria-label="Read <?= e($post['title'] ?? 'article') ?>">
              <img class="blog-card__image" src="<?= e($postImage) ?>" alt="<?= e($post['image_alt'] ?? '') ?>" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>" decoding="async">
            </a>
            <div class="blog-card__body">
              <div class="blog-card__kicker">
                <?php if ($postCategory !== ''): ?><span><?= e($categoryNames[$postCategory] ?? $postCategory) ?></span><?php endif; ?>
                <?php if (!empty($post['published_at'])): ?><time datetime="<?= e($post['published_at']) ?>"><?= e(date('M j, Y', strtotime($post['published_at']))) ?></time><?php endif; ?>
              </div>
              <h2 class="blog-card__title"><a href="<?= e($postUrl) ?>"><?= e($post['title'] ?? '') ?></a></h2>
              <?php if (!empty($post['excerpt'])): ?><p class="blog-card__excerpt"><?= e($post['excerpt']) ?></p><?php endif; ?>
              <a class="blog-card__read" href="<?= e($postUrl) ?>" aria-label="Read article: <?= e($post['title'] ?? '') ?>">Read article <span aria-hidden="true">→</span></a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
