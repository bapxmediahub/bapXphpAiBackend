---
type: doc
title: Shop Page
description: Public product discovery, category and collection filters, pricing, stock and cart actions.
category: page
---
# Shop Page

`GET /shop` uses `PublicController@shop` to list visible products from the hosted catalogue. The discovery form accepts a search term, category or collection, and sort order. Each card links to its product page and shows the current price. Purchasable cards provide cart controls.

## Browser tool

On supported browsers, the shop page registers `list_shop_results` through `document.modelContext`. It reads the currently rendered results in display order. Each result includes the name, product URL, displayed price and whether the card offers a purchase control. An empty search returns an empty list.

The optional integer `limit` ranges from 1 to 20 and defaults to 20. The zero-based `offset` defaults to 0. The response includes `products`, `total` and `nextOffset`. Pass a numeric `nextOffset` to read the next page. A null value marks the end of the list. The tool makes no network requests and exposes only public card fields. Its output schema declares the four required product fields and optional visible `description` (280 Unicode characters) and same-origin raster `imagePath`, plus `total` and `nextOffset`; malformed cards are omitted while the pagination cursor advances. This display-only tool counts rendered cards, not all backend catalogue records. It cannot change filters or a cart; use `search_public_products` for the full catalogue or exact product details.

[WebMCP imperative API](https://webmachinelearning.github.io/webmcp/#dom-modelcontext-registertool)

## Local MCP client

An operator can configure a trusted local MCP client to launch the absolute path to `bapXphp` with the argument `mcp:stdio`. The client must support protocol revision `2026-07-28`. The command uses the site's standard PHP database configuration. Access to launch the process is the local trust boundary. This STDIO process has no HTTP listener. The separate [public MCP endpoint](../public-mcp.md) exposes published website information and isolated capability-bound shopping actions; it never accepts private STDIO grants. The new commerce actions belong to HTTP MCP and browser WebMCP, and do not silently extend the local STDIO tool catalogue.

To inspect the current public tool names, input and output schemas, and read-only annotations, run `./bapXphp mcp:tools`. The JSON catalogue matches guest `tools/list` on STDIO. MCP clients can also call `server/discover` and `tools/call`.

`search_public_products` accepts an optional `query` up to 120 characters, zero-based `offset`, and `limit` from 1 to 20 (default 20). It searches the public catalogue, applies the current offer price, and returns the product name, site-relative path, price in INR, availability, a plain-text description of up to 280 characters and an optional safe local raster `imagePath`. Pass `nextOffset` to continue. Published records and the shop module setting are checked on each catalogue read. Disabling the shop removes the tool from discovery and rejects subsequent calls from clients that previously discovered it.

For exact details, pass `slugs` with one to three unique raw slugs obtained by
decoding the last segment of returned product paths once. Keep the query empty,
offset zero and limit at least the selection count. The response preserves that
order and omits missing or unpublished matches. Only this selected mode may add
up to three `imagePaths`, four `highlights` and six `specifications` label/value
rows from the real product record. Ordinary searches remain compact. These
arguments and schemas are shared across WebMCP, public HTTP MCP, and local
STDIO. Support resolves separately named comparison products before selecting
them, avoiding the previous combined-name search failure. See the
[public assistant guide](/ai-assistants) for connection and human-review flows;
the companion `/llms.txt` is advisory discovery information.

API-only agents can read `/api/agent-tools` for the actual public MCP registry,
access scopes and executable curl requests. Product projections add an absolute
`productUrl`; approved photos add `imageUrl`, and selected galleries add matching
`imageUrls`. These use the configured official HTTPS origin, never the request
Host. Relative paths remain available for native cards. Missing photos have no
invented substitute; see [HTTP discovery](../public-mcp.md).

`public_policy` reads the same guest guidance used by customer Support. Pass one `key` from `tracking`, `returns_or_problems`, `shipping_cost` or `payment_methods`. The result contains a single `value` string. For example, `./bapXphp mcp:policy tracking` prints the tracking policy as JSON. Existing customers can read these policies when the shop is disabled. Neither command accepts actor credentials or a customer identifier.

For owner-approved reporting, open **Admin → Integrations → Manage local MCP clients**, name the client and authorize paid-sales access. Copy the credential from its single display into the trusted client's secret manager. Supply it as the child process environment variable `BAPX_MCP_CLIENT_TOKEN` when launching `bapXphp mcp:stdio`. Keep the credential out of arguments, tool inputs and logs.

A valid grant adds `paid_sales_summary` to private STDIO discovery. Its optional integer `days` accepts 7, 30 (default) or 90. The response gives inclusive dates in India time, paid order count, gross sales, verified refunds and net sales in INR. A credential expires after 30 days. The owner can see each client's last successful read and revoke it on the same page. The server checks the stored grant again at invocation, including for clients that already discovered the tool.

Run `node --test tests/catalogue-mcp.test.cjs` with PHP on the path to exercise the transport using an isolated database bridge fixture. For an independent client check, install `@modelcontextprotocol/client@2.4.0` in a scratch directory with npm and run `node tests/catalogue-mcp-interop.cjs /path/to/scratch`. That test starts the site in fixture mode and compares the official client’s schemas and policy answers with the CLI.

## Shared public knowledge capability

`PublicKnowledgeService` is the canonical PHP read-only projection for public products, published blog/customer-help Markdown, public business contact details and the four existing enquiry service options. `search_public_products` in native STDIO MCP uses its bounded catalogue projection. Enterprise article search and Support's contextual article references both use the same published article retriever, without granting public access to admin/operator guides or private JSON sessions.

**Two WebMCP product views have distinct semantics.** `list_shop_results` is a snapshot of rendered DOM cards in display order, with `total` counting only those cards. It declares `outputSchema`, validates title/price length and same-origin `/product/...` URLs, skips malformed cards and advances the cursor by inspected cards, not returned records. **`search_public_products`** is the canonical full-catalogue tool available on public pages while Shop is enabled; it calls `GET /api/knowledge/products?q=...&offset=0&limit=20` with credentials omitted. Pagination reaches all published records returned by `ProductService::visible()` and `discover()`, not merely cards in the DOM. Output fields: `name`, `path`, `priceInr`, `available`, `description` and optional `imagePath`; the common result includes `products`, `total` and `nextOffset`. Both input and output schemas come from the PHP service.

WebMCP registration runs after the locally vendored, pinned **official `@w3c/webmcp-polyfill@0.1.1`** script (`assets/vendor/webmcp/polyfill-0.1.1.js`) on public pages. The polyfill leaves native `document.modelContext` intact. The official pinned npm package is reinstalled in a temporary CI fixture, compared byte-for-byte by SHA-256 against the vendored browser bundle and exercised in Chromium. This tests the page API, not external agent discoverability or a ChatGPT MCP connection. If WebMCP APIs are denied by a browser's permissions policy, the browser can still render the ordinary storefront; no unrestricted HTTP MCP listener is enabled.

`GET /api/knowledge/articles?q=...&offset=0&limit=5` returns only `articles`, `total`, and `nextOffset`; every article includes a published title, site-relative `/blog/{slug}` path, category and ranking score. `limit` is 1–20, `offset` is nonnegative and the query is at most 120 Unicode characters. The Blog module must be enabled. Draft, future, retired, module-disabled and linked Markdown is filtered by `BlogService::all(false)`.

`search_public_articles` exposes that same PHP source over local STDIO MCP and over browser WebMCP on public pages. WebMCP input/output schemas are emitted from PHP into the public page; the JavaScript browser adapter fetches only the public metadata endpoint with credentials omitted and checks returned fields. A WebMCP-capable browser may discover the tool; ordinary chat access requires a suitable browser agent or a host connected to the separate [public Streamable HTTP MCP endpoint](../public-mcp.md). Compatible MCP Apps hosts can render its public cards; creating the endpoint does not install a connector in a chat session.

The shared layout also registers `get_public_contact` and `list_public_services` on the landing page, including when Shop and Blog are disabled. The same PHP facts populate the human contact page and both agent registries. See [public business tools](../webmcp.md) for schemas, module gates and separate native/polyfill verification.

Run `php tests/public-knowledge.php`, `node --test tests/public-knowledge-webmcp.test.cjs tests/public-products-webmcp.test.cjs tests/public-business-webmcp.test.cjs tests/public-knowledge-api.test.cjs tests/catalogue-mcp.test.cjs` and the official client interop checks before merging. A successful synthetic test is not proof of native browser discovery or a production deploy.

[MCP stdio transport](https://modelcontextprotocol.io/specification/2026-07-28/basic/transports/stdio) · [Protocol versioning](https://modelcontextprotocol.io/specification/2026-07-28/basic/versioning)


## Mobile header and floating cart restoration

The public mobile header retains the circular logo, visible **Sri Panchami Spiritual** brand text, the accessible top cart icon/count, and the Menu button. At phone widths the brand may wrap naturally without hiding the text or pushing the cart outside the viewport. The unrequested fixed bottom navigation has been removed; the ordinary header remains the primary navigation.

The separate `mobile-cart-tray` is intentionally **not** the removed bottom navigation. It remains hidden when the cart is empty and is shown on the home, shop, category and product pages after a successful add-to-cart response (`cart_count > 0`), linking to `/cart`. On narrow screens the floating tray and Support button sit side by side without overlapping. The standalone cart page retains its context-sensitive checkout shortcut, now without an artificial bottom-navigation gap.

Historical source: the bottom navigation and mobile brand hiding were introduced in commit `58be472ff828b42ecd8ccd308a7b4b22031c1bb6` (27 May 2026), and the brand-hiding rule was later duplicated in the critical inline CSS by `975c3aea3085f579c4aed62ce75f5fc472143896` (29 May 2026). This is a selective mobile-layout restoration, not a revert of newer shop, cart, Help, or WebMCP functionality.

Regression: `tests/mobile-cart-restore-browser.cjs` exercises actual PHP-rendered shop HTML and styles at 320, 375, 390, 744, 860 and 1280 pixels, with a synthetic AJAX add-to-cart response, a visible floating tray, count synchronization, no bottom nav, and unobstructed click target. No production order or payment is created.

### A2UI v1.0 product presentation

For agents that support A2UI, `present_public_knowledge` returns an explicit `createSurface` containing bounded published product, article, contact or service cards and safe website links. Support and Enterprise render their actor-scoped answer first, with relevant public cards as optional references and complete-text fallback. The public HTTP MCP tools attach cards from the exact result page for compatible MCP Apps hosts. The presentation surface remains read-only; the separate, fixed commerce controller adds explicit product/cart/checkout controls to the native Support pane and MCP App. A chat host still needs to connect. See [A2UI contract](../a2ui.md) and [public MCP](../public-mcp.md).

### Shopping and checkout inside chat

Public HTTP clients and compatible browser agents can create an isolated cart,
read it, set an absolute product quantity, review checkout, explicitly prepare
payment and read payment/order status. Native Support cards supply Add to cart,
Buy now and Checkout controls. The checkout pane shows delivery/contact fields,
coupon entry, server-calculated totals and explicit confirmation. It is separate
from saved chat markup; only bounded recovery handles survive a page reload in
session storage. Saved product cards are revalidated before a new shopping action.

The cart uses current catalogue data and revisions. An expired or changed quote
must be reviewed again. Confirmation creates one durable attempt; status recovery
does not start another payment. Payment authorization may use an explicit
merchant-domain step when the external host does not support an approved native
payment integration. Paid status and saved-order status are distinct, and status
recovery remains available when Shop is disabled. See [remote commerce](../remote-commerce.md)
for capabilities, configuration and recovery limits.
