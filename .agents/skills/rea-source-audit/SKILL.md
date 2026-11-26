---
name: rea-source-audit
description: Use for evidence-based REA comparisons of open-source agent, support, telemetry or commerce code with this PHP repository.
---
# REA source audit

Follow `AGENTS.md`; read the original source behind the index before changing code.

## Start with the installed contract

Run `rea --help` (not `rea -help`), command-specific help, and the installed REA
skill documentation. Check runtime compatibility without changing global tooling.
Keep public reference clones and raw evidence outside the production checkout;
retain sanitized findings, source hashes and relative paths in the versioned
capability register and reference-source index;
never execute their package scripts or install their runtime as part of reading.

Pin each upstream commit and license. Exclude enterprise/commercial code paths
from free adoption recommendations. Compare DeepSeek Harness execution patterns,
Langfuse core observations/evaluations, Chatwoot core support workflows, and Bagisto
PHP commerce only where the actual source proves the feature.

## Evidence and claims

- `import-reference-source` inventories/hashes source; an import does not execute
  PHP/Ruby or prove runtime behavior. Preserve `historical-reference` and partial
  inventory flags. No parse failure is not a complete semantic proof.
- Use bounded `analyze-javascript-application` for supported JS/TS and retain full
  evidence. `trace-application-feature` must bind to that evidence and the literal
  source/module question. A no-match is not evidence that a feature is absent.
- Browser scenarios must declare each action. Start credential-free on public
  pages; capture no secrets, request bodies or customer conversations. Retain
  failed selectors, capture truncation and cleanup status; do not overstate
  event completeness when pending requests remain.
- Separate main, draft PR, dirty local worktree and live deployment observations.
  A reopened History view is not proof that the model receives previous turns.
- Publish a missing/partial/present matrix with local source symbols, pinned
  upstream paths, priority and acceptance tests. Preserve existing payment/GST/
  refund/idempotency safeguards rather than claiming they are absent.

## Reconstruct behavior, not a feature name

For every reference component, trace actual entrypoints and callees, input and
output shapes, state/persistence, actor/permission checks, normal/error/concurrent
transitions and recovery. Read the pinned source bodies and tests, then map
the invariants and intentional native deviations to executable regressions.
Do not substitute source inventories, screenshots, benchmark success or a
component scaffold for the complete working mechanism. Preserve unrelated
owner-approved content and design during adaptation.

## Engineering constraints

Native PHP remains the runtime. Business records remain MySQL. Agent conversations
remain one private JSON file per chat, not a new SQL table. Adapt observed contracts
through scoped PRs and deterministic tests; do not copy frameworks wholesale,
introduce paid analytics, export customer data or fabricate approval receipts.

Automatic reviewer checks must distinguish CI success, request, quota block,
in-progress activity and exact-head completed review. Read
`docs/engineering/ai-review-workflow.md`. Preserve another agent's uncommitted
work; isolate a branch/worktree when concurrent edits appear.

Regenerate maps and indexes with `./bapXphp update`, then `./bapXphp ci`. Keep
source-pinned manifests and bounded findings in `docs/research/`; raw evidence
and external clones stay outside the repository.

## Continuous capability reconciliation

Follow `docs/research/rea-engineering-workflow.md`. For every changed mapped
capability, inspect actual local/upstream code and acceptance evidence, record
the row with `php cli/rea-capabilities.php --record ID --reason ...`, and regenerate
the report. Never promote on file existence, CI alone or an invented live result.
