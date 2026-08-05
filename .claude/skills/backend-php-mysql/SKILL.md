---
name: backend-php-mysql
description: Use when editing PHP controllers, services, hosted MySQL persistence, authentication, agent context, orders, reviews, media metadata, payments, or audit behavior.
---
# PHP and MySQL Backend

- Follow the root `AGENTS.md` repository contract.
- Keep route -> controller -> service -> MySQL boundaries through `DatabaseService`; extend existing services instead of creating parallel stores.
- Hosted MySQL is the only runtime store for admin-editable records. Local development uses direct MySQL or the configured `<APP_URL>/remotedb` fallback. Never create local product, category, consultant, order, user, setting, media-metadata, or secret copies.
- `storage/schema/collections.php` declares collection shapes. `bapXphp db init` creates tables. `db sync` is only a compatibility alias for initialization; it does not import or export JSON/YAML.
- Blog bodies remain Markdown with YAML frontmatter in `content/blog/posts/`. Image binaries remain in `assets/images/` or writable upload storage.
- `media_files` is declared in the schema, but `MediaService` still persists its catalogue in YAML. Treat this as a known unwired boundary until the service is migrated.
- Filter customer/support context through `AgentContextService`. The admin agent currently assembles aggregates directly in `AdminController::agentAsk()`; keep its context allowlisted and remove personal customer data.
- Public consultations are scheduled appointments only. `ConsultationService`, `consultation_messages`, and `consultation_signals` are retained legacy code, not active product requirements. Do not expose messaging, calls, or WebRTC unless an explicit product change restores the complete authenticated workflow.
- When direct MySQL is unavailable, use the collection-allowlisted `DatabaseService` protocol at lowercase `/remotedb`. Configure its credential through Admin -> Integrations/MySQL secrets. Never expose arbitrary SQL, credentials, or secret records. An empty remote password currently fails open and remains a security gap.
- Keep payment clients as small `integrations/` wrappers, source secrets through `SecretService` or system environment variables, and verify signatures before mutating orders or wallet balances.
- Use `bapXphp read blog <slug>` and `bapXphp write blog [slug]` for blog operations. Product and other admin-editable content must be read and written through hosted MySQL-backed application flows.

## Testing

Run `php -l` on every changed PHP file and then `./bapXphp test`. For storage changes, exercise the affected admin page against the remote database. Confirm transport, non-2xx, and invalid-response failures surface visibly; repeated reads are memoized within a request; and mutations invalidate cached reads.

## Keeping this skill current

Update when a service boundary, remote mutation protocol, consultation product policy, or runtime store changes. Read `storage/schema/collections.php` first because it is canonical.
