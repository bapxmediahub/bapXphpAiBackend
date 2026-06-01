---
name: schema
description: Use when changing JSON database collections, fields, admin forms, media fields, or agent context payloads.
---

# JSON Schema

`storage/schema/collections.json` is the source of truth for the JSON database.

## Rules

- Add or change schema before changing collection data shape.
- Keep field names stable and descriptive.
- Mark owner fields for user-owned collections.
- Mark media fields for product, temple, astrologer, and future content records.
- Keep `agent_context` minimal and safe. Include only fields a support assistant can reveal to the owning user.
- Update docs when schema semantics change.

## Validation

```bash
php -r 'json_decode(file_get_contents("storage/schema/collections.json"), true, flags: JSON_THROW_ON_ERROR); echo "schema ok\n";'
php tests/run.php
```
