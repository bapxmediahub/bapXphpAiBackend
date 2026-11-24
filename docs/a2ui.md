---
type: doc
title: A2UI structured answers and public references
description: Server-owned structured answers for Support and Enterprise, with published cards shared by WebMCP and external MCP Apps.
category: docs
---
# Structured agent answers and public references

The two existing PHP agents use a bounded subset of [A2UI 1.0](https://a2ui.org/). The server selects trusted components and supplies their data; the native chat or external app renders them. The model never supplies executable HTML, JavaScript, component definitions or new tool permissions.

### Native inline enquiry in Support

A request to **book a new consultation** returns a deterministic policy response: booking is retired and no appointment slot can be reserved. Support opens a native in-panel enquiry view, loads the actual same-origin \`/contact?subject=general\` form, and submits only when the visitor presses **Send message**. Form fields are not inserted into saved conversation HTML or passed to the model. It reuses the same public contact validation, CSRF, rate limiting, contact persistence and mail queue; the response is confirmed only when the existing contact page returns its real **Message received** state. A failed or uncertain response never claims delivery or silently retries. The normal contact URL remains the fallback when the form cannot load. This is not a new booking route or a new A2UI component catalog.

## Answer presentation

Successful Support and Enterprise responses include an optional `a2ui` envelope alongside the complete original `reply` or `answer`. The chat renders the structured surface as its primary response. If the renderer is missing or rejects an unsupported surface, it shows the complete original response instead. A successful surface is not followed by a duplicate flat answer.

`AgentAnswerPresentationService` builds the native answer surface after the existing agent has completed its authorized work. It splits the accepted text into at most eight readable sections, preserves the full answer content, and attaches at most six approved navigation actions. An answer beyond the 12,000-character presentation budget falls back to its original text without changing the request outcome. For Enterprise it can also show up to two recorded fact groups, each with at most seven labelled values and a primary metric. Completed `sales_summary`, `gst_register_summary`, `support_enquiries`, `store_count` and `commerce_report` evidence supplies those values through the existing `AgentTraceService::safeTool()` projection. The group displays the recorded source, effective date range and observation time, or explicitly states when timing is unavailable. Failed lookups and missing values never become zero cards. This formatter performs no retrieval, tool execution or conversation writes. The latest completed `commerce_report` can add one bounded report with at most six rows and five labelled values per row. Product and coupon performance, stock status and customer frequency each use fixed server labels. Customer rows contain only the approved frequency bands. Report cards retain the calculation basis and show how many rows are displayed out of the recorded total; empty customer bands say No identified customer groups for this period, while other empty report rows say No matching records. Paid orders without customer identity remain visible in the separate recorded summary. Stock is labelled a current status snapshot rather than a historical quantity report.

| Surface | Data and actions | Host scope |
|---|---|---|
| Support answer | Existing authorized reply; public and customer navigation | `support` |
| Enterprise answer | Existing authorized reply and recorded aggregate facts; public links and fixed read-only report/help/ticket routes returned by tools | `admin` |
| Published references | Product, article, contact or service cards from the public knowledge source | `public` by default |

Owner source links remain inside the authenticated owner response. Private answers are never passed to `PublicA2uiService` as public card data, published as a public endpoint, or included in the external app. The Enterprise draft workflow remains the native editable proposal form with its existing owner review and save behavior. Fact labels are server-owned; values are never inferred from model prose or raw business records. Owner aggregate tools do not trigger a public catalogue lookup. Product/article tool references require returned public paths, which the public source then verifies.

## Relevant published cards

`PublicA2uiService::present(view, query, limit, layout, slugs)` uses the shared `PublicKnowledgeService` for four public views: `products`, `articles`, `contact` and `services`. Product layouts accept `auto`, `grid`, `list`, `detail` or `compare`; other views require `auto`. The server writes a fixed presentation family into the data model. The renderer validates that family against the surface and record count, then applies its own structural styles. Payloads cannot name CSS classes or supply layout code.

| Family | Use and bounded content |
|---|---|
| Product grid | A collection of up to four matching products with distinct price and availability, an optional image and a short published description. |
| Product list | The same verified records in a compact layout when the container is wide enough. |
| Product detail | One selected product; up to three safe images, four published highlights and six specification pairs. Additional photos use a native disclosure with a descriptive summary. |
| Product comparison | Two or three exact selections; repeat the same specification labels and show Not listed for an absent value. No inferred rankings or invented product attributes. |
| Article list | Published titles, categories and read links in a compact reading list. |
| Contact panel | Published phone, email and hours rows in one panel with one enquiry-form link. If neither phone nor email is published, the panel still offers the contact form. Its card limit does not hide contact fields. |
| Service tiles | Existing public enquiry descriptions and subject-specific contact-form links. These are not booking actions. |

An exact product request uses up to three distinct raw `slugs`, with an empty query and a limit large enough for the requested selection. `compare` requires two or three selections. Missing or hidden products never get substitutes; fewer than two returned products use a detail/empty view instead of claiming a completed comparison. Ordinary search remains compact, while exact selected-product results can include the additional published images and structured fields. Price and availability are typed presentation values, not parsed from prose or a combined display string.

`fromResult(view, result, limit, layout, requestedCount)` projects the exact public result already returned by a tool. External MCP Apps use this path so a paginated result is not replaced by a second lookup of page one. The MCP product adapter selects detail/comparison from its explicit slug selection and passes the requested selection count; plain search uses automatic layout. When only two of three requested products are published, the card retains those two real matches and explicitly says Showing 2 of 3 selected products. Missing selections are never silently replaced or hidden by the comparison heading. The direct canonical contact object is accepted for `contact`; other views use their ordinary paginated result object.

For native chats, `forQuestion(question, knowledge, answer)` uses the actual question or product slugs already grounded in the Support answer. Known product paths must match the published result exactly. Named comparisons use `PublicKnowledgeService::comparisonSlugs()`, which shares the exact published-name/slug resolver with native Support context. Each returned slug is then read through the canonical selected-product projection; the words in `A versus B` are never treated as one all-token catalogue search. Generic request words are removed from a named search; an unmatched question produces an empty public result instead of unrelated catalogue samples. Personal contact changes and questions about a customer's details or previously purchased services do not select public business cards. Service-specific questions retain their subject. Enterprise references require completed public tool evidence: product/article answers also need approved public paths, and contact/services need the matching sanitized public source and result count. Private or mixed tool activity cannot trigger public reference retrieval, and no owner data is projected into those cards.

## History, replay and fallback

The existing private JSON conversation continues to store its original question, answer, tool evidence and outcome. Presentation creates no second conversation schema or data store.

Authenticated Enterprise history and Support's idempotent replay format the saved answer text into the same native answer sections. Enterprise fact groups reuse stored tool evidence (or existing tool trace spans for older turns), including their original period, values, source link and observation time. They do not fetch the current catalogue or rebuild current prices as if those facts were part of a historical answer. The Support browser session can restore the already-rendered response without resending the request. Product photo controls use native `details`/`summary`, so they remain keyboard-operable when restored HTML has no JavaScript listeners. Report-only turns remain eligible for evidence restoration even when their separate scalar `facts` object is empty. Existing error handling, feedback, pending-action protection and draft review remain in their current flows.

The renderer validates the complete envelope before appending any surface. An invalid reference surface therefore cannot leave a half-rendered answer above the fallback. The complete text response remains available in the API even when presentation succeeds.

## External discovery and chat rendering

Public pages, including `/`, expose five read-only browser tools: `search_public_products`, `search_public_articles`, `get_public_contact`, `list_public_services` and `present_public_knowledge`. The shop also exposes the DOM-scoped `list_shop_results`. Six separate named commerce tools use the shared HTTP contract for capability-bound cart and checkout actions; they do not turn presentation components into arbitrary executable instructions. The presentation endpoint is `GET /api/knowledge/a2ui?view=products&limit=4`, with the other approved views available through the same validated contract. For an explicit comparison, use `view=products`, `layout=compare` and a JSON-encoded `slugs` array containing two or three raw published slugs. The browser adapter forwards the same arguments and enforces Unicode code-point query limits.

WebMCP tool discovery depends on the browser host. The adapter supports native `document.modelContext` and the pinned official polyfill; real native-browser and polyfill checks are distinct acceptance paths. Registering WebMCP tools on a website does not itself register those tools in an ordinary external chat session.

The public `POST /api/mcp` adapter transports the same canonical product/article/contact/service data through the modern and default-client protocol profiles. Every successful public tool result retains text and structured data and can include up to four cards from its exact returned page. Compatible hosts read the separate MCP Apps resource and render those cards inside chat. See [the public MCP transport and acceptance contract](public-mcp.md). This transport is separate from native answers and browser WebMCP discovery. The public renderer accepts a trusted, host-supplied HTTPS `publicBaseUrl` so product links and images resolve to the configured website from an embedded app. That origin is never accepted from tool arguments or model-generated surface data. A trusted app host supplies `openUrl(url)` to route a validated public link through its navigation bridge; click and keyboard activation invoke it with the resolved absolute URL. Payloads cannot supply callbacks. Native website chats keep ordinary relative same-site anchors.

## Renderer trust boundary

`assets/a2ui-public-renderer.js` is an intentionally small renderer for the [A2UI Basic catalog](https://a2ui.org/reference/components/), not the complete official SDK. A public host accepts exactly one public surface. A native chat requires its matching answer surface first, followed by at most one public reference surface; legacy public-only envelopes retain the full text fallback. The renderer validates version, catalog ID, surface scope, component identities, allowed data bindings, text budgets, graph depth, cycles, card count and navigation before appending content.

The approved components are `Column`, `Card`, `Text`, `Image`, `Button` and constrained `Row` pairs. Public `Row` is permitted only for matching contact fields or product specification labels/values. Public surfaces are bounded to 96 component definitions and native answers to 224; the renderer also caps rendered nodes at 160/320 respectively and graph depth at ten. The larger native budget accommodates one six-row recorded report while retaining the complete answer and existing fact groups. Data arrays retain their independent bounds, and arbitrary presentation names or private report data on a public surface are rejected. Images use the catalog's `url`, `description` and `fit: contain` fields. Image sources must be raster AVIF/GIF/JPEG/JPG/PNG/WebP paths under `/assets/images/` of at most 512 characters; remote URLs, data URLs and traversal are rejected. Product paths share the canonical 2,048-character cap. Both validate decoded path segments, preserving Unicode combining marks, encoded spaces and harmless internal dots while rejecting separators, controls, double encoding and dot-only segments. Text budgets count Unicode code points consistently with PHP. The only serialized action is `openUrl` to fixed safe paths, rendered as a native anchor. A recognized product-detail photo column may receive a renderer-owned native disclosure; this changes only local visibility and invokes no tool or network mutation. No automatic navigation, event callback, mutation, arbitrary component, model HTML or script is accepted. Text is inserted through DOM text APIs. A public renderer rejects both native answer scopes and every owner route.

Native hosts must explicitly pass their scope: `BapxA2ui.render(container, surface, {scope:'support'})` or `{scope:'admin'}`. Public tools and external apps omit that option. Choosing a rendering scope does not grant backend permissions; existing route authorization, CSRF and actor ownership remain authoritative.

The shared styles use the site's maroon/gold tokens, natural wrapping, contained images and at least 44px navigation/disclosure targets. Container queries choose columns based on the actual available card width, so narrow Support drawers and embedded apps stay readable even in a desktop window. Native flex chat bubbles containing a surface receive available width within their existing maximum: inline-size containment cannot supply a shrink-wrapped parent's intrinsic width, and saved replies have no request-status text to size that parent. At 375px, cards and comparison fields stack without horizontal page scrolling. Native answer paragraphs remain readable sections instead of repeated equal-weight boxes; recorded primary metrics have stronger hierarchy than supporting facts.

## Deliberate limits

The A2UI renderer does not provide general streaming updates, two-way forms,
arbitrary function calls, model-generated layouts or a complete A2UI SDK.
Checkout uses a separate fixed controller, `assets/public-mcp-shopping.js`,
shared by the public MCP App and native Support commerce pane. It renders only
the server's cart, review and status contracts; its explicit actions call named
tools after validation. A model cannot supply executable form markup or select
an arbitrary tool. Delivery/contact values and capabilities are excluded from
ordinary conversation persistence. Browser WebMCP waits for accepted native
updates before returning; unavailable native UI still permits a structured result
for the external client. See [remote commerce](remote-commerce.md)
for the action and payment boundary. Private business data remains on the
authenticated native route; public tools cannot access owner tools or conversation storage.

## Verification

`tests/public-a2ui.php` checks publication filtering, named-search relevance, exact-result pagination, contact/service projection with shop and blog disabled, image/path restrictions, scoped navigation, original-answer preservation, typed layout families, exact-result comparison fields, contact grouping, recorded fact/report labels, row bounds, timing, report-only history without retrieval and oversized-answer fallback. `tests/agent-presentation-scope.php` verifies that owner aggregate and ungrounded public-tool answers never construct a public presenter or retriever. `tests/fixtures/a2ui-responses.php` provides synthetic canonical data through the production presenters for browser checks.

`tests/a2ui-public-browser.cjs` covers the polyfill presentation tool, safe public and native rendering, product images, configured external origins, trusted keyboard link callbacks, Unicode/path parity, contact/service cards, recorded private facts, malicious text, unsafe links, cycles and atomic rejection. It exercises the actual Support interaction script at 1280px and 375px, including primary presentation, keyboard links and photo disclosures, complete fallback and session restoration without resubmission. Variant fixtures also check wide versus narrow containers within a desktop viewport and reject unknown families, mismatched comparison fields and private reports on public surfaces.

`tests/agent-history-browser.cjs` checks Enterprise primary presentation, approved source actions, visibly restored structured history and exact recorded report rows, readable live/replay container width, legacy/unsupported fallback, draft and history switching, and mobile layout. Native WebMCP CI and the post-deployment landing/shop probe separately verify browser tool discovery. A successful deployment probe demonstrates website/browser capability; it does not claim that an unrelated external chat client supports WebMCP or MCP Apps.


## Native chat canvas layout and browser QA (November 2026)

The existing \`BapxA2ui\` renderer continues to own the public product cards and
server-grounded Enterprise answer/report components. In **native Support and
Enterprise chat only**, a multi-product list/grid renders as a horizontally
scroll-snapping rail *inside the same answer canvas*, not as a second store or
frontend. Cards preserve the exact server-provided published product URL,
official image and current price. The rail has a keyboard-focusable named
region for arrow-key scrolling; public website pages and external MCP App
hosts retain their own layouts, and unconnected web-search hosts do not gain
write permissions.

The native Support panel reserves most of its bounded viewport for the
conversation and gives its composer a compact, accessible single-row message +
Send layout. The Enterprise bot answer uses the available safe width for
evidence and report cards without overflowing 375px mobile. None of these
presentation-only changes call tools, create a cart, submit a contact message,
or change the RBAC/CSRF/model configuration.

Repeatable **headless Chromium Playwright** QA is a developer/CI activity:

\`\`\`sh
# On a machine with PHP CLI, Node.js, Playwright and Chromium installed:
bash cli/agent-browser-qa.sh
\`\`\`

The self-contained runner uses actual PHP Support and Enterprise templates,
synthetic product/report inputs, the A2UI renderer and isolated request
interception. It tests 375px and 1280px canvas/viewport bounds, readable
composer/answers, focusable swipeable products and no page JS errors. The
native GitHub Actions QA job installs pinned Playwright on the runner and
publishes screenshot artifacts. It **does not** ship a second browser engine
to Hostinger, log in to a production owner, contact real provider models,
or prove the production deployment SHA. Live production acceptance must
be recorded separately with the deployed SHA and permitted credentials.
