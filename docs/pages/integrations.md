---
type: doc
title: Integrations
description: Route /admin/integrations - configure Razorpay, Stripe, Google OAuth, SMTP, Meta Pixel, native Google tag settings, Support Bot, and SEO defaults.
category: page
---
# Admin Integrations

Route: `/admin/integrations`

Controller: `AdminController@integrations`

Purpose: configure Razorpay (test/live), Stripe, Google OAuth, SMTP, Meta Pixel, native Google tag settings, Support Bot, and SEO defaults.

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

## Google tags versus connected reporting

The native Google section places validated configured tags; it is not the
WordPress Site Kit plugin or an authorized reporting client. The dashboard
and Integrations use `GoogleSiteKitClient::status()` for independent Analytics,
Ads and Search Console configuration states: missing, invalid or configured.
None of these states is a receipt from Google. Collection, conversions and
ownership stay unverified, and reporting stays not connected.

Supported input contracts are a G- measurement ID or an existing GT- tag ID,
an AW- numeric conversion ID, and the URL-safe content token from Search
Console's meta tag. A GTM container or an Ads event-label path is a different
integration contract and is not accepted in these fields. Paste the verification
content value only, not an entire HTML tag or DNS `google-site-verification=`
assignment. Surrounding whitespace is ignored for rendering; saved records
are not rewritten. Invalid values are visible in the owner status and omitted
from the public head, not silently executed as JavaScript/HTML.

The public layout now calls the same client rather than maintaining a duplicate
inline renderer. Loader attributes and meta values are HTML-encoded; config
IDs are JSON-encoded. One safe loader preserves the existing valid G/GT and AW
configuration behavior. Google documents that existing config-based snippets
continue to work; this fix does not automatically install Tag Manager, add
tracking destinations or alter consent policy.

Use Tag Assistant and each actual Google product to verify collection or
ownership. Real GA4/Search Console reports still require a separate admin-only
OAuth consent/refresh/revocation flow, property/site selection and reporting API
calls. Customer Google sign-in remains limited to its own login purpose.
Merchant product feeds and YouTube eligibility/linking are separate controls.

References checked 16 November 2026:
https://developers.google.com/tag-platform/gtagjs/configure
https://developers.google.com/tag-platform/gtagjs/reference
https://support.google.com/webmasters/answer/9008080
