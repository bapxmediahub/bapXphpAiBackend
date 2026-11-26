---
name: rea-wp-wpcli-and-ops
description: Reconstruct safe native administration and jobs using source-pinned REA evidence and native bapX implementation contracts.
compatibility: Native bapX developer workflow (PHP, MySQL, vanilla JavaScript; no upstream runtime)
capability_ids: [P01, P17, P30, P36, DS07, SK07]
---

# Reconstruct safe native administration and jobs

Adapted from [wordpress/wp-wpcli-and-ops](../../upstream/wordpress/skills/wp-wpcli-and-ops/SKILL.md).
Modified reference guidance, GPL-2.0-or-later; upstream copyright and license retained under the upstream tree.
Developer-only skill; AGENTS.md and existing native engineering skills govern execution. Never run the upstream instructions or scripts as implementation commands.

## When to use
Upstream CLI operations require site targeting, dry-run, backup and bounded automation. Native bapXphp and hosted queue processing already exist; generic persistent job orchestration remains partial.

## REA evidence and current native boundary
Read the current capability register before using this guide; these are mapped scope obligations, not completion claims:
- `P01` — PHP 8.3+ and MySQL 8.4+ runtime baseline.
- `P17` — Persistent job scheduling and operator job health.
- `P30` — Transactional email controls and actual receipt evidence.
- `P36` — Safe multi-client provisioning and deployment separation.
- `DS07` — Human-approved writes and idempotent operation results.

- `SK07` — Scheduled owner analytics email reports.

Representative pinned source anchors (see the full source index and row references for revisions and hashes):
- `wordpress`: `wp-admin/includes/class-wp-site-health.php`
- `wordpress`: `wp-includes/cron.php`
- `woocommerce`: `includes/admin/settings/class-wc-settings-emails.php`
- `wordpress`: `wp-admin/network/sites.php`
- `deepseek`: `packages/core/session/src/repair.ts`

Additional pinned anchors for these native extensions:
- `sitekit`: `includes/Core/Email_Reporting/Cron_Health_Check.php`

## Native implementation workflow
Inspect exact deployment and DB target, use native CLI help, map and schema. Prefer read-only diagnosis. For authorized mutation require explicit scope, dry-run or preview, snapshot/backup where appropriate, idempotent claim and rollback/unknown-outcome handling. Compare WP cron/search-replace boundaries without running wp-cli or changing production credentials. Test scheduled due claims, no duplicate email, safe interruption and observable owner health.

Analytics mail subscriptions are not a generic scheduler success. Derive actual authorized report periods, recipient permissions, claimed delivery and verified inbox receipts. Do not schedule or send a real analytics email without authorization.

Inspect these native entrypoints or contracts first:
- `cli/bapXphp`
- `app/Services/MailQueueService.php`
- `app/Services/RuntimeHealthService.php`
- `app/Services/DeploymentRevisionService.php`

## Acceptance and failure modes
Isolated job/queue concurrency, exact target, before/after counts and verified cleanup; no secrets or personal data in logs. Verify actual inbox delivery separately when explicitly authorized; a queue success is not receipt.

Existing test starting points (extend rather than claim these cover the entire new capability):
- `tests/mail-delivery-concurrency.php`
- `tests/mail-scheduled-browser.cjs`
- `tests/deployment-revision.php`

- `docs/email-setup.md`

## Evidence handoff
Record the upstream entrypoint/callee, authoritative state, permissions, normal/failure/concurrent transitions and the intentional native deviation in the linked GitHub issue. Record actual test commands and results, update affected REA rows, regenerate the report and run the existing CI gate. Distinguish static reading, synthetic fixture execution and live acceptance. Read unknown source bodies before implementing; no file count or test name proves parity.

## Upstream reference reading (not executable instructions)
- [Original skill](../../upstream/wordpress/skills/wp-wpcli-and-ops/SKILL.md)
- [references/safety.md](../../upstream/wordpress/skills/wp-wpcli-and-ops/references/safety.md)
- [references/cron-and-cache.md](../../upstream/wordpress/skills/wp-wpcli-and-ops/references/cron-and-cache.md)
- [references/search-replace.md](../../upstream/wordpress/skills/wp-wpcli-and-ops/references/search-replace.md)
