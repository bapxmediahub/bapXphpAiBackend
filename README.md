# Sri Panchami Spiritual Platform

A full-stack web application for devotional ecommerce, direct astrologer call sessions, text sessions, and temple management.

## Tech Stack

**Frontend:**
- Vanilla JavaScript for the storefront app
- PHP templates for public, account, and admin pages
- CSS (band.css) for styling

**Backend:**
- PHP for server-side logic and API handling
- JSON file-based storage (no database required)

## Local Development

```bash
# Start PHP development server
php -S 127.0.0.1:6020 index.php

# Run tests
php tests/run.php

# Generate project map
php tools/generate-project-map.php

# Validate project map
php tools/validate-project-map.php
```

## Structure

- `app/` - PHP routing, controllers, and services
- `views/` - PHP templates for public, account, and admin pages
- `assets/` - CSS, images, and static files
- `storage/data/` - JSON collections (products, orders, users, etc.)
- `tools/` - Project map generation and validation
- `docs/` - Architecture, deployment guides, and documentation
- `integrations/` - Third-party services (Google OAuth and Razorpay)

## Pricing Rules

- `1 rupee = 20 credits`
- `1 credit = Rs. 0.05`
- Minimum credit top-up: `Rs. 10`
- Text astrology session: `5 credits per user message`
- Direct call session: `0.5 credits per second`
- Credits are only for astrologer text and call sessions. They cannot be used for ecommerce products.
- Ecommerce products use direct card or UPI payment through Razorpay. Cash on delivery is not available.

## Product Catalog

The current ecommerce product list is documented in `docs/product-list.md`.
