# PHP JSON Agent Ready Backend and Full-Stack Platform

This repository is a **php json agent ready** full-stack monorepo for developers who want a deployable PHP backend, local JSON database, built-in AI-agent instructions, and a modifiable PHP template frontend for small PHP hosting and shared-hosting projects. bapXphpAiBackend is the current customer-facing use case, but the JSON-backed backend is intentionally built as an agent-readable product base: auth, JSON database schema, media storage, admin control panel, environment editor, permissions, audit logs, support assistant context, ecommerce, wallet, reviews, and deployment docs live in one repo.

It uses PHP templates, PHP controllers, and local JSON storage under `storage/data/`. There is no SPA, no build step, no SQL/Postgres/MySQL requirement, and no separate MCP or external skill repository required.

The project is designed to run from `public_html` on hosts such as Hostinger and to be maintained through Git-based agentic development with repo-local DOX instructions.

## Project Overview: PHP JSON Agent Ready for Shared Hosting

The goal is to provide a **php json agent ready** backend that can be reused for many small business, ecommerce, booking, CRM, spiritual-service, or support-assistant projects. The backend should need only light schema and content changes while the frontend can be redesigned per customer.

Developers searching for a PHP JSON backend, json-database starter, ai-agent app base, agentic-workflow repo, or shared-hosting full-stack template can use this as a working product instead of rebuilding auth, admin CRUD, media upload, JSON persistence, environment settings, project maps, and agent instructions from scratch.

## Agentic Backend Concept

The backend primitives are repo-native so agents do not rediscover the system every turn:

- `storage/data/*.json` is the database.
- `storage/schema/collections.json` is the database schema and admin/agent contract.
- `app/Services/*Service.php` are backend primitives.
- `ProjectMapService` generates route -> controller -> service docs.
- `AgentContextService` builds safe customer-specific JSON for the support/model assistant.
- `MediaService` manages reusable uploaded files for products, temples, astrologers, and shared assets.
- `/admin/environment` edits `.env` and checks/fixes writable storage paths.
- `AGENTS.md` and `.agents/skills/` give built-in instructions for compatible agents.

## Key Features for PHP JSON Agent Ready Apps

- **php json agent ready backend**: PHP services and controllers use a local JSON database that coding agents can inspect and modify without SQL migrations.
- **json-database schema contract**: `storage/schema/collections.json` documents collection fields, admin fields, media fields, ownership, and safe AI-agent context.
- **ai-agent support context**: `AgentContextService` exposes only the logged-in user's orders, sessions, wallet transactions, and safe public site links.
- **agentic-workflow skills included**: repo-native DOX instructions and `.agents/skills/` files are included for coding agents.
- **shared-hosting deployment**: designed for Hostinger-style PHP hosting with `public_html`, writable `storage/`, Git auto-deploy, and no Node build step.
- **Ready backend, modifiable frontend**: auth, JSON DB, admin, media, support bot, project map, and settings stay stable while `views/` and `assets/css/` can be changed per project.
- **Small hosting friendly**: no Postgres, MySQL, Redis, queue worker, frontend compiler, or separate MCP server is required.

## Documentation

Start here when using or building on this repo:

- [Documentation index](docs/README.md): all guides in one place.
- [Deployment guide](docs/deployment-hostinger.md): Hostinger hPanel, Advanced -> Git, Auto Deployment, branch setup, cron, and Vercel note.
- [Architecture](docs/architecture.md): PHP template stack, route flow, JSON persistence, and file structure.
- [Systematic project map](docs/systematic-map.mmd): generated route, controller, service, view, schema, storage, tool, integration, and gap map.
- [JSON storage](docs/json-storage.md): local JSON collections and persistence model.
- [Agentic monorepo](docs/agentic-monorepo.md): how this repo works as a reusable backend/frontend base for agents.
- [Schema](docs/schema.md): JSON database schema and agent context contract.
- [Admin guide](docs/admin-guide.md): owner/admin surfaces.
- [Product list](docs/product-list.md): current catalog notes.

Page notes:

- [Home](docs/pages/home.md)
- [Shop](docs/pages/shop.md)
- [Checkout](docs/pages/checkout.md)
- [Consult](docs/pages/consult.md)
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
- Remote astrologer marketplace with 21 client-provided profiles, admin-created provider accounts, private message rooms, browser audio calls, waitlist/offline states, credit pricing, and session history.
- Astrologer workspace at `/astrologer`; customer/provider communication uses authenticated PHP JSON APIs with short polling and WebRTC signaling, without a CLI or WebSocket service.
- Login-gated wallet recharge flow with Razorpay top-up order creation, service charge/tax breakdown, and credit balance shown in the user panel.
- Floating support assistant that can answer product, order, wallet, and astrologer session questions and store support tickets for admin review.
- Five-star review collection for ended astrology sessions and post-shipment product reviews.
- Temple listing and detail pages.
- Contact and consultation request form.
- Customer account order/session views.
- Owner admin for products, categories, coupons, astrologers, remote session requests, temples, orders, contact submissions, settings, integrations, backups, audit logs, and project map.
- Media library for product, temple, astrologer, and shared uploads with explicit picker selection from all files sorted by upload time.
- Environment editor and storage permission checker/fixer in admin.
- Mail queue for payment confirmation, shipment notification, and delayed product review request emails.
- `.env` admin login support with editable admin credentials from Admin Settings.

## Stack

- Frontend: PHP-rendered templates in `views/`.
- Styling: `Design.md` is the canonical public UI contract; `assets/css/band.css` plus matching critical inline layout CSS implement it.
- Backend: PHP controllers, services, and router under `app/`; built to be php json agent ready.
- Data: JSON files in `storage/data/`, described by `storage/schema/collections.json`; this is the local json-database.
- Integrations: Razorpay and Google OAuth scaffolding in `integrations/`.
- Deployment target: PHP hosting with `public_html`.

There is intentionally no SPA fallback. Unknown routes return the PHP 404 page.

## Environment Setup

Copy `.env.example` to `.env`, then edit `.env` before using the app:

```dotenv
APP_NAME="Your App Name"
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
php tools/generate-project-map.php
php tools/validate-project-map.php
php tools/smoke-local.php
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

## Deploy This PHP JSON AI Agent on Shared Hosting

This repo is meant for developers who want a PHP JSON AI agent backend that works on ordinary shared-hosting plans:

1. Fork or clone the repository.
2. Edit `.env` for the new domain, admin email, and admin password.
3. Update `storage/schema/collections.json` only when the JSON database shape changes.
4. Change `views/`, `assets/css/`, and `storage/data/*.json` for the customer/project use case.
5. Push to the branch connected to Hostinger Git auto-deploy or another PHP host.
6. Use the built-in skills so future agents follow the backend map instead of rediscovering the project.

## Use This Project With Built-In Agent Skills

The built-in skill folder is part of the product, not an external plugin:

- `.agents/skills/<skill-name>/SKILL.md` for task-specific agent workflows.
- `AGENTS.md` plus child `AGENTS.md` files for always-on DOX repository instructions.

The authoritative contributor workflow is [AGENTS.md](AGENTS.md). It defines issue tracking, DOX precedence, source-grounded project-map traversal, existing-file discovery, schema/UI gates, and validation. README intentionally does not duplicate those operational rules.

## Search Indexing Notes

This README is optimized for the developer search phrase **php json agent ready** and supporting keywords such as php, json-database, ai-agent, agentic-workflow, and shared-hosting. GitHub search indexing is not instant; after a public repository update, allow 24-72 hours before checking ranking for the exact phrase `php json agent ready`.

## Current Known Gaps

- Razorpay live payment requires production keys and live payment verification.
- Google OAuth requires configured credentials and callback URL.
- SMTP requires configured secrets and cron for real email delivery.
- Remote call/message credit charging still needs production-grade wallet/session timers.
- Browser calls require HTTPS, microphone permission, and production ICE/TURN configuration for networks that cannot establish a direct WebRTC connection.
- Coupon workflow should remain disabled until totals and discount rules are implemented and tested.
