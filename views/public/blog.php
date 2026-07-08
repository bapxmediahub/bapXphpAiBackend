<section class="blog-page">
  <div class="container">
    <h1 class="page-title"><?= e($categoryName ?? 'Blog & Updates') ?></h1>

    <?php if (!empty($categories)): ?>
    <div class="blog-categories">
      <a href="/blog" class="pill <?= $activeCategory === null ? 'pill--active' : '' ?>">All</a>
      <?php foreach ($categories as $cat): ?>
        <a href="/blog/category/<?= e($cat['slug'] ?? '') ?>"
           class="pill <?= $activeCategory === ($cat['slug'] ?? '') ? 'pill--active' : '' ?>">
          <?= e($cat['name'] ?? $cat['slug'] ?? '') ?>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($posts)): ?>
      <div class="empty-state">
        <p>No blog posts yet. Check back soon for updates, features, and spiritual insights.</p>
      </div>
    <?php else: ?>
      <div class="blog-grid">
        <?php foreach ($posts as $post): ?>
          <article class="blog-card">
            <?php if (!empty($post['image'])): ?>
              <img class="blog-card__image" src="<?= e($post['image']) ?>" alt="<?= e($post['title'] ?? '') ?>" loading="lazy">
            <?php endif; ?>
            <div class="blog-card__body">
              <?php if (!empty($post['category'])): ?>
                <span class="blog-card__category"><?= e($post['category']) ?></span>
              <?php endif; ?>
              <h2 class="blog-card__title">
                <a href="/blog/<?= e($post['slug'] ?? '') ?>"><?= e($post['title'] ?? '') ?></a>
              </h2>
              <?php if (!empty($post['excerpt'])): ?>
                <p class="blog-card__excerpt"><?= e($post['excerpt']) ?></p>
              <?php endif; ?>
              <?php if (!empty($post['published_at'])): ?>
                <time class="blog-card__date"><?= e(date('F j, Y', strtotime($post['published_at']))) ?></time>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
