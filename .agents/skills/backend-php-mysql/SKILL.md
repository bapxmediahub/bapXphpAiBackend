---
name: backend-php-mysql
description: Use when editing PHP controllers, services, hosted MySQL persistence, authentication, agent context, orders, reviews, media metadata, payments, or audit behavior.
---
# PHP and MySQL Backend

- Follow the root `AGENTS.md` repository contract.
- Keep route -> controller -> service -> MySQL boundaries through `DatabaseService`; extend existing services instead of creating parallel stores.
- Hosted MySQL remains the only runtime store for **business records** (products, users, orders, tickets, media metadata, settings and secrets). Use direct MySQL or `<APP_URL>/remotedb` for approved business operations; never create local data mirrors.
- **Explicit exception:** Support/Enterprise chat records use one private JSON file per conversation in `storage/agent-conversations/`. Do not create a chat SQL table or use `/remotedb` for conversation turns. `AgentConversationService` owns atomic writes, actor checks and explicit feedback; `AgentTraceService` stores sanitized source/tool/provider/eval spans inside each turn.
- `storage/schema/collections.php` declares collection shapes. `bapXphp db init` creates tables. `db sync` is only a compatibility alias for initialization; it does not import or export JSON/YAML.
- Blog bodies remain Markdown with YAML frontmatter in `content/blog/posts/`. Image binaries remain in `assets/images/` or writable upload storage.
- `MediaService` writes metadata to hosted MySQL `media_files`. Legacy YAML is read-only compatibility input. New uploaded binaries live in `.sps-media` beside the checkout, served by the constrained `/assets/images/media/…` handler in `index.php`; deployments must preserve that sibling directory.
- Filter customer/support retrieval through `AgentContextService` and `SupportToolRegistry`, with signed-in owner queries through `EnterpriseToolRegistry` / `AgentToolRegistry`. Authorization lives in `AgentCapabilityPolicy`, never in an LLM prompt or browser-selected role.
- Tool evidence may retain only sanitized arguments (e.g. validated date range, status, collection and result count), never raw customer email, private order ID, coupon code, credentials, raw SQL, complete tool results or hidden model reasoning. Local eval checks are not proof that arbitrary outputs are factual.
- Public consultation booking is retired. `ConsultationService`, `consultation_messages`, and `consultation_signals` are retained legacy code; historical appointments remain owner-accessible. Do not restore public booking, messaging, calls, or WebRTC without an explicit product change.
- When direct MySQL is unavailable, use the collection-allowlisted `DatabaseService` protocol at lowercase `/remotedb`. Authenticate with the configured database password; `RemoteDbController::requirePassword` now **fails closed** on an empty password (503) and rejects invalid credentials (401). Never send raw SQL from an agent, expose credentials, or route chat files through the DB bridge.
- Keep payment clients as small `integrations/` wrappers, source secrets through `SecretService` or system environment variables, and verify signatures before mutating orders or wallet balances.
- Use `bapXphp read blog <slug>` and `bapXphp write blog [slug]` for blog operations. Product and other admin-editable content must be read and written through hosted MySQL-backed application flows.

## Testing

Run `php -l` on every changed PHP file and then `./bapXphp test`. For storage changes, exercise the affected admin page against the remote database. Confirm transport, non-2xx, and invalid-response failures surface visibly; repeated reads are memoized within a request; and mutations invalidate cached reads.

## Keeping this skill current

Update when a service boundary, remote mutation protocol, consultation product policy, or runtime store changes. Read `storage/schema/collections.php` first because it is canonical.

## Agent and support persistence checks

For agent changes run `php cli/agent-eval.php` and the PHP
regressions. For chat filesystem changes also run
`php tests/agent-concurrency.php`, ensure only one JSON file per
chat and verify owner/guest isolation and CSRF-protected support feedback.
Keep metadata-only `audit_events` separate from sensitive chat
history. Before release verify Hostinger preserves the ignored JSON files.

## Execution evidence contract

For both agent surfaces, prepare the actor-owned private JSON request before
executing tools/providers. Persist actual span boundaries through
`AgentTraceService`; never backfill timestamps or call a produced response
delivered. Use effective result scope instead of requested filter arguments.
Unfinished requests are visible in Agent Monitoring and must not be blindly
replayed. Keep provider tokens/usage unavailable when absent, and distinguish
14-case offline regression scores from real customer satisfaction/accuracy.

## Support write consistency

Agent ticket creation is a controller business action, never a model-supplied
SQL/file tool. Preserve deterministic action identities and insert-if-absent
receipts. Staff transitions run through `updateSupportTicket` on the database
host with a row lock and expected version; do not restore read-all/upsert reply
or close logic. Test against the disposable CI database and do not infer email
delivery from a saved reply. Conversation history remains one private JSON file.
