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

The optional integer `limit` ranges from 1 to 20 and defaults to 20. The zero-based `offset` defaults to 0. The response includes `products`, `total` and `nextOffset`. Pass a numeric `nextOffset` to read the next page. A null value marks the end of the list. The tool makes no network requests and exposes only public card fields. Its output schema declares the four product fields plus `total` and `nextOffset`; malformed cards are omitted while the pagination cursor advances. This display-only tool counts rendered cards, not all backend catalogue records.

[WebMCP imperative API](https://webmachinelearning.github.io/webmcp/#dom-modelcontext-registertool)

## Local MCP client

An operator can configure a trusted local MCP client to launch the absolute path to `bapXphp` with the argument `mcp:stdio`. The client must support protocol revision `2026-07-28`. The command uses the site's standard PHP database configuration. Access to launch the process is the local trust boundary. The server has no HTTP listener.

To inspect the current public tool names, input and output schemas, and read-only annotations, run `./bapXphp mcp:tools`. The JSON catalogue matches guest `tools/list` on STDIO. MCP clients can also call `server/discover` and `tools/call`.

`search_public_products` accepts an optional `query` up to 120 characters, zero-based `offset`, and `limit` from 1 to 20 (default 20). It searches the public catalogue, applies the current offer price, and returns the product name, site-relative path, price in INR and availability. Pass `nextOffset` to continue. Published records and the shop module setting are checked on each catalogue read. Disabling the shop removes the tool from discovery and rejects subsequent calls from clients that previously discovered it.

`public_policy` reads the same guest guidance used by customer Support. Pass one `key` from `tracking`, `returns_or_problems`, `shipping_cost` or `payment_methods`. The result contains a single `value` string. For example, `./bapXphp mcp:policy tracking` prints the tracking policy as JSON. Existing customers can read these policies when the shop is disabled. Neither command accepts actor credentials or a customer identifier.

For owner-approved reporting, open **Admin → Integrations → Manage local MCP clients**, name the client and authorize paid-sales access. Copy the credential from its single display into the trusted client's secret manager. Supply it as the child process environment variable `BAPX_MCP_CLIENT_TOKEN` when launching `bapXphp mcp:stdio`. Keep the credential out of arguments, tool inputs and logs.

A valid grant adds `paid_sales_summary` to private STDIO discovery. Its optional integer `days` accepts 7, 30 (default) or 90. The response gives inclusive dates in India time, paid order count, gross sales, verified refunds and net sales in INR. A credential expires after 30 days. The owner can see each client's last successful read and revoke it on the same page. The server checks the stored grant again at invocation, including for clients that already discovered the tool.

Run `node --test tests/catalogue-mcp.test.cjs` with PHP on the path to exercise the transport using an isolated database bridge fixture. For an independent client check, install `@modelcontextprotocol/client@2.4.0` in a scratch directory with npm and run `node tests/catalogue-mcp-interop.cjs /path/to/scratch`. That test starts the site in fixture mode and compares the official client’s schemas and policy answers with the CLI.

## Shared public knowledge capability

`PublicKnowledgeService` is the canonical PHP read-only projection for public products and published blog/customer-help Markdown. `search_public_products` in native STDIO MCP uses its bounded catalogue projection. Enterprise article search and Support's contextual article references both use the same published article retriever, without granting public access to admin/operator guides or private JSON sessions.

`GET /api/knowledge/articles?q=...&offset=0&limit=5` returns only `articles`, `total`, and `nextOffset`; every article includes a published title, site-relative `/blog/{slug}` path, category and ranking score. `limit` is 1–20, `offset` is nonnegative and the query is at most 120 Unicode characters. The Blog module must be enabled. Draft, future, retired, module-disabled and linked Markdown is filtered by `BlogService::all(false)`.

`search_public_articles` exposes that same PHP source over local STDIO MCP and over browser WebMCP on public pages. WebMCP input/output schemas are emitted from PHP into the public page; the JavaScript browser adapter fetches only the public metadata endpoint with credentials omitted and checks returned fields. A WebMCP-capable browser may discover the tool; a plain ChatGPT conversation does **not** get direct invocation of site WebMCP without a suitable browser agent or separately authorized MCP connector. The application has no remote HTTP MCP transport.

Run `php tests/public-knowledge.php`, `node --test tests/public-knowledge-webmcp.test.cjs tests/catalogue-mcp.test.cjs` and the official client interop checks before merging. A successful synthetic test is not proof of native browser discovery or a production deploy.

[MCP stdio transport](https://modelcontextprotocol.io/specification/2026-07-28/basic/transports/stdio) · [Protocol versioning](https://modelcontextprotocol.io/specification/2026-07-28/basic/versioning)
