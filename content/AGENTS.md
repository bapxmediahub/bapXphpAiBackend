# Content DOX

## Purpose

Owns Markdown and YAML content rendered by public blog, help, and legal pages.

## Ownership

- `blog/posts/`: published Markdown articles with YAML frontmatter.
- `blog/categories.yaml`: blog category labels and summaries.
- `docs/`: customer-facing task guides rendered at `/help/{slug}`; `/docs` remains the help index because the physical repository `docs/` directory conflicts with nested routes on shared hosting.
- `legal/`: privacy and terms copy.

## Local Contracts

- Customer guides explain account access, orders, saved addresses, wallet payments, messages, calls, and support.
- Keep internal engineering, deployment, admin, and consultant operating documentation under repository `docs/`; never list it in the public help center.
- Every public guide needs `title`, `slug`, `summary`, and `order` frontmatter.
- Use `bapXphp read/write blog` and `bapXphp read/write docs` for content CRUD.

## Verification

- `bapXphp test`
- Browser check for `/docs`, `/help/{slug}`, `/blog`, and `/blog/{slug}`.
