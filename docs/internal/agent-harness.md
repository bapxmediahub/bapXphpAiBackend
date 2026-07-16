# Agent Harness Architecture

This is internal development documentation. It is intentionally absent from
the customer navigation.

## Source Of Truth

- `AGENTS.md`: binding repository contract.
- `config/agents/workflow.yaml`: machine-readable roles, scripts, edges,
  context policy, and telemetry contract.
- `agents.mmd`: generated visualization. Never edit it directly.
- `.agents/workflows/*.md`: detailed role behavior.
- `.agents/skills/*/SKILL.md`: task-specific operating knowledge.
- `.agents/workflows/tools/*/TOOL.md`: tool contracts and limitations.
- `.agents/hooks/`: local deterministic Git gates.
- `.github/workflows/`: repository events, CI, handoff routing, review, sync.
- `.agents/handoffs/`: objective evidence exchanged between roles.
- `.agents/ops/telemetry.json`: measured workflow outcomes.

## What Belongs Where

| Concern | Owner | Reason |
|---|---|---|
| PHP lint, tests, maps, schema checks, tax calculation | `bapXphp` scripts | Deterministic work does not need an agent. |
| Pre-commit and pre-push checks | `.agents/hooks/` | Fast local prevention before GitHub receives work. |
| Issue, comment, push, PR, schedule events | GitHub Actions | GitHub YAML provides event-triggered runners and repository permissions. |
| Scope and acceptance decisions | CTO role | Requires product intent and evidence comparison. |
| One bounded implementation objective | Worker role | Limits ownership and context. |
| Independent code/contract verification | Reviewer role | Must not inherit Worker assumptions or edit files. |
| Rendered desktop/mobile verification | Browser Tester | Source inspection is not proof of UI behavior. |
| Durable task procedure | Skill | Reusable knowledge loaded only for relevant work. |
| Executable capability | Tool | Has explicit inputs, outputs, success, and failure behavior. |
| Cross-role evidence | Handoff | Preserves objectives and findings without sharing hidden reasoning. |

## External Patterns Adapted

### ChatDev 2.0

ChatDev 2.0 stores runnable workflows as YAML, provides a runtime/SDK to
execute them, separates orchestration from tools, and exposes intermediate
artifacts and human feedback. This repository adapts that pattern with
`config/agents/workflow.yaml`, `bapXphp handoff`, committed evidence, and
generated `agents.mmd`. It does not adopt ChatDev's Python/Vue runtime.

Reference:
<https://github.com/OpenBMB/ChatDev>

### ElevenLabs Workflows

ElevenLabs distinguishes subagent nodes from guaranteed dispatch-tool nodes.
Tool nodes have explicit success and failure result edges. Agent transfers
preserve the conversation transcript while child prompts, tools, models, and
knowledge remain scoped to the child. Its workflow analytics include node
entries, duration, termination, and edge distribution.

This repository therefore:

- keeps deterministic commands as script/tool nodes, not agent prompts;
- gives each role an explicit skill/tool/path boundary;
- preserves issue objectives and evidence across handoffs;
- excludes hidden reasoning, secrets, and unrelated user data;
- records node entries, edge outcomes, duration, and termination reason.

References:
<https://elevenlabs.io/docs/eleven-agents/customization/agent-workflows>
<https://elevenlabs.io/docs/eleven-agents/customization/tools/system-tools/agent-transfer>

### OpenAI Harness Engineering

OpenAI describes the human role as specifying intent and building environments
and feedback loops that let agents work reliably. Architectural boundaries and
cross-cutting providers should be mechanically enforced rather than repeated
as optional prompt advice.

This repository applies that through schema validation, generated map
freshness, CI, hooks, role ownership, browser evidence, and CTO acceptance.

Reference:
<https://openai.com/index/harness-engineering/>

### GitHub Actions

GitHub Actions workflows are repository YAML files activated by repository,
external dispatch, scheduled, or manual events. Actions run deterministic jobs
and scripts with repository permissions; they do not replace role judgment.

Reference:
<https://docs.github.com/en/actions/concepts/workflows-and-actions/workflows>

## Required Flow

1. GitHub issue/comment event creates a scoped handoff.
2. CTO defines objective IDs, acceptance checks, allowed paths, skills, tools.
3. Worker implements one objective and records evidence.
4. Guaranteed scripts run and route success or failure.
5. Reviewer verifies without editing.
6. Browser Tester verifies user-visible objectives on desktop and mobile.
7. CTO accepts, returns gaps, or blocks the objective.
8. Score and telemetry are calculated from evidence.
9. PR merges only after the gate passes.
10. Live browser verification closes or reopens the loop.

## Telemetry

Each objective must record:

- pass, fail, or blocked status;
- start, completion, and duration;
- attempts and tool failures;
- evidence count and reviewer findings;
- regressions;
- node entries and edge outcomes;
- termination reason;
- final score.

Historical or estimated numbers must not be presented as current telemetry.
