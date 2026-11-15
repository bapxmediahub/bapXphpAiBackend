---
type: doc
title: Architecture
description: PHP-rendered public, account, and admin templates in views/ with shared CSS and small inline enhancement scripts.
category: docs
---
# Architecture

## Frontend

- PHP-rendered public, account, and admin templates in `views/`.
- Shared CSS in `assets/css/` plus small inline enhancement scripts in PHP layouts.
- Known public routes are dispatched by `index.php` into PHP controllers.
- Unknown routes render the PHP 404 page with `HTTP 404`. There is no SPA fallback.

## Backend

- PHP controllers in `app/Controllers/` render pages and handle form posts.
- `/api/*` routes return JSON for the shop, product, and temple endpoints.
- Business logic lives in services under `app/Services/`.
- Route and dependency documentation is generated from `app/Services/ProjectMapService.php`.

## Data Persistence

- Hosted MySQL is the runtime store for business records, through
  `DatabaseService` and the schema in `storage/schema/collections.php`.
- One **private JSON file per agent conversation** lives at
  `storage/agent-conversations/<conversation-id>.json` through
  `AgentConversationService`. Conversation files are not MySQL tables.
  Their trace/feedback/evaluation records are stored in the same JSON file.
- Runtime data is remote-only: `.env` supplies direct hosted MySQL credentials to `DatabaseService`; when direct access is unavailable, the CLI/application use `APP_URL/remotedb`. The local checkout is not a customer-data fallback.
- Blog posts are file-based: `content/blog/posts/*.md` with YAML frontmatter.
- Media metadata lives in MySQL `media_files` collection.
- New upload binaries live in the domain-local `.sps-media` directory beside the
  deployment checkout; `index.php` serves their existing public media URLs.
  Legacy media YAML is read-only compatibility input.
- `AgentContextService` builds safe user-specific support context.
  `SupportToolRegistry` and `EnterpriseToolRegistry` expose separate
  capabilities enforced by `AgentCapabilityPolicy`.
  `AgentTraceService` correlates observed retrieval, named tool calls,
  provider attempts, deterministic evaluations and the response in the
  session JSON. `AuditLogService` separately retains metadata-only run
  events in MySQL `audit_events`. No hosted observability service is needed.

## Current Data Flow

1. `index.php` routes public, account, review, and admin paths into the PHP router.
2. Controllers load data through services and render templates.
3. Customer forms post back to PHP controllers for cart, checkout, contact, reviews, and account flows.
4. Admin forms update MySQL-backed resources through services.
5. API clients use `/api/*` for JSON catalog data.

## File Structure

```text
api/                    PHP API entry point
app/
  Controllers/          Page and form controllers
  Services/             Business logic and MySQL persistence
  Router.php            Route dispatcher
assets/
  css/                  Shared stylesheets
cli/                    bapXphp CLI entry point and helper scripts
content/blog/posts/     Blog post markdown files
docs/                   Project, module, and deployment docs
storage/schema/         MySQL schema contract (collections.php)
storage/data/           Optional JSON seed files (one-time sync only)
storage/agent-conversations/  One private JSON document per support/admin chat
storage/agent-evals/     Optional offline fixture-eval snapshots (gitignored)
tests/fixtures/agent-golden*.json  Versioned, deterministic agent fixtures
views/
  public/               Customer-facing PHP templates
  account/              Signed-in customer pages
  admin/                Owner/admin pages
  layouts/              Shared layouts
```
