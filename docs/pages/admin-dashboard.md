---
type: doc
title: Admin Dashboard
description: Route /admin - paid commerce overview, order and product metrics, and recent historical sessions.
category: page
---

# Admin Dashboard

Route: `GET /admin`; controller `AdminController@dashboard`; view
`views/admin/dashboard.php` with `views/admin/_commerce-overview.php`.

The authenticated owner sees product/order counts, historical consultation
sessions, paid-order revenue, refunds, product ranking and order statuses for
the selected date period. `StoreStatsService` calculates commerce metrics
from actual MySQL-backed orders; these are not estimates or local JSON
resources. `SiteInsightsService` adds content-quality checks for product/blog
metadata plus new account and unresolved-ticket counts.

The reports are independent of the **Agent Monitoring** page at
`/admin/agent/monitoring`, which shows metadata-only operational telemetry,
private JSON-chat session summaries, explicit support resolution feedback and
a deterministic golden-fixture score. Neither screen can infer search rankings
or customer satisfaction from raw request volume alone.

Regression checks: an unauthenticated viewer redirects to login; no paid
orders produces a safe zero-valued empty state; nonempty figures reconcile with
`StoreStatsService`; mobile 375px tables stay usable. The empty-series chart
fix is independently reviewed in PR #245 until merged.
