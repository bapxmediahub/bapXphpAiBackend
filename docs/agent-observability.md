---
type: doc
title: Agent Observability and Evaluations
description: PHP agent permissions, JSON conversation traces, support feedback, and offline golden tests.
category: docs
---

# Two agents: capability, evidence, evaluation, outcome

This guide describes the code in this revision, including the follow-up to
merged PR #241. Release state and live acceptance are separate from this
implementation contract. It runs in native PHP: no hosted evaluation service, agent
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
| Native answer sections and recorded fact cards | `AgentAnswerPresentationService` after the authorized tool flow |
| Deterministic checks | `AgentEvaluationService`, `tests/fixtures/agent-golden*.json` |
| Operational metadata-only logs | `AuditLogService::agentRun` → existing MySQL `audit_events` |
| Owner session UI | GET `/admin/agent/history` and `/admin/agent/history/{id}` |
| Owner monitoring/JSON export | GET `/admin/agent/monitoring`, `/admin/agent/conversation/{id}` |

Exactly two PHP agent surfaces exist: customer Support and Enterprise Owner.
Both reuse `AiClient` but have **different PHP-enforced tool permissions**.
A browser role, prompt or model-supplied SQL/file path is never an authorization
grant. Business mutations remain explicit admin forms/drafts with CSRF and audit.

## Request and tool lifecycle

Both wire adapters validate the entire returned call-ID batch and execute only
functions advertised for that inference round. A rejected call records a safe
`tool_call_rejected` span (no arbitrary function name or raw arguments). The
model may correct its request on a later round. Duplicate IDs, incomplete
finish reasons and unfinished final tool calls cannot become accepted answers.
Article read evidence retains the bounded excerpt SHA-256. Provider continuation
fields remain in request-local transport memory, never stored in traces or chat.
The loopback suite checks both Google and OpenAI-compatible call correlation;
it is protocol verification, not a model-quality benchmark.


**Support:** identify the guest or authenticated customer, apply rate limits,
save a pending actor-owned turn, and invoke the shared model client. The model
selects relevant published knowledge, runtime instructions and tools; PHP
validates every call and returns scoped facts. Own-order and ticket lookups
require the authenticated customer identity. A model-selected contact form
proposal does not submit a form or automatically create a ticket.

**Enterprise:** verify admin/CSRF, load owned dialogue and validated previous
report metadata, then invoke the same model loop with owner-only capabilities.
The model decides which reports or documents to use and synthesizes the
answer. Only explicit slash commands use the separate UI-operation path.
Provider outages are recorded as errors, not successful local answers.
Both surfaces save the produced turn and observed evidence in their private
JSON conversation. New Chat clears the active ID; History reopens only the
actor's previous chats.

Both native chats then format the accepted answer into bounded A2UI sections.
Enterprise fact cards project only completed, sanitized aggregate evidence;
labels are server-owned and numbers never come from model prose. History uses
the recorded tool evidence and timing without repeating a lookup. Public
product/article/contact/service references use a separate published-data
projection; private facts never enter public WebMCP or MCP Apps. Presentation
failure retains the complete original answer. See [the presentation contract](a2ui.md).

## One JSON file per conversation

The authoritative chat transcript is

`storage/agent-conversations/<32-character-hex-id>.json`

There is no `agent_conversations` MySQL collection or
`/remotedb` chat mutation. The JSON stores actor type/ID or
anonymous guest token, message turns, timestamps, sanitized questions/answers,
trace spans, model/tool attempts, deterministic checks, and optional support
feedback. Per-chat `.conversation-<id>.lock` files protect atomic updates.
New writers also take a shared `.conversation.lock` compatibility barrier so
a previous deployment using the old exclusive global lock cannot race them; it is **not another chat**. PHP must preserve this ignored
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
lookup coupon arguments, provider bodies, credentials and hidden reasoning are excluded.
The conversation text itself remains sensitive business/customer content;
admin JSON export is protected and must never be linked as a static asset.

`commerce_report` has one narrow private presentation projection, `evidence.report`,
validated by `AgentTraceService::safeCommerceReport` both when recorded and when
replayed. It contains a fixed report type, canonical basis, INR currency, effective
range (absent for current stock), row limit, full row count, typed summary and up to
ten typed rows. Product names and report coupon labels are intentionally retained
for this authorized owner report. Customer rows contain only fixed anonymous
frequency bands, never email addresses, order references or arbitrary labels.
This is not permission to save raw tool results. Unknown fields are discarded;
missing/invalid amounts reject the presentation instead of becoming zeros.

Report evidence uses source `commerce_reports` and a derived
`/admin/tax-report?report=products|stock|customers|coupons` URL with the effective
dates for dated reports. The evidence fingerprint includes its recorded report
values. Summaries cover all matching rows even when the displayed ranking is
limited; customer bands exclude invalid/missing email keys while their paid orders
remain in the overall totals and a separate unidentified count. Paid-order
amounts use order dates and recorded verified refunds, not payment settlement
dates, profit or causal coupon attribution. Stock status cannot establish
on-hand quantities. Run `php tests/enterprise-commerce.php` for the canonical
calculation, strict scope, policy, redaction and saved-evidence fixtures.

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
Agent Monitoring samples the latest 40 chats across both agent surfaces.
Each Support conversation contributes one current outcome to the totals and
to the topic of its latest completed answer. Older chats and chats with no
recorded intent appear under Unclassified. Expanding a topic shows how many
of its chats are in each observed state. A new completed answer moves the chat
to its current topic and feedback state. Ticket lookup evidence counts the
actor-owned records examined before filtering and the matches returned. A
missing measurement stays null, including when the customer needs to sign in.

## Evidence quality and reproducible offline evaluations

`AgentEvaluationService::evaluate` checks only deterministic
response guardrails. It reports live `factual_accuracy` and
`groundedness` as `not_evaluated`, not as passing.
Do not present a nonempty answer or no-secret result as factual correctness.

The offline golden corpus and approved baseline live in:
`tests/fixtures/agent-golden.json` and
`tests/fixtures/agent-golden-baseline.json`.
The cases include collection selection, generic ambiguity, role denial, empty
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
status, provider attempts, allowed tool activity, mean, nearest-rank P50 and P95 duration.
Full chat text is not duplicated in `audit_events`.

The conversation audit table links the five most recent completed turns with
tool errors per sampled chat to their exact review pages. Its run references
come from private chat JSON, so staff can match failed lookups even when an
operational audit write was unavailable. Only recorded MySQL events contribute
to the 24-hour operational metrics.

Each attempt in Recent runs displays the observed HTTP status, cURL error and elapsed duration. New attempts can also carry the effective transfer timeout and a positive measured first-byte time. cURL 28 identifies a client-side timeout and does not determine which network hop or upstream component delayed the request. HTTP 5xx establishes an upstream error response. HTTP 429 directs the operator to provider quota and rate limits. A successful transfer can still produce an unusable answer. Each run keeps its own outcome, independently of its attempt counts.

The deployment-local health file stores only hashed model/connection identities,
bounded expiry timestamps and a provider-hold flag. Ordinary transient server
failures and timeouts are recorded but do not skip the primary in the next turn.
Quota or explicit long Retry-After may temporarily exclude a model. Explicit
diagnostics can contact the chosen model. Successful transfers clear their
entry. Legacy integer cooldowns are read as transient observations, not
provider-mandated holds. The shared scheduler tries each eligible model up
to three times, then the next in order; a second cycle starts from the first
eligible model if necessary and time remains. Each observed transfer records
provider identity, cycle number and attempt within that model's inference
round, and retains its own status, duration and request digest. Retries reuse
the immutable inference payload; they never replay a completed tool or reroute
a started exchange. Missing credentials, permanent failures and provider
holds cannot be hidden as successful attempts.

HTTP 400/422 responses may contribute one allowlisted, heuristic request-error hint (tool schema, tool history, generation configuration, or invalid arguments). The hint is derived from a bounded response in memory, not an exact root-cause diagnosis. Only the fixed category enters existing attempt evidence; raw provider content remains excluded. Unexplained numeric Enterprise replies, unless the owner explicitly requested a number only, are rejected without starting another model/tool generation. No server-generated free-form answer substitutes for the rejected model response.

Before an execution limit blocks a new step, the trace checkpoints the exhausted deadline, model-call, observed-token or span limit. New local stops appear separately from provider response errors. The blocked call adds no transfer. Historical `provider_budget` remains a combined call/token category. The private conversation export retains measured timing fields. Neither store records arbitrary provider headers, request payloads or response bodies. OpenAI's `x-request-id` is a provider-side identifier. The application's trace ID has a separate origin. [OpenAI debugging requests](https://developers.openai.com/api/reference/overview#debugging-requests) · [OpenAI error codes](https://developers.openai.com/api/docs/guides/error-codes) · [Google Gemini troubleshooting](https://ai.google.dev/gemini-api/docs/troubleshooting) · [cURL error 28](https://curl.se/libcurl/c/libcurl-errors.html)

The offline diagnosis fixtures cover three timed-out transfers followed by HTTP 503, and an HTTP 429 followed by HTTP 200. These patterns test interpretation of recorded evidence without issuing a provider request.

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

## Follow-up contracts: context, action receipts, lifecycle and history

- `contextForActor` authorizes the session first and supplies at most four prior
  completed turns and an 8,000-byte JSON dialogue budget by default. Dialogue is
  untrusted reference, not an instruction layer. Both agent prompts use it.
  Ordered public product slugs are saved with a turn; ordinal follow-ups fetch
  the current visible catalogue again instead of trusting historical prose.
- `requestedCollection` recognizes explicit Tamil Ayyappa/festival aliases.
  Generic or tied festival matches ask the customer to choose a real published
  collection, rather than substituting unrelated products. This is tested alias
  support, not a claim of complete multilingual semantic retrieval.
- Agent-created tickets use a deterministic action ID scoped to normalized
  account, conversation and trace. `insertIfAbsent` returns the original ticket
  on retry. The business ticket contains authorized conversation/turn references
  and a request digest. Legacy non-agent requests still receive unique IDs.
- Existing support-ticket JSON business rows now retain versioned staff actions:
  claim/release, priority, waiting/snooze, reply, close and reopen. The DB host
  performs a row lock and expected-version check in one transaction. Original
  `open`/`answered`/`closed` values remain compatible; `work_state` carries waiting
  or snooze. First human response, resolution episodes and actor transitions are
  distinct from bot latency. A saved reply is not proof of email delivery.
- History parsing is bounded to 200 candidate documents per page, with opaque
  continuation cursors and a Load more control. Directory metadata enumeration
  remains linear; this is not a claim of an indexed million-session database.
  Explicit owner deletion refuses unfinished actions. Export and deletion routes
  require a successfully persisted audit event before releasing/removing content.
- Every trace records the instruction-source hash, named tool-code hash, approved
  aggregate facts, result digest and observed timestamp. It does not archive
  private raw result rows or claim a source revision was supplied when it was not.
- Native chat budgets are 32 provider transfers, a 110-second inference deadline,
  115 seconds total traced execution and 16,000 observed total tokens. The browser
  request limit is 120 seconds. Two cycles of three attempts per eligible model
  are permitted before a tool exchange; later tool rounds stay on the same model.
  Other trace callers retain their default six calls/30 seconds unless overridden. Missing usage
  is marked incomplete, not zero. Budget checks happen before further work and
  transport timeout is clamped to remaining time. No cost is invented.
- `AuditLogService` returns `not_persisted` on a failed write and exposes that
  operational loss to the trace without retrying business actions.

Run `php tests/agent-interruption.php` to terminate a disposable worker after a
real saved checkpoint; the unfinished operation survives without false completion.
`tests/support-ticket-concurrency.php` is restricted to the disposable MySQL CI
service, not production: repeated requests create one ticket and competing staff
updates reject stale versions. Its results must be observed in CI before release.

A separate numerical CSAT survey, all-traffic outcome reporting, a mature
feedback-to-content review workflow and the nine commerce expansion gaps are
**not** implemented by these changes. Current acceptance and remaining work are tracked in GitHub issue #251; the dated REA source comparison is historical evidence, not the backlog.

## Runtime instruction provenance

Live business instructions now come from the fixed `.bapX/` manifests documented
in `runtime-agents.md`, never `AGENTS.md` or `.agents/skills/`. `AgentInstructionService`
validates the server role and file boundaries, then records the actual manifest
and bundle SHA-256 into the active trace when a prompt is loaded. Local replies
that never invoke model instructions do not claim a loaded prompt. Offline
experiment fingerprints include every role-allowlisted Markdown source.

## Retrieval decisions and bounded quality-review queue

`AgentContextService::catalogueSelection` returns an explicit intent, match /
no-match / clarification state, reason, bounded candidate/result count, public
product references, available source update timestamps and a result hash.
Exact aliases and actual named-collection membership take precedence. Explicit
catalogue browsing remains bounded; a missing specific item never falls back
to the first 20 unrelated products. Known Tamil collection aliases use the
same stored membership. Unknown source freshness stays null, not invented from
the request time. Descriptions cannot select a product by injecting a name
into instruction-like text.

The same JSON turn retains the sanitized decision and the observed retrieval
span. Model product links must belong to the retrieved product references;
invented links fail the deterministic guardrail. This does not establish
semantic correctness of every sentence, factual accuracy or calibrated confidence.
Public shipping-policy answers can be resolved locally from approved values.

The 24-hour operational table now separates failed provider **attempts** from
failed tool **calls** and request errors; each denominator is shown explicitly.
The human-review view is derived from the latest 40 chat summaries and links
negative feedback, failed guardrails and unfinished requests to authorized JSON.
It is not a global support backlog or an adjudicated human quality dataset.
Silence is never counted as resolved or abandoned without an explicit policy.

Existing public slugs containing spaces or Unicode are retained and URL-encoded
in source IDs and links. Retrieval guards must not silently drop valid client
records because an assumed lowercase ASCII slug format is narrower than the
actual ProductService contract.

## Capability names and operational counts

Operational tool activity derives allowed names from AgentCapabilityPolicy's
Support and Enterprise lists. The historical `customer_ticket_lookup` observed
label remains readable without becoming an executable model capability. Only
completed/error events contribute to these counts; incomplete states and
unknown tools are excluded. The projection still discards raw arguments and
results. A registry declaration is not an execution event, so local retrieval
must not be invented as a tool call merely to populate a chart.

## Evidence-bound staff answer review

Agent Monitoring links each completed chat to `/admin/agent/review/{id}`.
The authenticated operator selects a turn, inspects its recorded question,
answer, safe tool/retrieval evidence and instruction manifest, and submits an
explicit four-part rubric: factual accuracy, source support, task completion
and policy/privacy compliance. Each criterion is pass/fail/not-assessed; a
failed item derives `needs_correction`, and missing assessments derive
`insufficient_evidence`. A factual/source pass requires selection of recorded
tool/retrieval evidence. This is a review contract, not automatic semantic proof.

POST uses existing admin authentication and CSRF. The service independently
requires a real authenticated admin identity. A server-computed hash binds the
review to the original answer/evidence; the form also carries the current review
revision. A stale evidence hash or concurrent revision returns 409. Six-worker
filesystem tests prove one matching-version save wins while stale writers do
not overwrite history. Up to 20 bounded reviews append to the same turn in the
same private chat JSON; no separate review/transcript database or JSON mirror is
created. A review preserves the original answer, trace, automated evaluation,
customer feedback, ticket state and customer-activity ordering.

Operators declare `human`, `agent_assisted` or `verification_test` authorship.
The human option requires explicit personal attestation. The system records
**staff-declared** provenance; it cannot independently prove a physical human
performed the review. Automated QA must select verification_test, never claim
human authority. Latest human, assisted and test assessments for the same turn
are separate; a later test cannot mask an earlier human correction. Provenance
counts can overlap and must not be summed as unique reviewed turns.

Monitoring shows the recent-40-chat denominator, completed turns, latest human
verdicts, nonhuman/test annotations, unreviewed turns and review reasons.
Topic grouping uses the latest conversation intent, explicitly labeled, not
claim-level all-traffic clustering. These are selected review labels, not a
statistically representative production accuracy score. A human pass never
marks customer-confirmed resolution; only the existing customer action does.

The 31-case deterministic corpus includes exact net-sales answers with verified
refund/unpaid/cancelled/out-of-period fixtures and exact GST snapshot/count/scope
evidence. These synthetic known-answer tests are reproducible regression
coverage, not human-reviewed real-world labels or tax-filing certification.

### Selective runtime skill evidence

The model-only registry records the actual role constitution and shared skill
hashes, then adds each selected runtime document to the request's instruction
manifest. No instruction body or raw provider argument is added to operational
telemetry. Model-selected Support reads record their own tool spans, evidence
and public product-source identities. Public catalogue counts and bestseller
ranking are business reads, not customer or owner-wide private records.
All fresh free-form questions use model inference. Catalogue totals and best sellers are computed by model-selected tools, not natural-language shortcuts. Both surfaces share published article search and bounded exact body retrieval; evidence records public identities and excerpt digests, not raw query text or private documents. A failed lookup never becomes zero counts. Explicit commands and historical local-answer records remain distinguishable from model outcomes.
