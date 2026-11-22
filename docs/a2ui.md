---
type: doc
title: A2UI structured answers and public references
description: Server-owned structured answers for Support and Enterprise, with published cards shared by WebMCP and external MCP Apps.
category: docs
---
# Structured agent answers and public references

The two existing PHP agents use a bounded subset of [A2UI 1.0](https://a2ui.org/). The server selects trusted components and supplies their data; the native chat or external app renders them. The model never supplies executable HTML, JavaScript, component definitions or new tool permissions.

## Answer presentation

Successful Support and Enterprise responses include an optional `a2ui` envelope alongside the complete original `reply` or `answer`. The chat renders the structured surface as its primary response. If the renderer is missing or rejects an unsupported surface, it shows the complete original response instead. A successful surface is not followed by a duplicate flat answer.

`AgentAnswerPresentationService` builds the native answer surface after the existing agent has completed its authorized work. It splits the accepted text into at most eight readable sections, preserves the full answer content, and attaches at most six approved navigation actions. An answer beyond the 12,000-character presentation budget falls back to its original text without changing the request outcome. For Enterprise it can also show up to two recorded fact groups, each with at most seven labelled values. Completed `sales_summary`, `gst_register_summary`, `support_enquiries` and `store_count` evidence supplies those values through the existing `AgentTraceService::safeTool()` projection. The group displays the recorded source, effective date range and observation time, or explicitly states when timing is unavailable. Failed lookups and missing values never become zero cards. This formatter performs no retrieval, tool execution or conversation writes.

| Surface | Data and actions | Host scope |
|---|---|---|
| Support answer | Existing authorized reply; public and customer navigation | `support` |
| Enterprise answer | Existing authorized reply and recorded aggregate facts; public links and fixed read-only report/help/ticket routes returned by tools | `admin` |
| Published references | Product, article, contact or service cards from the public knowledge source | `public` by default |

Owner source links remain inside the authenticated owner response. Private answers are never passed to `PublicA2uiService` as public card data, published as a public endpoint, or included in the external app. The Enterprise draft workflow remains the native editable proposal form with its existing owner review and save behavior. Fact labels are server-owned; values are never inferred from model prose or raw business records. Owner aggregate tools do not trigger a public catalogue lookup. Product/article tool references require returned public paths, which the public source then verifies.

## Relevant published cards

`PublicA2uiService::present(view, query, limit)` uses the shared `PublicKnowledgeService` for four views: `products`, `articles`, `contact` and `services`. Each result contains at most four cards. Products can include the published description and an approved same-origin raster image, alongside price, availability and the product link. Contact fields and service options come from the canonical public source; service links preserve the existing contact-form subject.

`fromResult(view, result, limit)` projects the exact result already returned by a public tool. External MCP Apps use this path so a paginated product/article result is not replaced by a second lookup of page one. The direct canonical contact object is accepted for `contact`; the other views use their ordinary paginated result object.

For native chats, `forQuestion(question, knowledge, answer)` uses the actual question or product slugs already grounded in the Support answer. Known product paths must match the published result exactly. Generic request words are removed from a named search; an unmatched question produces an empty public result instead of unrelated catalogue samples. Personal contact changes and questions about a customer's details or previously purchased services do not select public business cards. Service-specific questions retain their subject. Enterprise references require completed public tool evidence: product/article answers also need approved public paths, and contact/services need the matching sanitized public source and result count. Private or mixed tool activity cannot trigger public reference retrieval, and no owner data is projected into those cards.

## History, replay and fallback

The existing private JSON conversation continues to store its original question, answer, tool evidence and outcome. Presentation creates no second conversation schema or data store.

Authenticated Enterprise history and Support's idempotent replay format the saved answer text into the same native answer sections. Enterprise fact groups reuse stored tool evidence (or existing tool trace spans for older turns), including their original period, values, source link and observation time. They do not fetch the current catalogue or rebuild current prices as if those facts were part of a historical answer. The Support browser session can restore the already-rendered response without resending the request. Existing error handling, feedback, pending-action protection and draft review remain in their current flows.

The renderer validates the complete envelope before appending any surface. An invalid reference surface therefore cannot leave a half-rendered answer above the fallback. The complete text response remains available in the API even when presentation succeeds.

## External discovery and chat rendering

Public pages, including `/`, expose five read-only browser tools: `search_public_products`, `search_public_articles`, `get_public_contact`, `list_public_services` and `present_public_knowledge`. The shop also exposes the DOM-scoped `list_shop_results`. The presentation endpoint is `GET /api/knowledge/a2ui?view=products&limit=4`, with the other approved views available through the same validated contract.

WebMCP tool discovery depends on the browser host. The adapter supports native `document.modelContext` and the pinned official polyfill; real native-browser and polyfill checks are distinct acceptance paths. Registering WebMCP tools on a website does not itself register those tools in an ordinary external chat session.

The public `POST /api/mcp` adapter transports the same canonical product/article/contact/service data through the modern and default-client protocol profiles. Every successful public tool result retains text and structured data and can include up to four cards from its exact returned page. Compatible hosts read the separate MCP Apps resource and render those cards inside chat. See [the public MCP transport and acceptance contract](public-mcp.md). This transport is separate from native answers and browser WebMCP discovery. The public renderer accepts a trusted, host-supplied HTTPS `publicBaseUrl` so product links and images resolve to the configured website from an embedded app. That origin is never accepted from tool arguments or model-generated surface data. A trusted app host supplies `openUrl(url)` to route a validated public link through its navigation bridge; click and keyboard activation invoke it with the resolved absolute URL. Payloads cannot supply callbacks. Native website chats keep ordinary relative same-site anchors.

## Renderer trust boundary

`assets/a2ui-public-renderer.js` is an intentionally small renderer for the [A2UI Basic catalog](https://a2ui.org/reference/components/), not the complete official SDK. A public host accepts exactly one public surface. A native chat requires its matching answer surface first, followed by at most one public reference surface; legacy public-only envelopes retain the full text fallback. The renderer validates version, catalog ID, surface scope, component identities, allowed data bindings, text budgets, graph depth, cycles, card count and navigation before appending content.

The approved public components are `Column`, `Card`, `Text`, `Image` and `Button`, bounded to 40 components. Native answers additionally allow `Row` for paired fact labels/values and are bounded to 96 components. Images use the catalog's `url`, `description` and `fit: contain` fields. Image sources must be raster AVIF/GIF/JPEG/JPG/PNG/WebP paths under `/assets/images/` of at most 512 characters; remote URLs, data URLs and traversal are rejected. Product paths share the canonical 2,048-character cap. Both validate decoded path segments, preserving Unicode combining marks, encoded spaces and harmless internal dots while rejecting separators, controls, double encoding and dot-only segments. Text budgets count Unicode code points consistently with PHP. The only action is `openUrl` to fixed safe paths, rendered as a native anchor. No automatic navigation, event callback, mutation, arbitrary component, model HTML or script is accepted. Text is inserted through DOM text APIs. A public renderer rejects both native answer scopes and every owner route.

Native hosts must explicitly pass their scope: `BapxA2ui.render(container, surface, {scope:'support'})` or `{scope:'admin'}`. Public tools and external apps omit that option. Choosing a rendering scope does not grant backend permissions; existing route authorization, CSRF and actor ownership remain authoritative.

The shared styles use the site's maroon/gold tokens, one-column layouts, natural wrapping, contained images and at least 44px navigation targets. They preserve visible focus and avoid horizontal overflow at 375px.

## Deliberate limits

This implementation does not provide general streaming updates, two-way forms, arbitrary function calls, model-generated layouts, checkout actions or a complete A2UI SDK. Extensions must add specific server-owned view models and renderer capabilities with tests. Private business data must remain on the authenticated native route; public tool access is not a path into owner tools or conversation storage.

## Verification

`tests/public-a2ui.php` checks publication filtering, named-search relevance, exact-result pagination, contact/service projection with shop and blog disabled, image/path restrictions, scoped navigation, original-answer preservation, recorded fact labels/values/timing, history without retrieval and oversized-answer fallback. `tests/agent-presentation-scope.php` verifies that owner aggregate and ungrounded public-tool answers never construct a public presenter or retriever. `tests/fixtures/a2ui-responses.php` provides synthetic canonical data through the production presenters for browser checks.

`tests/a2ui-public-browser.cjs` covers the polyfill presentation tool, safe public and native rendering, product images, configured external origins, trusted keyboard link callbacks, Unicode/path parity, contact/service cards, recorded private facts, malicious text, unsafe links, cycles and atomic rejection. It exercises the actual Support interaction script at 1280px and 375px, including primary presentation, keyboard links, complete fallback and session restoration without resubmission.

`tests/agent-history-browser.cjs` checks Enterprise primary presentation, approved source actions, saved structured history, legacy/unsupported fallback, draft and history switching, and mobile layout. Native WebMCP CI and the post-deployment landing/shop probe separately verify browser tool discovery. A successful deployment probe demonstrates website/browser capability; it does not claim that an unrelated external chat client supports WebMCP or MCP Apps.
