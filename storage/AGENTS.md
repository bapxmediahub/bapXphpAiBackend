# Storage DOX

## Purpose

Owns JSON database files, schema contracts, backups, runtime keys, locks, and writable runtime state.

## Ownership

- `schema/collections.json`: source of truth for collection shape, admin fields, media fields, ownership, and agent-visible context.
- `data/*.json`: JSON collections and admin/runtime data.
- `backups/`: backup output.
- Runtime files such as locks and keys are operational state.

## Local Contracts

- Update `storage/schema/collections.json` before changing collection shapes, admin fields, media fields, seed data, or agent-visible context.
- Keep every application collection declared in the schema even when its JSON file is created lazily at first write; test fixtures and runtime secrets are not application collections.
- Keep persistent data JSON-first unless the user explicitly requests a separate SQL migration.
- Do not expose secrets or all users' JSON data to customer-facing assistant context.

## Work Guidance

- Prefer schema-driven admin/resource changes over template-only field additions.
- Keep media records aligned with actual uploaded/static media paths.
- Keep `users`, `astrologers`, `appointments`, `consultation_messages`, and `consultation_signals` aligned when changing provider access or communication workflows.
- Avoid manual edits to lock files.

## Verification

- `php tests/run.php`
- `php tools/generate-project-map.php`
- `php tools/validate-project-map.php`

## Child DOX Index
