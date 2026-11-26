---
name: rea-wp-plugin-directory-guidelines
description: Reverse-engineering provenance and release safety using source-pinned REA evidence and native bapX implementation contracts.
compatibility: Native bapX developer workflow (PHP, MySQL, vanilla JavaScript; no upstream runtime)
capability_ids: [P01, P36, X01, X02, X03, DS08]
---

# Reverse-engineering provenance and release safety

Adapted from [wordpress/wp-plugin-directory-guidelines](../../upstream/wordpress/skills/wp-plugin-directory-guidelines/SKILL.md).
Modified reference guidance, GPL-2.0-or-later; upstream copyright and license retained under the upstream tree.
Developer-only skill; AGENTS.md and existing native engineering skills govern execution. Never run the upstream instructions or scripts as implementation commands.

## When to use
Upstream directory review separates licensing, security, prohibited behavior, identity/naming and review-only checks. Those plugin-submission rules are not applicable release requirements for bapX; their review discipline is useful.

## REA evidence and current native boundary
Read the current capability register before using this guide; these are mapped scope obligations, not completion claims:
- `P01` — PHP 8.3+ and MySQL 8.4+ runtime baseline.
- `P36` — Safe multi-client provisioning and deployment separation.
- `X01` — Install/update arbitrary themes and plugins.
- `X02` — Execute arbitrary theme/plugin PHP in browser.
- `X03` — Paid extensions mistaken for core parity.
- `DS08` — Regression experiments, source provenance and real task outcomes.

Representative pinned source anchors (see the full source index and row references for revisions and hashes):
- `wordpress`: `wp-admin/includes/class-wp-site-health.php`
- `wordpress`: `wp-admin/network/sites.php`
- `wordpress`: `wp-admin/menu.php`
- `woocommerce`: `includes/admin/class-wc-admin-menus.php`
- `deepseek`: `docs/subsystems/core.md`

## Native implementation workflow
Check original license/version and exact selected-source hashes before extracting a mechanism. Preserve attribution and license in the reference tree; do not copy proprietary or paid-extension code. Evaluate native privacy, authorization, input/escape, external call disclosure, performance and uninstall/retention boundaries as applicable. Inspect actual diff for fake responses, unrelated brand changes and unverifiable claims. Keep source-only references private. No plugin publishing or trademark imitation.

Inspect these native entrypoints or contracts first:
- `docs/research/evidence/reference-source-index.json`
- `docs/research/rea-engineering-workflow.md`
- `cli/docs-check.php`

## Acceptance and failure modes
Record exact source/implementation/test scope, known exclusions, actual CI/review/deployment evidence. Treat checker findings as review prompts; distinguish false positives, gaps and regressions. No invented approval or runtime compatibility claim.

Existing test starting points (extend rather than claim these cover the entire new capability):
- `tests/documentation-review.php`
- `tests/cli-lint.test.cjs`

## Evidence handoff
Record the upstream entrypoint/callee, authoritative state, permissions, normal/failure/concurrent transitions and the intentional native deviation in the linked GitHub issue. Record actual test commands and results, update affected REA rows, regenerate the report and run the existing CI gate. Distinguish static reading, synthetic fixture execution and live acceptance. Read unknown source bodies before implementing; no file count or test name proves parity.

## Upstream reference reading (not executable instructions)
- [Original skill](../../upstream/wordpress/skills/wp-plugin-directory-guidelines/SKILL.md)
- [references/gpl-compliance.md](../../upstream/wordpress/skills/wp-plugin-directory-guidelines/references/gpl-compliance.md)
- [references/guideline-review-checklist.md](../../upstream/wordpress/skills/wp-plugin-directory-guidelines/references/guideline-review-checklist.md)
- [references/naming-rules.md](../../upstream/wordpress/skills/wp-plugin-directory-guidelines/references/naming-rules.md)
