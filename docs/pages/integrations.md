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

## AI provider connections and model order

The owner can independently enable Google Gemini, OpenRouter and one custom
OpenAI-compatible HTTPS connection. Each connection has a masked API-key field
and ordered model IDs; Google also has per-model enable switches. The default
Google IDs remain Gemma 4 31B and 26B A4B; the default OpenRouter ID remains
openrouter/free. Editing the list adds model IDs rather than modifying PHP.
The preferred enabled connection runs first, then the other enabled connection,
then OpenRouter as the final fallback. The default Google-first order is
Gemma 31B, Gemma 26B A4B, then OpenRouter Free. Each model gets up to three
recoverable inference attempts before moving on, then a second cycle returns
to the first eligible model if the shared deadline permits. Disabled or unconfigured connections are never
used as hidden fallbacks. A named model test does not fall back to another
model. OpenRouter IDs other than free models can have provider charges.

API-key inputs stay blank and show only a short saved-key suffix outside the field. Blank replacement inputs preserve saved keys, and masked values are rejected. OpenRouter chat needs a standard inference key, not a management key. A changed compatible endpoint needs
its own key. The existing encrypted app_secrets MySQL record remains the only
runtime credential store. No secret values appear in the AI fields, diagnostics
or test fixtures. The shared request deadline applies across providers; once
a tool exchange starts, it never switches providers. The Google wire adapter
uses parametersJsonSchema for canonical JSON Schema, retaining server-side
authorization and validation.

Provider references: [Google REST function declarations](https://ai.google.dev/api/generate-content#FunctionDeclaration),
[Google retry strategy](https://ai.google.dev/gemini-api/docs/troubleshooting#retry-strategy),
[OpenAI SDK retries](https://github.com/openai/openai-python#retries),
[Vercel AI SDK settings](https://ai-sdk.dev/docs/ai-sdk-core/settings),
[OpenRouter error handling](https://openrouter.ai/docs/api_reference/errors-and-debugging).
The native PHP client applies these patterns synchronously; there is no scheduled
fallback job. Three attempts per model, the connection order and total budgets
are product choices, not mandates from the providers.

## Verify an actual provider response

The connection diagnostic uses the same guarded AiClient as both agents and
allows an exact provider/model target (even when two connections have the same
model ID) or the enabled fallback route. The text test requires the expected
response; the tool test calls an isolated read-only synthetic tool and verifies
the returned challenge. No order, customer or product data is sent by this
diagnostic. It records the actual attempted provider, model and safe status
without displaying keys or provider response bodies. Run the same tests locally
with bapXphp ai:probe text or bapXphp ai:probe tools, optionally followed by a
quoted provider|model selector. A missing tool call, unexpected answer, HTTP
error or timeout is not success.

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

## Built-in Google PageSpeed Insights

From the existing owner dashboard, open **PageSpeed Insights** at
`/admin/google-pagespeed`. The native view uses the site's configured HTTPS
origin and retrieves mobile/desktop results from Google's **fixed**
PageSpeed Insights v5 API endpoint only after the authenticated owner presses
**Analyze**. The POST is CSRF-protected. No WordPress plugin, additional Google
OAuth consent, browser API key or arbitrary target URL is involved.

Results contain the actual provider performance score, Lighthouse lab measurements,
available CrUX field data and up to four measured opportunities. If Google supplies
no score, insufficient field data, denies access or exhausts quota, the screen says
so instead of displaying fabricated zero metrics. A ten-minute PHP session cooldown
prevents repeated costly requests, and the last report is retained for up to
30 minutes in that owner's session; no database record or customer data is written.

The source analysis was derived from `google-site-kit.1.192.0.zip` and is
implemented independently in `PageSpeedInsightsClient` and
`AdminPageSpeedController`. Site Kit's other modules (AdSense, Tag Manager,
Ads, consent and email reports) are **not** implicitly implemented.
See `docs/research/native-wordpress-woocommerce-sitekit-20261124.md` and
[PageSpeed Insights API](https://developers.google.com/resources/api-libraries/documentation/pagespeedonline/v5/csharp/latest/classGoogle_1_1Apis_1_1PagespeedInsights_1_1v5_1_1PagespeedapiResource_1_1RunpagespeedRequest.html).

## Local MCP reporting clients

Open **Manage local MCP clients** from Integrations to issue or revoke a 30-day credential for paid-sales totals. The local client receives the credential through its process environment. The client access list shows expiry, revocation and last successful read. See the [local MCP client guide](shop.md#local-mcp-client).
