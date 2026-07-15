---
type: doc
title: GST Commerce
description: GST Product Billing and Growth Tracking workflow for paid ecommerce product orders.
category: module
---
# GST Product Billing and Growth Tracking

This workflow applies only to paid ecommerce product orders. Scheduled consultant appointments are not included in product tax invoices or GST product-sales exports.

## Owner Setup

1. Open **Admin -> Settings** and enter the GST legal name, trade name, GSTIN, principal place of business, supplier state, and state code from the registration certificate.
2. Open **Admin -> Products** and enter the confirmed HSN code and GST rate for every taxable product. Do not infer a rate from the product name; confirm classification with the business tax professional.
3. Open **Admin -> Integrations** to configure Meta Pixel, GA4, Google Ads, and Search Console identifiers.

## Product Order Tax Data

Storefront prices are treated as GST-inclusive. At checkout the order records the delivery state and optional customer GSTIN. The order stores an immutable product-tax snapshot containing HSN, rate, taxable value, CGST, SGST, IGST, total tax, place of supply, and supplier identity.

- Tamil Nadu delivery uses an intrastate CGST/SGST split.
- Delivery to another state uses IGST.
- A confirmed Razorpay product order receives a financial-year invoice number.
- Customers can open and print the invoice from **My Orders**.

## Filing Report

Open **Admin -> GST Report**, select the filing period, and export CSV. The report includes only confirmed product invoices and excludes cancelled orders and consultant appointments. It is a filing-oriented sales ledger, not direct GST Portal submission. Reconcile it with payment settlements, credit notes/returns, and the tax professional before filing.

## Advertising Events

When IDs are configured, the storefront sends `view_item`, `add_to_cart`, `begin_checkout`, and `purchase` events to GA4 and maps them to Meta ecommerce events. No tracking library is loaded when its identifier is absent.

## Remaining Operations

- Record returns/refunds as credit notes before using the report for final filing.
- Reconcile invoice sequence concurrency before higher-volume multi-worker deployment.
- Validate each campaign in Meta Events Manager, GA4 DebugView, Google Ads, and Search Console after configuration.
