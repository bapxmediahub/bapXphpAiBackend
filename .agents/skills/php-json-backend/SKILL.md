---
name: php-json-backend
description: Use this skill set when contributing to this PHP/JSON agent-ready monorepo.
---

# PHP JSON Backend

- Read `AGENTS.md` first, then the closest child `AGENTS.md` for every path you will touch.
- Keep JSON storage first and keep route -> controller -> service -> JSON-store boundaries.
- Use `storage/schema/collections.json` before changing collection shape, admin fields, media fields, seed data, or agent-visible context.
- Use `docs/systematic-map.mmd` as the single wiring map. Regenerate it with `php tools/generate-project-map.php` and validate with `php tools/validate-project-map.php`.
- Traverse the affected map path into primary sources and verify its route, controller, service, schema, storage, page, and navigation contracts. Do not create a parallel file from a map gap without first searching the repo and classifying the gap.
- Extend existing controllers, services, views, storage files, and tools when they already cover the use case.
- Do not add React, CDN React, a SPA fallback, SQL, or parallel project-map artifacts unless the user explicitly requests a separate migration.
