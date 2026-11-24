## Choose the correct settings screen

Site Settings controls the supported public business/site configuration. Integrations contains provider credentials and payment, mail, login and AI connection settings. Logo & Favicon manages identity images; Temples manages the supported temple records. These are different responsibilities.

## Update the logo or favicon

Use **Logo & Favicon** to upload a PNG or JPEG logo up to 512 × 512 pixels and 100 KiB, or a PNG favicon up to 64 × 64 pixels and 50 KiB. Convert SVG or ICO files to PNG before uploading. After saving, reload the page and check the previews and public header. New images are available in Media Library under Shared. If saving fails, reload to verify the active images before retrying.

## Before changing a connection

Confirm who authorized the change and which environment it affects. A live payment mode is not a test environment. Never submit a real payment simply to test a configuration. Preserve a verified recovery route for administrator access and do not overwrite keys accidentally. Do not share credentials in help, screenshots or chat.

## Save and verify only the intended behavior

Use the screen's explicit save action, then test the affected feature with approved fixtures or read-only checks. A saved API key does not prove provider entitlement, email inbox delivery or model quality. Module visibility changes can affect both navigation and direct routes; check the actual customer flow.

If a change fails, record the safe error and exact section for the developer team rather than repeatedly submitting or bypassing checks. These guides do not expose deployment files, private agent instructions or an unrestricted database tool.

## Check System Health before hosting changes

Open **System Health** in Settings to see the actual web PHP version, identified database engine/version and required extensions. The native platform target is stable PHP 8.3 or newer and MySQL 8.4 or newer. A higher MariaDB number does not make it MySQL 8.4.

Use **Refresh checks** to read the latest observation. The screen does not install anything or change your data. An unknown/unavailable result is not a pass, and an unmet target does not mean the store is necessarily offline. Ask the hosting/development team to verify the finding before changing a live connection.

Database changes require an approved destination, backup, restore and compatibility tests plus a rollback plan. Do not replace a working database or replay transactions simply to make a health label green. Command-line PHP can differ from the PHP serving the website; check this page after deployment.

## Read Google configuration accurately

The dashboard's **Google tags & reporting** section and Integrations' **Google
tags — configuration only** section show each field separately. **Valid format —
configured** does not mean Google received data or verified ownership.
Choose **Set up Google reporting** on the dashboard. Save the web OAuth client
under Integrations and register the callback shown on the reporting page.
Enable the Analytics Admin, Analytics Data and Search Console APIs in Google
Cloud. Connect an account with property access and select your GA4 property
and Search Console site. The dashboard shows sessions, top pages, sources,
clicks, impressions and top queries for the last 28 days through yesterday.
Use **Manage Google reporting** to change properties or disconnect.

Use a real G- measurement ID (or an existing GT- tag ID), an AW- numeric Ads ID,
and only the content token from Search Console's verification meta tag. Do not
paste scripts, a GTM container or the full DNS assignment into these fields.
Invalid saved values are reported and not rendered; the developer does not
silently replace them. Check actual collection with Tag Assistant and confirm
ownership in Search Console. Use **Google & YouTube** for the separate catalogue
feed and channel-readiness steps.


## Change the public header links

Open **Header menu** in the owner sidebar. Choose the pages in the order you
want them to appear and leave unused positions empty. Select **Save header
menu** and follow **View the public header** to check it. Published pages from the Pages editor are available by title. Withheld or missing pages disappear from the public menu. The Blog link keeps
its category dropdown. Shop and Blog become visible only when their modules
are enabled. The mobile shortcut bar and account links have separate roles.

## Configure chat checkout completion

In **Integrations → Razorpay**, select the intended test or live mode and use its
matching credentials and **Test checkout webhook secret** or **Live checkout
webhook secret**. The provider webhook points to the official site's
`/api/payments/razorpay-webhook` endpoint. Ask the authorized provider/hosting
operator to configure Payment Link paid, partially paid, expired and cancelled
events and the bounded recovery schedule. Saving these fields does not perform
that provider setup or prove payment works.

Customers can review their cart, delivery fields and totals inside Support or a
compatible connected chat. Payment may continue through an explicit merchant
page. Missing credentials or the matching webhook secret keep chat payment
unavailable. If a payment response is interrupted, check the existing payment
and order status before asking the customer to pay again. Do not submit a real
payment solely to test these settings.

## Authorize a local reporting client

Open **Integrations → Manage local MCP clients**. Authorize a named client to read paid-sales totals for 7, 30 or 90 days. Copy the credential from its single display into the trusted client’s secret manager as `BAPX_MCP_CLIENT_TOKEN` for `bapXphp mcp:stdio`. Grants expire after 30 days. Return to this page to check the last successful read or revoke access.
