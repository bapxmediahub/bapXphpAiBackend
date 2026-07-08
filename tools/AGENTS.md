# Tools DOX

## Purpose

Owns maintenance scripts, project-map generation/validation, local smoke checks, mail queue tooling, and the bapXphp project management CLI.

## Ownership

- `generate-project-map.php`: writes `docs/systematic-map.mmd`, including shared navigation-to-route relationships.
- `generate-docs-map.php`: writes `docs/KnowledgeMap.mmd` (documentation mindmap).
- `validate-project-map.php`: verifies the committed systematic map is fresh.
- `refresh-blog-cache.php`: fetches and caches GitHub-sourced blog/documents.
- `smoke-local.php`: starts a disposable local PHP server and checks key routes/API behavior.
- **`bapXphp`**: the primary project management CLI (bash). Entry point for agents and developers.
- Other scripts must have one clear concern.

## Local Contracts

- One tool per concern. Extend an existing tool when it already owns the workflow.
- The project map has one artifact: `docs/systematic-map.mmd`. `docs/KnowledgeMap.mmd` is a separate generated documentation mindmap, not a parallel project map.
- Tool output should be deterministic enough for CI and agent verification.
- **bapXphp** is the agent's first command. Run `bash tools/bapXphp help` or `bash tools/bapXphp understand` on session start.
- bapXphp db commands are MySQL-only (no JSON fallback). Exit with error if MySQL unreachable.
- `bapXphp docsmap` runs `tools/generate-docs-map.php` to regenerate `docs/KnowledgeMap.mmd`.
- `bapXphp bloggen` runs `tools/refresh-blog-cache.php` to refresh GitHub-sourced blog cache.

## bapXphp — Agent Quick Start

```bash
bash tools/bapXphp understand     # full project overview
bash tools/bapXphp context        # quick session orientation
bash tools/bapXphp db query products --limit 5   # query data (MySQL only)
bash tools/bapXphp db find orders ord_123        # find record (MySQL only)
bash tools/bapXphp docsmap        # regenerate KnowledgeMap.mmd
bash tools/bapXphp bloggen        # refresh blog cache from GitHub
bash tools/bapXphp check          # full validation chain
```

## SSH/MySQL Production Credentials

Connect to production for deploy, SQL queries, or SSH commands:

- **SSH**: `ssh -p 65002 u907253411@82.25.106.244` password `SPsprituals2026#`
- **MySQL tunnel**: `bapXphp db tunnel` → then query at `127.0.0.1:3307`
- **MySQL direct**: `srv1877.hstgr.io` (firewalled — use tunnel)
- **DB name**: `u907253411_db_name_sps`
- **DB user**: `u907253411_db_user_sps` / pass `SPsprituals2026#`
- **Git remote**: `origin` → `main` branch; already authenticated on production server
- **Production path**: `~/domains/sripanchamispiritual.com`
- **Deploy**: `bapXphp deploy` (git push + remote pull)

## Work Guidance

- Keep tools runnable from the repo root with `php tools/name.php` or `bash tools/bapXphp`.
- Do not bake customer-specific remote production URLs into local tools (exception: bapXphp remote config).
- Smoke checks should verify real routes in this repo, not copied routes from another project.
- `bapXphp` config is read from env vars (`BAPX_SSH_HOST`, `BAPX_MYSQL_DB`, etc.) — set them in `.env` or shell profile.

## Verification

- `php -l tools/changed-tool.php`
- `bash tools/bapXphp help` (verify all commands listed)
- `php tools/generate-project-map.php`
- `php tools/validate-project-map.php`
- `php tools/smoke-local.php`

## Child DOX Index
