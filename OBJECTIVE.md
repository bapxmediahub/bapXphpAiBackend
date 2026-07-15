---
title: Project Objectives & Engineering Report
description: Comprehensive report of current state, built features, undocumented systems, CLI gaps, next objectives.
category: root
---

# Project Objectives & Engineering Report

Generated: July 2026

## 1. Executive Summary

This report covers:
- What has been discussed and built across recent engineering sessions
- What has been discussed but NOT built
- What has been implemented but NOT documented
- Gaps between opencode/Playwright tools and our `bapXphp` CLI
- Next objectives for the engineering team

---

## 2. What Has Been Built (Implemented)

### 2.1 Fork Sync & CI Pipeline
| Feature | Status | Files |
|---------|--------|-------|
| Fork sync switch: event→schedule | ✅ Done | `.github/workflows/sync-upstream.yml` |
| `notify-fork.yml` removal | ✅ Done | Deleted |
| Fork sync test update | ✅ Done | `tests/run.php` |
| CI pipeline (lint → test → map val → docs val → codemap:val → smoke) | ✅ Done | `cli/bapXphp` `cmd_ci()` |

### 2.2 AGENTS.md & Documentation Consolidation
| Feature | Status | Files |
|---------|--------|-------|
| AGENTS.md consolidated to 117 lines | ✅ Done | `AGENTS.md` |
| Model Routing section moved to README | ✅ Done | `README.md` |
| Known Issues & Context section | ✅ Done | `AGENTS.md` |
| Skill Ownership section (CEO owns subagent-orchestration) | ✅ Done | `AGENTS.md` |
| YAML frontmatter with `type:` on all 71 source .md files | ✅ Done | `docs/*`, `content/blog/posts/*`, `.agents/skills/*` |
| ZERO-CODE rule preserved | ✅ Done | `AGENTS.md` |

### 2.3 Admin Agent Workflow Page
| Feature | Status | Files |
|---------|--------|-------|
| `GET /admin/developer/workflow` route | ✅ Done | `app/Services/ProjectMapService.php:763` |
| `AdminController@workflow()` method | ✅ Done | `app/Controllers/AdminController.php:519` |
| Workflow view with skills, handoffs, commands | ✅ Done | `views/admin/workflow.php` |
| Admin sidebar navigation link | ✅ Done | `views/layouts/admin.php` |
| Project map updated (109 routes, 49 views) | ✅ Done | `docs/systematic-map.mmd` |
| docs/map.mmd with coverage gaps | ✅ Done | `docs/map.mmd` |

### 2.4 Handoff System (CLI)
| Feature | Status | Files |
|---------|--------|-------|
| `bapXphp handoff validate` | ✅ Done | `cli/bapXphp`, `cli/handoff.php` |
| `bapXphp handoff comment` | ✅ Done | `cli/bapXphp` |
| `bapXphp handoff next` | ✅ Done | `cli/bapXphp`, `cli/handoff.php` |
| `bapXphp handoff template` | ✅ Done | `cli/bapXphp`, `cli/handoff.php` |
| `bapXphp handoff execute` | ✅ Done | `cli/bapXphp` |
| `bapXphp handoff score` | ✅ Done | `cli/bapXphp` |
| Handoff JSON schema | ✅ Done | `.agents/workflows/handoff.schema.json` |
| Workflow files (cto, worker, reviewer, browser-tester) | ✅ Done | `.agents/workflows/*.md` |
| Telemetry tracking | ✅ Done | `.agents/ops/telemetry.json` |
| Event-driven protocol (issues: opened, issue_comment) | ✅ Done | `.agents/handoffs/events/*.json` |

### 2.5 Map Architecture Overhaul (July 2026)
| Feature | Status | Files |
|---------|--------|-------|
| Two `map.mmd` files: `docs/map.mmd` (content) + `map.mmd` (code) | ✅ Done | `docs/map.mmd`, `map.mmd` |
| `bapXphp map` regenerates deterministically (not stale cat) | ✅ Done | `cli/bapXphp` |
| `bapXphp codemap` command | ✅ Done | `cli/bapXphp` |
| `bapXphp map:val` validates both maps via diff | ✅ Done | `cli/bapXphp` |
| CI validates root map.mmd freshness | ✅ Done | `cli/bapXphp` `cmd_ci()` |
| Gap nodes in generated maps (`unwired_services`, `unwired_schema_collections`) | ✅ Done | `ProjectMapService::renderSystematicMermaid()` |
| `docs/KnowledgeMap.mmd` deleted | ✅ Done | Deleted |
| `docs/knowledge-graph.mmd` deleted | ✅ Done | Deleted |

### 2.6 OKF v0.1 — Source-First Frontmatter Compliance
| Feature | Status | Files |
|---------|--------|-------|
| `type:` field in every source `.md` file (71 files) | ✅ Done | `docs/`, `content/blog/posts/`, `.agents/skills/*/SKILL.md` |
| Frontmatter added to 10 playwright reference files (were plain MD) | ✅ Done | `.agents/skills/playwright-cli/references/*.md` |
| KnowledgeGraphService reads `type` from source frontmatter | ✅ Done | `app/Services/KnowledgeGraphService.php` |
| Recursive directory scanning (was missing 25 docs in subdirs) | ✅ Done | `app/Services/KnowledgeGraphService.php` |
| Skill reference files indexed (playwright refs now in `.okf/`) | ✅ Done | `app/Services/KnowledgeGraphService.php` |
| `.okf/` bundle: 253 concepts, 237 edges (was 221/228) | ✅ Done | `.okf/` |
| `.okf/index.md` references `docs/map.mmd` (was `KnowledgeMap.mmd`) | ✅ Done | `KnowledgeGraphService.php` |
| `okfgen` no longer writes separate `knowledge-graph.mmd` | ✅ Done | `cli/generate-okf-bundle.php` |

### 2.7 Admin Audit Log Wiring
| Feature | Status | Files |
|---------|--------|-------|
| `saveOrderStatus` audit logged | ✅ Done | `AdminController.php` |
| `saveSettings` audit logged | ✅ Done | `AdminController.php` |
| `saveAdminCredentials` audit logged | ✅ Done | `AdminController.php` |
| `saveIntegrations` audit logged | ✅ Done | `AdminController.php` |
| Project map updated with AuditLogService | ✅ Done | `ProjectMapService.php` |

### 2.8 Agent Controller — White-Label
| Feature | Status | Files |
|---------|--------|-------|
| `agent_name` from YAML config + secrets override | ✅ Done | `AgentController.php:loadAgentConfig` |
| `seo_site_name` from secrets (no hardcoded name) | ✅ Done | `AgentController.php` |
| `agent_name` field in integrations form | ✅ Done | `views/admin/integrations.php` |
| Multi-provider AI (OpenAI, Google, Anthropic) | ✅ Done | `AgentController.php:callAi` |
| Model default: gemma-4-31b-it (was gpt-4o) | ✅ Done | `AgentController.php` |

### 2.9 SecretService / Model Config
| Feature | Status | Files |
|---------|--------|-------|
| usort null guard fix | ✅ Done | `SecretService.php:881` |
| `getModelConfig()` uses `agent_api_key`, `agent_model` | ✅ Done | `SecretService.php:891-892` |
| Google Gemini auto-endpoint detection | ✅ Done | `SecretService.php:894-895` |
| `agent_api_key` + `agent_model` environment fallback | ✅ Done | `SecretService.php:891-892` |

### 2.10 Remote DB — Password Auth
| Feature | Status | Files |
|---------|--------|-------|
| Password verification in controller via `SecretService` | ✅ Done | `RemoteDbController.php` |
| Password sent by `DatabaseService` in remote payloads | ✅ Done | `DatabaseService.php` |
| `remote_db_password` field in Admin → Integrations | ✅ Done | `views/admin/integrations.php` |
| `REMOTE_DB_PASSWORD` read from `.env` / config | ✅ Done | `config/database.php`, `SecretService.php` |
| Test updated to assert password auth | ✅ Done | `tests/run.php` |

---

## 3. What Was Discussed But NOT Built

### 3.1 MCP Endpoint (`/api/mcp`)
- **Discussed but never built.** User explicitly requested MCP endpoint at `https://sripanchamispiritual.com/api/mcp`
- Requires: JSON-RPC 2.0 protocol controller, tool definitions, resource access, prompt templates
- McpController.php was created in session but **deleted** as premature
- MCP routes were registered then **removed** from project map

### 3.2 A2A (Agent-to-Agent) Protocol
- **Discussed but never built.** User requested A2A protocol support at `/api/mcp`
- Google's A2A standard for agent-to-agent communication
- No implementation exists

### 3.3 `/v1` Admin & Support API
- **Discussed but never built.** User requested admin and support API at `/v1`
- Separate from MCP endpoint
- No implementation exists

### 3.4 Browser Tester — CLI-Based (No Playwright Server)
- **Discussed but never built.** User wants browser-tester enhanced for CLI-based browser control
- No Playwright server-side dependency
- Browser-tester workflow exists at `.agents/workflows/browser-tester.md` but no CLI implementation
- No `bapXphp browser` command exists

### 3.5 Roo Code / External Agent MCP Support
- **Discussed but never built.** User wants external agents (Roo Code, OpenCode, etc.) to connect via MCP
- No MCP server endpoint for external agents to discover tools
- No tool definitions for external consumption

### 3.6 CDN Optimization for Docs/Blog
- **Discussed.** User reviewed CDN docs but determined no CDN needed for documentation/blog content optimization
- YAML frontmatter (`type:`) was missing in source files — this was the actual gap, not CDN
- Fixed by adding `type:` to all source files directly (not just in `.okf/` export)

### 3.7 Structural Code Graph with Resolved Edges
- **Researched but not built.** Cursor/GitCortex/Codebase-Memory use tree-sitter AST parsing to build resolved call graphs
- Capabilities: `find_callers`, `trace_path`, `blast_radius`, `detect_changes`
- Our `ProjectMapService` does route→controller→service→collection edges via regex, not full AST
- Full tree-sitter graph (like GitCortex) would require PHP FFI or a separate binary
- **Not yet implemented** — route-level edges are sufficient for current agent needs

---

## 4. What Is Implemented But NOT Documented

### 4.1 Controllers Without Documentation (8 of 12)
| Controller | Routes | Documented? |
|-----------|--------|------------|
| `AgentController.php` | `POST /api/agent` | ❌ No |
| `SupportController.php` | `GET /support`, `POST /support/ask` | ❌ No |
| `CommerceController.php` | Cart, checkout, payment flows | ❌ No |
| `AccountController.php` | Dashboard, orders, bookings, install, invoices | ❌ No |
| `ReviewController.php` | `POST /reviews/product` | ❌ No |
| `BlogController.php` | Blog listing, show, category | ❌ No |
| `RemoteDbController.php` | `POST /remoteDB` | ❌ No |
| `BaseController.php` | Shared base class | ❌ No |

### 4.2 Services Without Dedicated Documentation (42 of 42)
All 42 services lack dedicated `docs/services/*.md` files.

### 4.3 View Pages Without Documentation (39 of 49)
- 15 public pages without page docs
- 15 admin pages without page docs
- 5 account pages without page docs

### 4.4 Entire Agent Infrastructure Not in `docs/`
- Handoff system (CLI commands, events, schema, workflows)
- Skills directory (11 skills, all undocumented in `docs/`)
- Telemetry system
- Agent workflow definitions
- MCP/A2A architecture

### 4.5 Additional Systems Without Documentation
- AI/Agent system (agent, support bot, admin agent, BlogDraftService, AgentContextService)
- CLI tools (23 PHP tools, none documented in `docs/`)
- Integrations (Stripe, Meta Pixel, Google Site Kit)
- Mail system (inbox/outbox, MailStorageService)
- Media system (MediaService, media library)
- Backup, Contact, Coupon, Shipping, Address, Rate Limiter, Image Optimizer, SEO

---

## 5. CLI Tool Gaps (OpenCode vs bapXphp)

### 5.1 OpenCode Has — bapXphp CLI Missing

| OpenCode Tool | bapXphp Equivalent | Status |
|--------------|-------------------|--------|
| `read` file | `bapXphp read file <path>` | ✅ Exists |
| `write` file | `bapXphp write file <path>` | ✅ Exists |
| `edit` file | `bapXphp edit <path> <search> <replace>` | ✅ Exists |
| `grep` | `bapXphp grep <pattern> [path]` | ✅ Exists |
| `glob` | `bapXphp find <glob>` | ✅ Partial |
| `bash` | `bapXphp run <command>` | ✅ Exists |
| `websearch` | ❌ **Not implemented** | 🚫 Missing |
| `webfetch` | ❌ **Not implemented** | 🚫 Missing |
| `task` (sub-agent dispatch) | ❌ **Not implemented** | 🚫 Missing |
| `skill` (load skills) | ❌ **Not implemented** | 🚫 Missing |
| `todowrite` (task tracking) | ❌ **Not implemented** | 🚫 Missing |
| `question` (ask user) | ❌ **Not implemented** | 🚫 Missing |

### 5.2 Playwright MCP Tools — bapXphp CLI Missing

All 17+ Playwright tools (navigate, click, snapshot, screenshot, fill, evaluate, network, type, press_key, wait_for, console, hover, select_option, file_upload, resize, drag/drop, tabs) — ❌ **None implemented**.

### 5.3 Chrome DevTools MCP Tools — All Missing

All 18+ DevTools tools (click, fill, snapshot, screenshot, evaluate, network, console, emulate, lighthouse, performance, heapsnapshot, etc.) — ❌ **None implemented**.

---

## 6. Research Completed (July 2026)

### 6.1 OKF v0.1 (Google Cloud, June 2026)
- **One required field**: `type` in YAML frontmatter per concept
- **Recommended**: `title`, `description`, `resource`, `tags`, `timestamp`
- **Cross-links**: standard markdown links = directed graph edges
- **Progressive disclosure**: `index.md` per directory lists contents
- **Permissive consumption**: unknown types, missing optional fields, broken links — never reject
- **Conformance**: every non-reserved `.md` has parseable YAML + non-empty `type`
- **Applied**: all 71 source `.md` files now have `type:` frontmatter; `.okf/` is a generated export

### 6.2 NotebookLM Indexing (Google Labs)
- **Source grounding**: answers restricted to uploaded sources only
- **Hybrid approach**: full-context for small documents, semantic chunking + embedding for large ones
- **Static snapshots**: files captured at upload time; no incremental re-indexing
- **Multi-angle retrieval**: generates intermediate questions, explores documents from multiple angles before synthesis
- **Relevance**: pure RAG (retrieval-augmented generation) pattern — our `.okf/` bundle serves a similar grounding role for agents

### 6.3 Cursor / GitCortex / Codebase-Memory Code Indexing
- **Merkle tree**: SHA-256 per file + directory hash for incremental change detection
- **AST chunking**: tree-sitter splits by function/class/interface (not line count)
- **Vector embeddings**: semantic similarity search (Turbopuffer/Qdrant)
- **Limitation**: no resolved edges → can't answer "what calls this?" or "what breaks?"
- **GitCortex solution**: tree-sitter → resolved call graph in KuzuDB → MCP tools (find_callers, trace_path, detect_changes, blast_radius)
- **Codebase-Memory**: 66 languages, SQLite graph, 14 MCP tools, 83% answer quality at 10× fewer tokens
- **Our approach**: `ProjectMapService::scan()` uses PHP regex for route→controller→service→collection edges; sufficient for monorepo-level understanding

---

## 7. Next Objectives

### Phase A: Source-First YAML Frontmatter (DONE)
- ✅ `type:` added to all source `.md` files (71 files across docs, blog, skills, references)
- ✅ KnowledgeGraphService reads `type` from source frontmatter (not hardcoded)
- ✅ Recursive scanning for all docs subdirectories
- ✅ `.okf/` bundle regenerated from enriched source

### Phase B: Documentation
1. Create `docs/services/` directory with docs for all 42 services
2. Create `docs/agents/` directory for agent infrastructure docs
3. Create `docs/cli/` directory for CLI tool documentation
4. Create page docs for all 39 undocumented views
5. Create integration docs for Stripe, Meta Pixel, Google Site Kit

### Phase C: Runbook Indexing
6. Convert undocumented controllers/services into OKF doc concepts (`type: controller`, `type: service`)
7. Add `type: route` frontmatter to route documentation
8. Index all `.php` code files as OKF concepts (functions, classes, methods)
9. Build `bapXphp index` command for deterministic codebase re-indexing

### Phase D: MCP Endpoint
10. Build MCP controller at `POST /api/mcp` (JSON-RPC 2.0)
11. Define MCP tools: `query_graph`, `read_concept`, `find_edges`, `resolve_path`
12. Wire `.okf/` bundle as MCP resource root

### Phase E: CLI Gaps
13. Add `bapXphp websearch <query>` CLI command
14. Add `bapXphp webfetch <url>` CLI command
15. Add `bapXphp browser` subcommand (navigate, click, snapshot, screenshot)
16. Add `bapXphp task` subcommand for sub-agent dispatch

### Phase F: Structural Code Graph
17. Evaluate tree-sitter PHP grammar for AST-level function/service call resolution
18. Build resolved `service → service` dependencies (beyond route-level edges)
19. Add `detect_changes` — git diff-based impact analysis
20. Add `trace_path` — call chain between two symbols

### Phase G: Browser Tester
21. Implement `bapXphp browser navigate <url>` — Playwright-style CLI browser control
22. Implement `bapXphp browser click <selector>`
23. Implement `bapXphp browser snapshot [file]`

---

## 8. Architecture Decisions (Pending)

| Decision | Options | Status |
|----------|---------|--------|
| **Who handles MCP work?** | Worker (execution), CTO (orchestration), or new role | ⏳ **Unresolved** |
| **MCP at `/api/mcp` vs separate server?** | Same PHP app vs standalone MCP server | ⏳ **Needs CEO input** |
| **A2A at same endpoint?** | `/api/mcp` serves both MCP + A2A vs separate `/api/a2a` | ⏳ **Needs CEO input** |
| **Tree-sitter PHP graph?** | PHP FFI wrapper vs separate Go/Rust binary (GitCortex) | ⏳ **Needs evaluation** |
| **Browser tester: Playwright MCP vs custom CLI?** | Use `@playwright/mcp` or build custom PHP browser control | ⏳ **Needs CEO input** |
