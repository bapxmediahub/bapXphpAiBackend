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

## OpenRouter Free optional provider

The existing AI Provider form contains a separate **Enable OpenRouter Free** toggle and
a masked OpenRouter API Key directly below Google API Key. The model ID and endpoint
are fixed to `openrouter/free` and `https://openrouter.ai/api/v1`; the owner
does not supply another URL. This option is **off by default** and leaves the
ordinary Google/OpenAI-compatible provider selection untouched when off.

Enabling requires a saved OpenRouter key. An empty masked input preserves an existing
key, and turning the option off retains it encrypted for a future opt-in. Both
Support and Enterprise try OpenRouter first. If an OpenRouter model cannot
answer **before any permitted tool call executes**, the existing Google
`gemma-4-31b-it` and `gemma-4-26b-a4b-it` routes are tried within the
original generation deadline, using the separately stored Google key. A completed
tool exchange is never replayed to a different provider. A missing Google key
cannot create a real fallback; failed attempts remain visible through the
existing safe timing/model diagnostics in Test AI Connection.

The same encrypted `app_secrets` row in hosted MySQL remains authoritative.
The page never returns a saved OpenRouter key in a password input, code, logs,
tests, A2UI or chat history. No key is supplied in sample documentation or CI
fixtures. Reference: https://openrouter.ai/openrouter/free .

## Secrets Storage

- Secrets are encrypted and stored in the remote MySQL `secrets` collection through Admin → Integrations.
- Local JSON files are not runtime secret storage. They may only be used for an explicitly requested one-time import.
- Never put secrets in committed files. Hosting environment variables are emergency fallback inputs for critical integrations only.

## Editable Fields

| Field | Environment variable (fallback) |
|---|---|
| Razorpay Mode, Test ID/Secret, Live ID/Secret | `RAZORPAY_MODE`, `RAZORPAY_TEST_KEY_ID`, etc. |
| Test checkout webhook secret / Live checkout webhook secret | `RAZORPAY_TEST_WEBHOOK_SECRET`, `RAZORPAY_LIVE_WEBHOOK_SECRET` |
| Stripe Secret Key | `STRIPE_SECRET_KEY` |
| Google Client ID / Secret | — |
| Meta Pixel ID | `META_PIXEL_ID` |
| Google Analytics ID | `GOOGLE_ANALYTICS_ID` |
| Google Ads ID | `GOOGLE_ADS_ID` |
| Google Site Verification | `GOOGLE_SITE_VERIFICATION` |
| Support Bot API Key / Model | — |
| SMTP Host, Port, Encryption, Username, Password | — |

Key checks: API setup links are visible, secrets are stored outside normal catalog JSON, and Google Calendar/Meet is not requested.

## Chat checkout completion

The Razorpay section has separate test and live checkout webhook secrets. Choose
the intended payment mode and configure that mode's credentials and matching
webhook at the official site's `/api/payments/razorpay-webhook` endpoint. The
supported Payment Link events are `payment_link.paid`,
`payment_link.partially_paid`, `payment_link.expired` and `payment_link.cancelled`.
An owner-authorized provider setup is required; saving these fields does not
configure the Razorpay dashboard or prove a completed payment.

Chat payment remains unavailable while the selected mode lacks its key pair or
webhook secret. Cart and checkout review can still be inspected. Completion uses
the verified captured amount/currency and a durable attempt; a signed notification
alone is not treated as payment proof. The hosting operator can schedule the
bounded reconciliation command documented in [remote commerce](../remote-commerce.md)
to recover missed callbacks. Do not submit a real purchase as a configuration test.

## Google tags versus connected reporting

The public tag renderer and reporting client serve independent purposes.
`GoogleSiteKitClient::status()` classifies saved Analytics, Ads and Search
Console tag values as missing, invalid or configured. The dashboard obtains
reporting authorization and metrics from `GoogleReportingClient`.

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

Use Tag Assistant to verify collection and Search Console to verify site ownership.

## Owner reporting

Open **Google reporting** from the dashboard or Integrations. In the Cloud
project that owns the configured web OAuth client, enable Analytics Admin API,
Analytics Data API and Search Console API. Add the displayed configured-origin
`/admin/google-reporting/callback` URI to the client. Configure the consent
screen and eligible users for the two read-only permissions. The owner signs
in to Google, grants offline access and selects a GA4 property and/or verified
Search Console site from Google's lists. Customer login uses its existing
callback and sign-in scopes. Merchant and YouTube account linking are separate.

The grant is encrypted in its own hosted MySQL secrets record. Reporting
refreshes access server-side. The dashboard requests 28 days through yesterday:
GA4 uses the property timezone, Search Console uses Pacific time and can lag
behind the current day. Search totals come from a dimensionless query, because
query-level rows omit some traffic. Disconnect revokes the grant before
clearing the local token. If Google is unavailable the owner can retry. Google
can revoke other grants for the same user and Cloud project when an
authorization is revoked. Use a separate project for unrelated applications.

References:

- https://developers.google.com/identity/protocols/oauth2/web-server
- https://developers.google.com/analytics/devguides/config/admin/v1/rest/v1beta/accountSummaries/list
- https://developers.google.com/analytics/devguides/reporting/data/v1/rest/v1beta/properties/batchRunReports
- https://developers.google.com/webmaster-tools/v1/api_reference_index
- https://developers.google.com/webmaster-tools/v1/how-tos/all-your-data
- https://developers.google.com/tag-platform/gtagjs/configure
- https://support.google.com/webmasters/answer/9008080

## Local MCP reporting clients

Open **Manage local MCP clients** from Integrations to issue or revoke a 30-day credential for paid-sales totals. The local client receives the credential through its process environment. The client access list shows expiry, revocation and last successful read. See the [local MCP client guide](shop.md#local-mcp-client).
