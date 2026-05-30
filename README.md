# Sri Panchami Spiritual Platform

Sri Panchami Spiritual is a PHP and JSON-backed web application for devotional ecommerce, Vedic astrology sessions, temple information, and owner-managed admin operations.

## Live Website

- Intended production domain: `https://sripanchamispiritual.com`
- Hosting signal: the domain responds from Hostinger/LiteSpeed.
- Current live status: the root URL loads, but the live deployment is behind local `main`; `/astrologers` still uses text call/chat buttons instead of the latest round icon actions, and `/forgot-password` returns fallback content instead of the local reset-request page.
- Local validated URL: `http://127.0.0.1:6021` during the latest audit.

## Project Objective

The app should let customers buy authentic spiritual products, browse temples, request remote astrologer text or direct-call sessions through Sri Panchami Spiritual, and let the owner manage catalog, astrologers, contact requests, orders, shipping, integrations, backups, and audit data without a database.

The durable architecture constraint is:

- Keep the backend in PHP.
- Keep persistence in JSON files under `storage/data/`.
- Do not replace the backend with a database or external CMS unless that becomes a separate project decision.

## Current Tech Stack

**Frontend**

- PHP-rendered public, account, and admin templates in `views/`.
- Vanilla JavaScript SPA files in `assets/js/` for fallback/SPA routes.
- Shared styling in `assets/css/band.css`.

**Backend**

- PHP routing, controllers, and services in `app/`.
- JSON file storage in `storage/data/`.
- Razorpay and Google OAuth integration scaffolding in `integrations/`.

## What Is Done And Working

- Public pages render locally: home, shop, product detail, cart, checkout, about, contact, temples, astrologers, spiritual, login, register, forgot password, and reset password.
- Product catalog loads from JSON data and product images point to existing local assets.
- Product detail to cart flow works locally through PHP session cart state.
- Cart quantity, remove, subtotal, free shipping, and checkout review screens render.
- Checkout detects missing Razorpay configuration and blocks live payment.
- Astrologer listing now follows the call/message marketplace pattern from competitor references: credit balance, recharge CTA, filters, search, status dots, ratings, and `CHAT`/`CALL`/`JOIN Q`/`OFFLINE` actions.
- Astrologer listing and profile actions use round icon-only controls for message and call instead of large text buttons.
- Astrologer profile pages now show a remote action panel with credit pricing, message/call icon controls, support-assisted `BOOK SESSION`, ratings, trust points, gifts, reviews, and no appointment date slots or per-slot booking forms.
- Ended astrology sessions can collect a five-star review from account bookings, and astrologer ratings are calculated from saved reviews.
- Product pages show product star rating summaries, and shipped/delivered orders can collect product reviews only after `review_request_after_at` is due.
- Payment confirmation, shipment notification, and delayed product review request emails are queued to JSON in `mail_queue`.
- Admin order status updates can mark orders as shipped/delivered, set `review_request_after_at` 10 days after shipment, and queue the shipment plus product-review emails.
- `php tools/process-mail-queue.php` can be run by cron to send due queued emails after SMTP secrets are configured.
- Consultation support requests go to the contact form with the astrology subject selected.
- Account pages require a signed-in user before showing order or booking history.
- Admin pages require an admin role. On a fresh JSON install, the first local email/password registration becomes the owner admin account and later registrations default to customer.
- Review submissions require a signed-in user before astrologer or product ratings can be saved.
- Admin pages render locally, including dashboard, products, categories, coupons, astrologers, appointments, temples, orders, order detail, contacts, settings, integrations, shipping, backups, audit log, and project map.
- Admin settings persist shipping mode, flat rate, currency, and timezone to JSON settings.
- `/sri-panchami-spiritual`, `/spiritual`, and `/api/categories` are routed locally and no longer fall through to missing pages.
- Cart no longer exposes the unfinished coupon input.
- Contact submissions persist to JSON storage.
- PHP syntax, service tests, route-controller mappings, project map validation, and frontend router/API unit tests pass.

## Pending Or Not Complete

- Production deployment is behind local code: `https://sripanchamispiritual.com/` loads in the browser, but `/astrologers` still shows text action buttons and does not yet include the latest local icon-button and guarded review flow.
- Razorpay live keys are not configured locally, so ecommerce payment cannot be completed end to end.
- Google OAuth is scaffolded but depends on configured client credentials and callback setup.
- Real payment verification, order creation with shipping/customer details, Google login, password reset email, and remote consultation payment flow still need live-credential testing.
- SMTP credentials and production cron are not configured locally, so queued customer emails cannot be sent end to end yet.

## Duplicate Or Conflicting Areas

- There are two frontend implementations for many screens:
  - PHP templates in `views/public/`.
  - Vanilla SPA pages in `assets/js/pages.js`.
- The main routed app currently uses PHP templates for the primary public routes, while unknown routes fall back to the SPA.
- JavaScript components are duplicated across:
  - `assets/js/components.js`
  - `assets/js/ui/components.js`
  - root `components/*.js`
- Core JS helpers are duplicated across:
  - `assets/js/app.js`
  - `assets/js/core/app-core.js`
  - `utils/api.js`
  - `utils/router.js`
- Documentation still has stale React wording in `docs/architecture.md` and `docs/deployment-hostinger.md`, while the current app is PHP templates plus vanilla JavaScript.

## What Needs Optimization Against The Objective

1. Deploy the latest local `main` build to production so the live site matches verified local behavior.
2. Choose one customer frontend source of truth: PHP templates or the vanilla SPA. Keeping both will continue creating duplicate UI, duplicate cart behavior, and mismatched routes.
3. Complete Razorpay production setup and verify payment, order persistence, cart clearing, and customer redirect end to end.
4. Complete remote call/message payment handling for text and direct-call sessions.
5. Replace the placeholder balance/recharge UI with real wallet state, top-up checkout, insufficient-credit handling, session timers, and queue status from backend data.
6. Configure SMTP secrets and production cron for `php tools/process-mail-queue.php`, then verify payment, shipment, and delayed review emails on the live host.
7. Implement coupons only when there is a real discount workflow and tests for totals.
8. Remove unused duplicate JS modules after the frontend direction is finalized.
9. Update stale docs that still describe a React/CDN architecture.
10. Add browser-level end-to-end tests for product purchase, remote consultation contact request, login/register, contact form, and admin resource edits.

## Local Development

```bash
php -S 127.0.0.1:6020 index.php
```

If port `6020` is already in use:

```bash
php -S 127.0.0.1:6021 index.php
```

Run validation:

```bash
php tests/run.php
php tools/validate-project-map.php
```

Generate project map:

```bash
php tools/generate-project-map.php
```

## Latest Audit Results

The latest local audit verified:

- `php tests/run.php`: passing.
- `php tools/validate-project-map.php`: passing.
- Registered local public GET routes return `HTTP 200`; guest account and admin routes now redirect with `HTTP 302` to enforce authentication.
- API smoke test verified valid JSON for `/api/`, `/api/shop`, `/api/categories`, `/api/product/karuppasami-dollar`, `/api/astrologers`, and `/api/temples`.
- Browser smoke test on desktop viewport: public shopping and astrologer pages render; guest account/admin pages redirect instead of exposing private data.
- Headless Google Chrome screenshots were captured for local astrologer list/profile, live astrologer list, and an Astroyogi competitor reference. Local matches the requested round icon call/message direction; live still shows text action buttons.
- Product add-to-cart flow works locally.
- Checkout screen renders the selected product and correctly reports that Razorpay is not configured.
- Astrologer profile shows remote consultation details and directs users to the contact form.
- Astrologer list/profile use icon-only message and call actions locally.
- Product and astrologer review storage and average rating calculation work locally.
- Mail queue service stores payment, shipment, and 10-day delayed product-review request emails; due-mail selection and the cron processor script are covered by tests.
- Local route gaps for `/sri-panchami-spiritual`, `/spiritual`, and `/api/categories` are fixed.
- Admin settings and admin list/detail pages are data-backed instead of placeholder-only screens.

Known audit failures:

- Live domain is behind the latest local code: `/astrologers` still shows old text actions, and `/forgot-password` returns fallback content rather than the local reset-request page.
