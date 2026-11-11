---
type: doc
title: GST Commerce
description: GST Product Billing and Growth Tracking workflow for paid ecommerce product orders.
category: module
---
# GST Product Billing and Growth Tracking

This workflow applies only to paid ecommerce product orders.

## Owner Setup

1. Open **Admin -> Settings** and enter the GST legal name, trade name, GSTIN, principal place of business, supplier state, and state code from the registration certificate.
2. Open **Admin -> Products** and enter the confirmed HSN code and GST rate for every taxable product. Do not infer a rate from the product name; confirm classification with the business tax professional.
3. Open **Admin -> Integrations** to configure Meta Pixel, GA4, Google Ads, and Search Console identifiers.

## Product Order Tax Data

Storefront prices are treated as GST-inclusive. At checkout the order records the delivery state and optional customer GSTIN. The order stores an immutable product-tax snapshot containing HSN, rate, taxable value, CGST, SGST, IGST, total tax, place of supply, and supplier identity.

Checkout requires a valid Indian state or Union Territory before starting payment. `TaxService` refuses to create a tax snapshot when the delivery or supplier state is missing or invalid; it never substitutes the supplier state while classifying the supply another way.

- Tamil Nadu delivery uses an intrastate CGST/SGST split.
- Delivery to another state uses IGST.
- A confirmed Razorpay product order receives a financial-year invoice number.
- Customers can open and print the invoice from **My Orders**.

## Invoice reconciliation

Open **Admin → Analytics → Commerce Reports**, select **GST**, and apply the invoice date range.
The totals CSV contains one row per issued invoice. The lines CSV contains one row per recorded tax line and
includes supplier and recipient GSTIN, a provisional recipient classification, place of supply, HSN, quantity,
rate and tax amounts. If an issued invoice has no lines, the export keeps a row with a review note.
Invoice snapshots stay intact; a gateway refund is flagged for review and is not a GST credit note.

Before calling an export filing ready, reconcile invoices and cancellations against the documents-issued sequence,
confirm valid HSN and unit codes, map eligible outward supplies into the current portal tables, and account for
credit/debit notes and amendments separately. GSTN's [Table 12 and 13 advisory](https://tutorial.gst.gov.in/downloads/news/updated_advisory_hsn_table12_25042025.pdf)
describes separate B2B/B2C HSN summaries and documents-issued reporting. Validate the resulting files with the
current GST utility and the accountant before filing. The CSVs in this screen do not do that validation.

## Admin order lookup

**Admin → Users** lists registered accounts, with expandable orders placed under the same checkout email.
Dates use India Standard Time; orders without an available creation timestamp say so. Guest orders without a
matching registered account remain under **Commerce → Orders**. Issued bills open from either screen and can
be printed or saved as PDF.

## Enterprise Agent follow-up

The existing PHP tool registry already scopes exact order lookups and serves aggregate sales. Reuse the same
invoice reconciliation calculations in a dedicated, read-only GST summary tool. Validate a bounded date range
on the server, return aggregate amounts and review counts with a report link, and omit recipient data.
Log tool outcome and latency, and test permission boundaries, refund handling and agreement with the admin report.
Keep filtering and aggregating in PHP; the model can explain the result but should not calculate tax, query
arbitrary SQL or file a return. See [OpenAI function calling](https://developers.openai.com/api/docs/guides/function-calling).

## Advertising Events

When IDs are configured, the storefront sends `view_item`, `add_to_cart`, `begin_checkout`, and `purchase` events to GA4 and maps them to Meta ecommerce events. No tracking library is loaded when its identifier is absent.

## Remaining Operations

- Record returns/refunds as credit notes before using the report for final filing.
- Reconcile invoice sequence concurrency before higher-volume multi-worker deployment.
- Validate each campaign in Meta Events Manager, GA4 DebugView, Google Ads, and Search Console after configuration.
