---
name: schema
description: Use when changing JSON database collections, fields, admin forms, media fields, or agent context payloads.
---

# JSON Schema

- Follow root `AGENTS.md` and `storage/AGENTS.md`.
- Update `storage/schema/collections.json` before changing JSON collection shapes, admin fields, media fields, seed data, or agent-visible context.
- Keep schema fields aligned with `storage/data/*.json`, admin resource forms, and `AgentContextService`.
- Provider access changes must align users, astrologers, appointments, consultation messages, and call-signaling collections.
- Regenerate and validate `docs/systematic-map.mmd` after schema or storage changes.
- Validate with `php tests/run.php`.
