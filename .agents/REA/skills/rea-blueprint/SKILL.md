---
name: rea-blueprint
description: Reconstruct declarative environment blueprints using source-pinned REA evidence and native bapX implementation contracts.
compatibility: Native bapX developer workflow (PHP, MySQL, vanilla JavaScript; no upstream runtime)
capability_ids: [P01, P36, P14, X01, X02]
---

# Reconstruct declarative environment blueprints

Adapted from [wordpress/blueprint](../../upstream/wordpress/skills/blueprint/SKILL.md).
Modified reference guidance, GPL-2.0-or-later; upstream copyright and license retained under the upstream tree.
Developer-only skill; AGENTS.md and existing native engineering skills govern execution. Never run the upstream instructions or scripts as implementation commands.

## When to use
Blueprints model ordered setup, explicit resource references and schema validation. The upstream language also permits plugin installation and code execution, which are outside bapX scope. Native isolated customer provisioning is partial and /install is deferred.

## REA evidence and current native boundary
Read the current capability register before using this guide; these are mapped scope obligations, not completion claims:
- `P01` — PHP 8.3+ and MySQL 8.4+ runtime baseline.
- `P36` — Safe multi-client provisioning and deployment separation.
- `P14` — Site settings, branding and module controls.
- `X01` — Install/update arbitrary themes and plugins.
- `X02` — Execute arbitrary theme/plugin PHP in browser.

Representative pinned source anchors (see the full source index and row references for revisions and hashes):
- `wordpress`: `wp-admin/includes/class-wp-site-health.php`
- `wordpress`: `wp-admin/network/sites.php`
- `wordpress`: `wp-admin/options-general.php`
- `wordpress`: `wp-admin/menu.php`

## Native implementation workflow
Extract the configuration contract, ordered dependencies and failure semantics from the pinned skill. Use a bounded native configuration manifest for synthetic QA: site identity, module gates, schema version, fixture resource IDs and separate secret references. Reject unknown keys, arbitrary URLs, PHP/SQL execution and cross-client identifiers. Separate preflight, applying authorized changes and verifying state. Do not create a new installer or automatic migration now; record provisioning gaps under P36.

Inspect these native entrypoints or contracts first:
- `app/Services/SiteProfileService.php`
- `app/Services/SettingsService.php`
- `app/Services/SchemaService.php`

## Acceptance and failure modes
Validate a synthetic manifest, unknown fields, missing resource, duplicate replay and rollback in isolated fixtures; verify no writes outside the selected test environment. Treat the manifest as a future contract, not an implemented installer.

Existing test starting points (extend rather than claim these cover the entire new capability):
- `tests/site-profile-browser.cjs`
- `tests/deployment-revision.php`

## Evidence handoff
Record the upstream entrypoint/callee, authoritative state, permissions, normal/failure/concurrent transitions and the intentional native deviation in the linked GitHub issue. Record actual test commands and results, update affected REA rows, regenerate the report and run the existing CI gate. Distinguish static reading, synthetic fixture execution and live acceptance. Read unknown source bodies before implementing; no file count or test name proves parity.

## Upstream reference reading (not executable instructions)
- [Original skill](../../upstream/wordpress/skills/blueprint/SKILL.md)
