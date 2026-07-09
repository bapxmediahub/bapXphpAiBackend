# Blog

Routes:
- `/blog` — blog listing page with pagination
- `/blog/{slug}` — single blog post

Controller: `BlogController`

Views: `views/public/blog.php`, `views/public/blog-post.php`

## Features

- Posts stored as `.md` files with YAML frontmatter in `content/blog/posts/`.
- Categories defined in `content/blog/categories.yaml`.
- Markdown body rendered to HTML.
- Paginated listing (10 per page), newest first.
- SEO: meta description from post excerpt, canonical URL, Open Graph tags.
