---
type: skill
name: schema
description: Use when changing MySQL database collections, fields, admin forms, media fields, or agent context payloads.
---
# Schema

- Follow the root `AGENTS.md` repository contract and its storage area rules.
- Update `storage/schema/collections.php` before changing collection shapes, admin fields, media fields, seed data, or agent-visible context.
- Keep schema fields aligned with MySQL tables, admin resource forms, and `AgentContextService`.
- Provider access changes must align users, astrologers, appointments, consultation messages, and call-signaling collections.
- Regenerate and validate `docs/systematic-map.mmd` after schema or storage changes.
- Follow the affected map edges through services and pages, and distinguish runtime data from genuinely undeclared storage before adding files.
- Validate with `php tests/run.php`.
