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

## Keeping this skill current

Update this skill when help, dispatch, an implementation file, or the binding
contract changes. A help entry without an implementation is a gap, not a feature.
