---
name: bapxphp-cli
description: Use when running or changing the repository-owned bapXphp CLI, including orientation, validation, maps, knowledge indexes, schema, database, logs, blogs, images, and development-user commands.
---

# bapXphp CLI

Treat `./bapXphp help` and the dispatch in `cli/bapXphp` as executable evidence.
Do not infer a command from old documentation. Normal coding tools and plain Git
remain valid; the CLI is for project-owned workflows, not a wrapper requirement for
every filesystem or Git operation.

## Orientation and query workflow

```bash
./bapXphp help
./bapXphp map
./bapXphp schema list
./bapXphp route:list
./bapXphp skills
./bapXphp status
./bapXphp understand
```

Read `index.yaml` discovery and query it narrowly. Use `docs/project-index.json` for
existence, `map.mmd` for wiring, original files for truth, and `/remotedb` for live
admin-editable values.

## Validation and generated artifacts

```bash
./bapXphp lint path/to/changed.php
./bapXphp test
./bapXphp update
./bapXphp ci
./bapXphp index
./bapXphp docsmap
./bapXphp codemap
```

`update` regenerates committed maps and indexes; `ci` checks drift. Never hand-edit
generated maps or `index.yaml`.

`lint` accepts one file or directory, or scans the normal source roots when the
path is omitted. An explicit path is forwarded to the handler; an invalid path,
PHP parser failure or directory traversal error must return nonzero. Directory
scans suppress ordinary successful parser lines while retaining all failed
filenames. A failed default scan must not print its success summary, and `ci`
must retain the lint failure while collecting the remaining gate results.

The local `ci` command also runs `php tests/storefront-search.php`, the same
fixture used by GitHub. It checks the native search route with mixed results,
pagination, visibility, Shop and Blog settings, malformed input and a module
settings outage. This test uses a synthetic loopback bridge. A nonzero exit
prints `STOREFRONT SEARCH FAILED` and makes CI fail after the other gates.

## Content, database and operations

```bash
./bapXphp read blog [slug]
./bapXphp write blog [slug]
./bapXphp blog:image <slug> <image> [options]
./bapXphp db query <collection> [filters]
./bapXphp db find <collection> <id>
./bapXphp db init
./bapXphp db status
./bapXphp logs [--limit N]
./bapXphp dev:user [--dry-run]
```

Blog commands operate on Markdown files. Product and other admin-editable values
must come from hosted MySQL. `db sync` currently performs initialization only; do
not describe it as JSON/YAML import or export.

## Scope

There is no `bapXphp tui` or application-agent CLI. The two application agents are
the customer support and owner/admin web surfaces. User attachments and web QA use
the active coding client's explicit tools and paths, outside this application CLI.

## Testing

For a CLI change, compare `./bapXphp help` with the dispatch and target file, execute
the smallest safe command, then run `./bapXphp ci`. Never print environment files,
credentials, secret values or customer records.

For lint/CI changes, run `node --test tests/cli-lint.test.cjs` with native PHP.
It copies the actual CLI launchers into a temporary miniature project and uses
real parser failures, paths with spaces and multiple malformed files. It also
checks that a failing storefront search stage fails CI while later stages run.
The miniature project uses valid PHP stand-ins for CI stages outside these assertions. The application
checkout and business data are untouched. A PHP wrapper that reports syntax
errors but exits zero cannot establish parser-status acceptance; the fixture
fails explicitly in that environment rather than skipping the check.

## Keeping this skill current

Update this skill when help, dispatch, an implementation file, or the binding
contract changes. A help entry without an implementation is a gap, not a feature.

## Agent offline test runner

`php cli/agent-eval.php` verifies the versioned golden JSON
fixtures against their committed baseline with no provider calls or customer
data. `--json` prints a machine-readable comparison;
`--write` creates an optional ignored experiment JSON in
`storage/agent-evals/`. GitHub CI runs the same deterministic gate.
Fixture changes and baseline changes must be reviewed together with evidence.

## Read-only Google product channel

`merchant:check [--json|--schema]` and `merchant:feed` delegate to
MerchantFeedService, the same projection used by the owner channel page and
public XML. Check uses the operation/schema/read-only JSON envelope; feed emits
only allowlisted public product XML. Do not interpret success as Google
approval or YouTube eligibility. There is no Google upload, DB migration,
credential mutation or MCP transport implied by these commands.

Follow the shared-domain pattern reviewed in WordPress's Abilities skill and
Shopify Shop CLI: business rules live once in a PHP service, interfaces expose
that same operation. A new CLI/tool output needs a schema, truthful errors and
source-doc tests. Generic developer shell/SQL/file commands are never eligible
for automatic exposure to runtime agents or MCP clients.

`./bapXphp write product [slug]` uses the shared catalogue save on direct MySQL. Omit the slug for a create and supply the current slug for an edit. Blank edit prompts preserve existing fields. A duplicate create or conflicting rename exits with an error. EOF before completing the prompts cancels the write.


## Public MCP capabilities

Inspect the active public tool schemas with `./bapXphp mcp:tools`. Use `./bapXphp mcp:policy tracking` for published tracking guidance. Both commands use the same guest capabilities as `mcp:stdio`. See `docs/pages/shop.md` for supported inputs, results and the local client trust boundary.

A grant issued under Admin → Integrations → Manage local MCP clients adds only `paid_sales_summary` to trusted STDIO discovery. Inject `BAPX_MCP_CLIENT_TOKEN` through the launcher’s secret manager. The public convenience commands stay guest-scoped. The owner grant, bounds and per-request authorization checks are exercised against disposable MySQL by `node --test tests/mcp-client-access.test.cjs`.
