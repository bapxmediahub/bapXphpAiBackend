# Integrations DOX

## Purpose

Owns third-party client wrappers for payment, OAuth, and future external services.

## Ownership

- `google-oauth/`: Google OAuth client code.
- `razorpay/`: Razorpay client code.
- `meta-pixel/`: Meta/Facebook Pixel client code.
- `google-site-kit/`: Google Site Kit (Analytics, Ads, Search Console) client code.

## Local Contracts

- Keep secrets out of public views and route them through existing secret/environment services.
- Integration clients should stay small wrappers consumed by services/controllers.

## Work Guidance

- Do not hardcode production credentials.
- Preserve shared-hosting compatibility.

## Verification

- `php -l integrations/path/to/changed.php`
- `php tests/run.php`
- `php tools/smoke-local.php` when route behavior changes.

## Child DOX Index

