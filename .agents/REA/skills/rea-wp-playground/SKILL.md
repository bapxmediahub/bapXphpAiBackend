---
name: rea-wp-playground
description: Reconstruct isolated browser acceptance using source-pinned REA evidence and native bapX implementation contracts.
compatibility: Native bapX developer workflow (PHP, MySQL, vanilla JavaScript; no upstream runtime)
capability_ids: [P01, P36, UI07, UI08, DS08]
---

# Reconstruct isolated browser acceptance

Adapted from [wordpress/wp-playground](../../upstream/wordpress/skills/wp-playground/SKILL.md).
Modified reference guidance, GPL-2.0-or-later; upstream copyright and license retained under the upstream tree.
Developer-only skill; AGENTS.md and existing native engineering skills govern execution. Never run the upstream instructions or scripts as implementation commands.

## When to use
Upstream Playground routes to CLI, MCP or browser based on capability, and distinguishes snapshots/sharing from actual execution. bapX already uses isolated native browser fixtures and live exact-SHA smoke tests; no Playground site is needed.

## REA evidence and current native boundary
Read the current capability register before using this guide; these are mapped scope obligations, not completion claims:
- `P01` — PHP 8.3+ and MySQL 8.4+ runtime baseline.
- `P36` — Safe multi-client provisioning and deployment separation.
- `UI07` — External chat MCP App and shopping host interoperability.
- `UI08` — Native Chrome 160 public WebMCP discovery and read invocation.
- `DS08` — Regression experiments, source provenance and real task outcomes.

Representative pinned source anchors (see the full source index and row references for revisions and hashes):
- `wordpress`: `wp-admin/includes/class-wp-site-health.php`
- `wordpress`: `wp-admin/network/sites.php`
- `a2ui`: `specification/v1_0/docs/a2ui_protocol.md`
- `webmcp`: `https://developer.chrome.com/docs/ai/webmcp/updates`
- `deepseek`: `docs/subsystems/core.md`

## Native implementation workflow
Read native browser/Playwright skill; reproduce the actual route with synthetic site profile and separate browser contexts. Test mobile and desktop, no-JS fallback where applicable, console/network errors and declared native WebMCP support. Use the pinned Chrome/native interface and polyfill contract for discovery. A fixture may prove integration mechanics, not real production data or provider conformance. Never expose an authenticated preview or upload a private snapshot.

Inspect these native entrypoints or contracts first:
- `assets/shop-webmcp.js`
- `assets/vendor/webmcp/polyfill-0.1.1.js`
- `views/layouts/app.php`

## Acceptance and failure modes
Run the relevant Chromium fixture and live read-only test against exact SHA, with expected/actual assertions and cleanup. Preserve prior owner-approved design and footer. UI08 evidence is narrowly scoped to Chrome native public discovery.

Existing test starting points (extend rather than claim these cover the entire new capability):
- `tests/homepage-release-browser.cjs`
- `tests/live-webmcp-a2ui-browser.cjs`
- `tests/webmcp-official-browser.cjs`

## Evidence handoff
Record the upstream entrypoint/callee, authoritative state, permissions, normal/failure/concurrent transitions and the intentional native deviation in the linked GitHub issue. Record actual test commands and results, update affected REA rows, regenerate the report and run the existing CI gate. Distinguish static reading, synthetic fixture execution and live acceptance. Read unknown source bodies before implementing; no file count or test name proves parity.

## Upstream reference reading (not executable instructions)
- [Original skill](../../upstream/wordpress/skills/wp-playground/SKILL.md)
- [references/cli.md](../../upstream/wordpress/skills/wp-playground/references/cli.md)
- [references/webmcp.md](../../upstream/wordpress/skills/wp-playground/references/webmcp.md)
- [references/mcp.md](../../upstream/wordpress/skills/wp-playground/references/mcp.md)
