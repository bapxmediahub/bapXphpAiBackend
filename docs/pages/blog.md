---
type: doc
title: Blog
description: Published blog, help guides, category filtering and article rendering with Markdown source files.
category: page
---

# Blog and Help Guides

Routes: `GET /blog`, `GET /blog/{slug}`,
`GET /blog/category/{slug}`. `BlogController` calls `BlogService` and
renders `views/public/blog.php` or `views/public/blog-post.php`.

Articles are stored in `content/blog/posts/*.md` with YAML frontmatter.
Published posts are filtered by category and optional `q` search; the current
listing is **not paginated**. `help` category posts are customer documentation,
with compatibility redirects from `/docs` and `/help/{slug}`.

The post title, description, original image metadata, Article JSON-LD and
OpenGraph properties come from published frontmatter, not MySQL. The admin
editor uses `BlogService` and a separate media picker. When a legacy article
references an image absent from the release, its public thumbnail/article
image and OG metadata must use a real, existing fallback; the underlying
original URL is retained for later media replacement. The missing-image fallback is implemented by `BlogService::resolvedImageUrl`; original references remain available for later artwork replacement.

Tests: verify image decode at desktop and 375px; category/keyword filtering,
publication hiding and source/link handling; do not infer an image exists from
a frontmatter URL without an actual file check.
