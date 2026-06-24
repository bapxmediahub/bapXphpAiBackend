# Razorpay Module

Owns payment order creation and signature verification.

Main files: `RazorpayClient.php`, `PaymentService.php`, `CommerceController.php`, `views/public/checkout.php`.

Checkout endpoints: `/checkout/create-order` and `/payment/verify`, with `/api/create-order` and `/api/verify-payment` available as JSON API aliases.

Key checks: missing keys block checkout clearly; keys may come from Admin Integrations or `RAZORPAY_KEY_ID` / `RAZORPAY_KEY_SECRET` hosting environment variables. The browser receives only the key id. The key secret stays server-side for order creation and HMAC-SHA256 signature verification.
