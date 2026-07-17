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

- Posts stored as Markdown with YAML frontmatter in `content/blog/posts/*.md`.
- Managed via `BlogService` and editable through Admin → Blog editor.
- Paginated listing (10 per page), newest first.
- SEO: meta description from post excerpt, canonical URL, Open Graph tags.
- `help` category posts serve as customer help center guides.
