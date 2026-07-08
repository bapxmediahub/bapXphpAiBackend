<section class="blog-post-page">
  <div class="container">
    <nav class="breadcrumbs">
      <a href="/blog">← Blog</a>
    </nav>

    <article class="blog-post">
      <header class="blog-post__header">
        <h1><?= e($meta['title'] ?? '') ?></h1>
        <div class="blog-post__meta">
          <?php if (!empty($meta['category'])): ?>
            <span class="blog-card__category"><?= e($meta['category']) ?></span>
          <?php endif; ?>
          <?php if (!empty($meta['published_at'])): ?>
            <time><?= e(date('F j, Y', strtotime($meta['published_at']))) ?></time>
          <?php endif; ?>
          <?php if (!empty($meta['author'])): ?>
            <span class="blog-post__author">By <?= e($meta['author']) ?></span>
          <?php endif; ?>
        </div>
      </header>

      <?php if (!empty($meta['image'])): ?>
        <img class="blog-post__featured" src="<?= e($meta['image']) ?>" alt="<?= e($meta['title'] ?? '') ?>" loading="lazy">
      <?php endif; ?>

      <div class="blog-post__content">
        <?= $content ?>
      </div>
    </article>

    <nav class="blog-post__nav">
      <a href="/blog" class="btn btn--outline">← All Posts</a>
    </nav>
  </div>
</section>
