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
php -S 127.0.0.1:8000

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
