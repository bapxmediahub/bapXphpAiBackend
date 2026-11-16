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
that the exception is legitimate. Existing product-editor fields do not supply
all Merchant attributes; missing identifier/category/variant data must be
reviewed and supplied through supported catalogue work or Google supplementary
data, never guessed by an agent.

## Connect in Merchant Center

Use the business's own Merchant Center account. Verify/claim the website,
select country/language, add this XML URL as a product file with scheduled
fetch, and configure real shipping, returns and business information. Inspect
Google's processing and item diagnostics after a fetch. Feed generation is
not a receipt from Google; successful ingestion is not approval. The admin
page therefore reports approval and account connection as unverified.

A scheduled file is the supported connection method implemented here. More
frequent API synchronization must use Merchant API with authorized account and
data-source setup. Do not start new work on the sunset Content API for Shopping.
Neither GA4 tag placement nor a Search Console verification string establishes
Merchant Center account ownership, product approval or reporting authorization.

## YouTube is a separate gate

Google documents direct Merchant Center product-source onboarding for eligible,
invited creators, including Analytics and channel/source linking. Standard
YouTube Studio store linking otherwise uses supported shopping platforms or
retailers. A custom PHP feed alone does not enable product tagging or bypass
YouTube channel eligibility. Confirm direct-onboarding access with YouTube;
no channel is marked linked and no `YouTube_Shopping` destination is fabricated.

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
