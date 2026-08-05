# CLAUDE.md

Binding contract for coding agents in this repository. `AGENTS.md` carries the same
contract for non-Claude tooling; if the two ever disagree, this file wins and
`AGENTS.md` must be corrected in the same change.

## Repository

- `bapxmediahub/bapXphpAiBackend` is the only working repository and the Hostinger
  deployment source. Issues, branches, PRs and releases all live there.
- The repository is independent and unforked. Do not add an upstream remote or
  synchronise from any other copy.
- Skills live in `.claude/skills/`. Hooks live in `.claude/hooks/`.
- There is no handoff chain, no role agents and no subagent orchestration. Those were
  removed — do not reintroduce them.

## Work order

1. `./bapXphp map` and `./bapXphp schema list` before proposing any change.
2. Read this file.
3. Read `docs/systematic-map.mmd` for route → controller → service wiring.
4. Search existing GitHub issues, then open an evidence-backed one (reproduction,
   affected paths, pinpointed cause, acceptance checks). Skip this for read-only
   diagnosis or when the user declines tracking.
5. Read the matching `.claude/skills/<name>/SKILL.md`.
6. Read `storage/schema/collections.php` for schema and `Design.md` for UI.
7. Inspect existing implementations before creating any file, route, service, view,
   collection or navigation item.

## Architecture

- **Frontend:** PHP templates in `views/`, following `Design.md`. No SPA, no build step.
- **Backend:** controllers and services in `app/`. Route → controller → service →
  MySQL via `DatabaseService`.
- **Schema:** `storage/schema/collections.php` is canonical. Update it before changing
  any collection shape.
- **Runtime store:** remote MySQL only. `storage/data/` JSON is one-time import
  material. Blog posts are Markdown with YAML frontmatter in `content/blog/posts/`;
  images live in storage. Everything else is in MySQL.
- **Agents:** exactly two, both pure PHP with no browser or external runtime
  dependency — a customer support agent and an admin agent. Admin agent context must
  go through `AgentContextService`; never expose all users' data.

## Environment

- The remote endpoint is `/remotedb`, all lowercase. `DatabaseService::remoteCall()`
  returns `[]` on any non-200, so a wrong URL renders an empty site with no error.
- `.env` holds only `APP_NAME`, `APP_URL`, the `BAPX_MYSQL_*` values and
  `BAPX_REMOTE_DB_URL`. Every other secret belongs in the MySQL `secrets` table via
  Admin → Integrations.

## Testing

This is a web app and a PWA. **Verify in a browser, not the terminal** — desktop and
375px mobile. Terminal checks cover generators, schema and tests only.

```bash
php -S 127.0.0.1:8811 index.php    # local server
./bapXphp ci                        # lint, tests, maps, smoke
```

Check computed styles rather than trusting a screenshot: an author rule like
`display:flex` silently overrides `[hidden]` and breaks JS filters with no error.

## Rules

- Extend existing controllers, services and views. No parallel implementations.
- Customer-facing UI follows `Design.md`.
- Admin mutations are auditable via `AuditLogService`, which must never break the
  operation it records.
- Secrets are never committed and never placed in `.env`.
- Before pushing to `main`: run `./bapXphp ci` and confirm it is green. `main` deploys
  to production, so a red build can still ship.
- Branch, push, open a PR. Never commit directly to `main`.
