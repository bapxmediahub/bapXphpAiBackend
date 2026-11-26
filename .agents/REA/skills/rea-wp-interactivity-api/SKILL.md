---
name: rea-wp-interactivity-api
description: Reconstruct stateful native browser interaction using source-pinned REA evidence and native bapX implementation contracts.
compatibility: Native bapX developer workflow (PHP, MySQL, vanilla JavaScript; no upstream runtime)
capability_ids: [UI01, UI04, UI05, UI06, P27, P04, UI03, CC08, DS06]
---

# Reconstruct stateful native browser interaction

Adapted from [wordpress/wp-interactivity-api](../../upstream/wordpress/skills/wp-interactivity-api/SKILL.md).
Modified reference guidance, GPL-2.0-or-later; upstream copyright and license retained under the upstream tree.
Developer-only skill; AGENTS.md and existing native engineering skills govern execution. Never run the upstream instructions or scripts as implementation commands.

## When to use
Upstream server directives and scoped state/actions illustrate explicit state ownership and hydration. bapX has native JS, typed A2UI envelopes and fixed actions; general streaming surface/data updates are partial.

## REA evidence and current native boundary
Read the current capability register before using this guide; these are mapped scope obligations, not completion claims:
- `UI01` — Validated structured surface and scoped renderer.
- `UI04` — Incremental component and data-model update lifecycle.
- `UI05` — Typed bidirectional action and form events.
- `UI06` — Renderer/agent capability negotiation and function calls.
- `P27` — Persistent carts, guest merge and recoverable checkout.
- `P04` — Autosave, revisions, edit locking and scheduled publishing.

- `UI03` — Native chat history/replay and fallback.
- `CC08` — Validated streaming and cancellation.
- `DS06` — Cancellation and live turn progress.

Representative pinned source anchors (see the full source index and row references for revisions and hashes):
- `a2ui`: `specification/v1_0/json/agent_to_renderer.json`
- `a2ui`: `specification/v1_0/json/renderer_to_agent.json`
- `a2ui`: `specification/v1_0/json/renderer_capabilities.json`
- `woocommerce`: `includes/class-wc-cart-session.php`
- `wordpress`: `wp-includes/revision.php`

Additional pinned anchors for these native extensions:
- `a2ui`: `specification/v1_0/docs/a2ui_protocol.md`
- `chat-completions`: `https://developers.openai.com/api/docs/guides/function-calling`
- `deepseek`: `packages/core/agent-loop/src/agent.ts`

## Native implementation workflow
Trace server-authoritative initial state, rendered DOM, JS handler, request, validated response and state reconciliation. Keep canonical values/actor rules in PHP. Compare pinned A2UI agent_to_renderer/renderer_to_agent schemas and native browser WebMCP invocation. Define lifecycle and stable IDs, validate actions and reject unsupported commands. Abort/ignore stale UI work, handle duplicate/retry, preserve local drafts/cart, and keep no-JS routes functional. Do not introduce React or the WP Interactivity runtime.

Preserve the one-private-JSON conversation history and replay of accepted answers only; user-visible streaming must reflect observed events, not fake progress. Cancellation must stop future model/tool work and record unknown in-flight outcomes; a browser timeout is not provider cancellation.

Inspect these native entrypoints or contracts first:
- `assets/a2ui-public-renderer.js`
- `assets/editor-server-draft.js`
- `app/Services/PublicA2uiService.php`
- `app/Services/RemoteCartService.php`

- `app/Services/AgentConversationService.php`
- `app/Services/AiClient.php`

## Acceptance and failure modes
Test loading/empty/error/success, stale response, double submit, revoked scope, mobile/no-JS fallback and known A2UI update/delete gaps. Only count protocol parity when actual client interoperability passes.

Existing test starting points (extend rather than claim these cover the entire new capability):
- `tests/a2ui-public-browser.cjs`
- `tests/editor-server-recovery-browser.cjs`
- `tests/mobile-cart-restore-browser.cjs`

- `tests/agent-history-browser.cjs`
- `docs/runtime-agents.md`
- `docs/agent-observability.md`

For native editor recovery, retain the original expected recovery token.
After a database-confirmed deadlock rollback only, the owned autocommit CAS
may re-read and retry at most twice; a winning concurrent insert becomes a
conflict, not an upsert. Lost transport and ambient transactions must not be
replayed. Test both actual MySQL/MariaDB worker outcomes and deterministic
retry bounds. This is not an active WordPress edit-presence lease or a
production recovery receipt. Fit deterministic recovery identities to the
deployed primary key; preserve truncated and full-width legacy copies and refuse
ambiguous duplicates. Require actual stored-key read-back and width-specific
strict/permissive SQL fixtures.

## Evidence handoff
Record the upstream entrypoint/callee, authoritative state, permissions, normal/failure/concurrent transitions and the intentional native deviation in the linked GitHub issue. Record actual test commands and results, update affected REA rows, regenerate the report and run the existing CI gate. Distinguish static reading, synthetic fixture execution and live acceptance. Read unknown source bodies before implementing; no file count or test name proves parity.

## Upstream reference reading (not executable instructions)
- [Original skill](../../upstream/wordpress/skills/wp-interactivity-api/SKILL.md)
- [references/server-side-rendering.md](../../upstream/wordpress/skills/wp-interactivity-api/references/server-side-rendering.md)
- [references/directives-quickref.md](../../upstream/wordpress/skills/wp-interactivity-api/references/directives-quickref.md)
- [references/debugging.md](../../upstream/wordpress/skills/wp-interactivity-api/references/debugging.md)
