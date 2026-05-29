# Sri Panchami Spiritual Platform

Sri Panchami Spiritual is a PHP and JSON-backed web application for devotional ecommerce, Vedic astrology sessions, temple information, and owner-managed admin operations.

## Live Website

- Intended production domain: `https://sripanchamispiritual.com`
- Hosting signal: the domain responds from Hostinger/LiteSpeed.
- Current live status: the root URL currently returns `HTTP 404`, so the production site is not serving the local app correctly yet.
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

- Public pages render locally: home, shop, product detail, cart, checkout, about, contact, temples, astrologers, login, and register.
- Product catalog loads from JSON data and product images point to existing local assets.
- Product detail to cart flow works locally through PHP session cart state.
- Cart quantity, remove, subtotal, free shipping, and checkout review screens render.
- Checkout detects missing Razorpay configuration and blocks live payment.
- Astrologer listing and profile pages render, including remote call/message session pricing.
- Astrologer profile pages no longer show appointment date slots or per-slot booking forms; consultation requests go to the contact form.
- Admin pages render locally, including dashboard, products, categories, coupons, astrologers, appointments, temples, orders, contacts, settings, integrations, shipping, backups, audit log, and project map.
- Contact submissions persist to JSON storage.
- PHP syntax, service tests, route-controller mappings, project map validation, and frontend router/API unit tests pass.

## Pending Or Not Complete

- Production deployment is not complete: `https://sripanchamispiritual.com` currently returns `HTTP 404`.
- Razorpay live keys are not configured locally, so ecommerce payment cannot be completed end to end.
- Google OAuth is scaffolded but depends on configured client credentials and callback setup.
- Customer account pages do not require login before showing order/booking pages; they should redirect guests to `/login`.
- Admin routes currently render without an admin authentication guard.
- Coupon input on cart is a placeholder and shows "Coupon feature coming soon".
- `/sri-panchami-spiritual` is registered in the PHP route map, but local routing serves the SPA fallback and shows the SPA 404 page.
- `/spiritual` is included in `index.php` route detection but has no route in the route registry, so it returns `HTTP 404`.
- `/api/categories` returns `HTTP 404`; the SPA shop page catches this, but the API route should be added or the SPA call removed.
- Real payment verification, order creation with shipping/customer details, Google login, password reset email, and remote consultation payment flow still need live-credential testing.

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

1. Fix production routing first so `https://sripanchamispiritual.com/` serves the app instead of 404.
2. Choose one customer frontend source of truth: PHP templates or the vanilla SPA. Keeping both will continue creating duplicate UI, duplicate cart behavior, and mismatched routes.
3. Add authentication and authorization guards for account and admin routes.
4. Complete Razorpay production setup and verify payment, order persistence, cart clearing, and customer redirect end to end.
5. Complete remote call/message payment handling for text and direct-call sessions.
6. Either implement coupons fully or remove the cart coupon UI until it is ready.
7. Align `index.php`, `ProjectMapService`, SPA routes, and API routes so there are no dead paths.
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
- Browser smoke test on desktop viewport: home, shop, product, cart, checkout, temples, astrologers, login, register, admin, and project map pages render without console errors.
- Product add-to-cart flow works locally.
- Checkout screen renders the selected product and correctly reports that Razorpay is not configured.
- Astrologer profile shows remote consultation details and directs users to the contact form.

Known audit failures:

- Live domain root returns `HTTP 404`.
- `/sri-panchami-spiritual` shows the SPA 404 page locally.
- `/spiritual` returns `HTTP 404`.
- `/api/categories` returns `HTTP 404`.
