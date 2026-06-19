---
name: docs
description: Use when editing README, docs, project-map docs, or agent-facing instructions.
---

# Docs

- Follow the applicable DOX chain: root `AGENTS.md`, then the nearest child `AGENTS.md`.
- `docs/systematic-map.mmd` is the only project-map artifact.
- Do not recreate `docs/PROJECT_MAP.md`, `docs/project-map.json`, or `docs/project-map.mmd`.
- Regenerate the map with `php tools/generate-project-map.php` after route, service, view, schema, storage, tool, or integration changes.
- Validate with `php tools/validate-project-map.php`.
- Keep durable docs concise, current, and aligned with the PHP/JSON shared-hosting architecture.
