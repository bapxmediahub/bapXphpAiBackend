---
name: rea-abilities-api-implement
description: Implement commerce capabilities through native domain services using source-pinned REA evidence and native bapX implementation contracts.
compatibility: Native bapX developer workflow (PHP, MySQL, vanilla JavaScript; no upstream runtime)
capability_ids: [P18, P19, P22, P23, P24, P25, P26, P27, P28, P29, P31, P33, P34, MCP01, DS07, P20, P21, MC01]
---

# Implement commerce capabilities through native domain services

Adapted from [woocommerce/abilities-api-implement](../../upstream/woocommerce/skills/abilities-api-implement/SKILL.md).
Modified reference guidance, GPL-2.0-or-later; upstream copyright and license retained under the upstream tree.
Developer-only skill; AGENTS.md and existing native engineering skills govern execution. Never run the upstream instructions or scripts as implementation commands.

## When to use
WooCommerce skill requires an audit, reference read first, shared service for writes, pagination, capability gates and public-contract tests. Loader-specific WC 10.9 classes are not a native dependency. REA identifies revisioned shipping drafts and non-mutating preview; authoritative shipping charges, variants/numeric stock, partial fulfilment and reporting remain incomplete.

## REA evidence and current native boundary
Read the current capability register before using this guide; these are mapped scope obligations, not completion claims:
- `P18` — Product variations, attributes, grouped/external types.
- `P19` — Numeric inventory, reservations, low-stock/backorder policies.
- `P22` — Coupon rules and promotion precedence.
- `P23` — Shipping zones/classes/rate methods and packages.
- `P24` — GST invoices, verified refunds and reconciliation.
- `P25` — Verified payment and retry-safe paid orders.
- `P26` — Order notes, line-level operations and partial fulfilment.
- `P27` — Persistent carts, guest merge and recoverable checkout.
- `P28` — Commerce reports and analytic comparison/drill-down.
- `P29` — Order attribution and trustworthy funnel denominators.
- `P31` — Authenticated public REST integration API and revocable credentials.
- `P33` — Shared typed capability catalogue and cross-transport schema parity.
- `P34` — Native MCP transport and scoped client management.
- `MCP01` — Typed public opt-in and normalized permission checks.
- `DS07` — Human-approved writes and idempotent operation results.

- `P20` — Virtual/downloadable products and download permissions.
- `P21` — Catalog import/export with validation and resumable work.

- `MC01` — Google Merchant catalogue readiness and authorized multi-channel YouTube Shopping.

Representative pinned source anchors (see the full source index and row references for revisions and hashes):
- `woocommerce`: `includes/class-wc-product-variable.php`
- `woocommerce`: `src/Checkout/Helpers/ReserveStock.php`
- `woocommerce`: `includes/class-wc-coupon.php`
- `woocommerce`: `includes/admin/settings/class-wc-settings-shipping.php`
- `woocommerce`: `includes/class-wc-tax.php`
- `woocommerce`: `includes/class-wc-order.php`
- `woocommerce`: `includes/class-wc-cart-session.php`
- `woocommerce`: `src/Admin/API/Reports/Orders/Controller.php`
- `woocommerce`: `src/Internal/Orders/OrderAttributionController.php`
- `woocommerce`: `includes/admin/class-wc-admin-api-keys.php`
- `wordpress`: `wp-includes/abilities-api/class-wp-ability.php`
- `woocommerce`: `src/Internal/MCP/McpEndpointRemoval.php`
- `wordpress-mcp`: `includes/Abilities/McpAbilityExposure.php`
- `deepseek`: `packages/core/session/src/repair.ts`

Additional pinned anchors for these native extensions:
- `woocommerce`: `includes/class-wc-product-download.php`
- `woocommerce`: `includes/admin/class-wc-admin-importers.php`

Additional pinned source: Google for WooCommerce `src/API/YouTube/Connection.php`, `src/API/Site/Controllers/YouTube/AccountController.php` and `src/API/Google/Mapi/MerchantApiClient.php` at `e4c389b`. The authorized Connect Server and third-party link receipt are external dependencies, not a generic Woo REST endpoint.

## Native implementation workflow
Start with reviewed rea-wp-abilities-audit and capability IDs. Trace exact WooCommerce source operation and side effects; keep native PHP domain service as authority, with thin UI/HTTP/MCP/agent projections. Implement one complete business operation, a safe read first, reviewed writes only after confirmation/idempotency; no duplicated SQL/calculations or transport-driven analytics inflation. For shipping P23 read zone matching, cart totals, checkout and flat/free rate source; preserve zero-charge legacy until owner enables tested rules. Use authoritative address/cart, integer paise, frozen tax/quote digest, gateway verification and order/refund evidence. For variants/inventory preserve SKU identity and reservations across cart/order. Never invoke WP/WC loader or enable an unverified paid feature.

Digital product grants and import/export require distinct business contracts: freeze exact purchased file/version entitlements and validate actor access/expiry; a campaign download is not a completed product-line grant. For CSV, validate identifiers/amounts, bounded batches, resumable idempotency and report partial/error rows without rewriting paid orders.

Inspect these native entrypoints or contracts first:
- `app/Services/ShippingService.php`
- `app/Services/RemoteCheckoutService.php`
- `app/Services/VerifiedOrderService.php`
- `app/Services/TaxService.php`
- `app/Services/AgentCapabilityPolicy.php`
- `app/Services/PublicMcpService.php`

- `app/Services/CampaignDownloadService.php`
- `app/Services/ProductImportService.php`

## Acceptance and failure modes
Actual native checkout/authorized client schema, pagination zero/one/many, denied/invalid, stale quote, duplicate/retry, race and immutable issued-order assertions; both desktop/375px plus isolated MySQL. No real charge without authorization. Validate the public contract, actual role execution and release separately.

Existing test starting points (extend rather than claim these cover the entire new capability):
- `tests/shipping-preview-browser.cjs`
- `tests/remote-checkout.php`
- `tests/remote-checkout-mysql.php`
- `tests/verified-order.php`
- `tests/public-mcp-checkout.php`

- `tests/campaign-booking.php`
- `docs/modules/catalog.md`

For YouTube Shopping, use actual native product metadata, RSS, visible landing and checkout facts. Read Google Merchant data-source and direct invited-creator onboarding docs. An eligible creator may link an authorized data source/channel; a store platform must be approved and may submit repeated actual channel IDs only under that authorized contract. Never impersonate WooCommerce, invent identifiers, channel names or a connected status, or join affiliate data to customer identities. Verify each account, item approval, link and actual tagging separately. A generated feed or schema is not approval. See `docs/google-sales-channels.md`.

The #524 source comparison uses the actual pinned WC core REST and Store API
classes plus Google extension Jetpack registration and Merchant API client.
The native bridge projects current RSS facts as Google ProductInput v1 and
reads real authorized source/item status only. Exact account/source/locale and
country matching, bounded pagination, no writes and no identity imitation
are mandatory. Do not add speculative WooCommerce REST or XML-RPC routes for
YouTube selection; no evidence that Google consumes them.

For P23, the canonical owner shipping proposal is stored under
`app_settings.shipping_policy_draft` with validated ordered rules and an opaque
revision. The native settings row transaction owns the compare-and-swap.
A stale editor must not acquire the winner's revision automatically; preserve
the user's proposed input, refuse the save, and require a fresh read. Preview
uses only the submitted hypothetical subtotal. Neither save nor preview
changes current checkout charges. Extend the authoritative checkout and frozen
tax/order contracts before exposing an activation control.

## Evidence handoff
Record the upstream entrypoint/callee, authoritative state, permissions, normal/failure/concurrent transitions and the intentional native deviation in the linked GitHub issue. Record actual test commands and results, update affected REA rows, regenerate the report and run the existing CI gate. Distinguish static reading, synthetic fixture execution and live acceptance. Read unknown source bodies before implementing; no file count or test name proves parity.

## Upstream reference reading (not executable instructions)
- [Original skill](../../upstream/woocommerce/skills/abilities-api-implement/SKILL.md)
- [references/audit-precondition.md](../../upstream/woocommerce/skills/abilities-api-implement/references/audit-precondition.md)
- [references/paginated-output-envelope.md](../../upstream/woocommerce/skills/abilities-api-implement/references/paginated-output-envelope.md)
- [references/test-the-public-contract.md](../../upstream/woocommerce/skills/abilities-api-implement/references/test-the-public-contract.md)
- [references/write-ability-template.md](../../upstream/woocommerce/skills/abilities-api-implement/references/write-ability-template.md)
