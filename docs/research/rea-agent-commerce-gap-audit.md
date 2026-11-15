---
type: doc
title: REA Agent and Commerce Gap Audit
description: Source-pinned REA findings across DeepSeek Harness, Langfuse core, Chatwoot core and Bagisto, with native PHP adoption targets.
category: docs
---

# REA audit — 28 scoped missing or incomplete capabilities

Audit: **15 November 2026**. This is a source-backed gap inventory, not a claim
that all 28 features have been implemented. P0 is correctness/release safety;
P1 is operational value; P2 is optional product expansion requiring a real need.

## Three project states must not be confused

- Main source: `08a988729c90c63d2bc9676829a82baf1850fbc4`.
- Remote draft #241 at audit start: `651e7d354b64c0fb8b56087c8f85a26efa422c25`.
- Dirty local continuation: HEAD `a5c3418072ad3ab8b01f643b866c4132e51c43cd`
  plus uncommitted trace/feedback/eval/docs changes. They were inspected, not
  claimed to be pushed, merged or deployed. This audit does not overwrite them.

A repository SHA is not proof of the deployed Hostinger revision. The public
browser observation below does not authenticate as an admin or verify checkout.

## REA use, provenance and limits

The installed command is `rea --help`; `rea -help` is unsupported. REA **6.8.0**
was run with an isolated Node **24.22.1** wrapper because the default Node
22.19 did not meet the installed package's supported runtime. No global Node,
editor MCP configuration or native reverse-engineering tool was replaced.

The audit used nine `import-reference-source` inventories for selected PHP,
Ruby and TypeScript modules and local main/draft sources. Those records are
**historical-reference, partial inventory** with hashes; they are not PHP
runtime call graphs. No upstream application/package scripts were executed.

REA `analyze-javascript-application` parsed four DeepSeek session-telemetry
files, visiting 7,735 AST nodes with no syntax parse failure. The bounded graph
still contains unresolved semantics. A `trace-application-feature` coordinator
module seed traversed **40 nodes / 58 edges**, with 25 observed static facts and
88 inferred facts. These are code relationships, not observed live execution.
An initial literal `session/event` seed did not match; it was retained as
no-match, not interpreted as feature absence.

A `capture-browser-scenario` loaded the live shop, waited for `.support-fab`,
opened Support and verified `#support-panel`. All four corrected actions
completed; REA terminated its browser. The first selector was nonunique and
failed, and is retained. The corrected capture's event completeness remains
**incomplete** due to pending requests. No login, support message, payment,
request/response body, cookie or credential was collected.

Source hashes, authority, limits and evidence IDs are in
[`rea-evidence-manifest.json`](rea-evidence-manifest.json). Raw artifacts and
reference clones remain outside production in
`Documents/rea-research/sps-20261115/`.

| Evidence | ID |
|---|---|
| Selected DeepSeek JS/TS analysis | `ev_91612ace5674e60eaf0484ac6a8969c2b66801a9557ea848e34344f826ea9a0d` |
| Coordinator static traversal | `ev_2799a2fd5bc9574e215a123e03b53cdbbadd8ea62ebe3aa2110c5025f9f55852` |
| Corrected public shop/Support scenario | `ev_0d9e18fd35d441efb5d4ef6e21c31e307c2c023f4c1b96d3b98b854b13c50d16` |

## Pinned reference implementations

| Key | Commit | Selected pattern and licensing |
|---|---|---|
| DSH | `39163dd30f703a79079e2d2446545cff3c453738` | DeepSeek Harness canonical session events, replay cursor and failure-contained telemetry; MIT |
| LF | `168ededf52afd3642d3594cd0c27c7d394ae77dd` | Langfuse observations, timestamps, provider usage and versioned datasets/runs; MIT core only, enterprise folders excluded |
| CW | `1ef1d2b45ae0a17a35c5f1bd055e4ddf04dadabf` | Chatwoot conversation states, assignment, response/resolution clocks and CSAT; MIT core, `enterprise/` excluded |
| BG | `c9209260c0f078e1b358c98050fe2e35a0ad75a4` | Bagisto PHP cart, order line, inventory, shipment/refund and promotion contracts; MIT |

DeepSeek is not a support CRM; Langfuse is not a commerce backend; Chatwoot's
enterprise/Captain feature flags are not proof of free availability. Bagisto
is Laravel-based; the adoption target is its domain invariants, not a framework
migration. Preserve native PHP, MySQL business records and one private JSON
file per chat. No paid integrations or telemetry export are added by this audit.

## Already present — do not rebuild or mislabel

Main already has gateway-verified and retry-safe order insertion, paid-order
stock review/on-hold handling, GST invoices, verified full/partial refund
amounts, dated/limited coupons, collections, mail queue, reviews, authentication
and scoped enterprise tools. `AuditLogService::record` already catches write
failures. These are strengths, not missing features.

Draft/local work additionally includes JSON chat persistence and ownership,
History/New Chat, named-collection support answers, grouped trace stages,
explicit binary resolution feedback and ten deterministic helper fixtures.
The gaps are their integration, completeness and acceptance—not that all these
concepts are absent.

## Gap matrix

The local paths below are code evidence, not roadmap guesses. Upstream keys
refer to pinned source links in the next section. The PHP target preserves
existing business safeguards; exact acceptance tests belong to the implementing PR.

### Agent and enterprise execution

| ID / priority | Missing or partial behavior | Local evidence and PHP acceptance target |
|---|---|---|
| A01 / P0 | Actual timing and causal parent-child spans | Local `AgentTraceService::build` explicitly groups stages after the answer. DSH/LF: record start/end/duration and actual parent at execution; prove interleaved tool/model ordering without invented timestamps. |
| A02 / P0 | Durable in-flight execution sequence and recovery | `AgentConversationService::record` saves final turns, not checkpoints before actions. DSH: keep sequenced started/completed/failed steps in the same chat JSON; interruption test must retain last committed state. |
| A03 / P0 | Retry-safe support side effects | `SupportTicketService::create` creates random IDs, then `SupportController::ask` persists chat. DSH/BG: request-key/action dedupe so retry after persistence failure cannot create a second ticket. |
| A04 / P0 | Ownership validation before tools/side effects | Existing append checks ownership, but Support runs first. Validate supplied chat ID/actor before retrieval, model calls or escalation; cross-actor test must execute zero tools. |
| A05 / P1 | Real multi-turn model context | Support model prompt uses current context/question; Enterprise restores report dates but not general transcript. DSH: bounded, owner-scoped turn selection with fresh source revalidation. History UI alone is not memory. |
| A06 / P1 | Replayable source/prompt/tool provenance | Trace stores source labels/counts, not complete version-bound evidence. LF: add sanitized source IDs/version hashes, prompt/tool contract version and evaluated response linkage; replay an exact synthetic fixture. |
| A07 / P1 | Provider usage and execution budgets | `AiClient` sets max output tokens but does not normalize returned usage. LF: optional input/output/cache usage, monotonic time/tool budgets; unknown usage remains null, never fabricated cost. |
| A08 / P1 | Ground-truth evaluation experiments | Ten local helper fixtures are useful but not end-to-end factual accuracy. LF: compare expected tool IDs/scopes/results plus model/seed/prompt version and sample cases; meaningful regressions must block, not only empty-response checks. |
| A09 / P1 | Audit delivery vs business-operation result | Audit failures are already contained; chat save failure can return retry after a successful ticket action. DSH/CW: preserve authoritative action outcome and report audit failure separately; a retry cannot repeat the action. |
| A10 / P1 | JSON lifecycle and bounded historical queries | One global lock, full JSON rewrite and file scans have no measured retention/backup lifecycle. Preserve one JSON per chat while testing pagination, bounded memory, retention, export audit and deployment restore. |

### Customer support workflow

| ID / priority | Missing or partial behavior | Local evidence and PHP acceptance target |
|---|---|---|
| S01 / P1 | Bidirectional conversation/ticket handoff | Chat may store ticket ID; ticket has no normalized conversation/turn linkage. CW: link both sides, include safe handoff context and dedupe human escalation. |
| S02 / P1 | Ownership of human work | Ticket schema only open/answered/closed. CW: scoped assignee/team, priority, pending/snooze and queue filters; reject unauthorized assignment changes. |
| S03 / P1 | Human-response and reopen lifecycle | `created_at`/`updated_at` cannot reconstruct first human reply, handoff, resolution or reopen. CW: explicit events/timestamps; bot latency must not be sold as human response time. |
| S04 / P1 | Satisfaction separated from resolution | Local feedback is binary and sampled, not production CSAT. CW: preserve explicit resolution, separate satisfaction and sample denominators; no silence-as-success, stale votes or another actor's ratings. |
| S05 / P1 | Retrieval quality feedback loop | #249 fixes the named Ayyappa case, not general multilingual retrieval. DSH/LF/CW: normalized intent/entities, real no-match, source citations, negative-feedback-to-fixture workflow and human-approved knowledge correction. |

### PHP commerce capabilities

| ID / priority | Missing or partial behavior | Local evidence and PHP acceptance target |
|---|---|---|
| C01 / P1 | Persistent and mergeable customer carts | `CartService` reads the PHP session; no business cart identity/state. BG: persistent cart with guest-to-account merge and price/stock revalidation; never merge another user's basket. |
| C02 / P0 | Complete, deduplicated checkout funnel | Local telemetry counts cart add/checkout start only. BG/DSH: allowlisted cart/checkout/payment/verified-order events with safe correlation and idempotency; client claims cannot certify payment. |
| C03 / P1 | Defensible abandonment cohorts | No persistent cart denominator/window. BG: derive age and conversion from cart state, disclose cohort and exclude retries; do not divide uncorrelated event counts. Recovery messaging needs consent. |
| C04 / P2 | Variant-level SKU, price and availability | Product schema has one price/stock status, not configurable SKUs. BG: add only for a real size/material requirement; selection must survive cart/order/GST line validation. |
| C05 / P1 | Numeric inventory and reservations | Current availability/on-hold safeguard does not track quantities or allocations. BG: atomic reserved/available quantity, oversell tests and explicit release rules; retain paid-order safeguards. |
| C06 / P1 | Line-level partial shipments | Order-level shipped status cannot express multiple dispatches. BG: line ordered/shipped/cancelled/refunded bounds; concurrent shipments cannot exceed remaining quantity. |
| C07 / P1 | Structured returns and refund allocation | Verified monetary refunds already exist. BG: request/decision/line quantities, allocation and tax/invoice linkage without pretending a gateway refund happened. Test partial/multiple refund reconciliation. |
| C08 / P2 | Additional promotion eligibility rules | Existing coupon dates, spend, caps, usage and free shipping are present. BG: optional product/category/customer eligibility and explicit stacking/rounding; do not replace working coupons. |
| C09 / P2 | Wishlist/save-for-later | No scoped repository/routes in inspected schema/code. BG: optional owner-bound saved items with move-to-cart revalidation; never build without customer demand. |

### Review and release observability

| ID / priority | Missing or partial behavior | Acceptance / repair in this patch |
|---|---|---|
| R01 / P0 | Trusted exact-head review request after CI | Add current-head/same-repo post-CI coordinator; no PR code execution with secrets, no fake review completion. |
| R02 / P0 | Reviewer draft and actor compatibility | Explicit CodeRabbit draft config; Sourcery human-author requirement, native app setup documented. Add missing `bapX/**` branch trigger. |
| R03 / P0 | Distinguish quota, pending and actual review receipt | Current #240 receipt is valid; #245 quota failure is not a pass. Tests prevent repeated quota spam and stale/forged approvals. |
| R04 / P1 | External activation prerequisites visible | Default-branch workflow, native app access and dedicated real-user token are distinct gates; no secret reuse, paid purchase or silent bot fallback. |

## Pinpoint upstream and local sources

- **DSH:** [coordinator.ts](https://github.com/deepseek-ai/deepseek-harness/blob/39163dd30f703a79079e2d2446545cff3c453738/packages/session/session-telemetry/src/coordinator.ts) and [session telemetry architecture](https://github.com/deepseek-ai/deepseek-harness/blob/39163dd30f703a79079e2d2446545cff3c453738/docs/subsystems/session-telemetry.md). They distinguish canonical events, replay cursor and downstream handoff. Do not turn best-effort delivery into an exactly-once claim; default redaction is not supplied automatically.
- **LF:** [OTel ingestion](https://github.com/langfuse/langfuse/blob/168ededf52afd3642d3594cd0c27c7d394ae77dd/packages/shared/src/server/otel/OtelIngestionProcessor.ts), [dataset/run schema](https://github.com/langfuse/langfuse/blob/168ededf52afd3642d3594cd0c27c7d394ae77dd/packages/shared/prisma/schema.prisma). Nested timings, parent IDs, versioned expected outputs and source trace links are richer than a final response log.
- **CW:** [reporting events](https://github.com/chatwoot/chatwoot/blob/1ef1d2b45ae0a17a35c5f1bd055e4ddf04dadabf/app/listeners/reporting_event_listener.rb), [conversation](https://github.com/chatwoot/chatwoot/blob/1ef1d2b45ae0a17a35c5f1bd055e4ddf04dadabf/app/models/conversation.rb), [CSAT](https://github.com/chatwoot/chatwoot/blob/1ef1d2b45ae0a17a35c5f1bd055e4ddf04dadabf/app/models/csat_survey_response.rb). Source comments admit handoff dedupe is best-effort; adopt a tested local invariant rather than claiming the upstream is perfect.
- **BG:** [cart](https://github.com/bagisto/bagisto/blob/c9209260c0f078e1b358c98050fe2e35a0ad75a4/packages/Webkul/Checkout/src/Cart.php), [line quantities](https://github.com/bagisto/bagisto/blob/c9209260c0f078e1b358c98050fe2e35a0ad75a4/packages/Webkul/Sales/src/Models/OrderItem.php), [shipments](https://github.com/bagisto/bagisto/blob/c9209260c0f078e1b358c98050fe2e35a0ad75a4/packages/Webkul/Sales/src/Repositories/ShipmentRepository.php), [refunds](https://github.com/bagisto/bagisto/blob/c9209260c0f078e1b358c98050fe2e35a0ad75a4/packages/Webkul/Sales/src/Repositories/RefundRepository.php), [cart cohort reporting](https://github.com/bagisto/bagisto/blob/c9209260c0f078e1b358c98050fe2e35a0ad75a4/packages/Webkul/Admin/src/Helpers/Reporting/Cart.php), [configurable product](https://github.com/bagisto/bagisto/blob/c9209260c0f078e1b358c98050fe2e35a0ad75a4/packages/Webkul/Product/src/Type/Configurable.php). Its two-day abandoned-cart cutoff is a product choice, not an automatic requirement for this customer.
- **Local:** `SupportController::ask`, `SupportBotService::modelReply`, `SupportTicketService::create`, `AdminController::agentAsk`, `AgentConversationService`, local `AgentTraceService`, `AiClient`, `SiteTelemetryService`, `CartService`, `ProductService`, `CouponService`, `OrderService`, `DatabaseService`, `storage/schema/collections.php` and route registry.

## Adopt in this order, not as a wholesale port

First isolate ownership and retry correctness, then persist observed execution
steps within the same chat JSON and wire bounded actual conversation context.
Next link support handoff/feedback into measurable human workflows and broaden
source-grounded experiments. Then introduce a trusted business funnel/persistent
cart and quantity invariants through independent, reviewed PHP changes. Optional
variants, campaign rules and wishlist come only after a customer requirement.

This patch implements **review coordination/configuration and research artifacts**.
It does not claim the agent or commerce matrix is now implemented. Runtime changes
must not be smuggled into this CI/research PR. See
[AI review workflow](../engineering/ai-review-workflow.md) for external quota and
activation limits, and the dedicated source-audit skill for repeatable REA usage.
