# Storage DOX

## Purpose

Owns collection schema, media metadata, backups, runtime keys, locks, and writable runtime state.

## Ownership

- `schema/collections.php`: source of truth for collection shape, admin fields, media fields, ownership, and agent-visible context.
- `backups/`: backup output.
- Runtime files such as locks and keys are operational state.

## Local Contracts

- Update `storage/schema/collections.php` before changing collection shapes, admin fields, media fields, seed data, or agent-visible context.
- Keep every application collection declared in the schema; test fixtures and runtime secrets are not application collections.
- Keep persistent data MySQL-first. `storage/schema/collections.php` is the schema contract.
- Secrets (Razorpay, Stripe, Google OAuth, SMTP, etc.) are stored in the MySQL `secrets` table via Admin → Integrations. Never expose secrets to customer-facing assistant context.
- Do not expose secrets or all users' data to customer-facing assistant context.
- When adding a new secrets field, update `SecretService.php` and the admin integrations form.

## Work Guidance

- Prefer schema-driven admin/resource changes over template-only field additions.
- Keep media records aligned with actual uploaded/static media paths.
- Keep `users`, `astrologers`, `appointments`, `consultation_messages`, and `consultation_signals` MySQL tables aligned when changing provider access or communication workflows.
- Avoid manual edits to lock files.

## Verification

- `bapXphp test`
- `bapXphp map:gen`
- `bapXphp map:val`

## Child DOX Index
