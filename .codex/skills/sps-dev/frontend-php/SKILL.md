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
- Test like a user in the browser after UI changes.

## Validation

```bash
php -l views/public/changed.php
php tools/smoke-local.php
```
