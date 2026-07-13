# Storage DOX

## Purpose

Owns the MySQL schema contract (collections.php), optional JSON seed files, backups, runtime keys, locks, and writable runtime state.

## Ownership

- `schema/collections.php`: source of truth for collection shape, admin fields, media fields, ownership, and agent-visible context.
- `data/*.json`: JSON seed files for one-time `bapXphp db sync` (not used at runtime).
- `backups/`: backup output.
- Runtime files such as locks and keys are operational state.

## Local Contracts

- Update `storage/schema/collections.php` before changing collection shapes, admin fields, media fields, seed data, or agent-visible context.
- Keep every application collection declared in collections.php; test fixtures and runtime secrets are not application collections.
- Keep persistent data in MySQL (via DatabaseService). JSON seed files are only for one-time `bapXphp db sync`.
- Runtime secrets live in the remote MySQL `secrets` collection and are edited through Admin -> Integrations. Any local JSON secret fixture is import-only and never runtime storage. Never expose secrets to customer-facing assistant context.
- Do not expose secrets or all users' data to customer-facing assistant context.
- When adding a new secrets field, update `SecretService.php` and the admin integrations form.
- The `secrets` collection is synced to MySQL via `bapXphp db sync` as a single-object JSON file (wrapped in array for sync).

## Work Guidance

- Prefer schema-driven admin/resource changes over template-only field additions.
- Keep media records aligned with actual uploaded/static media paths.
- Keep `users`, `astrologers`, `appointments`, `consultation_messages`, and `consultation_signals` aligned when changing provider access or communication workflows.
- Avoid manual edits to lock files.

## Verification

- `bapXphp test`
- `bapXphp map:gen`
- `bapXphp map:val`

## Child DOX Index
