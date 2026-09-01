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

Key checks: name, email, phone, address, city, and PIN are retained through payment verification; missing gateway configuration is shown clearly instead of silently failing. Razorpay verifies the returned payment id, order id, signature, fetched amount, and gateway order before saving. Stripe returns through `/payment/stripe/return`, where the server retrieves the Checkout Session and verifies paid status, INR currency, amount, and the session held for that customer. Both gateways share one idempotent order-persistence boundary and land on the owned order detail with the payment-success state. Failed or cancelled payment keeps the cart available for retry.
