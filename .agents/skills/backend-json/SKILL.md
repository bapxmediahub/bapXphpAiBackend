---
type: skill
name: backend-json
description: Use when editing PHP controllers, services, JSON persistence, auth, support assistant context, wallet, orders, reviews, media, or audit behavior.
---
# Backend JSON

- Follow the root `AGENTS.md` repository contract.
- Keep route -> controller -> service -> MySQL-store boundaries via `DatabaseService`.
- MySQL is the primary runtime store. `bapXphp db` CLI manages the DB (init, sync, query).
- JSON files in `storage/data/` are used only for one-time seeding via `bapXphp db sync`. Do not use `JsonStoreService` in runtime code.
- JSON files in `storage/data/` are used only for one-time seeding via `bapXphp db sync`. Do not use `JsonStoreService` in runtime code.
- Blog posts use YAML frontmatter in `content/blog/posts/`. Media metadata uses `storage/media.yaml`.
- Use `DatabaseService`, `ResourceService`, and existing services instead of ad hoc storage writes.
- Keep assistant/customer context filtered through `AgentContextService` or equivalent user-specific filtering.
- Implement consultation messaging and WebRTC signaling through authenticated PHP JSON APIs and `ConsultationService`; do not introduce a persistent WebSocket or CLI service.
- When direct MySQL is unavailable, remote writes must use the authenticated, collection-allowlisted `DatabaseService` mutation protocol. The `/remoteDB` endpoint is protected by `remote_db_password` (set via Admin → Integrations or `REMOTE_DB_PASSWORD` in `.env`). `DatabaseService` sends the password automatically in every remote payload. Never expose arbitrary write SQL or secret records through the remote endpoint.
- For shared-hosting payment integrations, keep gateway clients as small `integrations/` wrappers, source secrets through `SecretService` (MySQL-backed) or system env vars, and verify signatures server-side before mutating orders or wallet balances.
- Validate changed PHP with `php -l`, then run `bapXphp test`.
- Use `bapXphp read blog <slug>` / `bapXphp write blog [slug]` for all blog operations.
- Use `bapXphp read product <slug>` / `bapXphp write product [slug]` for all product operations.
