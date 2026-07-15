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
- `/api/*` routes return JSON for the shop, product, astrologer, and temple endpoints.
- Business logic lives in services under `app/Services/`.
- Route and dependency documentation is generated from `app/Services/ProjectMapService.php`.

## Data Persistence

- MySQL is the primary runtime data store, accessed through `DatabaseService`.
- Collection schema lives in `storage/schema/collections.php`. Data is stored in MySQL tables (not JSON files).
- Runtime data is remote-only: `.env` supplies direct hosted MySQL credentials to `DatabaseService`; when direct access is unavailable, the CLI/application use `APP_URL/remotedb`. The local checkout is not a customer-data fallback.
- Blog posts are file-based: `content/blog/posts/*.md` with YAML frontmatter.
- Media metadata lives in `storage/media.yaml` (not MySQL).
- `AgentContextService` builds safe user-specific context for the support/model assistant.

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
views/
  public/               Customer-facing PHP templates
  account/              Signed-in customer pages
  admin/                Owner/admin pages
  layouts/              Shared layouts
```
