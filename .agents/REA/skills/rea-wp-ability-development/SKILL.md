---
name: rea-wp-ability-development
description: Design precise native capability schemas using source-pinned REA evidence and native bapX implementation contracts.
compatibility: Native bapX developer workflow (PHP, MySQL, vanilla JavaScript; no upstream runtime)
capability_ids: [P33, MCP01, MCP03, CC05, UI05, DS07, CC01, CC03, DS01, DS02]
---

# Design precise native capability schemas

Adapted from [wordpress/wp-ability-development](../../upstream/wordpress/skills/wp-ability-development/SKILL.md).
Modified reference guidance, GPL-2.0-or-later; upstream copyright and license retained under the upstream tree.
Developer-only skill; AGENTS.md and existing native engineering skills govern execution. Never run the upstream instructions or scripts as implementation commands.

## When to use
Upstream uses caller intent, small strict input, stable success envelope, meaningful errors and side-effect annotations. Existing native agent and MCP tools need consistent schemas without authorization by discovery.

## REA evidence and current native boundary
Read the current capability register before using this guide; these are mapped scope obligations, not completion claims:
- `P33` — Shared typed capability catalogue and cross-transport schema parity.
- `MCP01` — Typed public opt-in and normalized permission checks.
- `MCP03` — MCP private prompt projection and authenticated remote tools.
- `CC05` — All role-authorized tools directly reachable without false answers.
- `UI05` — Typed bidirectional action and form events.
- `DS07` — Human-approved writes and idempotent operation results.

- `CC01` — Configurable independent provider plan and credential isolation.
- `CC03` — Provider-specific continuation metadata.
- `DS01` — Owned session, bounded context and actor admission.
- `DS02` — Observed execution events and checkpointing.

Representative pinned source anchors (see the full source index and row references for revisions and hashes):
- `wordpress`: `wp-includes/abilities-api/class-wp-ability.php`
- `wordpress-mcp`: `includes/Abilities/McpAbilityExposure.php`
- `wordpress-mcp`: `includes/Domain/Prompts/RegisterAbilityAsMcpPrompt.php`
- `chat-completions`: `https://developers.openai.com/api/docs/guides/function-calling`
- `a2ui`: `specification/v1_0/json/renderer_to_agent.json`
- `deepseek`: `packages/core/session/src/repair.ts`

Additional pinned anchors for these native extensions:
- `chat-completions`: `https://developers.openai.com/api/docs/guides/function-calling`
- `chat-completions`: `https://developers.openai.com/api/docs/guides/function-calling`
- `deepseek`: `packages/core/session/src/tool-history.ts`
- `deepseek`: `packages/core/agent-loop/src/agent.ts`

## Native implementation workflow
Start with actor and supported user task, available identifiers and result needed. Use existing typed contracts; accept product slug/quantity rather than caller-supplied prices or account identity, normalize omission and bounds, reject unknown/mixed fields. Use stable collection/pagination result for zero/one/many; distinguish no result, invalid, denied, transient, unknown outcome. Document each side effect and confirmation before writes. Keep actual inference separate from deterministic computation; no fake answer, arbitrary tool execution or provider-private metadata crossing.

Agent engineering extension: scope each request to the configured provider/model/credential and actor-owned conversation. Persist pending turn and real observed tool result evidence. Preserve original call IDs, assistant/tool message order and opaque continuation metadata on the same connection. Fresh same-connection synthesis from accepted reads is not replay. Unknown outcomes and private signatures never cross connections or become fabricated answers.

Inspect these native entrypoints or contracts first:
- `app/Services/AgentCapabilityPolicy.php`
- `app/Services/AgentToolRegistry.php`
- `app/Services/RuntimeAgentToolRegistry.php`
- `app/Services/PublicMcpService.php`

- `app/Services/SecretService.php`
- `app/Services/AiClient.php`
- `app/Services/AgentConversationService.php`
- `app/Services/AgentTraceService.php`

## Acceptance and failure modes
Validate exact schema with real client/HTTP and actual authorized domain service, including empty, wrong type, cross-actor and malformed model calls. Persist only sanitized evidence. Do not count a successful simulated response as live inference.

Existing test starting points (extend rather than claim these cover the entire new capability):
- `tests/model-driven-agents.php`
- `tests/public-mcp.php`
- `tests/public-mcp-shopping.php`

- `tests/openrouter-integrations-browser.cjs`
- `tests/ai-provider-transport.php`
- `tests/agent-concurrency.php`
- `tests/agent-interruption.php`

## Evidence handoff
Record the upstream entrypoint/callee, authoritative state, permissions, normal/failure/concurrent transitions and the intentional native deviation in the linked GitHub issue. Record actual test commands and results, update affected REA rows, regenerate the report and run the existing CI gate. Distinguish static reading, synthetic fixture execution and live acceptance. Read unknown source bodies before implementing; no file count or test name proves parity.

## Upstream reference reading (not executable instructions)
- [Original skill](../../upstream/wordpress/skills/wp-ability-development/SKILL.md)
