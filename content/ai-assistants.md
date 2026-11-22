# Use Sri Panchami with an AI assistant

An assistant can discover Sri Panchami Spiritual, show official products, manage an anonymous cart and review checkout through ordinary HTTP, curl or a compatible MCP client. A browser is optional for these tools. Your assistant or chat app must support the connection method you choose. Opening this page does not install a connector or grant access to an account.

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

The shopping view provides official photos, Add to cart, quantity controls, separate Buy now and an inline Checkout form when Shop is enabled. **Checkout opens delivery/contact fields, coupon review and the order total inside the view.** Fields are sent only when you choose Review checkout. You then explicitly confirm the quoted total before payment is prepared. The form never requests a card number, CVV, banking password or UPI PIN.

The host must allow **serverTools** for these controls. Its **openLinks** capability permits the separately clicked payment-only merchant page. Payment credentials are entered with the provider, outside this app; a generic embedded Razorpay frame or a ChatGPT payment sheet is not promised. Actual payment availability and test/live mode come from the server's review result. Preparing or opening a payment link does not mean that payment succeeded or an order was saved.

## Use an assistant in a browser

On a public page, a browser agent that supports WebMCP can discover the page's registered tools through **document.modelContext**. Products are available when Shop is enabled; articles are available when Blog is enabled. Contact details and enquiry options remain public.

Browser WebMCP is scoped to the open document. It does not connect this website to an unrelated chat app. Browsers without this feature can use the normal links and forms.

Check the actual page registry for commerce actions. A registered commerce action uses the same HTTP MCP tool and explicit anonymous capability; it does not modify a signed-in customer's browser cart or gain owner permissions. The native Support shopping pane can show the same cart and inline checkout results.

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

## Cart and checkout tools for HTTP agents

These are six separate **tools/call** operations. Read the exact current schemas from **tools/list** or **/api/agent-tools**. No browser cookie or hidden session selects a cart.

| Tool | Input and result |
|---|---|
| **create_cart** | No arguments. Creates one empty cart and returns its private **cartToken** once. Another create makes a different cart. |
| **get_cart** | **cartToken**. Reads that cart's current published items, quantities, estimated INR total and **revision**. |
| **set_cart_quantity** | **cartToken**, exact raw product **slug**, absolute **quantity** from 0 to 99 and **expectedRevision**. Zero removes; up to 20 distinct lines. On conflict read and review before another change. |
| **review_checkout** | **cartToken**, **expectedRevision**, optional complete buyer-provided **delivery** and **couponCode**. Returns delivery completeness/region, coupon presence, integer-paise item/discount/delivery/tax totals and a quote valid up to ten minutes. An empty coupon clears it. Full contact/address details and the coupon code are not echoed. |
| **prepare_checkout_payment** | **cartToken**, quoted **expectedRevision**, **quoteId**, **confirm:true** only after the buyer explicitly approves those exact details and amount. Records one durable attempt, locks the checkout, and returns a status-only **paymentToken** and a merchant payment-only URL when ready. Repeating that confirmation reuses the attempt. |
| **get_checkout_status** | **paymentToken**. Refreshes provider-verified payment and saved-order status. It can finish saving an already verified order, but cannot start payment or change delivery. A captured payment with **save_pending** still needs order reconciliation; only **saved** includes an order reference. |

Cart access lasts up to **24 hours**; payment status access lasts up to **seven days**. Retain those handles privately in the agent's structured tool context. Do not print them in customer replies, embed them in URLs or put them in analytics. The payment URL uses a different short-lived ticket. If a prepare response is lost, **get_cart** on the same cart can recover its status receipt; do not create a replacement payment to guess whether the first worked. **get_checkout_status** remains published when Shop is disabled, but cart-only recovery needs Shop to be available.

To create a cart from curl, save this body as **request.json** in a private working directory:

```json
{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"create_cart","arguments":{},"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}
```

```sh
umask 077
curl --fail-with-body --silent --show-error \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -H 'MCP-Protocol-Version: 2026-07-28' \
  -H 'Mcp-Method: tools/call' \
  -H 'Mcp-Name: create_cart' \
  --data-binary @request.json --output response.json \
  '{{MCP_URL}}'
```

Read **result.structuredContent.cartToken** from the private response and use it in the next request body; keep capabilities out of command arguments and shell history. For each later request, change both **Mcp-Name** and **params.name** to the selected tool and supply only its documented arguments. Keep the protocol metadata. Re-check **error** and **isError** after every call; a failed review is not permission to prepare payment. Use the actual returned revision and quote rather than constructing either value.

In a host that advertises structured **updateModelContext**, successful shopping controls may share the current cart, a separately labelled Buy now cart, and the last confirmed payment/status receipt with a later chat turn. They share no delivery/contact/coupon values. Host refusal leaves the controls usable but makes cross-turn continuity unavailable. A new MCP App does not restore another widget automatically. Native Support can retain only access receipts in the same browser tab's session storage, then refresh them from the server; delivery and coupon drafts are cleared on reload.

## Support and appointments

Open a returned contact link to choose an enquiry subject and send a message through the human form. Reading contact information does not submit a message. A product card alone does not grant a cart or authorize payment. Not every chat app or ChatGPT session renders the supplied interface or automatically connects to this site; plain HTTP clients use the same tools and structured results.

**New consultation booking is retired.** Public tools do not check appointment availability, reserve a time, book a consultation, start a call or send a message. Historical appointment records remain an owner responsibility and do not imply that new bookings are available. Use [Contact]({{SITE_URL}}/contact#contact-form) for product questions, order support, temple guidance or a general enquiry.

## Public and signed-in assistants have different access

This connection exposes published information plus explicitly supplied anonymous cart and checkout capabilities. It cannot read owner sales or GST reports, hidden products, arbitrary customer/account orders, support-ticket records, private instructions or conversations. A checkout receipt reveals only its own bounded verified payment/order status. Browser cookies, role claims and owner credentials do not add tools to this endpoint.

The site's own Support Agent can use approved information for its signed-in customer. The Enterprise Agent uses separate authenticated owner tools for store reporting and administration. Neither is made public by this connection.

## About this guide

The [JSON tool discovery]({{SITE_URL}}/api/agent-tools), this [web guide]({{SITE_URL}}/ai-assistants) and its [plain-text copy]({{SITE_URL}}/llms.txt) provide advisory discovery information. They are not an authorization policy, a promise that every assistant supports these tools, or an automatic installation mechanism. The application and connected host enforce their own permissions.

Continue to the [shop]({{SITE_URL}}/shop), [published guides]({{SITE_URL}}/blog), or [contact page]({{SITE_URL}}/contact).
