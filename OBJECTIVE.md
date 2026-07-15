---
title: Project Objectives & Engineering Report
description: Comprehensive report of current state, built features, undocumented systems, CLI gaps, and next objectives.
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
| CI pipeline (lint → test → map val → docs val → smoke) | ✅ Done | `cli/bapXphp` `cmd_ci()` |

### 2.2 AGENTS.md & Documentation Consolidation
| Feature | Status | Files |
|---------|--------|-------|
| AGENTS.md consolidated to 117 lines | ✅ Done | `AGENTS.md` |
| Model Routing section moved to README | ✅ Done | `README.md` |
| Known Issues & Context section | ✅ Done | `AGENTS.md` |
| Skill Ownership section (CEO owns subagent-orchestration) | ✅ Done | `AGENTS.md` |
| YAML frontmatter on all 39 docs .md files | ✅ Done | `docs/*.md` |
| ZERO-CODE rule preserved | ✅ Done | `AGENTS.md` |

### 2.3 Admin Agent Workflow Page
| Feature | Status | Files |
|---------|--------|-------|
| `GET /admin/developer/workflow` route | ✅ Done | `app/Services/ProjectMapService.php:763` |
| `AdminController@workflow()` method | ✅ Done | `app/Controllers/AdminController.php:519` |
| Workflow view with skills, handoffs, commands | ✅ Done | `views/admin/workflow.php` |
| Admin sidebar navigation link | ✅ Done | `views/layouts/admin.php` |
| Project map updated (107 routes, 49 views) | ✅ Done | `docs/systematic-map.mmd` |
| KnowledgeMap updated with coverage gaps | ✅ Done | `docs/KnowledgeMap.mmd` |

### 2.4 Handoff System (CLI)
| Feature | Status | Files |
|---------|--------|-------|
| `bapXphp handoff validate` | ✅ Done | `cli/bapXphp:790`, `cli/handoff.php` |
| `bapXphp handoff comment` | ✅ Done | `cli/bapXphp:808` |
| `bapXphp handoff next` | ✅ Done | `cli/bapXphp:825`, `cli/handoff.php` |
| `bapXphp handoff template` | ✅ Done | `cli/bapXphp:852`, `cli/handoff.php` |
| `bapXphp handoff execute` | ✅ Done | `cli/bapXphp:864` |
| `bapXphp handoff score` | ✅ Done | `cli/bapXphp:885` |
| Handoff JSON schema | ✅ Done | `.agents/workflows/handoff.schema.json` |
| Workflow files (cto, worker, reviewer, browser-tester) | ✅ Done | `.agents/workflows/*.md` |
| Telemetry tracking | ✅ Done | `.agents/ops/telemetry.json` |
| Event-driven protocol (issues: opened, issue_comment) | ✅ Done | `.agents/handoffs/events/*.json` |

### 2.5 Project Map Enhancements
| Feature | Status | Files |
|---------|--------|-------|
| New gap types: `admin_mutations_without_audit` | ✅ Done | `app/Services/ProjectMapService.php:803` |
| New gap types: `unwired_schema_collections` | ✅ Done | `app/Services/ProjectMapService.php:804` |
| `serviceCollections()` made public | ✅ Done | `app/Services/ProjectMapService.php:859` |
| Coverage gaps in KnowledgeMap | ✅ Done | `app/Services/DocsMapService.php:691` |
| Services without collection mapping in KnowledgeMap | ✅ Done | `app/Services/DocsMapService.php:720` |
| Mermaid diagram: gaps connect to missing controllers/services | ✅ Done | `app/Services/ProjectMapService.php:844-852` |

### 2.6 Admin Audit Log Wiring
| Feature | Status | Files |
|---------|--------|-------|
| `saveOrderStatus` audit logged | ✅ Done | `AdminController.php:475` |
| `saveSettings` audit logged | ✅ Done | `AdminController.php:485` |
| `saveAdminCredentials` audit logged | ✅ Done | `AdminController.php:486` |
| `saveIntegrations` audit logged | ✅ Done | `AdminController.php:489` |
| Project map updated with AuditLogService | ✅ Done | `ProjectMapService.php:744,754-755,762,772` |

### 2.7 Maya Controller — White-Label
| Feature | Status | Files |
|---------|--------|-------|
| `agent_name` from secrets (configurable) | ✅ Done | `MayaController.php:572-573` |
| `seo_site_name` from secrets (no hardcoded name) | ✅ Done | `MayaController.php:574` |
| `agent_name` field in integrations form | ✅ Done | `views/admin/integrations.php` |
| Multi-provider AI (OpenAI, Google, Anthropic) | ✅ Done | `MayaController.php:607-650` |
| Model default: gemma-4-31b-it (was gpt-4o) | ✅ Done | `MayaController.php:602` |

### 2.8 SecretService / Model Config
| Feature | Status | Files |
|---------|--------|-------|
| usort null guard fix | ✅ Done | `SecretService.php:881` |
| `getModelConfig()` uses `agent_api_key`, `agent_model` | ✅ Done | `SecretService.php:891-892` |
| Google Gemini auto-endpoint detection | ✅ Done | `SecretService.php:894-895` |
| `agent_api_key` + `agent_model` environment fallback | ✅ Done | `SecretService.php:891-892` |

### 2.9 Remote DB — Token Auth Removed
| Feature | Status | Files |
|---------|--------|-------|
| Token verification removed from controller | ✅ Done | `RemoteDbController.php:632-638` |
| Token sending removed from DatabaseService | ✅ Done | `DatabaseService.php:672-679` |
| Token prompting removed from integrations form | ✅ Done | `views/admin/integrations.php` |
| Token removed from CLI `bapXphp` | ✅ Done | `cli/bapXphp` `cmd_db_upsert` |
| `remote_db_token` env var removal from tests | ✅ Done | `tests/run.php` |

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

---

## 4. What Is Implemented But NOT Documented

### 4.1 Controllers Without Documentation (8 of 12)
| Controller | Routes | Documented? |
|-----------|--------|------------|
| `MayaController.php` | `POST /api/maya` | ❌ No |
| `SupportController.php` | `GET /support`, `POST /support/ask` | ❌ No |
| `CommerceController.php` | Cart, checkout, payment flows | ❌ No |
| `AccountController.php` | Dashboard, orders, bookings, install, invoices | ❌ No |
| `ReviewController.php` | `POST /reviews/product` | ❌ No |
| `BlogController.php` | Blog listing, show, category | ❌ No |
| `RemoteDbController.php` | `POST /remoteDB` | ❌ No |
| `BaseController.php` | Shared base class | ❌ No |

### 4.2 Services Without Dedicated Documentation (41 of 41)
All 41 services lack dedicated `docs/services/*.md` files. Many are mentioned in passing but none have their own documentation.

### 4.3 View Pages Without Documentation (39 of 49)
- 15 public pages without page docs (terms, privacy, contact, cart, product, categories, login, register, forgot-password, reset-password, spiritual, support, sitemap, 404, astrologer)
- 15 admin pages without page docs (list, detail, product-form, astrologer-form, resource, appearance, environment, mailbox, media, workflow, agent, tax-report, settings, consultation-analytics, blog)
- 5 account pages without page docs (orders, bookings, invoice, install, _nav)

### 4.4 Entire Agent Infrastructure Not in `docs/`
- Handoff system (CLI commands, events, schema, workflows)
- Skills directory (11 skills, all undocumented in `docs/`)
- Telemetry system
- Agent workflow definitions
- MCP/A2A architecture

### 4.5 Additional Systems Without Documentation
- AI/Agent system (Maya, support bot, admin agent, BlogDraftService, AgentContextService)
- CLI tools (19 PHP tools, none documented in `docs/`)
- Stripe, Meta Pixel, Google Site Kit integrations
- Mail system (inbox/outbox, MailStorageService)
- Media system (MediaService, media library)
- Backup system
- Contact submissions
- Coupon system
- Shipping system
- Address system
- Test suite (93 tests, no testing docs)
- Rate Limiter
- Image Optimizer
- SEO system

---

## 5. CLI Tool Gaps (OpenCode vs bapXphp)

### 5.1 OpenCode Has — bapXphp CLI Missing

| OpenCode Tool | bapXphp Equivalent | Status |
|--------------|-------------------|--------|
| `read` file | `bapXphp read file <path>` | ✅ Exists |
| `write` file | `bapXphp write file <path>` | ✅ Exists |
| `edit` file | `bapXphp edit <path> <search> <replace>` | ✅ Exists |
| `grep` | `bapXphp grep <pattern> [path]` | ✅ Exists |
| `glob` | `bapXphp find <glob>` | ✅ Partial (uses bash glob) |
| `bash` | `bapXphp run <command>` | ✅ Exists |
| `websearch` | ❌ **Not implemented** | 🚫 Missing |
| `webfetch` | ❌ **Not implemented** | 🚫 Missing |
| `task` (sub-agent dispatch) | ❌ **Not implemented** | 🚫 Missing |
| `skill` (load skills) | ❌ **Not implemented** | 🚫 Missing |
| `todowrite` (task tracking) | ❌ **Not implemented** | 🚫 Missing |
| `question` (ask user) | ❌ **Not implemented** | 🚫 Missing |

### 5.2 Playwright MCP Tools — bapXphp CLI Missing

| Playwright Tool | bapXphp Equivalent | Status |
|----------------|-------------------|--------|
| `browser_navigate` | ❌ **Not implemented** | 🚫 Missing |
| `browser_click` | ❌ **Not implemented** | 🚫 Missing |
| `browser_snapshot` | ❌ **Not implemented** | 🚫 Missing |
| `browser_screenshot` | ❌ **Not implemented** | 🚫 Missing |
| `browser_fill` / `browser_fill_form` | ❌ **Not implemented** | 🚫 Missing |
| `browser_evaluate` | ❌ **Not implemented** | 🚫 Missing |
| `browser_network_requests` | ❌ **Not implemented** | 🚫 Missing |
| `browser_type` | ❌ **Not implemented** | 🚫 Missing |
| `browser_press_key` | ❌ **Not implemented** | 🚫 Missing |
| `browser_wait_for` | ❌ **Not implemented** | 🚫 Missing |
| `browser_console_messages` | ❌ **Not implemented** | 🚫 Missing |
| `browser_hover` | ❌ **Not implemented** | 🚫 Missing |
| `browser_select_option` | ❌ **Not implemented** | 🚫 Missing |
| `browser_file_upload` | ❌ **Not implemented** | 🚫 Missing |
| `browser_resize` | ❌ **Not implemented** | 🚫 Missing |
| `browser_drag` / `browser_drop` | ❌ **Not implemented** | 🚫 Missing |
| `browser_tabs` | ❌ **Not implemented** | 🚫 Missing |

### 5.3 Chrome DevTools MCP Tools — All Missing

**All 18+ DevTools tools** (click, fill, snapshot, screenshot, evaluate, network, console, emulate, lighthouse, performance, heapsnapshot, etc.) — ❌ **None implemented** in bapXphp CLI.

---

## 6. Next Objectives

### Phase A: Documentation
1. Create `docs/services/` directory with docs for all 41 services
2. Create `docs/agents/` directory for agent infrastructure docs
3. Create `docs/cli/` directory for CLI tool documentation
4. Create page docs for all 39 undocumented views
5. Create integration docs for Stripe, Meta Pixel, Google Site Kit

### Phase B: MCP Endpoint
6. Build MCP controller at `POST /api/mcp` (JSON-RPC 2.0)
7. Define MCP tools: query_database, read_collection, run_bapXphp, read_project_map
8. Define MCP resources: schema, map, docs, handoffs
9. Define MCP prompts for common agent tasks
10. Register MCP routes in ProjectMapService

### Phase C: A2A Protocol
11. Build A2A task endpoints (SendMessage, GetTask, ListTasks, CancelTask)
12. Implement Agent Card discovery
13. Wire A2A into handoff chain

### Phase D: Admin & Support API (`/v1`)
14. Build `/v1` API controller
15. Admin endpoints: CRUD, analytics, audit log
16. Support endpoints: tickets, chat, agent context

### Phase E: CLI Gaps
17. Add `bapXphp websearch <query>` CLI command
18. Add `bapXphp webfetch <url>` CLI command
19. Add `bapXphp browser` subcommand (navigate, click, snapshot, screenshot)
20. Add `bapXphp task` subcommand for sub-agent dispatch
21. Add `bapXphp skill load <name>` command
22. Add `bapXphp todo` subcommand (create, list, update)

### Phase F: Browser Tester
23. Implement `bapXphp browser navigate <url>` — Playwright-style CLI browser control
24. Implement `bapXphp browser click <selector>`
25. Implement `bapXphp browser snapshot [file]`
26. Implement `bapXphp browser screenshot [file]`

---

## 7. Architecture Decisions (Pending)

| Decision | Options | Status |
|----------|---------|--------|
| **Who handles MCP work?** | Worker (execution), CTO (orchestration), or new role | ⏳ **Unresolved** |
| **MCP at `/api/mcp` vs separate server?** | Same PHP app vs standalone MCP server | ⏳ **Needs CEO input** |
| **A2A at same endpoint?** | `/api/mcp` serves both MCP + A2A vs separate `/api/a2a` | ⏳ **Needs CEO input** |
| **Browser tester: Playwright MCP vs custom CLI?** | Use `@playwright/mcp` or build custom PHP browser control | ⏳ **Needs CEO input** |
