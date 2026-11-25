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

## Shared read-only article retrieval

The canonical published article search lives in `PublicKnowledgeService`, backed by `BlogService::all(false)` over `content/blog/posts/*.md`. It returns bounded public titles, `/blog/{slug}` links, category, relevance, total and cursor; it never reads an arbitrary path or returns Markdown source to a public client. The Enterprise Agent's `search_articles`, Support's article hints, local MCP `search_public_articles`, and browser WebMCP's read-only `GET /api/knowledge/articles` adapter use this service. The ordinary public `/blog` HTML page remains independently filtered and is not changed to API pagination.

Both native agents include published article body matches in discovery. The
published title, path, category and score stay in the result. The exact-slug
read accepts optional topic keywords and selects a passage of up to 6,000
characters from the best matching rendered section. A slug-only request reads
the opening. Public HTTP, WebMCP and MCP search still use title and summary
ranking and keep the existing input and output schema.

Hidden, future-dated, retired-category, module-disabled or symlinked posts must not be returned. Disabling Blog removes the local article MCP tool and prevents public API results; operator Help and private JSON conversation records are not part of the public index. Tests use synthetic Markdown and check pagination/visibility without customer records.

## Customer help articles

The Help category includes account creation/access, product ordering, payment
confirmation, account/profile/address management and contacting Support.
`AdminHelpService::publicReferences` binds these published articles to their
original source contracts for documentation review. Public page-context links
use only these published customer-facing articles, not operator guides or
repository documentation. They are suppressed when the Blog module is disabled.
The operator Help & Docs hub is a separate authenticated route and content
allowlist; it does not depend on a live model or customer database mutation.

The owner post list sits in a named, keyboard-focusable horizontal scroll
region on narrow screens. All edit/publish/delete actions remain in the table;
only the list scrolls horizontally, not the entire admin page. Do not hide body
overflow to mask intrinsic table-width defects. Verify at 375px with long titles.

## Scheduling and compare-and-swap saves

BlogService owns public visibility: published flag, valid due publication date
and allowed category/module must all pass. Calendar dates start at midnight
Asia/Kolkata; explicit timezone timestamps compare actual instants. Missing
legacy dates retain their historical visibility, invalid dates fail closed.
The direct article route calls the same predicate as all public listings,
sitemap and agent retrieval. Raw `content/blog/posts` URLs are denied, so
private/scheduled Markdown cannot bypass the route.

Editor saves return the SHA-256 of the actual persisted file. Existing updates,
toggles and deletes require the expected hash under an interprocess lock;
atomic same-directory replacement preserves complete files. Missing/stale
revisions and create collisions fail, with submitted text retained in the owner
session for download and explicit reload. Existing slugs stay stable on title
edit. CLI image updates use the revision returned by find(). No arbitrary file
paths, symlinks, metadata-newline injection or oversized content are accepted.

New owner drafts are explicit. A new due/public post may send the separately
selected newsletter; a scheduled/hidden save sends none and no delayed newsletter
job is installed. Old UI forms lacking a revision must be reloaded before save.
Open **History** beside an article to inspect and restore an earlier server save.
Changed saves through BlogService, including visibility changes and restores, keep the
previous Markdown and frontmatter in up to 10 private `.rev` files beside that
article. Their extension excludes them from generated Markdown indexes. Preserve
these ignored files with the posts when backing up or deploying. Deleting an article
clears its history. New articles start with an empty history. The editor and interactive writing CLI use the same save service. Direct filesystem edits bypass this history.
Restoring requires the current article revision shown at preview time and checks
the archived content hash. It restores the saved publication settings and never
enqueues a newsletter.
Run `BAPX_TEST_MODE=1 php tests/blog-concurrency.php`; six synthetic writers must
produce one save and five conflicts. Do not create live scheduled articles as
QA fixtures or send newsletters during acceptance.


### Recover a browser draft

After reopening New Post or the same article, choose **Restore draft** if a
browser copy is available. Resolve the offer before editing. If the saved
revision is newer, select **Download browser draft** to keep the Markdown body
for comparison, then select **Discard browser copy** to continue. Recovery
depends on storage in this browser profile. On a shared computer, discard the
copy when you finish. Review publication and subscriber email settings before
selecting **Save**.

Public site search at `/search` includes visible shop products, published articles and temples. The menu opens the same GET form on mobile and desktop. Hidden and scheduled articles follow the blog publication gate.

### A2UI v1.0 published guide presentation

Both in-site agents and the public WebMCP adapter can display published article/guide cards through `PublicA2uiService`; draft, future and private admin guides remain unavailable. The browser renderer supports only trusted, read-only catalog components. See [A2UI contract](../a2ui.md).
