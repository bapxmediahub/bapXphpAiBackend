---
type: doc
title: Agentic Monorepo
description: This repo packages the backend and frontend together for small PHP hosting. The current public use case is Sri Panchami Spiritual.
category: docs
---
# Agentic PHP/MySQL Monorepo

This repo packages the backend and frontend together for small PHP hosting. The current public use case is Sri Panchami Spiritual, but the backend is reusable for other customer projects.

## Data Architecture

Remote MySQL is the only runtime data store. All dynamic data, including users, products, consultants, orders, appointments, reviews, settings, and secrets, lives in MySQL tables and is accessed through `DatabaseService`.

File-based storage is used only for:
- Blog posts: `content/blog/posts/*.md` with YAML frontmatter
- Blog categories: `content/blog/categories.yaml`
- Media metadata: `storage/media.yaml`
- One-time seeding: JSON files in `storage/data/` synced to MySQL via `bapXphp db sync`

## Backend Primitives

- Auth and roles
- MySQL-backed collections via DatabaseService
- Schema registry (collections.php)
- Admin CRUD
- Media uploads and picker
- Environment editor
- Storage permission checker
- Audit log
- Orders, addresses, reviews, and mail queue
- Support assistant context
- Git-based deployment

## Agent Instructions

The authoritative sequence is maintained in root `AGENTS.md`. The generated map is a navigable index into repository sources, not a replacement for them.

For each change, the agent selects the affected map path and verifies the original route, controller, service, view, navigation link, schema definition, and storage collection before editing. It searches for existing implementations before creating files and returns to the same source path during validation.

Agents should not need a separate MCP server or global skill install to understand this repo. The operating rules live with the code.

## NotebookLM Comparison

This workflow adopts the documented source-grounding pattern, not an undocumented claim about NotebookLM internals:

- NotebookLM notebooks contain a selected collection of sources, and chat answers use those sources. This repository selects source files through the root contract and the affected systematic-map path.
- NotebookLM citations take the reader back to source context. Here, Mermaid edges take the agent back to routes, controllers, services, views, schema, storage, tools, and navigation.
- NotebookLM mind maps are generated summaries of uploaded sources and Google warns that generated results can be inaccurate. Likewise, `docs/systematic-map.mmd` is derived context that must be regenerated and checked against primary files.
- NotebookLM source copies may need resynchronization after originals change. Here, regeneration plus byte-for-byte validation is the synchronization gate.

Official references: [NotebookLM chat and citations](https://support.google.com/notebooklm/answer/16179559?hl=en), [NotebookLM sources and synchronization](https://support.google.com/notebooklm/answer/16215270?hl=en), and [NotebookLM mind maps](https://support.google.com/notebooklm/answer/16212283?hl=en).

## Relation To Agent-Native Backend Platforms

Agent-native backend platforms expose database, auth, storage, deployments, logs, and model access as inspectable primitives. This repo follows the same idea for smaller PHP hosting, but keeps the primitives inside the monorepo:

- Database: MySQL tables via DatabaseService; JSON for one-time seed data
- Auth: PHP services with admin credentials in settings (Admin → Settings) and API secrets in encrypted store (Admin → Integrations)
- Storage: local media library
- Deployment: Hostinger Git auto-deploy
- Model context: `AgentContextService`
- Logs/audit: MySQL audit events via AuditLogService and admin pages
