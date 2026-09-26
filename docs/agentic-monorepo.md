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
- Media metadata: MySQL `media_files` collection (uploaded via Admin → Media)
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

## Monitoring and evaluation

Both PHP agent surfaces record `agent.run` events in hosted `audit_events` through
`AuditLogService`. Events contain the surface, bounded outcome and elapsed
milliseconds, with a system actor. They do not store questions, answers, customer
identifiers, credentials or model reasoning. The owner dashboard aggregates the
last 24 hours, separating model replies, fallbacks, sign-in guidance, drafts and
errors. No traffic and unavailable monitoring are shown explicitly.

Operational success is not answer quality. Before and after an agent change, use
the built-in Browser to ask a catalogue question, a delivery-policy question, a
signed-out personal-order question, a retired-booking question and an owner count
question. Compare answers and navigation actions with the current hosted data;
check that each request produces the expected monitoring outcome. A fallback is
not evidence that the provider worked, and a correct count must match the data.
Do not submit payments or publish drafts during these checks.

Turn confirmed failures into reproducible issue cases and regression checks.
Regenerate maps with `./bapXphp update`, run `./bapXphp ci`, review PR checks, and
repeat the affected Browser flows after deployment. Use the same representative
questions when changing prompts or models so regressions can be compared.

This approach follows [Anthropic's agent evaluation guidance](https://www.anthropic.com/engineering/demystifying-evals-for-ai-agents)
and [OpenAI's agent evaluation guidance](https://developers.openai.com/api/docs/guides/agent-evals):
combine operational traces with outcome checks and representative evaluation cases.
It does not add a hosted agent framework or a third agent surface.

## NotebookLM Comparison

This workflow adopts the documented source-grounding pattern, not an undocumented claim about NotebookLM internals:

- NotebookLM notebooks contain a selected collection of sources, and chat answers use those sources. This repository selects source files through the root contract and the affected systematic-map path.
- NotebookLM citations take the reader back to source context. Here, Mermaid edges take the agent back to routes, controllers, services, views, schema, storage, tools, and navigation.
- NotebookLM mind maps are generated summaries of uploaded sources and Google warns that generated results can be inaccurate. Likewise, `docs/systematic-map.mmd` is derived context that must be regenerated and checked against primary files.
- NotebookLM source copies may need resynchronization after originals change. Here, regeneration plus byte-for-byte validation is the synchronization gate.

Official references: [NotebookLM chat and citations](https://support.google.com/notebooklm/answer/16179559?hl=en), [NotebookLM sources and synchronization](https://support.google.com/notebooklm/answer/16215270?hl=en), and [NotebookLM mind maps](https://support.google.com/notebooklm/answer/16212283?hl=en).

## Relation To Agent-Native Backend Platforms

Agent-native backend platforms expose database, auth, storage, deployments, logs, and model access as inspectable primitives. This repo follows the same idea for smaller PHP hosting, but keeps the primitives inside the monorepo:

- Database: hosted MySQL tables via `DatabaseService`; no local runtime or seed catalogue
- Auth: PHP services with admin credentials in settings (Admin → Settings) and API secrets in encrypted store (Admin → Integrations)
- Storage: local media library
- Deployment: Hostinger Git auto-deploy
- Model context: `AgentContextService`
- Logs/audit: MySQL audit events via AuditLogService and admin pages
