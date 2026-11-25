---
type: doc
title: Anonymous carts and checkout in chat
description: Browser-independent commerce, authoritative review, explicit payment confirmation and durable signed completion.
category: docs
---
# Anonymous carts and checkout in chat

Ordinary HTTP MCP clients can prepare a real shopping cart without a browser
session or an administrator credential. The fixed [public MCP transport](public-mcp.md)
exposes six separate actions: `create_cart`, `get_cart`, `set_cart_quantity`,
`review_checkout`, `prepare_checkout_payment` and `get_checkout_status`.
`RemoteCartService` and `RemoteCheckoutService` own their canonical behavior.
Public catalogue discovery still comes from `search_public_products`. There is
no generic model-callable operation selector, arbitrary service/SQL executor,
stock reservation, account lookup, saved-address access or appointment booking.

## Explicit cart capability

The three public cart tools map to fixed internal operations:

| Operation | Arguments | Result |
|---|---|---|
| `create_cart` | No arguments | New empty cart and its private `cartToken` |
| `get_cart` | `cartToken` | Current cart, prices, availability and revision |
| `set_cart_quantity` | `cartToken`, raw published `slug`, integer `quantity`, `expectedRevision` | Absolute quantity update; zero removes the selection |

Each operation accepts only its declared keys. The quantity range is 0–99, with
at most 20 distinct products in one cart. Use the exact published product slug,
decoded once from the canonical product path; a product name, record ID,
encoded slug or arbitrary URL is not a selector.

Creation returns a random 128-bit record locator plus a separate random 256-bit
secret. The client treats the complete token as opaque and carries it explicitly
in later calls. Only the secret's SHA-256 digest is stored in MySQL. A token
grants access to that anonymous cart alone and expires 24 hours after creation;
reads and updates do not extend its lifetime. An unknown token and a valid but
expired token return distinct safe tool errors. Browser cookies, owner tokens
and MCP connection state cannot select a cart or broaden access.

The token is intentionally present in creation's `structuredContent`, so a
plain model/HTTP client can continue the task. Subsequent cart results do not
repeat it. Clients must retain it privately, avoid logging or displaying it,
and never place it in a product or checkout URL. An app widget can keep it in
memory; never merge unrelated widgets' carts. Native Support has the bounded
recovery behavior described below.

This follows MCP's non-normative
[explicit state-handle guidance](https://modelcontextprotocol.io/specification/2026-07-28/server/tools#stateful-tools).
Cart state is application data; it does not introduce a protocol session.

## Current products, money and conflicts

Cart rows store only raw product slugs and integer quantities. Reads resolve
the actual public `ProductService`/`PublicKnowledgeService` projection,
including visibility, the current offer window, stock status, bounded names,
and approved product/image paths and official URLs. Private product fields and
stored customer/order data are never copied into a cart result.

Each item reports `unitPriceInr`, `lineTotalInr`, `quantity` and `available`.
Units and line totals retain paise through the shared browser cart and checkout
path. Gateway amounts use rounded integer paise. `estimatedTotalInr` includes
the product prices and applicable included GST; final delivery and discounts
are reviewed in the shared in-chat checkout. No cart is a price or stock reservation.

If a selected product becomes hidden or disappears, its slug remains in
`unavailableSlugs` so the client can remove it, but stale product details are
omitted. A visible out-of-stock item remains marked unavailable. Any unavailable
selection makes the total `null` and blocks checkout preparation; the server
does not quote only the remaining subset.

An update must carry the current revision. The database host locks the
individual row and compares that revision before applying an absolute quantity.
An immediate identical retry of the last successful change is recognized and
does not increment quantity or revision again. A conflicting later update is
rejected: retrieve the cart and ask for the intended current quantity rather
than silently merging stale operations. A no-op at the current revision keeps
the review receipt even when MySQL normalizes JSON key order. Creation is not
idempotent; do not repeat `create_cart` to recover a known cart.

## Authoritative review inside chat

Native Support and compatible MCP Apps share a fixed checkout form. Checkout
opens it in chat; Buy now uses a separate cart. Delivery/contact/coupon data
is sent only when the shopper explicitly chooses Review. Card numbers, CVV,
bank credentials and UPI PINs never belong in this form or model context.

`review_checkout` accepts `cartToken`, `expectedRevision` and optional complete
`delivery` and `couponCode`. Delivery contains name, email, phone, address,
city, six-digit PIN, canonical Indian state/Union Territory and optional GSTIN.
It never accepts account IDs, saved-address IDs, client totals or tax fields.
Omit a coupon to retain it or send an empty string to clear it. One atomic
compare-and-set applies the submitted delivery and coupon together.

The service reuses `CouponService` eligibility and `TaxService` discount/tax
allocation. Totals are integer paise: items, discount, shipping and payable
total, with included GST/CGST/SGST/IGST. The current actual shipping charge is
zero; dormant flat-rate settings are not activated. Razorpay requires at least
₹1 (100paise), so lower post-coupon totals are rejected before attempt/provider
creation ([provider constraints](https://razorpay.com/docs/api/payments/payment-links/create-standard)).

A complete review returns a random `quoteId`, `cartRevision` and a ten-minute
expiry bounded by cart expiry. A private digest binds normalized delivery,
coupon, quantities, prices, supplier/product tax inputs and total. Public
responses contain completion/missing-field flags and delivery state, not full
contact/address/GSTIN/coupon text. Raw details stay in the fixed form locally
and the private draft, outside public A2UI, model summaries and saved chat HTML.

`prepare_checkout_payment` requires the exact revision, quote ID and
`confirm: true` after the shopper explicitly approves that review. The service
rechecks current visibility, availability, prices, tax and coupon eligibility.
Changed or expired evidence requires a fresh review and confirmation.

## Durable payment and truthful recovery

The database host locks the cart and inserts a private `checkout_attempts` row
in one transaction before provider I/O. The cart becomes `checkout_locked`.
Concurrent/repeated confirmation converges on one attempt, and later quantity
or delivery changes are rejected. The first caller creates a Razorpay Standard
Payment Link with a unique reference, INR integer amount, no partial payment,
30-minute expiry, notifications disabled and reminders disabled. Full delivery
and contact data is not included in link creation.

A timeout can follow successful provider creation. Retries look up that unique
reference instead of issuing another create request. Empty lookup remains
`unknown`; local handoff expiry or an absent reference is not proof of provider
expiry. Only authenticated provider evidence produces expired, cancelled or
captured status. Do not ask a shopper to pay again while an attempt is unresolved.

The primary payment URL is the merchant's own
`/checkout/payment?ticket=pay_…`, with a separate 30-minute capability. It
contains neither cart nor status secret. GET changes no browser cart, creates
no payment and saves no order. The page uses no-store/no-referrer/noindex and a
restrictive CSP, with no analytics, provider scripts or iframe. An explicit
**Continue to Razorpay** action opens the approved provider URL. Delivery and
order review stay inside compatible chat; actual payment credentials stay with
the provider. General MCP Apps do not assume entitlement to a host-specific
payment sheet or embed a generic third-party checkout.

Preparation returns `paymentToken` with a separate seven-day `statusExpiresAt`.
It is status-only: no account access, delivery changes or new payments. Its
domain-separated secret is recoverable from the cart's durable receipt, while
only the digest is stored. If preparation's response is lost, `get_cart` returns
a safe `checkout` receipt with attempt ID, status token and expiry. The client
must fetch `get_checkout_status`, not start another attempt. Status survives
24-hour cart cleanup.

Status reports payment state, order not-created/saved/save-pending, amount,
currency, expiry and check time. An order reference appears only after a save.
Status can recover an already verified order write, so it is not purely read-only.
A row-locked lease bounds parallel provider reconciliation. Provider link
reference, full amount, INR, order ID and captured payment identity are checked
through authenticated reads; a webhook's `paid` field alone is never sufficient.
See [reference lookup](https://razorpay.com/docs/api/payments/payment-links/fetch-all-standard),
[link fetch](https://razorpay.com/docs/api/payments/payment-links/fetch-id-standard)
and [order payments](https://razorpay.com/docs/api/orders/fetch-payments).

The shared `PaymentService::isCapturedRazorpayPayment` predicate rejects
authorized-only, wrong-currency, wrong-amount and mismatched-ID responses.
`VerifiedOrderService` preserves the verified total, passes the actual discount
to tax, and uses the database's idempotent order/invoice boundary. Complete
server-frozen GST/HSN/unit-price/line fields plus all eight supplier/default settings
remain usable after product deletion; fulfillment still requires review.
Incomplete/invalid tax evidence records the payment with tax review required,
without inventing an invoice. Shared persistence never changes `$_SESSION`.
The browser wrapper clears only its matching pending order and matching cart.

Configured GST reporting unit and per-item quantity are also captured in the
private quote and its digest. Issued lines use the confirmed values even if the
catalogue changes before the verified callback. A legacy quote may lack these
optional fields. See [GST reconciliation](modules/gst-commerce.md).

Native Support saves one versioned `sessionStorage` recovery snapshot containing
only current/Buy-now cart tokens, revisions and expiries, plus status token,
expiry and checkout kind. It saves no delivery, contact, coupon or quote draft.
Restore calls `get_cart`/`get_checkout_status` before displaying facts. Clear
and expiry remove handles; storage denial keeps an honest memory-only flow.
BFCache cancellation clears form personal data before restoring handles. MCP
Apps keep widget capabilities private; structured status continuity never
substitutes an old paid claim for a fresh server read.

## Signed completion and owner setup

Configure the mode-specific Razorpay credentials and matching checkout webhook
secret in owner Integrations. Availability and execution require the same
normalized selected-mode key pair and a webhook secret. `SecretService`
normalizes legacy keys. Provider authentication/eligibility failures produce
fixed safe notices, never raw provider bodies or private fields.

Configure the matching test/live webhook at
`https://YOUR-OFFICIAL-HOST/api/payments/razorpay-webhook` for
`payment_link.paid`, `payment_link.partially_paid`, `payment_link.expired` and
`payment_link.cancelled`. The exact route skips browser sessions and verifies
HMAC-SHA256 over the bounded raw body before JSON parsing. Do not reformat the
signed body; preserve secret-rotation continuity for outstanding events.
Duplicate/out-of-order events converge through provider verification and shared
order persistence. Transient verification/save failures return retryable failure.
See [signature/replay guidance](https://razorpay.com/docs/webhooks/validate-test)
and [Payment Link events](https://razorpay.com/docs/webhooks/payment-links).

Signed callbacks complete verified payments even when chat closes. For missed
events or interrupted saves, schedule `php cli/reconcile-checkouts.php --limit=20`
(for example, once per minute). It only checks existing attempts, never creates
another payment, and prints aggregate checked/saved counts. Investigate unresolved
attempts and account/credential changes before requesting another payment.

## Separate website checkout fallback

The internal `RemoteCartService::prepareCheckout` accepts `cartToken` and
`expectedRevision`; it is not another generic public MCP operation. A nonempty,
currently available cart returns a `checkoutUrl`, `expiresAt`, `cartRevision`
and current cart result. The URL contains a separate ticket, derived from the
cart secret and a frozen revision/expiry, and valid for at most 15 minutes.
The persistent cart token is not present in it.

Repeated preparation for the same revision reuses an unexpired receipt. An
expired receipt may be renewed within the original cart lifetime, so this tool
does not advertise unconditional idempotence. Any actual quantity change
invalidates the pending ticket.

`GET /cart/agent-checkout?ticket=…` renders current selections and prices.
It does not claim the ticket or change the browser cart. The page uses only
same-origin styles/images, with no analytics or scripts, `no-store`,
`no-referrer` and a restrictive Content Security Policy. Prices and availability
are checked again before import. A changed quote requires another review.

The shopper must check the replacement checkbox and submit the CSRF-protected
`POST /cart/agent-checkout`. This explicitly replaces the browser's cart and
redirects to the existing `/checkout` delivery/payment flow. The database
claim is bound to a random server-session secret, not a client-supplied account
identifier. Another browser cannot claim the same used ticket.

The cart becomes `handed_off` and remains readable, but further remote mutations
are rejected. A same-browser retry can recover a successful database claim
whose session save was interrupted. Once the session import receipt exists,
repeating the POST preserves any later browser cart edits. Session receipts
are retained until ticket expiry and bounded to 100 live receipts; the server
does not evict a live replay guard to accept another import.

The review link is a short-lived bearer capability: share it only with the
shopper. The normal checkout still requires delivery details and payment
confirmation. Preparing or importing a cart creates no order or payment.

## Persistence and resource limits

`agent_carts` and `checkout_attempts` are canonical MySQL collections excluded
from administrator resource editing and agent context exports.
`DatabaseService::remoteCartOperation` and `remoteCheckoutOperation` are fixed
mutation protocols. Direct calls hold the row transaction on
MySQL; the authenticated `/remotedb` action executes the same operation on the
database host. Neither transport replaces the full collection. Safe conflict,
expiry and rate-limit codes/status/retry timing survive the internal bridge.

Creation uses a database-scoped lock, a non-exported digest of the server peer
address, and limits of 10 new carts per minute and 20 per hour for that bucket.
Client-supplied forwarding headers do not change the bucket. At most 10,000
unexpired cart records are admitted. Each creation attempt removes at most
100 expired records; no background scheduler is required. Resource or rate
limits stop creation and provide retry guidance. Reuse an existing cart or
wait; do not switch transports to bypass the limit.

Checkout creation has a separate 20,000-record ceiling and removes at most 100
terminal receipts older than seven days. Unresolved attempts survive cart
cleanup and are not deleted merely because a status or payment link expires.
Capacity failure rolls back both attempt and cart lock. Draft details expire
with their 24-hour cart; completed attempts discard the duplicate delivery
snapshot once the paid order is canonical.

## Repeatable checks

- `php tests/remote-cart.php` exercises public projection, paise/markup parity,
  cross-cart rejection, visibility/offers, quantity limits, revision replay,
  ticket expiry/renewal and claim isolation using synthetic in-memory state.
- `php tests/remote-checkout.php` exercises actual checkout transitions,
  canonical coupon/tax math, quote expiry, confirmation, provider minimum,
  uncertainty, captured verification, signed completion, replay and PII exclusion
  with a deterministic fake gateway.
- `php tests/verified-order.php` checks shared captured verification,
  discounted/frozen tax evidence, idempotent persistence and session isolation.
- `php tests/remote-checkout-mysql.php` uses the same disposable loopback database
  to test atomic confirmation/reconciliation, the real HTTP bridge, capacity
  rollback, unresolved retention, native routes and synthetic verified order saves.
- `php tests/remote-cart-mysql.php` uses only disposable loopback MySQL at
  `127.0.0.1:33068`, database `sps_order_test`. It tests actual concurrent row
  operations, the authenticated HTTP bridge, creation limits and bounded
  cleanup, plus the real review route's GET, CSRF, explicit import, replay and
  interrupted-save recovery. It creates no paid order or real customer record.
- `node --test tests/remote-cart-review-browser.cjs` uses the existing external
  Playwright runtime to check six PHP-rendered states at 1280px and 375px,
  including native checkbox validation and keyboard submission without
  JavaScript. The browser fixture does not submit a production cart or payment.

Pure state-machine checks do not establish MySQL concurrency, and a template
fixture does not establish deployed payment behavior. Use the native database,
HTTP and browser gates for their respective boundaries.

Native HTTP, official MCP SDK/AppBridge and Support browser fixtures exercise
the six tools, inline form and recovery separately. QA must use synthetic
delivery and injected provider responses, never a real live payment, order,
contact message or reservation merely to test a flow.

After the deployed Git SHA is verified, `tests/live-agent-cart.cjs` uses actual
curl calls without a browser or login. It checks the payment routes reject
missing method/capability, creates one anonymous cart, sets one available product
to quantity one, reads its current amount, then removes the item and verifies
the cart is empty. The empty cart expires normally. It never submits delivery
details or invokes payment preparation, status reconciliation or order creation.
The Hostinger acceptance workflow runs this bounded probe alongside the public
read-only discovery and browser checks; a failure is not automatically replayed.

Authored campaign bookings share the Razorpay link verification and the
`/api/payments/razorpay-webhook` endpoint. A `book_` reference settles a
revision-bound booking receipt and its selected download versions in
`campaign_bookings`. Product checkout references continue to use
`checkout_attempts` and the shared verified-order writer.
See [Authored pages](modules/content-and-seo.md#authored-pages).
