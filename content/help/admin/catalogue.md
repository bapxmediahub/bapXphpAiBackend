## Maintain a product

Open Products and choose the record you need to edit. Review its name, public description, price/offer fields, stock state and image before saving. Preview the public product page afterwards. A saved image URL alone does not prove an image loads; use Media Library to inspect the actual asset.

Use **Browse products** to search the whole catalogue by name, slug or category. Set the page size or sort by name. **Edit** opens the original record, including a scheduled offer or hidden item.

In **Tax**, select **GST unit (UQC)** and enter **GST quantity per sale item**
using the values confirmed by your accountant. A sold item representing a
250-gram pack can report `0.250` kilograms when that is the approved unit.
Saved values apply to new checkouts and leave issued invoices intact.

## Import new products

In **Products → Import CSV**, download the sample and replace its example row. Include `slug`, `name` and `price`. The optional columns are `category` and `description`. Save a UTF-8 CSV of up to 100 products and 64 KiB. Use each lowercase, hyphenated slug once and enter the INR price as a positive decimal without a currency symbol.

Choose **Preview import**. Correct any reported row in the original file and upload it again. Review the proposed products, then choose **Confirm import**. The store rechecks existing slugs and skips matches. It saves new rows together, hidden and out of stock. Open each product to add images, verify its tax and stock settings, and publish it when ready. After an interrupted confirmation, preview the same file again to see which slugs already exist.

## Organise the shop

Categories group products. Special Collections have their own selected product membership; a festive collection should contain only the intended items. Check the public category or collection after saving. Product visibility and stock state are separate decisions: hidden items should not be advertised by the Support Agent.

## Check an offer

Open Coupons to inspect the code, discount kind/value, validity and available limits. Confirm the intended scope and amount before sharing an offer. A named coupon or collection does not guarantee that a particular customer's checkout is eligible; checkout validates the current conditions.

Stock labels are not a promise of a complete quantity/reservation system. For an existing paid order with a fulfillment hold, follow the order-review controls instead of changing payment history.

## Check the public listing

After saving, inspect the public product page and confirm its price, image and stock state. Search/social metadata follows the site's configured canonical domain and the current record: **In stock** or legacy **Active** is advertised as available; other stock states are not. A hidden product is a separate visibility choice, not a stock count.

Search engines and social apps can cache older previews. Correct metadata does not guarantee a ranking, an immediate refreshed preview or successful checkout. Ask the development team to investigate a mismatch with the page URL and a safe screenshot; do not change an unrelated product or create a test purchase to force a result.

## Prepare products for Google and YouTube

Open **Commerce → Google & YouTube** to review the existing catalogue and its XML feed URL. The page separates exportable records, exclusions and data warnings. Use **Edit product** to correct the actual record and **View listing** to check its public price, availability and image.

Add the feed URL as a scheduled product-file source in your business's Merchant Center account after verifying the domain and setting country, language, shipping and returns. Read Google's item diagnostics after the fetch; an XML file is not proof of account connection or product approval. Missing brand/identifiers and unsuitable images need real information, not invented values.

YouTube Shopping is another step. Direct custom-store onboarding is for eligible, invited creators; other connections use YouTube's supported retailers/platforms. Confirm channel access and linking before promising product tags. The website does not automatically activate YouTube Shopping or register an account on your behalf. Analytics and Search Console reporting are separate from this product feed.

For YouTube Shopping, open Commerce → Google & YouTube. The existing XML
feed and Merchant API product-input JSON download use the same public catalogue.
Enter the actual data-source language and label. A preview is not a Google
upload, item approval or linked channel. Review the real Google account and
each channel separately; no website endpoint can substitute for partner
authorization.
