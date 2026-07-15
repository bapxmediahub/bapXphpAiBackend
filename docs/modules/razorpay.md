---
type: doc
title: Razorpay Module
description: Owns payment order creation and signature verification.
category: module
---
# Razorpay Module

Owns payment order creation and signature verification.

Main files: `RazorpayClient.php`, `PaymentService.php`, `CommerceController.php`, `views/public/checkout.php`.

Checkout endpoints: `/checkout/create-order` and `/payment/verify`, with `/api/create-order` and `/api/verify-payment` available as JSON API aliases.

Key checks: missing keys block checkout clearly. Admin Integrations stores `razorpay_mode`, test keys, and live keys separately in the remote MySQL `secrets` table, then exposes the selected mode as the active key pair for product checkout. System environment variables remain a hosting fallback for critical credentials. The browser receives only the active key id. The key secret stays server-side for order creation and HMAC-SHA256 signature verification.
