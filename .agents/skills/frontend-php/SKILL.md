---
name: frontend-php
description: Use when editing public, account, shop, astrologer, temple, cart, checkout, contact, or support templates.
---

# PHP Frontend

- Follow root `AGENTS.md`, `views/AGENTS.md`, and `assets/AGENTS.md` for touched paths.
- Keep UI as PHP-rendered templates plus existing CSS; do not add React, CDN React, SPA shells, or a second frontend.
- Templates should consume controller-provided data and existing services, not read JSON storage directly.
- Match existing theme tokens and classes in `assets/css/band.css`.
- Use browser WebRTC only for call media and the authenticated consultation APIs for polling messages and signaling.
- Validate with `php -l` for changed templates, `php tests/run.php`, and a browser workflow for changed pages.
