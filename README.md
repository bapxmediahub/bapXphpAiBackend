# Sri Panchami Spiritual Platform

Sri Panchami Spiritual is a full-stack PHP ecommerce and astrology platform for small PHP hosting. It uses PHP templates, PHP controllers, and a JSON-backed backend with local storage under `storage/data/`; there is no SPA, no build step, and no SQL database requirement.

The project is designed to run from `public_html` on hosts such as Hostinger and to be maintained through Git-based agentic development with tools such as Codex, Claude Code, or similar coding agents.

## Documentation

Start here when using or building on this repo:

- [Documentation index](docs/README.md): all guides in one place.
- [Deployment guide](docs/deployment-hostinger.md): Hostinger hPanel, Advanced -> Git, Auto Deployment, branch setup, cron, and Vercel note.
- [Agent workflow](example-Agent.md): instructions for coding agents working in this repo.
- [Architecture](docs/architecture.md): PHP template stack, route flow, JSON persistence, and file structure.
- [Project map](docs/PROJECT_MAP.md): generated route -> controller -> service map.
- [JSON storage](docs/json-storage.md): local JSON collections and persistence model.
- [Admin guide](docs/admin-guide.md): owner/admin surfaces.
- [Product list](docs/product-list.md): current catalog notes.

Page notes:

- [Home](docs/pages/home.md)
- [Shop](docs/pages/shop.md)
- [Checkout](docs/pages/checkout.md)
- [Astrologers](docs/pages/astrologers.md)
- [Temples](docs/pages/temples.md)
- [About](docs/pages/about.md)
- [Admin dashboard](docs/pages/admin-dashboard.md)
- [Integrations](docs/pages/integrations.md)
- [Project map page](docs/pages/project-map.md)

Module notes:

- [Admin](docs/modules/admin.md)
- [Auth](docs/modules/auth.md)
- [Booking](docs/modules/booking.md)
- [Catalog](docs/modules/catalog.md)
- [Google OAuth](docs/modules/google-oauth.md)
- [Orders](docs/modules/orders.md)
- [Razorpay](docs/modules/razorpay.md)
- [Temples](docs/modules/temples.md)

## What This App Includes

- Product catalog, category browsing, product detail pages, cart, checkout, and Razorpay verification flow.
- Remote astrologer marketplace with call/message actions, waitlist/offline states, credit pricing, and account session history.
- Five-star review collection for ended astrology sessions and post-shipment product reviews.
- Temple listing and detail pages.
- Contact and consultation request form.
- Customer account order/session views.
- Owner admin for products, categories, coupons, astrologers, remote session requests, temples, orders, contact submissions, settings, integrations, backups, audit logs, and project map.
- Mail queue for payment confirmation, shipment notification, and delayed product review request emails.
- `.env` admin login support with editable admin credentials from Admin Settings.

## Stack

- Frontend: PHP-rendered templates in `views/`.
- Styling: `assets/css/band.css` plus critical inline layout CSS.
- Backend: PHP controllers, services, and router under `app/`.
- Data: JSON files in `storage/data/`.
- Integrations: Razorpay and Google OAuth scaffolding in `integrations/`.
- Deployment target: PHP hosting with `public_html`.

There is intentionally no SPA fallback. Unknown routes return the PHP 404 page.

## Environment Setup

Edit `.env` before using the app:

```dotenv
APP_URL=https://your-domain.example
ADMIN_USERNAME=admin
ADMIN_EMAIL=admin@your-domain.example
ADMIN_PASSWORD=ChangeThisAdmin123!
```

After first login, change admin credentials in `/admin/settings`.

## Local Development

Run the app:

```bash
php -S 127.0.0.1:6020 index.php
```

Run validation:

```bash
php tests/run.php
php tools/validate-project-map.php
php tools/smoke-local.php
```

Regenerate project-map docs after route or service changes:

```bash
php tools/generate-project-map.php
```

## Deployment

This repository is intended for Hostinger-style PHP hosting:

1. Connect the GitHub repo in Hostinger hPanel under **Advanced** -> **Git**.
2. Select the production branch, normally `main`.
3. Set the install path to `/public_html` when required.
4. Enable **Auto Deployment** for that branch.
5. Keep `storage/` and `storage/data/` writable by PHP.
6. Configure Razorpay, Google OAuth, and SMTP from Admin Integrations.
7. Add cron for queued mail:

```bash
php /home/ACCOUNT/public_html/tools/process-mail-queue.php
```

Full details are in [docs/deployment-hostinger.md](docs/deployment-hostinger.md).

## Agent Development Rules

Agents should:

- Read [example-Agent.md](example-Agent.md) before changing code.
- Use [docs/PROJECT_MAP.md](docs/PROJECT_MAP.md) before editing routes/controllers/services.
- Test locally and in a browser when changing UI.
- Run all validation commands before committing.
- Commit to the branch connected to hosting only after validation passes.

Agents must not reintroduce a SPA, React/CDN app shell, placeholder pages, or a second frontend.

## Current Known Gaps

- Razorpay live payment requires production keys and live payment verification.
- Google OAuth requires configured credentials and callback URL.
- SMTP requires configured secrets and cron for real email delivery.
- Remote call/message credit charging still needs production-grade wallet/session timers.
- Coupon workflow should remain disabled until totals and discount rules are implemented and tested.
