---
type: doc
title: Admin Guide
description: Verified operations for the owner dashboard, business tools, agents, monitoring and content.
category: docs
---

# Admin Guide

Admin access is authenticated by `AuthService`. The dashboard manages
products, categories, collections, coupons, orders, shipping, retained
consultation records, support tickets, templates, media, blog posts,
integrations and audit activity.

The dashboard shows the five latest orders in the selected period. Gross paid sales use recorded payments. Pending payment value counts outstanding pending orders separately. Each recent order opens its owner detail page.

## Two AI agent surfaces

- `/admin/agent`: read-only Enterprise Agent chat with human-approved edit
  drafts, **New chat** and **History**. Session restoration is actor-scoped; one
  chat corresponds to one JSON file.
- `/admin/agent/monitoring`: operational runs from MySQL `audit_events`
  plus summary metadata from the private session JSON files. Admin exports
  require authentication; do not serve session JSON directly.
- Public Support Agent: no privileged admin capabilities, may read public
  products/policies and the signed-in customer's own ticket status. Customer
  feedback marks a reply confirmed-resolved or needs-follow-up; silence does
  not count as a confirmed resolution.
- `/admin/support-tickets`: human review queue. Ticket status is an independent
  business record in MySQL, not a substitute for a chat-resolution metric.

Quality metrics distinguish offline deterministic regression fixtures from
live customer outcomes. An agent HTTP 200 or a nonempty answer does not prove
factual accuracy. See [agent observability](agent-observability.md).

In Enterprise Agent, use the paperclip to upload and attach an image while composing a message. To attach an existing page, article or image, type `@`, use the arrow keys to select a suggestion, and press Enter. Escape closes the suggestions.

## Other workflows

Media uploads live outside Git releases; their metadata is in MySQL
`media_files`. The Media Library permits guarded deletion of unused uploads
with confirmation and CSRF protection. Referenced, bundled and legacy-only
images are not deletable through that action.

Reports derive paid revenue/refunds and GST totals from verified orders;
empty report periods render safe zero-data states. Inspect actual deployed
behavior after release, not only CI. Historical appointments remain available,
but public consultation booking is retired.

## Help inside the owner workspace

Open **Help & Docs** from the permanent sidebar or topbar entry. Search task
words, select a category, open a guide, and use its links to return to the real
workspace. The expandable **Help for this page** section on admin screens
points to the relevant task guide. Search and guide navigation work without
JavaScript; the topbar link remains available on mobile.

Operator instructions are a fixed, authenticated collection, not a browser
for developer files or private runtime-agent prompts. Customers instead use
published Help blog articles linked from shop, cart, checkout, account and
Support. See `admin-help.md` for content ownership, privacy and source-review
checks. A guide describes a control; it never performs a business action.

## Native system diagnostics

Settings → System Health is a read-only authenticated view of the serving PHP
process, required extensions and configured database engine/version. It does
not show a public phpinfo dump or mutate hosting settings. See
`runtime-requirements.md` and the Settings operator guide before any change.

## Google catalogue readiness

Commerce → Google & YouTube reads the existing catalogue, shows basic feed
inclusions/exclusions and gives the XML URL for Merchant Center scheduled fetch.
Use its correction link to select the existing product in the normal editor;
opening the link does not save or duplicate a product. The readiness page is
read-only and works without JavaScript, while the existing product editor uses
JavaScript to prefill its form. Google account approval and YouTube eligibility
remain separate, unverified external steps. See `google-sales-channels.md`.

## Google reporting

On the dashboard, open **Set up Google reporting**. Register the displayed callback
on the Google web OAuth client, enable the Analytics Admin, Analytics Data and
Search Console APIs, and consent with an account that can read the desired
properties. Select the GA4 property and Search Console site from Google's lists.
The dashboard shows the previous 28 complete calendar days. Empty results,
quota limits and access problems appear beside the affected service. Manage
the connection or revoke its grant from **Manage Google reporting**. Public tag
installation and verification are separate settings under Integrations.

## Email templates and distinct recipient roles

Email → Templates exposes seven controlled previews and one-shot, explicitly
confirmed diagnostic sends. Customer and owner audiences cannot substitute for
one another; only the owner template reads the configured owner recipient.
Preview is read-only and synthetic, while test sending is a separate protected
POST that records a normal queue entry. Inbox confirmation is external evidence,
not a green transport label. See `email-setup.md` and the Email operator guide.

Agent Monitoring's **Review answers** links open a selected-turn assessment form.
Use recorded evidence, choose not-assessed for unavailable facts, declare
human/assisted/test authorship truthfully and keep rationale free of secrets.
The appended review is not a customer satisfaction vote or a ticket transition.
The linked operator monitoring guide explains conflicts and review history.


## Public header menu

In Appearance, choose **Header menu**. Select up to six first-party destinations
in reading order. Published owner-authored pages appear by title. Renaming a page updates its link label. Returning it to draft or deleting it hides the link. A published page created later at the same address uses that menu position. A blank position omits the link, and each page can appear
once. Save, then follow **View the public header**. Existing installations
use Shop, Temples, Blog, Search, About SPS and Contact until their first save.
Shop and Blog follow their site module settings. Blog retains its category
dropdown. Header editing leaves the home logo, account actions, footer and
mobile shortcut bar under their existing controls. Another editor's save
requires a fresh form before further changes.
