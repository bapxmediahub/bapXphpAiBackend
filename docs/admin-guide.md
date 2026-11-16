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

## Google status is not a reporting connection

The Web analytics dashboard section now separates local tag configuration from
unverified collection, conversion tracking and Search Console ownership. Follow
**Configure Google tags** to the actual Integrations section; use the external
Google validation links for evidence. Reporting OAuth/property data is not yet
connected, so no metrics are shown as zero or fabricated from tag IDs.

## Email templates and distinct recipient roles

Email → Templates exposes seven controlled previews and one-shot, explicitly
confirmed diagnostic sends. Customer and owner audiences cannot substitute for
one another; only the owner template reads the configured owner recipient.
Preview is read-only and synthetic, while test sending is a separate protected
POST that records a normal queue entry. Inbox confirmation is external evidence,
not a green transport label. See `email-setup.md` and the Email operator guide.
