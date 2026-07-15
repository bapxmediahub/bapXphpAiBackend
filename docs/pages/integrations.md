---
type: doc
title: Integrations
description: Route /admin/integrations - configure Razorpay, Stripe, Google OAuth, SMTP, Meta Pixel, Google Site Kit, Support Bot, and SEO defaults.
category: page
---
# Admin Integrations

Route: `/admin/integrations`

Controller: `AdminController@integrations`

Purpose: configure Razorpay (test/live), Stripe, Google OAuth, SMTP, Meta Pixel, Google Site Kit, Support Bot, and SEO defaults.

## Secrets Storage

- Secrets are encrypted and stored in the remote MySQL `secrets` collection through Admin → Integrations.
- Local JSON files are not runtime secret storage. They may only be used for an explicitly requested one-time import.
- Never put secrets in committed files. Hosting environment variables are emergency fallback inputs for critical integrations only.

## Editable Fields

| Field | Environment variable (fallback) |
|---|---|
| Razorpay Mode, Test ID/Secret, Live ID/Secret | `RAZORPAY_MODE`, `RAZORPAY_TEST_KEY_ID`, etc. |
| Stripe Secret Key | `STRIPE_SECRET_KEY` |
| Google Client ID / Secret | — |
| Meta Pixel ID | `META_PIXEL_ID` |
| Google Analytics ID | `GOOGLE_ANALYTICS_ID` |
| Google Ads ID | `GOOGLE_ADS_ID` |
| Google Site Verification | `GOOGLE_SITE_VERIFICATION` |
| Support Bot API Key / Model | — |
| SMTP Host, Port, Encryption, Username, Password | — |

Key checks: API setup links are visible, secrets are stored outside normal catalog JSON, and Google Calendar/Meet is not requested.
