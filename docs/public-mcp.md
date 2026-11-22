---
type: doc
title: Public MCP tools and chat cards
description: Stateless public Streamable HTTP MCP and the bounded MCP Apps presentation.
category: docs
---
# Public MCP tools and chat cards

The public endpoint is `POST <APP_URL>/api/mcp`. It reads the same published knowledge as the website and offers four tools: `search_public_products`, `search_public_articles`, `get_public_contact`, and `list_public_services`. Shop and Blog tools follow the current module settings. No browser login, administrator grant, customer order, private guide, conversation, provider call, contact submission or booking is part of this endpoint. The separate trusted local STDIO interface retains its own grant boundary.

## Transport contract

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

`php tests/public-mcp.php` checks both wire profiles, strict modern framing, legacy initialization and notifications, metadata, errors, public scope, canonical output, exact-page cards and resource limits. `node tests/public-mcp-sdk.cjs <sdk-directory>` uses both the unmodified default official MCP client and an explicitly modern client against the real PHP dispatcher through an in-process fetch adapter; this checks SDK interoperability without claiming a network test.

With native PHP, `node tests/public-mcp-http.cjs <sdk-directory>` runs the actual `/api/mcp` controller through both official client profiles, checking methods, MIME/Accept, Origin, sizes, errors and cookie/session independence. `CHROMIUM_PATH=<chrome> node tests/public-mcp-app-browser.cjs <sdk-directory>` first discovers the actual resource and contact result through the default client, then drives the shipped widget with the official `AppBridge` through a sandboxed browser fixture at desktop and 375px. It checks readiness, product/contact cards, keyboard links, overflow, denied payloads and text fallback. Test dependencies are installed outside the application checkout: `@modelcontextprotocol/client@2.4.0`, `@modelcontextprotocol/ext-apps@2.0.4`, `playwright@1.62.1`.

For read-only post-deployment acceptance, run `PUBLIC_MCP_URL=https://<site-origin>/api/mcp node tests/public-mcp-http.cjs <sdk-directory>`. This mode uses default and modern clients to discover and call the four public tools and read the declared resource. It checks actual published catalogue data and product-card metadata, without fixture data, cookies, owner tokens, private-tool probes, or mutations. It requires a published product, so an empty catalogue is reported as a failed product-card acceptance check. The local fixture-only negative requests and ordinary API session check are not sent to production.

The browser fixture proves the widget protocol and rendering path; it does not prove installation in a particular commercial chat host. For an independent manual host check, use the official [basic-host procedure](https://apps.extensions.modelcontextprotocol.io/api/documents/testing-mcp-apps.html), connect the endpoint, select a tool and inspect the rendered result. Its browser-side HTTP client must use a controlled same-origin development proxy; an unrelated localhost Origin is deliberately rejected by the production endpoint. Do not disable Origin validation. Production acceptance must also verify the deployed endpoint and record which real host/version was exercised.
