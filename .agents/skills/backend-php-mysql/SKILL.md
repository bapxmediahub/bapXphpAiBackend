---
name: backend-php-mysql
description: Use when editing PHP controllers, services, hosted MySQL persistence, authentication, agent context, orders, reviews, media metadata, payments, or audit behavior.
---
# PHP and MySQL Backend

- Follow the root `AGENTS.md` repository contract.
- Keep route -> controller -> service -> MySQL boundaries through `DatabaseService`; extend existing services instead of creating parallel stores.
- Hosted MySQL remains the only runtime store for **business records** (products, users, orders, tickets, media metadata, settings and secrets). Use direct MySQL or `<APP_URL>/remotedb` for approved business operations; never create local data mirrors.
- **Explicit exception:** Support/Enterprise chat records use one private JSON file per conversation in `storage/agent-conversations/`. Do not create a chat SQL table or use `/remotedb` for conversation turns. `AgentConversationService` owns atomic writes, actor checks and explicit feedback; `AgentTraceService` stores sanitized source/tool/provider/eval spans inside each turn.
- `storage/schema/collections.php` declares collection shapes. `bapXphp db init` creates tables. `db sync` is only a compatibility alias for initialization; it does not import or export JSON/YAML.
- Blog bodies remain Markdown with YAML frontmatter in `content/blog/posts/`. Image binaries remain in `assets/images/` or writable upload storage.
- `MediaService` writes metadata to hosted MySQL `media_files`. The shipped legacy catalogue is an offline migration input. Use `./bapXphp media:reconcile` to check archived and installation-specific YAML against MySQL. `--apply` requires direct MySQL and preserves existing fields. New uploaded binaries live in `.sps-media` beside the checkout, served by the constrained `/assets/images/media/…` handler in `index.php`; deployments must preserve that sibling directory.
- Filter customer/support retrieval through `AgentContextService` and `SupportToolRegistry`, with signed-in owner queries through `EnterpriseToolRegistry` / `AgentToolRegistry`. Authorization lives in `AgentCapabilityPolicy`, never in an LLM prompt or browser-selected role.
- Tool evidence may retain only sanitized arguments (e.g. validated date range, status, collection and result count), never raw customer email, private order ID, lookup coupon arguments, credentials, raw SQL, complete tool results or hidden model reasoning. The private `commerce_report` presentation exception stores only `AgentTraceService::safeCommerceReport` fields: bounded typed rows with product/coupon report labels, anonymous customer bands, aggregate summaries and canonical scope/source. Validate again on replay; never generalize this into raw result storage. Local eval checks are not proof that arbitrary outputs are factual.
- Public consultation booking is retired. `ConsultationService`, `consultation_messages`, and `consultation_signals` are retained legacy code; historical appointments remain owner-accessible. Do not restore public booking, messaging, calls, or WebRTC without an explicit product change.
- When direct MySQL is unavailable, use the collection-allowlisted `DatabaseService` protocol at lowercase `/remotedb`. Authenticate with the configured database password; `RemoteDbController::requirePassword` now **fails closed** on an empty password (503) and rejects invalid credentials (401). Never send raw SQL from an agent, expose credentials, or route chat files through the DB bridge.
- Keep payment clients as small `integrations/` wrappers, source secrets through `SecretService` or system environment variables, and verify signatures before mutating orders or wallet balances.
- Use `bapXphp read blog <slug>` and `bapXphp write blog [slug]` for blog operations. Product and other admin-editable content must be read and written through hosted MySQL-backed application flows.

## Testing

Run `php -l` on every changed PHP file and then `./bapXphp test`. For storage changes, exercise the affected admin page against the remote database. Confirm transport, non-2xx, and invalid-response failures surface visibly; repeated reads are memoized within a request; and mutations invalidate cached reads.

## Keeping this skill current

Update when a service boundary, remote mutation protocol, consultation product policy, or runtime store changes. Read `storage/schema/collections.php` first because it is canonical.

## Agent and support persistence checks

For agent changes run `php cli/agent-eval.php` and the PHP
regressions. For chat filesystem changes also run
`php tests/agent-concurrency.php`, ensure only one JSON file per
chat and verify owner/guest isolation and CSRF-protected support feedback.
Keep metadata-only `audit_events` separate from sensitive chat
history. Before release verify Hostinger preserves the ignored JSON files.

## Execution evidence contract

For both agent surfaces, prepare the actor-owned private JSON request before
executing tools/providers. Persist actual span boundaries through
`AgentTraceService`; never backfill timestamps or call a produced response
delivered. Use effective result scope instead of requested filter arguments.
Unfinished requests are visible in Agent Monitoring and must not be blindly
replayed. Keep provider tokens/usage unavailable when absent, and distinguish
versioned offline regression scores from real customer satisfaction/accuracy.

## Support write consistency

Agent ticket creation is a controller business action, never a model-supplied
SQL/file tool. Preserve deterministic action identities and insert-if-absent
receipts. Staff transitions run through `updateSupportTicket` on the database
host with a row lock and expected version; do not restore read-all/upsert reply
or close logic. Test against the disposable CI database and do not infer email
delivery from a saved reply. Conversation history remains one private JSON file.

## Developer/runtime instruction separation

This `.agents/` skill is for repository coding tools only. Live business
agent prompt text lives under `.bapX/`, selected by `AgentInstructionService`
with a fixed role allowlist. Update `docs/runtime-agents.md`, instruction
content fingerprints and isolation tests whenever that contract changes.
Never route a model-supplied path or developer index into live instructions.

## Public contact admission

Use the existing ContactService and owner Inbox, not an unverified visitor-to-SMTP
relay. Contact admission budgets are atomic in a private deployment-local file.
Do not send automatic contact mail or claim held historical contact rows; leave
account/order notifications and unrelated SMTP states unchanged. Re-enabling
automatic contact mail requires reviewed recipient verification and anti-abuse
controls. Never test by flooding the production form or replaying its backlog.

Published campaign forms share contact admission. Validate answers against
the exact published revision, and verify CSRF and the session receipt before
accepting a submission. On the database host, lock the authored page and persist
the Contact and its Inbox projection together with one idempotent receipt.
Store campaign slug and revision on the owner records. Keep form text out of
logs and leave the mail queue untouched.

## SMTP persistence and retry boundary

A pending queue row must be atomically claimed on the database host before
SMTP. Only the matching attempt can complete it. Unknown/interrupted attempts
are never automatically replayed; accepted DATA is not reversed by a lost QUIT
reply or logging failure. Update individual queue/outbox rows, never replace
those collections. The recorded `sent` state means SMTP acceptance (or legacy
sent), not inbox confirmation. Use loopback SMTP and disposable MySQL tests;
do not replay the customer's backlog or create fake paid orders for QA.

## Runtime identity and deployment target

Target stable PHP>=8.3 and MySQL>=8.4. `RuntimeHealthService` reads bounded
version/vendor facts; a MariaDB or unknown result is not a MySQL pass. Use
runtime:check and actual web System Health, and keep raw exception/DSN/secret
content out of output. A compatibility report is not authority to migrate a
production database or fabricate query/backup parity. Preserve older running
sites until a separate approved cutover is verified.

## Authored-page headers

Keep the optional uploaded image URL and page-specific alt text in the same
revisioned `pg:<slug>` row as the Markdown. Validate a local raster URL and its
Media Library ownership on the database host. Header attachment and upload
metadata removal use a shared upload row lock. Keep the existing publication
predicate, and verify the legacy no-image path, invalid ownership, stale save,
and removal after clearing in the isolated database and owner preview.

## Authored article integrity

All article writes must use BlogService's expected-content revision and atomic
lock/replace contract. Do not bypass it with direct file writes or reload a
newer revision to force a stale form through. Public consumers use isPublic/all
with the configured timezone and category rules. Preserve conflict recovery,
path guards and raw-source denial. Scheduled visibility is not scheduled email.

## Transactional email event and recipient contracts

MailTemplateService renders pure event content; it does not choose a recipient,
issue an invoice, create an order or send. MailQueueService owns audience/event
identity and SMTP acceptance. Preserve configured owner versus customer routing.
Use the public site profile for subject, shell identity and help links in new
messages. Keep SMTP sender, Reply-To and recipient configuration independent.
Previously rendered queue records retain their original message content.
Google welcome is only for a new persisted verified customer; normal login is
not signup. Password-change mail follows successful account mutation only.
Synthetic admin previews/samples must remain conspicuously labelled and cannot
contain usable reset/order/invoice targets. Do not equate sample receipt or
legacy sent status with real order/customer/owner acceptance. No live charge,
password change or backlog replay merely for QA. Keep the separate late-view
error-handling concern out of template patches.

Scheduled diagnostics use one explicitly confirmed synthetic queue record.
Keep sample-only selection and atomic claim checks aligned on scope, eligibility
and expiry. Test with an unrelated due record and a loopback SMTP recipient in
the disposable MySQL fixture.

## Structured answer review

Use AgentConversationService reviewDetail/reviewTurn for owner-only per-answer
assessment. Preserve original evidence and customer feedback, compare the
server evidence hash plus expected revision, and append under the existing
per-chat lock. No SQL/JSON mirror. Staff-declared human review requires explicit
attestation; automated tests must declare verification_test and must not inflate
human counts. Run tests/agent-review-concurrency.php along with chat concurrency
and interruption tests. Stale revisions/evidence are conflicts, not auto-retries.

## Dynamic request failure boundary

RequestFailureService replaces partial dynamic output with a safe correlated
500, not a generic database accusation or a promise that nothing was recorded.
Do not print raw exception/session/provider details even to an admin. Preserve
explicit controller503/CSRF/redirect semantics and CLI nonzero failures; never
retry a business mutation from the generic handler. Run the loopback-only
`tests/http-response-boundary.php` through normal CI and verify browser bodies,
not status alone. Do not introduce a production crash route or streaming flush
without an explicit compatible response design. See `docs/request-failures.md`.

### Product catalogue imports

Product creation from the owner form and CSV confirmation share a database-host lock. The import revalidates the entire bounded CSV, preserves slugs excluded at preview, rechecks current slugs and commits new hidden products in one transaction. Remote clients use the authenticated products actions `save_catalog_product` and `import_products` so these checks run on one MySQL connection. Verify replay, rollback and legacy IDs on an isolated fixture database.

## Public knowledge contracts

Keep product/article/contact/service facts in PublicKnowledgeService, with
shared PHP schemas and explicit public projections. Never expose a settings
row through a public tool. The human contact page uses the same public values
and four enquiry subjects; no retired consultation booking is advertised.
Support and Enterprise use the same public tool implementation and policy
entries. Trace contact/service retrieval as observed tool calls with counts,
not raw contact submissions or user search text. Product descriptions are
bounded plain text and images must be safe same-origin raster media paths.
See docs/webmcp.md and tests/public-knowledge.php.

Named public comparisons share AgentContextService::namedComparisonProducts and
PublicKnowledgeService's exact-slug projection. Keep two or three verified choices
in mention order; never substitute type examples for a missing named product.
Support's model-selected comparison facts and rendered cards must use the same identities. All fresh free-form agent questions invoke the shared model loop; never bypass inference to compose a local reply.
Run tests/catalogue-comparison.php when this selection contract changes.

## Public MCP transport

Keep `/api/mcp` as an exact stateless public route with its own fixed allowlisted
dispatcher. Never reuse the trusted local STDIO grant registry or a browser
actor. Preserve strict modern framing and the explicit default-client legacy
profile without a downgrade fallback or stored initialization state. Validate
Origin against the configured HTTPS site; the MCP session bypass must match only
this exact route. The separate exact Razorpay webhook bypass accepts only raw-body
HMAC authority and must never inherit a browser actor. Build MCP Apps cards from the returned public page without a second
lookup, retain text fallback, and let the host authorize navigation. Run the
official default/modern client, native HTTP and AppBridge browser fixtures;
after the live SHA gate run the public read-only endpoint probe. See
docs/public-mcp.md for contracts and client-capability limits.

## Anonymous remote commerce

`RemoteCartService` owns anonymous cart create/get/absolute-quantity updates
and the website review fallback. Public MCP exposes separate create/get/set,
review, confirmed payment preparation and status tools; never hide operations
behind a model-callable generic selector. MySQL `agent_carts` is not an admin resource or
agent-context collection. Keep the per-cart secret explicit and bounded to 24 hours,
store only its digest, and use the fixed database-host operation/row lock for
both direct and authenticated remote persistence. Preserve creation limits,
resource ceilings, revision conflicts and immediate replay semantics.

The website fallback uses a separate 15-minute ticket, never the persistent cart token in a
URL. GET cannot import a cart. The explicit replacement checkbox and CSRF POST
claim the ticket for the server session before importing browser lines. Preserve
same-browser interrupted-save recovery and the session replay receipt so an old
POST cannot overwrite later edits. The fallback itself creates no order/payment.
Resolve current public products and
offer prices, retain paise through display/gateway rounding, and reject stale
availability. Run the pure, disposable MySQL/HTTP and browser checks described
in docs/remote-commerce.md; never create a live order merely for QA.

`RemoteCheckoutService` owns atomic delivery/coupon review and the server quote,
not an account/address book. Keep full contact/address/coupon data out of tool
responses, model summaries and stored chat HTML. Bind explicit confirmation to
the exact cart revision, quote digest/ID and expiry. Persist the private
`checkout_attempts` row and cart lock atomically before provider I/O; use unique
reference lookup after uncertainty and never blindly repeat provider creation.
Unknown lookup is not proof of expiry. Payment requires the provider's minimum
INR amount, current availability and the same normalized mode credentials used
by execution. Keep the merchant payment ticket separate from cart and seven-day
status capabilities; the fixed chat form must not embed a generic provider
checkout or collect payment credentials.

Only authenticated captured-INR verification may enter `VerifiedOrderService`.
Preserve actual discount and complete frozen supplier/product tax fields; missing
product availability still creates a fulfillment hold. Shared persistence never
clears a browser session. Signed raw-body webhooks and bounded reconciliation
must recover order saves after chat/cart expiry. Unresolved attempts survive
cart cleanup, and capacity rejection must roll back the cart lock. Test with
real pure transitions, a fake gateway, disposable SQL/HTTP concurrency and native
form recovery; never treat fixture success as live payment proof.


## Authored-page downloads

Validate the combined file size from actual uploads and retained descriptors
against the same bound in the PHP editor and database-host revision write.
Keep immutable bytes outside the checkout under `.sps-downloads`. Publish
the page reference only after every new file is staged. Clean up new bytes
on a definitive stale or invalid result. Preserve them after an ambiguous
database acknowledgement so a committed page can still resolve its files.
Confirm the current published revision and access grant before streaming an
attachment. Keep names and identifiers out of unauthorized markup.

## Authored campaign booking consistency

Use the database-host campaign booking transaction to read the published page
revision and freeze the approved integer INR paise, gateway mode, and enabled
file descriptors. The first durable booking reservation owns provider creation.
Recover an ambiguous provider response by reference.
Retry only a verified pre-creation rejection through the owner-bound start POST.
Under the database lock preserve the snapshot and increment the creation
attempt before any provider POST. Ignore older provider results. The shared Razorpay
webhook verifies its mode-specific signature before checking the provider API.
Apply captured receipts and frozen paid files in one booking transition.
Verify that signed duplicates, delayed expiration, and wrong amounts cannot
overwrite a receipt or create a shop order. Use deterministic provider fixtures
and the disposable MySQL job.
