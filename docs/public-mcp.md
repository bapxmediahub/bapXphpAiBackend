---
type: doc
title: Public MCP tools and chat cards
description: Stateless public Streamable HTTP MCP and the bounded MCP Apps presentation.
category: docs
---
# Public MCP tools and chat cards

The public endpoint is `POST <APP_URL>/api/mcp`. It reads the same published knowledge as the website and offers four tools: `search_public_products`, `search_public_articles`, `get_public_contact`, and `list_public_services`. Shop and Blog tools follow the current module settings. No browser login, administrator grant, customer order, private guide, conversation, provider call, contact submission or booking is part of this endpoint. The separate trusted local STDIO interface retains its own grant boundary.

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

The endpoint supports two explicit profiles: [MCP revision 2026-07-28](https://modelcontextprotocol.io/specification/2026-07-28/basic/versioning) and the narrow [2025-11-25 handshake profile](https://modelcontextprotocol.io/specification/2025-11-25/basic/lifecycle). Both expose the same four public tools and remain stateless: no MCP session identifier, PHP session, GET event stream, or DELETE session operation. Older protocol revisions are not advertised.

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

[MCP Apps](https://apps.extensions.modelcontextprotocol.io/api/documents/overview.html) is a host rendering extension. Tool definitions include `_meta.ui.resourceUri: "ui://bapx/public-knowledge.html"` in both profiles. A supporting client should advertise `extensions["io.modelcontextprotocol/ui"].mimeTypes: ["text/html;profile=mcp-app"]` in modern per-request capabilities or legacy initialization capabilities.

The public resource linkage and card metadata are also available to clients that omit that advertisement, including the default reference host. This is an intentional compatibility choice: the [Apps specification](https://github.com/modelcontextprotocol/ext-apps/blob/main/specification/2026-01-26/apps.mdx#clientserver-capability-negotiation) recommends checking capabilities, but its resource-discovery behavior allows standard text fallback when a host does not render the linked UI. The [OpenAI quickstart](https://developers.openai.com/plugins/build/app-quickstart) likewise registers public tool UI metadata without a capability-dependent variant. Metadata does not execute an app or grant a capability; the host controls resource loading, the separate UI handshake, and every UI action.

The host reads that resource using `resources/read`. It receives a self-contained HTML document with MIME `text/html;profile=mcp-app`, the shared public A2UI renderer and a small native-JavaScript host bridge. Resource metadata permits image/static loads only from the configured site origin and no external network connections or nested frames. No remote script or frontend build is required by the PHP application.

The iframe handshake uses the separate [MCP Apps 2026-01-26 protocol](https://apps.extensions.modelcontextprotocol.io/api/variables/app.LATEST_PROTOCOL_VERSION.html): `ui/initialize`, then `ui/notifications/initialized`. Only after that does the host deliver `ui/notifications/tool-result`. The same iframe protocol works with either supported outer MCP profile.

`structuredContent` retains the canonical public tool schema. `_meta["in.bapx/publicSurface"]` contains at most four server-authored A2UI cards built from that exact returned page, including its offset. `content` always includes a readable text representation and the source origin. Clients without Apps support use the public data and text and can ignore the optional presentation metadata. Invalid or unsupported surfaces retain the text fallback.

The widget accepts messages only from its parent sandbox. The renderer validates its public catalog, components, data bindings and navigation paths. The configured site origin resolves public links and images; model/tool arguments cannot change it. Human activation of a card link asks the host to open the URL through `ui/open-link`. There are no automatic tool calls, form submissions, purchases or private scopes.

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

For read-only post-deployment acceptance, run `PUBLIC_MCP_URL=https://<site-origin>/api/mcp node tests/public-mcp-http.cjs <sdk-directory>`. This mode uses default and modern clients to discover and call the four public tools and read the declared resource. It checks actual published catalogue data and product-card metadata, without fixture data, cookies, owner tokens, private-tool probes, or mutations. It requires a published product, so an empty catalogue is reported as a failed product-card acceptance check. The local fixture-only negative requests and ordinary API session check are not sent to production.

The browser fixture proves the widget protocol and rendering path; it does not prove installation in a particular commercial chat host. For an independent manual host check, use the official [basic-host procedure](https://apps.extensions.modelcontextprotocol.io/api/documents/testing-mcp-apps.html), connect the endpoint, select a tool and inspect the rendered result. Its browser-side HTTP client must use a controlled same-origin development proxy; an unrelated localhost Origin is deliberately rejected by the production endpoint. Do not disable Origin validation. Production acceptance must also verify the deployed endpoint and record which real host/version was exercised.
