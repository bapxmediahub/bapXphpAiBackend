---
type: skill
name: php-json-backend
description: Use this skill set when contributing to this PHP/JSON agent-ready monorepo.
---
# PHP JSON Backend

- Read the root `AGENTS.md` repository contract first.
- Reproduce or inspect the behavior and pinpoint its owning map/source path before selecting or creating the implementation issue.
- Keep route -> controller -> service -> MySQL-store boundaries via `DatabaseService`.
- Remote MySQL is the only runtime store. JSON files in `storage/data/` are import fixtures only and never a runtime fallback.
- Use `storage/schema/collections.php` before changing collection shape, admin fields, media fields, seed data, or agent-visible context.
- Use `docs/systematic-map.mmd` as the single wiring map. Regenerate it with `php cli/generate-project-map.php` and validate with `php cli/validate-project-map.php`.
- For documentation or root instruction changes, also consult `docs/map.mmd` and `map.mmd` (root); regenerate with `bapXphp update`.
- Traverse the affected map path into primary sources and verify its route, controller, service, schema, storage, page, and navigation contracts. Do not create a parallel file from a map gap without first searching the repo and classifying the gap.
- Extend existing controllers, services, views, storage files, and tools when they already cover the use case.
- Do not add React, CDN React, a SPA fallback, or parallel project-map artifacts unless the user explicitly requests a separate migration.
