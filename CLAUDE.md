---
description: Claude instructions for agents working on this PHP/JSON full-stack monorepo.
globs: *
alwaysApply: true
---

# Claude Operating Guide

Read `AGENTS.md` first. This file exists so Claude-compatible agents enter the same workflow as Codex, OpenCode, and other repo-aware agents.

Use the matching skill under `.claude/skills/<skill-name>/SKILL.md`, then follow the canonical implementation guidance in `.codex/skills/<skill-name>/SKILL.md`.

## Required Habits

- Keep the backend framework reusable across projects: PHP controllers/services, JSON storage, schema, admin tools, and PHP templates.
- Do not introduce a SPA fallback, second frontend, SQL migration, or direct all-user JSON access unless the user explicitly asks for that architecture change.
- When a code change creates a reusable rule, update the matching skill so future agents inherit it.
- Validate changed PHP, run the project checks, and inspect changed UI like a user in the browser.
- Before finishing, search the changed workflow for placeholders, dead buttons, stale labels, duplicate fallbacks, and incomplete wiring.
