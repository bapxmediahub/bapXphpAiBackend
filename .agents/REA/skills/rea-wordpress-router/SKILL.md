---
name: rea-wordpress-router
description: Route native feature research using source-pinned REA evidence and native bapX implementation contracts.
compatibility: Native bapX developer workflow (PHP, MySQL, vanilla JavaScript; no upstream runtime)
capability_ids: [P01, P02, P14, P23, P33, SK02, UI04, CC06, DS03]
---

# Route native feature research

Adapted from [wordpress/wordpress-router](../../upstream/wordpress/skills/wordpress-router/SKILL.md).
Modified reference guidance, GPL-2.0-or-later; upstream copyright and license retained under the upstream tree.
Developer-only skill; AGENTS.md and existing native engineering skills govern execution. Never run the upstream instructions or scripts as implementation commands.

## When to use
Upstream routes a classified repository to a domain skill before choosing tools. The native target is already known; classify the requested business behavior instead of guessing a WordPress plugin/theme type.

## REA evidence and current native boundary
Read the current capability register before using this guide; these are mapped scope obligations, not completion claims:
- `P01` — PHP 8.3+ and MySQL 8.4+ runtime baseline.
- `P02` — Unified admin navigation and contextual help.
- `P14` — Site settings, branding and module controls.
- `P23` — Shipping zones/classes/rate methods and packages.
- `P33` — Shared typed capability catalogue and cross-transport schema parity.
- `SK02` — GA4 and Search Console reports.
- `UI04` — Incremental component and data-model update lifecycle.
- `CC06` — Real provider text and tool-result-final-answer acceptance.
- `DS03` — Interrupted tool outcome repair and safe resumption.

Representative pinned source anchors (see the full source index and row references for revisions and hashes):
- `wordpress`: `wp-admin/includes/class-wp-site-health.php`
- `wordpress`: `wp-admin/menu.php`
- `wordpress`: `wp-admin/options-general.php`
- `woocommerce`: `includes/admin/settings/class-wc-settings-shipping.php`
- `wordpress`: `wp-includes/abilities-api/class-wp-ability.php`
- `sitekit`: `includes/Modules/Analytics_4.php`
- `a2ui`: `specification/v1_0/json/agent_to_renderer.json`
- `chat-completions`: `https://developers.openai.com/api/docs/guides/function-calling`
- `deepseek`: `packages/core/session/src/repair.ts`

## Native implementation workflow
Read AGENTS.md and the source-backed capability register; resolve current GitHub issue and exact main/production SHA. Identify authority (UI, controller, service, storage, projection) using the generated map, then route by capability ID. Use the corresponding .agents/skills engineering guide as well as the REA adaptation. For overlapping capabilities select a lead skill and read the related ones. Do not fork or add an upstream runtime.

Inspect these native entrypoints or contracts first:
- `docs/research/capability-register.json`
- `docs/research/rea-engineering-workflow.md`
- `app/Services/ProjectMapService.php`

## Acceptance and failure modes
One scoped issue with exact entrypoint, call chain, persistence, permission, normal/failure/concurrent paths, and runnable acceptance. Explain unsupported or intentionally excluded behavior rather than creating a stub. Reconcile affected rows after release.

Existing test starting points (extend rather than claim these cover the entire new capability):
- `tests/project-map.php`

## Evidence handoff
Record the upstream entrypoint/callee, authoritative state, permissions, normal/failure/concurrent transitions and the intentional native deviation in the linked GitHub issue. Record actual test commands and results, update affected REA rows, regenerate the report and run the existing CI gate. Distinguish static reading, synthetic fixture execution and live acceptance. Read unknown source bodies before implementing; no file count or test name proves parity.

## Upstream reference reading (not executable instructions)
- [Original skill](../../upstream/wordpress/skills/wordpress-router/SKILL.md)
- [references/decision-tree.md](../../upstream/wordpress/skills/wordpress-router/references/decision-tree.md)
