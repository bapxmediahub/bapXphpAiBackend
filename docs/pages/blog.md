---
type: doc
title: Blog
description: Blog listing page with pagination and single blog post routes.
category: page
---
# Blog

Routes:
- `/blog` — blog listing page with pagination
- `/blog/{slug}` — single blog post

Controller: `BlogController`

Views: `views/public/blog.php`, `views/public/blog-post.php`

## Features

- Posts stored in `products.json` with `type: "blog"` (shared catalog collection).
- Markdown body rendered to HTML.
- Paginated listing (10 per page), newest first.
- SEO: meta description from post excerpt, canonical URL, Open Graph tags.
