# Content DOX

## Purpose

Owns Markdown and YAML content rendered by public blog, help, and legal pages.

## Ownership

- `blog/posts/`: published Markdown articles with YAML frontmatter.
- `blog/categories.yaml`: blog category labels and summaries.
- `blog/posts/`: editorial posts and customer-facing task guides. Guides use `category: help` and canonical `/blog/{slug}` URLs.
- `legal/`: privacy and terms copy.

## Local Contracts

- Customer guides explain account access, orders, saved addresses, product payments, consultant bookings, and support.
- Keep internal engineering, deployment, admin, and consultant operating documentation under repository `docs/`; never list it in the public help center.
- Every public guide needs `title`, `slug`, `summary`, and `order` frontmatter.
- Every post should define one `og_image` and descriptive `image_alt`; the same 16:9 image is used on listing and article pages. UI guides also define `source_url` for the exact page represented by the screenshot.
- Use `bapXphp read/write blog` and `bapXphp read/write docs` for content CRUD.

## Verification

- `bapXphp test`
- Browser check `/blog/category/help` and the affected `/blog/{slug}` pages. `/docs` and legacy `/help/{slug}` URLs only redirect.
