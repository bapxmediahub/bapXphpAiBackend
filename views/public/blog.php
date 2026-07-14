<section class="blog-page blog-editorial">
  <div class="container">
    <header class="blog-editorial__header">
      <span class="eyebrow serif-accent">Ideas, rituals and guidance</span>
      <h1 class="page-title"><?= e($categoryName ?? 'Sri Panchami Journal') ?></h1>
      <p>Practical spiritual guidance, astrology explainers, and thoughtful updates from our consultants and team.</p>
    </header>

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
        <?php foreach ($posts as $index => $post): ?>
          <?php $postImage = $post['image'] ?? $post['og_image'] ?? ($index === 0 ? '/assets/images/hero-temple-bg.webp' : '/assets/images/og-image.jpg'); ?>
          <article class="blog-card <?= $index === 0 ? 'blog-card--featured' : '' ?>">
            <a class="blog-card__media" href="/blog/<?= e($post['slug'] ?? '') ?>" aria-label="Read <?= e($post['title'] ?? 'article') ?>">
              <img class="blog-card__image" src="<?= e($postImage) ?>" alt="<?= e($post['image_alt'] ?? '') ?>" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>">
            </a>
            <div class="blog-card__body">
              <div class="blog-card__kicker">
                <?php if (!empty($post['category'])): ?><span class="blog-card__category"><?php
                    $catName = '';
                    foreach ($categories as $cat) {
                        if (($cat['slug'] ?? '') === ($post['category'] ?? '')) {
                            $catName = $cat['name'] ?? '';
                            break;
                        }
                    }
                    e($catName ?: $post['category']);
                ?></span><?php endif; ?>
                <?php if (!empty($post['published_at'])): ?><time class="blog-card__date"><?= e(date('M j, Y', strtotime($post['published_at']))) ?></time><?php endif; ?>
              </div>
              <h2 class="blog-card__title">
                <a href="/blog/<?= e($post['slug'] ?? '') ?>"><?= e($post['title'] ?? '') ?></a>
              </h2>
              <?php if (!empty($post['excerpt'])): ?>
                <p class="blog-card__excerpt"><?= e($post['excerpt']) ?></p>
              <?php endif; ?>
              <a class="blog-card__read" href="/blog/<?= e($post['slug'] ?? '') ?>">Read article <span aria-hidden="true">→</span></a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
