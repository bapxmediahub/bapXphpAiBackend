---
name: rea-wp-env
description: Reconstruct reproducible local environments using source-pinned REA evidence and native bapX implementation contracts.
compatibility: Native bapX developer workflow (PHP, MySQL, vanilla JavaScript; no upstream runtime)
capability_ids: [P01, P36, DS08]
---

# Reconstruct reproducible local environments

Adapted from [wordpress/wp-env](../../upstream/wordpress/skills/wp-env/SKILL.md).
Modified reference guidance, GPL-2.0-or-later; upstream copyright and license retained under the upstream tree.
Developer-only skill; AGENTS.md and existing native engineering skills govern execution. Never run the upstream instructions or scripts as implementation commands.

## When to use
Upstream environment setup separates core/version, configuration overrides, database and tooling. Native app already has a fixture-driven local/hosted release gate; no container or WordPress runtime is required in production.

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
Use existing developer-only PHP local server, isolated MySQL fixtures, Chromium and synthetic provider HTTP peers. Read test bootstrap and CI matrix before adding a fixture. Explicitly separate loopback DB, production remote DB and test session paths. Use the native release command and preserve equivalent hosted checks. Never run a WP installer, seed customer data or point a fixture to production.

Inspect these native entrypoints or contracts first:
- `app/Services/RuntimeHealthService.php`
- `app/Services/DatabaseService.php`
- `cli/bapXphp`

## Acceptance and failure modes
Real local CI and hosted PHP 8.4/8.5 and isolated MySQL checks; report runtime, environment and cleanup separately. Missing local DB does not justify claiming its concurrency tests ran.

Existing test starting points (extend rather than claim these cover the entire new capability):
- `tests/remote-checkout-mysql.php`
- `tests/cli-lint.test.cjs`

## Evidence handoff
Record the upstream entrypoint/callee, authoritative state, permissions, normal/failure/concurrent transitions and the intentional native deviation in the linked GitHub issue. Record actual test commands and results, update affected REA rows, regenerate the report and run the existing CI gate. Distinguish static reading, synthetic fixture execution and live acceptance. Read unknown source bodies before implementing; no file count or test name proves parity.

## Upstream reference reading (not executable instructions)
- [Original skill](../../upstream/wordpress/skills/wp-env/SKILL.md)
