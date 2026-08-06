---
description: Pointer to the binding agent contract for this PHP/MySQL monorepo.
globs: *
alwaysApply: true
---

# Agent Operating Guide

**`CLAUDE.md` is the binding contract. Read it and follow it in full.**

This file exists so non-Claude tooling that looks for `AGENTS.md` still finds the
contract. It deliberately does not restate the rules — there is exactly one copy of
each, and it lives in `CLAUDE.md`.

Quick orientation:

- Skills: canonical project skills live in `.claude/skills/<name>/SKILL.md`
- Hooks: `.claude/hooks/`
- Schema (canonical): `storage/schema/collections.php`
- UI (canonical): `Design.md`
- Wiring: `docs/systematic-map.mmd`
- Local browser-test credentials: `.env.test-user` (gitignored; never copy its values into tracked files or logs)

Before any change: `./bapXphp map && ./bapXphp schema list`.
Before pushing to `main`: `./bapXphp ci`, and confirm it is green.

The product has exactly two PHP agent surfaces: customer support and owner/admin chat.

Inventory of what actually exists (check before claiming a feature):
`docs/project-index.json` — generated, committed, drift-checked by `./bapXphp ci`.

Queryable project knowledge: `index.yaml`. It points to the original blogs, images,
code, documentation, maps and hosted `/remotedb` collections; it does not duplicate
blog bodies or runtime database records. Read `discovery` first and query narrowly;
do not load the complete generated index into agent context. Use a three-hop budget:
entry instructions → exact index match → original source. Avoid broad `ls`, recursive
globs and Git history unless the indexed target is absent.

- Model facts, including the AI model in use, live in `CLAUDE.md`. Verify against a provider's own docs before saying a model or field does not exist.
