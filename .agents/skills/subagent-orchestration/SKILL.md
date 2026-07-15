# Sub-Agent Orchestration Patterns

Use this skill when designing, implementing, or debugging sub-agent handoff workflows.

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

Each step produces verifiable evidence before the next starts.

## Implementation Guide

### CTO Agent (Orchestrator)
- Runs `bapXphp map` + `bapXphp schema list` before any action
- Runs `bapXphp handoff next <issue>` to load objectives
- Routes single objective to Worker via Task tool with structured JSON prompt
- Waits for Worker evidence, passes to Reviewer, then closes loop or routes next

### Worker Agent
- Receives one objective, investigates, implements, produces evidence
- Every file operation goes through `bapXphp` CLI (read file / write file / edit / grep / find / run)
- No parallel dispatch — focus on single objective
- Returns: files changed, commands run, evidence links

### Reviewer Agent
- Fresh context (doesn't inherit Worker context)
- Read-only permissions (deny edit/write by default)
- Verifies evidence against acceptance criteria
- Returns: pass/fail, gaps found, recommendations

## Critical Mistakes to Avoid

- ❌ Parallel dispatch when chain is required — penalizes telemetry score
- ❌ Letting sub-agents stage/commit files during parallel work — git corruption
- ❌ No iteration cap on review loops — $340 overrun seen in production (Anthropic)
- ❌ Passing full transcript back to parent — blows context
- ❌ Giving every sub-agent every tool — deny by default, allow per role
- ❌ Ignoring 17.2x error amplification of independent multi-agent systems

## Telemetry

Each cycle records to `.agents/ops/telemetry.json`:
- `duration_minutes`, `closed_issues`, `objectives_completed`
- `sub_agents_used`, `sub_agent_failures`, `tests_passed`
- `score` = `(closed/total) × (1 - errors/objectives) × max(0.5, 1 - duration/240) × 100`
- Goal: score ≥ 90

## References

- OpenAI Agents SDK: https://openai.github.io/openai-agents-python/handoffs/
- Anthropic multi-agent: https://claude.com/blog/building-multi-agent-systems-when-and-how-to-use-them
- Google ADK patterns: https://cloud.google.com/blog/products/ai-machine-learning/build-multi-agentic-systems-using-google-adk
- OpenCode Task tool: https://opencode.ai/docs/agents
- GitHub OrchestratorOps: https://github.github.com/gh-aw/patterns/orchestrator-ops/
- Sub-Agent MCP: https://github.com/systemgroupnet/Sub-Agent-MCP
- Google Research scaling paper: https://research.google/blog/towards-a-science-of-scaling-agent-systems-when-and-why-agent-systems-work/
- MCP handoff SEP: https://github.com/modelcontextprotocol/modelcontextprotocol/issues/2683
