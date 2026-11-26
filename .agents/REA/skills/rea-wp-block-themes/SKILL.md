---
name: rea-wp-block-themes
description: Reconstruct layout, template and design settings using source-pinned REA evidence and native bapX implementation contracts.
compatibility: Native bapX developer workflow (PHP, MySQL, vanilla JavaScript; no upstream runtime)
capability_ids: [P05, P14, P15, P36]
---

# Reconstruct layout, template and design settings

Adapted from [wordpress/wp-block-themes](../../upstream/wordpress/skills/wp-block-themes/SKILL.md).
Modified reference guidance, GPL-2.0-or-later; upstream copyright and license retained under the upstream tree.
Developer-only skill; AGENTS.md and existing native engineering skills govern execution. Never run the upstream instructions or scripts as implementation commands.

## When to use
Upstream theme JSON, template parts, precedence and style variations separate global design choices from content. Native Settings/Appearance and authored pages exist; arbitrary theme/plugin installation is excluded, reusable layouts remain partial.

## REA evidence and current native boundary
Read the current capability register before using this guide; these are mapped scope obligations, not completion claims:
- `P05` — General pages, reusable layouts and content hierarchy.
- `P14` — Site settings, branding and module controls.
- `P15` — Editable navigation, layout templates and global design controls.
- `P36` — Safe multi-client provisioning and deployment separation.

Representative pinned source anchors (see the full source index and row references for revisions and hashes):
- `wordpress`: `wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php`
- `wordpress`: `wp-admin/options-general.php`
- `wordpress`: `wp-admin/nav-menus.php`
- `wordpress`: `wp-admin/network/sites.php`

## Native implementation workflow
Inspect Design.md and the active shared layout, header-menu/settings services and content record schema. Trace how the reference selects templates, resolves defaults and overrides, and reuses parts. Adapt only whitelisted native tokens, layout selections and typed metadata; preserve client identity and current approved header/footer. Keep native PHP views server-rendered with progressive JS, not theme packages. Plan compatibility for old saved selections before changing schema.

Inspect these native entrypoints or contracts first:
- `views/layouts/app.php`
- `views/admin/header-menu.php`
- `app/Services/SiteProfileService.php`
- `app/Services/SettingsService.php`

## Acceptance and failure modes
Synthetic second-site settings test, actual desktop and mobile header/menu/footer, readable contrast and no JS errors; unknown layout falls back safely without overwriting saved content. Document intentional no-theme-runtime boundary.

Existing test starting points (extend rather than claim these cover the entire new capability):
- `tests/site-profile-browser.cjs`
- `tests/header-menu-browser.cjs`
- `tests/homepage-release-browser.cjs`

## Evidence handoff
Record the upstream entrypoint/callee, authoritative state, permissions, normal/failure/concurrent transitions and the intentional native deviation in the linked GitHub issue. Record actual test commands and results, update affected REA rows, regenerate the report and run the existing CI gate. Distinguish static reading, synthetic fixture execution and live acceptance. Read unknown source bodies before implementing; no file count or test name proves parity.

## Upstream reference reading (not executable instructions)
- [Original skill](../../upstream/wordpress/skills/wp-block-themes/SKILL.md)
- [references/theme-json.md](../../upstream/wordpress/skills/wp-block-themes/references/theme-json.md)
- [references/templates-and-parts.md](../../upstream/wordpress/skills/wp-block-themes/references/templates-and-parts.md)
- [references/style-variations.md](../../upstream/wordpress/skills/wp-block-themes/references/style-variations.md)
