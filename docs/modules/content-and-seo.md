---
type: doc
title: Blog Authoring, Markdown and SEO Services
description: Source-backed developer responsibilities for blog drafts, explicit publishing, metadata and rendered content.
category: module
---

# Content authoring and metadata

`AdminController::blog/saveBlog` and `views/admin/blog.php` implement the owner
editor. `BlogService` reads/writes local Markdown with YAML frontmatter in
`content/blog/posts/`; categories are `content/blog/categories.yaml`. Public
`BlogController` filters unpublished/retired/module-disabled content and renders
through the shared public templates. There is no runtime `users.json`, remote
GitHub article index or database-backed blog body.

## Drafts and enhancement

`BlogDraftService` builds a deterministic scaffold from template/title/source
URL. It is not itself a model client. `AdminController::aiDraftBlog` returns
that scaffold for review. `enhanceBlogTitle` and `enhanceBlogContent` separately
call the configured model and return only their own field. Neither endpoint
proves the content was saved or published. The owner must review, explicitly
Save, and select the publication state. Images remain manually uploaded or
selected from existing media. Revisions, automatic save and scheduled
publication must not be claimed without their own implementation and tests.

`BlogService::resolvedImageUrl` verifies same-site image files/persistent media
before selecting the existing fallback. `BlogController` supplies an absolute
configured-origin OpenGraph image and article schema base. Original image
frontmatter is preserved so a missing asset can be supplied later.

## Markdown and metadata

`MarkdownRenderer` converts Markdown into article HTML and is reused for drafts
and blog bodies. Any change must preserve safe text, links, tables, code and
images while enforcing HTML/URL safety; permission to author content does not
make arbitrary attributes executable by visitors. Use malicious-input fixtures
locally, never a production article, when testing rendering boundaries.

`SeoService::page` merges page defaults with controller overrides and supplies
canonical/OpenGraph/Twitter fields. `BaseController` composes those fields and
public JSON-LD. `BlogController` has explicit canonical/image overrides; generic
page origin and product availability come from `SeoService` and must be checked
against actual host/configuration and product data. SEO markup is descriptive
metadata, not proof of search ranking or measured traffic.

Developer sources: `app/Services/BlogService.php`, `BlogDraftService.php`,
`MarkdownRenderer.php`, `SeoService.php`, `app/Controllers/BlogController.php`,
`AdminController.php`, `views/admin/blog.php`, `views/public/blog*.php`.
Current defects, acceptance status and release evidence belong to GitHub issues,
not a duplicate list in this guide.
