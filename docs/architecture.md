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

- MySQL is the primary runtime data store. `DatabaseService` manages all persistence.
- Collection schema lives in `storage/schema/collections.php`. Data is stored in MySQL tables.
- JSON files in `storage/data/` are used only for one-time seeding via `bapXphp db sync`.
- Local development falls back to the `/remotedb` HTTP proxy when direct MySQL is unreachable.
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
  Services/             Business logic and persistence
  Router.php            Route dispatcher
assets/
  css/                  Shared stylesheets
docs/                   Project, module, and deployment docs
storage/schema/         Database schema contracts
tools/                  Validation, project-map, and queue scripts
views/
  public/               Customer-facing PHP templates
  account/              Signed-in customer pages
  admin/                Owner/admin pages
  layouts/              Shared layouts
```
