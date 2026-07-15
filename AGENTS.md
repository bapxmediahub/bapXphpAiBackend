---
description: Repository instructions for agents working on this PHP/JSON full-stack monorepo.
globs: *
alwaysApply: true
---

# Agent Operating Guide

## Orchestration Model (Maya + Silent Sub-Agents)

**Maya is the only agent the user ever interacts with.** She presents as a single, capable assistant that handles support queries, admin tasks, and code work directly. Internally, Maya dispatches Worker (and optionally Reviewer) sub-agents for complex or multi-step tasks — but this is never visible to the user.

Workflow roles are defined in `.agents/workflows/<role>.md` — tool-agnostic definitions usable by OpenCode, Claude Code, Codex, or any coding agent. Handoff execution: `bapXphp handoff execute <issue> [--next role]` reads the handoff JSON and outputs context + workflow for the next role.

Each cycle follows exactly:

```
User query → Maya (assess, route)
  → Worker (single objective) → evidence
  → Maya (verify, incorporate into response, close)
```

### Roles
- **Maya** — the unified public-facing agent. Maya handles all user interaction (support, admin, chat). For complex work, she silently dispatches Worker sub-agents, then incorporates their results into her response as if she did the work herself. She never reveals the internal chain.
- **Worker** — internal sub-agent that Maya dispatches via the Task tool. Executes one objective, produces evidence, and reports back to Maya. Never talks to the user.
- **Reviewer** — *(optional)* internal sub-agent that Maya may dispatch to verify Worker evidence before incorporating results.

### Internal handoff protocol (never exposed to user)
- `issues: opened` → `.github/workflows/issue-agent-trigger.yml` creates GitHub-style event payload in `.agents/handoffs/events/<issue>.json`
- `issue_comment: /handoff <role>` → `.github/workflows/issue-comment-handoff.yml` routes the active handoff to the next role
- Each handoff event has `event_type`, `workflow.current_role`, `workflow.next_role`, `workflow.sequence`
- Active handoff is always at `.agents/handoffs/active/current.json`
- Agents advance the chain by commenting `/handoff worker`, `/handoff reviewer`, etc. on the issue

### How Maya uses sub-agents transparently

When a user asks a complex question or requests work:

1. **Maya assesses** the request, breaks it into objectives
2. **Worker dispatch** — Maya uses the Task tool to dispatch a Worker with a single objective. The Task description includes: handoff event path, objective ID, relevant files, and acceptance criteria.
3. **Worker executes** — Worker implements, tests, and returns structured evidence
4. **Maya incorporates** — Maya reads the Worker's evidence, possibly dispatches Reviewer for verification, then responds to the user naturally as if she did the work
5. **Never exposed** — Maya never says "I dispatched a sub-agent" or "Worker did X". She says "I looked into this" or "Here's what I found".

### Why chain, not parallel
- Each step produces verifiable evidence before the next starts
- Worker focuses on one objective at a time — no context splitting
- Reviewer catches gaps before CTO routes next work
- Telemetry in `.agents/ops/telemetry.json` tracks cycle time, score, error rate

### Telemetry & Scoring
Each completed cycle records in `.agents/ops/telemetry.json`:
- `duration_minutes`, `total_issues`, `closed_issues`
- `objectives_completed`, `handoffs_used`, `files_changed`
- `tests_passed` (e.g. "93/93"), `gaps`, `errors`
- `sub_agents_used`, `sub_agent_failures`
- `score` (0-100) calculated from: completion rate × success rate × speed factor

Score formula: `(closed_issues/total_issues) × (1 - errors/objectives) × max(0.5, 1 - duration_minutes/240) × 100`

The goal is score ≥ 90 per cycle. If score < 70, the workflow needs optimization.

### GitHub Actions Triggers
- `issues: opened` → `.github/workflows/issue-agent-trigger.yml` creates handoff JSON
- `issue_comment: created` → can trigger reviewer handoff when comment starts with `/review`
- `push: main` → `.github/workflows/notify-fork.yml` dispatches downstream fork sync on `bapxmediahub/bapXphpAiBackend`

This repo is an agent-ready PHP/MySQL/YAML full-stack product base for small PHP hosting. It is not a SPA, not a separate MCP/skill server. The backend primitives live in this monorepo. Remote MySQL is the only runtime data store; local JSON files are import-only and never a runtime fallback. Blog posts and customer help guides use Markdown with YAML frontmatter in `content/blog/posts/`; help guides use the `help` category. Media metadata uses YAML in `storage/media.yaml`.

## Repository Contract

- This root `AGENTS.md` is the only binding agent contract in the repository. Do not create directory-level `AGENTS.md` files or duplicate these rules in skills.
- Keep investigation and file operations inside this repository by default. Do not search sibling folders, home directories, or unrelated projects unless the user explicitly requests that scope.
- Read this file before working. Update it only when a repository-wide durable workflow changes.
- After meaningful edits, update every affected durable page/module/role document in the same PR. Implementation without documentation reconciliation is incomplete.
- Keep instructions concise and operational. Delete stale or contradictory rules instead of preserving historical duplication.

## Core Shape

- Design system: `Design.md` is the canonical contract for customer-facing UI tokens, typography, geometry, components, and responsive behavior.
- Frontend: PHP templates in `views/`.
- Backend: PHP controllers and services in `app/`.
- Database: MySQL is the primary runtime store. `config/database.php` holds connection config. `bapXphp db` CLI manages the DB. Blog posts use YAML frontmatter in `content/blog/posts/`. Media metadata uses YAML in `storage/media.yaml`.
- Schema: `storage/schema/collections.php` (for reference; runtime uses MySQL `DatabaseService`).
- Media: `assets/images/media/` plus `storage/media.yaml`.
- Admin: owner tools for CRUD, media, environment variables, permissions, integrations, audit logs, project map, and blog management.
- Agent context: `AgentContextService` builds safe user-specific context for support/model assistants.
- Consultations: central admin manages consultant profiles and scheduled appointments; new requests queue SMTP notifications to the configured site mailbox. Consultant profiles are not login accounts.

## Diagnose, Then Issue

- For a meaningful code, schema, UI, documentation, or workflow change, reproduce or inspect the reported behavior first. Trace the affected systematic-map path and pinpoint the owning source before creating an issue. For documentation or instruction changes, also consult `docs/KnowledgeMap.mmd`.
- After diagnosis, search open GitHub issues. Select an existing matching issue or create one before editing when GitHub is available.
- Put reproduction evidence, affected source paths, the pinpointed cause, intended scope, and acceptance checks in the issue. Reference the issue in the branch and PR.
- Do not create an issue for read-only diagnosis, trivial questions, or when the user explicitly declines issue tracking.

## Known Issues

- Cart tray desktop styles were recently added (`.mobile-cart-tray` at ≥701px). The CSS class name is a misnomer — `mobile-cart-tray` now shows at all viewports.
- The GST launch issue (#169) closed all 8 objectives. TaxService, invoice views, admin tax report, and GST settings are fully wired.
- SupportTicketService persists escalated conversations. The `support_tickets` collection schema has `context`, `created_at`, `updated_at` fields.
- BlogDraftService provides template-based AI drafts for editorial, product, tool, and help content.
- If sub-agents are dispatched in parallel instead of sequential handoff, the telemetry score will penalize the cycle.

## Source-Grounded Work Order

1. Run `bapXphp map` AND `bapXphp schema list` **before any action** (mandatory pre-flight).
2. Run `bapXphp handoff next <issue>` — this reads the handoff JSON and tells you the next role and objective.
3. Read this root `AGENTS.md`.
4. Read `docs/systematic-map.mmd` as a generated index, follow the affected edges, reproduce the behavior when possible, and pinpoint the owning source.
5. Search for an existing issue, then select or create the evidence-backed issue when the diagnose-then-issue rule applies.
6. Read the narrow `.agents/skills/<skill-name>/SKILL.md` files that match the task; skills may add task technique but may not duplicate or override this contract.
7. Read `storage/schema/collections.php` for schema definitions and `Design.md` for customer-facing UI.
8. Search with `rg` and inspect existing implementations before creating any file, route, service, view, collection, or navigation item.
9. Implement against primary repository sources. The generated map summarizes relationships; it does not override source files.

## Project Map

- `docs/systematic-map.mmd` is the single project-map artifact (routes/controllers/services wiring). `docs/KnowledgeMap.mmd` is a separate generated documentation mindmap.
- Do not create `docs/PROJECT_MAP.md`, `docs/project-map.json`, `docs/project-map.mmd`, or parallel map generators (exception: `generate-docs-map.php` is the KnowledgeMap generator, not a parallel project map).
- `cli/generate-project-map.php` regenerates `docs/systematic-map.mmd`.
- `cli/generate-docs-map.php` regenerates `docs/KnowledgeMap.mmd`.
- `cli/validate-project-map.php` compares the generated Mermaid to the committed file.
- Update `ProjectMapService::scan()` and `ProjectMapService::renderSystematicMermaid()` when the map needs new sections, edges, or gap checks.
- Map validation alone is incomplete. For every affected map path, verify the source route, controller action, service, schema entry, storage collection, rendered page, and shared navigation that actually implement the behavior.
- Treat gap nodes as investigation prompts, not permission to scaffold a missing file. First determine whether the node is a JSON response, shared layout, runtime-only file, test fixture, or genuinely missing implementation.

## Rules

- Remote MySQL is the only runtime store. JSON files in `storage/data/` are used only for explicit one-time imports via `bapXphp db sync`.
- Update `storage/schema/collections.php` before changing a collection shape, admin fields, media fields, seed data, or agent-visible context.
- Extend existing controllers, services, views, storage files, and tools when they already cover the use case. Do not scaffold parallel implementations.
- When a code change reveals a reusable workflow rule, update the matching project skill under `.agents/skills/<skill-name>/SKILL.md` so future agents inherit the framework behavior. Keep skills business-agnostic.
- Keep route -> controller -> service -> remote MySQL boundaries via `DatabaseService`. `JsonStoreService` has been removed from the codebase.
- Do not add React, CDN React, a SPA fallback, or a second frontend.
- Customer-facing UI changes must follow `Design.md`: warm-neutral canvas, Inter/system sans typography, `#3A0003` primary maroon, `#D1B368` secondary gold, stable photo-first cards, restrained borders/shadows, and the documented responsive breakpoints.
- Admin mutations should be auditable via `AuditLogService`.
- User-specific assistant context must use `AgentContextService` or equivalent filtering. Never expose all users' data to a customer assistant.
- Product, temple, and astrologer media should use the media library picker/upload flow.
- Blog posts go in `content/blog/posts/` as `.md` files with YAML frontmatter. Blog categories in `content/blog/categories.yaml`.
- Customer help is blog content in the `help` category. Do not create a separate customer documentation content store or renderer.
- Media metadata goes in `storage/media.yaml` (not in MySQL).
- Secrets (Razorpay, Stripe, Google OAuth, SMTP, etc.) are admin-editable through Admin → Integrations and stored in the MySQL `secrets` table. Never put secrets in `.env`. System env vars serve as fallback for critical credentials.
- Before committing or pushing to remote `main`, verify the repo with the relevant PHP lint checks, tests, project-map generation/validation, and smoke checks.

## Area Contracts

- `app/`: keep route -> controller -> service -> `DatabaseService` boundaries; use `AgentContextService` for user-scoped assistant context and audit admin mutations.
- `views/` and `assets/`: keep PHP templates, follow `Design.md`, reuse shared tokens/components, and browser-test customer UI. Do not add a SPA or build pipeline.
- `storage/`: declare every persisted field in `storage/schema/collections.php`; remote MySQL is runtime truth and JSON is import-only.
- `content/`: blog/help content is Markdown with YAML frontmatter; help is the Blog `help` category and uses one shared 16:9 card/article image.
- `docs/`: maintain durable behavior documentation and only the generated `systematic-map.mmd` and `KnowledgeMap.mmd` map artifacts.
- `cli/`: extend `bapXphp` for repeatable operations; commands must be shared-hosting compatible, credential-safe, and non-interactive where agents need them.
- `integrations/`: keep clients small and obtain admin-managed secrets through `SecretService`; never hardcode credentials.
- `tests/`: assert current contracts without production credentials or network dependence; validation must detect stale generated maps.

## CLI-Only Operations

All project operations go through `bapXphp`. Never use raw shell/edit/write/find/grep tools directly — every file or content operation must use the corresponding `bapXphp` command:

| Operation | CLI Command |
|-----------|-------------|
| Read file    | `bapXphp read file <path>` |
| Write file   | `bapXphp write file <path>` (pipe stdin) |
| Edit file    | `bapXphp edit <path> <search> <replace>` |
| Search code  | `bapXphp grep <pattern> [path]` |
| Find files   | `bapXphp find <glob-pattern>` |
| Run command  | `bapXphp run <command...>` |
| List dir     | `bapXphp run ls <path>` |

This ensures every operation is auditable, logged, and consistent. If a required operation is missing or unsafe, enhance the nearest existing `bapXphp` command before performing the operation. Agent-facing bulk commands must be non-interactive, shared-hosting compatible, credential-safe, idempotent where practical, and provide `--dry-run` before mutations. GitHub operations remain in `gh`; browser verification remains in the Browser skill.

## Attachment System (.agents/temp/)

Every coding agent (OpenCode, Claude Code, Codex, etc.) uses `.agents/temp/` as the standard inbox for user-provided attachments:

1. User attaches a screenshot, image, PDF, or document
2. Agent copies/moves it to `.agents/temp/` inside this project
3. Agent reads the file from `.agents/temp/` to understand the visual/content request
4. Agent processes the file (describes screenshot, extracts text, etc.)
5. Agent acts on the request using the file as reference

This standardizes attachment handling across all agent types. Always check `.agents/temp/` when a user says they've attached something.

## Model Routing (from Admin Panel)

Sub-agents should use the most cost-effective AI model for their task. Model config is stored in the `secrets` table under `google_api_key` and `model`, editable via Admin → Integrations. Provider is auto-detected from model name.

| DB Column | Purpose | Current Value |
|-----------|---------|--------------|
| `google_api_key` | API key for AI provider | Set in Admin |
| `model` | Model ID (e.g. `gemma-4-31b-it`) | Set in Admin |

Read via `SecretService::getModelConfig()` at runtime. Never hardcode model names or API keys.

## Admin Panel Agent (bapXcli)

The admin panel has an agent interface at `/admin/agent` that:
- Answers questions about the site (users count, orders, revenue, products)
- Creates and edits blog posts via natural language
- Reads MySQL data through DatabaseService
- Calls AI model (configured in Admin → Integrations)
- Reads `.agents/temp/` for attachment-based requests
- Triggers CI/CD on the hosting server

The admin agent reads these collections: `users`, `orders`, `products`, `astrologers`, `appointments`, `support_tickets`, `audit_events`, `settings`. Write/mutation operations require user confirmation.

### Content CRUD (use the CLI, never edit files directly)

```bash
bapXphp read blog                    # list all blog posts
bapXphp read blog <slug>             # read a blog post
bapXphp write blog [slug]            # create or edit blog post (interactive)
bapXphp read docs [slug]             # list or read customer help guides
bapXphp write docs [slug]            # create or edit a customer help guide
bapXphp read product                  # list all products
bapXphp read product <slug>           # read a product
bapXphp write product [slug]          # create or edit product (interactive)
```

### Database (MySQL direct — no SSH needed)

```bash
bapXphp db query products --limit 5   # query products
bapXphp db find orders ord_123        # find an order
bapXphp db raw "SELECT * FROM ..."    # raw SQL
bapXphp db init                       # create MySQL tables from schema
bapXphp db sync                       # push JSON → MySQL
```

### Project Management

```bash
bapXphp test                          # run tests
bapXphp update                        # regenerate both map artifacts after source/docs changes
bapXphp ci                            # non-mutating full PR/CI validation
bapXphp check                         # alias for bapXphp ci
bapXphp serve                         # start dev server
bapXphp map:gen                       # regenerate project map
bapXphp docsmap                       # regenerate KnowledgeMap
bapXphp schema list                   # list all collections
bapXphp issue                         # create GitHub issue
bapXphp pr                            # create PR
bapXphp help                          # full reference
```

## Validation

Run the smallest useful validation for the change:

```bash
bapXphp lint path/to/changed.php
bapXphp update
bapXphp ci
```

For doc/AGENTS/skill changes, regenerate and validate both maps:

```bash
bapXphp update
```

For UI changes, also use a browser workflow. Codex agents must use `Browser:control-in-app-browser` for localhost and in-app browser verification when the Browser plugin is available. Standalone Playwright is only a fallback for agents or environments that do not have the Browser plugin. Click the changed page like a user and verify the visible result.

Local browser validation must use this project's single dev-server port: `127.0.0.1:6020`. If `6020` is already listening, inspect and reuse that running project server; do not start another copy on `6021` or any alternate port unless the user explicitly authorizes it.

Before finishing, search the touched workflow for placeholders, dead buttons, duplicated fallbacks, stale labels, and incomplete wiring. Remove or wire them instead of leaving non-working UI.

# CRITICAL RULE: ZERO-CODE INITIATION

You are forbidden from writing code or creating files upon receiving a new prompt.
Before proposing any code change, you MUST execute:
`bapXphp map` AND `bapXphp schema list`

## AUTOMATED ISSUE & DEPLOYMENT WORKFLOW (GH-CLI)

Immediately after inspecting the map and schema list, you must process the task completely through GitHub via the command line interface without requiring step-by-step user confirmation:

1. **Investigate & Diagnose:** Track down the code footprints. Identify the exact file name, page context, and specific line numbers causing the bug or holding back the feature.
2. **File the Issue:** Use `gh issue create` to open a clear issue, bug report, or feature ticket. You MUST explicitly embed the exact line references and file paths directly into the GitHub issue body so that future agent instances or humans have immediate grounding.
3. **Isolate and Execute:** Branch out, perform the micro-targeted code alterations, update affected durable documentation, run `bapXphp update`, and run non-mutating `bapXphp ci` to ensure zero regressions and fresh maps.
4. **Automated Merging:** Commit the clean updates, push the branch, run `gh pr create` to target `main`, and execute `gh pr merge --merge --delete-branch` to push the features straight to live.

   After merge, `push: main` triggers `.github/workflows/notify-fork.yml` which dispatches an `upstream-main-updated` event to `bapxmediahub/bapXphpAiBackend` via `secrets.FORK_SYNC_TOKEN`. The fork's `sync-upstream.yml` workflow handles the actual sync. Verify the downstream SHA matches.

5. **Channel Communication to GitHub:** Do not broadcast intermediate debugging steps or structural logs to the terminal prompt. All technical updates, state transitions, and implementation details belong inside the GitHub repository issue comments.
