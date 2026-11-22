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
- Business records are remote-only: `.env` supplies direct hosted MySQL credentials to `DatabaseService`; when direct access is unavailable, the CLI/application use `APP_URL/remotedb`. The local checkout is not a customer-data fallback.
- Blog posts are file-based: `content/blog/posts/*.md` with YAML frontmatter.
- `PublicKnowledgeService` is the shared read-only projection for visible products, published blog/customer-help metadata, public contact details and existing enquiry services. Both PHP agent surfaces reuse its public tools; local MCP shares the canonical product schema, and landing-page WebMCP calls bounded public GET endpoints. Contact page facts and service links come from the same explicit projection, even when Shop/Blog are disabled. A public request cannot address private Markdown, owner Help, broad settings rows or JSON conversation storage. See [WebMCP contracts and verification](webmcp.md).
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

## Instruction source isolation

Developer skills remain in `.agents/`; live prompts load only a fixed role
manifest under `.bapX/` through `AgentInstructionService`. `.bapX` is private
business instruction source, not conversation storage or an unrestricted file
tool. Runtime traces and offline experiments fingerprint the actual loaded
Markdown. See `docs/runtime-agents.md` for exact file/role/size/HTTP boundaries.

Both PHP layouts use shared `assets/site-motion.js` / `assets/css/site-motion.css`
as progressive enhancement, preserving semantic HTML and native scrolling.

## Human help surfaces

Authenticated operator instructions are a fixed `AdminHelpService` catalogue
under `content/help/admin/`, rendered by existing AdminController routes at
`/admin/docs`. They are neither runtime-agent prompt inputs nor a repository
file browser. Raw Markdown URLs are denied before static serving. Public help
remains published articles in `content/blog/posts/` linked by task context.
The developer graph indexes operator metadata without copying private bodies;
explicit source dependencies invalidate stale help-document review receipts.
See `admin-help.md` for the exact boundaries and browser acceptance contract.

Public canonical, organization, social-image and sitemap URLs use the configured
`APP_URL`, not request Host/HTTPS headers. The public layout consumes the resolved
SeoService metadata instead of reconstructing its own origin. This is metadata
correctness, not a new business-data or search-performance service.

## Runtime diagnostics

The native target is stable PHP>=8.3 and actual MySQL>=8.4.
`RuntimeHealthService` provides bounded read-only facts to the authenticated
System Health screen and CLI. Database vendor identity is separate from version;
MariaDB cannot pass the MySQL target by its numeric version. Existing deployment
noncompliance is reported, not silently migrated or used to disable checkout.

## Dynamic response integrity

The PHP bootstrap installs a dependency-free RequestFailureService before
session/environment work. Its owned output boundary prevents a late exception
from publishing a partial successful page; uncaught failures return safe,
correlated HTML/JSON500 without raw admin exception messages. Existing explicit
service503 responses and CLI nonzero failures remain distinct. The response
cannot prove whether a prior business action completed, so recovery guidance
requires checking state before repeating it. See `request-failures.md`.

### Declarative agent answers and public references (A2UI v1.0)

Both Support and Enterprise present successful answers through server-owned A2UI templates. `AgentAnswerPresentationService` formats the already-authorized answer, bounded recorded aggregate facts and read-only navigation in the existing native chat; it performs no retrieval and creates no new agent or storage layer. Enterprise fact groups use completed allowlisted tool evidence, with recorded period/source/observation context. History restores those saved facts; Support replay formats the saved answer without fetching current catalogue prices. `PublicA2uiService` separately projects published products, articles, contact details and service options from `PublicKnowledgeService`; exact tool results can become public cards without repeating a query or resetting pagination. Only these public references are exposed through WebMCP and `GET /api/knowledge/a2ui`. The stateless `POST /api/mcp` adapter exposes the same four public data tools with exact-result cards through an MCP Apps resource; both supported protocol profiles remain separate from owner STDIO grants and browser sessions. The shared renderer defaults to public scope and rejects private answer surfaces unless the native host explicitly supplies its Support or admin scope. It allows bounded Card/Column/Text/Image/navigation components and private label/value Rows, fails atomically on unsupported input and keeps the full original answer as fallback. See [A2UI design and acceptance](a2ui.md).
