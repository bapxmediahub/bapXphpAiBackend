---
type: doc
title: Admin Module
description: Owns owner-only pages under /admin.
category: module
---
# Admin Module

Owns owner-only pages under `/admin`.

Main files: `AdminController.php`, `views/admin/*`, `views/layouts/admin.php`.

Key checks: every admin route requires `AuthService`, settings persist real values, and list/detail pages render JSON-backed records.
