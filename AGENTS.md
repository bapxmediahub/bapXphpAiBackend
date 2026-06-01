---
description: Repository instructions for agents working on this PHP/JSON full-stack monorepo.
globs: *
alwaysApply: true
---

# Agent Operating Guide

This repo is an agent-ready PHP/JSON full-stack product base for small PHP hosting. It is not a SPA, not a SQL app, and not a separate MCP/skill server. The backend primitives live in this monorepo.

## Core Shape

- Frontend: PHP templates in `views/`.
- Backend: PHP controllers and services in `app/`.
- Database: JSON collections in `storage/data/`.
- Schema: `storage/schema/collections.json`.
- Media: `assets/images/media/` plus `storage/data/media_files.json`.
- Admin: owner tools for CRUD, media, environment variables, permissions, integrations, audit logs, and project map.
- Agent context: `AgentContextService` builds safe user-specific JSON for support/model assistants.

## Mandatory Read Order

1. `README.md`
2. `storage/schema/collections.json`
3. `docs/PROJECT_MAP.md`
4. `example-Agent.md`
5. The narrow skill under `.codex/skills/<skill-name>/`, `.claude/skills/<skill-name>/`, or `.agents/skills/<skill-name>/` that matches the task.

## Rules

- Keep JSON storage first. Do not introduce SQL/Postgres/MySQL unless the user explicitly asks for a separate migration.
- Update `storage/schema/collections.json` before changing a collection shape, admin fields, media fields, or agent-visible context.
- When a code change reveals a reusable workflow rule, update the matching project skill under `.codex/skills/<skill-name>/SKILL.md` so future agents inherit the framework behavior. Keep skills business-agnostic: describe the PHP/JSON backend, admin, UI, validation, deployment, and agent-context pattern, not one customer's domain.
- Keep route -> controller -> service -> JSON-store boundaries.
- Do not add React, CDN React, or a SPA fallback.
- Do not create a second frontend.
- Admin mutations should be auditable.
- User-specific assistant context must use `AgentContextService` or equivalent filtering. Never expose all users' JSON data to a customer assistant.
- Product, temple, and astrologer media should use the media library picker/upload flow.
- Environment and storage permission changes belong in `/admin/environment`.

## Validation

Run the smallest useful validation for the change:

```bash
php -l path/to/changed.php
php tests/run.php
php tools/validate-project-map.php
php tools/smoke-local.php
```

For UI changes, also use a browser workflow. Click the changed page like a user and verify the visible result.

Before finishing, search the touched workflow for placeholders, dead buttons, duplicated fallbacks, stale labels, and incomplete wiring. Remove or wire them instead of leaving non-working UI.
