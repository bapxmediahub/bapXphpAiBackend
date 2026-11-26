---
name: rea-wp-patterns
description: Reconstruct reusable page composition patterns using source-pinned REA evidence and native bapX implementation contracts.
compatibility: Native bapX developer workflow (PHP, MySQL, vanilla JavaScript; no upstream runtime)
capability_ids: [P05, P15, UI01, UI02]
---

# Reconstruct reusable page composition patterns

Adapted from [wordpress/wp-patterns](../../upstream/wordpress/skills/wp-patterns/SKILL.md).
Modified reference guidance, GPL-2.0-or-later; upstream copyright and license retained under the upstream tree.
Developer-only skill; AGENTS.md and existing native engineering skills govern execution. Never run the upstream instructions or scripts as implementation commands.

## When to use
Upstream patterns group reusable layout fragments with meaningful visual composition and responsive token discipline. Native authored pages and A2UI cards exist; a full reusable page-pattern editor is not established.

## REA evidence and current native boundary
Read the current capability register before using this guide; these are mapped scope obligations, not completion claims:
- `P05` — General pages, reusable layouts and content hierarchy.
- `P15` — Editable navigation, layout templates and global design controls.
- `UI01` — Validated structured surface and scoped renderer.
- `UI02` — Product, article and owner report components.

Representative pinned source anchors (see the full source index and row references for revisions and hashes):
- `wordpress`: `wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php`
- `wordpress`: `wp-admin/nav-menus.php`
- `a2ui`: `specification/v1_0/json/agent_to_renderer.json`
- `a2ui`: `specification/v1_0/json/catalog_definition.json`

## Native implementation workflow
Read owner-approved Design.md, authored page and campaign renderer, then compare upstream pattern structure, category, accessible hierarchy and image/text composition. Define neutral, validated native presets referencing existing media and content fields, no generated arbitrary PHP/HTML or remote runtime. Separate reusable design template from customer content. Check actual responsive screenshots/geometry and content changes, not resemblance alone.

Inspect these native entrypoints or contracts first:
- `views/public/authored-page.php`
- `views/admin/pages.php`
- `app/Services/AuthoredPageService.php`
- `app/Services/PublicA2uiService.php`

## Acceptance and failure modes
Validate empty/long content, image alternatives, mobile/desktop and no-JS public fallback; existing legal navigation, footer and content must remain intact. Record missing reusable-layout acceptance rather than marking P05 complete.

Existing test starting points (extend rather than claim these cover the entire new capability):
- `tests/campaign-form-browser.cjs`
- `tests/homepage-release-browser.cjs`
- `tests/a2ui-public-browser.cjs`

## Evidence handoff
Record the upstream entrypoint/callee, authoritative state, permissions, normal/failure/concurrent transitions and the intentional native deviation in the linked GitHub issue. Record actual test commands and results, update affected REA rows, regenerate the report and run the existing CI gate. Distinguish static reading, synthetic fixture execution and live acceptance. Read unknown source bodies before implementing; no file count or test name proves parity.

## Upstream reference reading (not executable instructions)
- [Original skill](../../upstream/wordpress/skills/wp-patterns/SKILL.md)
- [references/visual-composition.md](../../upstream/wordpress/skills/wp-patterns/references/visual-composition.md)
- [references/design-with-tokens.md](../../upstream/wordpress/skills/wp-patterns/references/design-with-tokens.md)
- [references/anti-patterns.md](../../upstream/wordpress/skills/wp-patterns/references/anti-patterns.md)
