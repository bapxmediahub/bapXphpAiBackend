<?php
    $schemaUrl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'sripanchamispiritual.com') . '/blog/' . ($slug ?? '');
    $schemaImage = $meta['og_image'] ?? $meta['image'] ?? ($seo['og_image'] ?? '');
    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'sripanchamispiritual.com')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => $schemaUrl . '/blog'],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $meta['title'] ?? 'Post', 'item' => $schemaUrl],
                ],
            ],
            [
                '@type' => 'Article',
                'headline' => $meta['title'] ?? '',
                'description' => $meta['seo_description'] ?? $meta['excerpt'] ?? '',
                'image' => $schemaImage ?: undefined,
                'datePublished' => $meta['published_at'] ?? '',
                'dateModified' => $meta['updated_at'] ?? $meta['published_at'] ?? '',
                'author' => [
                    '@type' => 'Person',
                    'name' => $meta['author'] ?? 'Sri Panchami Spiritual',
                ],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => 'Sri Panchami Spiritual',
                ],
                'mainEntityOfPage' => $schemaUrl,
            ],
        ],
    ];
    // Filter out undefined values
    $schema['@graph'][1] = array_filter($schema['@graph'][1], fn($v) => $v !== 'undefined');
?>
<script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
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
