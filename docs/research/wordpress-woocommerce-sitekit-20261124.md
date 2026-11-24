---
type: doc
title: WordPress, WooCommerce and Google Site Kit source-backed compatibility audit
description: REA 6.8.0 exact-ZIP provenance, historical matrix reconciliation, native capability boundaries, plugin preflight and executable follow-ups.
category: docs
---

# WordPress / WooCommerce / Site Kit — REA compatibility audit

**Audited 24 November 2026.** Baseline GitHub \`main\`: \`902228d43299294d9198538f114f33c61df75cfb\`.
This compares exact locally supplied archives with *current source*, not a live customer-hosted deployment or authenticated Google account. Nothing in the ZIPs was executed by REA or by the new PHP inspector.

## Source provenance and what was extracted

| Source | ZIP SHA-256 | ZIP members / PHP files | Existing REA scope |
|---|---|---|---|
| WordPress 7.1.4 | \`a147146c159362778ecbbbfb07bb20ddf4e85b130f971c824e656a8f938c5f87\` | 4,250 / 1,512 | Previous \`inspect-artifact\` and **35 selected historical PHP files** |
| WooCommerce 11.3.0 | \`d9eb1e46df8309eb37b6f7f3fba51480f2a9a00e7feb10b1487592fde39accc6\` | 6,349 / 3,658 | Previous \`inspect-artifact\`, **67 selected historical PHP files**, settings declarations and comparison matrix |
| Site Kit by Google 1.192.0 | \`da9c8424b90fc700b09484c20a13aaa3e4ea75edd05a175bf100c6484daee064\` | 2,301 / 1,923 | **New** REA \`inspect-artifact\` complete ZIP inventory and **90 selected historical PHP files** |

Local evidence lives under \`~/Documents/rea-research/wp-woo-20261116/\`:
\`archive-provenance.json\`, \`wordpress-archive-evidence.json\`,
\`woocommerce-archive-evidence.json\`, \`wordpress-source-evidence.json\`,
\`woocommerce-source-evidence.json\`, \`woocommerce-static-settings-declarations.json\`,
\`native-platform-gap-matrix.json\` and the new
\`google-site-kit-archive-evidence.json\`, \`google-site-kit-source-summary.json\`,
\`google-site-kit-selected-files.json\`.

The old comparison matrix (39 rows: 16 gap, 14 partial, 6 existing, 3 excluded) was built against **\`c1a873f\`**, not today's code. It cannot be presented as 30 outstanding gaps without revalidation. REA archive inventory proves the declared ZIP structure and file hashes; selected source imports are **partial historical source graphs**, not runtime execution, a complete dependency graph, a license grant or successful plugin activation. The Site Kit call-site counts below are static regex counts, not distinct API contracts.

## The WordPress runtime contract is the actual plugin-install blocker

The \`woocommerce/woocommerce.php\` and \`google-site-kit/google-site-kit.php\` entrypoints check \`ABSPATH\`, assume a loaded WordPress environment and register WordPress lifecycle hooks. WordPress 7.1.4 itself is **core**, not a plugin archive. Both plugins require a real provider of:

- **Boot lifecycle:** \`plugins_loaded\`, \`init\`, \`admin_init\`, network/plugin activation, deactivation, uninstall, dependency order and version checks
- **Hooks and state:** \`add_action\`, \`add_filter\`, \`apply_filters\`, \`do_action\`, \`get_option\`, \`update_option\`, transients, per-user metadata and migrations
- **Identity and authorization:** authenticated users, \`current_user_can\`, nonces, roles/capabilities, per-account OAuth data and privacy rules
- **HTTP and assets:** \`register_rest_route\`, WP HTTP transport, rewrite routing, enqueue scripts/styles, localized frontend config, translation/text domains and templates
- **Durability:** WP-Cron/Action Scheduler, scheduled work, schema persistence, third-party dependency autoloaders, error isolation and rollback

A simple hook shim would still not supply \`wpdb\`, posts, metadata, taxonomies, REST permission callbacks, admin screens and other WordPress semantics. Loading third-party PHP in the native customer's PHP process would execute code with that process's privileges. We therefore **cannot truthfully claim install-and-run compatibility** merely by copying files into an \`integrations/\` folder or defining \`ABSPATH\`.

New code under #353 implements the first safely testable step: static ZIP inspection through \`./bapXphp plugin inspect /path/archive.zip\`, no code execution, no extract, no host writes, with bounded decompression/entry sizes, traversal/symlink rejection, header parsing and declared dependencies. It reports \`core_not_plugin\` for WordPress and \`requires_wordpress_runtime\` for WooCommerce and Site Kit; \`installable=false\` is intentional. Full activation/compatibility is tracked in #354.

Official WP references: [plugin header contract](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/), [activation/deactivation](https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/). **Licensing:** WordPress and WooCommerce carry their own distribution licenses; Site Kit's entrypoint declares Apache 2.0. Audit notices and independent-implementation obligations before porting any source. No upstream source is copied by this feature.

## Reconcile the old 39-row matrix with current native PHP

| Prior rows | What current source *actually* shows | Decision |
|---|---|---|
| P01 PHP/MySQL baseline | \`bapXphp runtime:check\` and GitHub PHP 8.3/8.4/8.5 + isolated MySQL 8.4 tests now exist | **Earlier gap stale**; customer DB/runtime acceptance remains independent |
| P04 blog revisions and scheduling | \`BlogService::revisionHistory\`, \`restoreRevision\`, optimistic revision checks and date-based publication rules now exist | **Earlier broad gap stale/partial**; general scheduling, autosave and lock semantics separately verify |
| P05 authored pages | \`AuthoredPageService\` and admin authored-page routes exist | **Earlier gap partly delivered**; reusable block/widget templates remain separate |
| P10 list tools | Admin list-controls/pagination source exists | **Earlier gap partly delivered**; bulk policy and all screens still need proof |
| P21 catalog import/export | \`ProductImportService\` and owner import form exist | **Import exists**; resumable export/error replay not fully demonstrated |
| P24–P25 orders/payments/GST | \`RemoteCheckoutService\`, \`VerifiedOrderService\`, \`GstRegisterService\` and isolated race tests exist | **Preserve verified-payment and invoice invariants**, not duplicate them |
| P27 persistent cart | \`RemoteCartService\` exists alongside native session cart | **Not wholly absent**; guest-to-account merge and durable recovery need targeted tests |
| P28 commerce reports | \`SiteInsightsService\`, owner commerce reports/A2UI exist | **Earlier broad gap stale**; source-window attribution and denominators need independent checks |
| P31/P34 API/MCP | \`PublicMcpService\`, \`McpClientService\` and \`mcp:stdio\` exist | **Native MCP delivered in part**; not a WooCommerce v3 REST API or universal plugin grant |
| P17/P18/P19/P20/P23/P26/P32 | Mail queue exists; \`ShippingService\` is currently an empty class, no demonstrated general WP-Cron, numeric variant inventory, download-authorization model or outbound webhook subscribers | **Still concrete missing/partial targets**, not inferred absent merely from filenames |
| X01–X03 plugin/theme and paid extensions | Old matrix deliberately excluded arbitrary third-party plugin/theme runtime and paid Woo extensions | **Decision changed by owner request; architecture proposal #354**, no unsafe activation by default |

## WooCommerce 11.3.0: what we still need

The ZIP includes \`includes/class-wc-product-variable.php\`,
\`class-wc-product-variation.php\`, \`wc-stock-functions.php\`,
\`includes/class-wc-shipping-zone.php\`, \`includes/shipping/\`,
\`includes/class-wc-webhook.php\`, \`includes/rest-api/Controllers/Version3/\`,
\`src/StoreApi/\`, \`src/Internal/DataStores/Orders/\` and Action Scheduler.
These are *reference domain implementations*, not PHP to copy.

| Priority | Domain gap relative to demonstrated native PHP | First concrete acceptance |
|---|---|---|
| P1 | Product variant/SKU/price/stock identity, not just one product's catalog price | Selected variant persists through cart, tax, payment, order, refund; wrong variant is rejected |
| P1 | Numeric stock, reservations, release rules, backorders and race-safe oversell prevention | Two concurrent purchases cannot reserve more than the atomic available quantity |
| P1 | Shipping zones/classes/rate methods and address/rate selection; existing \`ShippingService\` contains no methods | Server verifies region/rate, no client-side rate claims or unexpected fees |
| P1 | Partial item fulfilment, returns and refund allocation with tax/GST ledger mapping | Multiple partial shipments/refunds cannot exceed ordered/verified amounts |
| P1 | Scoped outbound resource webhooks, HMAC signatures, idempotent retry and bounded event delivery | Delivery log/secret rotation/test receiver; existing inbound Razorpay webhook is not this feature |
| P2 | Download permissions, scheduled jobs, product import/export extension, reliable customer cohorts | Scoped records, expiry, event provenance and privacy-safe reporting before UX polish |

High-performance order storage is a useful **contract/design reference**, not a requirement to add WordPress \`wp_posts\` tables or import Woo HPOS tables. Existing MySQL JSON-collection business data remains authoritative. Sources: [HPOS](https://developer.woocommerce.com/docs/features/orders/high-performance-order-storage/), [Woo webhooks](https://developer.woocommerce.com/docs/apis/rest-api/v3/webhooks/). Issue #356 owns implementation slices.

## Google Site Kit 1.192.0: coverage, not the installation badge

The exact ZIP exposes module PHP files for Analytics 4, Search Console, Tag Manager, AdSense, PageSpeed Insights, Ads, Reader Revenue Manager and Sign in with Google; it also has consent-mode, email-reporting and Google Tag Gateway implementations. Its main file requires WordPress 5.2+ and PHP 7.4+.

**Static dependency call sites found within Site Kit's own \`includes/\` and main plugin file:** 462 action/filter invocations, 40 option calls, 167 capability calls, 35 scheduling calls, eight script/style enqueue calls and one direct REST-route registration call. They do not account for dependencies hidden in vendors or dynamic strings.

**Already present in current bapX PHP:**
- \`integrations/google-site-kit/GoogleSiteKitClient.php\`: validated gtag/Ads destinations, Search Console verification metadata and configured-vs-verified status; it does **not** establish that collection or ownership worked.
- \`integrations/google-site-kit/GoogleReportingClient.php\`: scoped owner OAuth, refresh/revoke, GA4 account/property choice, Analytics Data reports and Search Console site/query data.
- \`AdminGoogleReportingController\`, admin integrations/reporting views and \`tests/google-reporting.php\` / \`google-reporting-storage.php\`.

**Not yet proven:** a real customer's authorized OAuth consent/property selection and actual dashboard accuracy; Tag Manager container management, AdSense revenue, PageSpeed data, Reader Revenue Manager, Ads metrics, full consent-mode UX, Google Tag Gateway and recurring email reports. Some may require extra APIs/scopes/account eligibility. Reuse existing encrypted \`SecretService\`; never claim an unconnected service is active.

Do **not** attempt to run \`google-site-kit.php\` on the non-WordPress customer host. Stage 1 is actual owner OAuth/property acceptance of existing native reporting, stage 2 is a distinct read-only module (PageSpeed is a low-risk candidate), stage 3 is separately scoped Tags/Ads/consent work. See #355 and existing #46; official [Site Kit documentation](https://sitekit.withgoogle.com/documentation/).

## Delivery milestones and definition of done

1. **#353 now** — standalone statically verified ZIP preflight and safe CLI, fixture-backed traversal/symlink/ZIP bomb/duplicate rejection. It is *not* a plugin installer.
2. **#354 next** — choose explicit compatibility strategy: a reviewed native adapter (recommended for Site Kit) versus isolated real WordPress instance if arbitrary ecosystem plugins are a hard requirement. Model plugin dependency grants and per-plugin status before installation UI.
3. **#355** — run native reporting OAuth/real provider acceptance only with an authorized owner, then implement one missing scoped Google module at a time.
4. **#356** — Woo variable-products/stock/shipping/fulfilment/webhooks in independent PRs with race and payment tests, never as a single huge rewrite.

REA Evidence and copied source under \`~/Documents/rea-research\` stay *outside* the production Git checkout. No production hosting, personal Hostinger account, customer database or live Google OAuth account was touched for this research.
