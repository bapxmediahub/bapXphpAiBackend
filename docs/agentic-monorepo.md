# Agentic PHP/JSON Monorepo

This repo packages the backend and frontend together for small PHP hosting. The current public use case is Sri Panchami Spiritual, but the backend is reusable for other customer projects.

## Why JSON

JSON storage is intentional. It keeps the database readable by humans and coding agents, avoids hidden SQL schema state, and works on shared PHP hosting without a database server. The JSON files are not random data dumps; they are governed by:

- `storage/schema/collections.json`
- `JsonStoreService` atomic writes
- admin CRUD forms
- audit logging
- media library records
- project-map documentation
- agent-facing skills in the repo

## Backend Primitives

- Auth and roles
- JSON collections
- Schema registry
- Admin CRUD
- Media uploads and picker
- Environment editor
- Storage permission checker
- Audit log
- Orders, wallet, reviews, mail queue
- Support assistant context
- Git-based deployment

## Agent Instructions

Compatible agents should read:

1. `AGENTS.md`
2. `storage/schema/collections.json`
3. `docs/PROJECT_MAP.md`
4. `.codex/skills/sps-dev/SKILL.md` or the equivalent `.claude/` / `.agents/` path

Agents should not need a separate MCP server or global skill install to understand this repo. The operating rules live with the code.

## Relation To Agent-Native Backend Platforms

Agent-native backend platforms expose database, auth, storage, deployments, logs, and model access as inspectable primitives. This repo follows the same idea for smaller PHP hosting, but keeps the primitives inside the monorepo:

- Database: JSON collections and schema files
- Auth: PHP services and `.env` config
- Storage: local media library
- Deployment: Hostinger Git auto-deploy
- Model context: `AgentContextService`
- Logs/audit: JSON audit events and admin pages
