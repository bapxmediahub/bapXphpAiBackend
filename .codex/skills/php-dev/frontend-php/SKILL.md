---
name: frontend-php
description: Use when editing public, account, shop, astrologer, temple, cart, checkout, contact, or support templates.
---

# PHP Frontend

## Rules

- Use PHP-rendered templates in `views/`.
- Do not add a SPA fallback or second frontend.
- Keep forms posting to PHP controllers.
- Public pages should use real product, astrologer, temple, order, wallet, and support data from services.
- Keep labels, hero copy, CTA priority, and navigation aligned to the current project's primary workflow. Do not leave stale business copy from an older use case.
- Test like a user in the browser after UI changes, including desktop and mobile-sized layouts when the change affects responsive UI.
- Click or inspect every changed CTA, icon button, filter, menu item, and form action. Remove non-working controls or wire them to real PHP routes.
- Search touched templates for placeholders, stale labels, duplicate cards, hidden SPA fallbacks, and disconnected UI before finishing.

## Validation

```bash
php -l views/public/changed.php
php tools/smoke-local.php
```
