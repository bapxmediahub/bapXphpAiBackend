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

The optional integer `limit` ranges from 1 to 20 and defaults to 20. The zero-based `offset` defaults to 0. The response includes `products`, `total` and `nextOffset`. Pass a numeric `nextOffset` to read the next page. A null value marks the end of the list. The tool makes no network requests and exposes only public card fields.

[WebMCP imperative API](https://webmachinelearning.github.io/webmcp/#dom-modelcontext-registertool)

## Local MCP client

An operator can configure a trusted local MCP client to launch the absolute path to `bapXphp` with the argument `mcp:stdio`. The client must support protocol revision `2026-07-28`. The command uses the site's standard PHP database configuration. Access to launch the process is the local trust boundary. The server has no HTTP listener.

Clients can call `server/discover`, `tools/list` and `tools/call`. The available tool, `search_public_products`, accepts an optional `query` up to 120 characters, zero-based `offset`, and `limit` from 1 to 20 (default 20). It searches the public catalogue, applies the current offer price, and returns the product name, site-relative path, price in INR and availability. Pass `nextOffset` to continue. Published records and the shop module setting are checked on each catalogue read. Disabling the shop removes the tool from discovery and rejects subsequent calls from clients that previously discovered it.

Run `node --test tests/catalogue-mcp.test.cjs` with PHP on the path to exercise the transport using an isolated database bridge fixture.

[MCP stdio transport](https://modelcontextprotocol.io/specification/2026-07-28/basic/transports/stdio) · [Protocol versioning](https://modelcontextprotocol.io/specification/2026-07-28/basic/versioning)
