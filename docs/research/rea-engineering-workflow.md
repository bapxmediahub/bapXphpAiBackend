---
type: doc
title: REA source-to-capability engineering workflow
description: Versioned native feature obligations, review gates, evidence-based percentages and agent engineering contracts.
category: docs
---

# REA source-to-capability engineering workflow

This register follows the owner's native-platform direction. WordPress,
WooCommerce, Site Kit, WordPress MCP, A2UI and DeepSeek Harness are
reference implementations, not runtime dependencies or licenses to copy
their source. OpenAI-compatible Chat Completions is a wire contract, not
an agent framework.

## Durable source evidence and completion

- [Current capability register](capability-register.json) is the
  machine-readable source of truth for the selected adaptation obligations.
- [Generated completion report](capability-progress.md) is the human-readable
  coverage and evidence view.
- [Reference source index](evidence/reference-source-index.json) retains
  exact archive digests, Git revisions, relative paths, file hashes,
  REA import authority and limitations. The original
  [39-row WordPress/WooCommerce inventory](evidence/wordpress-woocommerce-20261116.json)
  retains its historical target revision and findings.
- Earlier [28-entry REA gap inventory](rea-gap-index.json) and
  [broader audit](2026-11-15-rea-agent-commerce-gaps.md) remain historical
  evidence. Do not add their counts to the current denominator.
- Raw captures, full archives, upstream source trees, customer records,
  credentials, browser profiles and provider response bodies stay outside
  Git. Only sanitized derived findings and source provenance move with the
  repository. The static imports are partial historical-reference graphs;
  they are not runtime traces or whole-product parity evidence.

The denominator is the versioned list of in-scope native acceptance obligations,
not number of upstream files, lines, classes, plugins or issue count. A broad
obligation remains partial until its whole stated scope is met. Split a broad
obligation only with a documented reason and preserved history; do not
inflate coverage by adding trivial completed items.

| Status | Required evidence |
|---|---|
| missing | No complete native implementation identified for the stated scope |
| partial | Some mechanisms exist, but a material integration or acceptance requirement remains |
| implemented | Scoped native behavior is present with named reproducible code/fixture evidence |
| verified | Implemented plus actual authorized production acceptance at an exact deployed SHA and matching reviewed source/evidence fingerprint |
| excluded | Explicitly outside the native product boundary; not counted |

Implementation = (implemented + verified) / in-scope count.
Production verification = verified / in-scope count.
Partial rows receive no fractional completion credit.
The initial snapshot is a conservative source reconciliation, not an assertion
that every listed test was rerun during the audit.

## Every relevant PR: review, implement, measure, record

1. Read AGENTS.md, the relevant developer skills, existing issue/PR history,
   current local source and the selected upstream source. Check official
   provider/protocol docs. For new research run REA against a bounded pinned
   public-source selection. Record relative paths, hashes, licenses, actual
   observed mechanism, local counterpart, remaining behavior, and test.
   Never execute upstream scripts or install reference runtimes.
2. Identify affected capability IDs in the PR description and the existing
   GitHub issue. If a new gap is genuinely outside existing scope, add a
   separately named obligation and link its existing or new issue.
   GitHub remains the only task backlog and authoritative acceptance history.
3. Preserve the last known working workflow. Compare the unchanged baseline
   with the candidate, including failure paths, before replacing provider,
   authentication, checkout or editorial behavior. Do not count mocked
   provider output or canned content as successful model inference.
4. Implement through existing PHP controllers/services/MySQL, native JS and
   the appropriate schema. Use one private JSON file per conversation.
   Add regression tests and source/acceptance mappings in the register.
5. Inspect the affected rows and update the finding/status only as evidence
   supports it. For each affected ID, record a specific review rationale:

   ```sh
   php cli/rea-capabilities.php --record CC05 --reason "Reviewed direct role-scoped declarations, multilingual controller regressions, module and actor isolation."
   php cli/rea-capabilities.php --render
   ./bapXphp update
   ./bapXphp ci
   ```

   The review command updates the fingerprint, not the implementation claim.
   A changed verified row is downgraded to implemented until fresh live
   acceptance is recorded. Do not bulk acknowledge changes without review.
   CI rejects changed mapped source/test files with stale fingerprints,
   missing mappings, invalid references, unsupported verified claims and
   stale generated percentages. A new source file needs an explicit mapping;
   the checker is not a semantic completeness oracle.
6. Review and merge only the exact passing head under the established repo
   workflow. Verify Hostinger SHA and authorized desktop/mobile end-to-end
   behavior. Add the production evidence receipt (deployed_sha, date,
   GitHub evidence URL, precise scope, matching source_sha256) to the row and
   promote to verified only when the entire scoped obligation passes.
   Submit the evidence update through a small PR. Never put secrets or
   customer data in the report. If acceptance fails, retain the failure
   and keep the row partial or implemented as appropriate.

## Source-derived agent engineering direction

The pinned DeepSeek Harness reference uses a single agent loop that records
turn/step starts, model-visible tool results and step completion in its
session log. Its session repair distinguishes a tool never started from an
unknown outcome after dispatch. Its cancellation signals are owned by the
loop. Its telemetry is a downstream observer, not the source of truth.
Adapt those mechanisms to native PHP, without adopting a Node runtime,
autonomous coding agent, shell tools, event-store dependency or continuous
background process.

The target loop is: authorize actor and conversation -> persist pending turn
-> assemble role instructions, accepted history and scoped context -> resolve
eligible capability declarations -> call configured model -> validate named
function arguments and role/module permission -> execute approved business
service -> checkpoint actual result and safe evidence -> return correlated
tool message to the originating provider -> model decides next call or final
answer -> validate and present -> persist actual outcome. Stop on explicit
budget, cancellation, unrecoverable failure or accepted final answer.
Do not replay an uncertain write or pass provider-specific continuation
metadata to another provider. Recovery may resume only from known durable
evidence; mark unknown outcomes instead of inventing success.

Knowledge RAG retrieves bounded current records/documents, Instruction RAG
selects fixed role-authorized skill content, Tool RAG resolves the callable
schema set. The model makes the decision, PHP enforces authorization,
validation, calculations and side effects. The current small tool registries
do not justify an arbitrary English keyword activation ceiling. PR #512
merged after exact-head CI and its deployment SHA was verified. Its source
correction counts as implemented; actual live model response acceptance
remains separate in CC06. Consider deferred schemas only if measured quality
and latency justify them for a larger catalogue.

Use Chat Completions for the owner's configured compatible connections.
The official contract is
[Chat Completions](https://developers.openai.com/api/reference/resources/chat/subresources/completions/methods/create)
and [function calling](https://developers.openai.com/api/docs/guides/function-calling).
Check actual model support; not every OpenAI model supports function calls
on Chat Completions. Preserve exact messages, assistant tool_calls IDs,
JSON-object arguments, role=tool results and appropriate continuation
metadata on the same provider/model. Keep real provider text, tool-call
selection, tool result, final response, and both actual controller workflows
as separate acceptance checks. No fake answer, no hidden paid fallback,
no arbitrary schema/endpoint invention. A failed call is a failure, not
a local assistant success.

WordPress MCP's pinned adapter illustrates opt-in discovery, schema detail,
input preparation and per-ability permission checks across HTTP and STDIO.
Keep native PHP capabilities canonical; public discovery must not expose
private instructions or customer/admin records. Transport metadata never
grants permission. Use the pinned A2UI 1.0 message schemas to evolve the
existing bounded renderer from verified data, with surface lifecycle,
component/data updates and scoped action handling as separately tested
obligations. Do not infer interoperability from rendering a card.

## Admin and analytics design direction

Retain the existing integrated owner navigation. Group content and media,
commerce and customers, reporting/analytics, and settings/integrations by
real workflows, not by copied plugin menus. WordPress's revision and
list-table mechanisms guide native editorial recovery, metadata, pagination
and permissions. WooCommerce source guides server-verified cart/checkout,
variant identities, stock reservations and fulfilment. Site Kit module
classes guide independently authorized reporting services with connection,
property, available-data, permission-denied, quota and disconnect states.
A saved tracking ID is not a reporting grant. No invented analytics values,
unused dashboard placeholders or duplicated data stores.

Prioritize active production regressions and correctness before new feature
expansion. Record current known-working baselines, rejected engineering
decisions and regression evidence in the linked issue and in the appropriate
capability finding. Continue independent work while CI runs, without
bypassing review, deployment or acceptance.
