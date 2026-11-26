---
name: rea-wp-phpstan
description: Reconstruct native static quality and typed boundaries using source-pinned REA evidence and native bapX implementation contracts.
compatibility: Native bapX developer workflow (PHP, MySQL, vanilla JavaScript; no upstream runtime)
capability_ids: [P01, P33, CC02, DS08]
---

# Reconstruct native static quality and typed boundaries

Adapted from [wordpress/wp-phpstan](../../upstream/wordpress/skills/wp-phpstan/SKILL.md).
Modified reference guidance, GPL-2.0-or-later; upstream copyright and license retained under the upstream tree.
Developer-only skill; AGENTS.md and existing native engineering skills govern execution. Never run the upstream instructions or scripts as implementation commands.

## When to use
Upstream PHPStan workflow discovers tooling, uses accurate types and treats baselines as migrations rather than suppression. Native app uses PHP syntax, typed services, generated maps and actual tests; WP stubs would hide errors.

## REA evidence and current native boundary
Read the current capability register before using this guide; these are mapped scope obligations, not completion claims:
- `P01` — PHP 8.3+ and MySQL 8.4+ runtime baseline.
- `P33` — Shared typed capability catalogue and cross-transport schema parity.
- `CC02` — Chat Completions message roles and correlated tool calls.
- `DS08` — Regression experiments, source provenance and real task outcomes.

Representative pinned source anchors (see the full source index and row references for revisions and hashes):
- `wordpress`: `wp-admin/includes/class-wp-site-health.php`
- `wordpress`: `wp-includes/abilities-api/class-wp-ability.php`
- `chat-completions`: `https://developers.openai.com/api/docs/guides/function-calling`
- `deepseek`: `docs/subsystems/core.md`

## Native implementation workflow
Use existing ./bapXphp ci and PHP parser, inspect typed service/DTO boundary, nullable and JSON shapes, exact status transitions and return values. If a first-party static analyzer already exists, use its documented config; do not install WP stubs or require Composer/Node on Hostinger. Trace unknown symbols to actual source, narrow deliberate annotations; never silence a real failure with broad ignores. Keep source/evidence fingerprints and generated route/schema maps consistent.

Inspect these native entrypoints or contracts first:
- `cli/bapXphp`
- `app/Services/ProjectMapService.php`
- `app/Services/AgentModelTurn.php`
- `app/Services/SchemaService.php`

## Acceptance and failure modes
Native parser catches malformed PHP, unit/controller tests cover invalid inputs, real HTTP and DB cases where applicable. Map diagnostics to exact source and fix rather than suppress. If additional analyzer is not installed, report not run.

Existing test starting points (extend rather than claim these cover the entire new capability):
- `tests/cli-lint.test.cjs`
- `tests/project-map.php`
- `tests/model-driven-agents.php`

## Evidence handoff
Record the upstream entrypoint/callee, authoritative state, permissions, normal/failure/concurrent transitions and the intentional native deviation in the linked GitHub issue. Record actual test commands and results, update affected REA rows, regenerate the report and run the existing CI gate. Distinguish static reading, synthetic fixture execution and live acceptance. Read unknown source bodies before implementing; no file count or test name proves parity.

## Upstream reference reading (not executable instructions)
- [Original skill](../../upstream/wordpress/skills/wp-phpstan/SKILL.md)
- [references/configuration.md](../../upstream/wordpress/skills/wp-phpstan/references/configuration.md)
- [references/third-party-classes.md](../../upstream/wordpress/skills/wp-phpstan/references/third-party-classes.md)
