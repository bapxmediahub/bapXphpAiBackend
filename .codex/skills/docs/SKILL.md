---
name: docs
description: Use when editing README, example-Agent, docs, project-map docs, or agent-facing instructions.
---

# Docs

## Rules

- Keep docs connected from `README.md` and `docs/README.md`.
- Agent docs should be instruction-first.
- Public docs should explain this as a PHP/JSON full-stack monorepo for small hosting.
- Update `docs/PROJECT_MAP.md` by running `php tools/generate-project-map.php` after route/service changes.
- Avoid stale references to SPA, SQL-only setup, or separate MCP/skill installs.

## Validation

Re-read every command, path, route, and file reference touched.
