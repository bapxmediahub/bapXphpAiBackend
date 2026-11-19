---
type: doc
title: Agentic Monorepo
description: This repo packages the backend and frontend together for small PHP hosting. The current public use case is Sri Panchami Spiritual.
category: docs
---
# Agentic PHP/MySQL Monorepo

This repo packages the backend and frontend together for small PHP hosting. The current public use case is Sri Panchami Spiritual, but the backend is reusable for other customer projects.

## Data Architecture

Remote MySQL remains the single source of truth for **business records**:
users, products, appointments, orders, reviews, tickets, settings, secrets,
media metadata and operational audit events. It is accessed through
`DatabaseService`. The **separate and deliberate** chat-history exception is
one private JSON document per Support or Enterprise Agent conversation in
`storage/agent-conversations/` through `AgentConversationService`; there is no
conversation SQL collection.

Outside the MySQL business collections, maintained file resources include:
- Blog posts: `content/blog/posts/*.md` with YAML frontmatter
- Blog categories use article frontmatter and the standard labels in `BlogService::categories()`
- Media binaries: bundled public assets and persistent uploaded files; uploaded media metadata is in MySQL `media_files`
- Private Support and Enterprise chat sessions: one `storage/agent-conversations/<id>.json` per chat
- Offline agent eval experiment summaries (explicit opt-in): `storage/agent-evals/`
- One-time initialization fixtures: `storage/data/*.json` (`bapXphp db sync` aliases DB initialization; not a runtime data sync)

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

The Support Agent is scoped to public products/policies and the authenticated
customer's own approved ticket lookup; Enterprise read-only tools are separately
allowlisted. Customer feedback on a support reply is written back into the
same JSON chat as `confirmed_resolved` or `needs_followup` only when the
customer explicitly votes. A new question resets the current resolution state.
Unrated responses are not counted as successful resolutions.

Each JSON turn includes a correlated execution trace with observed retrieval
sources/result counts, validated tool filter fields, sanitized provider
attempts, deterministic checks and response metadata. The spans share a root
trace ID; because providers do not expose full wall-clock span boundaries,
the record explicitly marks grouped-stage order, not a fabricated timeline.
No raw SQL, private selectors, API keys or model reasoning appear in operational
traces. `php cli/agent-eval.php` runs the committed golden fixtures without
calling an LLM; baseline SHA-256 and fixture SHA-256 support reproducible
comparisons, and CI fails on a regression. These guardrails must never be
reported as production answer accuracy.

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
