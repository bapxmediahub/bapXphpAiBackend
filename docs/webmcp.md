---
type: doc
title: Public WebMCP business tools
description: Landing-page discovery, shared public contracts and separate native CDP, JavaScript and polyfill verification.
category: docs
---
# Public business tools on the landing page

The public PHP layout registers a small set of imperative tools with
`document.modelContext`. They use the same `PublicKnowledgeService` projections
as the Support and Enterprise agents. A visitor does not need to open the shop
before asking an external browser agent about the business.

| Tool | Published result | Availability |
|---|---|---|
| `search_public_products` | Name, current INR price, availability, short description, optional local raster image and product path | Every public page when Shop is enabled |
| `search_public_articles` | Published article/help title, category, relevance and article path | Every public page when Blog is enabled |
| `get_public_contact` | Business name, public phones, support email, hours and contact page | Every public page |
| `list_public_services` | The four existing enquiry options, descriptions and contact-form subject links | Every public page |
| `present_public_knowledge` | A bounded declarative A2UI surface for a supported public view | Public layout; see the [presentation contract](a2ui.md) |
| `list_shop_results` | A paged snapshot of the products actually displayed under the current shop filters | Only the shop document containing `.shop-results` |
| `create_cart`, `get_cart`, `set_cart_quantity` | An isolated anonymous cart, current product facts and revision-bound absolute quantities | Every public page when Shop is enabled |
| `review_checkout` | Inline delivery completeness, coupon result and an expiring server-calculated quote | Every public page when Shop is enabled |
| `prepare_checkout_payment` | An explicitly confirmed durable payment attempt and optional merchant payment handoff | Every public page when Shop is enabled; requires configured payment credentials and webhook secret |
| `get_checkout_status` | Verified payment and saved-order status for one capability | Every public page, including when Shop is disabled |

Contact and service facts are selected explicitly. They are not a serialized
settings row. The four offerings are Product Inquiry, Temple Guidance, Order
Support and General Question; they do not claim that retired consultation
booking is available. The human contact page and tools use the same values,
subject allowlist and descriptions. A service link prefills an enquiry subject;
it does not submit the form. Contact and service tools form one registration
group. A refused registration aborts the shared lifetime, removes any installed
sibling and prevents execution until both registrations complete. This follows
[Chrome's capability-group guidance](https://developer.chrome.com/docs/ai/webmcp/best-practices);
it does not remove independently registered product, article or commerce tools.

The JSON GET endpoints are `/api/knowledge/products`, `/articles`, `/contact`,
`/services` and `/a2ui`, all under `/api/knowledge`. The scripts use
`credentials: 'omit'`. Search queries are at most 120 characters and result
pages at most 20 rows; A2UI cards are separately capped. Product descriptions
are plain text capped at 280 characters. Optional images are normalized to
same-origin raster paths below `/assets/images/`.
Product paths are capped at 2,048 encoded characters; Tamil and other Unicode
slugs retain valid combining marks. Invalid destinations are removed before
pagination. Published article visibility,
future-date filtering and module switches remain enforced on the server.
Unknown arguments, private fields and unapproved response paths are rejected.


## Declarative reuse of existing forms

The shop's real `GET /shop` keyword/category/sort form advertises `filter_visible_shop` with `toolautosubmit` because it only navigates to filtered public results. This does not duplicate the canonical `search_public_products` read, which queries the entire published catalogue.

The existing `POST /contact` form advertises `prepare_contact_enquiry` with field descriptions and **no** `toolautosubmit`: an AI-enabled browser may help fill fields, but the visitor must explicitly submit the message. A contact enquiry never books a retired consultation. Support may reuse this same-origin form inside its existing panel, with the original server's CSRF, bounded validation, atomic contact admission limits and on-page confirmation. Enquiries enter Contacts and Email Inbox; automatic contact email is held. WebMCP registration on these elements does not install remote chat tools or permit unattended writes.

## Finding the public tools

Every public page links to the human-readable `/ai-assistants` guide in its
footer and its head metadata. `/llms.txt` serves the same fixed, reviewed
`content/ai-assistants.md` source as plain text, with the configured HTTPS origin
and real `/api/mcp` URL. These are advisory discovery documents. They do not
install a connector, grant access, or establish a universal agent-discovery
standard. The guide links both representations and distinguishes public reads,
signed-in Support, and the owner-authorized Enterprise agent. New consultation
booking is retired; enquiries use the existing contact form.

The custom `/api/agent-tools` JSON document supplies the same live public MCP
registry, module gates, access metadata and copyable curl requests. It is not a
standard well-known manifest. API-only clients can use the listed HTTP endpoints
or MCP transport without a browser. Product results add official absolute
`productUrl`, optional `imageUrl`, and selected `imageUrls`, while preserving
relative paths for in-site rendering. The browser adapter validates absolute
URLs against the canonical origin emitted by the page. See [HTTP discovery and
verification](public-mcp.md).

Both discovery URLs are generated PHP responses, not static copies. They must
pass the public route gate in `index.php` as well as the registered router after
the hosting rewrite. Registering a controller route alone does not make it
reachable through that entry point.

## Search followed by exact product selection

The existing `search_public_products` tool accepts an optional `slugs` array of
up to three unique raw product slugs. Obtain them by decoding the final segment
of a returned product path once. Selection requires an empty query, offset zero,
and a limit at least as large as the selection. PHP, browser WebMCP, local STDIO
and public HTTP MCP share that contract. JSON GET clients use repeated
`slugs[]=...` values. Results preserve requested order and omit hidden, missing
or invalid products; they never substitute the first catalogue results.

Ordinary search stays compact. Selected products may additionally contain up to
three safe `imagePaths`, four plain-text `highlights` (160 characters each), and
six `specifications` rows (`label` up to 80, `value` up to 240 characters). These
fields come only from published ProductService records. Empty details are
omitted. Description and query limits count Unicode characters consistently;
safe Tamil combining marks remain valid in slugs and images.

Support's named comparison lookup resolves two or three separately named
products against the visible catalogue, accepting an exact or unambiguous name
for each. It does not run all names as one all-token search or invent missing
matches. Explicit external selections use the raw slug contract above.

The shop-only `list_shop_results` remains a snapshot of the current document's
cards. It can add the card's visible description and safe local image, but does
not search unseen records, infer hidden product details, or change a cart.

## Registration and client capability

The layout loads the pinned official W3C polyfill before the adapters. The
polyfill does not replace a native `document.modelContext`. Each adapter detects
the API, uses `await registerTool(...)` to accept either a synchronous or an
asynchronous implementation, catches registration refusal, and unregisters with
an AbortSignal on a non-persisted `pagehide`. A browser without the API keeps
normal links and forms. No script writes a fake native registry or broadly
exposes tools to unrelated origins.

WebMCP is a browser-tab interface. Its existence on a page does not install a
tool into an ordinary external chat, and A2UI JSON does not by itself make that
chat render cards. A compatible external host needs discovery and rendering
support; MCP Apps uses a separate remote MCP transport and sandboxed UI resource.
The site's [public MCP endpoint](public-mcp.md) at `/api/mcp` supplies that
transport for compatible chat hosts, with the same four published data tools
and six named commerce tools when Shop is enabled. A host still needs to connect explicitly.
Public tools must never inherit owner MCP credentials, private Help, customer
records, agent instructions or conversation JSON.

## Shared commerce actions and inline checkout

`assets/public-commerce-webmcp.js` reads the actual public MCP definitions emitted
by PHP and calls `/api/mcp` without cookies or authorization headers. A cart is
authorized by its own opaque capability; payment status uses a separate
capability. These handles must not be copied into ordinary chat text, analytics
or saved conversation markup. Product images and links come from the published
catalogue under the configured HTTPS origin.

The fixed six-tool group registers atomically. A partial registration failure
aborts the group; one existing tool cannot leave a misleading partial shopping
surface. Every execute call forwards its caller AbortSignal into fetch and native
UI synchronization. The request timeout ends after a valid response arrives,
while caller and page-lifecycle cancellation continue through UI synchronization.
The adapter validates canonical inputs and outputs and never automatically retries
a possibly applied write. Read/write and consequence annotations are guidance;
the server still enforces capabilities, revisions and explicit quote confirmation.

When native Support accepts a canonical result, execution waits for its separate
commerce pane to update visibly. If the native pane is unavailable or rejects
the result, the validated structured result remains available to the external
client; that fallback is not proof of a visible native update. The shared renderer provides product
actions, cart quantities, delivery/contact fields, coupon entry, final totals,
confirmation and payment/order status. The same module runs inside the remote
MCP App. Delivery values remain in the active form and private checkout record,
outside saved conversation markup. A minimal MCP Apps sandbox needs no native
form-submission permission: explicit buttons and Enter handling validate fields.

`review_checkout` never creates a payment. `prepare_checkout_payment` requires
`confirm: true`, the exact current revision and an unexpired quote ID. The server
saves the attempt and locks the cart before provider I/O. After a lost response,
`get_cart` returns its existing status-recovery receipt; `get_checkout_status`
reconciles that attempt without creating another. An uncertain provider response
stays uncertain until verified evidence is available. Captured payment and saved
order are reported separately, allowing recovery from an interrupted order save.

Payment authorization follows the host's supported payment integration. This
implementation keeps checkout review and status in chat and offers an explicit
merchant-domain payment step; it does not embed a third-party payment iframe in
an unsupported host or claim an approved host-native payment integration. No raw
card, CVV or payment PIN enters the agent tools. Merchant webhook configuration
and bounded reconciliation are described in [remote commerce](remote-commerce.md).

## Comparison with existing implementations

The implementation follows three concrete patterns from Google's public demos:

- [Flight search](https://googlechromelabs.github.io/webmcp-tools/demos/react-flightsearch/)
  separates catalogue search from listing the displayed results. Here,
  `search_public_products` and `list_shop_results` preserve that distinction.
- [WebMCPza Maker](https://googlechromelabs.github.io/webmcp-tools/demos/pizza-maker/)
  registers focused imperative capabilities when its page loads. Here, the
  shared public layout supplies business discovery immediately on `/`.
- [Le Petit Bistro](https://googlechromelabs.github.io/webmcp-tools/demos/french-bistro/)
  associates tools with existing human flows and validation. Here, services
  link to the real contact subject options; there is no invented booking flow.

The [current imperative API](https://developer.chrome.com/docs/ai/webmcp/imperative-api)
and [WebMCP draft](https://webmachinelearning.github.io/webmcp/) are the API
references. This PHP/native-JavaScript site does not need a frontend framework
or a general-purpose agent tool for each page element.

[BigCommerce's WebMCP guide](https://docs.bigcommerce.com/developer/api-reference/mcp/webmcp)
uses shared server/browser storefront tasks with search followed by exact
product detail. This site follows that useful sequence through one bounded
product tool. Its commerce actions use the same server-owned cart and checkout
contract in the browser adapter, HTTP tools and chat renderer.
[Chrome's tool-design guidance](https://developer.chrome.com/docs/ai/webmcp/build-tools)
also supports keeping capabilities scoped to the current role, page state and
task. Public catalogue reads and authorized commerce administration therefore
remain separate.

## Verification boundaries

`tests/webmcp-official-browser.cjs` runs in two separate Chromium lanes. The
official-polyfill lane verifies the vendored 0.1.1 bundle against npm and tests
discovery, execution, pagination, invalid input, lifecycle and unsupported
browsers. The native lane loads no polyfill, runs headed Chrome with WebMCP
enabled, and also discovers and calls the contact tool from an isolated
execution world. Failure to provide the native API fails that lane; it is not
reported as a successful polyfill test. Both lanes test `/` without shop cards,
the shop-only extra tool, and contact/services with commerce and blog absent.

The native lane additionally uses the browser's experimental
[WebMCP CDP domain](https://chromedevtools.github.io/devtools-protocol/tot/WebMCP/)
through `tests/fixtures/webmcp-cdp.cjs`. It installs listeners before
`WebMCP.enable`, checks the exact main-frame tool names on the landing and shop
pages, and invokes only `get_public_contact` with empty arguments. The command's
`invocationId` must match both the invocation and completion events, including
the observed frame/tool identity. The returned public contact must equal the
existing native JavaScript result. Discovery of a commerce tool does not execute
that tool. The native CDP shop registry includes exactly one additional
declarative HTML form tool, `filter_visible_shop`, alongside the imperative
registry. Chrome synthesizes its schema from the existing GET form (see
[Chrome Agentic Forms](https://developer.chrome.com/docs/modern-web-guidance/guides/webmcp/agentic-forms)).
Native JavaScript discovery waits for the same full registry because the form
can register asynchronously after the imperative tools. The pinned imperative
polyfill is checked separately; the form is not a new backend capability. Unknown CDP
names still fail the exact page allowlist.

Each CDP result logs `Browser.getVersion` product, protocol version and revision,
plus the observed descriptor profile and tool names. The helper uses the opaque
`toolId` when Chrome reports it. Older descriptors without IDs use the documented
`frameId`/`toolName` shape; mixed or malformed descriptors fail. These two shapes
are defined in the
[current Chromium protocol](https://github.com/ChromeDevTools/devtools-protocol/blob/master/pdl/domains/WebMCP.pdl)
and its
[earlier name-based revision](https://chromium.googlesource.com/chromium/src/+/7b2b2f1d8406dafea7bd199ebf6844c4f593753d/third_party/blink/public/devtools_protocol/domains/WebMCP.pdl).
The diagnostic has an eight-second deadline per page, invokes once, and fails on
an unsupported domain, missing tools, mismatched events, a changed registration,
or an unsuccessful completion. It does not retry with another protocol shape or
substitute page JavaScript for a failed CDP invocation. The focused
`node --test tests/webmcp-cdp.test.cjs` fixtures check event correlation, timeout
and schema rejection; only the real native Chrome lane establishes CDP acceptance.

This diagnostic attaches only to the Chrome instance launched by the repository's
own CI fixtures. It does not attach to a managed cloud browser, change page
permissions or relax an origin policy. A pass establishes the controlled Chrome
version's CDP behavior. An empty tool list in another browser/agent bridge still
requires that host's supported capability and version evidence; a native CDP pass
does not prove that the host consumes a JavaScript polyfill registry.

`tests/public-commerce-webmcp-browser.cjs` adds both real-polyfill and native
isolated-world commerce lanes at 1280px and 375px. They execute all six canonical
tools through the real PHP dispatcher with an isolated synthetic provider,
check visible native chat synchronization, and distinguish captured payment from
saved-order status. They assert that review does not create payment, that one
explicit confirmation creates one attempt, and that cookies, delivery details
and automatic navigation do not leak across the boundary. Separate HTTP/SDK,
MCP Apps host-bridge and native MySQL tests are documented in
[public MCP](public-mcp.md) and [remote commerce](remote-commerce.md). Fixture
success does not certify merchant provider configuration or a real paid purchase.

Native Chrome builds before 159.0.8140.0 need not expose `outputSchema` in
discovered descriptors. The PHP schema remains canonical and returned values
are checked in either lane. At and after that build, the browser fixture
requires the canonical output schemas, not just successful return values.
Chrome 160.0.8173.0 and later expose native Tool objects: use explicit
descriptor reads and keep the original object for execution; do not clone,
spread or JSON-serialize the handle. The synthetic registration-identity
regression verifies that unregistering and registering the same name again
does not allow a stale handle to invoke the replacement. See the [official
WebMCP update history](https://developer.chrome.com/docs/ai/webmcp/updates).
The separate forward CI job uses a sufficiently new system Chrome or pinned
Chrome for Testing 160.0.8184.0 and requires the new interface to be exercised.
It also checks both native commerce viewport fixtures, without production
credentials or payments. Unit fixtures cover both void and promise registration and
synchronous refusal. HTTP/controller fixtures exercise bounded input and
public/private separation.

`tests/public-knowledge-api.test.cjs` also enters the real `index.php` with a
rewritten script target and the original requested URI. It checks both discovery
URLs, the plain-text query-string variant, GET-only dispatch and rejected path
suffixes. The separate `tests/public-agent-discovery.php` verifies the reviewed
source, configured origin, controller content and discovery links.

After the deployment SHA gate, `tests/live-webmcp-a2ui-browser.cjs` independently
checks the real landing and shop pages in polyfill and native modes. It renders
a returned card in a test host on the page. The native mode repeats the bounded
CDP discovery and public contact read on both pages, without invoking commerce
actions. Both lanes also verify the actual shop filter's declarative attributes
and one ordinary read-only GET navigation, without submitting any contact form.
Discovery and form navigation are distinct checks, not a CDP form invocation.
This proves browser-level production behavior in the recorded Chrome
version; it does not claim that an unrelated chat client has installed the site's
connector or rendered its MCP App.

### Execution cancellation (Chrome 153+)
Public read tools combine the invocation AbortSignal with their document lifetime signal; cancelling one call does not unregister the tool. See https://developer.chrome.com/docs/ai/webmcp/updates and the public knowledge cancellation regression.
