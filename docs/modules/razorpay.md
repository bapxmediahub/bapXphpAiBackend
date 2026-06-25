# Razorpay Module

Owns payment order creation and signature verification.

Main files: `RazorpayClient.php`, `PaymentService.php`, `CommerceController.php`, `views/public/checkout.php`.

Checkout endpoints: `/checkout/create-order` and `/payment/verify`, with `/api/create-order` and `/api/verify-payment` available as JSON API aliases.

Key checks: missing keys block checkout clearly. Admin Integrations stores `razorpay_mode`, test keys, and live keys separately, then exposes the selected mode as the active key pair for checkout and wallet recharge. Hosting environment variables can also provide `RAZORPAY_MODE`, `RAZORPAY_TEST_KEY_ID`, `RAZORPAY_TEST_KEY_SECRET`, `RAZORPAY_LIVE_KEY_ID`, and `RAZORPAY_LIVE_KEY_SECRET`; legacy `RAZORPAY_KEY_ID` / `RAZORPAY_KEY_SECRET` are still accepted and mapped into the inferred active mode. The browser receives only the active key id. The key secret stays server-side for order creation and HMAC-SHA256 signature verification.
