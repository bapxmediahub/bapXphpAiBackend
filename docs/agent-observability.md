# Native PHP agent observability and commerce insights

This implementation requires no paid telemetry or eval service. It extends the existing provider client, MySQL data layer and admin UI.

## Surfaces and capabilities
- Support uses `SupportToolRegistry`: published catalog/policy and authenticated customer ticket facts. All private lookups derive identity from PHP authentication; arbitrary SQL, admin tools, file writes and customer selectors are unavailable.
- Enterprise uses `EnterpriseToolRegistry` on the existing `AgentToolRegistry` read-only allowlist. Admin role is verified by PHP before a lookup. Site changes continue through reviewable draft forms, not raw SQL or file writes.
- `AgentInstructionService` versions baseline instructions separately for support and enterprise. Text instructions complement, never replace, enforced PHP permissions.
- `AiClient` retains current model/provider compatibility and bounded tool execution. Provider secrets remain in configured integrations.

## Conversations and trace retention
- MySQL `agent_conversations` is created on the database host when first used, using the existing remote DB bridge if needed. The runtime database user needs CREATE TABLE privileges for the first request, or the table must be provisioned in advance.
- Each conversation is one JSON document in the private MySQL table, with actor type/id, timestamps, turns, tool summaries, provider status/timing and deterministic evaluation checks. Session IDs are random and access is checked against the authenticated actor. Browser-submitted report filters are **not** trusted as server-verified history.
- The Admin Agent Monitoring page lists recent sessions and allows authenticated administrators to export individual JSON transcripts. The audit_events operational log remains metadata-only; it does not contain full conversation bodies.
- For **one physical JSON file per chat**, provision a private directory outside document root and the application tree, restrict it to the PHP runtime user (mode 0700), and set `BAPX_AGENT_JSON_DIR` to its absolute path. Files are atomically refreshed as `<conversation-id>.json`, mode 0600. MySQL remains canonical; the file is a recoverable snapshot.
- Transcripts can contain customer conversations. Establish retention, deletion/export access policy and storage encryption at rest before enabling broadly in production. Never store credentials, payment secrets or hidden model reasoning.
- An unavailable transcript store currently fails the chat request rather than falsely claiming successful audit persistence. Payment/ticket actions must not be automatically retried in this state.

## Evaluations
`AgentEvaluationService` performs deterministic safety/format checks per turn (empty answer, internal scaffold, secret-looking text, unsupported tool names and some enterprise source expectations). These are **basic assertions**, not claims of objective answer accuracy. `tests/run.php` contains permission, transcript, telemetry and SEO regression fixtures. Improve with reference datasets for exact GST/order totals, ownership isolation and instruction following before declaring production-level evaluation coverage.

## Commerce and SEO data
- First-party `cart_add` and `checkout_initiated` events record minimal metadata using the existing audit_events collection. No IP address, email, cookies or user profiling data is stored in these events.
- Paid-order counts, net revenue and refunds come from saved orders via `StoreStatsService`. Funnel events are counts, not unique-user conversions.
- `SiteInsightsService` scans published products/articles for missing titles, short text, image gaps and excerpts. These are site content checks, **not** search impressions, keyword rankings or organic traffic. Existing GA4/Search Console links remain optional; local insights work without them.

## Deployment checks
Run `BAPX_TEST_MODE=1 php tests/run.php` and the project-map checks, then verify on staging:
1. Guest/customer/admin sessions cannot read each other's chats or tool data.
2. Multiple turns retain report scope across unrelated turns; new chat resets it.
3. MySQL conversation append works through direct and remote paths, including concurrent requests.
4. Admin export is private, correctly escaped and contains the expected turns.
5. Checkout/cart events do not affect purchase success or payment verification on telemetry failure.
6. Dashboard works with no conversations and no commerce events.

This branch is not a production deployment and does not claim live end-to-end verification.
