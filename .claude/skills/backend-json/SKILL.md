---
name: backend-json
description: Use when editing PHP controllers, services, MySQL persistence, auth, support context, wallet, orders, reviews, media, or audit behavior.
---
# PHP and MySQL Backend

- Follow the root `AGENTS.md` repository contract.
- Keep route -> controller -> service -> MySQL-store boundaries via `DatabaseService`.
- Hosted MySQL is the only runtime store for admin-editable records. Local development uses direct MySQL or the configured `<APP_URL>/remotedb` fallback; do not create local product, category, consultant, order, user, setting, or secret copies.
- `storage/schema/collections.php` declares collection shapes. `bapXphp db init` creates tables. `db sync` is currently only a compatibility alias for initialization and does not import or export JSON/YAML data.
- Blog bodies remain Markdown with YAML frontmatter in `content/blog/posts/`; images remain files in `assets/images/` or writable upload storage.
- `media_files` is declared in the schema, but `MediaService` currently persists its catalogue in YAML. Treat this as a known unwired boundary and do not claim it is MySQL-backed until the service is migrated.
- Use `DatabaseService`, `ResourceService`, and existing services instead of ad hoc storage writes.
- Keep assistant/customer context filtered through `AgentContextService` or equivalent user-specific filtering.
- Implement consultation messaging and WebRTC signaling through authenticated PHP JSON APIs and `ConsultationService`; do not introduce a persistent WebSocket or CLI service.
- When direct MySQL is unavailable, use the collection-allowlisted `DatabaseService` protocol at lowercase `/remotedb`. Configure its credential through Admin → Integrations/MySQL secrets; do not document credentials in tracked files. Authentication hardening remains required while an empty remote password is accepted, so never expose arbitrary SQL or secret records.
- For shared-hosting payment integrations, keep gateway clients as small `integrations/` wrappers, source secrets through `SecretService` (MySQL-backed) or system env vars, and verify signatures server-side before mutating orders or wallet balances.
- Validate changed PHP with `php -l`, then run `bapXphp test`.
- Use `bapXphp read blog <slug>` / `bapXphp write blog [slug]` for all blog operations.
- Use `bapXphp read product <slug>` / `bapXphp write product [slug]` for all product operations.

## Testing

php -l on every changed file, then `./bapXphp test`. For storage changes, exercise the affected admin page in a browser against the remote DB. Confirm failed transport and non-2xx responses surface visibly, repeated collection reads are memoized within the request, and mutations invalidate cached reads.

## Keeping this skill current

Update this skill when a service boundary, the remote mutation protocol, or the set of runtime stores changes. Re-read `storage/schema/collections.php` first — it is canonical.
