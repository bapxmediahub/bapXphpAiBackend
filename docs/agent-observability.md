# Native PHP agent observability and commerce insights

The application has two PHP agent surfaces: Support and Enterprise. No paid
telemetry platform, evaluation service, or separate chat database is required.

## Capabilities and orchestration

- **Support** uses `SupportToolRegistry` with published shop/policy data and the authenticated customer's own scoped ticket records. It cannot run arbitrary SQL or write files.
- **Enterprise** uses `EnterpriseToolRegistry`, which exposes only the existing named and validated read-only business queries. Drafted changes still require the admin's confirmation through normal forms.
- `AgentCapabilityPolicy` enforces authorization in PHP, not by trusting model-generated arguments. `AgentInstructionService` supplies separately versioned instructions.
- `AiClient` continues to handle configured model providers, bounded tool execution, and failover. Business records stay in the existing MySQL collections.

## One JSON file per session

- **Authoritative transcript location:** `storage/agent-conversations/<conversation-id>.json`, where the ID is a random 32-character hex string. No `agent_conversations` table exists and no conversation rows are written to MySQL.
- On the first message, PHP creates the JSON file. Each additional message appends a turn to that same file. New chats get a new ID. Each JSON document contains the actor type and authenticated ID or anonymous guest token, timestamps, messages, trace IDs, bounded tool/provider metadata and evaluation results. Secrets are redacted before saving.
- `AgentConversationService` uses a shared `.conversation.lock` and atomic temporary-file rename to prevent concurrent writes from losing turns. The lock is bookkeeping, not an additional conversation record.
- The PHP runtime must be able to create/write files under `storage/agent-conversations/`. The directory is private (0700 when newly created) and JSON files are restricted to mode 0600. The tracked directory `.htaccess`, root `.htaccess` and built-in PHP server guard deny public access to /storage URLs. Never serve JSON transcripts as static files.
- All conversation JSON files are ignored by Git and must be retained through hosting deployments. **Back up** `storage/agent-conversations/` separately, alongside the existing MySQL and media backups. They contain customer business data and should have an explicit retention/deletion policy and storage encryption where available.
- Admin Agent Monitoring lists recent session metadata and offers an authenticated JSON export. It does not load all transcripts into the dashboard or expose them in the public UI.
- User or admin chat questions and any report follow-up dates are loaded only from a session belonging to the authenticated actor. The browser cannot make another person's conversation accessible merely by supplying its ID.
- Recording fails visibly when filesystem persistence is unavailable; avoid claiming that a support ticket or other action failed solely because its conversation audit could not be saved.

## Evaluations

`AgentEvaluationService` performs local deterministic checks for empty answers,
internal response scaffolding, secret-like text, unsupported tool names and basic
business lookup requirements. They are checks, **not** proof of factual accuracy or
quality. Extend with golden financial, privacy and support-resolution fixtures
before treating them as a production quality score.

## Commerce and SEO

- First-party cart-add and checkout-start counts use the existing operational `audit_events` collection, independently of chat JSON files. No IP, raw prompt or customer email is stored in these event records.
- Paid-order counts, net revenue and refund totals use `StoreStatsService`. These are business statistics, not unique-user web conversion rates.
- `SiteInsightsService` checks published product/article metadata for missing titles, short descriptions, images and excerpts. These local checks do **not** measure Google search impressions, traffic or rankings.

## Required verification

Run `./bapXphp update`, `./bapXphp ci`, and `php tests/agent-concurrency.php`.
The last command uses only a disposable local filesystem directory, creates
no SQL tables and tests concurrent appends, actor isolation and session lists.

Verify in Playwright on desktop and 375px mobile:

1. Guests and logged-in customers can request support, and consecutive turns land in one JSON file.
2. Admin Agent new chat resets its conversation ID and old report dates do not leak into another actor's chat.
3. The admin monitoring view shows only summary metadata. Its authenticated export produces the right JSON, while unauthenticated direct file requests are denied.
4. Files persist across the Hostinger Git auto-deployment flow.
5. Cart/checkout telemetry failure never changes payment success or order persistence.

The feature is not production-verified until the live host can write to storage
and the authenticated end-to-end checks have passed.
