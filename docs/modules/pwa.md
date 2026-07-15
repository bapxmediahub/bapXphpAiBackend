---
type: doc
title: PWA Support
description: The public customer app and admin dashboard expose PWA metadata.
category: module
---
# PWA Support

The public customer app and admin dashboard expose PWA metadata. Customer installation is an authenticated account workflow; it is not a floating site control.

## Manifests

| Scope | URL | Source |
|---|---|---|
| Public (`/`) | `/manifest.json` | `assets/pwa/manifest-user.json` |
| Admin (`/admin/`) | `/admin/manifest.json` | Generated in `index.php` |

## Service Workers

| Scope | URL | Precaches |
|---|---|---|
| Public (`/`) | `/sw.js` | `assets/pwa/sw-user.js` — precaches static app metadata only; PHP-rendered pages such as `/shop`, `/cart`, and `/checkout` are network-first so product, cart, and payment UI does not go stale |
| Admin (`/admin/`) | `/admin/sw.js` | Generated — precaches `/admin`, `/login` |

## Customer Installation

Signed-in customers open **Dashboard → Install App**, routed to `/account/dashboard/install`.

- The account menu remains visible whether installation is supported or not.
- Installed mode is detected through `display-mode: standalone` and the iOS standalone flag.
- `beforeinstallprompt` enables an explicit page-level **Install App** command when the browser supports it.
- Safari instructions use **Share → Add to Home Screen**.
- Other unsupported/ineligible browsers receive browser-menu and update guidance instead of a missing button.
- `views/layouts/app.php` registers the customer service worker but renders no floating installation control.

The admin layout retains its separate admin installation control and manifest scope.
