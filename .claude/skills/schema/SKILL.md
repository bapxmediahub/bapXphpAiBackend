---
name: schema
description: Use when changing MySQL database collections, fields, admin forms, media fields, or agent context payloads.
---
# Schema

- Follow the root `AGENTS.md` repository contract and its storage area rules.
- Update `storage/schema/collections.php` before changing collection shapes, admin fields, media fields, seed data, or agent-visible context.
- Keep schema fields aligned with MySQL tables, admin resource forms, and `AgentContextService`.
- Admin-editable runtime data and media metadata belong in hosted MySQL. Blogs remain
  Markdown and image binaries remain files; their queryable metadata and usage
  relationships belong in the generated index and, where editable, MySQL.
- Do not add local product/category/consultant seed catalogues. Use direct MySQL or `<APP_URL>/remotedb` for local testing.
- `media_files` is presently a declared but unwired collection because `MediaService` still uses YAML; preserve this as an explicit gap until the owning service is migrated.
- Provider access changes must align users, astrologers, appointments, consultation messages, and call-signaling collections.
- Run `./bapXphp update` after schema or storage changes so `index.yaml`, maps and project indexes stay queryable.
- Follow the affected map edges through services and pages, and distinguish runtime data from genuinely undeclared storage before adding files.
- Validate with `php tests/run.php`.

## Testing

`./bapXphp schema list` before and after, and confirm every persisted field is declared. Then `./bapXphp test`.

## Keeping this skill current

Update `storage/schema/collections.php` **before** changing any collection shape, admin field, or media field — it is canonical and the rest of the tree follows it.
