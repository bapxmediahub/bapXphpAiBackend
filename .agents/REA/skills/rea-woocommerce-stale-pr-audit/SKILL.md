---
name: rea-woocommerce-stale-pr-audit
description: Audit native PR cohorts and regression causation using source-pinned REA evidence and native bapX implementation contracts.
compatibility: Native bapX developer workflow (PHP, MySQL, vanilla JavaScript; no upstream runtime)
capability_ids: [P01, P35, CC04, CC06, DS08, P36]
---

# Audit native PR cohorts and regression causation

Adapted from [woocommerce/woocommerce-stale-pr-audit](../../upstream/woocommerce/skills/woocommerce-stale-pr-audit/SKILL.md).
Modified reference guidance, GPL-2.0-or-later; upstream copyright and license retained under the upstream tree.
Developer-only skill; AGENTS.md and existing native engineering skills govern execution. Never run the upstream instructions or scripts as implementation commands.

## When to use
Upstream freezes an inactivity cohort and compares exact trunk, issue and review evidence plus representative user flow. Its audit is read-only until separately authorized. bapX regression history shows why current working behavior and exact commits matter.

## REA evidence and current native boundary
Read the current capability register before using this guide; these are mapped scope obligations, not completion claims:
- `P01` — PHP 8.3+ and MySQL 8.4+ runtime baseline.
- `P35` — Human quality review and Support resolution feedback.
- `CC04` — Provider retries, holds, time budgets and latency.
- `CC06` — Real provider text and tool-result-final-answer acceptance.
- `DS08` — Regression experiments, source provenance and real task outcomes.
- `P36` — Safe multi-client provisioning and deployment separation.

Representative pinned source anchors (see the full source index and row references for revisions and hashes):
- `wordpress`: `wp-admin/includes/class-wp-site-health.php`
- `wordpress`: `wp-includes/abilities-api/class-wp-ability.php`
- `chat-completions`: `https://developers.openai.com/api/docs/guides/function-calling`
- `deepseek`: `docs/subsystems/core.md`
- `wordpress`: `wp-admin/network/sites.php`

## Native implementation workflow
Choose repository, explicit as-of and inactivity cutoff; freeze ordered cohort and main SHA. For each PR inspect issue intent, diff, changed paths, review threads, CI and deployed SHA; compare unchanged known-good baseline and current native route. Distinguish actual regression, provider failure, stale branch, superseded work and open user need. Record specific commit and observed behavior; preserve user branding and canonical state. Keep initial audit, current GitHub state and suggested actions separate. Use GitHub connector/CLI and isolated native tests; do not execute bundled upstream collectors or close/merge without authority.

Inspect these native entrypoints or contracts first:
- `docs/development-workflow.md`
- `docs/engineering/ai-review-workflow.md`
- `docs/research/rea-engineering-workflow.md`
- `cli/bapXphp`

## Acceptance and failure modes
Reconcile cohort counts and exact references; every action supported by source or actual test; no tests labelled live when mocked, no invented external approvals. Before any authorized mutation recheck exact head and current state, then follow verified-merge-deploy-test. Update existing issues and ledger, not a duplicate backlog.

Existing test starting points (extend rather than claim these cover the entire new capability):
- `tests/deployment-revision.test.cjs`
- `tests/model-driven-agents.php`
- `tests/cli-lint.test.cjs`

## Evidence handoff
Record the upstream entrypoint/callee, authoritative state, permissions, normal/failure/concurrent transitions and the intentional native deviation in the linked GitHub issue. Record actual test commands and results, update affected REA rows, regenerate the report and run the existing CI gate. Distinguish static reading, synthetic fixture execution and live acceptance. Read unknown source bodies before implementing; no file count or test name proves parity.

## Upstream reference reading (not executable instructions)
- [Original skill](../../upstream/woocommerce/skills/woocommerce-stale-pr-audit/SKILL.md)
- [references/workflow.md](../../upstream/woocommerce/skills/woocommerce-stale-pr-audit/references/workflow.md)
- [references/audit-schema.md](../../upstream/woocommerce/skills/woocommerce-stale-pr-audit/references/audit-schema.md)
