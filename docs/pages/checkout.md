---
type: doc
title: Checkout Page
description: Route /checkout - collect shipping contact details and start a configured Razorpay or Stripe checkout.
category: page
---
# Checkout Page

Route: `/checkout`

Controller: `PublicController@checkout`

Purpose: collect shipping contact details and start Razorpay or Stripe checkout when that gateway is configured.

Key checks: name, email, phone, address, city, state/Union Territory, and PIN are retained through payment verification; the state is selected from the Indian state master before a gateway opens. Missing gateway configuration is shown clearly instead of silently failing. Razorpay verifies the returned payment id, order id, signature, captured status, INR currency and exact integer amount before saving. Stripe returns through `/payment/stripe/return`, where the server retrieves the Checkout Session and verifies paid status, INR currency, amount, and the session held for that customer. Both gateways share `VerifiedOrderService` and land on the owned order detail with the payment-success state. Verification failures retain the pending payment and permit a status/retry action; an uncertain charge must not silently ask the shopper to pay again. Browser cleanup only clears its matching pending order and matching cart.

An external assistant can create an anonymous cart, submit delivery/coupon
details, review the authoritative quote, explicitly confirm payment and retrieve
verified status through six public HTTP MCP tools. Native Support and compatible
MCP Apps share the fixed inline checkout form. Delivery/review stay in chat;
provider-owned payment uses the separately chosen merchant continuation at
`/checkout/payment`. No generic provider iframe or assumed host payment-sheet
entitlement is embedded. Signed callbacks and bounded reconciliation finish
confirmed payments even after the browser closes.

The internal website fallback's `/cart/agent-checkout` page shows current public
selections and an explicit replacement checkbox. GET changes no browser cart;
the shopper's CSRF-protected POST imports those lines and redirects here.
The review page creates no order or payment and never receives the persistent
cart token. See [anonymous carts and checkout in chat](../remote-commerce.md).

The browser cart resolver retains decimal INR amounts and shares the current
product/offer policy with remote estimates. Displayed lines use those resolved
amounts; both server and checkout gateway payloads round to integer paise rather
than truncating floating-point multiplication.
