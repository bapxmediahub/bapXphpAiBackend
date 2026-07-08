---
name: php-json-backend
description: Use this skill set when contributing to this PHP/JSON agent-ready monorepo.
---

# PHP JSON Backend

- Read `AGENTS.md` first, then the closest child `AGENTS.md` for every path you will touch.
- Reproduce or inspect the behavior and pinpoint its owning map/source path before selecting or creating the implementation issue.
- Keep JSON storage first and keep route -> controller -> service -> JSON-store boundaries.
- JSON is the canonical data format; MySQL is the query backend for `bapXphp db` CLI operations. JSON files are synced to MySQL tables.
- Use `storage/schema/collections.php` before changing collection shape, admin fields, media fields, seed data, or agent-visible context.
- Use `docs/systematic-map.mmd` as the single wiring map. Regenerate it with `php tools/generate-project-map.php` and validate with `php tools/validate-project-map.php`.
- For documentation/AGENTS.md changes, also consult `docs/KnowledgeMap.mmd` and regenerate it with `bash tools/bapXphp docsmap`.
- Traverse the affected map path into primary sources and verify its route, controller, service, schema, storage, page, and navigation contracts. Do not create a parallel file from a map gap without first searching the repo and classifying the gap.
- Extend existing controllers, services, views, storage files, and tools when they already cover the use case.
- Do not add React, CDN React, a SPA fallback, or parallel project-map artifacts unless the user explicitly requests a separate migration.
