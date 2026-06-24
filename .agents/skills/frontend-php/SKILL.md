---
name: frontend-php
description: Use when editing public, account, shop, astrologer, temple, cart, checkout, contact, or support templates.
---

# PHP Frontend

- Follow root `AGENTS.md`, `views/AGENTS.md`, and `assets/AGENTS.md` for touched paths.
- Read and follow root `Design.md` before changing any customer-facing UI. Treat it as the canonical token, typography, component, and responsive contract.
- Keep UI as PHP-rendered templates plus existing CSS; do not add React, CDN React, SPA shells, or a second frontend.
- Templates should consume controller-provided data and existing services, not read JSON storage directly.
- Reuse shared tokens and classes in `assets/css/band.css`; keep its tokens synchronized with the critical CSS in `views/layouts/app.php`.
- Preserve the product's real routes and content. Apply the design system surgically instead of copying reference-product labels or scaffolding parallel components.
- Use browser WebRTC only for call media and the authenticated consultation APIs for polling messages and signaling.
- Reuse the circular top-overlapping marketplace card geometry, face-focused clipped portrait frame, and message/call/profile icon row on home and consult surfaces. Render only real profile metadata/reviews, and keep hero slides isolated on a warm-neutral image frame.
- Validate with `php -l` for changed templates, `php tests/run.php`, and a browser workflow for changed pages. Codex agents should use `Browser:control-in-app-browser` for local UI checks when available; standalone Playwright is only a fallback for agents or environments without the Browser plugin.
