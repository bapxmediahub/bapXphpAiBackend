<?php
    $categorySlug = (string)($meta['category'] ?? '');
    $categoryName = $categoryNames[$categorySlug] ?? '';
    // Use the configured canonical origin, not an untrusted request Host header.
    $schemaBase = rtrim((string)($schemaBase ?? (require app_path('config/database.php'))['app_url']), '/');
    $schemaUrl = $schemaBase . '/blog/' . ($slug ?? '');
    $schemaImage = \App\Services\BlogService::resolvedImageUrl($meta);
    if (str_starts_with($schemaImage, '/')) $schemaImage = $schemaBase . $schemaImage;
    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $schemaBase],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => $schemaBase . '/blog'],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $meta['title'] ?? 'Post', 'item' => $schemaUrl],
                ],
            ],
            [
                '@type' => 'Article',
                'headline' => $meta['title'] ?? '',
                'description' => $meta['seo_description'] ?? $meta['excerpt'] ?? '',
                'image' => $schemaImage ?: 'undefined',
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
<section class="blog-post-page blog-article">
  <div class="container">
    <nav class="breadcrumbs blog-article__breadcrumbs" aria-label="Breadcrumb">
      <a href="/blog">Journal</a>
      <?php if ($categoryName !== ''): ?>
        <span aria-hidden="true">/</span>
        <a href="/blog/category/<?= e(rawurlencode($categorySlug)) ?>"><?= e($categoryName) ?></a>
      <?php endif; ?>
    </nav>

    <article class="blog-post">
      <header class="blog-post__header">
        <h1><?= e($meta['title'] ?? '') ?></h1>
        <?php if (!empty($meta['excerpt'])): ?><p class="blog-post__dek"><?= e($meta['excerpt']) ?></p><?php endif; ?>
        <div class="blog-post__meta">
          <?php if (!empty($meta['published_at'])): ?>
            <time datetime="<?= e($meta['published_at']) ?>"><?= e(date('F j, Y', strtotime($meta['published_at']))) ?></time>
          <?php endif; ?>
          <?php if (!empty($meta['author'])): ?>
            <span class="blog-post__author">By <?= e($meta['author']) ?></span>
          <?php endif; ?>
        </div>
      </header>

      <?php $articleImage = \App\Services\BlogService::resolvedImageUrl($meta); ?>
      <figure class="blog-post__featured"><img src="<?= e($articleImage) ?>" alt="<?= e($meta['image_alt'] ?? '') ?>" loading="eager"></figure>

      <div class="blog-post__content">
        <?= $content ?>
      </div>
      <?php $sourceUrl = trim((string)($meta['source_url'] ?? '')); $sourceScheme = parse_url($sourceUrl, PHP_URL_SCHEME); ?>
      <?php if ($sourceUrl !== '' && ($sourceUrl[0] === '/' || in_array($sourceScheme, ['http', 'https'], true))): ?>
      <p class="blog-post__source"><span>Related page</span><a href="<?= e($sourceUrl) ?>">Open the page shown in this guide</a></p>
      <?php endif; ?>
    </article>

    <?php if ($categorySlug === 'help'): ?>
      <aside class="blog-post__cta">
        <div><h2>More help guides</h2><p>Find answers about your account, orders, and payments.</p></div>
        <a href="/blog/category/help" class="btn btn-outline">Browse Help</a>
      </aside>
    <?php elseif (module_on('shop')): ?>
      <aside class="blog-post__cta">
        <div><h2>Explore our collection</h2><p>Find spiritual products for daily worship and gifting.</p></div>
        <a href="/shop" class="btn btn-primary">Browse products</a>
      </aside>
    <?php endif; ?>
  </div>
</section>
