---
title: Contributor Guide
description: Guide for contributors including roles, workflow, handoff system, CLI, and documentation standards.
category: root
---

# Contributor Guide

## Roles

| Role | Responsibilities | Documents |
|------|-----------------|-----------|
| **CEO** | Repository architect. Owns skills, orchestration, telemetry design. Never implements features. | `AGENTS.md`, `.agents/skills/subagent-orchestration/SKILL.md` |
| **CTO** | Issue routing, handoff chain management, merge approval, loop closure. | `.agents/workflows/cto-workflow.md` |
| **Worker** | Implementation. Receives objectives from CTO, produces evidence. | `.agents/workflows/worker.md` |
| **Reviewer** | Verifies evidence, checks tests, finds gaps. | `.agents/workflows/reviewer.md` |
| **Browser Tester** | Verifies UI changes via browser testing. | `.agents/workflows/browser-tester.md` |

## Handoff Chain

```
Issue → CTO → Worker → Reviewer → CTO (close loop) → Merge
```

Each role advances the chain by commenting `/handoff <role>` on the GitHub issue.

## CLI Commands

### Essential
```bash
bapXphp map && bapXphp schema list    # Mandatory pre-flight before changes
bapXphp ci                             # Full validation (lint → test → maps → smoke)
bapXphp update                         # Regenerate and validate maps
```

### Handoff
```bash
bapXphp handoff next <issue>           # Show next role + objective
bapXphp handoff template <issue>       # Generate empty handoff template
bapXphp handoff validate <file>        # Validate handoff JSON
bapXphp handoff comment <file> <pr>    # Post handoff on PR
bapXphp handoff execute <issue>        # Emit handoff context
bapXphp handoff score <issue>          # Score the cycle
```

### File Operations (use these instead of raw shell)
```bash
bapXphp read file <path>
bapXphp write file <path>              # Reads stdin
bapXphp edit <path> <search> <replace>
bapXphp grep <pattern> [path]
bapXphp find <glob>
bapXphp run <command...>
```

### Database
```bash
bapXphp db query <collection> [--where 'f=v'] [--limit N]
bapXphp db upsert <collection> '<json>'
bapXphp db delete <collection> <id>
bapXphp db init                        # Create tables from schema
```

## Documentation Standards

1. Every `.md` file must have YAML frontmatter (`title`, `description`, `category`)
2. `docs/` files use these categories: `docs`, `module`, `page`, `role`
3. Page docs go in `docs/pages/`, module docs in `docs/modules/`, role docs in `docs/roles/`
4. Implementation without documentation reconciliation is incomplete
5. After meaningful edits, update all affected durable page/module/role documents

## Pre-Flight Checklist (Worker)

1. ✅ Run `bapXphp map` AND `bapXphp schema list`
2. ✅ Read `AGENTS.md`
3. ✅ Read `docs/systematic-map.mmd` for route/controller/service wiring
4. ✅ Search existing issues
5. ✅ Read matching `.agents/skills/<skill>/SKILL.md`
6. ✅ Read `storage/schema/collections.php`
7. ✅ Inspect existing implementations before creating files

## Pre-Merge Checklist (CTO)

1. ✅ All objectives have evidence (handoff validated)
2. ✅ `bapXphp ci` passes (lint → test → map val → docs val → smoke)
3. ✅ Both maps regenerated and validated
4. ✅ Affected docs updated
5. ✅ No stale generated maps
6. ✅ PR description references issue number
7. ✅ Fork sync verified (if applicable)

## Git Workflow

```bash
git checkout -b <scope>/<description>   # Branch from main
# ... make changes ...
bapXphp update                           # Regenerate maps
bapXphp ci                               # Validate everything
bapXphp pr                               # Create PR (runs CI first)
# After CTO review:
bapXphp merge                            # Merge to main
```
