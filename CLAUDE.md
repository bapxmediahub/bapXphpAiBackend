# Claude Code Guide

Read `AGENTS.md` first. This repo includes Claude-compatible project skills under `.claude/skills/sps-dev/`.

Use this project as a PHP/JSON full-stack monorepo:

- JSON collections are the database.
- `storage/schema/collections.json` is the database schema.
- PHP services are the backend primitives.
- Admin pages expose media, environment, permissions, audit, and CRUD surfaces.
- Frontend is PHP templates and can be changed per customer without rebuilding the backend.

Do not replace the architecture with SQL, a SPA, or external MCP requirements unless the user explicitly asks.
