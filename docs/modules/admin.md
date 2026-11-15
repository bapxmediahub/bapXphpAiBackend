---
type: doc
title: Admin Module
description: Owns owner-only pages under /admin.
category: module
---
# Admin Module

Owns owner-only pages under `/admin`.

Main files: `AdminController.php`, `views/admin/*`, `views/layouts/admin.php`.

Key checks: every admin route requires `AuthService`, settings persist real values, and business list/detail pages render MySQL records; chat history remains private per-conversation JSON.

## Owner AI agents

`/admin/agent` hosts a role-protected Enterprise Agent with
read-only named queries, editable drafts and New Chat/History. The owner's
history is private JSON with one file per conversation; reopening does not
create a second file.

`/admin/agent/monitoring` reports metadata-only operational
runs, private session summaries, **explicit** customer feedback counts on a
bounded recent sample and offline golden fixture results. It does not turn
silence into confirmed success and does not duplicate transcript bodies in
MySQL. Review `docs/agent-observability.md` for the trace and
permission contract.
