---
type: skill
name: subagent-orchestration
description: Use when designing, implementing, or debugging sub-agent handoff workflows, model routing, telemetry, or admin-panel agent wiring.
---
# Sub-Agent Orchestration Patterns

## Sources Researched

| Source | Key Pattern | Handoff Mechanism |
|--------|-------------|-------------------|
| OpenAI Agents SDK | Triage → specialist handoff; agents-as-tools | Tool-based handoff; LLM decides transfer |
| Anthropic/Claude | Orchestrator-worker; verification subagent | Agent tool; markdown-defined agents |
| Google ADK/Gemini | SequentialAgent, ParallelAgent, LoopAgent | sub_agents param; @agent syntax; A2A protocol |
| OpenCode | Task tool dispatch; markdown-defined agents | task() with subagent_type; fresh context |
| GitHub Actions | OrchestratorOps; workflow_dispatch fan-out | Job delegation via needs/matrix |
| MCP Community | AgentHandoff JSON packets; Sub-Agent MCP | Structured handoff files; Streamable HTTP |

## Universal Patterns (All Sources Agree)

1. **Orchestrator-worker**: A lead agent delegates to specialists. Always have a coordinator — independent agents amplify errors 17.2x vs centralized 4.4x.
2. **Fresh context isolation**: Subagents get their own context window. Pass only what they need (summary, not full transcript).
3. **Start simple**: One agent per role; add specialists only when context pollution or conflicting instructions arise.
4. **Parallel only for independent work**: Requires parallel safety check — no file overlap, no git-index contention, no test interference.
5. **Verification subagent**: Most consistently effective pattern — dedicated reviewer with read-only permissions.

## This Repo's Contract

From AGENTS.md: **Chain, Not Parallel** — sequential handoff only:

```
Issue → handoff JSON → CTO → Worker → evidence → Reviewer → findings → CTO
```

## Model Routing

Sub-agents should use the most cost-effective model for their task. Configured from the admin panel via MySQL `secrets` table (Google AI endpoint, Gemini API key, model name).

| Role | Recommended Model | Rationale |
|------|------------------|-----------|
| CTO (Orchestrator) | Gemini 2.5 Pro / Claude Opus | Planning, synthesis, routing decisions |
| Worker (Implementation) | Gemini 2.5 Flash / Claude Sonnet | Fast execution, good at code tasks |
| Reviewer (Verification) | Gemini 2.5 Flash | Cheap verification with structured rubric |

Model selection is read from `secrets` table at runtime:
- `ai_model_provider` : `"google"` | `"openai"` | `"anthropic"`
- `ai_model_name` : model ID string
- `ai_api_endpoint` : base URL for the API
- `ai_api_key` : authentication key

## Admin Panel Agent (bapXcli)

The admin panel (`/admin`) has a built-in agent interface that:
- Answers questions about the site (users count, orders, revenue)
- Creates and edits blog posts via natural language
- Reads product/consultant/temple data from MySQL
- Delegates sub-agents to the cloud hosting environment for CI/CD operations
- Reads attachments from `.agents/temp/`

The admin agent wires to the AI model endpoint configured in Admin → Integrations. It runs as a PHP controller action that:
1. Receives a prompt from the admin user
2. Queries MySQL for context (users, orders, products, etc.)
3. Calls the configured AI API endpoint
4. Streams the response back to the admin panel

## Telemetry (Optimized)

Each cycle records to `.agents/ops/telemetry.json` with enriched fields:

```json
{
  "id": "cycle-YYYY-MM-DD-NNN",
  "started": "ISO8601",
  "completed": "ISO8601",
  "duration_minutes": 0,
  "model_used": "gemini-2.5-flash",
  "api_endpoint": "https://generativelanguage.googleapis.com/v1/models/",
  "total_issues": 0,
  "closed_issues": 0,
  "objectives_completed": 0,
  "handoffs_used": 0,
  "files_changed": 0,
  "tests_passed": "0/0",
  "gaps": 0,
  "errors": 0,
  "sub_agents_used": 0,
  "sub_agent_parallel": 0,
  "sub_agent_failures": 0,
  "tokens_input": 0,
  "tokens_output": 0,
  "cost_estimate_usd": 0.0,
  "score": 0
}
```

**Score formula**: `(closed_issues/total_issues) × (1 - errors/objectives) × max(0.5, 1 - duration_minutes/240) × 100`

Goal: score ≥ 90 per cycle. If score < 70, the workflow needs optimization.

## Implementation Guide

### CTO Agent (Orchestrator)
- Runs `bapXphp map` + `bapXphp schema list` before any action
- Runs `bapXphp handoff next <issue>` to load objectives
- Routes single objective to Worker via Task tool with structured JSON prompt
- Waits for Worker evidence, passes to Reviewer, then closes loop or routes next
- Can create blog posts, read user/order data, answer questions about the site

### Worker Agent
- Receives one objective, investigates, implements, produces evidence
- Every file operation goes through `bapXphp` CLI
- No parallel dispatch — focus on single objective
- Returns: files changed, commands run, evidence links

### Reviewer Agent
- Fresh context (doesn't inherit Worker context)
- Read-only permissions (deny edit/write by default)
- Verifies evidence against acceptance criteria
- Returns: pass/fail, gaps found, recommendations

### Admin Panel Agent
- PHP controller that receives natural language prompts
- Queries MySQL for site data (user count, orders, products)
- Calls AI API endpoint (configured in admin integrations)
- Can trigger CI/CD workflows on the hosting server
- Reads `.agents/temp/` for user-provided attachments

## Attachment Flow (.agents/temp/)

All coding agents (OpenCode, Claude Code, Codex, etc.) treat `.agents/temp/` as the standard inbox:

1. User attaches a screenshot, image, or document
2. Agent copies/moves it to `.agents/temp/` within this project
3. Agent reads it from `.agents/temp/` to understand the user's visual request
4. Agent processes the file (e.g. describes the screenshot, extracts text from PDF)
5. Agent acts on the request using the file as reference

This standardizes attachment handling across all coding agent types.

## CI/CD Hosting Awareness

The site is hosted on Hostinger shared hosting with Git auto-deploy:
- **Host**: Hostinger VPS / shared hosting
- **Auto-deploy**: Git push → production webhook
- **CI**: GitHub Actions (`bapXphp ci`)
- **DB**: Remote MySQL (production)
- **Agent sub-delegation**: Sub-agents can trigger `workflow_dispatch` on GitHub Actions for long-running tasks
- **Recovery**: Manual `gh repo sync` only when event-driven sync fails

## Critical Mistakes to Avoid

- ❌ Parallel dispatch when chain is required — penalizes telemetry score
- ❌ Letting sub-agents stage/commit files during parallel work — git corruption
- ❌ No iteration cap on review loops — $340 overrun seen in production (Anthropic)
- ❌ Passing full transcript back to parent — blows context
- ❌ Giving every sub-agent every tool — deny by default, allow per role
- ❌ Ignoring 17.2x error amplification of independent multi-agent systems
- ❌ Hardcoding model/API keys — always read from MySQL `secrets` table
- ❌ Writing files directly instead of using `bapXphp write file`

## References

- OpenAI Agents SDK: https://openai.github.io/openai-agents-python/handoffs/
- Anthropic multi-agent: https://claude.com/blog/building-multi-agent-systems-when-and-how-to-use-them
- Google ADK patterns: https://cloud.google.com/blog/products/ai-machine-learning/build-multi-agentic-systems-using-google-adk
- OpenCode Task tool: https://opencode.ai/docs/agents
- GitHub OrchestratorOps: https://github.github.com/gh-aw/patterns/orchestrator-ops/
- Google Research scaling paper: https://research.google/blog/towards-a-science-of-scaling-agent-systems-when-and-why-agent-systems-work/
- Hostinger Git auto-deploy: docs/deployment-hostinger.md
