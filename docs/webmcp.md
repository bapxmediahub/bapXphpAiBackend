---
type: doc
title: Public WebMCP business tools
description: Landing-page discovery, shared public contracts and separate native and polyfill verification.
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

Contact and service facts are selected explicitly. They are not a serialized
settings row. The four offerings are Product Inquiry, Temple Guidance, Order
Support and General Question; they do not claim that retired consultation
booking is available. The human contact page and tools use the same values,
subject allowlist and descriptions. A service link prefills an enquiry subject;
it does not submit the form.

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
and cards attached to each result. A host still needs to connect explicitly.
Public tools must never inherit owner MCP credentials, private Help, customer
records, agent instructions or conversation JSON.

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
product tool; it does not add commerce writes or checkout automation.
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

Native Chrome versions before 159 need not expose `outputSchema` in discovered
descriptors. The PHP schema remains canonical and returned values are checked
in either lane. Unit fixtures cover both void and promise registration and
synchronous refusal. HTTP/controller fixtures exercise bounded input and
public/private separation.

`tests/public-knowledge-api.test.cjs` also enters the real `index.php` with a
rewritten script target and the original requested URI. It checks both discovery
URLs, the plain-text query-string variant, GET-only dispatch and rejected path
suffixes. The separate `tests/public-agent-discovery.php` verifies the reviewed
source, configured origin, controller content and discovery links.

After the deployment SHA gate, `tests/live-webmcp-a2ui-browser.cjs` independently
checks the real landing and shop pages in polyfill and native modes. It renders
a returned card in a test host on the page. This proves browser-level production
behavior; it does not claim that an unrelated chat client has installed the
site's connector or rendered its MCP App.
