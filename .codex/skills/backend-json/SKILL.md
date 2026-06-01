---
name: backend-json
description: Use when editing PHP controllers, services, JSON persistence, auth, support assistant context, wallet, orders, reviews, media, or audit behavior.
---

# Backend JSON

## Scope

- `app/Controllers/**`
- `app/Services/**`
- `storage/data/*.json`
- `storage/schema/collections.json`
- `api/index.php`

## Rules

- Preserve route -> controller -> service -> JSON-store separation.
- Use `JsonStoreService` for collection reads/writes.
- Use lock-safe writes, not ad hoc file writes for runtime collections.
- Update `storage/schema/collections.json` when collection shapes change.
- Keep customer assistant context filtered by user identity through `AgentContextService`.
- Keep customer assistant memory scoped to the active browser/user session unless the product explicitly needs durable support records.
- Support/model assistants may read only safe public catalog context plus the logged-in user's filtered context. Do not give customer assistants direct filesystem or all-user JSON access.
- Add audit log records for admin mutations.
- Avoid SQL migrations and database clients unless the user starts a separate migration.
- If backend behavior becomes a reusable framework rule, update this skill or the relevant child skill with that rule.

## Validation

```bash
php -l app/Controllers/ChangedController.php
php -l app/Services/ChangedService.php
php tests/run.php
```
