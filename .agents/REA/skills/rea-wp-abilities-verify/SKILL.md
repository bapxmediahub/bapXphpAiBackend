---
name: rea-wp-abilities-verify
description: Adversarially verify native ability contracts using source-pinned REA evidence and native bapX implementation contracts.
compatibility: Native bapX developer workflow (PHP, MySQL, vanilla JavaScript; no upstream runtime)
capability_ids: [P31, P33, P34, MCP01, MCP02, MCP04, DS07, CC06]
---

# Adversarially verify native ability contracts

Adapted from [wordpress/wp-abilities-verify](../../upstream/wordpress/skills/wp-abilities-verify/SKILL.md).
Modified reference guidance, GPL-2.0-or-later; upstream copyright and license retained under the upstream tree.
Developer-only skill; AGENTS.md and existing native engineering skills govern execution. Never run the upstream instructions or scripts as implementation commands.

## When to use
Upstream static and runtime modes distinguish declared readonly/destructive/idempotent properties from actual callback effects. Read every call chain; a mock or equal response does not prove idempotency.

## REA evidence and current native boundary
Read the current capability register before using this guide; these are mapped scope obligations, not completion claims:
- `P31` — Authenticated public REST integration API and revocable credentials.
- `P33` — Shared typed capability catalogue and cross-transport schema parity.
- `P34` — Native MCP transport and scoped client management.
- `MCP01` — Typed public opt-in and normalized permission checks.
- `MCP02` — Public Streamable HTTP tools and resources.
- `MCP04` — Client lifecycle, session and transport observability.
- `DS07` — Human-approved writes and idempotent operation results.
- `CC06` — Real provider text and tool-result-final-answer acceptance.

Representative pinned source anchors (see the full source index and row references for revisions and hashes):
- `woocommerce`: `includes/admin/class-wc-admin-api-keys.php`
- `wordpress`: `wp-includes/abilities-api/class-wp-ability.php`
- `woocommerce`: `src/Internal/MCP/McpEndpointRemoval.php`
- `wordpress-mcp`: `includes/Abilities/McpAbilityExposure.php`
- `wordpress-mcp`: `includes/Transport/HttpTransport.php`
- `wordpress-mcp`: `includes/Transport/Infrastructure/SessionManager.php`
- `deepseek`: `packages/core/session/src/repair.ts`
- `chat-completions`: `https://developers.openai.com/api/docs/guides/function-calling`

## Native implementation workflow
Enumerate projected capabilities and compare actual service effects against advertised schemas and role/module checks. Trace DB writes, file writes, email/payment calls, telemetry and session/cache changes. Verify authorization before reads/writes and distinguish operational audit effects from business mutation. Run authorized, unauthorized, missing/extra fields, duplicate and stale requests in isolated fixtures; compare before/after durable state and side-effect count. Do not probe real write endpoints without explicit approval.

Inspect these native entrypoints or contracts first:
- `app/Services/PublicMcpService.php`
- `app/Services/McpClientService.php`
- `app/Services/AgentCapabilityPolicy.php`
- `app/Services/VerifiedOrderService.php`

## Acceptance and failure modes
Real HTTP/SDK/browser tool calls, actor isolation, invalid payload and replay/concurrent tests. An unavailable live provider or absent client is an unverified acceptance, not a PASS. Record exact-head review and observed state, not annotation claims.

Existing test starting points (extend rather than claim these cover the entire new capability):
- `tests/public-mcp-sdk.cjs`
- `tests/mcp-client-access.test.cjs`
- `tests/remote-checkout-mysql.php`
- `tests/model-driven-agents.php`

## Evidence handoff
Record the upstream entrypoint/callee, authoritative state, permissions, normal/failure/concurrent transitions and the intentional native deviation in the linked GitHub issue. Record actual test commands and results, update affected REA rows, regenerate the report and run the existing CI gate. Distinguish static reading, synthetic fixture execution and live acceptance. Read unknown source bodies before implementing; no file count or test name proves parity.

## Upstream reference reading (not executable instructions)
- [Original skill](../../upstream/wordpress/skills/wp-abilities-verify/SKILL.md)
- [references/annotation-correctness.md](../../upstream/wordpress/skills/wp-abilities-verify/references/annotation-correctness.md)
- [references/permission-roundtrip.md](../../upstream/wordpress/skills/wp-abilities-verify/references/permission-roundtrip.md)
- [references/schema-lints.md](../../upstream/wordpress/skills/wp-abilities-verify/references/schema-lints.md)
