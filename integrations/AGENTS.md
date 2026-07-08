# Integrations DOX

## Purpose

Owns third-party client wrappers for payment, OAuth, and future external services.

## Ownership

- `google-oauth/`: Google OAuth client code.
- `razorpay/`: Razorpay client code.
- `stripe/`: Stripe payment client code (available as Razorpay alternative, not yet wired into runtime).
- `meta-pixel/`: Meta/Facebook Pixel client code.
- `google-site-kit/`: Google Site Kit (Analytics, Ads, Search Console) client code.

## Local Contracts

- Keep secrets out of public views and route them through existing secret/environment services.
- Integration clients should stay small wrappers consumed by services/controllers.

## Work Guidance

- Do not hardcode production credentials.
- Preserve shared-hosting compatibility.
- All integration secrets (Razorpay test/live keys, Stripe secret key, Google OAuth, Meta Pixel, Google Site Kit, Support Bot API key/model, SEO defaults, and SMTP) are stored encrypted in `settings.secrets.json` and edited through **Admin → Integrations** (`/admin/integrations`). Keep secrets out of `.env`.
- Every secret key exposed by `SecretService` must have a corresponding editable field in `views/admin/integrations.php`; saving the form persists all secrets to the encrypted store via `SecretService::save()`. This repo is a flat-file JSON backend, so secrets are first-class admin-managed data, not environment config.

## Verification

- `php -l integrations/path/to/changed.php`
- `php tests/run.php`
- `php tools/smoke-local.php` when route behavior changes.

## Child DOX Index

