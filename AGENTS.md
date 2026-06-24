---
description: Repository instructions for agents working on this PHP/JSON full-stack monorepo.
globs: *
alwaysApply: true
---

# Agent Operating Guide

This repo is an agent-ready PHP/JSON full-stack product base for small PHP hosting. It is not a SPA, not a SQL app, and not a separate MCP/skill server. The backend primitives live in this monorepo.

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
- Database: JSON collections in `storage/data/`.
- Schema: `storage/schema/collections.json`.
- Media: `assets/images/media/` plus `storage/data/media_files.json`.
- Admin: owner tools for CRUD, media, environment variables, permissions, integrations, audit logs, and project map.
- Agent context: `AgentContextService` builds safe user-specific JSON for support/model assistants.
- Consultations: admin-created astrologer accounts use PHP API polling for messages and WebRTC signaling; browser WebRTC carries call audio.

## Diagnose, Then Issue

- For a meaningful code, schema, UI, documentation, or workflow change, reproduce or inspect the reported behavior first. Trace the affected systematic-map path and pinpoint the owning source before creating an issue.
- After diagnosis, search open GitHub issues. Select an existing matching issue or create one before editing when GitHub is available.
- Put reproduction evidence, affected source paths, the pinpointed cause, intended scope, and acceptance checks in the issue. Reference the issue in the branch and PR.
- Do not create an issue for read-only diagnosis, trivial questions, or when the user explicitly declines issue tracking.

## Source-Grounded Work Order

1. Read this root `AGENTS.md`.
2. Read `docs/systematic-map.mmd` as a generated index, follow the affected edges, reproduce the behavior when possible, and pinpoint the owning source.
3. Search for an existing issue, then select or create the evidence-backed issue when the diagnose-then-issue rule applies.
4. Identify target paths and read their complete root-to-leaf `AGENTS.md` chain.
5. Read the narrow `.agents/skills/<skill-name>/SKILL.md` files that match the task.
6. Read `storage/schema/collections.json` for JSON-backed behavior and `Design.md` for customer-facing UI.
7. Search with `rg` and inspect existing implementations before creating any file, route, service, view, collection, or navigation item.
8. Implement against primary repository sources. The generated map summarizes relationships; it does not override source files.

## Project Map

- `docs/systematic-map.mmd` is the only project-map artifact.
- Do not create `docs/PROJECT_MAP.md`, `docs/project-map.json`, `docs/project-map.mmd`, or parallel map generators.
- `tools/generate-project-map.php` regenerates `docs/systematic-map.mmd`.
- `tools/validate-project-map.php` compares the generated Mermaid to the committed file.
- Update `ProjectMapService::scan()` and `ProjectMapService::renderSystematicMermaid()` when the map needs new sections, edges, or gap checks.
- Map validation alone is incomplete. For every affected map path, verify the source route, controller action, service, schema entry, storage collection, rendered page, and shared navigation that actually implement the behavior.
- Treat gap nodes as investigation prompts, not permission to scaffold a missing file. First determine whether the node is a JSON response, shared layout, runtime-only file, test fixture, or genuinely missing implementation.

## Rules

- Keep JSON storage first. Do not introduce SQL/Postgres/MySQL unless the user explicitly asks for a separate migration.
- Update `storage/schema/collections.json` before changing a collection shape, admin fields, media fields, seed data, or agent-visible context.
- Extend existing controllers, services, views, storage files, and tools when they already cover the use case. Do not scaffold parallel implementations.
- When a code change reveals a reusable workflow rule, update the matching project skill under `.agents/skills/<skill-name>/SKILL.md` so future agents inherit the framework behavior. Keep skills business-agnostic.
- Keep route -> controller -> service -> JSON-store boundaries.
- Keep consultation communication in authenticated `/api/consultations/*` endpoints backed by `ConsultationService`; do not add a CLI or WebSocket service.
- Do not add React, CDN React, a SPA fallback, or a second frontend.
- Customer-facing UI changes must follow `Design.md`: warm-neutral canvas, Inter/system sans typography, `#3A0003` primary maroon, `#D1B368` secondary gold, stable photo-first cards, restrained borders/shadows, and the documented responsive breakpoints.
- Admin mutations should be auditable.
- User-specific assistant context must use `AgentContextService` or equivalent filtering. Never expose all users' JSON data to a customer assistant.
- Product, temple, and astrologer media should use the media library picker/upload flow.
- Environment and storage permission changes belong in `/admin/environment`.
- Before committing or pushing to remote `main`, verify the repo with the relevant PHP lint checks, tests, project-map generation/validation, and smoke checks.

## Validation

Run the smallest useful validation for the change:

```bash
php -l path/to/changed.php
php tests/run.php
php tools/generate-project-map.php
php tools/validate-project-map.php
php tools/smoke-local.php
```

For UI changes, also use a browser workflow. Codex agents must use `Browser:control-in-app-browser` for localhost and in-app browser verification when the Browser plugin is available. Standalone Playwright is only a fallback for agents or environments that do not have the Browser plugin. Click the changed page like a user and verify the visible result.

Before finishing, search the touched workflow for placeholders, dead buttons, duplicated fallbacks, stale labels, and incomplete wiring. Remove or wire them instead of leaving non-working UI.

## Child DOX Index

- `app/AGENTS.md`: PHP controllers, services, bootstrap, router, and route registry.
- `api/AGENTS.md`: JSON API entrypoint behavior.
- `.agents/AGENTS.md`: repo-owned agent skills and skill contracts.
- `views/AGENTS.md`: PHP-rendered public, account, admin, and layout templates.
- `storage/AGENTS.md`: JSON data, schema contracts, writable runtime files, and backups.
- `docs/AGENTS.md`: durable documentation and the single systematic project map.
- `integrations/AGENTS.md`: third-party integration client wrappers.
- `tools/AGENTS.md`: maintenance scripts, project-map generation/validation, local smoke checks, and mail queue tooling.
- `tests/AGENTS.md`: PHP regression tests and test fixtures.
- `assets/AGENTS.md`: CSS and static image/media assets.
