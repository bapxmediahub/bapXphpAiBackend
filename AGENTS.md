---
description: Repository instructions for agents working on this PHP/JSON full-stack monorepo.
globs: *
alwaysApply: true
---

# Agent Operating Guide

This repo is an agent-ready PHP/JSON full-stack product base for small PHP hosting. It is not a SPA, not a SQL app, and not a separate MCP/skill server. The backend primitives live in this monorepo.

## DOX Contract

- `AGENTS.md` files are binding work contracts for their subtrees.
- Before editing, read this root file, identify every expected target path, then read every `AGENTS.md` from the repo root down to each target.
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

## Mandatory Read Order

1. `README.md`
2. `Design.md` for customer-facing UI work.
3. `storage/schema/collections.json`
4. `docs/systematic-map.mmd`
5. The closest applicable `AGENTS.md` files from the DOX chain.
6. The narrow skill under `.agents/skills/<skill-name>/SKILL.md` that matches the task.

## Project Map

- `docs/systematic-map.mmd` is the only project-map artifact.
- Do not create `docs/PROJECT_MAP.md`, `docs/project-map.json`, `docs/project-map.mmd`, or parallel map generators.
- `tools/generate-project-map.php` regenerates `docs/systematic-map.mmd`.
- `tools/validate-project-map.php` compares the generated Mermaid to the committed file.
- Update `ProjectMapService::scan()` and `ProjectMapService::renderSystematicMermaid()` when the map needs new sections, edges, or gap checks.

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

For UI changes, also use a browser workflow. Click the changed page like a user and verify the visible result.

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
