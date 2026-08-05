# CLAUDE.md

**`AGENTS.md` is the binding contract for this repository. Read it first and follow it in full.**

This file exists only so Claude Code loads the contract automatically. It intentionally
does **not** restate the rules — `AGENTS.md` says stale duplication must be deleted rather
than preserved, so there is exactly one copy of every rule and it lives there.

## Before any code change

Per the `AGENTS.md` Work Order and the ZERO-CODE INITIATION rule:

```bash
./bapXphp map && ./bapXphp schema list
```

Then read `docs/systematic-map.mmd`, `storage/schema/collections.php`, and `Design.md`
for UI work.

## Environment notes for this checkout

- **Runtime store is remote MySQL only.** There is no local MySQL and none is expected.
  `storage/data/` JSON is one-time import material, not a runtime store.
- **Remote endpoint is `/remotedb` (all lowercase).** `config/database.php` builds the URL
  as `APP_URL . '/remoteDB'`, which 404s against the live host. `DatabaseService::remoteCall()`
  swallows non-200 responses and returns `[]`, so the failure is silent and the site simply
  renders empty. Set `BAPX_REMOTE_DB_URL` explicitly in `.env` to work around it.
- **`.env` holds only** `APP_NAME`, `APP_URL`, the `BAPX_MYSQL_*` connection values, and
  `BAPX_REMOTE_DB_URL`. Every other secret belongs in the MySQL `secrets` table via
  Admin → Integrations, per `AGENTS.md`.

## Testing

This is a web app and a PWA. **Verify in a browser, not in the terminal.** Terminal checks
are for generators and schema only. Browser-test both desktop and mobile viewports —
`Design.md` is canonical for responsive behavior.

Local server used for verification:

```bash
php -S 127.0.0.1:8811 index.php
```

## Do not

- Add `.claude/` or any other duplicated role folder — `.agents/workflows/` and
  `.agents/handoffs/` are canonical.
- Log in through `/login` with a plaintext admin password while pointed at the live
  database. `AuthController::loginPost()` re-saves credentials through
  `EnvService::saveAdminCredentials()`, which reads `SettingsService::public()` —
  currently the wrong `settings` row — and will overwrite live GST configuration.
