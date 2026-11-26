---
type: doc
title: Google Product Feed and Channel Readiness
description: Native product export, owner preflight and the separate Merchant Center and YouTube setup boundaries.
category: docs
---

# Website catalogue → Google Merchant Center → eligible YouTube Shopping

Commerce → **Google & YouTube** opens `/admin/google-merchant`. It reads the
existing product records and explains what is exportable, excluded or needs
review. **Edit product** selects the actual existing record in the current
product form; it does not save or duplicate it. That existing editor uses
JavaScript for prefill, while the readiness page itself remains readable
without JavaScript. It does not connect a Google account, send an API write, register a
YouTube destination or change the database. The existing connection, tables,
products, pricing and checkout remain authoritative.

## Website product source

`GET /feeds/google-products.xml` returns Google-namespace RSS 2.0 from
`MerchantFeedService`, using `ProductService` for current product values and
active-offer pricing. Its URL uses configured `APP_URL`, never a request Host.
The product ID stays stable; the existing slug is encoded in the landing URL.
Current payable price is submitted as `price` in INR; this first version does
not create a separate sale-price-history claim or alter tax calculation.

Only allowlisted product fields are serialized. Hidden/draft/inactive records,
missing title/description/ID, invalid price or unusable product images are
excluded and counted. Real out-of-stock records remain `out_of_stock`; active
legacy stock becomes `in_stock`. Duplicate IDs never overwrite the first item.
No customer, order, supplier-cost, settings-secret or diagnostic field enters
the public XML. The route is unavailable when the shop module is disabled;
data-access failures return 503 rather than a successful empty inventory.

Local images are checked against current bundled/persistent image files, with
no generic site-artwork substitute. External HTTP(S) product images are not
fetched server-side and are flagged for review. Pixel count is a basic check,
not a visual-policy judgment; watermark, relevance and AI-image metadata checks
remain necessary. Images below 500px are flagged for Google's announced
January 31, 2027 requirement, not silently upscaled or labeled compliant.

Brand, GTIN, MPN and condition are emitted only when explicitly recorded and
valid. Unknown identifiers are **not** converted to `identifier_exists=no`.
An explicit no-identifier record can be represented, but the owner must confirm
that the exception is legitimate. The native product editor provides owner-only brand, GTIN, MPN, condition and
identifier-status fields. Use the existing product record; explicit blanks clear
fields, omission preserves them. GTINs are validated as strings with a check
digit; contradictory no-identifier declarations and invalid conditions are
refused. The Enterprise draft model cannot populate these facts. RSS and
product JSON-LD share the public metadata projection, and the visible product
page displays supplied brand/condition/MPN. Unknown identifiers and categories
or variants must still be reviewed, never guessed.

## Connect in Merchant Center

Use the business's own Merchant Center account. Verify/claim the website,
select country/language, add this XML URL as a product file with scheduled
fetch, and configure real shipping, returns and business information. Inspect
Google's processing and item diagnostics after a fetch. Feed generation is
not a receipt from Google; successful ingestion is not approval. The admin
page therefore reports approval and account connection as unverified.

A scheduled file is the implemented product-delivery method here. Typed Merchant API preview and read-only diagnostics are also available; actual synchronization requires authorized account and data-source setup. Do not start new work on the sunset Content API for Shopping.
Neither GA4 tag placement nor a Search Console verification string establishes
Merchant Center account ownership, product approval or reporting authorization.

## YouTube is a separate gate

Google documents direct Merchant Center product-source onboarding for eligible,
invited creators, including Analytics and channel/source linking. Standard
YouTube Studio store linking otherwise uses supported shopping platforms or
retailers. A custom PHP feed alone does not enable product tagging or bypass
YouTube channel eligibility. Confirm direct-onboarding access with YouTube;
no channel is marked linked and no `YouTube_Shopping` destination is fabricated.

## Multi-channel customer onboarding and WooCommerce reference

Google for WooCommerce v3.6 introduced YouTube Shopping. The pinned source
`woocommerce/google-listings-and-ads@e4c389ba652d10d427c4595edfd67d93d9320e48`
was imported with REA as a selected static historical reference. Its
`Connection::connect()` obtains a connection URL from the authorized Connect
Server, `third_party_link()` sends store URL, name and Merchant ID through its
proxy, and the account controller distinguishes Google connection, incomplete
link and linked state. Its Merchant API client is separate. These public source
findings do not provide partner credentials or authority to clone that service.
No fake WooCommerce REST identity is needed or implemented.

For Sri Panchami's multiple channels, first verify the business's own Merchant
Center website/data source, actual catalogue approvals, configured shipping and
return policy, and Analytics connection. In YouTube Studio confirm each actual
channel's eligibility and account access. Request direct invited-creator
onboarding with YouTube when available. A participating platform may use repeated
channel-ID attributes only after approved platform onboarding; do not put
placeholder or guessed channel IDs into the feed. Keep each channel's real
linking and product-tagging evidence separate. The owner has not yet supplied
channel identities or authorized account access.

In the initial live audit at `1a3a160`, 43 feed items were returned, all
in stock, none with brand/GTIN/MPN/condition. Sample product landing price matched
RSS and JSON-LD (1689 INR), and actual Chromium at 1280 and 375 px showed the
long title occupied 248/182 px respectively, with no purchase-adjacent terms
or contact links. The native landing improvement preserves the full product
title, adds visible stock and real checkout/purchase guidance beside existing
buttons, and does not promise unverified delivery times or returns. General
storefront UI and actual shipping/return policy verification remain separate.

References:
- https://woocommerce.com/document/youtube-shopping-google-for-woocommerce/
- https://support.google.com/merchants/answer/12375531
- https://support.google.com/merchants/answer/6324478
- https://developers.google.com/search/docs/appearance/structured-data/merchant-listing
- https://baymard.com/research-articles/mobile-ux-ecommerce (updated 2026-07-14).

## Narrow native Merchant API v1 compatibility bridge

The downloaded WooCommerce 11.3.0 archive (SHA-256
`d9eb1e46df8309eb37b6f7f3fba51480f2a9a00e7feb10b1487592fde39accc6`)
was re-inspected with REA: WC REST authentication/key creation, products
controller, and Store API product route/schema/query are distinct from the
Google extension. The Google for WooCommerce source at the existing
`e4c389b` pin calls WordPress.com site registration/authorization and an
authenticated partner proxy; its Merchant ProductInput client resolves the
actual data source. No evidence shows YouTube Studio selecting this platform
by a public WooCommerce product endpoint or a version banner. Do not expose
fake WordPress, WooCommerce, Jetpack or XML-RPC identities, credentials, or
authorization receipts. Native protocol compatibility is useful only where
the consuming client has an authorized, documented contract.

The native owner page exports an attachment at its existing authenticated
`/admin/google-merchant?format=product-inputs&language=en&feed_label=IN`
route. The language and feed label must come from the real data source;
these values are examples, not assumed production configuration.
`MerchantApiService::preview()` maps the existing RSS-eligible offer IDs,
links, images, stock, owner-confirmed metadata and payable INR amount to
Google's current `ProductInput` v1 shape. Integer price micros derive from
the same two-decimal price, preserving leading-zero GTINs. It does not
produce Google resource names, claim approval, write data or change the RSS.

For a participating platform only, a separately authorized operator can
prepare an offline proposal with the actual assigned stable group ID and
one or more actual channel IDs. The preview emits the four documented
custom attribute names (repeating channel ID); it never changes the public
feed, issues a link, sets a destination, or transmits those values.
Never invent the IDs, impersonate the WooCommerce platform, or assume
monetization is equivalent to Shopping eligibility.

Developer-only `merchant:diagnose` uses an explicitly supplied ephemeral
Merchant API bearer token in the operator environment and exact account,
primary data source, language, feed label and country. It makes only bounded
GETs to the fixed Google Merchant API host, without redirects or token
persistence/output. Check the selected data source's actual
`YOUTUBE_SHOPPING` destination and the matching processed offers' country
statuses; affiliate, unrelated data sources, archived offers and unknown
statuses are distinct. At most five pages of 100 account products are scanned.
An incomplete scan does not label unseen items missing. API quota/authorization
errors are failures, not empty success. Item approval never proves a YouTube
channel link. An owner must obtain the authorized content scope and Google
developer registration before real use; the existing reporting grant is not
reused or broadened.

Current developer commands:

```bash
./bapXphp merchant:inputs --language en --feed-label IN
./bapXphp merchant:diagnose --account <account-id> --data-source <source-id> --language en --feed-label IN --country IN
```

Supply the short-lived `BAPX_MERCHANT_ACCESS_TOKEN` only via an authorized
operator environment, not command arguments, source code, browser forms,
chat, logs or the repository. Do not set a real token for automated tests.
The CLI help documents the explicit partner-preview arguments. There is no
automatic API insertion, deletion, source registration or OAuth write in this
release.

References:
- https://developers.google.com/merchant/api/reference/rest/products_v1/accounts.productInputs
- https://developers.google.com/merchant/api/reference/rest/products_v1/ProductAttributes
- https://developers.google.com/merchant/api/reference/rest/datasources_v1/accounts.dataSources/get
- https://developers.google.com/merchant/api/reference/rest/products_v1/accounts.products/list
- https://developers.google.com/merchant/api/guides/quickstart/registration
- https://developer.woocommerce.com/docs/apis/store-api/resources-endpoints/products/
- https://woocommerce.com/document/google-for-woocommerce/common-errors/
- https://support.google.com/merchants/answer/12375531

## Shared CLI and verification

```bash
./bapXphp merchant:check
./bapXphp merchant:check --json
./bapXphp merchant:check --schema
./bapXphp merchant:feed
```

The CLI, owner screen and public feed call the same PHP projection. JSON output
has a stable operation/schema version and `read_only=true`. `--schema` describes
the contract without loading product data; feed output is XML on stdout.
Unknown options exit 2; operational failures exit 1 and emit no credentials or
raw exception details. A completed check can include exclusions/warnings and
must not be interpreted as Google approval. These are native CLI commands, not
a claim that an MCP transport or external account connection is implemented.

Run the PHP regressions, source/docs review gate and normal repository CI.
Browser tests inspect the actual XML document, sample landing pages/prices,
owner route and no-JS/mobile behavior, and guest/customer denial. No purchase,
Google upload, credential save or database mutation is required for these checks.

## Primary references (reviewed 16 November 2026)

- Product specification: https://support.google.com/merchants/answer/7052112
- Product files: https://support.google.com/merchants/answer/12631822
- Scheduled fetch: https://support.google.com/merchants/answer/14991445
- Direct YouTube source onboarding: https://support.google.com/merchants/answer/12375531
- Supported store connections: https://support.google.com/youtube/answer/12258186
- Merchant API approval distinction: https://developers.google.com/merchant/api/guides/products/add-manage
- Content API sunset: https://developers.google.com/shopping-content/guides/deprecation-and-sunset
