---
type: doc
title: Integrations
description: Configure payments, Google OAuth and site tags, SMTP, and the Chat Completions connections shared by Support and Enterprise.
category: page
---
# Admin Integrations

Route: `/admin/integrations`

Controller: `AdminController@integrations`

Use this page to configure Razorpay (test and live), Stripe, Google OAuth, SMTP,
Meta Pixel, Google site tags, and the AI connections for Support and Enterprise.

## AI provider connections and model order

The owner can independently enable Google Gemini, OpenRouter and one custom
OpenAI-compatible HTTPS connection. All enabled connections use
`POST {baseUrl}/chat/completions` with the selected connection's Bearer key.
The protocol is shared across providers and keeps the model choice in Admin.

| Connection | API base URL | Default models |
|---|---|---|
| Google Gemini API | `https://generativelanguage.googleapis.com/v1beta/openai` | `gemma-4-31b-it`, then `gemma-4-26b-a4b-it` |
| OpenRouter | `https://openrouter.ai/api/v1` | `openrouter/free` when enabled |
| Custom OpenAI-compatible connection | Owner-configured HTTPS URL | Owner-configured model IDs |

The model lists are editable, and Google also has per-model enable switches.
Choose Google or the custom connection as the preferred provider. The preferred
eligible connection runs first, followed by the other eligible connection and
then OpenRouter. Google is the default preference. OpenRouter starts disabled.
Specific OpenRouter model IDs may incur charges. The synchronous fallback loop
makes up to three attempts per model for a recoverable failure and can return to
the first eligible model for one more cycle within the shared deadline. After a
tool exchange starts, recovery retries the continuation on that connection. [Google compatibility](https://aistudio.google.com/docs/openai) ·
[Hosted Gemma model IDs](https://gemma.ai/deployment/gemini-api/#supported-models) ·
[OpenRouter Free](https://openrouter.ai/openrouter/free) ·
[OpenRouter error handling](https://openrouter.ai/docs/api_reference/errors-and-debugging).

API-key inputs stay blank. Saved-key status appears outside each field and may
include a short suffix. Leave an input blank to keep its saved key when the
endpoint stays the same. A changed custom endpoint needs its own key. Use a
standard OpenRouter inference key for its connection. The application rejects
masked values and stores credentials in the encrypted `app_secrets` MySQL
record. PHP validates model-selected tool arguments and authorization before
executing a call.

## Configure the optional custom connection

1. Under **AI Providers and Models**, select **Enable custom connection** in
   **Custom OpenAI-compatible connection**. To try it before Google, select
   **OpenAI-compatible API** as the preferred provider.
2. Enter the HTTPS API base URL, the API key issued for that endpoint, and one
   or more exact model IDs in fallback order. For direct OpenAI, use
   `https://api.openai.com/v1` and a Chat Completions model with function
   calling, such as `gpt-6-luna`. For direct OpenAI Luna tool requests, PHP
   sends the required `reasoning_effort: "none"`.
3. For endpoint-only routing, disable **Enable Google models** and
   **Enable OpenRouter Free**. Save the AI provider settings. Both agents then
   use the custom connection, including Enterprise draft generation.
4. Select the exact connection and model under **Test the AI connection**.
   Run **Text response** and **Tool round trip** before relying on that model
   for either agent.

Set **Chat Completions token limit field** to match the endpoint. **Automatic**
uses `max_completion_tokens` for direct OpenAI and `max_tokens` for existing
compatible connections. Choose `max_completion_tokens` explicitly for a custom
endpoint that expects the current field.
The endpoint must support Chat Completions function declarations and
multi-turn messages with `tool_call_id`.
[Chat Completions HTTP reference](https://developers.openai.com/api/reference/resources/chat/subresources/completions/methods/create) ·
[GPT-6 Luna capabilities](https://developers.openai.com/api/docs/models/gpt-6-luna).

## Verify an actual provider response

The connection diagnostic uses the same guarded AiClient as both agents and
allows an exact provider/model target (even when two connections have the same
model ID) or the enabled fallback route. The text test requires the expected
response; the tool test calls an isolated read-only synthetic tool and verifies
the returned challenge. No order, customer or product data is sent by this
diagnostic. It displays the attempted provider, model, and safe status. A 401
names the API key field for the rejected connection. A 403 points to that
connection's access settings. The browser result card omits endpoints, keys
and provider response bodies. [OpenAI API error codes](https://developers.openai.com/api/docs/guides/error-codes).

Run the same tests locally with `./bapXphp ai:probe text` or
`./bapXphp ai:probe tools`, optionally followed by a quoted `provider|model`
selector. Verify both modes separately for every enabled, configured target in
the selector, including `openrouter|openrouter/free` when configured.
Then exercise both agents with an authorized lookup on the deployed release.
Loopback tests verify request and continuation handling. Live probes establish
which exact models complete both modes with the deployment's provider access.

## Secrets Storage

- Secrets are encrypted and stored in the remote MySQL `secrets` collection through Admin → Integrations.
- Local JSON files are not runtime secret storage. They may only be used for an explicitly requested one-time import.
- Never put secrets in committed files. Hosting environment variables are emergency fallback inputs for critical integrations only.

## Other editable fields

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

Open **Google reporting** from Site analytics or Integrations. In the Cloud
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

From Site analytics, open **PageSpeed Insights** at
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
