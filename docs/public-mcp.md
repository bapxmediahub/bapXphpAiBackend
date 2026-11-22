---
type: doc
title: Public MCP tools and chat cards
description: Stateless public Streamable HTTP MCP and the bounded MCP Apps presentation.
category: docs
---
# Public MCP tools and chat cards

The public endpoint is `POST <APP_URL>/api/mcp`. It exposes the same four published-information tools as the website, plus six focused anonymous commerce operations: `create_cart`, `get_cart`, `set_cart_quantity`, `review_checkout`, `prepare_checkout_payment`, and `get_checkout_status`. Each commerce call has an explicit capability boundary; it does not use an actor, browser session or owner grant. Shop gates new shopping and Blog gates articles. An existing seven-day payment status receipt remains usable when Shop is off. No private guide, conversation, arbitrary customer/account order, contact submission or booking is exposed. The trusted local STDIO interface retains its separate grant boundary.

## HTTP discovery and official product URLs

`GET /api/agent-tools` is a **site-specific** JSON discovery document, with
`format: bapx.agent-tools` and `version: 1`. It is not a standardized
`.well-known` manifest. `PublicAgentDiscoveryService` consumes the actual
`PublicMcpService::toolDefinitions()` registry, including current module gates,
input/output schemas, annotations and MCP Apps metadata. It never manufactures
an alternate list of agent tools. Existing public GET handlers are listed as
HTTP equivalents only when their corresponding tool is currently published.

The document includes the configured origin, MCP URL, supported protocol
versions, exact public read requests and shell-quoted curl examples. The
canonical public guide at `/ai-assistants` and `/llms.txt` includes GET reads and
complete MCP list/call examples for agents with no browser. Tool errors may
still use HTTP 200; clients must check both JSON-RPC `error` and `result.isError`.
MCP Apps rendering requires a compatible connected host. Discovery alone does
not install a connector, grant a capability or create buttons in an arbitrary
chat session.

Each registry entry carries explicit `_meta["in.bapx/access"]` scope and
authorization metadata. The discovery service groups those declarations and
does not infer public access from `readOnlyHint`: a capability-scoped cart read
would still have a different access boundary from public catalogue data. No
capability values, cookies, actor records or private tool registry enter this
response. The route accepts GET with no query parameters and returns no-store
JSON; unavailable configuration/source returns 503 without raw error details.

The `commerce` section identifies only commerce tools actually present in that
registry and states the canonical cart, quote and status lifetimes. Executable
discovery examples remain bounded reads. The reviewed public guide adds a
separate explicit cart-creation curl example using a private request/response
file, so access handles need not appear in command arguments or shell history.
No anonymous cart receives a public GET equivalent.

`PublicSiteUrlService::origin()` shares the validated configured HTTPS origin
with MCP and public product projections. It never uses the request's Host
header. Every current product result includes `productUrl`; an approved
`imagePath` also has `imageUrl`, and selected `imagePaths` have matching
`imageUrls` in the same order. Only approved media already present in the
published product record qualify. Missing or foreign images receive no
substitute. The original relative fields remain intact for A2UI, and the
additional schema fields are optional for older payload compatibility.

The browser product adapter validates absolute fields against the canonical
origin safely embedded in the page and the matching validated relative paths.
An alias host cannot replace that authority. Invalid origin configuration
keeps normal storefront HTML available, while public URL-producing reads fail
closed; legacy relative-only fixtures still validate.

## Transport contract

The public `/ai-assistants` page and advisory `/llms.txt` explain how to connect
this endpoint. Both use the same reviewed public Markdown source and configured
HTTPS origin. They do not install a connector or grant credentials. Server
instructions describe the same search, exact-selection, human-link and public
scope boundaries; they explicitly state that new consultation booking is
retired.

The endpoint supports two explicit profiles: [MCP revision 2026-07-28](https://modelcontextprotocol.io/specification/2026-07-28/basic/versioning) and the narrow [2025-11-25 handshake profile](https://modelcontextprotocol.io/specification/2025-11-25/basic/lifecycle). Both expose the same current registry and remain stateless at the transport: no MCP session identifier, PHP session, GET event stream, or DELETE session operation. Anonymous commerce state belongs to the explicit bounded server capabilities, not a transport session. Older protocol revisions are not advertised.

Every POST carries one JSON-RPC 2.0 message and `Content-Type: application/json`. The client accepts both `application/json` and `text/event-stream`; this bounded server responds with JSON. A request body is limited to 64 KiB.

### Current protocol: 2026-07-28

There is no initialization handshake in this profile. Requests include these headers:

| Header | Required value |
|---|---|
| `MCP-Protocol-Version` | `2026-07-28`, equal to the body metadata |
| `Mcp-Method` | The JSON-RPC method |
| `Mcp-Name` | Tool name for `tools/call`, resource URI for `resources/read` |

The `params._meta` object includes `io.modelcontextprotocol/protocolVersion` and the object `io.modelcontextprotocol/clientCapabilities`. Client name/version in `io.modelcontextprotocol/clientInfo` is optional and confers no access. Header names are case-insensitive; their values are checked against the body. `Mcp-Name` supports the standard Base64 sentinel encoding. See [Streamable HTTP](https://modelcontextprotocol.io/specification/2026-07-28/basic/transports/streamable-http).

Supported methods are `server/discover`, `tools/list`, `tools/call`, `resources/list`, `resources/templates/list` (empty) and `resources/read`. Every successful result includes `resultType: complete`; discovery, lists and resource reads include `ttlMs: 0` and `cacheScope: public`. No subscriptions or change notifications are advertised. Lists have deterministic order and fit in one response; a supplied cursor is rejected. Tool search pagination remains the bounded `offset`/`limit` arguments. See [caching](https://modelcontextprotocol.io/specification/2026-07-28/server/utilities/caching).

Missing or mismatched mirrored headers return HTTP 400 / `-32020`; unsupported versions return 400 / `-32022` with supported versions; unknown RPCs return 404 / `-32601`; invalid metadata or unavailable tools/resources return 400 / `-32602`. There are no supported incoming core HTTP notifications in this profile; notification POSTs receive 400 with no JSON-RPC response. An `initialize` request with modern framing remains an error.

### Default-client compatibility: 2025-11-25

The official TypeScript client still [defaults to legacy negotiation](https://github.com/modelcontextprotocol/typescript-sdk/blob/main/packages/client/src/client/versionNegotiation.ts), and the [basic-host reference](https://github.com/modelcontextprotocol/ext-apps/blob/main/examples/basic-host/src/implementation.ts) uses an unconfigured client. These clients send `initialize` first. The endpoint validates its `protocolVersion`, object `capabilities`, and `clientInfo` name/version, then returns `protocolVersion: 2025-11-25`, server capabilities, `serverInfo`, and instructions. If initialization requests another revision, the server offers the one supported legacy revision; a client that cannot use it must disconnect.

Later legacy requests carry `MCP-Protocol-Version: 2025-11-25`. They do not require `Mcp-Method`, `Mcp-Name`, or the modern per-request metadata. The supported methods are `tools/list`, `tools/call`, `resources/list`, `resources/templates/list`, `resources/read`, and `ping`. Legacy results use their standard envelopes without modern `resultType`, `ttlMs`, or `cacheScope` fields. The required `notifications/initialized` receives HTTP 202 with an empty body; unsupported or malformed notifications receive 400 with an empty body. See the [legacy HTTP transport](https://modelcontextprotocol.io/specification/2025-11-25/basic/transports).

The compatibility layer stores no client identity, capability, or authorization state. Public data and optional public presentation metadata are safe to return without a session. Missing modern headers or invalid modern metadata never trigger a downgrade: requests containing modern framing are checked by the strict modern profile. Do not mix the two wire formats.

### Shared boundaries

Parse and malformed-request errors use standard JSON-RPC codes. A failed public read returns 503 / `-32603`, with no raw exception content. Invalid tool arguments produce a readable `isError` tool result. GET/DELETE receive 405. Neither protocol can gain private tools through client-supplied capabilities, cookies, bearer tokens, or a session ID.

Expected cart/checkout refusals also use HTTP 200 with `result.isError:true`.
`_meta["in.bapx/shoppingError"]` contains a safe fixed `code`, semantic
`httpStatus` and `retryAfter`. For example, a stale cart revision has
`httpStatus:409`; a per-capability rate limit has `httpStatus:429`. These are
tool-error metadata, not a claim that the outer HTTP response was 409 or 429.
The text never echoes a capability, personal input or provider exception.

The exact `/api/mcp` route skips PHP session initialization. Cookies and bearer tokens cannot add tools. `Origin`, when present, must match the configured HTTPS `APP_URL` origin; invalid origins receive 403, including preflight. Server-side agents may omit `Origin`. Same-origin browser preflight advertises only POST and the MCP headers; no credentialed cross-origin access is enabled. `APP_URL` must be an HTTPS origin without credentials, a path, query or fragment.

## Cards inside a compatible host

For product details or a comparison, call `search_public_products` first, then
pass up to three unique raw slugs from those published results in `slugs`.
Decode each product path's last segment once. This mode requires an empty
`query`, `offset: 0`, and a `limit` covering the selected count. It returns exact
visible matches in the requested order, omitting missing or hidden products.
The ordinary compact search remains unchanged. Selected records may add real,
bounded `imagePaths` (three), `highlights` (four), and `specifications` (six
label/value rows). Safe image paths are relative to the configured site origin.
No private stock, supplier, customer or owner fields enter this projection.

[MCP Apps](https://apps.extensions.modelcontextprotocol.io/api/documents/overview.html) is a host rendering extension. Products and the six commerce tools link `_meta.ui.resourceUri` to `ui://bapx/public-shopping.html`; articles, contact and services use `ui://bapx/public-knowledge.html`. Both resources are discoverable in both protocol profiles. A supporting client should advertise `extensions["io.modelcontextprotocol/ui"].mimeTypes: ["text/html;profile=mcp-app"]` in modern per-request capabilities or legacy initialization capabilities.

The public resource linkage and card metadata are also available to clients that omit that advertisement, including the default reference host. This is an intentional compatibility choice: the [Apps specification](https://github.com/modelcontextprotocol/ext-apps/blob/main/specification/2026-01-26/apps.mdx#clientserver-capability-negotiation) recommends checking capabilities, but its resource-discovery behavior allows standard text fallback when a host does not render the linked UI. The [OpenAI quickstart](https://developers.openai.com/plugins/build/app-quickstart) likewise registers public tool UI metadata without a capability-dependent variant. Metadata does not execute an app or grant a capability; the host controls resource loading, the separate UI handshake, and every UI action.

The host reads the resource using `resources/read`. It receives self-contained HTML with MIME `text/html;profile=mcp-app`, the public A2UI renderer and a small native-JavaScript host bridge. The shopping resource also embeds the fixed shopping/checkout modules and their shared CSS. Resource metadata permits image/static loads only from the configured site origin and no external connections or nested frames. No remote script or frontend build is required. The minimum Apps sandbox does not promise `allow-forms`: buttons and Enter handlers explicitly validate and invoke the host; no native form submission is needed.

The iframe handshake uses the separate [MCP Apps 2026-01-26 protocol](https://apps.extensions.modelcontextprotocol.io/api/variables/app.LATEST_PROTOCOL_VERSION.html): `ui/initialize`, then `ui/notifications/initialized`. Only after that does the host deliver `ui/notifications/tool-result`. The same iframe protocol works with either supported outer MCP profile.

`structuredContent` retains each tool's canonical output schema. Public information also supplies `_meta["in.bapx/publicSurface"]`, with at most four server-authored A2UI cards built from the exact returned page. Product and commerce results use `_meta["in.bapx/shoppingView"]` with fixed `products`, `cart`, `review`, `payment` or `status` values. These select trusted templates, not model-authored actions. Readable `content` remains available to plain clients. Commerce text contains only bounded human facts and the separate payment URL when applicable; it never serializes a cart or payment capability. Invalid/unsupported UI payloads retain a text fallback and cannot erase an existing valid shopping receipt.

The widget accepts messages only from its parent sandbox. It validates catalog components, bindings, public paths and official photo URLs. Model/tool arguments cannot change the configured origin. Human card links ask the host to open a safe URL through `ui/open-link`. Rendering product information creates no cart or checkout action. Named commerce controls call `tools/call` only through an advertised `serverTools` capability. They never fetch a private API directly from the iframe.

## Anonymous cart and inline checkout

The fixed tools use `RemoteCartService` and `RemoteCheckoutService`, also used
by the native Support shopping pane. There is no public operation-selector
executor. Tool descriptors carry precise annotations: creation is not
idempotent; absolute quantity updates are destructive and idempotent; review
can renew an expired quote and therefore does not claim idempotence. Preparing
the same confirmed quote reuses its durable attempt. Status is not marked
read-only because provider verification can finish persisting an already-paid
order. These annotations describe behavior, not authorization.

| Tool | Required access and behavior |
|---|---|
| `create_cart` | Creates an empty 24-hour anonymous cart; returns `cartToken` once in structured data. |
| `get_cart` | Requires that `cartToken`; returns current public items, estimate and revision, plus a status-recovery receipt for a locked checkout. |
| `set_cart_quantity` | Same capability, exact raw slug, absolute quantity 0–99 and observed `expectedRevision`; zero removes, at most 20 lines, conflicts require a fresh read. |
| `review_checkout` | Same capability/revision; optional complete buyer-provided delivery and coupon form. One atomic patch produces bounded completeness/region, coupon presence and exact integer-paise totals. Full address/contact/coupon values are not echoed. |
| `prepare_checkout_payment` | Same capability, exact `quoteId`, quote revision and `confirm:true` after explicit buyer approval. Records an attempt before provider I/O and locks the checkout. |
| `get_checkout_status` | Separate `paymentToken`, valid up to seven days; cannot change delivery or start payment. Reports verified payment and order status separately. |

Add to cart updates the current cart. Buy now creates a separate one-product
cart and opens its inline checkout; it does not merge another cart. Checkout
opens local delivery/contact/coupon fields. Only Review sends them to the
store. The buyer sees authoritative totals, GST inclusion, delivery region and
the local address they entered before pressing the exact-amount confirmation.
A quote lasts up to ten minutes and is checked again by PHP before payment.
Current shipping is zero because that is the canonical server quote; the UI
does not invent charges or profit/inventory quantities. Cart estimates and
quoted payment amounts are labelled separately.

Payment availability/mode come from actual server configuration. The provider
integration needs the merchant's Razorpay mode credentials, signed webhook and
supported Payment Links account capability; a UI card cannot enable these.
No external provider script or iframe runs inside the app. The supplied
`paymentUrl` is restricted to the configured origin's
`/checkout/payment?ticket=pay_…` path and is opened only by a separate user
button when the host supports `openLinks`. The merchant page requires another
explicit Continue to provider. The [OpenAI plugin guidelines](https://developers.openai.com/plugins/plugin-guidelines)
and [payment guidance](https://developers.openai.com/plugins/build/monetization)
do not make a generic app eligible for an embedded ChatGPT payment sheet.
The delivery form and review remain inside the chat; payment credentials do
not enter the tool schemas or model context.

A prepared link, browser callback or authorization is not a paid order. Only
`get_checkout_status` supplies refreshed provider-verified facts. It distinguishes
`captured`/`save_pending` from `captured`/`saved`; only a saved order has a reference.
An unknown provider response keeps the same durable attempt. A lost prepare
response can be recovered with `get_cart` while Shop is enabled. Do not create
a new checkout to guess whether the previous one worked. Status access stays
available when Shop is disabled; a saved cart handle alone cannot recover a
missing payment receipt while cart tools are disabled. See [remote commerce](remote-commerce.md)
for database, provider, webhook and reconciliation boundaries.

### Context, reload and interruption

Capabilities are intentionally model-visible in `structuredContent`, consistent
with browserless clients retaining opaque handles. They are never hidden by a
claim that all hosts isolate them. The MCP App keeps handles out of the DOM, URLs,
human text, logging and application storage. Only hosts advertising structured
`updateModelContext` receive a minimal successful-action snapshot: distinct
current/Buy now carts and the last payment receipt, amount and verified status.
No delivery/contact/coupon fields or raw error text enter that snapshot.
Context calls are serialized; refusal leaves local controls working and shows
an honest cross-turn limitation. Opening a payment page does not publish a
success claim.

MCP Apps use memory only. A new widget needs the completed tool input/result
that supplies the chosen handle; it never silently merges an unrelated cart.
The native Support adapter alone may keep a bounded capability/revision/expiry
receipt in that browser tab's session storage, outside saved chat HTML. Reload
clears form drafts and fetches current server facts before displaying them.
Transient fetch failure retains the unverified access receipt for a later retry,
without showing cached prices or payment claims. Definitively expired/unknown
access is removed. Shop-off mode restores only status access.

The shared module accepts an external native/browser-tool result atomically,
waiting at most 45 seconds for a current human action. An AbortSignal discards
only that queued synchronization. Wrong result families, mismatched handles,
malformed data, older cart revisions and payment-status downgrades cannot
replace newer valid state. Cancellation/teardown clears local personal drafts
and blocks later chained writes; late link/context acknowledgments cannot
resurrect a cancelled or newer checkout.

The host can tear down the widget before initialization completes as well as
afterward. Teardown acknowledges once and releases timers, pending requests,
the resize observer and message listener. A successful link retry clears its
old refusal message; a late link result cannot replace a newer tool result,
error or cancellation state.

Publishing this endpoint does not install a ChatGPT connector or make all browser agents render cards. A host must connect to this URL, support an outer protocol and complete the MCP Apps bridge. [ChatGPT's UI guidance](https://developers.openai.com/plugins/build/chatgpt-ui) supports the standard resource linkage and bridge; actual installation and rendering still require a connected-host check. Browser WebMCP remains document-scoped discovery; [WebMCP](webmcp.md) and [the shared A2UI subset](a2ui.md) describe those separate boundaries.

## Repeatable verification

After the actual deployed Git SHA is verified, `node --test tests/live-agent-http-discovery.cjs`
uses the real curl executable with no browser, cookies or credentials. It reads
the discovery document, executes its published tools/list and contact requests,
compares MCP and ordinary GET contact facts, and checks a live product's absolute
official URL plus its actual raster image when present. It never invokes an
action tool. The deployed-revision workflow runs this after the existing public
SDK check. HTTP errors, including 429, remain failures and are not retried or
converted to empty success. The separate browser probe records only safe
response headers when `/llms.txt` fails, so intermittent hosting responses can be
diagnosed without publishing cookies or page bodies.

`php tests/public-mcp.php` checks both wire profiles, strict modern framing, legacy initialization and notifications, metadata, errors, public scope, canonical output, exact-page cards and resource limits. `node tests/public-mcp-sdk.cjs <sdk-directory>` uses both the unmodified default official MCP client and an explicitly modern client against the real PHP dispatcher through an in-process fetch adapter; this checks SDK interoperability without claiming a network test.

`php tests/public-product-selection.php` checks ordered exact selection, missing
and hidden records, selected-only details, Unicode limits, module gates and both
HTTP MCP profiles using an in-request synthetic catalogue. `php
tests/public-agent-discovery.php` checks registry parity, module gates, explicit
scope declarations, canonical URLs, guide source, real routes and discovery
links. It executes every published MCP curl body against the real dispatcher.
The product-selection fixture checks official URL/path/image ordering and
foreign-image exclusion. Neither test contacts or changes a production
catalogue. `node --test tests/public-products-webmcp.test.cjs` checks browser
origin/path parity, Unicode selectors, malformed URL rejection and compatibility
with older relative-only responses.

`node --test tests/public-knowledge-api.test.cjs` executes the real `index.php`
entry point for `/ai-assistants`, `/llms.txt` and `/api/agent-tools`, preserving the original URI
after the hosting rewrite. It checks the text query variant, GET-only dispatch
and rejected suffix/private paths and discovery query values, so a registered but unreachable discovery
route cannot pass solely through direct controller tests.

With native PHP, `node tests/public-mcp-http.cjs <sdk-directory>` runs the actual `/api/mcp` controller through both official client profiles, checking methods, MIME/Accept, Origin, sizes, errors and cookie/session independence. `CHROMIUM_PATH=<chrome> node tests/public-mcp-app-browser.cjs <sdk-directory>` first discovers the actual resource and contact result through the default client, then drives the shipped widget with the official `AppBridge` through a sandboxed browser fixture at desktop and 375px. It checks readiness, product/contact cards, keyboard links, overflow, denied payloads and text fallback. Test dependencies are installed outside the application checkout: `@modelcontextprotocol/client@2.4.0`, `@modelcontextprotocol/ext-apps@2.0.4`, `playwright@1.62.1`.

`php tests/public-mcp-shopping.php` checks named cart contracts, explicit scopes,
both profiles, isolation/replay/conflicts, privacy, module gating and status
availability. `php tests/public-mcp-checkout.php` uses the actual checkout,
tax/coupon and verified-order services with an injected synthetic provider:
quote/confirmation, receipt recovery, no PII echo, one attempt, save-pending
recovery, saved order and expiry. `node tests/public-mcp-shopping-sdk.cjs
<sdk-directory>` exercises both unmodified default and explicitly modern clients
against those same services; this is SDK interoperability, not a network claim.

`node tests/public-mcp-shopping-http.cjs <sdk-directory>` uses the real
`index.php` HTTP route and disposable MySQL for cart changes. `node
tests/public-mcp-checkout-http.cjs <sdk-directory>` uses native PHP HTTP and the
real controller with constructor-injected service/provider fixtures, checks
aborted and discarded responses, and recovers one verified saved order.
Its router is restricted to loopback and CLI/CLI-server, with temporary
synthetic state; no production request can select a test service or provider.
Backend MySQL fixtures separately cover durable locks/concurrency. None of
these tests calls a live payment provider.

`CHROMIUM_PATH=<chrome> node tests/public-mcp-shopping-browser.cjs
<sdk-directory>` exercises the actual shared form with the official AppBridge,
minimum sandbox and restrictive host CSP at desktop/375px. It checks real
official fixture photos, pointer/Enter input, no typing/render-triggered calls,
revision conflicts, separate Buy now, inline quote/confirmation, payment-link
refusal, verified status, optional context privacy/denial and interruption.
Native Support tests also cover session receipt recovery and malformed external
results. Browser/HTTP tests require a native runtime; PHP-WASM syntax/pure
fixtures do not prove that those flows passed.

For read-only post-deployment acceptance, run `PUBLIC_MCP_URL=https://<site-origin>/api/mcp node tests/public-mcp-http.cjs <sdk-directory>`. This mode uses default and modern clients to discover and call the four public tools and read the declared resource. It checks actual published catalogue data and product-card metadata, without fixture data, cookies, owner tokens, private-tool probes, or mutations. It requires a published product, so an empty catalogue is reported as a failed product-card acceptance check. The local fixture-only negative requests and ordinary API session check are not sent to production.

The browser fixture proves the widget protocol and rendering path; it does not prove installation in a particular commercial chat host. For an independent manual host check, use the official [basic-host procedure](https://apps.extensions.modelcontextprotocol.io/api/documents/testing-mcp-apps.html), connect the endpoint, select a tool and inspect the rendered result. Its browser-side HTTP client must use a controlled same-origin development proxy; an unrelated localhost Origin is deliberately rejected by the production endpoint. Do not disable Origin validation. Production acceptance must also verify the deployed endpoint and record which real host/version was exercised.

An explicitly authorized post-deployment cart action check is separate from the
read-only gate: `PUBLIC_MCP_URL=https://<site-origin>/api/mcp node --test
tests/live-agent-cart.cjs`. It uses real curl with private bodies on stdin,
checks an actual official product/photo, creates one anonymous cart, sets one
item to quantity one, reads the revision/amount, then removes the item and
verifies empty in `finally`. It sends no delivery, coupon or payment data and
cannot call a checkout tool. The empty capability record expires normally;
cleanup failure remains a failed check. Do not run this mutation probe as an
unannounced read-only or generic production smoke test.
