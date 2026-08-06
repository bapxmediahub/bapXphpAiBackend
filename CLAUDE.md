# CLAUDE.md

Binding contract for coding agents in this repository. `AGENTS.md` carries the same
contract for non-Claude tooling; if the two ever disagree, this file wins and
`AGENTS.md` must be corrected in the same change.

## Repository

- `bapxmediahub/bapXphpAiBackend` is the only working repository and the Hostinger
  deployment source. Issues, branches, PRs and releases all live there.
- The repository is independent and unforked. Do not add an upstream remote or
  synchronise from any other copy.
- Canonical project skills live in `.claude/skills/`. Do not create a duplicate
  `.agents/skills/` tree. Hooks live in `.claude/hooks/`.
- The product has exactly two PHP agent surfaces: customer support and owner/admin chat.

## Work order

### Navigation budget

For repository questions, use at most three discovery hops before answering:

1. Read this file and only the `discovery`, `query_examples`, and `summary` block at
   the top of `index.yaml`.
2. Narrow-search `index.yaml` or `docs/project-index.json` for the exact route,
   concept, filename, collection, class, or skill.
3. Open the returned original source and answer with its path/symbol.

Do not run broad directory listings, recursive globs, Git history, or read the whole
generated index when the indexed path answers the question. Broaden only when a
target is absent, and state that absence as a finding.

1. `./bapXphp map` and `./bapXphp schema list` before proposing any change.
2. Read this file.
3. **Check `docs/project-index.json` before claiming any feature exists.** It is the
   generated, committed inventory of every route, controller, service, view and
   collection. If something is not in it, it does not exist — add it rather than
   assuming it is there. Read `docs/systematic-map.mmd` for the wiring diagram.
   Use root `index.yaml` to query original project concepts and relationships,
   including blogs, images and hosted `/remotedb` collections. Read its `discovery`
   section first and query narrowly; never load the entire generated file into context.
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
- **Runtime store:** hosted MySQL only for admin-editable records. Do not create local
  product, category, consultant, order, user, setting, media-metadata or secret copies.
  Blog posts are Markdown with YAML frontmatter in `content/blog/posts/`; image
  binaries remain files. `MediaService` still has a local YAML catalogue: this is a
  known migration gap, not an approved second runtime store.
- **Agents:** exactly two pure-PHP application surfaces — customer support and
  owner/admin chat. Admin agent context must
  go through `AgentContextService`; never expose all users' data.

## Environment

- The remote endpoint is `/remotedb`, all lowercase. `DatabaseService::remoteCall()`
  must throw on transport, non-2xx and invalid-response failures; never convert a DB
  outage into an empty catalogue.
- `.env` holds only `APP_NAME`, `APP_URL`, the `BAPX_MYSQL_*` values and
  `BAPX_REMOTE_DB_URL`. Every other secret belongs in the MySQL `secrets` table via
  Admin → Integrations.

## Model facts you must not guess

Your training has a cutoff and this project uses models released after it. **Search the
web before asserting that a model, endpoint or API field does not exist.** Saying "that
model name looks wrong" without checking sends the owner chasing a configuration problem
that is not there.

- `gemma-4-31b-it` **is a real, current model** on the Gemini API, served from
  `https://generativelanguage.googleapis.com/v1beta/models`. It is the configured model
  for both agents. Proof:
  <https://ai.google.dev/gemma/docs/core/gemma_on_gemini_api> and
  <https://huggingface.co/google/gemma-4-31B-it>. It was wrongly called fake twice
  during development; the real defect was ours, in response parsing.
- It is a **reasoning model**. Its reply may contain the model thinking out loud, either
  as parts flagged `"thought": true` or as prose before the answer. Read the answer with
  `AiClient::answerFromParts()`; never take `parts[0]`, which is usually the reasoning.
  Reference: <https://github.com/google-gemini/cookbook/issues/1198>.
- `thinkingConfig.includeThoughts: false` is silently ignored by this model family, and
  `thinkingLevel` is rejected outright by this endpoint (HTTP 400, `Unknown name
  "thinkingLevel"`). Filter the reasoning out of the response instead of trying to
  switch it off.

The general rule: when a fact about an external service decides what you change, verify
it against that service's own documentation and record the URL beside the code.

## Testing

This is a web app and a PWA. **Verify in a browser, not the terminal** — desktop and
375px mobile. Terminal checks cover generators, schema and tests only.

Local browser-test credentials and the fixed customer test ID are stored in
`.env.test-user`. The file is gitignored; read it locally when authentication is
required, and never copy its credential values into tracked files, command output,
test reports or logs.

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
