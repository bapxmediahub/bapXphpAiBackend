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

- Skills: `.claude/skills/<name>/SKILL.md`
- Hooks: `.claude/hooks/`
- Schema (canonical): `storage/schema/collections.php`
- UI (canonical): `Design.md`
- Wiring: `docs/systematic-map.mmd`

Before any change: `./bapXphp map && ./bapXphp schema list`.
Before pushing to `main`: `./bapXphp ci`, and confirm it is green.

The handoff chain, role agents and subagent orchestration described in earlier
revisions have been removed. Do not reintroduce them.
