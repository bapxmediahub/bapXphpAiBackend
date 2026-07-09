# Agentic PHP/JSON Monorepo

This repo packages the backend and frontend together for small PHP hosting. The current public use case is Sri Panchami Spiritual, but the backend is reusable for other customer projects.

## Why MySQL

MySQL is the primary runtime data store. The schema is governed by `storage/schema/collections.php` and managed through `DatabaseService`. JSON files in `storage/data/` are used only for one-time seeding via `bapXphp db sync`. This keeps the database inspectable while using Hostinger's built-in MySQL for runtime persistence.

## Backend Primitives

- Auth and roles
- MySQL tables (via `DatabaseService`)
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

The authoritative sequence is maintained in root `AGENTS.md`. The generated map is a navigable index into repository sources, not a replacement for them.

For each change, the agent selects the affected map path and verifies the original route, controller, service, view, navigation link, schema definition, and storage collection before editing. It searches for existing implementations before creating files and returns to the same source path during validation.

Agents should not need a separate MCP server or global skill install to understand this repo. The operating rules live with the code.

## NotebookLM Comparison

This workflow adopts the documented source-grounding pattern, not an undocumented claim about NotebookLM internals:

- NotebookLM notebooks contain a selected collection of sources, and chat answers use those sources. This repository selects source files through the DOX chain and the affected systematic-map path.
- NotebookLM citations take the reader back to source context. Here, Mermaid edges take the agent back to routes, controllers, services, views, schema, storage, tools, and navigation.
- NotebookLM mind maps are generated summaries of uploaded sources and Google warns that generated results can be inaccurate. Likewise, `docs/systematic-map.mmd` is derived context that must be regenerated and checked against primary files.
- NotebookLM source copies may need resynchronization after originals change. Here, regeneration plus byte-for-byte validation is the synchronization gate.

Official references: [NotebookLM chat and citations](https://support.google.com/notebooklm/answer/16179559?hl=en), [NotebookLM sources and synchronization](https://support.google.com/notebooklm/answer/16215270?hl=en), and [NotebookLM mind maps](https://support.google.com/notebooklm/answer/16212283?hl=en).

## Relation To Agent-Native Backend Platforms

Agent-native backend platforms expose database, auth, storage, deployments, logs, and model access as inspectable primitives. This repo follows the same idea for smaller PHP hosting, but keeps the primitives inside the monorepo:

- Database: MySQL tables and schema contracts
- Auth: PHP services with admin credentials in settings (Admin → Settings) and API secrets in encrypted store (Admin → Integrations)
- Storage: local media library
- Deployment: Hostinger Git auto-deploy
- Model context: `AgentContextService`
- Logs/audit: MySQL `audit_events` table and admin pages
