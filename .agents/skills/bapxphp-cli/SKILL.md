---
type: skill
name: bapxphp-cli
description: Use bapXphp CLI for ALL file/content/db operations. Never use raw shell/edit/write/find/grep tools directly.
---
# bapXphp CLI

All project operations go through `bapXphp`. This ensures every operation is auditable, logged, and consistent.

## Generic File Operations

| Operation | CLI Command |
|-----------|-------------|
| Read file    | `bapXphp read file <path>` |
| Write file   | `bapXphp write file <path>` (pipe stdin) |
| Edit file    | `bapXphp edit <path> <search> <replace>` |
| Search code  | `bapXphp grep <pattern> [path]` |
| Find files   | `bapXphp find <glob-pattern>` |
| Run command  | `bapXphp run <command...>` |
| List dir     | `bapXphp run ls <path>` |

## Content CRUD

```bash
bapXphp read blog                    # list all blog posts
bapXphp read blog <slug>             # read a blog post
bapXphp write blog [slug]            # create or edit blog post (interactive)
bapXphp read docs [slug]             # list or read customer help guides
bapXphp write docs [slug]            # create or edit a customer help guide
bapXphp read product                  # list all products
bapXphp read product <slug>           # read a product
bapXphp write product [slug]          # create or edit product (interactive)
```

## Database

```bash
bapXphp db query products --limit 5   # query products
bapXphp db find orders ord_123        # find an order
bapXphp db raw "SELECT * FROM ..."    # raw SQL
bapXphp db init                       # create MySQL tables from schema
bapXphp db sync                       # push JSON → MySQL
```

## Project Management

```bash
bapXphp test                          # run tests
bapXphp update                        # regenerate both map artifacts after source/docs changes
bapXphp ci                            # non-mutating full PR/CI validation
bapXphp check                         # alias for bapXphp ci
bapXphp serve                         # start dev server
bapXphp map:gen                       # regenerate project map
bapXphp docsmap                       # regenerate docs/map.mmd (content)
bapXphp codemap                        # regenerate map.mmd (code graph)
bapXphp schema list                   # list all collections
bapXphp issue                         # create GitHub issue
bapXphp pr                            # create PR
bapXphp help                          # full reference

## Validation

Run the smallest useful validation for the change:

```bash
bapXphp lint path/to/changed.php
bapXphp update
bapXphp ci
```

## Telemetry & Ops

Handoffs and telemetry live under `.agents/`:

```bash
bapXphp handoff next <issue>         # load next objective
bapXphp handoff validate <file>      # validate handoff JSON
```

Cycle data is tracked in `.agents/ops/telemetry.json`.

## Attachments

When a coding agent attaches screenshots, images, or files, they go into `.agents/temp/`. This is the standard inbox for all user-provided attachments in this repo.

```bash
bapXphp run ls .agents/temp/         # list attachments
bapXphp read file .agents/temp/<file> # read an attachment
```

## Browser Agent (headless browser automation)

```bash
bapXphp browser-agent open <url>              # fetch page, YAML snapshot
bapXphp browser-agent click <selector>         # click element
bapXphp browser-agent fill <selector> <value>  # fill form input
bapXphp browser-agent submit [selector]        # submit form
bapXphp browser-agent snapshot                 # YAML page snapshot
bapXphp browser-agent smoke <url>              # health check
bapXphp browser-agent screenshot [file]        # YAML output
bapXphp browser-agent config set <k> <v>       # runtime config
bapXphp browser-agent log                      # audit trail
bapXphp browser-agent count <tag>              # count DOM tags
bapXphp browser-agent close                    # clean session
bapXphp browser-agent --pw <command>           # forward to Playwright
```

## Missing Commands

If a required operation is missing or unsafe, enhance the nearest existing `bapXphp` command before performing the operation. Never use raw bash/write/edit/find tools for operations that have a `bapXphp` equivalent.
