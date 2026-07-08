---
description: Repository instructions for agents working on this PHP/JSON full-stack monorepo.
globs: *
alwaysApply: true
---

# Agent Operating Guide

This repo is an agent-ready PHP/MySQL/YAML full-stack product base for small PHP hosting. It is not a SPA, not a separate MCP/skill server. The backend primitives live in this monorepo. MySQL is the primary runtime data store; JSON files are used only for CLI-based one-time seeding via `bapXphp db sync`. Blog posts use YAML frontmatter in `content/blog/posts/`. Media metadata uses YAML in `storage/media.yaml`.

## DOX Contract

- `AGENTS.md` files are binding work contracts for their subtrees.
- Read this root file first. Identify every expected target path, then read every `AGENTS.md` from the repo root down to each target before editing.
- The nearest `AGENTS.md` controls local details. Parent docs continue to control repo-wide rules; child docs may not weaken this DOX contract.
- After meaningful edits, re-check changed paths against the DOX chain, update the closest owning `AGENTS.md` when purpose, structure, workflow, artifacts, contracts, or durable preferences changed, and refresh parent Child DOX Index entries when children change.
- Keep DOX docs concise and operational. Delete stale or contradictory instructions instead of explaining old history.

## Core Shape

- Design system: `Design.md` is the canonical contract for customer-facing UI tokens, typography, geometry, components, and responsive behavior.
- Frontend: PHP templates in `views/`.
- Backend: PHP controllers and services in `app/`.
- Database: MySQL is the primary runtime store. `config/database.php` holds connection config. `bapXphp db` CLI manages the DB. Blog posts use YAML frontmatter in `content/blog/posts/`. Media metadata uses YAML in `storage/media.yaml`.
- Schema: `storage/schema/collections.php` (for reference; runtime uses MySQL `DatabaseService`).
- Media: `assets/images/media/` plus `storage/media.yaml`.
- Admin: owner tools for CRUD, media, environment variables, permissions, integrations, audit logs, project map, and blog management.
- Agent context: `AgentContextService` builds safe user-specific context for support/model assistants.
- Consultations: admin-created astrologer accounts use PHP API polling for messages and WebRTC signaling; browser WebRTC carries call audio.

## Diagnose, Then Issue

- For a meaningful code, schema, UI, documentation, or workflow change, reproduce or inspect the reported behavior first. Trace the affected systematic-map path and pinpoint the owning source before creating an issue. For documentation/AGENTS.md changes, also consult `docs/KnowledgeMap.mmd`.
- After diagnosis, search open GitHub issues. Select an existing matching issue or create one before editing when GitHub is available.
- Put reproduction evidence, affected source paths, the pinpointed cause, intended scope, and acceptance checks in the issue. Reference the issue in the branch and PR.
- Do not create an issue for read-only diagnosis, trivial questions, or when the user explicitly declines issue tracking.

## Source-Grounded Work Order

1. Read this root `AGENTS.md`.
2. Read `docs/systematic-map.mmd` as a generated index, follow the affected edges, reproduce the behavior when possible, and pinpoint the owning source.
3. Search for an existing issue, then select or create the evidence-backed issue when the diagnose-then-issue rule applies.
4. Identify target paths and read their complete root-to-leaf `AGENTS.md` chain.
5. Read the narrow `.agents/skills/<skill-name>/SKILL.md` files that match the task.
6. Read `storage/schema/collections.php` for JSON-backed behavior and `Design.md` for customer-facing UI.
7. Search with `rg` and inspect existing implementations before creating any file, route, service, view, collection, or navigation item.
8. Implement against primary repository sources. The generated map summarizes relationships; it does not override source files.

## Project Map

- `docs/systematic-map.mmd` is the single project-map artifact (routes/controllers/services wiring). `docs/KnowledgeMap.mmd` is a separate generated documentation mindmap.
- Do not create `docs/PROJECT_MAP.md`, `docs/project-map.json`, `docs/project-map.mmd`, or parallel map generators (exception: `generate-docs-map.php` is the KnowledgeMap generator, not a parallel project map).
- `tools/generate-project-map.php` regenerates `docs/systematic-map.mmd`.
- `tools/generate-docs-map.php` regenerates `docs/KnowledgeMap.mmd`.
- `tools/validate-project-map.php` compares the generated Mermaid to the committed file.
- Update `ProjectMapService::scan()` and `ProjectMapService::renderSystematicMermaid()` when the map needs new sections, edges, or gap checks.
- Map validation alone is incomplete. For every affected map path, verify the source route, controller action, service, schema entry, storage collection, rendered page, and shared navigation that actually implement the behavior.
- Treat gap nodes as investigation prompts, not permission to scaffold a missing file. First determine whether the node is a JSON response, shared layout, runtime-only file, test fixture, or genuinely missing implementation.

## Rules

- Keep JSON storage first. MySQL is the query backend for `bapXphp db` CLI operations; JSON files are synced to MySQL tables.
- Update `storage/schema/collections.php` before changing a collection shape, admin fields, media fields, seed data, or agent-visible context.
- Extend existing controllers, services, views, storage files, and tools when they already cover the use case. Do not scaffold parallel implementations.
- When a code change reveals a reusable workflow rule, update the matching project skill under `.agents/skills/<skill-name>/SKILL.md` so future agents inherit the framework behavior. Keep skills business-agnostic.
- Keep route -> controller -> service -> MySQL-store boundaries via `DatabaseService`. Do not use `JsonStoreService` in runtime code.
- Do not add React, CDN React, a SPA fallback, or a second frontend.
- Customer-facing UI changes must follow `Design.md`: warm-neutral canvas, Inter/system sans typography, `#3A0003` primary maroon, `#D1B368` secondary gold, stable photo-first cards, restrained borders/shadows, and the documented responsive breakpoints.
- Admin mutations should be auditable via `AuditLogService`.
- User-specific assistant context must use `AgentContextService` or equivalent filtering. Never expose all users' data to a customer assistant.
- Product, temple, and astrologer media should use the media library picker/upload flow.
- Blog posts go in `content/blog/posts/` as `.md` files with YAML frontmatter. Blog categories in `content/blog/categories.yaml`.
- Media metadata goes in `storage/media.yaml` (not in MySQL).
- Secrets (Razorpay, Stripe, Google OAuth, SMTP, etc.) are admin-editable through Admin → Integrations and stored in the MySQL `secrets` table. Never put secrets in `.env`. System env vars serve as fallback for critical credentials.
- Before committing or pushing to remote `main`, verify the repo with the relevant PHP lint checks, tests, project-map generation/validation, and smoke checks.

## CLI-Only Operations

All project operations go through `bapXphp`. Never edit files directly unless explicitly told otherwise.

### Content CRUD (use the CLI, never edit files directly)

```bash
bapXphp read blog                    # list all blog posts
bapXphp read blog <slug>             # read a blog post
bapXphp write blog [slug]            # create or edit blog post (interactive)
bapXphp read product                  # list all products
bapXphp read product <slug>           # read a product
bapXphp write product [slug]          # create or edit product (interactive)
```

### Database (MySQL direct — no SSH needed)

```bash
bapXphp db query products --limit 5   # query products
bapXphp db find orders ord_123        # find an order
bapXphp db raw "SELECT * FROM ..."    # raw SQL
bapXphp db init                       # create MySQL tables from schema
bapXphp db sync                       # push JSON → MySQL
```

### Project Management

```bash
bapXphp test                          # run tests
bapXphp check                         # full validation chain
bapXphp serve                         # start dev server
bapXphp map:gen                       # regenerate project map
bapXphp docsmap                       # regenerate KnowledgeMap
bapXphp schema list                   # list all collections
bapXphp issue                         # create GitHub issue
bapXphp pr                            # create PR
bapXphp help                          # full reference
```

## Validation

Run the smallest useful validation for the change:

```bash
bapXphp lint path/to/changed.php
bapXphp test
bapXphp map:gen
bapXphp map:val
bapXphp smoke
```

For doc/AGENTS/skill changes, also regenerate the KnowledgeMap:

```bash
bapXphp docsmap
```

For UI changes, also use a browser workflow. Codex agents must use `Browser:control-in-app-browser` for localhost and in-app browser verification when the Browser plugin is available. Standalone Playwright is only a fallback for agents or environments that do not have the Browser plugin. Click the changed page like a user and verify the visible result.

Local browser validation must use this project's single dev-server port: `127.0.0.1:6020`. If `6020` is already listening, inspect and reuse that running project server; do not start another copy on `6021` or any alternate port unless the user explicitly authorizes it.

Before finishing, search the touched workflow for placeholders, dead buttons, duplicated fallbacks, stale labels, and incomplete wiring. Remove or wire them instead of leaving non-working UI.

## Child DOX Index

- `app/AGENTS.md`: PHP controllers, services, bootstrap, router, and route registry.
- `api/AGENTS.md`: JSON API entrypoint behavior.
- `.agents/AGENTS.md`: repo-owned agent skills and skill contracts.
- `views/AGENTS.md`: PHP-rendered public, account, admin, and layout templates.
- `storage/AGENTS.md`: JSON data, schema contracts, writable runtime files, and backups.
- `docs/AGENTS.md`: durable documentation and the systematic project map and KnowledgeMap.
- `integrations/AGENTS.md`: third-party integration client wrappers.
- `tools/AGENTS.md`: maintenance scripts, project-map generation/validation, local smoke checks, and mail queue tooling.
- `tests/AGENTS.md`: PHP regression tests and test fixtures.
- `assets/AGENTS.md`: CSS and static image/media assets.
