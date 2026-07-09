# PWA Support

All three role dashboards have PWA install support: admin, astrologer, customer.

## Manifests

| Scope | URL | Source |
|---|---|---|
| Public (`/`) | `/manifest.json` | `assets/pwa/manifest-user.json` |
| Admin (`/admin/`) | `/admin/manifest.json` | Generated in `index.php` |
| Astrologer (`/astrologer/`) | `/astrologer/manifest.json` | Generated in `index.php` |

## Service Workers

| Scope | URL | Precaches |
|---|---|---|
| Public (`/`) | `/sw.js` | `assets/pwa/sw-user.js` — precaches static app metadata only; PHP-rendered pages such as `/shop`, `/cart`, and `/checkout` are network-first so product, cart, and payment UI does not go stale |
| Admin (`/admin/`) | `/admin/sw.js` | Generated — precaches `/admin`, `/login` |
| Astrologer (`/astrologer/`) | `/astrologer/sw.js` | Generated — precaches `/astrologer`, `/login` |

## Install Button

A fixed bottom-right "Install App" button (`pwa-install-btn`) appears on both layouts:

- `views/layouts/app.php` — customer and astrologer pages
- `views/layouts/admin.php` — admin pages

Behavior:
- Listens for `beforeinstallprompt` — only shows when browser supports PWA install.
- On click, triggers the native browser install prompt.
- Auto-hides after install completes.
- Styled via `assets/css/band.css` `.pwa-install-btn` class using design tokens (`--color-maroon`, `--shadow-md`, `--radius-pill`).
