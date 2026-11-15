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
`media_files`. The pending customer correction PR #250 introduces guarded
media deletion; do not remove referenced or bundled images without the
reviewed workflow.

Reports derive paid revenue/refunds and GST totals from verified orders;
the zero-orders dashboard crash is addressed in draft PR #245. Do not merge
or claim deployment of draft-only fixes until browser and post-deployment
checks pass. Existing historical appointments remain available, but public
consultation booking is retired.
