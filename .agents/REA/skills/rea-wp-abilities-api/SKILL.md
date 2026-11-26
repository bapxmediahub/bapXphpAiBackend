---
name: rea-wp-abilities-api
description: Reconstruct domain capability and transport projections using source-pinned REA evidence and native bapX implementation contracts.
compatibility: Native bapX developer workflow (PHP, MySQL, vanilla JavaScript; no upstream runtime)
capability_ids: [P31, P33, P34, MCP01, MCP02, MCP03, CC05, DS04]
---

# Reconstruct domain capability and transport projections

Adapted from [wordpress/wp-abilities-api](../../upstream/wordpress/skills/wp-abilities-api/SKILL.md).
Modified reference guidance, GPL-2.0-or-later; upstream copyright and license retained under the upstream tree.
Developer-only skill; AGENTS.md and existing native engineering skills govern execution. Never run the upstream instructions or scripts as implementation commands.

## When to use
Upstream distinguishes stable domain ability from MCP/REST/UI projection, with explicit opt-in and shared service rather than duplicate SQL. REA pinned WordPress abilities and MCP adapter permission/normalization source. Native role schemas exist but cross-transport parity is partial.

## REA evidence and current native boundary
Read the current capability register before using this guide; these are mapped scope obligations, not completion claims:
- `P31` — Authenticated public REST integration API and revocable credentials.
- `P33` — Shared typed capability catalogue and cross-transport schema parity.
- `P34` — Native MCP transport and scoped client management.
- `MCP01` — Typed public opt-in and normalized permission checks.
- `MCP02` — Public Streamable HTTP tools and resources.
- `MCP03` — MCP private prompt projection and authenticated remote tools.
- `CC05` — All role-authorized tools directly reachable without false answers.
- `DS04` — Instruction assembly and explicit tool authority.

Representative pinned source anchors (see the full source index and row references for revisions and hashes):
- `woocommerce`: `includes/admin/class-wc-admin-api-keys.php`
- `wordpress`: `wp-includes/abilities-api/class-wp-ability.php`
- `woocommerce`: `src/Internal/MCP/McpEndpointRemoval.php`
- `wordpress-mcp`: `includes/Abilities/McpAbilityExposure.php`
- `wordpress-mcp`: `includes/Transport/HttpTransport.php`
- `wordpress-mcp`: `includes/Domain/Prompts/RegisterAbilityAsMcpPrompt.php`
- `chat-completions`: `https://developers.openai.com/api/docs/guides/function-calling`
- `deepseek`: `packages/core/agent-loop/src/agent.ts`

## Native implementation workflow
Identify meaningful user tasks and authoritative service, stable identifier, input/output/error contract and actor/module permissions. Audit via rea-wp-abilities-audit before adding. Adapt to AgentCapabilityPolicy, RuntimeAgentToolRegistry, PublicMcpService, McpClientService and browser tool registrations; each projection explicitly opts in and validates at invocation. Keep small native model registries directly callable; optional metadata search cannot hide authorized tools. Separate public records, owner records and private developer instructions. Do not register WP abilities or use WP hooks.

Inspect these native entrypoints or contracts first:
- `app/Services/AgentCapabilityPolicy.php`
- `app/Services/RuntimeAgentToolRegistry.php`
- `app/Services/PublicMcpService.php`
- `app/Services/McpClientService.php`

## Acceptance and failure modes
Compare declarations and results across existing projections; deny guest/private and disabled modules, invalid/unknown inputs, revoked grants and arbitrary path/SQL. Exercise actual protocol clients and native model call correlation. Use one service, not duplicate controller calculation.

Existing test starting points (extend rather than claim these cover the entire new capability):
- `tests/public-mcp.php`
- `tests/mcp-client-access.test.cjs`
- `tests/model-driven-agents.php`

## Evidence handoff
Record the upstream entrypoint/callee, authoritative state, permissions, normal/failure/concurrent transitions and the intentional native deviation in the linked GitHub issue. Record actual test commands and results, update affected REA rows, regenerate the report and run the existing CI gate. Distinguish static reading, synthetic fixture execution and live acceptance. Read unknown source bodies before implementing; no file count or test name proves parity.

## Upstream reference reading (not executable instructions)
- [Original skill](../../upstream/wordpress/skills/wp-abilities-api/SKILL.md)
- [references/domain-vs-projection.md](../../upstream/wordpress/skills/wp-abilities-api/references/domain-vs-projection.md)
- [references/shared-core-service.md](../../upstream/wordpress/skills/wp-abilities-api/references/shared-core-service.md)
- [references/input-schema-gotchas.md](../../upstream/wordpress/skills/wp-abilities-api/references/input-schema-gotchas.md)
