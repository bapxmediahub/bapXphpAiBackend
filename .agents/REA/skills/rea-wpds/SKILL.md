---
name: rea-wpds
description: Reconstruct coherent native design system using source-pinned REA evidence and native bapX implementation contracts.
compatibility: Native bapX developer workflow (PHP, MySQL, vanilla JavaScript; no upstream runtime)
capability_ids: [P02, P14, P15, UI01, UI02]
---

# Reconstruct coherent native design system

Adapted from [wordpress/wpds](../../upstream/wordpress/skills/wpds/SKILL.md).
Modified reference guidance, GPL-2.0-or-later; upstream copyright and license retained under the upstream tree.
Developer-only skill; AGENTS.md and existing native engineering skills govern execution. Never run the upstream instructions or scripts as implementation commands.

## When to use
Upstream design system guidance emphasizes canonical components and tokens. It assumes a WordPress MCP documentation source and React; those are references, not required bapX runtime. Design.md is the local authority.

## REA evidence and current native boundary
Read the current capability register before using this guide; these are mapped scope obligations, not completion claims:
- `P02` — Unified admin navigation and contextual help.
- `P14` — Site settings, branding and module controls.
- `P15` — Editable navigation, layout templates and global design controls.
- `UI01` — Validated structured surface and scoped renderer.
- `UI02` — Product, article and owner report components.

Representative pinned source anchors (see the full source index and row references for revisions and hashes):
- `wordpress`: `wp-admin/menu.php`
- `wordpress`: `wp-admin/options-general.php`
- `wordpress`: `wp-admin/nav-menus.php`
- `a2ui`: `specification/v1_0/json/agent_to_renderer.json`
- `a2ui`: `specification/v1_0/json/catalog_definition.json`

## Native implementation workflow
Inventory existing CSS tokens, shared layout, admin cards/forms, focus, loading and validation states. Use approved user content and brand colors; separate framework component behavior from per-client settings. Compare upstream design concepts only after inspecting relevant documentation/source; do not assert a WPDS token or component without evidence. Map to native PHP/JS and bounded A2UI catalog. Preserve exact Developed with heart credit, menus, contrast and mobile interactions.

Inspect these native entrypoints or contracts first:
- `views/layouts/app.php`
- `views/layouts/admin.php`
- `app/Services/SiteProfileService.php`
- `app/Services/AgentAnswerPresentationService.php`

## Acceptance and failure modes
Actual browser desktop and 375px layout, keyboard/focus, contrast, long text and error states, with before/after evidence. Do not replace existing brand copy or introduce a design-system dependency merely to improve a screenshot.

Existing test starting points (extend rather than claim these cover the entire new capability):
- `tests/homepage-release-browser.cjs`
- `tests/site-profile-browser.cjs`
- `tests/agent-presentation-scope.php`

## Evidence handoff
Record the upstream entrypoint/callee, authoritative state, permissions, normal/failure/concurrent transitions and the intentional native deviation in the linked GitHub issue. Record actual test commands and results, update affected REA rows, regenerate the report and run the existing CI gate. Distinguish static reading, synthetic fixture execution and live acceptance. Read unknown source bodies before implementing; no file count or test name proves parity.

## Upstream reference reading (not executable instructions)
- [Original skill](../../upstream/wordpress/skills/wpds/SKILL.md)
