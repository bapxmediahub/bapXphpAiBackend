---
type: doc
title: Orders Module
description: Owns cart checkout, payment verification, order storage, shipping status, customer order history, and product review timing.
category: module
---
# Orders Module

Owns cart checkout, payment verification, order storage, shipping status, customer order history, and product review timing.

Main files: `CommerceController.php`, `RemoteCheckoutController.php`, `CartService.php`, `RemoteCartService.php`, `RemoteCheckoutService.php`, `RemoteCheckoutStateService.php`, `VerifiedOrderService.php`, `OrderService.php`, `MailQueueService.php`, checkout/order views.

`CartService` resolves current public products and offer prices for browser
cart/checkout amounts, preserving paise. `RemoteCartService` provides a separate
anonymous MySQL cart capability to HTTP agents. `RemoteCheckoutService` applies
delivery/coupon details in one revision-bound review, quotes canonical included
tax in integer paise, and requires explicit confirmation before a durable
provider attempt. Its status capability, signed provider webhook and bounded
reconciler can finish a verified payment independently of a browser or cart
lifetime. The website review fallback replaces a session cart only after CSRF
validation and shopper confirmation; that fallback alone creates no payment.
See [remote commerce](../remote-commerce.md) for capability expiry, conflicts,
rate limits and replay recovery.

Key checks: shipping fields persist, admin status updates queue shipment/review emails, and product reviews unlock after `review_request_after_at`.

Verified gateway callbacks use `VerifiedOrderService`, a payment-derived order ID and atomic insert-if-absent storage. Razorpay's shared predicate requires captured status, INR, exact amount and matching payment/order IDs. Tax receives the actual applied discount. Complete server-frozen product and supplier tax inputs survive later product deletion, while incomplete evidence requires tax review. If stock changes after checkout, the paid order is stored as `on_hold`, the customer and owner are told that availability needs review, and fulfillment cannot proceed until the owner explicitly confirms availability. Search Admin → Orders for “Fulfillment review required”; contact the customer, confirm stock or arrange a refund, then record the resolution on the order. Callback retries return the original order and do not queue another confirmation. Shared persistence never changes browser session state.

For payments made before this safeguard, reconcile the gateway export against Admin → Orders by payment ID and investigate any paid ID without an order before creating or refunding anything. When a held order references a missing product and lacks complete frozen tax evidence, restore the original HSN and GST rate before invoice review; the application deliberately refuses to guess those values. Preserve the gateway total and payment ID, document the customer outcome, and never rewrite an already-issued invoice.

## Saved shipping zone proposals and preview

The owner Shipping screen offers a dry run of up to five ordered Indian delivery
zones. Choose a state, optional comma-separated exact six-digit PINs or
2–5 digit prefixes ending in *, and a positive flat fee or free shipping with
an optional minimum subtotal. The first state/PIN match wins; a failed minimum
or an unmatched address returns no fee. Values are converted exactly to integer
paise. The hypothetical subtotal is entered by the owner.

The owner can save the ordered rules as a draft in the canonical
`app_settings.shipping_policy_draft` record. This stores only the validated
zones and an opaque revision, using the existing settings row transaction.
A stale revision is refused without overwriting the winning proposal; invalid
or unavailable data cannot activate a rate. The separate preview remains
non-mutating and uses a hypothetical subtotal entered by the owner. Saving,
clearing or previewing a draft never changes the active shipping configuration,
customer checkout, GST, quote digests, orders or payments.

The native scope follows the pinned WooCommerce zone order, normalized
destination, flat-rate and minimum-based free-shipping source contracts, but
does not implement WooCommerce packages, shipping classes, cost expressions,
multiple selectable methods or coupon-driven free shipping. Production charging
still requires authoritative current cart/address quotes, revision-bound totals,
GST and frozen invoice allocation, gateway verification and acceptance across
both checkout paths before the owner can activate any saved proposal.

Browser Razorpay and Stripe order creation use the same server-computed
cart less the validated coupon and the same frozen supplier/item tax
projection as remote checkout. Both gateways retain the actual discount,
pending-order evidence and original invoice inputs until verified capture. A non-payable cart or total below the gateway minimum is
rejected before payment setup; client-posted amounts never substitute for the
server total. Free-order fulfillment is a separate unsupported workflow.
