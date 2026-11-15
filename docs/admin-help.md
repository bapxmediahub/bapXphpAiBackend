---
type: doc
title: Operator Help and Customer Guide Navigation
description: Authenticated task-based operator guides, public customer how-to links, and source-dependent documentation review.
category: docs
---

# Help is part of the workflow, not a repository browser

The owner workspace has a permanent **Help & Docs** entry in its sidebar and
topbar. `GET /admin/docs` provides a searchable, category-filtered catalogue;
`GET /admin/docs/{slug}` opens one curated operator guide. The same authenticated
`AdminController` handles both. Guests and non-admin accounts cannot view them.

The guide catalogue is a fixed manifest in `AdminHelpService`: title, category,
summary, original Markdown file, real task links and exact supporting source
paths. The twelve guides cover navigation, catalogue/offers, orders/shipping,
commerce/GST reports, support cases, Enterprise chat, monitoring, blog publishing,
media, customer accounts, email and settings.

## Actual navigation behavior

Search is an ordinary GET form with an 80-character query limit. It matches all
query words in the curated title, summary and guide body; it does not search
customer records, arbitrary files or credentials. Category links retain the
query. No matches produce an explicit empty state with a clear-filter path.
Guides offer real heading anchors and GET links back to their workspace.
JavaScript is not required to search, filter, open or read a guide.

Other owner screens show a native expandable **Help for this page** section.
Route matching respects complete path segments, and specific monitoring paths
are checked before their broader agent prefix. Unknown admin sections receive
the general navigation guide rather than an invented task-specific answer.
The topbar help entry remains reachable on mobile even without JavaScript.

Public shop/product/cart/checkout/account/login/contact screens link to the
existing published **Help** blog category and its task guides. The Support panel
has a separate public guide link outside the changing conversation log. Public
links are hidden when the Blog module is disabled; they never point to private
operator files. Existing `/docs` and `/help/{slug}` compatibility routes remain.

## Privacy and content boundaries

Operator files live at `content/help/admin/*.md`, not in `docs/issues`, `.agents/`
or `.bapX/`. The service requires a server-authenticated admin and accepts only
fixed slugs. Path-shaped or unknown slugs cannot select files. Required sources
are bounded UTF-8 files; symlink hops, missing/oversized or invalid sources fail
closed. The hardened existing `MarkdownRenderer` sanitizes rendered HTML.

Raw operator directory URLs are denied in root/directory Apache rules and the
PHP development-router guard before static-file handling, including decoded
path forms. Pages inherit private no-store admin headers. Do not serve the
Markdown files from a static bucket or create a general developer-document viewer.
Operator guidance is not a runtime-agent prompt and grants no tool permissions.

Guides explain actual controls and limitations. They do not claim that SMTP
acceptance is inbox delivery, a chat answer is customer resolution, a draft is
an applied edit, or a metadata date is an active publishing scheduler. Missing
or conflicting behavior is recorded in GitHub issues/comments with evidence,
not as a backlog or incident narrative in the how-to articles.

## Indexing and drift prevention

`KnowledgeGraphService` registers `operator_guide` concepts containing original
file references and source relationships, without copying private guide bodies
into generated indexes. Customer how-to articles remain ordinary published
`blog` concepts. Developer indexes aid discovery; they do not prove a feature
works or grant public access to its private source.

`config/documentation.php` builds a dedicated `operator-help` review contract
from the fixed guide/public-reference manifests. Shared feature sources are
explicit dependencies: an OrderService/controller edit invalidates the guide
review even when commerce/foundation owns that source file. Prose, templates,
links and the original implementation must be compared before recording a new
review rationale. Hashes detect change; they cannot automatically certify truth.

After a reviewed change run `php cli/docs-check.php --list`, record the affected
areas with a specific explanation, then `php cli/docs-check.php --check`,
`./bapXphp update` and `./bapXphp ci`. Tests cover real destination routes and
published public guides, private-role/path isolation, reference-only indexing
and dependency-triggered review staleness. Browser acceptance covers search,
filters, heading/task navigation and context links at 1280px/375px, no-JS and
unauthorized access. Repeat affected flows after deployment before closing the
GitHub issue; no user data needs to be changed to verify a read-only help feature.
