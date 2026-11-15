---
type: doc
title: Agent Observability and Evaluations
description: PHP agent permissions, JSON conversation traces, support feedback, and offline golden tests.
category: docs
---

# Two agents: capability, evidence, evaluation, outcome

These capabilities are implemented on **draft PR #241**, not yet certified on
production. They run in native PHP: no hosted evaluation service, agent
framework, extra model subscription or conversation SQL table.

## Entrypoints and source of truth

| Operation | Actual implementation |
|---|---|
| Support question | POST `/support/ask`, `SupportController::ask` → `SupportBotService` |
| Explicit support feedback | POST `/support/feedback` → `AgentConversationService::rateSupportTurn` |
| Public catalogue and owned ticket data | `AgentContextService`, `SupportToolRegistry`, `SupportTicketService` |
| Owner question | POST `/admin/agent/ask`, `AdminController::agentAsk` |
| Named read-only owner tools | `EnterpriseToolRegistry` / `AgentToolRegistry` |
| Authorization and distinct instructions | `AgentCapabilityPolicy`, `AgentInstructionService` |
| Provider failover | `AiClient`, configured by `SecretService` |
| Private chat/ownership | `AgentConversationService` |
| Sanitized correlated spans | `AgentTraceService` |
| Deterministic checks | `AgentEvaluationService`, `tests/fixtures/agent-golden*.json` |
| Operational metadata-only logs | `AuditLogService::agentRun` → existing MySQL `audit_events` |
| Owner session UI | GET `/admin/agent/history` and `/admin/agent/history/{id}` |
| Owner monitoring/JSON export | GET `/admin/agent/monitoring`, `/admin/agent/conversation/{id}` |

Exactly two PHP agent surfaces exist: customer Support and Enterprise Owner.
Both reuse `AiClient` but have **different PHP-enforced tool permissions**.
A browser role, prompt or model-supplied SQL/file path is never an authorization
grant. Business mutations remain explicit admin forms/drafts with CSRF and audit.

## Request and tool lifecycle

**Support:** server-identify guest/customer → rate limit → recognize ticket,
public policy, named product/collection, or other request → retrieve only
public visible records or the authenticated customer's own tickets → optionally
use bounded model synthesis → return answer with trace ID → save one redacted
turn → wait for explicit customer feedback. A named Ayyappa festive collection
uses verified collection membership, not the first 20 random product listings.

**Enterprise:** verify admin/CSRF → load a previous report only from that
admin's existing JSON conversation → resolve simple aggregates locally or
execute named allowlisted read-only PHP tools → assemble sources/result counts
→ bounded model response/fallback → record trace and evaluation. New Chat clears
the active ID; History reopens only that admin's previous chats.

## One JSON file per conversation

The authoritative chat transcript is

`storage/agent-conversations/<32-character-hex-id>.json`

There is no `agent_conversations` MySQL collection or
`/remotedb` chat mutation. The JSON stores actor type/ID or
anonymous guest token, message turns, timestamps, sanitized questions/answers,
trace spans, model/tool attempts, deterministic checks, and optional support
feedback. A `.conversation.lock` bookkeeping file protects
atomic updates; it is **not another chat**. PHP must preserve this ignored
directory across every Hostinger deployment. Files use restricted permissions;
the site's `/storage/` routes are inaccessible directly over HTTP.
Back up the directory separately, with retention, deletion and access controls.

In every turn, `trace_id` is the common root linking observed
retrieval, named tool calls, sanitized filters/source/result counts, bounded
provider attempts, deterministic evaluation and final answer metadata.
`AgentTraceService` gives each span a distinct ID and parent
trace. New controller requests use `ordering: observed_execution`: span IDs,
opening sequence, UTC start/end times and monotonic elapsed durations are captured
at the actual retrieval, tool, provider, evaluation and response boundaries.
Function calls link to the preceding provider attempt where applicable. Older
or standalone fixture summaries remain explicitly marked
`grouped_by_stage_not_wall_clock`; they are never relabeled as runtime evidence.
The response status is **produced**, not delivered: PHP cannot prove the browser
received it. Provider `first_byte_ms` is transport first-byte latency, not a
fabricated time-to-first-token. Input/output/cache token counts are normalized
when supplied by the provider; missing counts and unconfigured costs remain unknown.

Only allowlisted fields can enter tool evidence: e.g. report `from`
and `to`, topic/status, source category, result count and an
approved admin report URL. Email addresses, private order identifiers, raw SQL,
coupon codes, provider bodies, credentials and hidden reasoning are excluded.
The conversation text itself remains sensitive business/customer content;
admin JSON export is protected and must never be linked as a static asset.

## Pre-execution checkpoints and interrupted requests

Before attachments, retrieval, model calls or ticket creation, both controllers
call `AgentConversationService::prepareTurn`. It verifies actor ownership,
capacity and writable private storage, and writes a `pending_turns` intent into
the **same conversation JSON**. Span opening and closing checkpoints persist
through `AgentTraceService`; a failed mandatory checkpoint blocks further work.
Final atomic persistence replaces that pending entry with the completed turn.
The monitor exposes unfinished-request counts and a collapsible execution trace.

Support's UI supplies a cryptographically random request ID. An exact completed
request can return the already stored answer; an in-flight/uncertain duplicate
returns 409 instead of executing the action again. The UI keeps that request ID
for same-page retries. Legacy clients without request IDs and a fresh browser
request after reload are not covered by an exactly-once guarantee. Do not
blindly replay a ticket action whose outcome is uncertain. No SQL chat table,
background replay worker or model-based action permission is introduced.

Signed-in Support calls require CSRF before they could create a ticket. When a
ticket is known to exist but final transcript saving fails, the response reports
that ticket plus a storage warning; it does not claim the ticket failed and
invite a duplicate. Operational `audit_events` logging remains non-blocking,
whereas required conversation checkpoints intentionally fail closed.

## Resolution vs answer

Support's feedback widget sends CSRF, the current conversation ID, latest
turn ID and one of `helpful` or `not_helpful`.
PHP checks session ownership and the most recent turn, and records a single
customer vote **in that same JSON file**. A new question resets prior success.

| Stored current state | Actual meaning |
|---|---|
| `awaiting_feedback` | Bot replied; no customer satisfaction established |
| `confirmed_resolved` | Customer explicitly selected Yes, resolved |
| `needs_followup` | Customer selected No, need help |
| `escalated` | The app actually created a ticket for the human team |
| `unknown` | Historical chat with no classified state |

Silence or the customer closing the widget is **not** assumed resolution.
The monitoring UI reports these counts on a clearly labeled **recent sample
of up to 40 chats**, not all traffic. “Support answered” does not equal
“customer satisfied” or “issue resolved.” Negative feedback remains unresolved
and customers can continue chatting or contact support.

## Evidence quality and reproducible offline evaluations

`AgentEvaluationService::evaluate` checks only deterministic
response guardrails. It reports live `factual_accuracy` and
`groundedness` as `not_evaluated`, not as passing.
Do not present a nonempty answer or no-secret result as factual correctness.

The offline golden corpus and approved baseline live in:
`tests/fixtures/agent-golden.json` and
`tests/fixtures/agent-golden-baseline.json`.
The 14 cases include collection selection, generic ambiguity, role denial, empty
sales, ticket lookup-vs-creation, follow-up periods, secret-leak rejection,
executed-scope precedence and provider usage normalization.

`php cli/agent-eval.php` compares per-case results against the
baseline, emits fixture and baseline SHA-256 hashes plus PHP/instruction
versions plus an implementation fingerprint and per-source SHA-256 values, and exits nonzero for changed, added, removed or failing cases.
`php cli/agent-eval.php --json` shows machine-readable output;
`--write` optionally writes a **data-free** experiment snapshot
under ignored `storage/agent-evals/`. No paid judge/model call
occurs. GitHub CI runs this check on the PR.

The current deterministic corpus is a regression gate, not a research
benchmark. Human-reviewed customer satisfaction, semantic faithfulness,
privacy, source freshness, financial totals and model performance still
require separate grounded validation on real cases.

## Monitoring and operations

`AuditLogService` keeps privacy-safe operational run metadata
in MySQL for the last-24-hour agent dashboard: request count, fallback/error
status, provider attempts, allowed tool activity, mean and P95 duration.
Full chat text is not duplicated in `audit_events`.

Admin Agent Monitoring additionally shows per-chat JSON summary counts,
customer-confirmed vs unresolved support feedback, the offline fixture
pass count and dataset hash, and authorized transcript export. Reports
label their denominators, time windows and limitations. Cart/checkout first-
party counters are action totals, **not** unique-user conversion rates.
SEO content audits do not claim Google traffic/ranking results.

## Verification and release gate

`./bapXphp update`, `./bapXphp ci`,
`php cli/agent-eval.php` and
`php tests/agent-concurrency.php` must pass.
Desktop and 375px browser testing must cover admin History/New Chat, guest
Support feedback, role isolation, report follow-ups, correct collection data,
and privacy of session JSON URLs. Repeat affected flows after Hostinger
deploy; confirm transcripts survive a release and no production secrets
or customer conversations are committed.

References (patterns researched, not required installations):
- [OpenTelemetry GenAI semantic conventions](https://opentelemetry.io/docs/specs/semconv/gen-ai/).
- [Open-source Arize Phoenix](https://github.com/Arize-ai/phoenix).
- [Intercom resolution state definitions](https://www.intercom.com/help/en/articles/7022438-reporting-metrics-attributes).
- [OWASP AI Agent Security](https://cheatsheetseries.owasp.org/cheatsheets/AI_Agent_Security_Cheat_Sheet.html).
