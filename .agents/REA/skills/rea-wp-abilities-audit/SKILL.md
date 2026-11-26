---
name: rea-wp-abilities-audit
description: Audit native capability coverage before implementation using source-pinned REA evidence and native bapX implementation contracts.
compatibility: Native bapX developer workflow (PHP, MySQL, vanilla JavaScript; no upstream runtime)
capability_ids: [P31, P33, P34, MCP01, MCP03, CC05, DS07]
---

# Audit native capability coverage before implementation

Adapted from [wordpress/wp-abilities-audit](../../upstream/wordpress/skills/wp-abilities-audit/SKILL.md).
Modified reference guidance, GPL-2.0-or-later; upstream copyright and license retained under the upstream tree.
Developer-only skill; AGENTS.md and existing native engineering skills govern execution. Never run the upstream instructions or scripts as implementation commands.

## When to use
Upstream audit enumerates controllers, traces gates, records side effects and groups semantic operations rather than exposing each REST endpoint. Native projected surfaces are not yet fully unified. Use this as a source-derived audit method.

## REA evidence and current native boundary
Read the current capability register before using this guide; these are mapped scope obligations, not completion claims:
- `P31` — Authenticated public REST integration API and revocable credentials.
- `P33` — Shared typed capability catalogue and cross-transport schema parity.
- `P34` — Native MCP transport and scoped client management.
- `MCP01` — Typed public opt-in and normalized permission checks.
- `MCP03` — MCP private prompt projection and authenticated remote tools.
- `CC05` — All role-authorized tools directly reachable without false answers.
- `DS07` — Human-approved writes and idempotent operation results.

Representative pinned source anchors (see the full source index and row references for revisions and hashes):
- `woocommerce`: `includes/admin/class-wc-admin-api-keys.php`
- `wordpress`: `wp-includes/abilities-api/class-wp-ability.php`
- `woocommerce`: `src/Internal/MCP/McpEndpointRemoval.php`
- `wordpress-mcp`: `includes/Abilities/McpAbilityExposure.php`
- `wordpress-mcp`: `includes/Domain/Prompts/RegisterAbilityAsMcpPrompt.php`
- `chat-completions`: `https://developers.openai.com/api/docs/guides/function-calling`
- `deepseek`: `packages/core/session/src/repair.ts`

## Native implementation workflow
Freeze current SHA and REA source pin. Enumerate actual routes with ./bapXphp route:list and map; follow each controller to authoritative service and collection. For each meaningful operation record ID, actor, module, input/output/error schema, side effects, idempotency, exposure and test. Reconcile actual declarations in every transport; omit internal scheduler, admin credentials and raw SQL. Map existing register IDs and issues, mark unknowns, and group by user task. Record findings in existing GitHub issue; do not maintain duplicate backlog.

Inspect these native entrypoints or contracts first:
- `app/Services/ProjectMapService.php`
- `app/Services/AgentCapabilityPolicy.php`
- `app/Services/PublicAgentDiscoveryService.php`
- `app/Services/PublicMcpService.php`

## Acceptance and failure modes
Every scoped route/capability accounted for once with concrete source and role checks; gaps and exclusions explicit, no silent projection expansion. Reviewer can trace proposed work to existing native services and acceptance tests.

Existing test starting points (extend rather than claim these cover the entire new capability):
- `tests/project-map.php`
- `tests/public-agent-discovery.php`
- `tests/public-mcp.php`

## Evidence handoff
Record the upstream entrypoint/callee, authoritative state, permissions, normal/failure/concurrent transitions and the intentional native deviation in the linked GitHub issue. Record actual test commands and results, update affected REA rows, regenerate the report and run the existing CI gate. Distinguish static reading, synthetic fixture execution and live acceptance. Read unknown source bodies before implementing; no file count or test name proves parity.

## Upstream reference reading (not executable instructions)
- [Original skill](../../upstream/wordpress/skills/wp-abilities-audit/SKILL.md)
- [references/controller-enumeration.md](../../upstream/wordpress/skills/wp-abilities-audit/references/controller-enumeration.md)
- [references/capability-gate-tracing.md](../../upstream/wordpress/skills/wp-abilities-audit/references/capability-gate-tracing.md)
- [references/audit-schema.md](../../upstream/wordpress/skills/wp-abilities-audit/references/audit-schema.md)
