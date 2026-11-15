---
type: doc
title: REA-backed Agent and Commerce Gap Analysis
description: Pinned open-source comparison and observed reviewer failures; distinguishes main, draft and local unfinished work.
category: docs
---

# REA-backed agent, support and commerce analysis

**As of 15 November 2026. Research findings, not a claim that the gaps are implemented.**

## Executive result

27 scoped gaps were identified across agent reliability, support operations, enterprise evidence, commerce and review automation. Existing payment verification, refund import, GST reporting, coupons, local role separation and atomic chat JSON are preserved—not listed as missing.

The critical architectural gap is not a missing dashboard widget: the PHP runtime needs durable request/side-effect identity and real execution-boundary evidence. Stronger metrics are useful only when the underlying lifecycle is trustworthy.

## What was actually run

- `rea -help`: rejected; CLI directed to `rea --help`. Installed version: **6.8.0**. Read its shipped skill, README, command schemas and implementation contract before analysis.
- Eight bounded source imports: five external source trees, two application snapshots, and the selected DB-GPT trace/data-agent files. Every importer result labels source coverage **partial** and historical-reference.
- Focused DeepSeek JavaScript/TypeScript analysis: 3 selected files, 2,445 AST nodes visited, 52 analyzer findings; 57 inventoried files explicitly outside the selected scope. These are analyzer facts—not 52 bugs.
- REA public browser capture: live `/shop`, open Support, confirm visible panel; four steps completed, 123 observed events, no retained request/response bodies or header values. No login, payment or customer mutation. That observation does not certify private/admin flows.
- DB-GPT full-tree import was stopped when it exceeded the bounded research window; 15 selected trace/data-agent/license files were then imported successfully. No full-tree DB-GPT coverage is claimed.
- Node 22.19.0 is below REA's declared supported engine range; the executed commands worked, but the environment is not represented as fully supported. No global runtime upgrade, MCP registration or native decompiler installation was performed.

Complete raw evidence remains under the local REA research directory; this repo stores content hashes, selected source permalinks and findings, not copied third-party applications.

## Baselines and licensing

- Main source baseline: `08a988729c90c63d2bc9676829a82baf1850fbc4`. This is repository main source, not proof of which commit Hostinger currently serves.
- Local draft snapshot: `a5c3418072ad3ab8b01f643b866c4132e51c43cd`, including explicitly catalogued **uncommitted** files. Remote PR #241 is still at `651e7d3…`; local feedback/trace/eval additions are not falsely labeled pushed or deployed.
- DeepSeek Harness, Bagisto, OpenHands and DB-GPT: MIT source reviewed. Langfuse and Chatwoot: only their MIT cores; the source licenses explicitly exclude enterprise directories. No enterprise-only modules are treated as freely reusable.
- OpenHands here is its current agent-canvas frontend reference. It is not presented as a complete support-ticket backend or a substitute for the customer's PHP application.

| Reference | Pinned commit | Pattern studied |
|---|---|---|
| deepseek-harness | `39163dd30f703a79079e2d2446545cff3c453738` | Session identity, checkpoint barriers, redaction, bounded telemetry transport |
| langfuse | `168ededf52afd3642d3594cd0c27c7d394ae77dd` | Nested observations, latency/usage fields, datasets and experiments |
| chatwoot | `1ef1d2b45ae0a17a35c5f1bd055e4ddf04dadabf` | Human assignment, conversation states and CSAT |
| bagisto | `c9209260c0f078e1b358c98050fe2e35a0ad75a4` | PHP cart/inventory/promotion/shipment/refund/reporting patterns |
| openhands | `02c438a78ec8b205cfdf9e9822330018241b0510` | Frontend model metrics and telemetry privacy controls |
| db-gpt | `83e7ff9a50c6e1f87aa878f21d2d1355a1d79dbc` | Enterprise data-agent execution checks and real span timestamps |

## Missing capabilities, in priority order

P0 = integrity/reliability or release blocker; P1 = next operational capability; P2 = optional feature expansion. “Missing” is limited to the inspected implementation, not an assertion about every possible branch or customer requirement.

| ID | Priority | Area | Gap |
|---|---|---|---|
| A01 | P0 | Agents | Real execution span boundaries |
| A02 | P0 | Agents | Durable checkpoints and idempotent support actions |
| A03 | P0 | Agents | Separate business durability from telemetry failure |
| A04 | P1 | Agents | Token/cost/latency budgets |
| A05 | P1 | Agents | Evidence provenance for every factual answer |
| A06 | P1 | Agents | Reproducible experiments beyond ten guardrail fixtures |
| A07 | P1 | Agents | Saved conversation is not full conversational context |
| A08 | P1 | Agents | Retrieval precision, ambiguity and local languages |
| A09 | P1 | Agents | Governed JSON retention and scalable listing |
| S01 | P1 | Support | Assigned human handoff with context |
| S02 | P1 | Support | Reopen and response-time lifecycle |
| S03 | P1 | Support | CSAT and outcome denominators |
| S04 | P1 | Support | Negative-feedback remediation queue |
| E01 | P1 | Enterprise | Scoped, bounded business-query execution |
| E02 | P1 | Enterprise | Evidence-backed permission and instruction regression coverage |
| T01 | P0 | Telemetry | End-to-end commerce event identity |
| T02 | P1 | Telemetry | Privacy, sampling and test-traffic separation |
| T03 | P1 | Telemetry | Service-level health instead of only chat counters |
| C01 | P0 | Commerce | Quantity inventory and reservations |
| C02 | P0 | Commerce | Atomic coupon redemption limits |
| C03 | P1 | Commerce | Persistent carts and consented recovery |
| C04 | P1 | Commerce | Per-item partial fulfillment |
| C05 | P1 | Commerce | Item-level returns and restocking |
| C06 | P2 | Commerce | Rule-based promotions |
| C07 | P2 | Commerce | Saved shopping preferences |
| C08 | P1 | Commerce | Customer and product analytics beyond summary counts |
| R01 | P0 | Reviews | Review delivery and current-head evidence |

### A01 — Real execution span boundaries (P0)

**Current evidence:** Main has request trace IDs and run/tool timings. The unfinished draft creates stage-grouped spans after the answer, explicitly not a wall-clock timeline.

**Remaining implementation:** Capture started/ended/failed spans at actual retrieval, tool and provider boundaries with sequence, parent/span ID, request ID and terminal response evidence.

**Acceptance:** An injected provider error leaves ordered, timed evidence up to failure; every executed tool and retry links to the same turn.

**Our code:** `app/Services/AgentTraceService.php`, `app/Services/AiClient.php`, `app/Services/AgentToolRegistry.php`.

**Reference source:** [ds_sessions](https://github.com/deepseek-ai/deepseek-harness/blob/39163dd30f703a79079e2d2446545cff3c453738/docs/subsystems/session-telemetry.md#L1-L144); [db_spans](https://github.com/eosphoros-ai/DB-GPT/blob/83e7ff9a50c6e1f87aa878f21d2d1355a1d79dbc/packages/dbgpt-core/src/dbgpt/util/tracer/base.py#L41-L158); [lf_spans](https://github.com/langfuse/langfuse/blob/168ededf52afd3642d3594cd0c27c7d394ae77dd/packages/shared/src/observationsTable.ts#L1-L145).

### A02 — Durable checkpoints and idempotent support actions (P0)

**Current evidence:** The answer/ticket can be produced before AgentConversationService saves its JSON turn.

**Remaining implementation:** Persist request intent before side effects, stable tool invocation/idempotency key, and explicit unknown-outcome recovery. Keep it inside the same conversation JSON, not a new chat table.

**Acceptance:** Kill execution before/after ticket creation and retry: never create duplicate cases, lose evidence or blindly replay an uncertain action.

**Our code:** `app/Controllers/SupportController.php`, `app/Services/SupportBotService.php`, `app/Services/AgentConversationService.php`.

**Reference source:** [ds_checkpoints](https://github.com/deepseek-ai/deepseek-harness/blob/39163dd30f703a79079e2d2446545cff3c453738/packages/session/session-checkpoint-policy/README.md#L1-L110).

### A03 — Separate business durability from telemetry failure (P0)

**Current evidence:** Operational audit writes and transcript persistence are on the response path; some errors lead to a generic retry response after useful work.

**Remaining implementation:** Different failure policy for mandatory state checkpoints and noncritical telemetry; report actual business outcome, telemetry loss counter and bounded backpressure.

**Acceptance:** Unavailable logging cannot imply a completed ticket/payment failed; mandatory checkpoint failure blocks a new side effect before dispatch.

**Our code:** `app/Services/AuditLogService.php`, `app/Controllers/SupportController.php`.

**Reference source:** [ds_sessions](https://github.com/deepseek-ai/deepseek-harness/blob/39163dd30f703a79079e2d2446545cff3c453738/docs/subsystems/session-telemetry.md#L1-L144); [ds_queue](https://github.com/deepseek-ai/deepseek-harness/blob/39163dd30f703a79079e2d2446545cff3c453738/packages/telemetry/otel/README.md#L1-L72).

### A04 — Token/cost/latency budgets (P1)

**Current evidence:** Provider attempt status/duration exist; input/output/cache tokens, TTFT and budget consumption are not consistently captured.

**Remaining implementation:** Provider-specific usage normalization with missing values marked unavailable, per-turn timeout/token/call budget, and cancellation propagation.

**Acceptance:** Fixture providers with and without usage produce truthful metrics; budget exhaustion ends once with an inspectable reason.

**Our code:** `app/Services/AiClient.php`, `app/Services/AgentTraceService.php`.

**Reference source:** [lf_spans](https://github.com/langfuse/langfuse/blob/168ededf52afd3642d3594cd0c27c7d394ae77dd/packages/shared/src/observationsTable.ts#L1-L145); [oh_metrics](https://github.com/OpenHands/OpenHands/blob/02c438a78ec8b205cfdf9e9822330018241b0510/src/utils/conversation-metrics.ts#L1-L79).

### A05 — Evidence provenance for every factual answer (P1)

**Current evidence:** Named tools expose source links; unfinished traces retain some safe filters/counts but not complete query/result provenance.

**Remaining implementation:** Record scope, canonical dates/timezone, source revision/freshness, approved operation/version and redacted result digest. Compare claims to exact scoped results.

**Acceptance:** Two different scopes cannot share evidence; zero rows remain valid and distinct from query failure; private identifiers are not logged.

**Our code:** `app/Services/AgentToolRegistry.php`, `app/Services/AgentTraceService.php`.

**Reference source:** [db_queries](https://github.com/eosphoros-ai/DB-GPT/blob/83e7ff9a50c6e1f87aa878f21d2d1355a1d79dbc/packages/dbgpt-core/src/dbgpt/agent/expand/data_scientist_agent.py#L72-L156); [lf_spans](https://github.com/langfuse/langfuse/blob/168ededf52afd3642d3594cd0c27c7d394ae77dd/packages/shared/src/observationsTable.ts#L1-L145).

### A06 — Reproducible experiments beyond ten guardrail fixtures (P1)

**Current evidence:** The local unfinished draft has ten deterministic fixtures, hashes and a baseline; live accuracy/groundedness correctly remain not_evaluated.

**Remaining implementation:** Broader versioned datasets, actual output/evidence assertions, prompt/tool/model/config and code version fingerprint, per-case regression diff and reviewer labels.

**Acceptance:** Run the same grounded cases before/after a change; reveal factual regressions rather than equating response_present with accuracy.

**Our code:** `app/Services/AgentEvaluationService.php`, `cli/agent-eval.php`, `tests/fixtures/agent-golden.json`.

**Reference source:** [lf_experiments](https://github.com/langfuse/langfuse/blob/168ededf52afd3642d3594cd0c27c7d394ae77dd/web/src/features/datasets/README.md#L1-L54); [db_queries](https://github.com/eosphoros-ai/DB-GPT/blob/83e7ff9a50c6e1f87aa878f21d2d1355a1d79dbc/packages/dbgpt-core/src/dbgpt/agent/expand/data_scientist_agent.py#L72-L156).

### A07 — Saved conversation is not full conversational context (P1)

**Current evidence:** JSON history and admin report-date carry-over exist in draft. Support synthesis is largely per-message; reopening UI text alone does not ground general follow-ups.

**Remaining implementation:** Bounded actor-owned turn/context selection, entity carry-over and explicit reset, with tool freshness revalidation.

**Acceptance:** “That item” and Tamil follow-ups resolve only within the right chat; New Chat and account switching cannot leak prior context.

**Our code:** `app/Controllers/AdminController.php`, `app/Services/SupportBotService.php`, `app/Services/AgentConversationService.php`.

**Reference source:** [ds_sessions](https://github.com/deepseek-ai/deepseek-harness/blob/39163dd30f703a79079e2d2446545cff3c453738/docs/subsystems/session-telemetry.md#L1-L144); [db_queries](https://github.com/eosphoros-ai/DB-GPT/blob/83e7ff9a50c6e1f87aa878f21d2d1355a1d79dbc/packages/dbgpt-core/src/dbgpt/agent/expand/data_scientist_agent.py#L72-L156).

### A08 — Retrieval precision, ambiguity and local languages (P1)

**Current evidence:** #249 fixes exact named-collection retrieval; the selector uses limited English tokens/aliases and basic catalogue matching.

**Remaining implementation:** Typed intent routing, Tamil/alternate spelling fixtures, positive/no-match/ambiguous outcomes and match-reason evidence; never default to unrelated items.

**Acceptance:** Customer Ayyappa examples, Tamil queries, empty collections and injection-bearing content all return correct scoped results or clarification.

**Our code:** `app/Services/AgentContextService.php`, `app/Services/SupportBotService.php`.

**Reference source:** [db_queries](https://github.com/eosphoros-ai/DB-GPT/blob/83e7ff9a50c6e1f87aa878f21d2d1355a1d79dbc/packages/dbgpt-core/src/dbgpt/agent/expand/data_scientist_agent.py#L72-L156); [lf_experiments](https://github.com/langfuse/langfuse/blob/168ededf52afd3642d3594cd0c27c7d394ae77dd/web/src/features/datasets/README.md#L1-L54).

### A09 — Governed JSON retention and scalable listing (P1)

**Current evidence:** Private file permissions, atomic rename and actor checks exist; global lock and filesystem scanning remain.

**Remaining implementation:** Per-chat contention control, bounded pagination/index projection, retention/export/deletion policy and deployment backup/restore checks without another transcript source of truth.

**Acceptance:** Restore a backup and continue the same chat ID; large session counts do not require parsing every transcript on each history request.

**Our code:** `app/Services/AgentConversationService.php`.

**Reference source:** [ds_sessions](https://github.com/deepseek-ai/deepseek-harness/blob/39163dd30f703a79079e2d2446545cff3c453738/docs/subsystems/session-telemetry.md#L1-L144); [lf_spans](https://github.com/langfuse/langfuse/blob/168ededf52afd3642d3594cd0c27c7d394ae77dd/packages/shared/src/observationsTable.ts#L1-L145).

### S01 — Assigned human handoff with context (P1)

**Current evidence:** Support creates a ticket and has open/answered/closed states; there is no comparable ownership/assignment transition model.

**Remaining implementation:** Team/admin assignment, handoff reason, ticket-to-conversation/turn link and a minimal authorized context packet.

**Acceptance:** Human owner can see why the agent escalated and reply from verified context; bot/human ownership never conflicts.

**Our code:** `app/Services/SupportTicketService.php`, `app/Services/SupportBotService.php`, `storage/schema/collections.php`.

**Reference source:** [cw_assignment](https://github.com/chatwoot/chatwoot/blob/1ef1d2b45ae0a17a35c5f1bd055e4ddf04dadabf/app/services/conversations/assignment_service.rb#L1-L62); [cw_lifecycle](https://github.com/chatwoot/chatwoot/blob/1ef1d2b45ae0a17a35c5f1bd055e4ddf04dadabf/app/models/conversation.rb#L1-L220).

### S02 — Reopen and response-time lifecycle (P1)

**Current evidence:** Current ticket state is stored; historical state transitions and response/resolution clocks are not a complete timeline.

**Remaining implementation:** Opened/first-response/resolved/reopened/snoozed timestamps, event history, waiting owner and overdue queue. Do not assume commercial SLA modules are MIT core.

**Acceptance:** Reopened cases restart the correct clock; reports distinguish created-in-period from resolved-in-period.

**Our code:** `app/Services/SupportTicketService.php`, `app/Services/AgentToolRegistry.php`.

**Reference source:** [cw_lifecycle](https://github.com/chatwoot/chatwoot/blob/1ef1d2b45ae0a17a35c5f1bd055e4ddf04dadabf/app/models/conversation.rb#L1-L220).

### S03 — CSAT and outcome denominators (P1)

**Current evidence:** The unfinished local code adds latest-answer yes/no feedback and a bounded recent sample; it is not full CSAT or verified ticket closure.

**Remaining implementation:** Separate answer helpfulness, customer-confirmed resolution, ticket state and survey response rate; avoid silent-success or survey duplication.

**Acceptance:** Negative feedback remains unresolved; a new question reopens state; response-rate and resolution-rate denominators are visible and distinct.

**Our code:** `app/Services/AgentEvaluationService.php`, `app/Services/AgentConversationService.php`, `views/admin/agent-monitoring.php`.

**Reference source:** [cw_csat](https://github.com/chatwoot/chatwoot/blob/1ef1d2b45ae0a17a35c5f1bd055e4ddf04dadabf/app/models/csat_survey_response.rb#L1-L47); [cw_survey](https://github.com/chatwoot/chatwoot/blob/1ef1d2b45ae0a17a35c5f1bd055e4ddf04dadabf/app/services/csat_survey_service.rb#L1-L145).

### S04 — Negative-feedback remediation queue (P1)

**Current evidence:** Not-helpful feedback is saved but does not create an assigned remediation/knowledge-gap workflow.

**Remaining implementation:** Group unresolved issues by intent/source gap, attach affected evidence and publish policy/content changes through human review.

**Acceptance:** A repeated bad recommendation creates a reproducible fixture and reviewable source correction, not automatic unsupervised training.

**Our code:** `app/Services/AgentEvaluationService.php`, `app/Controllers/SupportController.php`.

**Reference source:** [cw_assignment](https://github.com/chatwoot/chatwoot/blob/1ef1d2b45ae0a17a35c5f1bd055e4ddf04dadabf/app/services/conversations/assignment_service.rb#L1-L62); [lf_experiments](https://github.com/langfuse/langfuse/blob/168ededf52afd3642d3594cd0c27c7d394ae77dd/web/src/features/datasets/README.md#L1-L54).

### E01 — Scoped, bounded business-query execution (P1)

**Current evidence:** Named PHP tools are safer than arbitrary model SQL, but several operations read full collections then filter in PHP.

**Remaining implementation:** Indexed named query/aggregate contracts, maximum row/response limits, query timing and explicit access scope. Do not copy DB-GPT arbitrary generated SQL.

**Acceptance:** Large-order fixtures return accurate scoped totals without unbounded scans; empty data and DB failure stay distinct.

**Our code:** `app/Services/AgentToolRegistry.php`, `app/Services/DatabaseService.php`.

**Reference source:** [db_queries](https://github.com/eosphoros-ai/DB-GPT/blob/83e7ff9a50c6e1f87aa878f21d2d1355a1d79dbc/packages/dbgpt-core/src/dbgpt/agent/expand/data_scientist_agent.py#L72-L156); [bg_reports](https://github.com/bagisto/bagisto/blob/c9209260c0f078e1b358c98050fe2e35a0ad75a4/packages/Webkul/Admin/src/Routes/web/reporting-routes.php#L1-L56).

### E02 — Evidence-backed permission and instruction regression coverage (P1)

**Current evidence:** Support/Enterprise permissions and human-reviewed drafts exist; broad prompt-injection, export-role and refusal tests are still thin.

**Remaining implementation:** Test untrusted retrieved instructions, tool argument tampering, export capabilities and source-poisoning; enforce all critical boundaries in PHP.

**Acceptance:** A model or page instruction cannot expand customer/admin scope, run a write tool, expose a foreign session or certify its own correctness.

**Our code:** `app/Services/AgentCapabilityPolicy.php`, `app/Services/EnterpriseToolRegistry.php`, `app/Services/AgentEvaluationService.php`.

**Reference source:** [ds_checkpoints](https://github.com/deepseek-ai/deepseek-harness/blob/39163dd30f703a79079e2d2446545cff3c453738/packages/session/session-checkpoint-policy/README.md#L1-L110); [lf_experiments](https://github.com/langfuse/langfuse/blob/168ededf52afd3642d3594cd0c27c7d394ae77dd/web/src/features/datasets/README.md#L1-L54).

### T01 — End-to-end commerce event identity (P0)

**Current evidence:** Draft SiteTelemetryService counts only cart_add and checkout_initiated; paid orders are reported separately.

**Remaining implementation:** Correlate product/search/cart/checkout/payment/order/shipment/refund events with event ID, schema version, consent-safe session and server-authoritative paid outcome.

**Acceptance:** A retry emits no duplicate purchase; payment success counts only a verified durable order; raw click totals are never sold as conversion rate.

**Our code:** `app/Services/SiteTelemetryService.php`, `app/Controllers/CommerceController.php`, `app/Services/OrderService.php`.

**Reference source:** [bg_events](https://github.com/bagisto/bagisto/blob/c9209260c0f078e1b358c98050fe2e35a0ad75a4/packages/Webkul/Checkout/src/Listeners/OrderEventsHandler.php#L1-L29); [bg_reports](https://github.com/bagisto/bagisto/blob/c9209260c0f078e1b358c98050fe2e35a0ad75a4/packages/Webkul/Admin/src/Routes/web/reporting-routes.php#L1-L56).

### T02 — Privacy, sampling and test-traffic separation (P1)

**Current evidence:** Counters omit PII, but consent-aware measurement and explicit bot/test sample segmentation are incomplete.

**Remaining implementation:** Keep analytics local-first, honor applicable consent/DNT controls, separate synthetic QA from customer metrics and sample entire traces consistently.

**Acceptance:** Disabled analytics sends nothing optional; QA runs cannot inflate customer conversion or support-resolution reports.

**Our code:** `app/Services/SiteTelemetryService.php`, `views/layouts/app.php`.

**Reference source:** [oh_privacy](https://github.com/OpenHands/OpenHands/blob/02c438a78ec8b205cfdf9e9822330018241b0510/src/services/telemetry.ts#L1-L175); [lf_sampling](https://github.com/langfuse/langfuse/blob/168ededf52afd3642d3594cd0c27c7d394ae77dd/packages/shared/src/server/ingestion/sampling.ts#L1-L59).

### T03 — Service-level health instead of only chat counters (P1)

**Current evidence:** There are mail queue, provider, DB and payment services, but no common cross-service evidence and alert view.

**Remaining implementation:** Correlate remote DB failures/latency, mail enqueue/send/known delivery status, payment callback/reconciliation, media errors and background job retries.

**Acceptance:** SMTP accepted is labeled accepted, not inbox delivered; a service failure links to its affected order/request without credentials.

**Our code:** `app/Services/MailQueueService.php`, `app/Services/DatabaseService.php`, `app/Services/OrderService.php`.

**Reference source:** [ds_sessions](https://github.com/deepseek-ai/deepseek-harness/blob/39163dd30f703a79079e2d2446545cff3c453738/docs/subsystems/session-telemetry.md#L1-L144); [db_spans](https://github.com/eosphoros-ai/DB-GPT/blob/83e7ff9a50c6e1f87aa878f21d2d1355a1d79dbc/packages/dbgpt-core/src/dbgpt/util/tracer/base.py#L41-L158).

### C01 — Quantity inventory and reservations (P0)

**Current evidence:** Product stock status and paid-order on-hold handling exist; not an inventory ledger with salable/reserved quantities.

**Remaining implementation:** Quantity per SKU, atomic reservation/release, movement history, optional source/location and variant inventory.

**Acceptance:** Two concurrent attempts for the last item cannot both promise fulfillment; cancellation/refund stock movement happens once.

**Our code:** `storage/schema/collections.php`, `app/Services/ProductService.php`, `app/Services/OrderService.php`.

**Reference source:** [bg_inventory](https://github.com/bagisto/bagisto/blob/c9209260c0f078e1b358c98050fe2e35a0ad75a4/packages/Webkul/Product/src/Models/ProductInventory.php#L1-L49); [bg_ship](https://github.com/bagisto/bagisto/blob/c9209260c0f078e1b358c98050fe2e35a0ad75a4/packages/Webkul/Sales/src/Repositories/ShipmentRepository.php#L1-L150).

### C02 — Atomic coupon redemption limits (P0)

**Current evidence:** Coupons already support validity dates, spend and global/per-customer limits; usage is counted from orders rather than atomically reserved.

**Remaining implementation:** Recheck/reserve usage with concurrency protection inside the correct order/payment workflow; never discard a successfully paid order just because a coupon race is found.

**Acceptance:** Concurrent last-redemption tests never exceed the approved limit or create an unrecorded successful payment.

**Our code:** `app/Services/CouponService.php`, `app/Services/OrderService.php`.

**Reference source:** [bg_coupon](https://github.com/bagisto/bagisto/blob/c9209260c0f078e1b358c98050fe2e35a0ad75a4/packages/Webkul/CartRule/src/Listeners/Order.php#L25-L145).

### C03 — Persistent carts and consented recovery (P1)

**Current evidence:** CartService holds a PHP session cart, with no durable cart lifecycle/recovery data.

**Remaining implementation:** Persistent cart IDs/updated timestamp/active-converted-expired state, authenticated merge and opt-in recovery messaging.

**Acceptance:** Returning customers recover the correct cart; logged-out and other-customer carts remain private; no unsolicited recovery email is sent.

**Our code:** `app/Services/CartService.php`, `app/Controllers/CommerceController.php`.

**Reference source:** [bg_cart](https://github.com/bagisto/bagisto/blob/c9209260c0f078e1b358c98050fe2e35a0ad75a4/packages/Webkul/Checkout/src/Models/Cart.php#L1-L120); [bg_marketing](https://github.com/bagisto/bagisto/blob/c9209260c0f078e1b358c98050fe2e35a0ad75a4/packages/Webkul/Marketing/src/Helpers/Campaign.php#L1-L87).

### C04 — Per-item partial fulfillment (P1)

**Current evidence:** Order-level status/courier/tracking exists, not a complete per-item shipped/outstanding ledger.

**Remaining implementation:** Shipment records with item quantity, source and tracking; partial shipment progress and fulfillment audit.

**Acceptance:** An order split into shipments retains accurate remaining quantities and cannot over-ship.

**Our code:** `app/Services/OrderService.php`, `storage/schema/collections.php`.

**Reference source:** [bg_ship](https://github.com/bagisto/bagisto/blob/c9209260c0f078e1b358c98050fe2e35a0ad75a4/packages/Webkul/Sales/src/Repositories/ShipmentRepository.php#L1-L150); [bg_inventory](https://github.com/bagisto/bagisto/blob/c9209260c0f078e1b358c98050fe2e35a0ad75a4/packages/Webkul/Product/src/Models/ProductInventory.php#L1-L49).

### C05 — Item-level returns and restocking (P1)

**Current evidence:** Verified partial/full monetary refund import and GST/order reports already exist. This is NOT a missing-refunds claim.

**Remaining implementation:** Return authorization/reason, per-item refundable quantity, shipping/tax allocation, restock decision and notification lifecycle.

**Acceptance:** A partial returned line changes exact refundable quantity/tax/stock once while retaining gateway evidence and issued-invoice integrity.

**Our code:** `app/Services/OrderService.php`, `app/Services/GstRegisterService.php`.

**Reference source:** [bg_refund](https://github.com/bagisto/bagisto/blob/c9209260c0f078e1b358c98050fe2e35a0ad75a4/packages/Webkul/Sales/src/Repositories/RefundRepository.php#L1-L210).

### C06 — Rule-based promotions (P2)

**Current evidence:** Flat/percentage coupons, dates and limits exist; no full catalog/cart-rule condition engine.

**Remaining implementation:** Prioritized product/category/customer-group conditions and safe stacking rules with deterministic preview.

**Acceptance:** Fixture promotion combinations have stable, auditable totals; existing coupons retain behavior.

**Our code:** `app/Services/CouponService.php`, `storage/schema/collections.php`.

**Reference source:** [bg_coupon](https://github.com/bagisto/bagisto/blob/c9209260c0f078e1b358c98050fe2e35a0ad75a4/packages/Webkul/CartRule/src/Listeners/Order.php#L25-L145); [bg_marketing](https://github.com/bagisto/bagisto/blob/c9209260c0f078e1b358c98050fe2e35a0ad75a4/packages/Webkul/Marketing/src/Helpers/Campaign.php#L1-L87).

### C07 — Saved shopping preferences (P2)

**Current evidence:** No wishlist capability was found in the inspected schema/routes.

**Remaining implementation:** Wishlist/save-for-later and optional variant selection backed by actual demand, with actor ownership.

**Acceptance:** A customer cannot view another customer's wishlist; removed products do not break saved lists.

**Our code:** `app/Services/ProjectMapService.php`, `storage/schema/collections.php`.

**Reference source:** [bg_wishlist](https://github.com/bagisto/bagisto/blob/c9209260c0f078e1b358c98050fe2e35a0ad75a4/packages/Webkul/Customer/src/Repositories/WishlistRepository.php#L1-L17); [bg_cart](https://github.com/bagisto/bagisto/blob/c9209260c0f078e1b358c98050fe2e35a0ad75a4/packages/Webkul/Checkout/src/Models/Cart.php#L1-L120).

### C08 — Customer and product analytics beyond summary counts (P1)

**Current evidence:** Basic paid sales, refunds, top products, new-user and content-health cards exist or are drafted.

**Remaining implementation:** Windowed cohort/repeat purchase, AOV, refund/fulfillment trends, search/no-results metrics, exportable drill-downs and source/denominator labels.

**Acceptance:** Every card reconciles to orders/events for the same time window; empty data is not an error; no search-ranking claims without a source.

**Our code:** `app/Services/StoreStatsService.php`, `app/Services/SiteInsightsService.php`.

**Reference source:** [bg_reports](https://github.com/bagisto/bagisto/blob/c9209260c0f078e1b358c98050fe2e35a0ad75a4/packages/Webkul/Admin/src/Routes/web/reporting-routes.php#L1-L56); [lf_spans](https://github.com/langfuse/langfuse/blob/168ededf52afd3642d3594cd0c27c7d394ae77dd/packages/shared/src/observationsTable.ts#L1-L145).

### R01 — Review delivery and current-head evidence (P0)

**Current evidence:** CI passes on six PRs; five are drafts. #240 has a real Codex receipt. #243/#245 show explicit Codex code-review quota exhaustion. Sourcery/CodeRabbit completion is not observed.

**Remaining implementation:** One trusted post-CI dispatcher, draft-capable native settings, actual current-SHA receipts, quota/configuration blocks and idempotent separate command comments.

**Acceptance:** Stale CI/forks/bot identity/forged receipts cannot trigger privileged work; a request never becomes a false approval. No token/credits are silently provisioned.

**Our code:** `.github/workflows/ci.yml`, `.github/workflows/branch-pr.yml`.

## Do not copy these patterns blindly

- DeepSeek's telemetry queue is best-effort; it is **not** a durable outbox or warehouse acknowledgement. Copy its separation of checkpoints and telemetry, not a fictional exactly-once exporter.
- DB-GPT's inspected correctness check retries on empty query results. That would be wrong for legitimate zero sales/support tickets. Borrow execution validation and trace scope, retain truthful zero-result handling, and never expose arbitrary generated SQL.
- OpenHands has consent/DNT gates, but also a pre-consent installation event and external PostHog transport. Do not introduce that external tracking or proxy behavior into this PHP customer site; adopt explicit local-first controls only.
- A model/fixture pass is not CSAT or true financial correctness. Do not auto-train on failed conversations, silently close tickets, or send recovery email without the proper consent.
- Keep one JSON file per conversation. Durable intent/checkpoint states can be fields in that file; business inventory/orders/tickets remain in MySQL. No paid observability platform is required.

## Automatic review diagnosis and repair scope

The default branch has no explicit post-successful-CI review coordinator and its branch-PR workflow omits `bapX/**`. Five open PRs are drafts. CodeRabbit and Sourcery skip drafts by default. Codex has a current-head completed receipt on #240, so it is not universally disconnected; its replies on #243 and #245 explicitly report exhausted code-review usage.

Manual standalone CodeRabbit and Sourcery commands were posted to #245 as the authenticated owner. They are **requests**, not proof of receipt/completion. The GitHub installations API returned 403 for this credential; absence of a visible review cannot be promoted into a claim that an app is uninstalled.

This branch adds a metadata-only trusted-default-branch coordinator, current-head/quota/deduplication tests, CodeRabbit draft/incremental configuration, and `bapX/**` branch coverage. The coordinator requires a dedicated real-user `REVIEW_REQUEST_TOKEN` for trigger comments because Sourcery ignores bot comments. No such token has been configured, and the unrelated existing token has not been reused. Native app authorization, draft settings and quota reset remain external prerequisites. The code is staged for PR review; no merge or deployment is implied.

## Practical implementation sequence

1. Preserve and validate the unfinished #241 work, then implement A01–A03: lifecycle events, pre-action checkpoints and idempotent escalation; inject failures around every boundary.
2. Add explicit support assignment, reopen clocks and outcome/CSAT denominators (S01–S04); expand multilingual/source-grounding fixtures (A06–A08).
3. Add canonical commerce events and atomic inventory/coupon behavior (T01, C01–C02), followed by line-level shipment/return workflows.
4. Extend reports only after the underlying events reconcile, and leave optional promotion/wishlist features behind measured customer demand.

This research branch intentionally does not replace the app with Bagisto, Langfuse, Chatwoot or DB-GPT, and does not claim all listed capabilities have been implemented.
