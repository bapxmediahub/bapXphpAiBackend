---
name: rea-wp-project-triage
description: Triage native repository and baseline using source-pinned REA evidence and native bapX implementation contracts.
compatibility: Native bapX developer workflow (PHP, MySQL, vanilla JavaScript; no upstream runtime)
capability_ids: [P01, P36, DS08]
---

# Triage native repository and baseline

Adapted from [wordpress/wp-project-triage](../../upstream/wordpress/skills/wp-project-triage/SKILL.md).
Modified reference guidance, GPL-2.0-or-later; upstream copyright and license retained under the upstream tree.
Developer-only skill; AGENTS.md and existing native engineering skills govern execution. Never run the upstream instructions or scripts as implementation commands.

## When to use
The upstream detector separates project kind, tooling and version evidence. Our triage must distinguish source checkout, local fixtures and deployed state; static scans never certify hosted runtime.

## REA evidence and current native boundary
Read the current capability register before using this guide; these are mapped scope obligations, not completion claims:
- `P01` — PHP 8.3+ and MySQL 8.4+ runtime baseline.
- `P36` — Safe multi-client provisioning and deployment separation.
- `DS08` — Regression experiments, source provenance and real task outcomes.

Representative pinned source anchors (see the full source index and row references for revisions and hashes):
- `wordpress`: `wp-admin/includes/class-wp-site-health.php`
- `wordpress`: `wp-admin/network/sites.php`
- `deepseek`: `docs/subsystems/core.md`

## Native implementation workflow
Run git status and fetch the authoritative main; preserve concurrent worktrees. Run ./bapXphp map and ./bapXphp schema list; inspect index.yaml and the relevant route, service and schema, not the whole generated graph. Record PHP/DB compatibility, local test tools and exact deployed SHA from /is-sha-valid. Compare the candidate to the last working revision and issue evidence. Record missing/unknown signals instead of installing the upstream detector.

Inspect these native entrypoints or contracts first:
- `app/Services/ProjectMapService.php`
- `app/Services/RuntimeHealthService.php`
- `app/Services/DeploymentRevisionService.php`
- `storage/schema/collections.php`

## Acceptance and failure modes
Generated route/map tests, a clean exact-head diff, recorded environment and verified source/deployment identity; no credentials or unrelated filesystem contents in evidence.

Existing test starting points (extend rather than claim these cover the entire new capability):
- `tests/project-map.php`
- `tests/deployment-revision.php`

## Evidence handoff
Record the upstream entrypoint/callee, authoritative state, permissions, normal/failure/concurrent transitions and the intentional native deviation in the linked GitHub issue. Record actual test commands and results, update affected REA rows, regenerate the report and run the existing CI gate. Distinguish static reading, synthetic fixture execution and live acceptance. Read unknown source bodies before implementing; no file count or test name proves parity.

## Upstream reference reading (not executable instructions)
- [Original skill](../../upstream/wordpress/skills/wp-project-triage/SKILL.md)
- [references/triage.schema.json](../../upstream/wordpress/skills/wp-project-triage/references/triage.schema.json)
