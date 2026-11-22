# Use Sri Panchami with an AI assistant

An assistant can discover and read Sri Panchami Spiritual through ordinary HTTP, curl or a compatible MCP client. A browser is optional. Your assistant or chat app must support the connection method you choose. Opening this page does not install a connector or grant access to an account.

## Start with HTTP discovery

Read the current tool names, full input/output schemas, access scopes, transport URL and copyable curl requests:

```sh
curl --fail-with-body --silent --show-error '{{SITE_URL}}/api/agent-tools'
```

**/api/agent-tools is this site's custom discovery document.** It is not a standardized well-known location. Its tool definitions come from the same live registry as MCP **tools/list** and reflect the enabled Shop and Blog modules. Discovery supplies no account access or cart capability.

For simple public reads, use these ordinary GET endpoints without a login or browser cookie:

```sh
curl --fail-with-body --silent --show-error '{{SITE_URL}}/api/knowledge/contact'
curl --fail-with-body --silent --show-error '{{SITE_URL}}/api/knowledge/services?q=temple&limit=3'
curl --fail-with-body --silent --show-error '{{SITE_URL}}/api/knowledge/products?q=ring&limit=3'
curl --fail-with-body --silent --show-error '{{SITE_URL}}/api/knowledge/articles?q=rudraksha&limit=3'
```

HTTP search uses **q**, **offset** and **limit**; MCP uses **query**, **offset** and **limit**. An empty result means that the current published source has no match. Do not substitute another product merely to fill a card.

## Call MCP without a browser

The following request lists the real tools using the supported **2026-07-28** profile, which does not use an initialization handshake:

```sh
curl --fail-with-body --silent --show-error \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -H 'MCP-Protocol-Version: 2026-07-28' \
  -H 'Mcp-Method: tools/list' \
  --data-raw '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}' \
  '{{MCP_URL}}'
```

Then call a tool using its exact schema. This read returns the published contact information and submits nothing:

```sh
curl --fail-with-body --silent --show-error \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -H 'MCP-Protocol-Version: 2026-07-28' \
  -H 'Mcp-Method: tools/call' \
  -H 'Mcp-Name: get_public_contact' \
  --data-raw '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"get_public_contact","arguments":{},"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}' \
  '{{MCP_URL}}'
```

Inspect both the JSON-RPC **error** and the result's **isError** value. A tool can report an input or source failure inside an HTTP 200 response. Tool facts are in **result.structuredContent** and readable text is in **result.content**. Server-side clients do not need an **Origin** header; browser-origin requests must match the configured site origin.

## Connect a compatible chat app

Add this public MCP endpoint through your app's supported connection settings:

**{{MCP_URL}}**

The endpoint supports MCP **2026-07-28** and the **2025-11-25** initialization profile. A compatible client handles the protocol. It needs no site login, owner token or customer credentials. A host with MCP Apps support can display the supplied product and information cards; other clients receive readable text and structured data.

## Use an assistant in a browser

On a public page, a browser agent that supports WebMCP can discover the page's registered tools through **document.modelContext**. Products are available when Shop is enabled; articles are available when Blog is enabled. Contact details and enquiry options remain public.

Browser WebMCP is scoped to the open document. It does not connect this website to an unrelated chat app. Browsers without this feature can use the normal links and forms.

## Available public tools

- **search_public_products** searches published products by name, category or description. Results include the current INR price, availability, a short description, a product path and an optional real product image.
- **search_public_articles** finds published articles and customer help guides. Drafts, scheduled posts and private operator guides are excluded.
- **get_public_contact** reads the published business name, phone numbers, support email, opening hours and contact-page link.
- **list_public_services** lists Product Inquiry, Temple Guidance, Order Support and General Question. These are existing contact-form subjects, not appointment offers.

Browser agents also have **present_public_knowledge** for bounded public cards. On the shop page, **list_shop_results** reads only the products displayed under that document's current filters, with their displayed price and optional visible description/image. Use **search_public_products** to search the full published catalogue.

## Find or compare real products

Search using the customer's named product or subject, rather than a full conversational question. Use the returned names and paths; do not invent a product, price, specification or spiritual outcome. If nothing matches, explain that and ask for a clearer name or offer the contact page.

Search accepts **query**, **offset** and **limit**. Follow a non-null **nextOffset** for another page. Queries are limited to 120 Unicode characters and pages to 20 products.

For more detail or a comparison, take up to three product paths from a search result and decode each final slug once. Pass those exact raw values in **slugs**, with an empty query, offset zero and a limit covering the selection. Results keep the requested order and omit missing or unpublished items. The selected records can include up to three real images, four highlights and six labelled specifications. Ordinary search results stay compact.

Use only returned product facts when comparing. A missing specification is unknown, not a negative feature. Price and availability describe the current lookup and can change; check the linked product page before purchasing.

Product results include **productUrl**, and approved images include **imageUrl**. Exact selections can also include **imageUrls** in the same order as **imagePaths**. These are absolute URLs from the official configured site origin, ready for an HTTP client or a chat product card. Existing **path**, **imagePath** and **imagePaths** remain relative to **{{SITE_URL}}** for in-site rendering. Images come from the actual published product record and safe local raster paths under **/assets/images/**. If an image is absent, show no product image; do not substitute a search-engine, generated or unrelated image.

For exact selection over ordinary HTTP, repeat **slugs[]** with raw slugs decoded once from returned product paths. Use curl's **--get --data-urlencode** for each value, an empty **q**, **offset=0**, and a limit covering the selection. Never invent a slug from a product name.

## Purchase, support and appointments

Open the returned product page to buy through the site's cart and checkout. Open a returned contact link to choose an enquiry subject and send a message through the human form. Reading a tool result does not submit either action.

Check the live discovery document for supported actions and each tool's explicit access scope. The public-information tools described here are reads. A product card alone does not imply Add to cart, Buy now or Checkout support. A compatible connected MCP Apps host may render the site's supplied interface; other HTTP agents call available tools directly and receive structured results. Not every chat app or ChatGPT session supports that interface or automatically connects to this site.

**New consultation booking is retired.** Public tools do not check appointment availability, reserve a time, book a consultation, start a call or send a message. Historical appointment records remain an owner responsibility and do not imply that new bookings are available. Use [Contact]({{SITE_URL}}/contact#contact-form) for product questions, order support, temple guidance or a general enquiry.

## Public and signed-in assistants have different access

This connection returns public information only. It cannot read owner sales or GST reports, hidden products, customer orders, support-ticket records, private instructions or conversations. Browser cookies, role claims and owner credentials do not add tools to this endpoint.

The site's own Support Agent can use approved information for its signed-in customer. The Enterprise Agent uses separate authenticated owner tools for store reporting and administration. Neither is made public by this connection.

## About this guide

The [JSON tool discovery]({{SITE_URL}}/api/agent-tools), this [web guide]({{SITE_URL}}/ai-assistants) and its [plain-text copy]({{SITE_URL}}/llms.txt) provide advisory discovery information. They are not an authorization policy, a promise that every assistant supports these tools, or an automatic installation mechanism. The application and connected host enforce their own permissions.

Continue to the [shop]({{SITE_URL}}/shop), [published guides]({{SITE_URL}}/blog), or [contact page]({{SITE_URL}}/contact).
