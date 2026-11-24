---
type: doc
title: Native feature reconstruction from WordPress, WooCommerce and Site Kit
description: Exact ZIP REA evidence, already implemented PHP contracts, source-derived missing features, and safe native development order.
category: docs
---

# Three reference ZIPs → built-in PHP features

**Scope corrected 24 November 2026:** WordPress 7.1.4, WooCommerce 11.3.0 and Google Site Kit 1.192.0 are reference implementations for features **built directly into this existing PHP/MySQL website**. There is **no WordPress installation, plugin upload, PHP plugin loader, hook compatibility API, or separate WordPress database**. Do not copy upstream vendor files into production.

## Exact source evidence

| Reference | SHA-256 | ZIP inventory | Relevant source |
|---|---|---|---|
| WordPress 7.1.4 | `a147146c159362778ecbbbfb07bb20ddf4e85b130f971c824e656a8f938c5f87` | 4,250 entries; 1,512 PHP files | `wp-includes/revision.php`, `wp-includes/post.php`, `wp-admin/includes/class-wp-list-table.php`, `wp-admin/includes/privacy-tools.php` |
| WooCommerce 11.3.0 | `d9eb1e46df8309eb37b6f7f3fba51480f2a9a00e7feb10b1487592fde39accc6` | 6,349 entries; 3,658 PHP files | `includes/class-wc-product-variable.php`, `includes/wc-stock-functions.php`, `includes/class-wc-shipping-zone.php`, `includes/class-wc-webhook.php` |
| Site Kit 1.192.0 | `da9c8424b90fc700b09484c20a13aaa3e4ea75edd05a175bf100c6484daee064` | 2,301 entries; 1,923 PHP files | `includes/Modules/PageSpeed_Insights.php`, `includes/Modules/Analytics_4.php`, `includes/Modules/Search_Console.php` |

REA 6.8.0 inspected the exact ZIPs, selected 35 WordPress, 67 WooCommerce and 90 Site Kit PHP source files as **historical reference** (partial, no source execution). Evidence and extracted references remain in `~/Documents/rea-research/wp-woo-20261116/`, **outside the production repository**. A selected static PageSpeed JS analysis parsed 24,630 AST nodes; its inferred relationships are not observed browser behavior.

The older `native-platform-gap-matrix.json` has 39 rows (16 gap, 14 partial, 6 existing, 3 excluded), but was generated against **older** commit `c1a873f`. Current feature status must be reverified from source and tests. Do not add all 30 old gap/partial counts as live missing work.

## WordPress CMS → native feature roadmap (#270)

**Already implemented in current PHP:** `BlogService` draft/publish and revision history/restore, optimistic revision conflicts, scheduled visibility, `AuthoredPageService`, `MediaService`, `SeoService`, `AdminHelpService`, user roles and admin list/filter infrastructure. These are existing product contracts; do not add a WordPress adapter.

**Gaps requiring separate evidence and PRs:** structured page/layout templates, scoped autosave and edit-session locking, content taxonomies and blog-comment moderation, consent and customer-data export/erasure, persistent background job scheduling, redirects and richer navigation. Each native service needs its own API, admin UI, ownership checks and tests, not new WP hooks.

## WooCommerce commerce → native feature roadmap (#356)

**Already implemented:** real product catalog, carts including `RemoteCartService`, authenticated customers and address book, coupon rules, verified order/payment checkout, GST/tax reports, merchant feed, order invoices and safe existing refund flows. The existing inbound Razorpay payment webhook is not a general outgoing event subscription system.

**Priority for independently tested slices:**
1. Variable products: variant SKU/price/stock identity through cart, order, tax and refund.
2. Numeric stock and atomic reservations/release, backorders and low-stock policy.
3. Shipping zones/classes/methods and server-verified rates; the existing `ShippingService` is empty at this audit.
4. Line-level partial shipments and returns allocation, preserving gateway/GST reconciliation.
5. Authenticated commerce API identities and signed outbound webhook delivery/replay logs.
6. Virtual/downloadable products and import/export/marketing functionality only as separately scoped product work.

Reference WooCommerce classes as **domain contracts**, not source to copy or a requirement to adopt Woo HPOS tables.

## Google Site Kit → native Google integrations (#355)

**Already implemented:** `GoogleSiteKitClient` validated tags/verification status, `GoogleReportingClient` owner OAuth refresh/revoke, GA4 Data/Admin property discovery and Search Console reporting, with owner UI and tests. Existing source is **not** proof a customer Google account has connected or returned authentic reports.

**This native delivery (#358):** owner-only PageSpeed Insights via the official v5 API, using configured canonical HTTPS origin and explicit CSRF-protected Analyze action. Mobile and desktop results project actual Lighthouse score, lab metrics, available CrUX and up to four opportunities. Null/no-data/quota never invent a score. No extra Google OAuth scope, plugin runtime, database migration or auto-request on page load. PHP-session cooldown bounds repeated requests. Source: Site Kit `includes/Modules/PageSpeed_Insights.php` and official docs:
- https://sitekit.withgoogle.com/documentation/supported-services/pagespeed-insights/
- https://developers.google.com/resources/api-libraries/documentation/pagespeedonline/v5/csharp/latest/classGoogle_1_1Apis_1_1PagespeedInsights_1_1v5_1_1PagespeedapiResource_1_1RunpagespeedRequest.html

**Still to implement/verify:** actual owner OAuth and real reports, Tag Manager management, AdSense insights, Google Ads, consent mode, Reader Revenue Manager, Google Tag Gateway and configurable email reports. Each is a separate built-in service with explicit provider authorization and truthful status; none is called “installed Site Kit.”

## Delivery rules

Keep the current PHP auth/CSRF/role policy, original MySQL business records, one private JSON per agent chat, and the existing customer Git auto-deployment process. REA source inventories are evidence, not licenses to copy arbitrary PHP. Use independent implementations and synthetic browser/transport QA before any approved merge. Do not touch the founder's personal Hostinger account or conflate GitHub green checks with verified production deployment.
