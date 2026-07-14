# CLI DOX

## Purpose

Owns the bapXphp project CLI entry point and all PHP helper scripts for project-map generation/validation, local smoke checks, mail queue processing, blog/product CRUD, and maintenance operations.

## Ownership

- **`bapXphp`**: the primary project management CLI (bash). Entry point for agents and developers.
- `generate-project-map.php`: writes `docs/systematic-map.mmd`, including shared navigation-to-route relationships.
- `generate-docs-map.php`: writes `docs/KnowledgeMap.mmd` (documentation mindmap).
- `validate-project-map.php`: verifies the committed systematic map is fresh.
- `validate-docs-map.php`: verifies the committed KnowledgeMap is fresh.
- `refresh-blog-cache.php`: fetches and caches GitHub-sourced blog/documents.
- `smoke-local.php`: starts a disposable local PHP server and checks key routes/API behavior.
- `blog-read.php` / `blog-write.php`: CLI blog post read and interactive create/edit tools.
- `blog-image.php`: center-crop a local screenshot/image to the shared 16:9 blog card/article WebP and update post frontmatter; supports `--dry-run`.
- `doc-read.php` / `doc-write.php`: CLI customer help guide read and interactive create/edit tools.
- `product-read.php` / `product-write.php`: CLI product read and interactive create/edit tools.
- `import-product-images.php`: idempotent ZIP/folder gallery import, image optimization, and MySQL product media updates.
- Other scripts must have one clear concern.

## Local Contracts

- One tool per concern. Extend an existing tool when it already owns the workflow.
- The project map has one artifact: `docs/systematic-map.mmd`. `docs/KnowledgeMap.mmd` is a separate generated documentation mindmap, not a parallel project map.
- Tool output should be deterministic enough for CI and agent verification.
- **bapXphp** is the agent's first command. Use it for ALL project operations. Never edit content files directly.
- bapXphp db commands operate on remote MySQL directly or through the authenticated remote DB protocol; never silently fall back to local runtime data.
- Prefer `bapXphp db hosted <sql>` for owner-authorized remote mutations when `.env.mysql` provides hosting and MySQL access; this path does not use an application mutation token.
- `bapXphp db sync` handles single-object JSON files (e.g. `secrets.json`, `settings.json`) by wrapping them in a single-record array.
- `bapXphp update` regenerates and validates both map artifacts; `bapXphp ci` validates without rewriting tracked files.
- `bapXphp bloggen` runs `cli/refresh-blog-cache.php` to refresh GitHub-sourced blog cache.
- Use `bapXphp read blog <slug>` and `bapXphp write blog [slug]` for all blog post operations.
- Use `bapXphp blog:image <slug> <screenshot-or-image> --dry-run` before attaching UI screenshots; the non-dry run writes one shared card/article image.
- Use `bapXphp read docs <slug>` and `bapXphp write docs [slug]` for customer help guide operations.
- When a project task is not safely operable through `bapXphp`, extend the closest existing CLI concern first. New commands must support non-interactive agent use, work from the repo root on shared hosting, avoid embedded credentials/customer URLs, and provide `--dry-run` for bulk mutations.
- Use `bapXphp product:images <archive.zip|folder> --dry-run` before importing product galleries; the importer orders front, back, then side images and updates both `image_url` and `image_urls`.
- `bapXphp logs` reads live remote MySQL `audit_events`; local development logs require the explicit `--local` flag.
- Use `bapXphp artifacts:clean --dry-run` before untracking `server.log`, `storage/logs/`, or `output/playwright/` artifacts.

## bapXphp — Agent Quick Start

```bash
bapXphp understand     # full project overview
bapXphp context        # quick session orientation
bapXphp db query products --limit 5   # query data (MySQL only)
bapXphp db find orders ord_123        # find record (MySQL only)
bapXphp update         # regenerate both maps after source/docs changes
bapXphp bloggen        # refresh blog cache from GitHub
bapXphp ci             # non-mutating PR/CI validation
```

## Work Guidance

- Keep tools runnable from the repo root with `bapXphp <command>`.
- Do not bake customer-specific remote production URLs into local tools.
- Smoke checks should verify real routes in this repo, not copied routes from another project.
- `bapXphp` config is read from env vars (`BAPX_MYSQL_HOST`, `BAPX_MYSQL_DB`, etc.) — set them in `.env` or shell profile. `.env` only holds APP_NAME, APP_URL, and optional BAPX_* env vars — never secrets.

## Verification

- `php -l cli/changed-tool.php`
- `bapXphp help`
- `bapXphp update`
- `bapXphp ci`
